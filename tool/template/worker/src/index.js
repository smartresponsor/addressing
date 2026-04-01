// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

function te() { return new TextEncoder(); }

function toHex(bytes) {
  const b = new Uint8Array(bytes);
  let out = "";
  for (let i = 0; i < b.length; i++) out += b[i].toString(16).padStart(2, "0");
  return out;
}

function safeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string") return false;
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

async function hmacHex(secret, data) {
  const key = await crypto.subtle.importKey(
    "raw",
    te().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const sig = await crypto.subtle.sign("HMAC", key, te().encode(data));
  return toHex(sig);
}

function envStr(env, key, def) {
  const v = env && env[key];
  if (typeof v !== "string") return def;
  const s = v.trim();
  return s === "" ? def : s;
}

function envInt(env, key, def) {
  const v = envStr(env, key, "");
  if (!v) return def;
  const n = Number(v);
  return Number.isFinite(n) ? n : def;
}

function json(obj, status = 200) {
  return new Response(JSON.stringify(obj, null, 2), {
    status,
    headers: { "content-type": "application/json; charset=utf-8" }
  });
}

function bad(status, code, message) {
  return json({ ok: false, code, message }, status);
}

function normalizeKid(raw) {
  const s = String(raw || "").trim().toUpperCase();
  const kid = s === "" ? "K1" : s;
  if (!/^K[1-9]\d*$/.test(kid)) return null;
  if (kid.length > 6) return null;
  return kid;
}

function secretForKid(env, kid) {
  const v = envStr(env, `SR_TRIGGER_SECRET_${kid}`, "");
  if (v) return v;

  // legacy fallback only for K1
  if (kid === "K1") {
    const legacy = envStr(env, "SR_TRIGGER_SECRET", "");
    if (legacy) return legacy;
  }
  return "";
}

function detectKids(env) {
  const out = [];
  for (const k of ["K1", "K2", "K3"]) {
    if (envStr(env, `SR_TRIGGER_SECRET_${k}`, "")) out.push(k);
  }
  if (out.length === 0 && envStr(env, "SR_TRIGGER_SECRET", "")) out.push("K1");
  return out;
}

function allowedTask(env) {
  const raw = envStr(env, "SR_ALLOWED_TASK", "scan,health,doctor,validate,plan,codex,pr");
  return raw.split(",").map(s => s.trim()).filter(Boolean);
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === "/health" || url.pathname === "/health/") {
      const repoName = envStr(env, "GH_REPO", "unknown");
      return json({
        ok: true,
        service: `${repoName}-agent-trigger`,
        kid: { supported: detectKids(env), default: "K1" }
      }, 200);
    }

    if (url.pathname !== "/dispatch" && url.pathname !== "/dispatch/") {
      return bad(404, "NotFound", "Use /dispatch or /health");
    }

    if (request.method !== "POST") {
      return bad(405, "MethodNotAllowed", "POST required");
    }

    const kid = normalizeKid(request.headers.get("X-SR-Kid"));
    if (!kid) return bad(400, "BadKid", "X-SR-Kid must be like K1, K2, ...");

    const secret = secretForKid(env, kid);
    if (!secret) return bad(500, "Misconfig", `Secret not set: SR_TRIGGER_SECRET_${kid}`);

    const tsHeader = request.headers.get("X-SR-Timestamp") || "";
    const sigHeader = (request.headers.get("X-SR-Signature") || "").toLowerCase();

    const ts = Number(tsHeader);
    if (!Number.isFinite(ts) || ts <= 0) {
      return bad(401, "BadTimestamp", "X-SR-Timestamp required (unix seconds)");
    }

    const skew = envInt(env, "SR_TIME_SKEW_SEC", 300);
    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - ts) > skew) {
      return bad(401, "TimestampSkew", "Timestamp outside allowed window");
    }

    const rawBody = await request.text();
    const expected = await hmacHex(secret, `${ts}.${rawBody}`);
    if (!safeEqual(expected, sigHeader)) return bad(403, "BadSignature", "Signature mismatch");

    let payload;
    try {
      payload = rawBody.trim() ? JSON.parse(rawBody) : {};
    } catch {
      return bad(400, "BadJson", "Body must be JSON");
    }

    const task = String(payload.task || "").trim();
    const allowed = allowedTask(env);
    if (!task) return bad(400, "BadTask", "task is required");
    if (!allowed.includes(task)) return bad(400, "BadTask", `task not allowed: ${task}`);

    const owner = envStr(env, "GH_OWNER", "");
    const repo = envStr(env, "GH_REPO", "");
    const workflow = envStr(env, "GH_WORKFLOW", "");
    const token = envStr(env, "GH_TOKEN", "");
    const refDefault = envStr(env, "GH_REF", "master");
    const ghApiVersion = envStr(env, "GH_API_VERSION", "2022-11-28");

    if (!owner || !repo || !workflow || !token) {
      return bad(500, "Misconfig", "GH_OWNER/GH_REPO/GH_WORKFLOW/GH_TOKEN must be set");
    }

    const ref = String(payload.ref || refDefault).trim() || refDefault;
    const inputs = (payload && typeof payload.inputs === "object" && payload.inputs) ? payload.inputs : {};
    inputs.task = task;

    const ghUrl = `https://api.github.com/repos/${owner}/${repo}/actions/workflows/${workflow}/dispatches`;
    const ghRes = await fetch(ghUrl, {
      method: "POST",
      headers: {
        Accept: "application/vnd.github+json",
        Authorization: `Bearer ${token}`,
        "X-GitHub-Api-Version": ghApiVersion,
        "User-Agent": `sr-${repo}-agent-trigger`
      },
      body: JSON.stringify({ ref, inputs })
    });

    if (ghRes.status === 204) {
      return json({ ok: true, dispatched: true, repo: `${owner}/${repo}`, workflow, ref, task, kid }, 200);
    }

    const text = await ghRes.text();
    return json({
      ok: false,
      dispatched: false,
      status: ghRes.status,
      repo: `${owner}/${repo}`,
      workflow,
      ref,
      task,
      kid,
      github: text.slice(0, 2000)
    }, ghRes.status);
  }
};

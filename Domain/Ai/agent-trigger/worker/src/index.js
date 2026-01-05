// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

function textEncoder() {
  return new TextEncoder();
}

function toHex(bytes) {
  const b = new Uint8Array(bytes);
  let out = "";
  for (let i = 0; i < b.length; i++) {
    out += b[i].toString(16).padStart(2, "0");
  }
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
    textEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const sig = await crypto.subtle.sign("HMAC", key, textEncoder().encode(data));
  return toHex(sig);
}

function envInt(env, key, def) {
  const v = env[key];
  if (typeof v !== "string" || v.trim() === "") return def;
  const n = Number(v);
  return Number.isFinite(n) ? n : def;
}

function envStr(env, key, def) {
  const v = env[key];
  if (typeof v !== "string" || typeof v.trim !== "function") return def;
  if (v.trim() === "") return def;
  return v;
}

function parseAllowedTask(env) {
  const raw = envStr(env, "SR_ALLOWED_TASK", "scan,health,doctor,validate,plan,codex");
  return raw.split(",").map(s => s.trim()).filter(Boolean);
}

function json(status, obj) {
  return new Response(JSON.stringify(obj, null, 2), {
    status,
    headers: { "content-type": "application/json; charset=utf-8" }
  });
}

function bad(status, code, message) {
  return json(status, { ok: false, code, message });
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === "/health") {
      return json(200, { ok: true, service: "address-agent-trigger" });
    }

    if (url.pathname !== "/dispatch") {
      return bad(404, "NotFound", "Use /dispatch or /health");
    }

    if (request.method !== "POST") {
      return bad(405, "MethodNotAllowed", "POST required");
    }

    const secret = envStr(env, "SR_TRIGGER_SECRET", "");
    if (!secret) return bad(500, "Misconfig", "SR_TRIGGER_SECRET not set");

    const tsHeader = request.headers.get("X-SR-Timestamp") || "";
    const sigHeader = (request.headers.get("X-SR-Signature") || "").toLowerCase();

    const ts = Number(tsHeader);
    if (!Number.isFinite(ts) || ts <= 0) return bad(401, "BadTimestamp", "X-SR-Timestamp required (unix seconds)");

    const skew = envInt(env, "SR_TIME_SKEW_SEC", 300);
    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - ts) > skew) return bad(401, "TimestampSkew", "Timestamp outside allowed window");

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
    const allowed = parseAllowedTask(env);
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
        "Accept": "application/vnd.github+json",
        "Authorization": `Bearer ${token}`,
        "X-GitHub-Api-Version": ghApiVersion,
        "User-Agent": "sr-address-agent-trigger"
      },
      body: JSON.stringify({ ref, inputs })
    });

    if (ghRes.status === 204) {
      return json(200, { ok: true, dispatched: true, repo: `${owner}/${repo}`, workflow, ref, task });
    }

    const text = await ghRes.text();
    return json(ghRes.status, {
      ok: false,
      dispatched: false,
      status: ghRes.status,
      repo: `${owner}/${repo}`,
      workflow,
      ref,
      task,
      github: text.slice(0, 2000)
    });
  }
};

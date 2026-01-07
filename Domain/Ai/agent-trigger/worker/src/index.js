// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

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

function envStr(env, key, def = "") {
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

function parseAllowedTask(env) {
  const raw = envStr(env, "SR_ALLOWED_TASK", "scan,health,doctor,validate,plan,codex,pr");
  return raw.split(",").map((s) => s.trim()).filter(Boolean);
}

async function hmacHex(secret, data) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
      "raw",
      enc.encode(secret),
      { name: "HMAC", hash: "SHA-256" },
      false,
      ["sign"]
  );
  const sig = await crypto.subtle.sign("HMAC", key, enc.encode(data));
  return toHex(sig);
}

function json(obj, status = 200, extraHeaders = {}) {
  return new Response(JSON.stringify(obj, null, 2), {
    status,
    headers: { "content-type": "application/json; charset=utf-8", ...extraHeaders },
  });
}

function bad(status, code, message, extra = {}) {
  return json({ ok: false, code, message, ...extra }, status);
}

// Rotation model:
// - clients send header X-SR-Kid: K1 / K2
// - worker reads SR_TRIGGER_SECRET_K1 / SR_TRIGGER_SECRET_K2
// - fallback for old clients: SR_TRIGGER_SECRET (no kid)
function pickSecret(env, kidHeaderRaw) {
  const kidHeader = (kidHeaderRaw || "").trim().toUpperCase();

  if (kidHeader) {
    const byKid = envStr(env, `SR_TRIGGER_SECRET_${kidHeader}`, "");
    if (byKid) return { kid: kidHeader, secret: byKid, mode: "kid" };
  }

  const k1 = envStr(env, "SR_TRIGGER_SECRET_K1", "");
  if (k1) return { kid: "K1", secret: k1, mode: "default_k1" };

  const legacy = envStr(env, "SR_TRIGGER_SECRET", "");
  if (legacy) return { kid: "", secret: legacy, mode: "legacy" };

  return { kid: kidHeader || "K1", secret: "", mode: "missing" };
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === "/" || url.pathname === "") {
      return json({ ok: true, service: `${envStr(env, "GH_REPO", "unknown")}-agent-trigger` }, 200);
    }

    if (url.pathname === "/health" || url.pathname === "/health/") {
      const repoName = envStr(env, "GH_REPO", "unknown");
      return json({ ok: true, service: `${repoName}-agent-trigger` }, 200);
    }

    if (url.pathname !== "/dispatch") {
      return bad(404, "NotFound", "Use /dispatch or /health");
    }

    if (request.method !== "POST") {
      return bad(405, "MethodNotAllowed", "POST required");
    }

    const kidHeader = request.headers.get("X-SR-Kid") || "";
    const picked = pickSecret(env, kidHeader);

    if (!picked.secret) {
      return bad(500, "Misconfig", "Trigger secret not set (SR_TRIGGER_SECRET_K1/K2 or legacy SR_TRIGGER_SECRET)");
    }

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
    const expected = await hmacHex(picked.secret, `${ts}.${rawBody}`);
    if (!safeEqual(expected, sigHeader)) {
      return bad(403, "BadSignature", "Signature mismatch", { kid: picked.kid, mode: picked.mode });
    }

    let payload = {};
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

    const inputs =
        payload && typeof payload.inputs === "object" && payload.inputs ? payload.inputs : {};
    inputs.task = task;

    const ghUrl = `https://api.github.com/repos/${owner}/${repo}/actions/workflows/${workflow}/dispatches`;

    let ghRes;
    try {
      ghRes = await fetch(ghUrl, {
        method: "POST",
        headers: {
          Accept: "application/vnd.github+json",
          Authorization: `Bearer ${token}`,
          "X-GitHub-Api-Version": ghApiVersion,
          "User-Agent": `sr-${repo}-agent-trigger`,
        },
        body: JSON.stringify({ ref, inputs }),
      });
    } catch (e) {
      return bad(502, "GitHubDispatchFailed", "GitHub dispatch request failed", {
        error: String(e && e.message ? e.message : e),
      });
    }

    if (ghRes.status === 204) {
      return json(
          {
            ok: true,
            dispatched: true,
            repo: `${owner}/${repo}`,
            workflow,
            ref,
            task,
            kid: picked.kid,
            mode: picked.mode,
          },
          200
      );
    }

    const text = await ghRes.text();
    return json(
        {
          ok: false,
          dispatched: false,
          status: ghRes.status,
          repo: `${owner}/${repo}`,
          workflow,
          ref,
          task,
          kid: picked.kid,
          mode: picked.mode,
          github: text.slice(0, 2000),
        },
        ghRes.status >= 200 && ghRes.status <= 599 ? ghRes.status : 502
    );
  },
};

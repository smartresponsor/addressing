// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

function toHex(bytes) {
  return [...new Uint8Array(bytes)]
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

async function sha256Hex(str) {
  const buf = new TextEncoder().encode(str);
  const hash = await crypto.subtle.digest("SHA-256", buf);
  return toHex(hash);
}

async function hmacSha256Hex(secret, str) {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );

  const sig = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(str),
  );

  return toHex(sig);
}

function constantTimeEqual(a, b) {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

function normalizeSecret(secret) {
  if (!secret) return "";
  let s = secret.trim();
  if (
    (s.startsWith('"') && s.endsWith('"')) ||
    (s.startsWith("'") && s.endsWith("'"))
  ) {
    s = s.slice(1, -1);
  }
  return s.trim();
}

function json(status, obj) {
  return new Response(JSON.stringify(obj, null, 2), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value || ""), 10);
  if (Number.isFinite(parsed) && parsed > 0) return parsed;
  return fallback;
}

function normalizeRef(ref) {
  if (!ref) return "";
  if (ref.startsWith("refs/heads/")) {
    return ref.slice("refs/heads/".length);
  }
  return ref;
}

async function checkNonce(env, nonce) {
  const ttl = parsePositiveInt(env.SR_NONCE_TTL_SEC, 600);
  const id = env.SR_NONCE_GUARD.idFromName("global");
  const stub = env.SR_NONCE_GUARD.get(id);
  const res = await stub.fetch("https://nonce.guard/check", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ nonce, ttl }),
  });

  if (res.status === 409) {
    return { ok: false, status: 409, code: "Replay" };
  }

  if (!res.ok) {
    return { ok: false, status: 500, code: "NonceGuardError" };
  }

  return { ok: true };
}

async function dispatchWorkflow(env, payload, debugInfo) {
  const owner = env.GH_OWNER;
  const repo = env.GH_REPO;
  const workflow = env.GH_WORKFLOW;
  const token = env.GH_TOKEN;
  const ref = payload.ref || env.GH_REF;

  if (!owner || !repo || !workflow) {
    return json(500, { ok: false, code: "DispatchConfigMissing" });
  }

  if (!token) {
    return json(500, { ok: false, code: "TokenNotConfigured" });
  }

  const inputs = { task: payload.task, ...(payload.inputs || {}) };
  const dispatchBody = JSON.stringify({ ref, inputs });

  const dispatchRes = await fetch(
    `https://api.github.com/repos/${owner}/${repo}/actions/workflows/${workflow}/dispatches`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/vnd.github+json",
        "User-Agent": "addressing-agent-trigger",
      },
      body: dispatchBody,
    },
  );

  if (dispatchRes.status !== 204) {
    const bodyText = await dispatchRes.text();
    return json(dispatchRes.status, {
      ok: false,
      code: "DispatchFailed",
      status: dispatchRes.status,
      body: bodyText,
      debug: debugInfo,
    });
  }

  const run = await findLatestRun(env, ref, token);
  const response = { ok: true, dispatched: true };
  if (run) {
    response.runId = run.id;
    response.runUrl = run.html_url;
  }
  return json(200, response);
}

async function findLatestRun(env, ref, token) {
  const owner = env.GH_OWNER;
  const repo = env.GH_REPO;
  const workflow = env.GH_WORKFLOW;
  if (!owner || !repo || !workflow || !token) {
    return null;
  }

  const branch = normalizeRef(ref || env.GH_REF);
  const params = new URLSearchParams({
    event: "workflow_dispatch",
    branch,
  });
  const runsRes = await fetch(
    `https://api.github.com/repos/${owner}/${repo}/actions/workflows/${workflow}/runs?${params}`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/vnd.github+json",
        "User-Agent": "addressing-agent-trigger",
      },
    },
  );

  if (!runsRes.ok) {
    return null;
  }

  const data = await runsRes.json();
  const runs = Array.isArray(data.workflow_runs) ? data.workflow_runs : [];
  const now = Date.now();
  for (const run of runs) {
    if (run.event !== "workflow_dispatch") continue;
    if (branch && run.head_branch && run.head_branch !== branch) continue;
    const createdAt = Date.parse(run.created_at);
    if (!Number.isFinite(createdAt)) continue;
    if (now - createdAt > 60_000) continue;
    return run;
  }

  return null;
}

export class SrNonceGuard {
  constructor(state) {
    this.state = state;
  }

  async fetch(request) {
    if (request.method !== "POST") {
      return new Response("Method Not Allowed", { status: 405 });
    }

    let payload;
    try {
      payload = await request.json();
    } catch {
      return json(400, { ok: false, code: "BadNonce" });
    }

    const nonce = String(payload?.nonce || "").trim();
    if (!nonce) {
      return json(400, { ok: false, code: "BadNonce" });
    }

    const ttl = parsePositiveInt(payload?.ttl, 600);
    const existing = await this.state.storage.get(nonce);
    if (existing) {
      return json(409, { ok: false, code: "Replay" });
    }

    await this.state.storage.put(nonce, Date.now(), { expirationTtl: ttl });
    return json(200, { ok: true });
  }
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    if (url.pathname === "/health" && request.method === "GET") {
      return json(200, { ok: true });
    }

    if (request.method !== "POST") {
      return new Response("Method Not Allowed", { status: 405 });
    }

    const kid = (request.headers.get("X-SR-Kid") || "").toUpperCase();
    const tsHeader = request.headers.get("X-SR-Timestamp") || "";
    const ts = Number(tsHeader);
    const sig = (request.headers.get("X-SR-Signature") || "").toLowerCase();
    const nonce = (request.headers.get("X-SR-Nonce") || "").trim();

    if (!nonce) {
      return json(401, { ok: false, code: "BadNonce" });
    }

    if (!/^K\d+$/.test(kid) || !Number.isFinite(ts)) {
      return json(401, { ok: false, code: "BadAuthHeader" });
    }

    const secret = normalizeSecret(env[`SR_TRIGGER_SECRET_${kid}`]);
    if (!secret) {
      return json(500, { ok: false, code: "SecretNotConfigured", kid });
    }

    const rawBody = await request.text();
    const bodyHash = await sha256Hex(rawBody);
    const signed = `${ts}.${bodyHash}`;
    const expected = await hmacSha256Hex(secret, signed);

    if (!constantTimeEqual(expected, sig)) {
      const res = { ok: false, code: "BadSignature", kid };
      if (env.SR_DEBUG === "1") {
        res.debug = {
          ts,
          sig,
          expected,
          bodyHash,
          signed,
          secretSha256: await sha256Hex(secret),
          rawBody,
        };
      }
      return json(403, res);
    }

    const nonceCheck = await checkNonce(env, nonce);
    if (!nonceCheck.ok) {
      return json(nonceCheck.status, {
        ok: false,
        code: nonceCheck.code,
      });
    }

    let payload;
    try {
      payload = JSON.parse(rawBody || "{}");
    } catch {
      return json(400, { ok: false, code: "BadJson" });
    }

    return dispatchWorkflow(env, payload, env.SR_DEBUG === "1" ? { ts, kid } : undefined);
  },
};

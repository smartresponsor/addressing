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

export default {
  async fetch(request, env) {
    if (request.method !== "POST") {
      return new Response("Method Not Allowed", { status: 405 });
    }

    const kid = (request.headers.get("X-SR-Kid") || "").toUpperCase();
    const ts = Number(request.headers.get("X-SR-Timestamp"));
    const sig = (request.headers.get("X-SR-Signature") || "").toLowerCase();

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

    return json(200, { ok: true, verified: true });
  },
};

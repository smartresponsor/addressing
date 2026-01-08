// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

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

function envInt(env, key, def) {
  const v = env && env[key];
  if (typeof v !== "string" || v.trim() === "") return def;
  const n = Number(v);
  return Number.isFinite(n) ? n : def;
}

function envStr(env, key, def) {
  const v = env && env[key];
  if (typeof v !== "string") return def;
  const s = v.trim();
  return s === "" ? def : s;
}

function parseCsvUpper(raw) {
  return String(raw || "")
      .split(",")
      .map((s) => s.trim().toUpperCase())
      .filter(Boolean);
}

function parseAllowedTask(env) {
  const raw = envStr(env, "SR_ALLOWED_TASK", "scan,health,doctor,validate,plan,codex,pr");
  return raw.split(",").map((s) => s.trim()).filter(Boolean);
}

function parseAllowedKid(env) {
  const raw = envStr(env, "SR_TRIGGER_ALLOWED_KID", "K1,K2");
  const kids = parseCsvUpper(raw).filter((k) => /^K\d+$/.test(k));
  return kids.length ? kids : ["K1"];
}

function json(obj, status = 200) {
  return new Response(JSON.stringify(obj, null, 2), {
    status,
    headers: { "content-type": "application/json; charset=utf-8" },
  });
}

function bad(status, code, message, extra = undefined) {
  const body = { ok: false, code, message };
  if (extra && typeof extra === "object") Object.assign(body, extra);
  return json(body, status);
}

function pickKidCandidates(env, headerKid) {
  const allowed = parseAllowedKid(env);
  const wanted = String(headerKid || "").trim().toUpperCase();
  if (wanted) {
    if (!/^K\d+$/.test(wanted)) return { error: "BadKid", candidates: [] };
    if (!allowed.includes(wanted)) return { error: "KidNotAllowed", candidates: [] };
    return { error: "", candidates: [wanted] };
  }

  const defKid = envStr(env, "SR_TRIGGER_DEFAULT_KID", allowed[0]).trim().toUpperCase();
  const def = allowed.includes(defKid) ? defKid : allowed[0];
  const others = allowed.filter((k) => k !== def);

  return { error: "", candidates: [def, ...others] };
}

function getSecretForKid(env, kid) {
  const key = `SR_TRIGGER_SECRET_${kid}`;
  const v = envStr(env, key, "");
  if (v) return v;
  return envStr(env, "SR_TRIGGER_SECRET", "");
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === "/" || url.pathname === "") {
      return json({ ok: true, service: "agent-trigger", hint: "Use /health or POST /dispatch" }, 200);
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

    const tsHeader = request.headers.get("X-SR-Timestamp") || "";
    const sigHeader = (request.headers.get("X-SR-Signature") || "").toLowerCase();
    const kidHeader = request.headers.get("X-SR-Kid") || "";

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

    const pick = pickKidCandidates(env, kidHeader);
    if (pick.error) {
      return bad(401, pick.error, "Invalid or not allowed X-SR-Kid");
    }

    let usedKid = "";
    let verified = false;

    for (const kid of pick.candidates) {
      const secret = getSecretForKid(env, kid);
      if (!secret) continue;

      const expected = await hmacHex(secret, `${ts}.${rawBody}`);
      if (safeEqual(expected, sigHeader)) {
        usedKid = kid;
        verified = true;
        break;
      }
    }

    if (!verified) {
      return bad(403, "BadSignature", "Signature mismatch", { kidTried: pick.candidates });
    }

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

    const inputs = payload && typeof payload.inputs === "object" && payload.inputs ? payload.inputs : {};
    inputs.task = task;

    const ghUrl = `https://api.github.com/repos/${owner}/${repo}/actions/workflows/${workflow}/dispatches`;

    const ghRes = await fetch(ghUrl, {
      method: "POST",
      headers: {
        Accept: "application/vnd.github+json",
        Authorization: `Bearer ${token}`,
        "X-GitHub-Api-Version": ghApiVersion,
        "User-Agent": `sr-${repo}-agent-trigger`,
      },
      body: JSON.stringify({ ref, inputs }),
    });

    if (ghRes.status === 204) {
      return json(
          { ok: true, dispatched: true, repo: `${owner}/${repo}`, workflow, ref, task, kid: usedKid },
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
          kid: usedKid,
          github: text.slice(0, 2000),
        },
        ghRes.status
    );
  },
};



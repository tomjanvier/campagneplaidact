/**
 * Faux fournisseur OIDC « Act » pour essais locaux — NE JAMAIS DÉPLOYER.
 *
 * Expose le contrat minimal attendu par PlaidAct\CampaignCore\Act_SSO :
 *   GET  /.well-known/openid-configuration
 *   GET  /authorize  (écran de choix d'utilisateur, usage local uniquement)
 *   GET  /approve    (délivre un code à usage unique puis redirige)
 *   POST /token      (échange code -> access_token opaque)
 *   GET  /userinfo   (identité : sub, email, email_verified, name, roles)
 *
 * Lancement :  node server.mjs            (port 8081)
 *              PORT=8090 node server.mjs  (autre port)
 *
 * Côté WordPress (réglages « Connexion Act (SSO) ») :
 *   émetteur http://localhost:8081, client test-client, module activé,
 *   plus le filtre de développement :
 *   add_filter("plaidact_act_sso_allow_insecure_issuer", "__return_true");
 *   (mu-plugin local ; http://localhost uniquement, jamais en production).
 */

import http from "node:http";
import crypto from "node:crypto";

const PORT = Number(process.env.PORT || 8081);
const ISSUER = `http://localhost:${PORT}`;

// Comptes de démonstration : chaque clé donne un rôle Act distinct afin de
// contrôler le mappage (admin=administrator, editeur=editor, membre=subscriber).
const USERS = {
    admin: { sub: "act-admin-1", email: "admin@example.org", name: "Admin Act", roles: ["admin"] },
    editeur: { sub: "act-editeur-1", email: "editeur@example.org", name: "Éditeur Act", roles: ["editeur"] },
    membre: { sub: "act-membre-1", email: "membre@example.org", name: "Membre Act", roles: ["membre"] },
};

const codes = new Map(); // code -> { redirectUri, userKey }
const tokens = new Map(); // accessToken -> userKey

function randomToken() {
    return crypto.randomBytes(32).toString("base64url");
}

function sendJson(res, status, payload) {
    const body = JSON.stringify(payload);
    res.writeHead(status, { "content-type": "application/json; charset=utf-8", "content-length": Buffer.byteLength(body) });
    res.end(body);
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

function readBody(req) {
    return new Promise((resolve) => {
        let body = "";
        req.on("data", (chunk) => { body += chunk; });
        req.on("end", () => resolve(body));
    });
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url || "/", ISSUER);

    // Document de découverte OIDC.
    if (req.method === "GET" && url.pathname === "/.well-known/openid-configuration") {
        return sendJson(res, 200, {
            issuer: ISSUER,
            authorization_endpoint: `${ISSUER}/authorize`,
            token_endpoint: `${ISSUER}/token`,
            userinfo_endpoint: `${ISSUER}/userinfo`,
            response_types_supported: ["code"],
            code_challenge_methods_supported: ["S256"],
        });
    }

    // Écran de connexion de démonstration : choix du compte.
    if (req.method === "GET" && url.pathname === "/authorize") {
        const redirectUri = url.searchParams.get("redirect_uri") || "";
        const state = url.searchParams.get("state") || "";
        const clientId = url.searchParams.get("client_id") || "";

        if (!redirectUri || !state) {
            res.writeHead(400, { "content-type": "text/plain; charset=utf-8" });
            return res.end("Parametres redirect_uri et state requis.");
        }

        const buttons = Object.keys(USERS).map((key) => {
            const approve = new URL(`${ISSUER}/approve`);
            approve.searchParams.set("redirect_uri", redirectUri);
            approve.searchParams.set("state", state);
            approve.searchParams.set("user", key);
            const user = USERS[key];
            return `<p><a href="${escapeHtml(approve.toString())}">${escapeHtml(user.name)} — ${escapeHtml(user.email)} (${escapeHtml(user.roles.join(", "))})</a></p>`;
        }).join("\n");

        res.writeHead(200, { "content-type": "text/html; charset=utf-8" });
        return res.end(`<!doctype html><html lang="fr"><meta charset="utf-8"><title>Faux Act — connexion locale</title><body><h1>Se connecter avec Act (local)</h1><p>Client : ${escapeHtml(clientId)}</p>${buttons}</body></html>`);
    }

    // Délivrance du code puis redirection vers WordPress.
    if (req.method === "GET" && url.pathname === "/approve") {
        const redirectUri = url.searchParams.get("redirect_uri") || "";
        const state = url.searchParams.get("state") || "";
        const userKey = url.searchParams.get("user") || "";

        if (!redirectUri || !state || !USERS[userKey]) {
            res.writeHead(400, { "content-type": "text/plain; charset=utf-8" });
            return res.end("Demande invalide.");
        }

        const code = randomToken();
        codes.set(code, { redirectUri, userKey });
        console.log(`[mock-act] code delivre pour ${userKey}`);

        const back = new URL(redirectUri);
        back.searchParams.set("code", code);
        back.searchParams.set("state", state);
        res.writeHead(302, { location: back.toString() });
        return res.end();
    }

    // Échange du code contre un jeton opaque à usage unique.
    if (req.method === "POST" && url.pathname === "/token") {
        const params = new URLSearchParams(await readBody(req));
        const entry = codes.get(params.get("code") || "");
        codes.delete(params.get("code") || "");

        if (params.get("grant_type") !== "authorization_code" || !entry) {
            return sendJson(res, 400, { error: "invalid_grant" });
        }

        const accessToken = randomToken();
        tokens.set(accessToken, entry.userKey);
        console.log(`[mock-act] jeton emis pour ${entry.userKey}`);
        return sendJson(res, 200, { access_token: accessToken, token_type: "Bearer", expires_in: 3600 });
    }

    // Identité du porteur.
    if (req.method === "GET" && url.pathname === "/userinfo") {
        const userKey = tokens.get(String(req.headers.authorization || "").replace(/^Bearer\s+/i, ""));

        if (!userKey || !USERS[userKey]) {
            return sendJson(res, 401, { error: "invalid_token" });
        }

        const user = USERS[userKey];
        return sendJson(res, 200, {
            sub: user.sub,
            email: user.email,
            email_verified: true,
            name: user.name,
            roles: user.roles,
        });
    }

    res.writeHead(404, { "content-type": "text/plain; charset=utf-8" });
    res.end("Introuvable.");
});

server.listen(PORT, () => {
    console.log(`[mock-act] fournisseur OIDC local : ${ISSUER}`);
});

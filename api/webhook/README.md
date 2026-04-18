# Rarefolio Main Site — Webhook Receivers
These PHP endpoints receive signed notifications from the marketplace
(`01a_rarefolio_marketplace`) when mints confirm or ownership changes.
## Files
| File                    | Purpose                                                |
| ----------------------- | ------------------------------------------------------ |
| `_hmac.php`             | Shared: signature verification + replay protection     |
| `mint-complete.php`     | Receiver for `event=mint.complete`                     |
| `ownership-change.php`  | Receiver for `event=ownership.change`                  |
| `.env.example`          | Sample env vars (reference only; do not copy to .env)  |
| `.htaccess`             | Blocks direct access to `_hmac.php`, hardens headers   |
## Required environment variable
The receivers read one env var at runtime:
```
RF_WEBHOOK_SECRET   (required — 64 hex characters recommended)
```
This **must** match `PUBLIC_SITE_WEBHOOK_SECRET` on the marketplace.
### How to set it
Pick the method that matches your hosting stack. Never commit the real value.
**cPanel / Plesk / shared hosting** — Use the "Environment Variables" panel:
```
RF_WEBHOOK_SECRET = <64-hex-secret>
```
**Apache + mod_env** — Add to this directory's `.htaccess` at deploy time:
```apache path=null start=null
SetEnv RF_WEBHOOK_SECRET <64-hex-secret>
```
Keep that line out of git by either templating it at deploy time or adding
`api/webhook/.htaccess` to `.gitignore`.
**nginx + php-fpm** — Add to your pool config:
```
env[RF_WEBHOOK_SECRET] = <64-hex-secret>
```
Then reload: `sudo systemctl reload php8.1-fpm`.
**Local dev (PowerShell)**:
```powershell
$env:RF_WEBHOOK_SECRET = "<64-hex-secret>"
php -S localhost:8080 -t .
```
## Optional environment variables
```
RF_WEBHOOK_MAX_SKEW    default: 300     (seconds)
RF_WEBHOOK_NONCE_DIR   default: system temp dir
```
## Generating the secret
Use the helper shipped with the marketplace:
```powershell
php M:\01_Warp_Projects\01_projects\01a_rarefolio_marketplace\scripts\gen-webhook-secret.php
```
Paste its 64-character hex output into BOTH sides (see `docs/CONFIG.md`
in the marketplace repo for the full walkthrough).
## Security posture
- HTTPS only in production. Never accept webhooks over plain HTTP.
- `_hmac.php` is blocked from direct browser access by `.htaccess`.
- Nonces are stored as empty files in the nonce dir; make sure that dir is
  writable by the PHP user and NOT web-reachable.
- Failed requests return JSON errors with no stack traces.
## Delivery log
Each accepted webhook appends one JSON line to:
```
uploads/webhook-log/mint-complete.log
uploads/webhook-log/ownership-change.log
```
These files are gitignored. Rotate/archive them as part of your regular log hygiene.
## Full documentation
- `docs/CONFIG.md` (marketplace) — end-to-end config walkthrough
- `docs/WEBHOOKS.md` (marketplace) — signature format, events, payloads
- `docs/API.md` (marketplace) — public read API (the other direction)

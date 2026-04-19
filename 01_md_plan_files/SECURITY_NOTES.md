# Security Notes — rarefolio.io

Internal log of security incidents, alerts, and triage decisions for the
`powerproweb/rarefolio` repository. Kept under `01_md_plan_files/` (excluded
from production deploy by `.github/workflows/deploy.yml`).

---

## 2026-04-19 — GitGuardian alert: "Curl Username Password" (FALSE POSITIVE)

### Alert summary

- **Source:** GitGuardian email notification
- **Secret type:** Curl Username Password
- **Repository:** `powerproweb/rarefolio`
- **Pushed:** 2026-04-19, 15:40:46 UTC
- **Triggering commit:** `ed6d652` — *"CI: switch to lftp SFTP (port 22) —
  BlueHost uses SSH-based SFTP only"*
- **File:** `.github/workflows/deploy.yml`

### Root cause

The lftp-based deploy step introduced a URL of the shape
`scheme://user:password@host`, which GitGuardian's pattern-based detector
flags regardless of template substitution:

```yaml
open sftp://${{ secrets.SFTP_USER }}:\$SFTP_PASS@${{ secrets.SFTP_HOST }}:22
```

GitGuardian does not evaluate GitHub Actions `${{ secrets.* }}` placeholders
or shell variables like `$SFTP_PASS`; it only sees the literal
`user:password@host` shape and raises the alert.

### Impact assessment

**No real credential was ever committed to the repository.**

- `${{ secrets.SFTP_USER }}`, `${{ secrets.SFTP_PASS }}`, and
  `${{ secrets.SFTP_HOST }}` are GitHub Actions secret references resolved
  at runtime inside the Actions runner.
- `$SFTP_PASS` inside the `lftp -c "..."` block is a shell variable populated
  from the runner's `env:` block (also sourced from the secret).
- Reviewed commits `039367a`, `6b5fa5f`, and `ed6d652` — all three use
  `${{ secrets.* }}` references, none contain literal credentials.
- GitHub Actions masks secret values in job logs, so the cleartext password
  should not have appeared in runner output either.

### Remediation

1. **Root cause fix — done.** Commit `67aa748` *"CI: switch to rsync over SSH
   with key auth"* replaced the password-based lftp URL with an ed25519
   deploy key and `rsync -e "ssh -i ~/.ssh/deploy_key ..."`. The offending
   URL pattern is no longer present on `main`.
2. **Secret rotation — recommended.** Because `SFTP_PASS` is no longer used
   by the workflow, delete it from
   *GitHub → Settings → Secrets and variables → Actions* and rotate the
   corresponding BlueHost cPanel password as a precaution.
3. **History rewrite — not performed.** The pattern still appears in commits
   `039367a`, `6b5fa5f`, and `ed6d652`. Because no real credential is
   embedded, rewriting history was judged unnecessary. Revisit if GitGuardian
   or another scanner keeps re-raising the incident after it is marked as
   a false positive.

### GitGuardian dashboard action

Marked as **false positive** with the following rationale:

> GitHub Actions secret placeholder (`${{ secrets.SFTP_PASS }}`) and shell
> variable reference (`$SFTP_PASS`) in a `sftp://user:pass@host` URL inside
> a workflow file. No literal credential is stored in the repository. The
> workflow has since been replaced by key-based SSH auth in commit
> `67aa748`.

Steps to mark in the GitGuardian UI:

1. Open the incident from the email link (or GitGuardian → Incidents).
2. Click **Resolve** (or **Ignore**) → choose reason **"False positive"**.
3. Paste the rationale above into the comment field.
4. Save.

If the same pattern is re-detected later, add a repo-scoped ignore rule for
`.github/workflows/deploy.yml` via
*GitGuardian → Settings → Ignored Secrets / Custom rules*.

### Follow-up checklist

- Delete the now-unused `SFTP_PASS` GitHub Actions secret.
- Confirm the ed25519 deploy key (`SFTP_KEY`) and the rsync workflow succeed
  on the next push to `main`.
- Rotate the BlueHost cPanel password (defense-in-depth).
- Mark the GitGuardian incident as false positive with the rationale above.

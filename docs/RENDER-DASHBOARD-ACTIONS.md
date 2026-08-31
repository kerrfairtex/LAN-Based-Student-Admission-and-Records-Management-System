# Render dashboard action checklist

Three remaining critical errors from the line-by-line audit require dashboard
clicks. Render's API doesn't allow programmatically attaching disks or
changing the health check path / autoDeploy flag for existing services — these
must be done in the dashboard.

Service: **trac-jhs-sarms** (id `srv-da4a6v3bc2fs73ccffm0`)
URL: https://dashboard.render.com/web/srv-da4a6v3bc2fs73ccffm0

## #2 — Attach the persistent disk

The Dockerfile + entrypoint expect `/var/www/html/storage` to be a persistent
disk. Without it, sessions, registrar backups, and uploaded CSVs vanish on
every redeploy.

1. Go to https://dashboard.render.com/web/srv-da4a6v3bc2fs73ccffm0
2. Click **Disks** in the left sidebar
3. Click **Add Disk**
   - Name: `trac-jhs-storage`
   - Mount path: `/var/www/html/storage`
   - Size: `1` GB
4. Confirm. Render will trigger a redeploy to attach it.

Verify after redeploy:

```bash
curl -sS https://trac-jhs-sarms.onrender.com/healthcheck.php
# {"status":"ok","service":"trac-jhs-sarms","time":"..."}
```

Sign in to the live site, do anything that writes (upload a CSV via LIS import,
or just sign in/out — both write to the persistent disk). Redeploy the service
once. The data should survive.

## #8 + #9 — Fix the health check path

Live currently uses `/` (the default), which boots the entire landing page
and (via `config/app.php` → `database.php`) attempts a DB connection on every
health probe. If the DB is briefly unavailable, Render marks the service
unhealthy.

`/healthcheck.php` is purpose-built: it returns 200 JSON without touching the DB.

Two ways to fix it:

### Option A — one-off change in the dashboard

1. Go to https://dashboard.render.com/web/srv-da4a6v3bc2fs73ccffm0
2. Click **Settings** → **Health Check Path**
3. Change from `/` to `/healthcheck.php`
4. Save. Render triggers a redeploy.

### Option B — apply `render.yaml` as the Infrastructure-as-Code source

This is the one-shot fix that makes `render.yaml` the source of truth going
forward (no more dashboard drift).

1. Go to https://dashboard.render.com/web/srv-da4a6v3bc2fs73ccffm0
2. Click **Settings** → **Infrastructure as Code** (or "YAML" depending on UI rev)
3. Click **Apply Blueprint** or **Re-apply render.yaml**
4. Render will diff the live config against the YAML and show pending changes:
   - healthCheckPath → /healthcheck.php
   - disk → trac-jhs-storage at /var/www/html/storage (1 GB)
   - autoDeploy → false (you may want this; see #10)
5. Confirm each change.

After applying, the YAML is now the source of truth. Future drift between
dashboard and YAML is no longer possible.

## #10 — Disable auto-deploy

Right now `autoDeploy: "yes"` means **every push to main** auto-deploys to
production. You already have `RENDER_API_KEY` in `~/.bashrc` for explicit
deploys. Switching to manual deploy gives you a review gate.

1. Go to https://dashboard.render.com/web/srv-da4a6v3bc2fs73ccffm0
2. Click **Settings** → **Auto-Deploy** → **Off**
3. Save.

Then deploys happen via:

```bash
curl -s -X POST -H "Authorization: Bearer $RENDER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"clearCache":"do_not_clear"}' \
  https://api.render.com/v1/services/srv-da4a6v3bc2fs73ccffm0/deploys
```

Poll status until `"status":"live"` (~60–90 seconds).

## Verification matrix after all three fixes

| Check | Command | Expected |
|-------|---------|----------|
| Disk attached | Render dashboard → Disks shows `trac-jhs-storage` | yes |
| healthCheckPath is `/healthcheck.php` | `curl -sS -D - -o /dev/null https://trac-jhs-sarms.onrender.com/healthcheck.php \| head -1` returns HTTP 200 | yes |
| autoDeploy is off | Render dashboard → Settings → Auto-Deploy | Off |
| Session survives a manual redeploy | sign in, redeploy, sign-in still works | yes |
| Upload survives a manual redeploy | upload LIS CSV, redeploy, file still in /uploads | yes |
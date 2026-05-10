---
title: Deployment
tags: [deployment, production, packaging]
---

# Deployment

## Packaging

From the project root:

```bash
# 1. Clean old artefacts
rm -f custom_apps/obsidian_viewer/js/*.mjs custom_apps/obsidian_viewer/css/*.css

# 2. Build
cd custom_apps/obsidian_viewer && npm run build && cd ../..

# 3. Package (IMPORTANT: do not change these excludes — see note below)
tar czf /tmp/obsidian_viewer-$VERSION.tar.gz \
  --exclude='node_modules' --exclude='.git' --exclude='src' \
  --exclude='package*.json' --exclude='tsconfig.json' \
  --exclude='composer.*' --exclude='*.map' \
  -C custom_apps obsidian_viewer
```

> **Do not exclude `*.mjs`** — the main JavaScript bundle is `obsidian_viewer-main.mjs`. Excluding it produces a tarball that loads CSS but no JavaScript, resulting in a blank page. This exact command has been validated in production; do not improvise variations.

## Installing on a Nextcloud instance

```bash
# 1. Upload the tarball
scp /tmp/obsidian_viewer-$VERSION.tar.gz user@server:/tmp/

# 2. On the server: extract into custom_apps
cd /path/to/nextcloud/custom_apps
rm -rf obsidian_viewer
tar xzf /tmp/obsidian_viewer-$VERSION.tar.gz

# 3. Fix ownership
chown -R www-data:www-data obsidian_viewer

# 4. Run upgrade (required after every version change)
sudo -u www-data php /path/to/nextcloud/occ upgrade

# 5. Enable if first install
sudo -u www-data php /path/to/nextcloud/occ app:enable obsidian_viewer
```

## Nextcloud compatibility

| App version | Nextcloud min | Nextcloud max |
|-------------|---------------|---------------|
| 0.8.x | 31 | 32 |

Adjust `min-version` and `max-version` in `appinfo/info.xml` when testing against new Nextcloud releases.

## Verifying the deployment

After installing, verify the app loads:

1. Log into Nextcloud — the Obsidian Viewer icon should appear in the top navigation bar
2. Click it — the app should load (not a blank page)
3. If you see a blank page, check the browser console for missing JS files and verify the tarball was packaged correctly (see [[Development Setup#Common issues]])

API health check:

```bash
curl -s -u USER:PASS -H 'OCS-APIREQUEST: true' \
  'https://your-server/apps/obsidian_viewer/api/vaults'
```

Should return `{"vaults":[...]}`.

## Updating an existing installation

Same as fresh install — extract the new tarball over the old directory, run `occ upgrade`. Nextcloud handles the version transition.

No database migrations exist (the app has no database). No data is lost during updates.

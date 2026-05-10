---
title: Development Setup
tags: [setup, development, docker]
---

# Development Setup

## Prerequisites

- **Docker** and **Docker Compose** (v2)
- **Node.js 20.x** and **npm 10.x** on the host (not inside the container)
- A terminal with `curl` for API testing

## Step 1: Clone the repository

```bash
git clone <repo-url>
cd NEXT-OBSIDIAN
```

## Step 2: Start the Docker environment

```bash
docker-compose up -d
```

This starts:
- **MariaDB 11** — database for Nextcloud
- **Nextcloud 31** — web server on `http://localhost:8082`

The app source is bind-mounted into the container at `/var/www/html/custom_apps/obsidian_viewer`.

Wait for Nextcloud to finish its first-run initialisation (~30 seconds). You can check:

```bash
docker-compose logs -f nextcloud
```

Default credentials: `admin` / `admin`.

## Step 3: Install Node.js dependencies

```bash
cd custom_apps/obsidian_viewer
npm install
```

> **Important:** `@nextcloud/vite-config` is pinned to exactly `1.5.6`. Do not upgrade it — version 1.7+ pulls Vite 7, which is incompatible with the Nextcloud build tooling and will break the build silently.

## Step 4: Build the frontend

```bash
npm run build
```

For iterative development:

```bash
npm run watch
```

Build output goes to `js/` and `css/` within the app directory.

## Step 5: Enable the app

```bash
docker-compose exec -u www-data nextcloud php occ app:enable obsidian_viewer
```

## Step 6: Copy the test vault into Nextcloud

```bash
docker cp test_vault/. <container-name>:/var/www/html/data/admin/files/TestVault/
docker-compose exec nextcloud chown -R www-data:www-data /var/www/html/data/admin/files/TestVault
docker-compose exec -u www-data nextcloud php occ files:scan admin
```

Replace `<container-name>` with the actual container name (check with `docker ps`, typically `next-obsidian-nextcloud-1`).

## Step 7: Verify

1. Open `http://localhost:8082` and log in as `admin`
2. The Obsidian Viewer app should appear in the top navigation bar
3. The TestVault should be detected automatically

### API verification

```bash
# List vaults
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults'

# File tree
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/files'

# Graph
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/graph'
```

## Common issues

### PHP changes not picked up

Nextcloud caches PHP via OPcache. After modifying PHP files:

```bash
docker-compose restart nextcloud
```

A graceful Apache reload (`kill -USR1 1` inside the container) sometimes works but is unreliable. A full container restart is the sure fix.

### "Update needed" after version bump

Every change to `<version>` in `info.xml` requires:

```bash
docker-compose exec -u www-data nextcloud php occ upgrade
```

### Build produces no output / blank page

Check that `js/obsidian_viewer-main.mjs` exists after building. If missing, the app loads CSS but no JavaScript, resulting in a blank page.

### Stale CSS chunks

Vite does not remove old chunk files. Before a clean build:

```bash
rm -f custom_apps/obsidian_viewer/js/*.mjs custom_apps/obsidian_viewer/css/*.css
npm run build
```

## Enabling debug mode

Edit `config/config.php` inside the container (or via `docker-compose exec`) and set:

```php
'debug' => true,
```

This disables Nextcloud's JS/CSS caching and shows full error traces.

---
title: Maintenance
tags: [maintenance, operations]
---

# Maintenance

## Version bumping

1. Update `<version>` in `appinfo/info.xml`
2. Rebuild: `cd custom_apps/obsidian_viewer && npm run build`
3. On every Nextcloud instance running the app:
   ```bash
   sudo -u www-data php occ upgrade
   ```
4. If the instance shows "Update needed" in the browser, running `occ upgrade` resolves it

## Clearing caches

### PHP OPcache (server-side)

After changing PHP files on a running instance:

```bash
# Docker
docker-compose restart nextcloud

# Bare-metal Apache
sudo systemctl restart apache2
```

### Browser cache (client-side)

Version bumps in `info.xml` change the `?v=` hash on all asset URLs, which busts browser caches automatically. If a user still sees old behaviour, a hard refresh (Ctrl+Shift+R) resolves it.

### Nextcloud JS/CSS cache

Nextcloud merges and caches JS/CSS. In development, set `'debug' => true` in `config/config.php` to disable this. In production, `occ upgrade` or `occ maintenance:repair` clears the cache.

## Checking the deployed version

```bash
grep '<version>' /path/to/nextcloud/custom_apps/obsidian_viewer/appinfo/info.xml
```

Or via the Nextcloud admin panel under Apps.

## Logs

Nextcloud logs are at `/path/to/nextcloud/data/nextcloud.log`. Filter for `obsidian_viewer`:

```bash
grep obsidian_viewer /path/to/nextcloud/data/nextcloud.log | tail -20
```

In Docker:

```bash
docker-compose exec nextcloud tail -100 /var/www/html/data/nextcloud.log | grep obsidian_viewer
```

## Updating the test vault in Docker

```bash
docker cp test_vault/. <container-name>:/var/www/html/data/admin/files/TestVault/
docker-compose exec nextcloud chown -R www-data:www-data /var/www/html/data/admin/files/TestVault
docker-compose exec -u www-data nextcloud php occ files:scan admin
```

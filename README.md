# Obsidian Viewer for Nextcloud

A read-only Nextcloud app that renders Obsidian vaults directly in the browser. Browse your vaults with full Obsidian-flavoured markdown, graph view, search, and canvas support — no sync, no install, no editing.

## Why

Obsidian is excellent for organising project information. Nextcloud is excellent for sharing files and collaborating. But sharing the *rendered* Obsidian experience requires everyone to install and sync the app. This viewer bridges the gap: anyone with Nextcloud access can browse vaults the way they're meant to be read.

See [docs/Motivation.md](docs/Motivation.md) for the full story.

## Features

- **Full Obsidian markdown** — wiki-links, embeds, callouts, tags, highlights, LaTeX, Mermaid diagrams, syntax highlighting
- **Graph view** — D3.js force-directed graph matching Obsidian's physics model, with full settings panel
- **Search** — Full Obsidian-compatible search language (AND, OR, NOT, operators, regex, property queries)
- **Query API** — Metadata queries exposed as JSON for integration with Analytics and external tools
- **Canvas support** — Basic rendering of `.canvas` files
- **Nextcloud-native** — inherits permissions, uses the Files app for non-markdown content, respects CSP

## Quick start

### Prerequisites

- Docker and Docker Compose v2
- Node.js 20.x and npm 10.x

### Setup

```bash
git clone <this-repo>
cd NEXT-OBSIDIAN

# Start Nextcloud + MariaDB
docker-compose up -d

# Install dependencies and build
cd custom_apps/obsidian_viewer
npm install
npm run build
cd ../..

# Enable the app
docker-compose exec -u www-data nextcloud php occ app:enable obsidian_viewer

# Load the test vault
docker cp test_vault/. $(docker ps -qf name=nextcloud):/var/www/html/data/admin/files/TestVault/
docker-compose exec nextcloud chown -R www-data:www-data /var/www/html/data/admin/files/TestVault
docker-compose exec -u www-data nextcloud php occ files:scan admin
```

Open `http://localhost:8082`, log in as `admin`/`admin`, and click Obsidian Viewer in the navigation bar.

See [docs/Development Setup.md](docs/Development%20Setup.md) for full instructions and troubleshooting.

## Deploying to production

```bash
# Build and package
cd custom_apps/obsidian_viewer && npm run build && cd ../..
tar czf /tmp/obsidian_viewer-0.8.4.tar.gz \
  --exclude='node_modules' --exclude='.git' --exclude='src' \
  --exclude='package*.json' --exclude='tsconfig.json' \
  --exclude='composer.*' --exclude='*.map' \
  -C custom_apps obsidian_viewer

# On the server
cd /path/to/nextcloud/custom_apps
rm -rf obsidian_viewer
tar xzf /tmp/obsidian_viewer-0.8.4.tar.gz
chown -R www-data:www-data obsidian_viewer
sudo -u www-data php /path/to/nextcloud/occ upgrade
```

See [docs/Deployment.md](docs/Deployment.md) for details.

## Documentation

The `docs/` folder is an Obsidian vault — open it in Obsidian for the best reading experience, or browse it on GitHub:

- [Architecture](docs/Architecture.md) — Backend, frontend, API, data flow
- [Design Philosophy](docs/Design%20Philosophy.md) — Integration over duplication, interface boundaries
- [Rendering Features](docs/Rendering%20Features.md) — All supported Obsidian syntax
- [Graph View](docs/Graph%20View.md) — Force-directed graph, settings, physics model
- [Search](docs/Search.md) — Full expression language reference
- [Query API](docs/Query%20API.md) — Metadata queries, external integration
- [Testing](docs/Testing.md) — Test vault, procedures, production testing
- [Performance](docs/Performance.md) — Profiling data, bottlenecks, scaling
- [For Maintainers](docs/For%20Maintainers.md) — Interface contracts, what's open for revision
- [Project Journal](docs/Project%20Journal.md) — Complete session-by-session log (~24 hours human, ~15 hours AI)
- [Human-AI Collaboration](docs/Human-AI%20Collaboration.md) — How this was built as a human-AI partnership

## How this was built

This project is a collaboration between Pietro Mincuzzi and Claude (Anthropic's AI), working through [Claude Code](https://claude.com/claude-code). The human defined requirements, made design decisions, and tested everything in the browser. The AI wrote the code, maintained documentation, and handled technical implementation. Total human investment: ~24 hours; AI-active session time within that: ~15 hours.

See [docs/Human-AI Collaboration.md](docs/Human-AI%20Collaboration.md) for the full story of how the partnership worked.

## Compatibility

| Component | Version |
|-----------|---------|
| Nextcloud | 31–32 |
| PHP | 8.x |
| Node.js (build only) | 20.x |

## Contact

Bug reports, questions and feature requests belong in [GitHub Issues](https://github.com/pixmix/obsidian-viewer-nextcloud/issues). That is the watched channel; please do not use personal email.

## Licence

Copyright © 2026 Pietro Mincuzzi.

AGPL-3.0-or-later — same as Nextcloud. The full licence text is in [LICENCE](LICENCE).

---
title: Architecture
tags: [architecture, technical]
---

# Architecture

## Overview

```
Browser (Vue.js + D3.js)              Nextcloud Server (PHP 8.x)
────────────────────────               ────────────────────────────
App.vue                                PageController
├── FileTree.vue                       ├── serves HTML template
├── MarkdownViewer.vue                 │
│   ├── markdown-it + plugins          VaultController (JSON API)
│   ├── highlight.js                   ├── list vaults
│   ├── KaTeX                          ├── file tree
│   └── Mermaid (external UMD)         ├── file content
├── CanvasViewer.vue                   ├── link graph
├── GraphView.vue (D3.js)              ├── search
├── GraphSettings.vue                  ├── properties
├── QueryView.vue                      ├── query
├── QuickSwitcher.vue                  ├── file info
├── VaultSearch.vue                    └── raw file
├── Bookmarks.vue                      
└── SearchHints.vue                    VaultService
                                       ├── vault detection (.obsidian/)
api.js ←── HTTP/JSON ──→               ├── file tree scanning
searchMatcher.js                       ├── link extraction + resolution
                                       ├── search parser (tokeniser/parser/evaluator)
                                       ├── query engine
                                       └── tag extraction
```

## Backend — PHP

The backend is a standard Nextcloud app under `custom_apps/obsidian_viewer/`. It has no database — all data comes from Nextcloud's file storage API (`IRootFolder`, `IUserSession`).

### Key files

| File | Role |
|------|------|
| `lib/AppInfo/Application.php` | Bootstrap, CSP policy (blob: for PDF embeds) |
| `lib/Controller/PageController.php` | Serves the Vue app template |
| `lib/Controller/VaultController.php` | All API endpoints |
| `lib/Service/VaultService.php` | All business logic — vault detection, file ops, search, graph, queries |
| `appinfo/routes.php` | Route definitions |
| `appinfo/info.xml` | App metadata, version, NC compatibility |

### API endpoints

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/` | HTML page (Vue mount point) |
| `GET` | `/api/vaults` | List detected vaults |
| `GET` | `/api/vaults/{vault}/files` | Full file tree |
| `GET` | `/api/vaults/{vault}/file?path=...` | Raw file content (JSON-wrapped) |
| `GET` | `/api/vaults/{vault}/graph?mdLinks=0\|1` | Link graph (nodes + edges) |
| `GET` | `/api/vaults/{vault}/search?q=...` | Full-text search |
| `GET` | `/api/vaults/{vault}/properties` | All frontmatter property names |
| `POST` | `/api/vaults/{vault}/query` | Metadata query engine |
| `GET` | `/api/vaults/{vault}/fileinfo?name=...` | Nextcloud file ID + directory |
| `GET` | `/api/vaults/{vault}/raw?name=...` | Raw file bytes (images, PDFs) |

All endpoints require authentication (`@NoAdminRequired`). CSRF protection is active on all except the page endpoint and query.

## Frontend — Vue 2.7 + Vite

The frontend is a single-page Vue application mounted on Nextcloud's `#content` div. It uses `@nextcloud/vue` components for the navigation shell and custom components for all content rendering.

### Build chain

- **Vite** with `@nextcloud/vite-config` 1.5.6 (pinned — later versions pull Vite 7 which breaks the build)
- **Vue 2.7** (Nextcloud 31 standard)
- Output to `js/` and `css/` directories

### Rendering pipeline

Markdown rendering is entirely client-side via `markdown-it` with custom plugins:

1. `wikilinks.js` — `[[target|display]]` syntax
2. `embeds.js` — `![[note]]`, `![[note#section]]`, `![[file.pdf]]`
3. `callouts.js` — `> [!type] title` blockquote syntax
4. `tags.js` — `#tag` inline tags
5. `highlights.js` — `==highlighted text==`
6. `math.js` — `$inline$` and `$$block$$` LaTeX via KaTeX
7. `comments.js` — `%%hidden comments%%`

Code syntax highlighting uses highlight.js with 16 language grammars. Mermaid diagrams are rendered via the UMD build loaded as an external script (required to comply with Nextcloud's CSP — dynamic `import()` is blocked).

### State management

No Vuex — plain reactive `data()` properties in `App.vue`. Navigation history is a simple array with an index pointer.

## Security model

- All file access goes through Nextcloud's `IRootFolder` → `getUserFolder()` — the app can only see files the authenticated user has access to
- Path traversal blocked: `..` rejected in all file-path parameters
- CSP: `blob:` added to `frame-src` for inline PDF rendering; all other policies inherited from Nextcloud
- No database, no caching, no state stored server-side beyond Nextcloud's own file metadata

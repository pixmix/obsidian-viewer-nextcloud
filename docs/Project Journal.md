---
title: Project Journal
tags: [project, journal, time-tracking]
---

# Project Journal

Chronological log of all working sessions. Each entry records the date, approximate duration, focus area, and tasks completed. This is the authoritative record of time and effort invested in the project.

**Total human time invested through v0.8.4: approximately 24 hours.**
**Of which AI-active session time: approximately 14 hours.**

> [!info] Keeping this up to date
> Every future session must append an entry to this journal. This is a non-negotiable project practice — it tracks investment, provides context for future contributors, and helps estimate effort for similar projects.

---

## 2026-05-06 — ~15 min — Project kickoff and architecture planning

- Defined the project: read-only Nextcloud app for visualising Obsidian vaults
- Agreed key requirements: auto-detect vaults via `.obsidian/`, no editing, full rendering fidelity
- Prioritised features: core markdown, wiki-links, embeds, callouts, graph view, canvas (must-have), dataview (strong want)
- Agreed architecture: PHP backend (NC file storage API), Vue.js + TypeScript frontend, client-side rendering
- Agreed 6-phase delivery plan (skeleton → markdown → rich content → graph → canvas → dataview)
- Identified next step: confirm dev environment (local Nextcloud / Docker) before starting phase 1

**Milestone:** Project scope and architecture agreed. No code yet.

## 2026-05-07 — 3h 52m human / ~3h 30m AI — Dev environment + Phases 1–3

- Set up Docker dev environment: Nextcloud 31 + MariaDB on localhost:8082
- Resolved container mount conflicts (initial rsync vs bind mounts)
- Built complete PHP backend: Application.php, PageController, VaultController, VaultService
- All 3 API endpoints working: vault detection, file tree, file content
- Created test vault with sample .md, .canvas files, copied into NC user files
- Installed Node.js 20.x on host, set up Vite + Vue 2.7 build chain
- Built full Vue frontend: App.vue, FileTree.vue, MarkdownViewer.vue, VaultSelector.vue, api.js
- Fixed layout issues: mount on #content, remove NcContent wrapper, override overflow:clip, add opaque background
- Added Util::addStyle for CSS loading

**Milestone:** Phase 1 complete — working app with vault detection, sidebar file browser, and basic markdown rendering.

## 2026-05-07 (continued) — Phases 2–3 + Mermaid CSP fix

- Implemented 6 markdown-it plugins: wiki-links, embeds, callouts, tags, highlights, math/KaTeX
- Added highlight.js with 16 languages
- Added Mermaid diagram rendering and dataview placeholder styling
- Added frontmatter display panel
- Fixed bullet point rendering (Nextcloud resets `list-style: none`)
- Fixed Welcome.md flickering bug: moved frontmatter parsing from computed to watcher
- Diagnosed and fixed Mermaid page-reload bug: Nextcloud's CSP blocks dynamic `import()`. Solution: load Mermaid UMD build via `Util::addScript()` as external dependency
- Recovered from accidental file loss after Vite 7 upgrade broke the build
- Pinned `@nextcloud/vite-config` to 1.5.6
- Deployed v0.4.1

**Milestone:** Phases 1–3 complete — full Obsidian markdown rendering.

## 2026-05-08 — 5h 43m human / ~3h 15m AI — Phase 4 + search parser

- Implemented D3.js force-directed graph view with wiki-link relationship mapping
- Added server-side link extraction with path resolution
- Built left action bar: sidebar collapse, quick switcher (Ctrl+O), graph toggle
- Built content title bar with back/forward navigation history
- Built graph settings panel: Filters, Groups, Display, Forces sections
- Animate feature: chronological node reveal by mtime
- Added canvas nodes to graph
- Created QuickSwitcher.vue and GraphSettings.vue
- Version bump to 0.6.0

**Milestone:** Phase 4 complete — graph view with full settings, Obsidian-style UI layout, navigation history.

## 2026-05-08 (continued) — Bug fixes, sidebar tabs, unified search parser (v0.6.3–v0.7.2)

- Fixed graph breakage (temporal dead zone), arrow sizing, arrows covered by nodes
- Fixed incomplete tag extraction (frontmatter parsing)
- Added sidebar tabs: Files, Search, Bookmarks
- Added backend search API and properties API
- Built full Obsidian-compatible expression parser in both JS and PHP
- Supports: implicit AND, OR, NOT, grouping, quoted phrases, regex, all 10 operators, property syntax, nested sub-expressions
- Version bumped to 0.7.2

**Milestone:** Unified search language complete — identical expression parser in JS and PHP.

## 2026-05-08 (continued) — Search parser negation fix

- Fixed negation inside operator arguments (`line:-x`, `tag:-test`)
- Tokeniser and parser fixes in both JS and PHP

**Milestone:** Search parser handles negation within operator scopes.

## 2026-05-09 — 9h 30m human / ~4h 30m AI — Rendering, graph physics, query, production

- Fixed table text wrapping, copy-to-clipboard on indented code blocks
- Redesigned frontmatter/properties display with type icons
- Fixed hover preview popups
- Added non-markdown file support in sidebar
- Rewrote graph force simulation to match Obsidian's physics model (forceX/forceY, quadratic scaling)
- Built custom slider tooltips
- Fixed graph initial load, animate, ResizeObserver bug
- Fixed link extraction: embeds create edges, code blocks stripped, backslash handling
- Installed d3 as npm dependency

**Milestone:** Graph physics matches Obsidian's model. Link extraction correct.

## 2026-05-09 (continued) — Query API, in-app Query View, graph animation fixes (v0.8.0)

- Built metadata query API: POST endpoint with fields, filter, sort, limit
- Built QueryView.vue: field picker, filter input, sort, results table
- Integrated with Nextcloud Analytics app
- Fixed graph toggle bug (tags/orphans with fixed positions)
- Deployed v0.8.0 to production Nextcloud 32

**Milestone:** Query API and query builder complete. Production deployment.

## 2026-05-10 — 5h 10m human / ~3h 30m AI — Production fixes, mdLinks, docs, GitHub

- Case-insensitive link resolution with lowercase lookup maps
- Show properties toggle with icon state change
- Embedded file links opening in Nextcloud Files
- Packaged v0.8.1

**Milestone:** Three production-feedback features complete.

## 2026-05-10 (continued) — Bug fixes: graph edges, PDF CSP, file URLs (v0.8.2)

- Fixed graph dangling edges on hover/drag
- Fixed graph edge filter (note-only allowlist)
- Fixed PDF embed CSP with blob URLs and `AddContentSecurityPolicyEvent`
- Fixed file open URLs with fileInfo API endpoint
- Packaged v0.8.2

**Milestone:** All features working correctly in production.

## 2026-05-10 (continued) — Markdown links, unresolved nodes, packaging fix (v0.8.3–v0.8.4)

- Changed unresolved graph nodes from yellow to light grey, visible by default
- Added standard markdown link `[text](target)` extraction to graph backend
- Added "Markdown links" toggle in graph settings (triggers server reload)
- Relative path resolution for markdown links (`../`, `./`)
- Fixed packaging command: `--exclude='*.mjs'` was stripping the main JS bundle, causing blank page on production
- Performance profiling: identified Nextcloud file API as the bottleneck (~13s for 440 files)
- Documented packaging command in project memory
- Prepared GitHub repository structure and documentation vault

**Milestone:** Markdown link graph extraction complete. Packaging SOP validated. Documentation started.

---

## Time summary

The project tracks two time measures:
- **Human time** — total time the developer spent on the project (testing, thinking, reviewing, deploying, AI interaction). Derived from time tracking logs at 80% focus allocation.
- **AI session time** — time spent in active AI-assisted coding sessions (a subset of human time).

| Date | Human time | AI session time | Phase |
|------|-----------|----------------|-------|
| 2026-05-06 | ~15 min | ~15 min | Planning |
| 2026-05-07 | 3h 52m | ~3h 30m | Dev environment + Phases 1–3 |
| 2026-05-08 | 5h 43m | ~3h 15m | Phase 4 + search parser |
| 2026-05-09 | 9h 30m | ~4h 30m | Rendering polish + query + production |
| 2026-05-10 | 5h 10m | ~3h 30m | Production fixes + mdLinks + docs |
| **Total** | **~24h 15m** | **~14h 45m** | **v0.1 → v0.8.4** |

The gap between human and AI time reflects testing in the browser, deploying to production, reviewing output, thinking through design decisions, and other work the AI cannot see or do. This is typical of human-AI collaboration: the AI accelerates the coding, but the human's investment in verification and judgement remains substantial.

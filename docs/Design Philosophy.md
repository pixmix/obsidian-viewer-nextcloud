---
title: Design Philosophy
tags: [philosophy, architecture, integration]
---

# Design Philosophy

## Integration over duplication

The most important design principle in this project: **integrating Obsidian-style content access within Nextcloud's ecosystem is more valuable than replicating every Obsidian feature inside the app itself.**

Obsidian is excellent software. Trying to rebuild all of it inside a Nextcloud app would be a losing proposition — always behind, always incomplete, always competing with the original. Instead, this app focuses on two things:

1. **Faithful rendering** of vault content in the browser, so people can read it without Obsidian
2. **API surfaces** that let other tools access vault data, so the information flows into the Nextcloud ecosystem

The [[Query API]] is the clearest expression of this: rather than building a full Dataview engine, the app exposes vault metadata as a JSON endpoint that Analytics, custom dashboards, or any HTTP client can consume.

## Where features belong

When considering a new feature, ask:

- **Does it help a reader understand the content?** → Build it in the app (rendering, navigation, search)
- **Does it help other systems consume the data?** → Build it as an API (queries, metadata, export)
- **Does it replicate an authoring workflow?** → Don't build it. That's Obsidian's job.

## Interface boundaries

The app has a clear separation between backend and frontend:

- **Backend** handles all file I/O, link resolution, search, and metadata extraction. It speaks JSON.
- **Frontend** handles all rendering, interaction, and state. It consumes JSON.

These two never share code or assumptions beyond the API contract. This means:

- The rendering engine can be replaced (e.g. switching from markdown-it to remark) without touching PHP
- The backend can be optimised (caching, background indexing) without touching Vue
- New API consumers (mobile apps, CLI tools) can use the backend directly

## On Nextcloud integration

The app deliberately uses Nextcloud's native patterns:

- `@nextcloud/vue` components for the navigation shell
- `@nextcloud/axios` for HTTP with CSRF handling
- `Util::addScript()` / `Util::addStyle()` for asset loading (CSP-safe)
- `IRootFolder` → `getUserFolder()` for all file access (inherits permissions)
- Standard Nextcloud app structure (no custom autoloading, no composer)

This isn't just for consistency — it ensures the app benefits from Nextcloud upgrades and security patches without modification.

## Read-only is a feature

The app intentionally does not edit files. This is not a limitation — it is a design choice:

- Obsidian vaults have a single source of truth: the local copy managed by Obsidian
- Editing in the browser creates sync conflicts with no clear resolution path
- Read-only access is the use case: sharing project information with people who need to read it
- It keeps the codebase dramatically simpler — no conflict resolution, no locking, no undo

## Simplicity over completeness

The app has no database, no background jobs, no caching layer, no configuration UI. Every request reads directly from Nextcloud's file storage. This means:

- Zero state to manage or migrate
- Zero data to lose
- Installation is "extract tarball, run upgrade"
- Uninstallation is "delete the folder"

The cost is performance on large vaults (see [[Performance]]). Caching can be added later if needed, but the current stateless design is intentionally kept as long as it's viable.

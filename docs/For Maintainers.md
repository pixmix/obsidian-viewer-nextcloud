---
title: For Maintainers
tags: [maintainers, architecture, contribution]
---

# For Maintainers

## Open for revision

Every feature in this app is open for revision. The current implementations were chosen for speed of delivery and correctness, not as permanent architectural commitments. Specifically:

### Rendering engine

The markdown rendering uses `markdown-it` with 7 custom plugins. This could be replaced with `remark`/`unified` or any other renderer, provided:

- **Interface:** Takes a markdown string, returns HTML
- **Contract:** Must support all extensions listed in [[Rendering Features]] — wiki-links, embeds, callouts, tags, highlights, LaTeX, comments
- **Test:** All items in the [[Testing]] checklist must pass

### Graph engine

D3.js force-directed graph. Could be replaced with Cytoscape.js, vis-network, or a WebGL renderer. The interface:

- **Input:** `{ nodes: [{id, name, type, mtime}], edges: [{source, target, caseMismatch?}] }`
- **Output:** Visual graph with click-to-navigate, hover highlight, zoom/pan
- **Settings:** Must expose the controls described in [[Graph View#Settings panel]]

### Search parser

Dual implementation in JS and PHP. Could be unified (e.g. compile to WASM, or move entirely server-side). The contract:

- **Input:** Search expression string (see [[Search#Search syntax]])
- **Output:** Boolean match against a note's content + metadata
- **Constraint:** Both implementations must produce identical results for any valid query

### File storage access

Currently uses Nextcloud's `IRootFolder` / `IUserSession` API for all file access. If performance becomes an issue (see [[Performance]]), a caching layer or background indexer could be introduced between the service layer and the storage API, provided:

- **Contract:** Same data, same permissions — a user must never see files they can't access in the Files app
- **Freshness:** Changes must be reflected within a reasonable time (minutes, not hours)

## Interface contracts — the rule

**Any component can be replaced, but the interface it exposes must be maintained or explicitly migrated.**

This means:

1. **Document the interface before changing it.** What does the component accept? What does it return? What invariants does it maintain?
2. **Keep the API stable across versions.** External consumers (Analytics, custom scripts) depend on the [[Query API]] shape. Breaking changes need a version bump and migration path.
3. **Test at the boundary.** Unit tests for internal logic are nice. Integration tests at the API boundary are essential.

The goal is to allow any component to be rewritten, optimised, or replaced by a third-party library without breaking the rest of the system.

## Integration with other Nextcloud apps

The architecture deliberately exposes API surfaces that other apps can consume. Current integrations:

- **Nextcloud Analytics:** Can use the query API as an ExternalJson datasource (see [[Query API#Nextcloud Analytics app]])
- **Nextcloud Files:** File embeds and sidebar clicks open files in the Files app using Nextcloud's native URL pattern

Future integration possibilities:

- **Nextcloud Talk:** Share vault content or search results in chat
- **Nextcloud Deck:** Link cards to vault notes
- **Nextcloud Flow:** Trigger actions based on vault content changes
- **External dashboards:** Any tool that speaks HTTP can query vault metadata

When considering integration, prefer **API-level coupling** (other apps call our API) over **code-level coupling** (other apps import our PHP classes). This keeps version dependencies loose.

## Code structure conventions

- **PHP:** PSR-4 autoloading under `lib/`. One service class (`VaultService`), one controller (`VaultController`). No composer dependencies.
- **Vue:** Components in `src/components/`, markdown plugins in `src/markdown/`, services in `src/services/`. No Vuex — state lives in `App.vue`.
- **No comments unless the "why" is non-obvious.** The code should be readable from naming and structure.
- **No feature flags or backwards-compatibility shims.** Change the code directly.

## Time tracking

All work on this project is logged in the [[Project Journal]]. Every future session should append an entry with date, duration, focus area, and tasks completed. This is the authoritative record of effort invested.

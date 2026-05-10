---
title: Performance
tags: [performance, profiling]
---

# Performance

## Profiling data

Measured 2026-05-10 against the SZ-BOTTLEFILL production vault (4,623 files, 439 markdown, 778 folders) on a VPS running Nextcloud 32.

### API response times

| Endpoint | Server time | Payload size | What it does |
|----------|-------------|--------------|-------------|
| Vault list | 1.7s | 74 B | Scans folder tree for `.obsidian/` dirs (up to 5 levels) |
| File tree | **6.8s** | **1.1 MB** | Walks all 4,623 files + 778 folders via Nextcloud storage API |
| Graph (wikilinks only) | **12.6s** | 86 KB | Reads all 439 `.md` files, regex link extraction, target resolution |
| Graph (+ markdown links) | **13.9s** | 200 KB | Same + standard `[text](target)` extraction |
| Single file content | ~0.5s | Varies | Reads one file via Nextcloud storage API |

### Client-side rendering

| Step | Time | What |
|------|------|------|
| JS bundle parse | ~1s | 1.1 MB main bundle |
| D3 graph render (500 nodes) | ~1-2s | Force simulation: 300 synchronous warm-up ticks + layout |
| Markdown render | <100ms | markdown-it + all plugins |
| Mermaid diagrams | ~0.5-1s per diagram | External UMD library |

## Where the time goes

### The bottleneck is Nextcloud's file API

The graph endpoint spends ~12 seconds reading 439 files through `$node->getContent()`. Each call goes through Nextcloud's storage abstraction: metadata lookup, permission check, encryption layer, storage backend. The actual regex work (link extraction, target resolution) is negligible.

The file tree endpoint spends ~7 seconds calling `getDirectoryListing()` recursively across 778 folders — same overhead per call.

### Network is not the bottleneck

The payloads (86 KB graph, 1.1 MB tree) transfer in under a second on any reasonable connection. The wait is almost entirely server-side processing.

### Client rendering is fast

D3's force simulation and markdown-it rendering are both sub-second. The Mermaid UMD library is 3.1 MB but is loaded once and cached.

## Scaling expectations

| Vault size | File tree | Graph | Notes |
|------------|-----------|-------|-------|
| ~10 files | <1s | <1s | Development test vault |
| ~100 files | ~2s | ~3s | Estimated |
| ~440 files | ~7s | ~13s | Measured (SZ-BOTTLEFILL) |
| ~1000 files | ~15s | ~30s | Estimated (linear scaling) |

Scaling is roughly linear with file count because the dominant cost is per-file I/O through Nextcloud's API.

## Possible optimisations (not yet implemented)

1. **Server-side caching:** Cache the graph and file tree, invalidate on file changes via Nextcloud's `IEventDispatcher` filesystem hooks. This would be the biggest single improvement.
2. **Background indexing:** Pre-compute the link graph on a Nextcloud background job. Serve the cached result immediately; rebuild periodically or on-demand.
3. **Incremental graph updates:** Only reprocess files that changed since the last request. Requires storing a file modification map.
4. **Lazy file tree:** Load subdirectories on expand rather than the full tree upfront. Reduces initial load but adds latency to folder expansion.

These are future options. The current stateless design is simpler to maintain and has no data to migrate or lose. See [[Design Philosophy#Simplicity over completeness]].

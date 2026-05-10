---
title: Known Limitations
tags: [quality, limitations]
---

# Known Limitations

## Not yet implemented

| Feature | Status | Notes |
|---------|--------|-------|
| Dataview queries | Placeholder styling | Dataview blocks are shown as styled code blocks but not executed. The [[Query API]] covers some of the same ground via a different interface. |
| Canvas view | Basic | Canvas files render, but the visual canvas layout (node positioning, zoom/pan) is minimal. |
| Obsidian plugins | Not supported | Community plugin features (e.g. Kanban, Excalidraw) are not rendered. |
| Editing | By design | The app is intentionally read-only. See [[Design Philosophy#Read-only is a feature]]. |
| Themes | Not supported | Obsidian CSS themes are not applied. The app uses Nextcloud's colour scheme. |
| Mobile layout | Basic | The layout works on mobile but is not optimised for small screens. |

## Known performance bounds

- **Vault list:** Scans up to 5 levels deep for `.obsidian/` directories. Deeply nested vaults may not be detected.
- **File tree:** Loads the entire tree upfront. Vaults with thousands of files take several seconds (see [[Performance]]).
- **Graph:** Reads all markdown files on every request. No caching. Scales linearly with file count.
- **Search:** Full-text search scans all files on every query. No index.

## Known edge cases

- **Frontmatter parsing:** Only YAML frontmatter between `---` fences is recognised. TOML or JSON frontmatter is not supported.
- **Wiki-link ambiguity:** When multiple files share the same basename, the app picks the first match (same as Obsidian, but the ordering may differ).
- **Mermaid CSP:** Mermaid is loaded as a UMD bundle (3.1 MB) because Nextcloud's CSP blocks dynamic `import()`. This adds to the initial page weight.
- **PDF embeds:** Rendered via blob URLs to bypass CSP `frame-ancestors`. If the browser blocks blob URLs, PDFs fall back to a download link.

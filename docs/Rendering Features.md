---
title: Rendering Features
tags: [features, rendering, markdown]
---

# Rendering Features

## Obsidian markdown extensions

All rendering is client-side via `markdown-it` with custom plugins in `src/markdown/`.

| Feature | Plugin | Status |
|---------|--------|--------|
| Standard markdown | markdown-it core | Complete |
| Wiki-links `[[target\|display]]` | `wikilinks.js` | Complete |
| Note embeds `![[note]]`, `![[note#section]]` | `embeds.js` | Complete |
| File embeds `![[file.pdf]]`, `![[data.csv]]` | `embeds.js` | Complete (PDF inline, others as links) |
| Callouts `> [!type] title` | `callouts.js` | Complete |
| Tags `#tag` | `tags.js` | Complete |
| Highlights `==text==` | `highlights.js` | Complete |
| LaTeX `$inline$` and `$$block$$` | `math.js` (KaTeX) | Complete |
| Comments `%%hidden%%` | `comments.js` | Complete |
| Task lists `- [ ]` / `- [x]` | `markdown-it-task-lists` | Complete |
| Footnotes `[^1]` | `markdown-it-footnote` | Complete |
| Frontmatter display | `markdown-it-front-matter` | Complete |
| Mermaid diagrams | Mermaid UMD (external) | Complete |
| Syntax highlighting | highlight.js (16 languages) | Complete |
| Dataview queries | — | Placeholder styling only |
| Canvas `.canvas` files | `CanvasViewer.vue` | Basic support |

## Syntax highlighting languages

JavaScript, TypeScript, Python, Bash, CSS, JSON, YAML, XML/HTML, PHP, SQL, Markdown, C, C++, Java, Rust, Go.

## Frontmatter / properties panel

The properties panel (toggled via the menu icon in the title bar) renders YAML frontmatter with:
- Type icons (text, list, date, checkbox, number, tags)
- Tag values as styled pills
- Checkbox values as actual checkboxes (read-only)
- YAML lists as bulleted items

## Note embeds

`![[Note Name]]` embeds the full content of the referenced note inline, rendered through the same markdown pipeline. `![[Note#Section]]` embeds only the matching heading section. `![[Note#^blockid]]` embeds a single block by its block ID.

Embeds are resolved recursively but guarded against infinite loops via a `data-resolved` attribute.

## File embeds

- **PDF:** Rendered inline via `<iframe>` using blob URLs (to bypass Nextcloud's `frame-ancestors` CSP). A "Open in Nextcloud" link appears below.
- **Other files:** Rendered as clickable links that open the file in Nextcloud's Files app using the native URL pattern (`/apps/files/files/{id}?dir={dir}&openfile=true`).

## Hover previews

Hovering over a wiki-link shows a popup preview of the target note after a 1-second delay. The preview opens above the link (or below if there's no space above). The popup is clickable and dismisses on mouse leave.

## Link resolution

Wiki-links are resolved using the same algorithm as Obsidian:
1. Try exact path match (with `.md` appended)
2. Try relative to the current file's directory
3. Try basename match across the vault
4. Case-insensitive fallback for all of the above

Case-mismatched links work but are visually flagged in the [[Graph View]] with red dotted edges.

---
title: Testing
tags: [testing, quality]
---

# Testing

## Test vault

The repository includes a purpose-built test vault at `test_vault/` that exercises all supported Obsidian features:

### Files

| File | Tests |
|------|-------|
| `Welcome.md` | Wiki-links, case-mismatch links (`[[project notes]]`, `[[NESTED NOTE]]`), unresolved links (`[[Future Ideas]]`, `[[Research/Papers to Read]]`), file embeds (`![[sample-report.pdf]]`, `![[data-export.csv]]`), note embeds (`![[Project Notes]]`), callouts, tags, highlights, code blocks, Mermaid diagrams, LaTeX |
| `Project Notes.md` | Frontmatter properties (title, tags, status), wiki-links, standard markdown links to existing and non-existent targets, task lists, dataview blocks |
| `subfolder/Nested Note.md` | Subfolder resolution, case-mismatch wiki-link (`[[WELCOME]]`), standard markdown links with relative paths (`../Project Notes.md`), callouts |
| `Test Canvas.canvas` | Canvas JSON format, file node references, text nodes with wiki-links |
| `sample-report.pdf` | PDF embed rendering, blob URL CSP bypass |
| `data-export.csv` | Non-markdown file embed (clickable link to Nextcloud Files) |

### What the test vault covers

- Wiki-link resolution: exact match, basename match, case-insensitive fallback
- Standard markdown link resolution: relative paths with `../`, URL-encoded spaces
- Embed resolution: note embeds, section embeds, file embeds (PDF, CSV)
- Graph edge extraction: wiki-links, embeds, markdown links, canvas references
- Graph node types: md, canvas, tag, unresolved
- Graph edge types: normal, case-mismatch (red dotted)
- Frontmatter parsing: inline arrays, YAML lists, single values
- Callout rendering: info, warning types
- Syntax highlighting: Python code block
- Mermaid diagrams: flowchart
- LaTeX: inline formula
- Highlight syntax: `==text==`
- Tag extraction: frontmatter tags + inline `#tags`

## Manual test procedure

### After any change

1. Build: `cd custom_apps/obsidian_viewer && npm run build`
2. Restart container: `docker-compose restart nextcloud`
3. Run `occ upgrade` if version was bumped
4. Open `http://localhost:8082/apps/obsidian_viewer/`

### Feature verification checklist

- [ ] App loads (not blank page)
- [ ] TestVault appears in vault selector
- [ ] File tree shows all files (Welcome.md, Project Notes.md, subfolder/, etc.)
- [ ] Non-markdown files visible with appropriate icons
- [ ] Click Welcome.md — renders with all features
- [ ] Wiki-links are clickable and navigate correctly
- [ ] Case-mismatch links resolve (e.g. `[[project notes]]` → `Project Notes.md`)
- [ ] Unresolved links render but don't navigate
- [ ] Note embed (`![[Project Notes]]`) renders inline
- [ ] PDF embed shows inline viewer with "Open in Nextcloud" link
- [ ] CSV embed shows as clickable link
- [ ] Callout renders with appropriate styling
- [ ] Code block has syntax highlighting
- [ ] Mermaid diagram renders
- [ ] LaTeX formula renders
- [ ] Highlights render with background colour
- [ ] Tags render as styled pills
- [ ] Properties panel toggles on/off
- [ ] Hover preview shows on wiki-link hover (1s delay)
- [ ] Graph view shows nodes and edges
- [ ] Graph: case-mismatch edges shown as red dotted lines
- [ ] Graph: unresolved nodes shown in light grey
- [ ] Graph: "Markdown links" toggle adds/removes edges (verify node/edge count changes)
- [ ] Graph: "Existing files only" hides unresolved nodes
- [ ] Graph: tags toggle works
- [ ] Graph: orphans toggle works
- [ ] Graph: settings sliders respond
- [ ] Graph: clicking a node navigates to the note
- [ ] Search tab: basic search returns results
- [ ] Quick switcher (Ctrl+O): opens, navigates
- [ ] Bookmarks: add, remove, persist across page reload
- [ ] Back/forward navigation works

### API verification

```bash
# Vaults
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults'

# Graph without mdLinks
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/graph' \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(f'{len(d[\"nodes\"])} nodes, {len(d[\"edges\"])} edges')"

# Graph with mdLinks
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/graph?mdLinks=1' \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(f'{len(d[\"nodes\"])} nodes, {len(d[\"edges\"])} edges')"

# Search
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/search?q=test'

# Query
curl -s -u admin:admin -H 'OCS-APIREQUEST: true' \
  -H 'Content-Type: application/json' \
  -d '{"fields":["file.name","title","tags"]}' \
  'http://localhost:8082/apps/obsidian_viewer/api/vaults/TestVault/query'
```

### Expected graph counts (TestVault)

| Mode | Nodes | Edges |
|------|-------|-------|
| Without mdLinks | 12 | 17 |
| With mdLinks | 15 | 21 |

## Production vault testing

The app was extensively tested against two real-world vaults:

### TestVault (included in repo)
- 3 markdown files, 1 canvas, 1 PDF, 1 CSV
- Purpose-built to cover all rendering features
- Small enough for quick iteration

### SZ-BOTTLEFILL (private, not in repo)
- 439 markdown files, 4,184 other files, 778 folders
- Real project vault with library documentation (LVGL, etc.)
- Tests performance at scale, case-insensitive resolution across many files
- Revealed that most links were standard markdown links (317 of 441 files), not wiki-links (23 files) — which led to the "Markdown links" toggle feature
- Used for all performance profiling (see [[Performance]])

Testing against real vaults is essential. The test vault validates feature correctness; production vaults validate performance and edge cases that synthetic tests don't cover.

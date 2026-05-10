---
title: Graph View
tags: [features, graph, d3]
---

# Graph View

The graph view renders a force-directed network of all notes, tags, and their relationships using D3.js. It is toggled via the graph icon in the left action bar.

## What the graph shows

- **Nodes:** Every `.md` file, `.canvas` file, `#tag`, and optionally unresolved link targets
- **Edges:** Wiki-links (`[[target]]`), embeds (`![[target]]`), canvas file references, tag associations, and optionally standard markdown links (`[text](target)`)
- **Current file** is highlighted with a ring when navigating from a note to the graph

## Node types and visual encoding

| Type | Colour | Shape |
|------|--------|-------|
| Markdown file | Purple (default) / group colour | Filled circle |
| Canvas file | Orange, dashed stroke | Filled circle |
| Tag | — | Filled circle |
| Unresolved target | Light grey (`#c0c0c0`) | Filled circle |

Node radius scales with the number of connections (degree).

## Edge types

- **Normal:** Solid line
- **Case-mismatched:** Red dotted line (the link resolves, but with different capitalisation)

## Settings panel

The gear icon in the title bar opens the settings panel with four sections:

### Filters
- **Search:** Filter nodes by name using the [[Search]] expression language
- **Tags:** Show/hide tag nodes
- **Attachments:** Show/hide attachment nodes
- **Existing files only:** Hide unresolved (grey) nodes
- **Markdown links:** Include standard `[text](target)` links in addition to `[[wiki-links]]` (triggers a server reload — this is a backend parameter)
- **Orphans:** Show/hide nodes with no connections

### Groups
- Assign colours to nodes matching a search query
- Multiple groups supported, each with its own colour picker

### Display
- **Arrows:** Show directional arrows on edges
- **Text fade threshold:** Zoom level below which node labels fade out
- **Node size:** Base radius multiplier
- **Link thickness:** Edge stroke width

### Forces
All sliders match Obsidian's ranges and behaviour:
- **Centre force** (0–1): Pulls nodes toward the centre
- **Repel force** (0–20): Pushes nodes apart
- **Link force** (0–1): Strength of edge springs
- **Link distance** (0–500): Natural spring length

The force model uses `d3.forceX`/`d3.forceY` (per-node pull) rather than `d3.forceCenter` (centre-of-mass), matching Obsidian's physics. Quadratic scaling maps slider values to effective D3 force strengths.

## Animation

The "Animate" button reveals nodes one by one in chronological order (by file modification time), with each node settling into position before the next appears. Only visible nodes (after filter settings) are animated.

## Navigation

Clicking a node navigates to that note (switches from graph view to the markdown viewer). The graph preserves its state when toggling back.

## Performance

The graph API reads all `.md` and `.canvas` files server-side to extract links. See [[Performance]] for timing data. For a vault with ~440 files, expect ~13 seconds for the API response. The D3 rendering itself is fast (~1–2 seconds for 500 nodes).

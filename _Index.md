# NEXT-OBSIDIAN — Index

Obsidian Viewer for Nextcloud: a read-only Nextcloud app (`obsidian_viewer`) that renders Obsidian vaults stored in Nextcloud in the browser, with Obsidian-flavoured markdown, graph view, search and canvas, and no editing.

Map of content for this vault. The project root **is** the vault — including the material that was here before it.

## Documents

- [[README]] — what the app does, features, requirements, installation, contact and licence (the public front page on GitHub)
- [[Index|Documentation]] — the full documentation set, itself an Obsidian vault
- `LICENCE` — AGPL-3.0-or-later, full text
- `Contacts.md` — everyone this project writes to; read it before composing any outbound message. **Local only, never in git**: the repository is public and the file may hold contacts that are not.

## Notes

### Getting started

- [[Development Setup]] — Docker environment, Node.js, first build
- [[Deployment]] — packaging and installing on a production Nextcloud
- [[Maintenance]] — version bumps, cache clearing, troubleshooting

### Architecture and design

- [[Architecture]] — backend, frontend, data flow, component map
- [[Design Philosophy]] — integration over duplication, interface boundaries
- [[Performance]] — server, client and network breakdown, profiling data
- [[Known Limitations]] — what the viewer does not do, and why

### Features

- [[Rendering Features]] — markdown, wiki-links, embeds, callouts, Mermaid, LaTeX
- [[Graph View]] — force-directed graph, settings, physics model
- [[Search]] — the Obsidian search language, in JS and PHP
- [[Query API]] — metadata queries and external integration

### Project

- [[Motivation]] — why the viewer exists
- [[For Maintainers]] — what a maintainer needs to know first
- [[Testing]] — how the app is tested
- [[Human-AI Collaboration]] — how the app was built
- [[Project Journal]] — development history

### Development vaults

- `test_vault/` — the fixture vault the viewer is tested against (tracked)
- `sandbox_vault/`, `theme_dev_vault/` — scratch vaults (untracked)
- `custom_apps/obsidian_viewer/` — the app source

The four folders above are excluded from this vault's graph and search by `userIgnoreFilters`, so their notes do not surface as project notes.

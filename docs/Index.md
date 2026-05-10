---
title: Obsidian Viewer for Nextcloud
tags: [index, overview]
---

# Obsidian Viewer for Nextcloud

A read-only Nextcloud app that renders Obsidian vaults directly in the browser. No sync, no install, no editing — just open your Nextcloud and browse your vaults the way Obsidian intended.

## Why this exists

See [[Motivation]] for the full story. The short version: Obsidian is an excellent tool for coordinating project information, and Nextcloud is a capable platform for repository management and collaboration. This app bridges the two — letting customers and external teams access vault content as it is meant to be read, without needing to install or synchronise Obsidian.

## Documentation map

### Getting started
- [[Development Setup]] — Docker environment, Node.js, first build
- [[Deployment]] — Packaging, installing on production Nextcloud
- [[Maintenance]] — Version bumps, cache clearing, troubleshooting

### Architecture and design
- [[Architecture]] — Backend, frontend, data flow, component map
- [[Design Philosophy]] — Integration over duplication, interface boundaries
- [[Performance]] — Server/client/network breakdown, bottlenecks, profiling data

### Features
- [[Rendering Features]] — Markdown, wiki-links, embeds, callouts, Mermaid, LaTeX, etc.
- [[Graph View]] — Force-directed graph, settings, physics model
- [[Search]] — Full Obsidian-compatible search language
- [[Query API]] — Metadata queries, external integration, Analytics app

### Quality
- [[Testing]] — Test vault, manual test procedures, production vault testing
- [[Known Limitations]] — What's not yet implemented, known performance bounds

### Project
- [[Project Journal]] — Session-by-session log of all work, time, and milestones
- [[Human-AI Collaboration]] — How this project was built as a human-AI partnership
- [[For Maintainers]] — Architecture decisions open for revision, interface contracts

## Current version

**v0.8.4** — See [[Project Journal]] for the full history.

## Licence

AGPL-3.0 — same as Nextcloud itself.

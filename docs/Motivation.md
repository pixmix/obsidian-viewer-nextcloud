---
title: Motivation
tags: [overview, philosophy]
---

# Motivation

## The problem

Obsidian is a powerful tool for coordinating project information — fast, flexible, and local-first. Nextcloud is a capable self-hosted platform for file management, sharing, and collaboration. Many teams use both together: vaults live in Nextcloud-synced folders, edited locally in Obsidian.

But sharing the *rendered* output is harder. If a project manager builds a vault to coordinate work across teams, every person who needs to read it must:

1. Install Obsidian
2. Set up sync to the Nextcloud folder
3. Keep the vault up to date
4. Learn enough Obsidian to navigate it

For external customers, contractors, or team members who just need to *read* — this is too much friction. They don't need editing. They need a URL they can open in a browser.

## The solution

Obsidian Viewer renders vaults directly inside Nextcloud's web interface. Anyone with Nextcloud access can browse vault content — wiki-links, callouts, embedded notes, graphs, canvases — the way it was intended to be read. No sync, no install, no learning curve beyond clicking links.

## Design constraints

These constraints follow from the use case:

- **Read-only.** Editing creates sync conflicts with the authoritative Obsidian copy. The app is purely a viewer.
- **No data duplication.** The app reads files directly from Nextcloud storage via the existing file API. No import step, no database, no copies.
- **Nextcloud permissions apply.** If a user can't see a folder in the Files app, they can't see it in Obsidian Viewer. The app inherits Nextcloud's access control.
- **Rendering fidelity.** The goal is to make content look as close to Obsidian as possible. A reader shouldn't wonder whether something is rendering correctly.

## What this is not

- Not a replacement for Obsidian — it doesn't edit, create, or manage notes
- Not a general markdown editor or viewer — it specifically targets Obsidian-flavoured features
- Not a Nextcloud Notes alternative — Notes is for creating content in Nextcloud; this is for viewing content authored in Obsidian

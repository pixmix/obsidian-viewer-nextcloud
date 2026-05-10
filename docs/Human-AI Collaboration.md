---
title: Human-AI Collaboration
tags: [collaboration, ai, process]
---

# Human-AI Collaboration

This project was built as a partnership between a human developer (Pietro Mincuzzi) and an AI assistant (Claude, Anthropic's large language model), working through Claude Code — Anthropic's CLI tool for AI-assisted software development.

## The setup

- **Human:** Defines requirements, makes design decisions, tests in the browser, deploys to production, provides feedback on what works and what doesn't
- **AI:** Writes code, debugs issues, reads documentation, profiles performance, maintains project memory across sessions
- **Harness:** Claude Code provides the working environment — file read/write, shell access, web fetching, persistent memory across conversations

## How the collaboration developed

### Phase 1: Architecture agreement (~15 min)

The first session was pure conversation. Pietro described what he wanted — a read-only Nextcloud app for Obsidian vaults — and the AI proposed an architecture (PHP backend, Vue frontend, 6-phase plan). They discussed trade-offs (which rendering library, which graph engine, what to prioritise) and reached agreement before any code was written.

This upfront alignment was essential. Without it, the AI would have made assumptions that needed correction later.

### Phase 2: Rapid implementation (~4 hours)

The next sessions were high-throughput coding. The AI wrote the full app — PHP backend, Vue components, markdown plugins, graph view, search parser — while Pietro tested each feature in the browser and reported issues.

The pattern was:
1. AI writes a feature
2. AI rebuilds and deploys to the Docker dev environment
3. Pietro tests in the browser
4. Pietro reports what works, what doesn't, and what to do next
5. AI fixes, rebuilds, Pietro re-tests

This loop typically took 5–15 minutes per feature. The AI could write code faster than a human, but the human's testing was irreplaceable — rendering fidelity, interaction feel, and "does this actually work the way a user expects" are things the AI cannot evaluate.

### Phase 3: Production feedback (~3 hours)

Once the app was deployed to production Nextcloud with a real vault (440+ files), a new class of issues appeared:
- Performance bottlenecks invisible on a 3-file test vault
- Link resolution edge cases from real-world content
- CSP restrictions that didn't manifest in the Docker dev environment
- Features missing that only became obvious when viewing real project data

This phase required the tightest collaboration. Pietro would describe what he saw (screenshots, console errors, specific behaviour), and the AI would diagnose, fix, and redeploy. Some issues (like the CSP `frame-ancestors` blocking) required multiple rounds of investigation.

### Phase 4: Documentation (~2 hours)

The AI wrote the documentation vault you're reading now, drawing on:
- The project journal maintained across all sessions
- Memory entries from previous conversations
- The current state of the codebase
- Performance profiling data collected during the session

Pietro specified what the documentation should cover and what principles to emphasise. The AI structured and wrote it.

## Time investment

| Measure | Total |
|---------|-------|
| Human time (tested, reviewed, deployed, thought, coded with AI) | ~24 hours |
| AI-active session time (subset of above) | ~15 hours |

The human logged approximately 30 hours on the project dates, of which ~80% was focused on this project. The AI sessions account for about 60% of the human time — the remaining 40% was spent testing in the browser, deploying, reviewing, thinking through design decisions, and other work the AI cannot observe or assist with. See [[Project Journal#Time summary]] for the day-by-day breakdown.

## What worked well

- **Upfront alignment** on architecture and priorities avoided wasted work
- **Fast feedback loops** — the AI rebuilds in seconds, Pietro tests immediately
- **Persistent memory** — the AI maintains a project journal and memory entries across sessions, so context isn't lost between conversations
- **Division of labour** — the AI handles code volume and technical detail; the human handles design judgement and testing
- **Explicit corrections** — when Pietro says "don't do X" or "this is wrong," the AI records it as a feedback memory and doesn't repeat the mistake

## What was harder

- **Browser testing** — the AI cannot open a browser. All UI verification depends on the human. When the human says "it works," the AI trusts that; when the human says "it's broken," the AI must diagnose from descriptions and console output.
- **SSH/deployment** — interactive authentication blocks the AI from deploying directly. The human handles the last mile.
- **Reproducing what worked** — the AI once changed a working packaging command and introduced a bug (excluding `.mjs` files). Lesson learned: proven SOPs should be reused exactly, not improvised.
- **Context limits** — long sessions approach the AI's context window limit, requiring conversation compression. Important details can be lost if not saved to memory or files.

## Tools and environment

- **Claude Code** — Anthropic's CLI tool, providing file system access, shell commands, web fetching, and persistent memory
- **Model:** Claude (Opus 4.6)
- **Editor:** The AI reads and writes files directly — no IDE in the loop
- **Build chain:** npm + Vite on the host, Docker for Nextcloud
- **Deployment:** Manual SCP + SSH (human-driven)

## For other human-AI teams

If you're considering a similar collaboration:

1. **Start with architecture, not code.** Agree on the shape before the AI starts writing.
2. **Test everything yourself.** The AI can write correct-looking code that doesn't work in practice. Your browser is the source of truth.
3. **Be specific with feedback.** "It's broken" is less useful than "the graph shows segments to nowhere when I hover a node with a case-mismatch edge."
4. **Save what works.** When a command, procedure, or approach proves correct, record it. The AI can drift between sessions.
5. **Use memory.** If the AI has a persistent memory system, use it to record decisions, preferences, and lessons learned. It makes future sessions dramatically more productive.
6. **Don't over-delegate.** The AI is a tool, not an autonomous agent. You make the decisions; the AI executes them and surfaces information to help you decide.

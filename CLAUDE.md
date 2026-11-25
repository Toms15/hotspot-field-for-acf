# CLAUDE.md

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `Toms15/hotspot-field-for-acf` (via the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Default canonical labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Workflow

### Commits

Do not add `Co-Authored-By` trailers (or any AI attribution) to commit messages.

### Agent orchestration

The main session acts only as **orchestrator**: it does not implement todos/tickets itself.

- For every todo/ticket, spawn a **Solo** sub-agent (`mcp__solo__spawn_agent`) to implement it.
- Every implementer's output is then reviewed by a **separate Solo sub-agent** before the todo counts as done.
- The orchestrator coordinates, relays review findings back for fixes, updates the issue tracker, and reports to the user.

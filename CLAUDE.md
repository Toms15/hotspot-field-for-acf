# CLAUDE.md

## Agent skills

### Issue tracker

Issues live in GitHub Issues for `Toms15/hotspot-field-for-acf` (via the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Default canonical labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Ground rules for every agent

- **Clean room:** write original code only; never open or download code from the earlier plugins `acf-image-mapping-hotspots` or `image-hotspots-field-for-acf` (ADR 0001).
- **Secrets:** `.env` holds `ACF_PRO_LICENSE`; read it only programmatically, and keep its value out of output, logs and commits. The dev `.htaccess` keeps dotfiles off HTTP.
- **Tickets:** implementers and reviewers finish per `docs/agents/definition-of-done.md`; reviewers also apply `CODING_STANDARDS.md`.

## Workflow

### Commits

Do not add `Co-Authored-By` trailers (or any AI attribution) to commit messages.

### Agent orchestration

The main session acts only as **orchestrator**: it does not implement todos/tickets itself.

- For every todo/ticket, spawn a **Solo** sub-agent (`mcp__solo__spawn_agent`) to implement it.
- Every implementer's output is then reviewed by a **separate Solo sub-agent** before the todo counts as done.
- The orchestrator coordinates, relays review findings back for fixes, updates the issue tracker, and reports to the user.

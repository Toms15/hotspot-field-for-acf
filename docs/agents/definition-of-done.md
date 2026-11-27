# Definition of done

## Implementer

A ticket is done when all of these hold:

1. Work sits on a branch `<issue>-<slug>` from an up-to-date `main`; commits carry no AI attribution.
2. Every acceptance criterion of the ticket is covered by a test at an agreed seam: PHPUnit on the public PHP API, or Playwright in the browser. Later tickets' scope stays out.
3. `npm test` passes locally (PHPCS, Plugin Check, all PHPUnit suites, Playwright).
4. A PR is open with `Closes #<issue>` and a table mapping each acceptance criterion to the test that verifies it, plus any decision the reviewer should weigh. The PR stays unmerged.
5. CI on the PR's latest commit is green.
6. wp-env is stopped.
7. The last line of your final message is `IMPLEMENTATION DONE: <PR URL>`, or `IMPLEMENTATION BLOCKED: <reason>` when only a human can unblock it.

After review fixes: push, wait for green CI, reply on the PR with what changed, then repeat steps 6–7.

## Reviewer

Review only: leave files, branches and the merge to others. Judge the PR against the ticket, the spec (issue #2), `GLOSSARY.md`, `docs/adr/` and `CODING_STANDARDS.md`. Post one review with `gh pr review <pr> --comment`, findings ranked most severe first, each marked **BLOCKER** or **NIT** with file/line and a concrete fix. The last line of your final message is `REVIEW DONE: APPROVED` or `REVIEW DONE: CHANGES REQUESTED (<n> blockers)`.

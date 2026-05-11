---
name: fix-bug
description: Use for diagnosing and fixing a bug with the smallest safe code change and targeted validation.
---

# Fix Bug

Use this skill for bug fixes.

## Required context

- Read `AGENTS.md`.
- Read relevant project documentation for ownership boundaries and validation if it exists.
- Read area-specific docs when the bug touches security, deployment, frontend behavior, generated files, or user data.

## Workflow

1. Reproduce the bug or understand it from logs, tests, reports, or code paths.
2. Identify the likely root cause before editing.
3. Discuss the root cause and viable fix options with the human before editing code.
4. Do not edit code until the human explicitly approves one option or asks you to implement.
5. Avoid speculative or broad changes.
6. Make the smallest fix that addresses the approved root cause.
7. Add a regression test when practical.
8. If the fix changes documented behavior or user-visible behavior, update docs or explain why docs are not needed.
9. If the fix restores intended behavior, documentation may not be needed, but say so in the final response.
10. Run the most relevant validation.
11. Do not weaken tests, delete assertions, or hide failures.
12. Self-review the diff for unrelated changes, documentation impact, and risk-zone impact.

## Stop conditions

Stop and ask if:

- the root cause points outside the approved scope;
- the root cause or fix options have not been discussed with the human yet;
- the fix requires new dependencies, package/lockfile changes, generated edits, or broad refactoring;
- the bug involves auth, security, secrets, public API contracts, deployment, or production config beyond the approved scope;
- validation fails before your changes.

---
name: review-diff
description: Use to review a diff, branch, or set of changes without modifying files.
---

# Review Diff

Use this skill for read-only review.

## Required context

- Read `AGENTS.md`.
- Read relevant project documentation if it exists.
- Read area-specific docs when the diff touches frontend, security, deployment, generated code, or testing.

## Workflow

1. Work read-only. Do not modify files.
2. Inspect the diff and relevant surrounding code.
3. Prioritize correctness, regressions, security, deployment risk, public contracts, generated files, missing tests, and concrete missing documentation.
4. Classify findings by severity.
5. Check whether an intentional behavior change lacks expected documentation, changelog, release notes, API docs, or developer docs.
6. Report missing documentation only when it is concrete and relevant.
7. Avoid vague, stylistic, or speculative findings.
8. Suggest minimal fixes for each concrete issue.
9. If no blocking issues are found, say so and note residual validation/test risk.

## Output

Lead with findings ordered by severity. Include file and line references when possible.
Then include open questions or assumptions, followed by a brief summary only if useful.

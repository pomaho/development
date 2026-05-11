---
name: debug-failing-checks
description: Use when validation, tests, lint, typecheck, build, or CI checks fail and the agent must diagnose before editing.
---

# Debug Failing Checks

Use this skill when a command fails locally or CI reports a failure.

## Required context

- Read `AGENTS.md`.
- Read relevant project documentation if it exists.
- Inspect area-specific docs if the failure involves frontend, security, deployment, or generated files.

## Workflow

1. Capture the failing command, working directory, and relevant error output.
2. Identify whether the failure is likely caused by:
   - recent agent changes;
   - pre-existing issues;
   - environment or missing dependencies;
   - flaky tests;
   - incorrect command or working directory.
3. Do not edit code before identifying the likely cause.
4. Fix only failures related to the approved task and your own changes.
5. Do not delete tests, weaken assertions, skip validation, or mask errors.
6. Do not update documentation just to satisfy a check.
7. Mention documentation impact only if the fix changes behavior or developer workflow.
8. Re-run the narrowest relevant failing check after each fix.
9. If the failure predates your changes, stop and report it as pre-existing.

## Stop conditions

Stop and ask if fixing the failure requires:

- new dependencies;
- package or lockfile changes;
- build/deployment/CI configuration changes;
- generated/vendor/build-output edits;
- broad refactoring;
- touching auth, secrets, public API contracts, or production infrastructure outside the approved scope.

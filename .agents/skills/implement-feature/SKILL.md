---
name: implement-feature
description: Use for implementing a scoped feature where the affected area can be planned before editing.
---

# Implement Feature

Use this skill for scoped feature work.

## Required context

- Read `AGENTS.md`.
- Read relevant project documentation for the touched area if it exists.
- Read area-specific docs as needed for frontend work, security, deployment, generated files, or user data.

## Workflow

1. Identify affected modules, files, tests, and risk zones.
2. Produce a short implementation plan before editing.
3. Wait for plan approval unless the task explicitly says the plan is already approved.
4. Make the smallest safe change; preserve existing architecture and conventions.
5. Prefer local edits over new abstractions.
6. Add or update focused tests when practical and appropriate.
7. If the feature changes user-visible behavior, API behavior, configuration, deployment behavior, saved data format, generated output, public workflows, or developer-facing commands, update relevant docs or explain why docs are not needed.
8. Run relevant validation.
9. Self-review the diff for scope creep, generated files, dependency changes, documentation impact, and risk zones.

## Stop conditions

Stop and ask before continuing if the feature requires:

- new dependencies;
- package or lockfile changes;
- broad refactoring;
- generated/vendor/build-output edits;
- public API contract changes;
- deployment/production config changes outside the approved scope;
- auth/security/secrets/user-data changes outside the approved scope.

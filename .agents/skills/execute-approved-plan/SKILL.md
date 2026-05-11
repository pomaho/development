---
name: execute-approved-plan
description: Use after the human has approved an implementation plan and the agent should proceed autonomously within that approved scope.
---

# Execute Approved Plan

Use this skill only after the human has approved a plan, or when the user explicitly says
the plan is already approved.

## Required context

- Read `AGENTS.md`.
- Read relevant project documentation for the touched area if it exists.

## Workflow

1. Restate the approved scope in one or two sentences.
2. Implement the smallest safe change that satisfies the plan.
3. Stay inside the approved scope and local architecture.
4. Do not ask routine implementation questions while the work remains in scope.
5. Run relevant validation.
6. Fix validation failures caused by your own changes with minimal follow-up edits.
7. Review the final diff before finishing.
8. During self-review, check:
   - Did this change intentionally modify behavior?
   - If yes, were relevant docs updated?
   - If docs were not updated, is there a clear reason?
   - Does the final response include a Documentation impact section?
9. Final response must include Summary, Changed files, Validation, Documentation impact, and Risks / follow-ups.

## Stop conditions

Stop and ask if:

- the approved plan becomes invalid;
- the change expands beyond the approved scope;
- a new dependency is needed;
- package files, lockfiles, package-manager config, generated/vendor files, or build output must change;
- deployment/production infrastructure is affected outside the approved plan;
- auth, security, secrets, public API contracts, or user/customer data are affected outside the approved plan;
- validation fails before your changes;
- broad refactoring becomes necessary;
- the safe validation path is unclear.

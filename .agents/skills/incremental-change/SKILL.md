---
name: incremental-change
description: Use an incremental workflow with plan approval, test-first intent per step, focused optional commits, immediate step review, and separate fixes for blocking findings.
---

# Incremental Change

Use this skill when the user asks for `incremental-change`, "my flow", "мой flow",
"мой workflow", "commit-sized flow", "commit-sized TDD flow", focused incremental steps,
or focused incremental commits.

## Workflow

1. Read `AGENTS.md`.
2. Infer the relevant validation from the repository's existing scripts, tests, and conventions.
3. Read relevant project documentation for the touched area if it exists.
4. Produce a short plan split into focused commit-sized logical steps.
5. Wait for human approval unless the user explicitly says the plan is already approved.
6. For each approved step:
   - state the intended focused test or explicit test gap before editing;
   - run or add the focused test where practical;
   - implement the smallest change for that step;
   - run targeted validation for the touched area;
   - if the user asked for commits, commit the focused change using `.agents/skills/commit-workflow/SKILL.md`;
   - immediately review the committed diff, staged diff, or current step diff;
   - fix Critical, High, Medium, and otherwise blocking findings in separate focused follow-up steps or commits unless the user explicitly defers them.
7. Report remaining Low findings, validation limits, and skipped checks.
8. When the work produced multiple commits or a substantial diff, include a final section titled
   `Description For CR`: goal/problem, expected behavior, non-goals, step or commit scope,
   validation, risks/follow-ups. Keep implementation details out unless they affect review.

## Boundaries

- Do not add dependencies, edit generated/vendor/build-output files, touch package files,
  change public contracts, or edit supervised-only areas unless those changes are explicitly
  included in the approved plan.
- Do not move to the next step when a new logical code path or file lacks a focused test or
  an explicitly reported test gap.
- If a planned feature would duplicate non-trivial existing logic, add a refactor-first step
  before feature work. Keep the refactor separate with tests, validation, and immediate review.
- Stop and ask when the plan becomes invalid, scope expands, validation fails before the
  agent's changes, or the work crosses a stop condition from `AGENTS.md`.

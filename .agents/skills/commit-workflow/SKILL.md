---
name: commit-workflow
description: Create focused git commits when the user asks the agent to commit work, commit after steps, or work in commit-sized increments.
---

# Commit Workflow

Use this skill when the user asks the agent to create git commits, commit after implementation
steps, or work in commit-sized increments.

## Before Committing

1. Read `AGENTS.md`.
2. Run `git status --short`.
3. Identify files changed by the current task.
4. Keep user or unrelated changes out of the commit.
5. If unrelated changes are mixed into a file you edited, inspect the diff and stage only
   the hunks that belong to the approved task.

## Commit-Sized Steps

- Keep each commit narrowly scoped and cohesive.
- Do not mix behavior changes, refactors, UI polish, docs, and validation fixes in one commit
  unless they are inseparable.
- If review finds a Critical, High, Medium, or otherwise blocking issue after a commit-sized
  step, fix it in a separate corrective commit unless the user explicitly defers it.
- Report progress by commit-sized step when the user asked for incremental commits.

## Validation

- Run targeted validation for the changed behavior before staging.
- Run broader validation before the final commit or final handoff when practical.
- If validation cannot run, record the skipped command and reason in the final response.
- If a validation failure is pre-existing or unrelated, stop and report it before committing more work.

## Staging

- Stage only files and hunks that belong to the approved task.
- Review staged content with `git diff --cached`.
- Verify no secrets, generated output, unrelated formatting churn, dependency changes, or large data are staged unless explicitly approved.
- Do not use destructive git commands such as `git reset --hard` or `git checkout --` unless the user explicitly requested that operation.

## Commit Message

Use a concise imperative subject that describes the committed change. Add a body only when
the reason, validation, or risk is not obvious from the diff.

## After Committing

- Review the committed diff for correctness, regressions, security, missing tests, and documentation impact before moving to the next planned step.
- Run `git status --short` and confirm only expected uncommitted changes remain.
- In the final response, list commit hashes and messages when commits were created.
- If no commit was created because validation failed, staging was unsafe, or scope changed, explain the reason.

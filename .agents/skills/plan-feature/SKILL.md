---
name: plan-feature
description: Use when the user wants to plan, work through, shape, refine, or specify a feature before implementation, including feature planning, requirements, UX requirements, product requirements, architecture notes, or pre-implementation discovery; produce a Feature Brief without editing implementation files.
---

# Plan Feature

Use this skill when the user asks to plan, shape, explore, specify, or refine a new feature before implementation.

Trigger examples include:

- "work through this feature";
- "plan a new feature";
- "shape this feature idea";
- "define feature requirements";
- "prepare feature requirements";
- "plan this feature";
- "write UX requirements";
- "draft architecture notes".

## Required Reading

1. Read `AGENTS.md`.
2. Read relevant project documentation if it exists.
3. Read only the docs that match the feature area, such as architecture, frontend patterns, security, deployment, generated code, or testing.

## Workflow

1. Confirm that this is planning only when the user has not already made that explicit.
2. Inspect the relevant code before making claims about current behavior, architecture, data flow, contracts, or implementation feasibility. Use repository search and focused file reads to ground conclusions in actual code.
3. Ask focused clarification questions if the feature cannot be planned safely from the current context or verified code.
4. Produce a Feature Brief covering problem, users/use cases, proposed behavior, scope, UX states, code context, domain impact, data/API/persistence impact, acceptance criteria, risks, open questions, and suggested implementation slices.
5. Break suggested implementation slices into atomic stages that can be implemented and validated independently. For each stage, state the goal, likely touched area, and focused validation signal.
6. Do not turn the slices into an approved implementation plan unless the user asks for that next step.
7. When the Feature Brief is complete, explicitly ask whether the user wants the brief saved as a document under `docs/features/*.md` or kept only in the chat.
8. If the user asks to move to implementation before answering the save question, ask the save question first and wait for the answer before starting any implementation workflow.

## Boundaries

- Do not edit source, tests, configs, generated files, infrastructure, deployment files, package files, lockfiles, or implementation docs during feature planning.
- Do not create or update a feature planning document unless the user explicitly asks to persist the plan.
- If the user asks to save the plan, create or update only the focused planning document unless they explicitly request another location.
- Do not start implementation after planning until the user has answered whether to save the Feature Brief. Implementation also requires a separate user request and the normal `implement-feature` or `execute-approved-plan` workflow.
- Do not invent current behavior, module names, APIs, data shapes, saved formats, deployment behavior, or constraints. If code inspection does not verify a point, label it as an assumption or open question.

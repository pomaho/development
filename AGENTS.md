**Tradeoff:** Prefer caution over speed; use judgment on trivial tasks.

## 1. Think Before Coding
Don't assume or hide confusion. Surface tradeoffs.
- State assumptions explicitly; if uncertain, ask.
- If multiple interpretations exist, present them; don't pick silently.
- Mention simpler approaches; push back when warranted.
- If unclear, stop, name confusion, ask.

## 2. Simplicity First
Minimum code that solves the task. Nothing speculative.
- No extra features, one-off abstractions, or unrequested flexibility/config.
- No error handling for impossible cases.
- If 200 lines can be 50, rewrite.
Check: would a senior call this overcomplicated? If yes, simplify.

## 3. Surgical Changes
Touch only what's required. Clean up only your mess.
- Don't improve adjacent code, comments, or formatting.
- Don't refactor what isn't broken.
- Match existing style, even if you'd do it differently.
- Mention unrelated dead code; don't remove it.
- Remove only orphans your changes created: unused imports/vars/functions/files.
- Don't delete pre-existing dead code unless asked.
Rule: every changed line must map directly to the request.

## 4. Goal-Driven Execution
Define success criteria. Loop until verified.
- "Add validation" -> test invalid inputs, pass.
- "Fix bug" -> reproduce with a test, fix.
- "Refactor" -> tests pass before/after.

For multi-step:
1. Step -> verify: specific check
2. Step -> verify: specific check

Strong criteria enable autonomy; weak ones require clarification.

## 5. amoCRM Access: Library vs. Raw HTTP
`amocrm/amocrm-api-library` (`AmoCRMApiClient`) is a dependency but is currently used only for OAuth (token exchange/refresh, long-lived tokens — see `AmoTokenManager`, `AmoOAuthTokenExchanger`, `AmoClientFactory`). Everything else talks to amoCRM through the hand-rolled `AmoFallbackHttpClient`.
- **Writing to amoCRM** (creating/updating leads, notes, etc.) or **managing webhook subscriptions**: use the library's typed services (`Leads`, `Webhooks`, etc.) instead of hand-building HTTP payloads.
- **Reading/syncing bulk entity data** (leads, tasks, events): keep using `AmoFallbackHttpClient`, matching the existing pattern in `CrmAuditService`/`AmoTaskSyncService`/`AmoWebhookService`. Do not migrate this to the library — its typed models normalize the response, and our schema depends on storing amoCRM's exact raw JSON (`raw` column) so reports can read arbitrary/future fields (`custom_fields_values`, `embedded.loss_reason`, event `value_before`/`value_after`) without a sync-code change. The library also doesn't auto-paginate or auto-retry rate limits, so it wouldn't simplify this path anyway.
- Don't migrate existing working sync code to the library "for consistency" — it's live production data with no functional gain and real regression risk.

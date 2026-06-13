# amoCRM Account Workspace Architecture

## Purpose

The project is evolving from a simple amoCRM connector into a client workspace for managing, syncing, automating, and analyzing multiple amoCRM accounts. The UI and backend should be organized around the selected amoCRM account and around stable amoCRM domain areas, not around the order in which features were added.

## Workspace Model

Each `AmoAccount` is a separate workspace:

```text
AmoAccount
  -> CRM structure
  -> CRM data
  -> Synchronization
  -> Automation
  -> Analytics
  -> Integrations
  -> Settings
  -> Logs
```

All account-scoped data must remain tied to `amo_account_id`. Services should receive `AmoAccount` explicitly and must not use a global amoCRM account.

## UI Navigation Groups

### Overview

Purpose: health and quick account status.

Pages:

```text
/amo-accounts/{id}/dashboard
/amo-accounts/{id}
```

Content:

- connection status;
- auth status;
- last sync status;
- entity counts;
- recent errors;
- quick actions.

### CRM Data

Purpose: local snapshots of operational amoCRM entities.

Pages:

```text
/amo-accounts/{id}/leads
/amo-accounts/{id}/crm/contacts        future
/amo-accounts/{id}/crm/companies       future
/amo-accounts/{id}/crm/tasks           future
/amo-accounts/{id}/crm/events          future
/amo-accounts/{id}/crm/unsorted        future
```

Entities:

- leads;
- contacts;
- companies;
- tasks;
- events;
- unsorted leads.

### CRM Structure

Purpose: amoCRM configuration and metadata.

Pages:

```text
/amo-accounts/{id}/pipelines
/amo-accounts/{id}/crm-audit/fields
/amo-accounts/{id}/catalogs
/amo-accounts/{id}/users
/amo-accounts/{id}/roles
```

Entities:

- pipelines;
- statuses;
- custom fields;
- catalogs and lists;
- lead loss reasons;
- sources;
- users;
- groups;
- roles and rights.

### Synchronization

Purpose: control data loading and consistency.

Pages:

```text
/amo-accounts/{id}/lead-sync-schedules
/amo-accounts/{id}/events-sync
/amo-accounts/{id}/crm-audit
```

Processes:

- configured lead sync schedules;
- one-time manual loading;
- event sync;
- structure sync;
- webhook event processing;
- sync run history.

Principle: webhooks provide near-real-time updates; scheduler provides consistency; manual sync provides initial loading and recovery.

### Automation

Purpose: actions that mutate amoCRM data.

Pages:

```text
/amo-accounts/{id}/pipelines/create
/amo-accounts/{id}/pipelines/{pipelineId}/clone
/amo-accounts/{id}/pipelines/transfer-leads
/amo-accounts/{id}/responsibility-redistribution
```

Processes:

- create pipelines;
- clone pipelines;
- move leads between pipelines;
- redistribute responsibility;
- future bulk field updates;
- future webhook scenarios.

Automation pages should be visually separated from read-only data pages because they can change client CRM data.

### Analytics

Purpose: reports and dashboards built from local data.

Pages:

```text
/amo-accounts/{id}/task-statistics
/widgets/amo/{publicKey}/task-overdue-dashboard
```

Future reports:

- recruiter performance;
- source performance;
- SLA by stage;
- manager activity;
- duplicate control;
- custom dashboards.

Principle: reports should be built from local database snapshots and projections, not directly from amoCRM API on each page load.

### Integrations

Purpose: installable account features and amoCRM dashboard widgets.

Pages:

```text
/amo-accounts/{id}/integrations
/amo-accounts/{id}/widgets
/amo-accounts/{id}/widgets/{dashboard_widget}/settings
/amo-oauth/external
```

Entities:

- integration modules;
- dashboard widgets;
- OAuth connections;
- public installation flow.

### Settings And Logs

Purpose: account configuration, secrets, public endpoints, and diagnostics.

Pages:

```text
/amo-accounts/{id}/edit
/logs/api
```

Settings:

- account profile;
- auth credentials;
- webhook URL;
- widget public keys;
- report configuration;
- API logs.

## Backend Service Layers

Current `app/Services/Amo` should gradually move toward these domain folders:

```text
app/Services/Amo/
  Client/
    AmoClientFactory.php
    AmoTokenManager.php
    AmoFallbackHttpClient.php

  Accounts/
    AmoAccountProfileService.php

  Structure/
    PipelinesService.php
    CustomFieldsService.php
    CatalogsService.php
    UsersRolesService.php

  Entities/
    LeadsService.php
    ContactsService.php
    CompaniesService.php
    TasksService.php
    EventsService.php

  Sync/
    LeadSyncScheduleRunner.php
    CrmAuditService.php
    TaskStatisticsSyncService.php
    EventSyncService.php

  Webhooks/
    AmoWebhookService.php

  Automation/
    LeadTransferService.php
    ResponsibilityRedistributionService.php
    PipelineCloneService.php

  Analytics/
    TaskStatisticsService.php
    RecruiterReportsService.php
    DashboardCacheService.php
```

This should be done incrementally. Avoid broad namespace moves unless tests are already covering the affected area.

## Data Layers

The application should keep four separate data layers:

```text
1. Structure snapshots
   Pipelines, statuses, fields, catalogs, users, roles.

2. Entity snapshots
   Leads, contacts, companies, tasks, events.

3. Sync state
   Schedules, sync runs, webhook events, cursors, errors.

4. Analytics projections
   Precomputed report rows, dashboard cache versions, aggregate tables.
```

The current `crm_entity_snapshots` table is acceptable for MVP and flexible sync workflows. If entity volume grows, split high-volume entities into dedicated tables:

```text
crm_leads
crm_contacts
crm_companies
crm_tasks
crm_events
```

## Module Direction

Future account features should be packaged as modules with a consistent contract:

```php
interface AmoAccountModule
{
    public function code(): string;
    public function name(): string;
    public function navigation(): array;
    public function routes(): array;
    public function widgets(): array;
    public function requiredScopes(): array;
}
```

Candidate modules:

- `CrmStructureModule`;
- `LeadSyncModule`;
- `TaskAnalyticsModule`;
- `RecruitingAnalyticsModule`;
- `PipelineAutomationModule`;
- `WebhookMonitorModule`;
- `DashboardWidgetsModule`.

Each module should own its routes, menu entries, permission requirements, sync jobs, widgets, and configuration schema.

## Implementation Slices

### Stage 1: Navigation Grouping

Goal: group existing account pages by domain in the sidebar without moving routes.

Validation:

- affected React layout builds;
- existing route links remain valid;
- UI tests/static assertions cover new labels.

### Stage 2: Account Overview Cleanup

Goal: make account show/dashboard pages surface sync health, webhook URL, and quick actions in the new categories.

Validation:

- feature tests for account page props;
- React build.

### Stage 3: Sync Center

Goal: consolidate lead schedules, event sync, CRM audit, and webhook event visibility into a sync-focused section.

Validation:

- sync schedule tests;
- webhook tests;
- route tests for admin/viewer access.

### Stage 4: Analytics Section

Goal: separate task/recruiting/dashboard reports from raw CRM data and automation actions.

Validation:

- report endpoint tests;
- widget iframe tests;
- React build.

### Stage 5: Service Namespace Refactor

Goal: move services into domain folders with minimal behavior changes.

Validation:

- full unit suite for amo services;
- targeted feature tests for controllers using moved services.

### Stage 6: Module Contract

Goal: introduce a module registry for navigation, routes, widgets, and feature metadata.

Validation:

- module registry tests;
- navigation tests;
- no route regressions.

## Guardrails

- Do not move routes and services in the same step unless unavoidable.
- Do not change amoCRM API behavior while doing UI grouping.
- Keep account-scoped data tied to `amo_account_id`.
- Keep secrets out of UI, logs, and frontend payloads.
- Keep webhook handling asynchronous.
- Keep scheduler-based sync as a consistency layer even after webhook support.

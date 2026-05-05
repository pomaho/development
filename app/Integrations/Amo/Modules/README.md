# amoCRM modules

MVP module:

- `UsersAudit`: sync users and roles, show rights, admin summary, active/inactive summary.
- `PipelinesBuilder`: create amoCRM lead pipelines and statuses from account-scoped UI.
- `CrmAudit`: sync CRM metadata and operational snapshots for process diagnostics.

Reserved future modules:

- `LeadsAnalyticsModule`
- `CallsAnalyticsModule`
- `SourcesAnalyticsModule`
- `WebhookMonitorModule`
- `WidgetsModule`
- `CustomDashboardsModule`
- `IntegrationsHealthModule`

Each module should have a code, name, enabled flag, config, routes, a service class, and optional dashboard widgets.

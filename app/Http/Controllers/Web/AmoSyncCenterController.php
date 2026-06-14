<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAmoWebhookEventJob;
use App\Models\AmoAccount;
use App\Models\AmoWebhookEvent;
use App\Models\LeadSyncSchedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AmoSyncCenterController extends Controller
{
    private const WEBHOOK_RETRY_DELAY_SECONDS = 60;

    public function __invoke(AmoAccount $amoAccount): Response
    {
        $this->authorize('view', $amoAccount);

        return Inertia::render('AmoAccounts/SyncCenter/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'summary' => [
                'lead_schedules_total' => LeadSyncSchedule::query()->where('amo_account_id', $amoAccount->id)->count(),
                'lead_schedules_enabled' => LeadSyncSchedule::query()->where('amo_account_id', $amoAccount->id)->where('is_enabled', true)->count(),
                'webhook_events_pending' => AmoWebhookEvent::query()->where('amo_account_id', $amoAccount->id)->where('status', AmoWebhookEvent::STATUS_PENDING)->count(),
                'webhook_events_failed' => AmoWebhookEvent::query()->where('amo_account_id', $amoAccount->id)->where('status', AmoWebhookEvent::STATUS_FAILED)->count(),
            ],
            'can' => [
                'retry_webhooks' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'recentWebhookEvents' => AmoWebhookEvent::query()
                ->where('amo_account_id', $amoAccount->id)
                ->latest('received_at')
                ->limit(10)
                ->get()
                ->map(fn (AmoWebhookEvent $event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'entity_type' => $event->entity_type,
                    'entity_id' => $event->entity_id,
                    'status' => $event->status,
                    'received_at' => $event->received_at?->toDateTimeString(),
                    'processed_at' => $event->processed_at?->toDateTimeString(),
                    'error_message' => $event->error_message,
                ]),
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'retry_failed_webhooks' => route('amo-accounts.sync.webhooks.retry-failed', $amoAccount),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'sync_center' => route('amo-accounts.sync.index', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                    'lead_sync_schedules' => route('amo-accounts.lead-sync-schedules.index', $amoAccount),
                    'events_sync' => route('amo-accounts.events-sync.index', $amoAccount),
                    'task_statistics' => route('amo-accounts.task-statistics.index', $amoAccount),
                    'responsibility_redistribution' => route('amo-accounts.responsibility-redistribution.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }

    public function retryFailedWebhooks(AmoAccount $amoAccount): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $events = AmoWebhookEvent::query()
            ->where('amo_account_id', $amoAccount->id)
            ->where('status', AmoWebhookEvent::STATUS_FAILED)
            ->latest('received_at')
            ->limit(100)
            ->get();

        foreach ($events as $event) {
            $event->forceFill([
                'status' => AmoWebhookEvent::STATUS_PENDING,
                'error_message' => null,
                'processed_at' => null,
            ])->save();

            ProcessAmoWebhookEventJob::dispatch($event->id)->delay(now()->addSeconds(self::WEBHOOK_RETRY_DELAY_SECONDS));
        }

        return back()->with('status', "Webhook events отправлены на повторную обработку: {$events->count()}.");
    }
}

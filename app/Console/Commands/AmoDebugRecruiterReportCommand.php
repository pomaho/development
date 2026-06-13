<?php

namespace App\Console\Commands;

use App\Models\AmoAccount;
use App\Models\AmoAccountDashboardWidget;
use App\Services\Amo\Analytics\AmoTaskStatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AmoDebugRecruiterReportCommand extends Command
{
    protected $signature = 'amo:debug-recruiter-report {accountId} {--from=} {--to=} {--pipeline-id=} {--field-id=}';
    protected $description = 'Show local diagnostics for the recruiter leads report.';

    public function handle(AmoTaskStatisticsService $statisticsService): int
    {
        $account = AmoAccount::query()->findOrFail((int) $this->argument('accountId'));
        $installation = AmoAccountDashboardWidget::query()
            ->where('amo_account_id', $account->id)
            ->whereHas('widget', fn ($query) => $query->where('code', 'task_overdue_dashboard'))
            ->first();
        $config = $installation?->config ?? [];

        if ($this->option('pipeline-id')) {
            $config['pipeline_id'] = (int) $this->option('pipeline-id');
            $config['pipeline_name'] = null;
        }

        if ($this->option('field-id')) {
            $config['recruiter_field_id'] = (int) $this->option('field-id');
            $config['recruiter_field_name'] = null;
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;
        $diagnostics = $statisticsService->recruiterLeadDistributionDiagnostics($account, $from, $to, $config);

        $this->info("Account: {$account->name} ({$account->base_domain})");
        $this->table(['Metric', 'Value'], [
            ['pipeline_id', $diagnostics['pipeline_id'] ?? 'all'],
            ['pipeline_name', $diagnostics['pipeline_name'] ?? '-'],
            ['field_id', $diagnostics['field_id'] ?? '-'],
            ['field_name', $diagnostics['field_name']],
            ['field_found', $diagnostics['field_found'] ? 'yes' : 'no'],
            ['field_type', $diagnostics['field_type'] ?? '-'],
            ['field_enum_count', $diagnostics['field_enum_count']],
            ['synced_leads_total', $diagnostics['synced_leads_total']],
            ['period_leads_total', $diagnostics['period_leads_total']],
            ['pipeline_leads_total', $diagnostics['pipeline_leads_total']],
            ['pipeline_period_leads_total', $diagnostics['pipeline_period_leads_total']],
            ['pipeline_first_lead_created_at', $diagnostics['pipeline_first_lead_created_at'] ?? '-'],
            ['pipeline_last_lead_created_at', $diagnostics['pipeline_last_lead_created_at'] ?? '-'],
            ['leads_with_field', $diagnostics['leads_with_field']],
            ['assigned_leads', $diagnostics['assigned_leads']],
        ]);

        $this->line('Field values:');
        $this->table(
            ['Enum ID', 'Value', 'Count', 'Matched enum'],
            collect($diagnostics['field_values'])->map(fn (array $value): array => [
                $value['enum_id'] ?? '-',
                $value['value'],
                $value['count'],
                $value['matched_enum'] ? 'yes' : 'no',
            ])->all()
        );

        $this->line('Sample leads:');
        $this->table(
            ['ID', 'Name', 'Pipeline', 'Status', 'Created at', 'Values'],
            collect($diagnostics['sample_leads'])->map(fn (array $lead): array => [
                $lead['id'],
                $lead['name'],
                $lead['pipeline_id'] ?? '-',
                $lead['status_id'] ?? '-',
                $lead['created_at'] ?? '-',
                collect($lead['field_values'])->map(fn (array $value): string => ($value['enum_id'] ?? '-').': '.$value['value'])->implode('; '),
            ])->all()
        );

        return self::SUCCESS;
    }
}

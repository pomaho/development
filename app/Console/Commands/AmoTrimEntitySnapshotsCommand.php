<?php

namespace App\Console\Commands;

use App\Models\CrmEntitySnapshot;
use Illuminate\Console\Command;

class AmoTrimEntitySnapshotsCommand extends Command
{
    protected $signature = 'amo:trim-entity-snapshots
                            {--entity-type= : tasks or events (default: both)}
                            {--dry-run : Show what would be trimmed without writing}
                            {--chunk=500 : Records per batch}';

    protected $description = 'Trim raw JSON in crm_entity_snapshots to only needed fields, freeing disk space.';

    private const TASK_KEYS = ['is_completed', 'complete_till', 'text', '_task_statistics'];

    public function handle(): int
    {
        $entityType = $this->option('entity-type');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) ($this->option('chunk') ?: 500);

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        if ($entityType === null || $entityType === 'tasks') {
            $this->trimTasks($dryRun, $chunk);
        }

        if ($entityType === null || $entityType === 'events') {
            $this->trimEvents($dryRun, $chunk);
        }

        return self::SUCCESS;
    }

    private function trimTasks(bool $dryRun, int $chunk): void
    {
        $this->info('Trimming tasks...');

        $totalBefore = 0;
        $totalAfter = 0;
        $updated = 0;
        $skipped = 0;

        CrmEntitySnapshot::query()
            ->where('entity_type', 'tasks')
            ->whereNotNull('raw')
            ->orderBy('id')
            ->chunkById($chunk, function ($records) use ($dryRun, &$totalBefore, &$totalAfter, &$updated, &$skipped) {
                foreach ($records as $record) {
                    $raw = $record->raw;

                    if (! is_array($raw)) {
                        $skipped++;
                        continue;
                    }

                    $trimmed = $this->trimTaskRaw($raw);

                    $jsonBefore = json_encode($raw);
                    $jsonAfter = json_encode($trimmed);

                    $totalBefore += strlen($jsonBefore);
                    $totalAfter += strlen($jsonAfter);

                    if ($jsonBefore === $jsonAfter) {
                        $skipped++;
                        continue;
                    }

                    if (! $dryRun) {
                        $record->raw = $trimmed;
                        $record->save();
                    }

                    $updated++;
                }

                $this->output->write('.');
            });

        $this->newLine();
        $this->line(sprintf(
            'Tasks: %d updated, %d skipped. Size: %s → %s (saved %s, %.0f%%)',
            $updated,
            $skipped,
            $this->humanBytes($totalBefore),
            $this->humanBytes($totalAfter),
            $this->humanBytes($totalBefore - $totalAfter),
            $totalBefore > 0 ? ($totalBefore - $totalAfter) / $totalBefore * 100 : 0,
        ));
    }

    private function trimEvents(bool $dryRun, int $chunk): void
    {
        $this->info('Trimming events...');

        $totalBefore = 0;
        $updated = 0;
        $skipped = 0;

        CrmEntitySnapshot::query()
            ->where('entity_type', 'events')
            ->whereNotNull('raw')
            ->orderBy('id')
            ->chunkById($chunk, function ($records) use ($dryRun, &$totalBefore, &$updated, &$skipped) {
                foreach ($records as $record) {
                    $raw = $record->raw;

                    if (! is_array($raw)) {
                        $skipped++;
                        continue;
                    }

                    $totalBefore += strlen(json_encode($raw));

                    if (! $dryRun) {
                        $record->raw = null;
                        $record->save();
                    }

                    $updated++;
                }

                $this->output->write('.');
            });

        $this->newLine();
        $this->line(sprintf(
            'Events: %d nulled, %d skipped. Freed ~%s.',
            $updated,
            $skipped,
            $this->humanBytes($totalBefore),
        ));
    }

    private function trimTaskRaw(array $raw): array
    {
        $trimmed = [
            'is_completed' => (bool) ($raw['is_completed'] ?? false),
            'complete_till' => $raw['complete_till'] ?? null,
            'text' => $raw['text'] ?? null,
            'result' => $raw['result'] ?? null,
        ];

        if (isset($raw['_task_statistics']) && is_array($raw['_task_statistics'])) {
            $stats = $raw['_task_statistics'];
            $trimmedStats = [
                'completed_at' => $stats['completed_at'] ?? null,
                'completed_by' => $stats['completed_by'] ?? null,
                'completed_event_id' => $stats['completed_event_id'] ?? null,
            ];
            $trimmed['_task_statistics'] = array_filter($trimmedStats, fn ($v) => $v !== null);
        }

        return $trimmed;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / 1024, 0) . ' KB';
    }
}

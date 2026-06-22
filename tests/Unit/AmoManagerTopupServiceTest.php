<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AmoAccount;
use App\Models\AmoCredential;
use App\Models\CrmEntitySnapshot;
use App\Models\CrmPipelineStatusSnapshot;
use App\Services\Amo\Analytics\AmoManagerTopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AmoManagerTopupServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PIPELINE_ID = 10904262;
    private const MANAGER_FIELD_ID = 845835;
    private const PREPAYMENT_FIELD_ID = 845975;
    private const TOPUP_DATE_FIELD_ID = 845843;

    private AmoAccount $account;
    private array $config;
    private AmoManagerTopupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AmoAccount::query()->create([
            'name' => 'Test Client',
            'base_domain' => 'test.amocrm.ru',
        ]);
        $this->account->credentials()->create([
            'auth_type' => AmoCredential::AUTH_LONG_LIVED,
            'access_token' => 'test-token',
        ]);

        $this->config = [
            'pipeline_id' => self::PIPELINE_ID,
            'manager_field_id' => self::MANAGER_FIELD_ID,
            'prepayment_field_id' => self::PREPAYMENT_FIELD_ID,
            'topup_date_field_id' => self::TOPUP_DATE_FIELD_ID,
        ];

        $this->service = new AmoManagerTopupService();
    }

    // ─── breakdown() ──────────────────────────────────────────────────────────

    public function test_breakdown_returns_correct_topup_formula(): void
    {
        $this->createLead('1', 'Иванов', price: 150_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(1, $result['summary']['dealCount']);
        $this->assertEquals(100_000, $result['summary']['topupTotal']);
        $this->assertCount(1, $result['managers']);
        $this->assertSame('Иванов', $result['managers'][0]['name']);
        $this->assertEquals(100_000, $result['managers'][0]['topupTotal']);
    }

    public function test_breakdown_skips_deal_when_topup_is_zero(): void
    {
        $this->createLead('1', 'Иванов', price: 50_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
        $this->assertCount(0, $result['managers']);
    }

    public function test_breakdown_skips_deal_when_topup_is_negative(): void
    {
        $this->createLead('1', 'Иванов', price: 30_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_skips_deal_without_manager(): void
    {
        $this->createLead('1', manager: null, price: 100_000, prepayment: 20_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_skips_deal_without_prepayment_field(): void
    {
        $this->createLeadRaw('1', customFields: [
            ['field_id' => self::MANAGER_FIELD_ID, 'values' => [['value' => 'Иванов']]],
            ['field_id' => self::TOPUP_DATE_FIELD_ID, 'values' => [['value' => (string) now()->timestamp]]],
        ], price: 100_000);

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_skips_deal_with_zero_price(): void
    {
        $this->createLead('1', 'Иванов', price: 0, prepayment: 0, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_filters_by_topup_date_field_period(): void
    {
        // Inside period
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        // Outside period — last month
        $this->createLead('2', 'Петров', price: 200_000, prepayment: 50_000, topupDate: now()->subMonth());

        $result = $this->service->breakdown(
            $this->account,
            now()->startOfMonth(),
            now()->endOfMonth(),
            $this->config,
        );

        $this->assertSame(1, $result['summary']['dealCount']);
        $this->assertSame('Иванов', $result['managers'][0]['name']);
    }

    public function test_breakdown_groups_by_manager_and_sums_correctly(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Иванов', price: 80_000, prepayment: 30_000, topupDate: now());
        $this->createLead('3', 'Петров', price: 200_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(3, $result['summary']['dealCount']);
        $this->assertEquals(280_000, $result['summary']['topupTotal']); // (80+50) + 150
        $this->assertCount(2, $result['managers']);

        // Sorted descending by topupTotal
        $this->assertSame('Петров', $result['managers'][0]['name']);
        $this->assertEquals(150_000, $result['managers'][0]['topupTotal']);
        $this->assertSame(1, $result['managers'][0]['dealCount']);

        $this->assertSame('Иванов', $result['managers'][1]['name']);
        $this->assertEquals(130_000, $result['managers'][1]['topupTotal']); // 80k + 50k
        $this->assertSame(2, $result['managers'][1]['dealCount']);
    }

    public function test_breakdown_excludes_won_stages(): void
    {
        $this->createStatus(amo_status_id: 999, type: 142); // won
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now(), statusId: 999);

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_excludes_lost_stages(): void
    {
        $this->createStatus(amo_status_id: 888, type: 143); // lost
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now(), statusId: 888);

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_excludes_postponed_stage_by_name(): void
    {
        // Name must contain 'тлож' substring (matches both 'Отложено' and 'отложено' via LIKE '%тлож%')
        $this->createStatus(amo_status_id: 777, name: 'Отложено', type: 0);
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now(), statusId: 777);


        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    public function test_breakdown_includes_active_stage(): void
    {
        $this->createStatus(amo_status_id: 555, name: 'В работе', type: 0);
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now(), statusId: 555);

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(1, $result['summary']['dealCount']);
    }

    public function test_breakdown_filters_by_pipeline_id(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now(), pipelineId: self::PIPELINE_ID);
        $this->createLead('2', 'Петров', price: 100_000, prepayment: 20_000, topupDate: now(), pipelineId: 99999);

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(1, $result['summary']['dealCount']);
        $this->assertSame('Иванов', $result['managers'][0]['name']);
    }

    public function test_breakdown_applies_manager_filter(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Петров', price: 200_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown(
            $this->account,
            now()->startOfMonth(),
            now()->endOfMonth(),
            $this->config,
            ['Иванов'],
        );

        $this->assertSame(1, $result['summary']['dealCount']);
        $this->assertSame('Иванов', $result['managers'][0]['name']);
        // allManagerNames still contains both (computed before manager filter)
        $this->assertContains('Иванов', $result['allManagerNames']);
        $this->assertContains('Петров', $result['allManagerNames']);
    }

    public function test_breakdown_builds_monthly_breakdown(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Иванов', price: 60_000, prepayment: 10_000, topupDate: now()->addMonth());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->addMonth()->endOfMonth(), $this->config);

        $this->assertCount(2, $result['monthlyBreakdown']);
        $this->assertSame(now()->format('Y-m'), $result['monthlyBreakdown'][0]['month']);
        $this->assertEquals(80_000, $result['monthlyBreakdown'][0]['total']);
        $this->assertSame(now()->addMonth()->format('Y-m'), $result['monthlyBreakdown'][1]['month']);
        $this->assertEquals(50_000, $result['monthlyBreakdown'][1]['total']);
    }

    public function test_breakdown_returns_summary_counts(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Петров', price: 200_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->breakdown($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $this->assertSame(2, $result['summary']['managerCount']);
        $this->assertSame(2, $result['summary']['dealCount']);
        $this->assertEquals(230_000, $result['summary']['topupTotal']); // 80k + 150k
    }

    public function test_breakdown_parses_topup_date_as_unix_timestamp(): void
    {
        $ts = Carbon::create(2026, 1, 15)->timestamp;
        $this->createLeadRaw('1', customFields: [
            ['field_id' => self::MANAGER_FIELD_ID, 'values' => [['value' => 'Иванов']]],
            ['field_id' => self::PREPAYMENT_FIELD_ID, 'values' => [['value' => '20000']]],
            ['field_id' => self::TOPUP_DATE_FIELD_ID, 'values' => [['value' => (string) $ts]]],
        ], price: 100_000);

        $result = $this->service->breakdown(
            $this->account,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 1, 31),
            $this->config,
        );

        $this->assertSame(1, $result['summary']['dealCount']);
    }

    public function test_breakdown_excludes_deal_outside_date_range(): void
    {
        $ts = Carbon::create(2026, 3, 1)->timestamp;
        $this->createLeadRaw('1', customFields: [
            ['field_id' => self::MANAGER_FIELD_ID, 'values' => [['value' => 'Иванов']]],
            ['field_id' => self::PREPAYMENT_FIELD_ID, 'values' => [['value' => '20000']]],
            ['field_id' => self::TOPUP_DATE_FIELD_ID, 'values' => [['value' => (string) $ts]]],
        ], price: 100_000);

        $result = $this->service->breakdown(
            $this->account,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 1, 31),
            $this->config,
        );

        $this->assertSame(0, $result['summary']['dealCount']);
    }

    // ─── leads() ──────────────────────────────────────────────────────────────

    public function test_leads_returns_deals_sorted_by_topup_desc(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Петров', price: 300_000, prepayment: 50_000, topupDate: now());
        $this->createLead('3', 'Сидоров', price: 200_000, prepayment: 100_000, topupDate: now());

        $result = $this->service->leads($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $topups = array_column($result['leads'], 'topup');
        $this->assertEquals([250_000, 100_000, 80_000], $topups);
    }

    public function test_leads_filters_by_manager(): void
    {
        $this->createLead('1', 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        $this->createLead('2', 'Петров', price: 200_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->leads($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config, 'Иванов');

        $this->assertSame(1, $result['total']);
        $this->assertSame('Иванов', $result['leads'][0]['manager']);
    }

    public function test_leads_returns_price_prepayment_and_topup(): void
    {
        $this->createLead('1', 'Иванов', price: 150_000, prepayment: 50_000, topupDate: now());

        $result = $this->service->leads($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config);

        $lead = $result['leads'][0];
        $this->assertSame(150_000.0, $lead['price']);
        $this->assertSame(50_000.0, $lead['prepayment']);
        $this->assertSame(100_000.0, $lead['topup']);
        $this->assertSame('Иванов', $lead['manager']);
    }

    public function test_leads_indicates_limited_results(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createLead((string) $i, 'Иванов', price: 100_000, prepayment: 20_000, topupDate: now());
        }

        $result = $this->service->leads($this->account, now()->startOfMonth(), now()->endOfMonth(), $this->config, limit: 3);

        $this->assertSame(5, $result['total']);
        $this->assertCount(3, $result['leads']);
        $this->assertTrue($result['limited']);
        $this->assertSame(3, $result['limit']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createLead(
        string $externalId,
        ?string $manager,
        float $price,
        float $prepayment,
        Carbon $topupDate,
        int $pipelineId = self::PIPELINE_ID,
        int $statusId = 100,
    ): CrmEntitySnapshot {
        $customFields = [];

        if ($manager !== null) {
            $customFields[] = [
                'field_id' => self::MANAGER_FIELD_ID,
                'values' => [['value' => $manager]],
            ];
        }

        $customFields[] = [
            'field_id' => self::PREPAYMENT_FIELD_ID,
            'values' => [['value' => (string) $prepayment]],
        ];

        $customFields[] = [
            'field_id' => self::TOPUP_DATE_FIELD_ID,
            'values' => [['value' => (string) $topupDate->timestamp]],
        ];

        return $this->createLeadRaw($externalId, $customFields, $price, $pipelineId, $statusId);
    }

    private function createLeadRaw(
        string $externalId,
        array $customFields,
        float $price,
        int $pipelineId = self::PIPELINE_ID,
        int $statusId = 100,
    ): CrmEntitySnapshot {
        return CrmEntitySnapshot::query()->create([
            'amo_account_id' => $this->account->id,
            'entity_type' => 'leads',
            'external_id' => $externalId,
            'name' => 'Lead ' . $externalId,
            'pipeline_id' => $pipelineId,
            'status_id' => $statusId,
            'entity_created_at' => now(),
            'custom_fields_values' => $customFields,
            'raw' => ['price' => $price],
            'synced_at' => now(),
        ]);
    }

    private function createStatus(int $amo_status_id, string $name = 'Этап', int $type = 0): CrmPipelineStatusSnapshot
    {
        return CrmPipelineStatusSnapshot::query()->create([
            'amo_account_id' => $this->account->id,
            'amo_pipeline_id' => self::PIPELINE_ID,
            'amo_status_id' => $amo_status_id,
            'name' => $name,
            'type' => $type,
            'sort' => 10,
            'raw' => [],
            'synced_at' => now(),
        ]);
    }
}

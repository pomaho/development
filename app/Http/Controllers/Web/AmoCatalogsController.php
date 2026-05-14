<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Services\Amo\AmoCatalogsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmoCatalogsController extends Controller
{
    public function index(AmoAccount $amoAccount, AmoCatalogsService $catalogsService): Response
    {
        $catalogs = [];
        $error = null;

        try {
            $catalogs = $catalogsService->fetchCatalogs($amoAccount);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return Inertia::render('AmoAccounts/Catalogs/Index', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'catalogs' => collect($catalogs)->map(fn (array $catalog): array => [
                'id' => $catalog['id'] ?? null,
                'name' => $catalog['name'] ?? '-',
                'type' => $catalog['type'] ?? 'regular',
                'sort' => $catalog['sort'] ?? null,
                'can_add_elements' => (bool) ($catalog['can_add_elements'] ?? false),
                'can_show_in_cards' => (bool) ($catalog['can_show_in_cards'] ?? false),
                'can_link_multiple' => (bool) ($catalog['can_link_multiple'] ?? false),
            ])->values(),
            'error' => $error,
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount),
        ]);
    }

    public function storeCatalog(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'can_add_elements' => ['nullable', 'boolean'],
            'can_show_in_cards' => ['nullable', 'boolean'],
            'can_link_multiple' => ['nullable', 'boolean'],
        ]);
        $data['can_add_elements'] = $request->boolean('can_add_elements');
        $data['can_show_in_cards'] = $request->boolean('can_show_in_cards');
        $data['can_link_multiple'] = $request->boolean('can_link_multiple');

        $catalogsService->createCatalog($amoAccount, $data);

        return back()->with('status', 'Список отправлен в amoCRM.');
    }

    public function storeElements(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'catalog_id' => ['required', 'integer', 'min:1'],
            'elements' => ['required', 'string', 'max:10000'],
        ]);

        $names = collect(preg_split('/\R/u', $data['elements']) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values()
            ->all();

        $catalogsService->createElements($amoAccount, (int) $data['catalog_id'], $names);

        return back()->with('status', 'Элементы списка отправлены в amoCRM.');
    }

    public function storeChainedListField(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'in:leads,customers'],
            'sort' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'levels' => ['required', 'array', 'min:2', 'max:5'],
            'levels.*.title' => ['required', 'string', 'max:255'],
            'levels.*.catalog_id' => ['required', 'integer', 'min:1'],
            'levels.*.parent_catalog_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $levels = collect($data['levels'])->values()->all();
        foreach ($levels as $index => $level) {
            $levels[$index]['parent_catalog_id'] = $index === 0 ? 0 : (int) $levels[$index - 1]['catalog_id'];
        }
        $data['levels'] = $levels;

        $catalogsService->createChainedListField($amoAccount, $data);

        return back()->with('status', 'Связанное поле отправлено в amoCRM.');
    }

    private function links(AmoAccount $amoAccount): array
    {
        return [
            'dashboard' => route('dashboard'),
            'amo_accounts' => route('amo-accounts.index'),
            'oauth' => route('amo-oauth.external.index'),
            'api_logs' => route('logs.api'),
            'logout' => route('logout'),
            'store_catalog' => route('amo-accounts.catalogs.store', $amoAccount),
            'store_elements' => route('amo-accounts.catalogs.elements.store', $amoAccount),
            'store_chained_list_field' => route('amo-accounts.catalogs.chained-list-fields.store', $amoAccount),
            'current_account' => [
                'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                'show' => route('amo-accounts.show', $amoAccount),
                'users' => route('amo-accounts.users', $amoAccount),
                'roles' => route('amo-accounts.roles', $amoAccount),
                'leads' => route('amo-accounts.leads', $amoAccount),
                'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                'catalogs' => route('amo-accounts.catalogs.index', $amoAccount),
                'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                'integrations' => route('amo-accounts.integrations', $amoAccount),
                'widgets' => route('amo-accounts.widgets', $amoAccount),
            ],
        ];
    }
}

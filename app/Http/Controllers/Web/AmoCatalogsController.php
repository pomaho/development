<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogElementsRequest;
use App\Http\Requests\StoreCatalogRequest;
use App\Models\AmoAccount;
use App\Services\Amo\Structure\AmoCatalogsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmoCatalogsController extends Controller
{
    public function index(AmoAccount $amoAccount, AmoCatalogsService $catalogsService): Response
    {
        $catalogs = [];
        $enumFields = [];
        $error = session('catalogs_error');

        try {
            $catalogs = $catalogsService->fetchCatalogs($amoAccount);
            $enumFields = $catalogsService->fetchEnumCustomFields($amoAccount);
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
            'enumFields' => collect($enumFields)->map(fn (array $field): array => [
                'id' => $field['id'] ?? null,
                'name' => $field['name'] ?? '-',
                'type' => $field['type'] ?? null,
                'entity_type' => $field['entity_type'] ?? null,
                'sort' => $field['sort'] ?? null,
                'enums' => collect($field['enums'] ?? [])->map(fn (array $enum): array => [
                    'id' => $enum['id'] ?? null,
                    'value' => $enum['value'] ?? '',
                    'sort' => $enum['sort'] ?? null,
                ])->values(),
            ])->values(),
            'composePreview' => session('catalogs_compose_preview'),
            'composeForm' => session('catalogs_compose_form') ?? [
                'parent_catalog_id' => '',
                'child_catalog_id' => '',
                'template' => '{parent} {child}',
                'mappings' => '',
            ],
            'error' => $error,
            'can' => [
                'sync' => request()->user()?->can('sync', $amoAccount) ?? false,
            ],
            'links' => $this->links($amoAccount),
        ]);
    }

    public function storeCatalog(StoreCatalogRequest $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $data = $request->validated();
        $data['can_add_elements'] = $request->boolean('can_add_elements');
        $data['can_show_in_cards'] = $request->boolean('can_show_in_cards');
        $data['can_link_multiple'] = $request->boolean('can_link_multiple');

        $catalogsService->createCatalog($amoAccount, $data);

        return back()->with('status', 'Список отправлен в amoCRM.');
    }

    public function storeElements(StoreCatalogElementsRequest $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $data = $request->validated();

        $names = collect(preg_split('/\R/u', $data['elements']) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values()
            ->all();

        $catalogsService->createElements($amoAccount, (int) $data['catalog_id'], $names);

        return back()->with('status', 'Элементы списка отправлены в amoCRM.');
    }

    public function previewComposedElements(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $this->validatedComposeData($request);

        try {
            $preview = $catalogsService->previewComposedElementNames(
                $amoAccount,
                (int) $data['parent_catalog_id'],
                (int) $data['child_catalog_id'],
                $data['template'],
                $this->parseMappings($data['mappings'] ?? '')
            );
        } catch (\Throwable $exception) {
            return back()
                ->with('catalogs_compose_form', $this->composeForm($data))
                ->with('catalogs_error', $exception->getMessage());
        }

        return back()
            ->with('catalogs_compose_preview', $preview)
            ->with('catalogs_compose_form', $this->composeForm($data))
            ->with('status', "Предпросмотр готов: {$preview['ready']} элементов к переименованию.");
    }

    public function applyComposedElements(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $this->validatedComposeData($request);

        try {
            $result = $catalogsService->applyComposedElementNames(
                $amoAccount,
                (int) $data['parent_catalog_id'],
                (int) $data['child_catalog_id'],
                $data['template'],
                $this->parseMappings($data['mappings'] ?? '')
            );
        } catch (\Throwable $exception) {
            return back()
                ->with('catalogs_compose_form', $this->composeForm($data))
                ->with('catalogs_error', $exception->getMessage());
        }

        return back()
            ->with('catalogs_compose_preview', $result)
            ->with('catalogs_compose_form', $this->composeForm($data))
            ->with('status', "Переименовано элементов: {$result['updated']}.");
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

    public function updateEnumField(Request $request, AmoAccount $amoAccount, AmoCatalogsService $catalogsService): RedirectResponse
    {
        $this->authorize('sync', $amoAccount);

        $data = $request->validate([
            'entity_type' => ['required', 'string', 'in:leads,contacts,companies'],
            'field_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'values' => ['required', 'string', 'max:20000'],
        ]);

        $data['enums'] = collect(preg_split('/\R/u', $data['values']) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(function (string $line): array {
                [$id, $value] = array_pad(explode('|', $line, 2), 2, null);

                if ($value === null) {
                    return ['value' => trim($id)];
                }

                return [
                    'id' => is_numeric(trim($id)) ? (int) trim($id) : null,
                    'value' => trim($value),
                ];
            })
            ->values()
            ->all();

        $catalogsService->updateEnumCustomField($amoAccount, $data['entity_type'], (int) $data['field_id'], $data);

        return back()->with('status', 'Значения поля отправлены в amoCRM.');
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
            'compose_elements_preview' => route('amo-accounts.catalogs.elements.compose-preview', $amoAccount),
            'compose_elements_apply' => route('amo-accounts.catalogs.elements.compose-apply', $amoAccount),
            'store_chained_list_field' => route('amo-accounts.catalogs.chained-list-fields.store', $amoAccount),
            'update_enum_field' => route('amo-accounts.catalogs.enum-fields.update', $amoAccount),
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

    private function validatedComposeData(Request $request): array
    {
        return $request->validate([
            'parent_catalog_id' => ['required', 'integer', 'min:1'],
            'child_catalog_id' => ['required', 'integer', 'min:1', 'different:parent_catalog_id'],
            'template' => ['required', 'string', 'max:255'],
            'mappings' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    private function parseMappings(?string $mappings): array
    {
        return collect(preg_split('/\R/u', (string) $mappings) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(function (string $line): ?array {
                [$child, $parent] = array_pad(explode('|', $line, 2), 2, null);

                if ($parent === null || trim($child) === '' || trim($parent) === '') {
                    return null;
                }

                return [
                    'child' => trim($child),
                    'parent' => trim($parent),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function composeForm(array $data): array
    {
        return [
            'parent_catalog_id' => (string) $data['parent_catalog_id'],
            'child_catalog_id' => (string) $data['child_catalog_id'],
            'template' => $data['template'],
            'mappings' => $data['mappings'] ?? '',
        ];
    }
}

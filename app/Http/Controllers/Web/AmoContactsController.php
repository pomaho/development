<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use App\Models\CrmEntitySnapshot;
use App\Services\Exports\TableExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmoContactsController extends Controller
{
    private const CATEGORY_FIELD_ID = 845859;

    public function __invoke(Request $request, AmoAccount $amoAccount): Response
    {
        return Inertia::render('AmoAccounts/Contacts', [
            'account' => [
                'id' => $amoAccount->id,
                'name' => $amoAccount->name,
                'base_domain' => $amoAccount->base_domain,
            ],
            'contacts' => $this->filteredQuery($request, $amoAccount)
                ->latest('entity_updated_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (CrmEntitySnapshot $entity): array => [
                    'id' => $entity->id,
                    'external_id' => $entity->external_id,
                    'type' => $entity->entity_type,
                    'name' => $entity->name,
                    'category' => $entity->entity_type === 'contacts' ? $this->category($entity) : null,
                    'responsible_user_id' => $entity->responsible_user_id,
                    'entity_created_at' => $entity->entity_created_at?->toDateTimeString(),
                    'entity_updated_at' => $entity->entity_updated_at?->toDateTimeString(),
                ]),
            'types' => [
                ['value' => 'contacts', 'label' => 'Контакт'],
                ['value' => 'companies', 'label' => 'Компания'],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'type' => $request->filled('type') ? (string) $request->input('type') : '',
                'category' => $request->string('category')->toString(),
            ],
            'links' => [
                'dashboard' => route('dashboard'),
                'amo_accounts' => route('amo-accounts.index'),
                'oauth' => route('amo-oauth.external.index'),
                'api_logs' => route('logs.api'),
                'logout' => route('logout'),
                'export' => route('amo-accounts.contacts.export', array_merge(['amo_account' => $amoAccount], $request->query())),
                'reset' => route('amo-accounts.contacts', $amoAccount),
                'current_account' => [
                    'dashboard' => route('amo-accounts.dashboard', $amoAccount),
                    'show' => route('amo-accounts.show', $amoAccount),
                    'users' => route('amo-accounts.users', $amoAccount),
                    'roles' => route('amo-accounts.roles', $amoAccount),
                    'leads' => route('amo-accounts.leads', $amoAccount),
                    'contacts' => route('amo-accounts.contacts', $amoAccount),
                    'pipelines' => route('amo-accounts.pipelines.index', $amoAccount),
                    'crm_audit' => route('amo-accounts.crm-audit.index', $amoAccount),
                    'integrations' => route('amo-accounts.integrations', $amoAccount),
                    'widgets' => route('amo-accounts.widgets', $amoAccount),
                ],
            ],
        ]);
    }

    public function export(Request $request, AmoAccount $amoAccount, TableExportService $export): StreamedResponse
    {
        $entities = $this->filteredQuery($request, $amoAccount)->latest('entity_updated_at')->get();

        return $export->csv("amo-contacts-{$amoAccount->id}.csv", [
            'ID',
            'Тип',
            'Название',
            'Категория',
            'Ответственный',
            'Создан',
            'Обновлён',
            'Поля',
            'Raw',
        ], $entities->map(function (CrmEntitySnapshot $entity): array {
            return [
                $entity->external_id,
                $entity->entity_type === 'contacts' ? 'Контакт' : 'Компания',
                $entity->name,
                $entity->entity_type === 'contacts' ? $this->category($entity) : null,
                $entity->responsible_user_id,
                $entity->entity_created_at,
                $entity->entity_updated_at,
                $entity->custom_fields_values,
                $entity->raw,
            ];
        }));
    }

    private function filteredQuery(Request $request, AmoAccount $amoAccount): Builder
    {
        $query = CrmEntitySnapshot::query()
            ->where('amo_account_id', $amoAccount->id)
            ->whereIn('entity_type', ['contacts', 'companies']);

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('external_id', 'like', $search));
        }

        if ($request->filled('type')) {
            $query->where('entity_type', $request->input('type'));
        }

        if ($request->filled('category')) {
            $query->where('entity_type', 'contacts')->whereRaw(
                "JSON_SEARCH(custom_fields_values, 'one', ?, NULL, '$[*].values[*].value') IS NOT NULL",
                [$request->input('category')]
            );
        }

        return $query;
    }

    private function category(CrmEntitySnapshot $entity): ?string
    {
        foreach ($entity->custom_fields_values ?? [] as $field) {
            $fieldId = (int) ($field['field_id'] ?? $field['id'] ?? 0);
            if ($fieldId === self::CATEGORY_FIELD_ID) {
                $value = (string) ($field['values'][0]['value'] ?? '');

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}

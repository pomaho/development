<?php

declare(strict_types=1);

namespace App\Services\Amo\Webhooks;

use App\Models\AmoAccount;
use App\Services\Amo\Client\AmoFallbackHttpClient;
use Illuminate\Support\Arr;

final class AmoWebhooksRegistrationService
{
    public const AVAILABLE_EVENTS = [
        'Сделки' => [
            'add_lead' => 'Создание',
            'update_lead' => 'Изменение',
            'delete_lead' => 'Удаление',
            'restore_lead' => 'Восстановление',
            'status_lead' => 'Смена статуса',
            'responsible_lead' => 'Смена ответственного',
            'note_lead' => 'Добавление примечания',
        ],
        'Контакты' => [
            'add_contact' => 'Создание',
            'update_contact' => 'Изменение',
            'delete_contact' => 'Удаление',
            'restore_contact' => 'Восстановление',
            'note_contact' => 'Добавление примечания',
        ],
        'Компании' => [
            'add_company' => 'Создание',
            'update_company' => 'Изменение',
            'delete_company' => 'Удаление',
            'restore_company' => 'Восстановление',
            'note_company' => 'Добавление примечания',
        ],
        'Задачи' => [
            'add_task' => 'Создание',
            'update_task' => 'Изменение',
            'delete_task' => 'Удаление',
            'complete_task' => 'Выполнение',
        ],
    ];

    public function __construct(private readonly AmoFallbackHttpClient $http)
    {
    }

    /**
     * Returns all webhooks registered in amoCRM for this account.
     *
     * @return array<int, array{destination: string, settings: string[], created_at: int|null}>
     */
    public function list(AmoAccount $account): array
    {
        $response = $this->http->get($account, '/api/v4/webhooks');

        return Arr::get($response, '_embedded.webhooks', []);
    }

    /**
     * Registers a new webhook subscription in amoCRM.
     *
     * @param string[] $events e.g. ['leads:add', 'leads:update']
     */
    public function register(AmoAccount $account, string $destination, array $events): void
    {
        $this->http->post($account, '/api/v4/webhooks', [
            'destination' => $destination,
            'settings' => $events,
        ]);
    }

    /**
     * Unsubscribes a webhook from amoCRM by its destination URL.
     */
    public function unsubscribe(AmoAccount $account, string $destination): void
    {
        $this->http->delete($account, '/api/v4/webhooks', [
            'destination' => $destination,
        ]);
    }

    /**
     * Returns a flat list of all valid event keys across all groups.
     *
     * @return string[]
     */
    public static function allValidEvents(): array
    {
        return array_keys(Arr::collapse(self::AVAILABLE_EVENTS));
    }
}

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
            'leads:add' => 'Создание',
            'leads:update' => 'Изменение',
            'leads:delete' => 'Удаление',
            'leads:restore' => 'Восстановление',
            'leads:status_changed' => 'Смена статуса',
            'leads:responsible_changed' => 'Смена ответственного',
            'leads:note_add' => 'Добавление примечания',
        ],
        'Контакты' => [
            'contacts:add' => 'Создание',
            'contacts:update' => 'Изменение',
            'contacts:delete' => 'Удаление',
            'contacts:restore' => 'Восстановление',
        ],
        'Компании' => [
            'companies:add' => 'Создание',
            'companies:update' => 'Изменение',
            'companies:delete' => 'Удаление',
            'companies:restore' => 'Восстановление',
        ],
        'Задачи' => [
            'tasks:add' => 'Создание',
            'tasks:update' => 'Изменение',
            'tasks:delete' => 'Удаление',
            'tasks:complete' => 'Выполнение',
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

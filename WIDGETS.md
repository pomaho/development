# Виджеты дашбордов amoCRM

Документ описывает все существующие виджеты, их архитектуру и инструкции по добавлению новых.

---

## Содержание

1. [Архитектура](#архитектура)
2. [Существующие виджеты](#существующие-виджеты)
3. [Как добавить новый виджет](#как-добавить-новый-виджет)
4. [Шаблон карточки задачи](#шаблон-карточки-задачи)
5. [Компоненты UI (переиспользуемые)](#компоненты-ui-переиспользуемые)

---

## Архитектура

### Слои системы

```
БД (dashboard_widgets)              → тип виджета (код, название)
БД (amo_account_dashboard_widgets)  → привязка виджета к клиенту + config JSON
PHP Service                         → бизнес-логика, агрегация сделок из CrmEntitySnapshot
PHP Controller                      → HTTP-методы, маршруты
TSX Page                            → визуализация (React/Inertia)
```

### Данные сделок

Все сделки хранятся локально в таблице `crm_entity_snapshots` (`entity_type = 'lead'`).
Синхронизируются через вебхуки amoCRM и по расписанию.

Кастомные поля сделки — JSON-колонка `custom_fields_values`.
Структура полей — таблица `crm_custom_field_snapshots`.
Этапы воронок — таблица `crm_pipeline_status_snapshots`.

### Ключевые файлы

| Файл | Роль |
|------|------|
| `app/Services/Amo/Analytics/AmoTaskStatisticsService.php` | Вся бизнес-логика агрегации |
| `app/Http/Controllers/Widget/AmoTaskOverdueDashboardController.php` | HTTP-контроллер виджетов |
| `app/Http/Controllers/Web/AmoAccountWidgetsController.php` | Настройки виджета в админке |
| `app/Http/Requests/UpdateWidgetSettingsRequest.php` | Валидация формы настроек |
| `routes/web.php` | Маршруты виджетов |
| `resources/js/Pages/Widgets/Amo/TaskOverdueDashboardV2.tsx` | TSX-страница V2 |
| `resources/js/Pages/Widgets/Amo/_shared/uiKit.tsx` | Общие UI-примитивы (`ReportSection`, `AccentSummary`, `SectionSkeleton`, `SectionError`, `EmptyState`, `WidgetHeader`, `rub()`, `rubFull()`, `buildUrl()`, `LoadState<T>`) |
| `resources/js/Pages/AmoAccounts/Widgets/Settings.tsx` | Страница настроек виджета |

### Общий код vs код одного клиента

Если виджет по-настоящему универсален (все параметры приходят через `config`, без хардкода конкретных ID полей/воронки клиента) — его PHP- и TSX-код лежит в общих папках (`app/Services/Amo/Analytics/`, `app/Http/Controllers/Widget/`, `resources/js/Pages/Widgets/Amo/`), как `AmoTaskStatisticsService`/`AmoTaskOverdueDashboardController`.

Если виджет хардкодит ID полей/воронки конкретного клиента как **дефолт** (не только опционально через `config`) — значит по факту он написан под одного клиента, даже если технически конфигурируем. Такой код кладём в `Clients/{ИмяКлиента}/`:

```
app/Services/Amo/Analytics/Clients/Eurohome/
app/Http/Controllers/Widget/Clients/Eurohome/
resources/js/Pages/Widgets/Amo/Clients/Eurohome/
```

Пример: `AmoManagerTopupService`/`AmoProductGroupService` хардкодят ID полей Eurohome (845975, 845835, 845843, 871211 и т.д.) как дефолты и реально настроены только для account_id=3 — поэтому лежат в `Clients/Eurohome/`, а не в общей папке.

Чисто презентационные примитивы без клиентской специфики (`ReportSection`, `WidgetHeader` и т.д.) — общие для всех, живут в `_shared/`.

---

## Существующие виджеты

### 1. `task_overdue_dashboard` — Устаревший дашборд (V1)

> **Статус: устаревший.** Заменён виджетом V2. Оставлен для совместимости.

- **URL:** `/widgets/amo/{publicKey}/task-overdue-dashboard`
- **TSX:** `resources/js/Pages/Widgets/Amo/TaskOverdueDashboard.tsx`
- **Конфиг:** нет настраиваемых полей

---

### 2. `task_overdue_dashboard_v2` — Основной дашборд рекрутинга (V2)

> **Статус: активный.** Используется для аналитики работы рекрутеров.

- **URL:** `/widgets/amo/{publicKey}/task-overdue-dashboard-v2`
- **TSX:** `resources/js/Pages/Widgets/Amo/TaskOverdueDashboardV2.tsx`

#### Что показывает

| Секция | Описание |
|--------|----------|
| **Встал в график** | Сделки с рекрутером + менеджером, достигшие финального этапа. Зелёный бар-чарт по рекрутерам. |
| **Отчёт по сделкам / Рекрутер** | Все сделки по каждому рекрутеру + сколько передано менеджеру. |
| **По командам** | Передачи менеджерам, сгруппированные по полю "Команда". |
| **По городам** | Передачи, сгруппированные по полю "Город". |
| **По источникам** | Передачи, сгруппированные по полю "Источник". |
| **Задачи** | Статистика задач по пользователям (выполненные, просроченные). |
| **Проект / Город / Вакансия** | Таблица сделок с рекрутером + менеджером по проекту, городу, вакансии, источнику. |
| **Подробно по рекрутерам** | Раскрывающиеся карточки: команда → город → источник для каждого рекрутера. |

#### Кликабельность

Все числа-счётчики кликабельны — открывают попап со списком сделок и ссылками в amoCRM.

#### API-эндпоинты

| Маршрут | Метод сервиса | Назначение |
|---------|--------------|------------|
| `/recruiter-leads` | `recruiterLeadDistribution` | Сводка по рекрутерам |
| `/recruiter-team-city-breakdown` | `recruiterTeamCityBreakdown` | Разбивка по командам/городам |
| `/task-statistics` | `statistics` | Статистика задач |
| `/project-city-vacancy` | `projectCityVacancyBreakdown` | Таблица проект/город/вакансия |
| `/project-city-vacancy-leads` | `projectCityVacancyLeads` | Список сделок для попапа |
| `/recruiter-schedule` | `recruiterScheduleBreakdown` | Сделки на финальном этапе |
| `/user-overdue-tasks` | `userOverdueTasks` | Просроченные задачи пользователя |

#### Конфигурация (настройки виджета)

| Ключ конфига | Что задаёт | По умолчанию |
|-------------|-----------|-------------|
| `pipeline_id` / `pipeline_name` | Воронка для фильтрации | Все воронки |
| `recruiter_field_id` / `recruiter_field_name` | Поле сделки "Рекрутер" | Авто по имени "Рекрутер" |
| `manager_field_id` / `manager_field_name` | Поле сделки "Менеджер" | Авто по имени "Менеджер" |
| `team_field_id` / `team_field_name` | Поле сделки "Команда" | Авто по имени "Команда" |
| `city_field_id` / `city_field_name` | Поле сделки "Город" | Авто по имени "Город" |
| `source_field_id` / `source_field_name` | Поле сделки "Источник" | Авто по имени "Источник" |
| `success_status_id` / `success_status_name` | Этап "Встал в график" | Секция скрыта если не задан |

---

---

### 3. `manager_topup_dashboard` — Доплаты по менеджерам

> **Статус: активный.** Клиент: eurohomenew.amocrm.ru (аккаунт ID 3).

- **URL:** `/widgets/amo/{publicKey}/manager-topup`
- **TSX:** `resources/js/Pages/Widgets/Amo/Clients/Eurohome/ManagerTopupDashboard.tsx` (экспортирует переиспользуемый `ManagerTopupContent`)
- **Сервис:** `app/Services/Amo/Analytics/Clients/Eurohome/AmoManagerTopupService.php`
- **Контроллер:** `app/Http/Controllers/Widget/Clients/Eurohome/AmoManagerTopupController.php`

#### Что показывает

| Секция | Описание |
|--------|----------|
| **Карточки** | Количество менеджеров, сделок и общая сумма доплат |
| **Бар-чарт** | Горизонтальные полосы по каждому менеджеру с суммой доплат |
| **График по месяцам** | Столбчатая диаграмма по полю «Месяц предполагаемой доплаты» |
| **Таблица** | Сводка по менеджерам с долей от общей суммы |
| **Попап** | Список сделок с детализацией: бюджет, аванс, доплата, ссылка в amoCRM |

#### Формула доплаты

```
доплата = raw['price'] (Бюджет сделки) - custom_field_845975 (Сумма предоплаты)
Сделки с доплатой ≤ 0 исключаются
```

#### Фильтрация по периоду

По кастомному полю **845843 «Месяц предполагаемой доплаты»** (дата, Unix timestamp).

#### Исключаемые этапы

Автоматически из `crm_pipeline_status_snapshots`:
- type = 142 (Успешно реализовано)
- type = 143 (Закрыто нереализовано)
- Имена, содержащие «отлож» или «заморожен»

#### Конфигурация

| Ключ конфига | Что задаёт | Значение для eurohomenew |
|-------------|-----------|------------------------|
| `pipeline_id` | Воронка | 10904262 |
| `pipeline_name` | Название воронки | Массовый подбор |
| `prepayment_field_id` | Поле «Сумма предоплаты» | 845975 |
| `manager_field_id` | Поле «Менеджер» | 845835 |
| `topup_date_field_id` | Поле «Месяц предполагаемой доплаты» | 845843 |

#### API-эндпоинты

| Маршрут | Метод | Назначение |
|---------|-------|-----------|
| `/widgets/amo/{key}/manager-topup` | GET | Страница виджета |
| `/api/.../manager-topup/data` | GET | Агрегация: менеджеры + месяцы + сводка |
| `/api/.../manager-topup/leads` | GET | Список сделок для попапа |

---

## Как добавить новый виджет

### Для разработчика (с Claude или самостоятельно)

#### Шаг 1 — Зарегистрировать тип виджета в БД

```sql
INSERT INTO dashboard_widgets (code, name, is_enabled, created_at, updated_at)
VALUES ('my_widget_code', 'Название виджета', 1, NOW(), NOW());
```

`code` — латинские буквы и дефисы, уникальный идентификатор. Используется везде в роутах и условиях.

#### Шаг 2 — Добавить бизнес-логику в сервис

Файл: `app/Services/Amo/Analytics/AmoTaskStatisticsService.php`

Добавить публичный метод `myWidgetData(AmoAccount $account, ?Carbon $from, ?Carbon $to, array $config): array`
и приватный строитель `buildMyWidgetData(...)`.

Данные читаются через `CrmEntitySnapshot::query()` с `chunkById(500, ...)`.
Кастомные поля — через вспомогательные методы сервиса:
- `recruiterEnumIds()` — enum-id из поля типа список
- `fieldHasAnyValue()` — проверка заполненности поля
- `fieldValueLabels()` — текстовые значения поля

#### Шаг 3 — Добавить контроллер и маршруты

Файл: `app/Http/Controllers/Widget/AmoTaskOverdueDashboardController.php`

```php
public function myWidget(Request $request, string $publicKey, AmoTaskStatisticsService $statisticsService): JsonResponse
{
    $installation = $this->installation($publicKey, 'my_widget_code');
    [$from, $to] = $this->period($request);
    return response()->json(['data' => $statisticsService->myWidgetData(...)]);
}
```

Файл: `routes/web.php`

```php
// Страница
Route::get('/widgets/amo/{publicKey}/my-widget', [AmoTaskOverdueDashboardController::class, 'showMyWidget'])
    ->middleware('amo-widget-frame-policy')
    ->name('widgets.amo.my-widget.show');

// API
Route::get('/api/widgets/amo/{publicKey}/my-widget/data', [AmoTaskOverdueDashboardController::class, 'myWidget'])
    ->middleware('amo-widget-frame-policy')
    ->name('api.widgets.amo.my-widget.data');
```

#### Шаг 4 — Создать TSX-страницу

Файл: `resources/js/Pages/Widgets/Amo/MyWidget.tsx`

Базовая структура:

```tsx
export default function MyWidget({ account, period, links }: Props) {
    const periodParams = { from: period.from, to: period.to };
    const dataState = useApiData<MyData>(links.data, periodParams);

    return (
        <div className="min-h-screen bg-slate-50 p-4">
            <MySection state={dataState} />
        </div>
    );
}
```

Переиспользуемые компоненты из `TaskOverdueDashboardV2.tsx` (см. ниже).

#### Шаг 5 — Настроить конфиг (если нужны настройки)

Добавить поля в:
- `AmoAccountWidgetsController::settings()` — чтение из конфига
- `AmoAccountWidgetsController::updateSettings()` — сохранение
- `UpdateWidgetSettingsRequest::rules()` — валидация
- `Settings.tsx` — UI формы

#### Шаг 6 — Привязать виджет к клиенту

Зайти в `/amo-accounts` → выбрать аккаунт → `Dashboard-блоки` → создать привязку → настроить поля.
После сохранения в таблице `amo_account_dashboard_widgets` появится `public_key`.

Ссылка виджета: `/widgets/amo/{public_key}/my-widget`

#### Шаг 7 — Встроить в amoCRM

Ссылку вставить как внешний виджет в настройках рабочего стола amoCRM клиента.

---

### Только для сотрудника (без написания кода)

> Этот сценарий — когда тип виджета уже добавлен в код, нужно только привязать к новому клиенту.

1. Зайти в `/amo-accounts` в системе
2. Найти аккаунт клиента → `Dashboard-блоки`
3. Нажать "Добавить блок" → выбрать нужный тип виджета из списка
4. Нажать "Настройки" → выбрать воронку, поля сделки (рекрутер, менеджер, команда, город, источник, этап "Встал в график")
5. Сохранить
6. Скопировать публичную ссылку виджета
7. Вставить ссылку в настройки рабочего стола amoCRM клиента

---

## Шаблон карточки задачи

Заполнить перед постановкой задачи Claude или сотруднику:

```
## Новый виджет: [название]

**Клиент:** [название аккаунта]
**Воронка:** [название воронки или "все"]

**Что показывать:**
- [ ] Сделки (из CrmEntitySnapshot)
- [ ] Задачи
- [ ] Другое: ___

**Группировка данных:**
- [ ] По рекрутеру
- [ ] По менеджеру
- [ ] По команде
- [ ] По городу
- [ ] По источнику
- [ ] По этапу воронки
- [ ] По полю сделки: ___

**Фильтры:**
- Воронка: ___
- Этап: ___
- Период: [дата создания / дата изменения]
- Условие включения сделки: ___  (пример: заполнен рекрутер И менеджер)

**Кликабельность чисел:** да / нет
**Попап со списком сделок:** да / нет

**Визуализация:**
- [ ] Таблица
- [ ] Бар-чарт (горизонтальные полосы)
- [ ] Круговая диаграмма
- [ ] Карточки с раскрытием
- [ ] Другое: ___

**Цветовая схема:** зелёная / фиолетовая / янтарная / любая

**Дополнительно:** [любые пожелания]
```

---

## Компоненты UI (переиспользуемые)

Все компоненты находятся в `TaskOverdueDashboardV2.tsx`. При создании нового виджета можно скопировать нужные.

| Компонент | Что делает |
|-----------|-----------|
| `ReportSection` | Карточка-секция с заголовком, подписью, aside-блоком и контентом |
| `AccentSummary` | Цветной блок с крупным числом (тоны: `brand`, `warning`, `success`) |
| `CountButton` | Кликабельное число-ссылка (фиолетовое) или серый "0" |
| `LeadsModal` | Попап со списком сделок и ссылками в amoCRM |
| `BreakdownTable` | Таблица строк с полосами прогресса |
| `BreakdownCard` | Таблица + круговая диаграмма в одной карточке |
| `PieChart` | Круговая диаграмма из массива `{name, count, color}` |
| `Progress` | Горизонтальная полоса прогресса (тоны: `brand`, `warning`, `danger`) |
| `SectionSkeleton` | Заглушка-скелетон при загрузке |
| `SectionError` | Блок с ошибкой загрузки |
| `useApiData<T>` | Хук для загрузки данных с API-эндпоинта |

### Паттерн загрузки данных

```tsx
type LoadState<T> =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | { status: 'loaded'; data: T };

function useApiData<T>(url: string, params: Record<string, string>): LoadState<T> {
    // реализован в TaskOverdueDashboardV2.tsx — скопировать в новый виджет
}
```

### Паттерн секции с попапом

```tsx
function MySection({ state, leadsUrl, periodParams, baseDomain }) {
    const [leadsFilter, setLeadsFilter] = useState<LeadsFilter | null>(null);

    if (state.status === 'loading') return <SectionSkeleton rows={4} />;
    if (state.status === 'error') return <SectionError message={state.message} />;

    return (
        <>
            <ReportSection eyebrow="..." title="..." aside={<AccentSummary ... />}>
                <CountButton value={n} onClick={() => setLeadsFilter({ ..., label: '...' })} />
            </ReportSection>
            {leadsFilter && (
                <LeadsModal filter={leadsFilter} leadsUrl={leadsUrl} periodParams={periodParams} baseDomain={baseDomain} onClose={() => setLeadsFilter(null)} />
            )}
        </>
    );
}
```

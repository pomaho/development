# Sonic Expert task overdue dashboard widget

Пакет amoCRM-виджета для встраивания iframe-отчета Sonic Expert в amoCRM.

## Что делает виджет

- Получает `public_key` из настроек установленного виджета.
- Собирает iframe URL:

```text
{service_base_url}/widgets/amo/{public_key}/task-overdue-dashboard
```

- Рендерит iframe с отчетом по выполненным просроченным задачам.

## Как получить public_key

В Laravel-сервисе:

```text
Клиенты → нужный аккаунт → Dashboard-блоки → Просроченные выполненные задачи
```

Скопируйте `Ключ клиента` или `Iframe URL`.

## Как собрать zip

Из корня проекта:

```bash
cd amo-widget/task-overdue-dashboard
zip -r sonic-expert-task-overdue-dashboard.zip manifest.json script.js style.css i18n
```

## Настройки виджета в amoCRM

```text
Адрес Sonic Expert: https://develop.sonic.expert
Ключ клиента: public_key из страницы Dashboard-блоки
```

## Placement

В `manifest.json` указаны:

```json
["dashboard", "widget_page", "settings"]
```

Если конкретный аккаунт amoCRM не поддерживает кастомный placement на рабочем столе, используйте `widget_page`: отчет откроется как отдельная страница виджета внутри amoCRM.

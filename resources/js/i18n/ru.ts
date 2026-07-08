const ru = {
    nav: {
        main: 'Основное',
        accountOverview: 'Обзор аккаунта',
        crmData: 'CRM-данные',
        crmStructure: 'CRM-структура',
        sync: 'Синхронизация',
        automation: 'Автоматизация',
        analytics: 'Аналитика',
        integrations: 'Интеграции',
        system: 'Система',

        dashboard: 'Dashboard',
        clients: 'Клиенты',
        oauthAmo: 'OAuth amoCRM',

        clientOverview: 'Обзор клиента',
        accountProfile: 'Профиль аккаунта',

        dataCenter: 'Центр данных',
        leads: 'Сделки',
        contacts: 'Контакты',

        structureCenter: 'Центр структуры',
        pipelines: 'Воронки',
        crmFields: 'Поля CRM',
        lists: 'Списки',
        users: 'Пользователи',
        roles: 'Роли и права',

        syncCenter: 'Центр синхронизации',
        leadSyncSchedules: 'Расписание синхронизаций',
        crmAudit: 'CRM-аудит',
        events: 'События',

        automationCenter: 'Центр автоматизации',
        responsible: 'Ответственные',

        analyticsCenter: 'Центр аналитики',
        tasks: 'Задачи',

        integrationsList: 'Интеграции',
        dashboardBlocks: 'Dashboard-блоки',
        webhooks: 'Вебхуки',

        apiLogs: 'API-логи',
    },

    sidebar: {
        appSubtitle: 'amoCRM operations',
        closePanel: 'Закрыть панель',
        closeSidebar: 'Закрыть боковую панель',
        mainNavLabel: 'Основная навигация',
    },

    header: {
        workspace: 'Workspace',
        allAccounts: 'Все аккаунты',
        selectAccount: 'Выбрать аккаунт',
        openSidebar: 'Открыть боковую панель',
        logout: 'Выйти',
    },

    breadcrumbs: {
        label: 'Хлебные крошки',
    },

    errorBoundary: {
        title: 'Что-то пошло не так',
        retry: 'Попробовать снова',
    },

    pagination: {
        prev: 'Назад',
        next: 'Вперед',
    },
} as const;

export default ru;

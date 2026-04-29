@php($credential = $credential ?? $account->credentials)
<div class="grid gap-4 md:grid-cols-2">
    <label class="block text-sm">
        <span>Название</span>
        <input name="name" value="{{ old('name', $account->name) }}" required class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Домен amoCRM</span>
        <input name="base_domain" value="{{ old('base_domain', $account->base_domain) }}" placeholder="company.amocrm.ru" required class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Тип авторизации</span>
        <select name="auth_type" required class="mt-1 w-full rounded border-slate-300">
            <option value="long_lived_token" @selected(old('auth_type', $credential?->auth_type) === 'long_lived_token')>long_lived_token</option>
            <option value="oauth" @selected(old('auth_type', $credential?->auth_type) === 'oauth')>oauth</option>
        </select>
    </label>
    <label class="flex items-end gap-2 text-sm">
        <input name="is_active" value="1" type="checkbox" @checked(old('is_active', $account->is_active ?? true)) class="rounded border-slate-300">
        <span>Активен</span>
    </label>
    <label class="block text-sm md:col-span-2">
        <span>Access token {{ $credential?->maskedAccessToken() ? '('.$credential->maskedAccessToken().')' : '' }}</span>
        <input name="access_token" type="password" autocomplete="off" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Client ID</span>
        <input name="client_id" type="password" autocomplete="off" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Client secret</span>
        <input name="client_secret" type="password" autocomplete="off" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Redirect URI</span>
        <input name="redirect_uri" value="{{ old('redirect_uri', $credential?->redirect_uri) }}" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Refresh token</span>
        <input name="refresh_token" type="password" autocomplete="off" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm">
        <span>Token expires at</span>
        <input name="token_expires_at" type="datetime-local" value="{{ old('token_expires_at', $credential?->token_expires_at?->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded border-slate-300">
    </label>
    <label class="block text-sm md:col-span-2">
        <span>Заметки</span>
        <textarea name="notes" rows="4" class="mt-1 w-full rounded border-slate-300">{{ old('notes', $account->notes) }}</textarea>
    </label>
</div>

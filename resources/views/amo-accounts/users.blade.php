@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold">Пользователи: {{ $account->name }}</h1>
    <a href="{{ route('amo-accounts.show', $account) }}" class="text-sm text-blue-700">Назад к аккаунту</a>
</div>

<form method="get" class="mb-4 flex flex-wrap gap-3 rounded border border-slate-200 bg-white p-4 text-sm">
    <input name="search" value="{{ request('search') }}" placeholder="Имя или email" class="rounded border-slate-300">
    <select name="active" class="rounded border-slate-300">
        <option value="">Любая активность</option>
        <option value="1" @selected(request('active') === '1')>Только активные</option>
        <option value="0" @selected(request('active') === '0')>Только неактивные</option>
    </select>
    <select name="role_id" class="rounded border-slate-300">
        <option value="">Все роли</option>
        @foreach ($roles as $role)
            <option value="{{ $role }}" @selected((string) request('role_id') === (string) $role)>{{ $role }}</option>
        @endforeach
    </select>
    <select name="group_id" class="rounded border-slate-300">
        <option value="">Все группы</option>
        @foreach ($groups as $group)
            <option value="{{ $group }}" @selected((string) request('group_id') === (string) $group)>{{ $group }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2"><input type="checkbox" name="admins" value="1" @checked(request()->boolean('admins')) class="rounded border-slate-300"> Только админы</label>
    <button class="rounded bg-blue-700 px-3 py-2 text-white">Фильтр</button>
</form>

<x-dashboard.table>
    <table class="w-full text-left text-xs">
        <thead class="text-slate-500">
            <tr><th class="py-2">ID</th><th>Имя</th><th>Email</th><th>Активен</th><th>Админ</th><th>Role</th><th>Group</th><th>Сделки</th><th>Контакты</th><th>Компании</th><th>Задачи</th><th>Почта</th><th>Каталоги</th><th>Sync</th><th>Raw</th></tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                @php($rights = $user->rights ?? [])
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-2">{{ $user->amo_user_id }}</td>
                    <td class="font-medium">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->is_active ? 'да' : 'нет' }}</td>
                    <td>{{ $user->is_admin ? 'да' : 'нет' }}</td>
                    <td>{{ $user->role_id }}</td>
                    <td>{{ $user->group_id }}</td>
                    <td>{{ json_encode($rights['leads'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ json_encode($rights['contacts'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ json_encode($rights['companies'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ json_encode($rights['tasks'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ json_encode($rights['mail_access'] ?? $rights['mail'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ json_encode($rights['catalogs'] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ $user->synced_at }}</td>
                    <td><x-json-viewer :data="$user->raw" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $users->links() }}</div>
</x-dashboard.table>
@endsection

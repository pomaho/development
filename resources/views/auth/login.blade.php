@extends('layouts.app')

@section('content')
<div class="mx-auto mt-16 max-w-md rounded border border-slate-200 bg-white p-6">
    <h1 class="mb-6 text-xl font-semibold">Вход</h1>
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <label class="block text-sm">
            <span>Email</span>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="block text-sm">
            <span>Пароль</span>
            <input name="password" type="password" required class="mt-1 w-full rounded border-slate-300">
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input name="remember" type="checkbox" class="rounded border-slate-300">
            <span>Запомнить</span>
        </label>
        <button class="w-full rounded bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800">Войти</button>
    </form>
</div>
@endsection

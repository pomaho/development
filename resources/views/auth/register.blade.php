@extends('layouts.app')

@section('content')
<div class="mx-auto mt-16 max-w-md rounded border border-slate-200 bg-white p-6">
    <h1 class="mb-6 text-xl font-semibold">Регистрация</h1>
    <form method="post" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <input name="name" placeholder="Имя" required class="w-full rounded border-slate-300">
        <input name="email" type="email" placeholder="Email" required class="w-full rounded border-slate-300">
        <input name="password" type="password" placeholder="Пароль" required class="w-full rounded border-slate-300">
        <input name="password_confirmation" type="password" placeholder="Повтор пароля" required class="w-full rounded border-slate-300">
        <button class="w-full rounded bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800">Создать аккаунт</button>
    </form>
</div>
@endsection

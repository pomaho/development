@extends('layouts.app')

@section('content')
<h1 class="mb-6 text-2xl font-semibold">Добавить amoCRM аккаунт</h1>
<form method="post" action="{{ route('amo-accounts.store') }}" class="rounded border border-slate-200 bg-white p-5">
    @csrf
    @include('amo-accounts._form')
    <div class="mt-6 flex gap-3">
        <button class="rounded bg-blue-700 px-4 py-2 text-white hover:bg-blue-800">Сохранить</button>
        <a href="{{ route('amo-accounts.index') }}" class="rounded border border-slate-300 px-4 py-2">Отмена</a>
    </div>
</form>
@endsection

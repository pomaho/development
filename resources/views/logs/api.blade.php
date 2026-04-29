@extends('layouts.app')

@section('content')
<h1 class="mb-6 text-2xl font-semibold">API-логи</h1>
<x-dashboard.table>
    <table class="w-full text-left text-sm">
        <thead class="text-slate-500"><tr><th class="py-2">Дата</th><th>Аккаунт</th><th>Method</th><th>URL</th><th>Status</th><th>Duration</th><th>Error</th><th>Response</th></tr></thead>
        <tbody>
            @foreach ($logs as $log)
                <tr class="border-t border-slate-100 align-top">
                    <td class="py-2">{{ $log->created_at }}</td>
                    <td>{{ $log->account?->name ?? '-' }}</td>
                    <td>{{ $log->method }}</td>
                    <td class="max-w-md break-all">{{ $log->url }}</td>
                    <td>{{ $log->status_code ?? '-' }}</td>
                    <td>{{ $log->duration_ms ? $log->duration_ms.' ms' : '-' }}</td>
                    <td>{{ $log->error_message }}</td>
                    <td><x-json-viewer :data="$log->response_payload" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-dashboard.table>
@endsection

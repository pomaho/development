@props(['data'])
<details class="max-w-2xl">
    <summary class="cursor-pointer text-sm font-medium text-blue-700">Посмотреть JSON</summary>
    <pre class="mt-2 max-h-96 overflow-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
</details>

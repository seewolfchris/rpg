@if (session('status'))
    <div id="app-flash" class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-200">
        {{ session('status') }}
    </div>
@endif

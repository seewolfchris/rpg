@php($appVersion = (string) config('app.version', 'v0.07-beta'))
@php($appBuild = (string) config('app.build', ''))

<div class="rounded-lg border border-stone-800/80 bg-black/35 px-4 py-3 text-xs uppercase tracking-[0.08em] text-stone-400">
    <span class="text-stone-500">Build:</span>
    <span class="font-semibold text-amber-300">{{ $appVersion }}</span>
    @if ($appBuild !== '')
        <span class="text-stone-500">|</span>
        <span class="font-mono text-stone-300">{{ $appBuild }}</span>
    @endif
    <span class="text-stone-500">| Beta</span>
</div>

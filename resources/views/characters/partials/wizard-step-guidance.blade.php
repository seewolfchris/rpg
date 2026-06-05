@php
    $wizardStepKey = (string) ($wizardStepKey ?? '');
    $guidance = (array) ($guidance ?? []);
    $title = (string) ($guidance['title'] ?? '');
    $entries = [
        'task' => 'Was du hier tust',
        'why' => 'Warum das wichtig ist',
        'later' => 'Was du später ändern kannst',
        'skip' => 'Was du jetzt noch nicht wissen musst',
    ];
@endphp

<aside
    class="rounded-2xl border border-amber-700/45 bg-amber-950/10 p-5"
    @if ($wizardStepKey !== '') x-show="wizardShowsStep('{{ $wizardStepKey }}')" @endif
    aria-label="Hinweise zu {{ $title }}"
>
    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300/85">Schrittfokus</p>
    <h2 class="mt-1 font-heading text-xl text-stone-100">{{ $title }}</h2>

    <dl class="mt-4 divide-y divide-stone-800/80 text-sm leading-relaxed">
        @foreach ($entries as $entryKey => $entryLabel)
            <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[12rem_minmax(0,1fr)]">
                <dt class="font-semibold text-stone-100">{{ $entryLabel }}</dt>
                <dd class="text-stone-300">{{ (string) ($guidance[$entryKey] ?? '') }}</dd>
            </div>
        @endforeach
    </dl>
</aside>

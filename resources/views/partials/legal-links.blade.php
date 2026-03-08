@php($imprintUrl = 'https://c76.org/impressum/')
@php($privacyUrl = 'https://c76.org/datenschutz/')

<div class="space-y-2 text-center">
    <nav aria-label="Rechtliche Hinweise" class="flex flex-wrap items-center justify-center gap-3 text-[0.7rem] uppercase tracking-[0.1em] text-stone-500">
        <a href="{{ $imprintUrl }}" rel="noopener noreferrer" class="transition hover:text-stone-300">
            Impressum
        </a>
        <span class="text-stone-700">•</span>
        <a href="{{ $privacyUrl }}" rel="noopener noreferrer" class="transition hover:text-stone-300">
            Datenschutz
        </a>
    </nav>
    <p class="text-xs tracking-[0.08em] text-stone-500">
        ©2026 copyright by C. Sieber | all rights reserved
    </p>
</div>

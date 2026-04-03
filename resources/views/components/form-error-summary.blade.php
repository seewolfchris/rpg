@props(['title' => 'Bitte Eingaben prüfen'])

@if ($errors->any())
    <section
        {{ $attributes->merge([
            'class' => 'rounded-md border border-red-700/70 bg-red-900/20 px-4 py-3 text-sm text-red-100',
        ]) }}
    >
        <p class="font-semibold uppercase tracking-widest text-red-200">{{ $title }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-red-100/95">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </section>
@endif


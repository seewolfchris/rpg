@php($threads = $gmContactPanelData->threads)
@php($selectedThread = $gmContactPanelData->selectedThread)
@php($selectedThreadId = $selectedThread?->id)
@php($createErrors = $errors->getBag('gmContactThread'))

<section id="gm-contact-panel" class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">SL-Kontakte</p>
            <h2 class="font-heading text-2xl text-stone-100">Spielleitung kontaktieren</h2>
            <p class="mt-2 text-sm text-stone-300">Vertrauliche Rückfragen nur zwischen dir und der Kampagnenleitung.</p>
        </div>

        @if ($gmContactPanelData->canCreateThread)
            <div
                x-data="{ open: {{ $createErrors->any() ? 'true' : 'false' }} }"
                class="relative"
            >
                <button
                    type="button"
                    @click="open = true"
                    class="rounded-md border border-amber-400/70 bg-amber-500/20 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-400/30"
                >
                    Spielleitung kontaktieren
                </button>

                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6"
                    @keydown.escape.window="open = false"
                >
                    <div class="w-full max-w-2xl rounded-xl border border-stone-700 bg-neutral-900 p-5 shadow-2xl">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-heading text-xl text-stone-100">Neuer SL-Kontakt</h3>
                            <button
                                type="button"
                                @click="open = false"
                                class="rounded border border-stone-600/80 px-2 py-1 text-xs uppercase tracking-widest text-stone-300 hover:border-stone-400 hover:text-stone-100"
                            >
                                Schließen
                            </button>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('campaigns.gm-contacts.store', ['world' => $world, 'campaign' => $campaign]) }}"
                            hx-post="{{ route('campaigns.gm-contacts.store', ['world' => $world, 'campaign' => $campaign]) }}"
                            hx-target="#gm-contact-panel"
                            hx-swap="outerHTML"
                            class="mt-4 space-y-4"
                        >
                            @csrf

                            <div>
                                <label for="gm-contact-subject" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Betreff</label>
                                <input
                                    id="gm-contact-subject"
                                    type="text"
                                    name="subject"
                                    maxlength="180"
                                    value="{{ old('subject') }}"
                                    required
                                    class="w-full rounded-md border border-stone-600/80 bg-neutral-950/70 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    placeholder="Kurzes Anliegen"
                                >
                                @if ($createErrors->has('subject'))
                                    <p class="mt-2 text-sm text-red-300">{{ $createErrors->first('subject') }}</p>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="gm-contact-character" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Charakter (optional)</label>
                                    <select
                                        id="gm-contact-character"
                                        name="character_id"
                                        class="w-full rounded-md border border-stone-600/80 bg-neutral-950/70 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    >
                                        <option value="">Kein Charakter</option>
                                        @foreach ($gmContactPanelData->characterOptions as $character)
                                            <option value="{{ $character->id }}" @selected((string) old('character_id') === (string) $character->id)>
                                                {{ $character->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($createErrors->has('character_id'))
                                        <p class="mt-2 text-sm text-red-300">{{ $createErrors->first('character_id') }}</p>
                                    @endif
                                </div>

                                <div>
                                    <label for="gm-contact-scene" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Szene (optional)</label>
                                    <select
                                        id="gm-contact-scene"
                                        name="scene_id"
                                        class="w-full rounded-md border border-stone-600/80 bg-neutral-950/70 px-4 py-2.5 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    >
                                        <option value="">Keine Szene</option>
                                        @foreach ($gmContactPanelData->sceneOptions as $sceneOption)
                                            @php($sceneTitle = trim((string) ($sceneOption->title ?? '')))
                                            <option value="{{ $sceneOption->id }}" @selected((string) old('scene_id') === (string) $sceneOption->id)>
                                                {{ $sceneTitle !== '' ? $sceneTitle : 'Szene #'.$sceneOption->id }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($createErrors->has('scene_id'))
                                        <p class="mt-2 text-sm text-red-300">{{ $createErrors->first('scene_id') }}</p>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label for="gm-contact-content" class="mb-2 block text-xs uppercase tracking-widest text-stone-400">Nachricht</label>
                                <textarea
                                    id="gm-contact-content"
                                    name="content"
                                    rows="6"
                                    required
                                    class="w-full rounded-md border border-stone-600/80 bg-neutral-950/70 px-4 py-3 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                                    placeholder="Beschreibe dein Anliegen..."
                                >{{ old('content') }}</textarea>
                                @if ($createErrors->has('content'))
                                    <p class="mt-2 text-sm text-red-300">{{ $createErrors->first('content') }}</p>
                                @endif
                            </div>

                            <button
                                type="submit"
                                class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                            >
                                Thread starten
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (! $gmContactPanelData->canCreateThread)
        <p class="mt-5 text-sm text-stone-400">
            Dieser Bereich ist nur für Kampagnen-Teilnehmer, Co-GMs, Kampagnenleitung oder Admins verfügbar.
        </p>
    @elseif ($threads->isEmpty())
        <p class="mt-5 text-sm text-stone-400">Noch keine SL-Kontakte vorhanden.</p>
    @else
        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(16rem,20rem)_1fr]">
            <div class="space-y-2">
                @foreach ($threads as $thread)
                    @php($threadStatus = (string) $thread->status)
                    <a
                        href="{{ route('campaigns.show', ['world' => $world, 'campaign' => $campaign, 'gm_contact_thread' => $thread->id]) }}#gm-contact-panel"
                        hx-get="{{ route('campaigns.gm-contacts.show', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $thread]) }}"
                        hx-target="#gm-contact-thread-detail"
                        hx-swap="outerHTML"
                        class="block rounded-lg border px-3 py-3 transition {{ (int) $selectedThreadId === (int) $thread->id
                            ? 'border-amber-500/70 bg-amber-900/15'
                            : 'border-stone-700/70 bg-neutral-900/60 hover:border-stone-500/70' }}"
                    >
                        <p class="text-sm font-semibold text-stone-100">{{ $thread->subject }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                            {{ $thread->creator?->name ?? 'Unbekannt' }}
                            • <x-relative-time :at="$thread->last_activity_at ?? $thread->created_at" />
                        </p>
                        <p class="mt-2 text-[0.65rem] uppercase tracking-[0.08em] {{ $threadStatus === \App\Models\CampaignGmContactThread::STATUS_CLOSED
                            ? 'text-red-300'
                            : ($threadStatus === \App\Models\CampaignGmContactThread::STATUS_WAITING_FOR_GM
                                ? 'text-amber-300'
                                : ($threadStatus === \App\Models\CampaignGmContactThread::STATUS_WAITING_FOR_PLAYER
                                    ? 'text-emerald-300'
                                    : 'text-stone-300')) }}">
                            {{ $thread->statusLabel() }}
                        </p>
                    </a>
                @endforeach
            </div>

            @include('campaigns.partials.gm-contact-thread-detail', [
                'world' => $world,
                'campaign' => $campaign,
                'selectedThread' => $selectedThread,
                'selectedThreadMessages' => $gmContactPanelData->selectedThreadMessages,
                'isGmSide' => $gmContactPanelData->isGmSide,
            ])
        </div>
    @endif
</section>

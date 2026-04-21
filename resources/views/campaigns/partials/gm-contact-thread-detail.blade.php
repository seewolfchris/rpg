@php($messageErrors = $errors->getBag('gmContactMessage'))
@php($statusErrors = $errors->getBag('gmContactStatus'))

<div id="gm-contact-thread-detail" class="rounded-lg border border-stone-700/70 bg-neutral-900/60 p-4">
    @if (! $selectedThread)
        <p class="text-sm text-stone-400">Wähle einen Thread aus, um Nachrichten zu sehen.</p>
    @else
        @php($status = (string) $selectedThread->status)
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="font-heading text-xl text-stone-100">{{ $selectedThread->subject }}</h3>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500">
                    Erstellt von {{ $selectedThread->creator?->name ?? 'Unbekannt' }}
                    • <x-relative-time :at="$selectedThread->created_at" />
                </p>
            </div>
            <span class="rounded border px-2 py-1 text-[0.65rem] uppercase tracking-[0.08em] {{ $status === \App\Models\CampaignGmContactThread::STATUS_CLOSED
                ? 'border-red-700/60 bg-red-900/20 text-red-300'
                : ($status === \App\Models\CampaignGmContactThread::STATUS_WAITING_FOR_GM
                    ? 'border-amber-700/60 bg-amber-900/20 text-amber-300'
                    : ($status === \App\Models\CampaignGmContactThread::STATUS_WAITING_FOR_PLAYER
                        ? 'border-emerald-700/60 bg-emerald-900/20 text-emerald-300'
                        : 'border-stone-600/80 bg-black/40 text-stone-300')) }}">
                {{ $selectedThread->statusLabel() }}
            </span>
        </div>

        @if ($selectedThread->scene || $selectedThread->character)
            <p class="mt-2 text-xs text-stone-400">
                @if ($selectedThread->scene)
                    Szene: {{ trim((string) $selectedThread->scene->title) !== '' ? $selectedThread->scene->title : 'Szene #'.$selectedThread->scene->id }}
                @endif
                @if ($selectedThread->scene && $selectedThread->character)
                    •
                @endif
                @if ($selectedThread->character)
                    Charakter: {{ $selectedThread->character->name }}
                @endif
            </p>
        @endif

        <div class="mt-4 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
            @forelse ($selectedThreadMessages as $message)
                <article class="rounded-md border border-stone-700/60 bg-black/25 px-3 py-3">
                    <p class="text-xs uppercase tracking-[0.08em] text-stone-400">
                        {{ $message->user?->name ?? 'Unbekannt' }}
                        • <x-relative-time :at="$message->created_at" />
                    </p>
                    <div class="mt-2 whitespace-pre-line text-sm text-stone-200">{{ $message->content }}</div>
                </article>
            @empty
                <p class="text-sm text-stone-400">Noch keine Nachrichten vorhanden.</p>
            @endforelse
        </div>

        @can('reply', $selectedThread)
            <form
                method="POST"
                action="{{ route('campaigns.gm-contacts.messages.store', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                hx-post="{{ route('campaigns.gm-contacts.messages.store', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                hx-target="#gm-contact-panel"
                hx-swap="outerHTML"
                class="mt-4 space-y-3"
            >
                @csrf
                <label for="gm-contact-reply-content" class="block text-xs uppercase tracking-widest text-stone-400">Antwort</label>
                <textarea
                    id="gm-contact-reply-content"
                    name="content"
                    rows="4"
                    required
                    class="w-full rounded-md border border-stone-600/80 bg-neutral-950/70 px-4 py-2.5 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/40"
                    placeholder="Antwort verfassen..."
                >{{ old('content') }}</textarea>
                @if ($messageErrors->has('content'))
                    <p class="text-sm text-red-300">{{ $messageErrors->first('content') }}</p>
                @endif

                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Antwort senden
                </button>
            </form>
        @else
            <p class="mt-4 text-sm text-stone-400">Dieser Thread ist geschlossen. Antworten sind erst nach Wiederöffnung möglich.</p>
        @endcan

        @can('updateStatus', $selectedThread)
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if ($status !== \App\Models\CampaignGmContactThread::STATUS_CLOSED)
                    <form
                        method="POST"
                        action="{{ route('campaigns.gm-contacts.status.update', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                        hx-post="{{ route('campaigns.gm-contacts.status.update', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                        hx-target="#gm-contact-panel"
                        hx-swap="outerHTML"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="closed">
                        <button
                            type="submit"
                            class="rounded-md border border-red-700/80 bg-red-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-200 transition hover:bg-red-900/40"
                        >
                            Thread schließen
                        </button>
                    </form>
                @endif

                @if ($status === \App\Models\CampaignGmContactThread::STATUS_CLOSED)
                    <form
                        method="POST"
                        action="{{ route('campaigns.gm-contacts.status.update', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                        hx-post="{{ route('campaigns.gm-contacts.status.update', ['world' => $world, 'campaign' => $campaign, 'gmContactThread' => $selectedThread]) }}"
                        hx-target="#gm-contact-panel"
                        hx-swap="outerHTML"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="open">
                        <button
                            type="submit"
                            class="rounded-md border border-emerald-600/80 bg-emerald-900/20 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-emerald-200 transition hover:bg-emerald-900/40"
                        >
                            Thread wieder öffnen
                        </button>
                    </form>
                @endif

                @if ($statusErrors->has('status'))
                    <p class="text-sm text-red-300">{{ $statusErrors->first('status') }}</p>
                @endif
            </div>
        @endcan
    @endif
</div>

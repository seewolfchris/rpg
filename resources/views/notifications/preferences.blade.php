@extends('layouts.auth')

@section('title', 'Mitteilungs-Einstellungen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <x-navigation.back-link :href="$backUrl" label="Zurück" />
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Einstellungen</p>
            <h1 class="font-heading text-3xl text-stone-100">Mitteilungs-Präferenzen</h1>
            <p class="mt-3 text-sm text-stone-300">
                Lege fest, welche Ereignisse in der App und per E-Mail gemeldet werden.
            </p>
            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                Browser-Push nutzt Web Push (VAPID) und benötigt eine erlaubte Notification-Permission.
            </p>
        </div>

        <section class="rounded-2xl border border-amber-700/50 bg-amber-950/25 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-300/90">Datenschutz & Offline-Funktion</p>
            <p class="text-sm leading-relaxed text-amber-100/95">
                Offline-Speicherung ist standardmäßig aus. Wenn du sie ausdrücklich aktivierst, werden für die Offline-Nutzung Seiteninhalte, ungesendete Beiträge und optionale lokale Entwürfe im Browser gespeichert. Auf geteilten oder kompromittierten Geräten können andere Personen diese Inhalte lesen. Beim Deaktivieren, bei Logout und bei einem Kontowechsel werden die verwalteten lokalen Daten gelöscht.
            </p>
        </section>

        <form
            method="POST"
            action="{{ route('notifications.preferences.update') }}"
            class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8"
            data-notification-preferences-form
        >
            @csrf
            @method('PATCH')
            @if (is_string($returnTo ?? null) && $returnTo !== '')
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif

            <div class="space-y-5">
                <article class="rounded-xl border border-amber-700/50 bg-amber-950/20 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Offline-Warteschlange</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Aktiviere diesen Schalter nur, wenn private Spielinhalte auf diesem Gerät für die Offline-Nutzung gespeichert werden dürfen.
                    </p>
                    <div class="mt-3">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-100">
                            <input
                                type="checkbox"
                                name="offline_storage_enabled"
                                value="1"
                                @checked($offlineQueueEnabled)
                                class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60"
                                data-offline-queue-toggle
                            >
                            Offline-Speicherung ausdrücklich aktivieren
                        </label>
                    </div>
                    <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-400">
                        Die Einstellung wird sofort gespeichert. Beim Ausschalten werden alle von C76-RPG verwalteten lokalen Offline-Daten sofort gelöscht.
                    </p>
                </article>

                <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Moderationsstatus für eigene Beiträge</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung bei Freigegeben/Ausstehend/Abgelehnt.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="post_moderation_database" value="1" @checked(data_get($preferences, 'post_moderation.database')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Im Spiel
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="post_moderation_mail" value="1" @checked(data_get($preferences, 'post_moderation.mail')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            E-Mail
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="post_moderation_browser" value="1" @checked(data_get($preferences, 'post_moderation.browser')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Browser-Push
                        </label>
                    </div>
                </article>

                <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Neue Beiträge in Szenen</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung bei neuen Beiträgen von anderen Teilnehmenden.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="scene_new_post_database" value="1" @checked(data_get($preferences, 'scene_new_post.database')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Im Spiel
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="scene_new_post_mail" value="1" @checked(data_get($preferences, 'scene_new_post.mail')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            E-Mail
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="scene_new_post_browser" value="1" @checked(data_get($preferences, 'scene_new_post.browser')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Browser-Push
                        </label>
                    </div>
                </article>

                <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Kampagnen-Einladungen</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung bei neuen Einladungen zu privaten Kampagnen.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="campaign_invitation_database" value="1" @checked(data_get($preferences, 'campaign_invitation.database')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Im Spiel
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="campaign_invitation_mail" value="1" @checked(data_get($preferences, 'campaign_invitation.mail')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            E-Mail
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="campaign_invitation_browser" value="1" @checked(data_get($preferences, 'campaign_invitation.browser')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Browser-Push
                        </label>
                    </div>
                </article>

                <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Charakter-Erwaehnungen</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung, wenn ein eigener Charakter per <code>@Name</code> in einem Beitrag erwaehnt wird.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="character_mention_database" value="1" @checked(data_get($preferences, 'character_mention.database', true)) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            Im Spiel
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="character_mention_mail" value="1" @checked(data_get($preferences, 'character_mention.mail', false)) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            E-Mail
                        </label>
                    </div>
                </article>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    class="rounded-md border border-amber-500/60 bg-amber-500/15 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                >
                    Speichern
                </button>
                <a
                    href="{{ $backUrl }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zum Posteingang
                </a>
            </div>
        </form>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Browser-Push</p>
                    <h2 class="font-heading text-2xl text-stone-100">Verknüpfte Geräte</h2>
                    <p class="mt-2 text-sm text-stone-300">
                        Hier kannst du einzelne Browser-Verknüpfungen oder alle gespeicherten Push-Geräte entfernen.
                    </p>
                </div>

                @if ($pushSubscriptions->isNotEmpty())
                    <form
                        method="POST"
                        action="{{ route('notifications.preferences.push-devices.destroy-all') }}"
                        data-confirm="Wirklich alle Push-Geräte entfernen?"
                        data-push-device-remove-all-form
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-600/70 bg-red-950/30 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-red-100 transition hover:bg-red-950/55">
                            Alle entfernen
                        </button>
                    </form>
                @endif
            </div>

            @if ($pushSubscriptions->isEmpty())
                <p class="mt-5 rounded-xl border border-stone-800 bg-neutral-900/60 p-4 text-sm text-stone-400">
                    Noch kein Push-Gerät gespeichert.
                </p>
            @else
                <div class="mt-5 space-y-3">
                    @foreach ($pushSubscriptions as $pushSubscription)
                        <article
                            class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4"
                            data-push-device
                            data-endpoint-hash="{{ hash('sha256', $pushSubscription->endpoint) }}"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-stone-100">
                                        {{ $pushSubscription->device_name ?: 'Browsergerät' }}
                                        <span class="ml-2 hidden rounded-full border border-emerald-600/60 px-2 py-0.5 text-[0.65rem] uppercase tracking-wider text-emerald-200" data-push-current-badge>
                                            Dieses Gerät
                                        </span>
                                    </p>
                                    <p class="mt-1 text-xs text-stone-400">
                                        Welt: {{ $pushSubscription->world?->name ?? 'Unbekannt' }}
                                        · zuletzt bestätigt:
                                        {{ ($pushSubscription->last_used_at ?? $pushSubscription->updated_at)?->format('d.m.Y H:i') }}
                                    </p>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('notifications.preferences.push-devices.destroy', $pushSubscription) }}"
                                    data-push-device-remove-form
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-stone-600/80 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-red-500/70 hover:text-red-100">
                                        Entfernen
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
@endsection

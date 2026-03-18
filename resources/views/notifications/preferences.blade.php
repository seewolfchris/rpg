@extends('layouts.auth')

@section('title', 'Mitteilungs-Einstellungen | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-4xl space-y-6">
        <div class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Einstellungen</p>
            <h1 class="font-heading text-3xl text-stone-100">Mitteilungs-Präferenzen</h1>
            <p class="mt-3 text-sm text-stone-300">
                Lege fest, welche Ereignisse in der App und per E-Mail gemeldet werden.
            </p>
            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                Browser-Push nutzt Web Push (VAPID) und benoetigt eine erlaubte Notification-Permission.
            </p>
        </div>

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            @csrf
            @method('PATCH')

            <div class="space-y-5">
                <article class="rounded-xl border border-stone-800 bg-neutral-900/60 p-4">
                    <h2 class="font-heading text-lg text-stone-100">Moderationsstatus für eigene Posts</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung bei Freigegeben/Ausstehend/Abgelehnt.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="post_moderation_database" value="1" @checked(data_get($preferences, 'post_moderation.database')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            In-App
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
                    <h2 class="font-heading text-lg text-stone-100">Neue Posts in Szenen</h2>
                    <p class="mt-1 text-sm text-stone-300">
                        Benachrichtigung bei neuen Beiträgen von anderen Teilnehmenden.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="scene_new_post_database" value="1" @checked(data_get($preferences, 'scene_new_post.database')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-amber-400 focus:ring-amber-500/60">
                            In-App
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
                            In-App
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
                            In-App
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
                    href="{{ route('notifications.index') }}"
                    class="rounded-md border border-stone-600/80 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                >
                    Zur Inbox
                </a>
            </div>
        </form>
    </section>
@endsection

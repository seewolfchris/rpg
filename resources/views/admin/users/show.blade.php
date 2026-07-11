@extends('layouts.auth')

@section('title', $user->name.' | Benutzerverwaltung')

@section('content')
    @php($roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role)
    @php($statusValue = $user->status instanceof \App\Enums\UserStatus ? $user->status->value : (string) $user->status)
    @php($statusLabel = match ($statusValue) {
        \App\Enums\UserStatus::ACTIVE->value => 'Aktiv',
        \App\Enums\UserStatus::PENDING->value => 'Ausstehend',
        default => 'Gesperrt',
    })
    @php($isDeletedUserSystemAccount = $user->isDeletedUserSystemAccount())

    <section class="ui-page-wide rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin · Benutzer</p>
                <h1 class="mt-2 font-heading break-words text-3xl text-stone-100 sm:text-4xl">{{ $user->name }}</h1>
                <p class="mt-3 break-words text-sm text-stone-300">{{ $user->email }}</p>
                @if ($isDeletedUserSystemAccount)
                    <p class="mt-3 inline-flex rounded-full border border-red-500/70 px-2 py-1 text-xs uppercase tracking-widest text-red-200">
                        Technisches Systemkonto
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.users.index') }}" class="ui-btn inline-flex">Übersicht</a>
                @unless ($isDeletedUserSystemAccount)
                    <a href="{{ route('admin.users.edit', $user) }}" class="ui-btn ui-btn-accent inline-flex">Bearbeiten</a>
                @endunless
            </div>
        </div>
    </section>

    @error('user')
        <section class="ui-page-wide mt-4 rounded-2xl border border-red-500/60 bg-red-950/30 p-4 text-sm text-red-100">
            {{ $message }}
        </section>
    @enderror

    <section class="ui-page-wide mt-6 grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
            <h2 class="font-heading text-xl text-stone-100">Stammdaten</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Name</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">E-Mail</dt>
                    <dd class="mt-1 break-words text-stone-100">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Erstellt</dt>
                    <dd class="mt-1 text-stone-300"><x-relative-time :at="$user->created_at" /></dd>
                </div>
            </dl>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
            <h2 class="font-heading text-xl text-stone-100">Status und Rechte</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Accountstatus</dt>
                    <dd class="mt-1">
                        <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest
                            @if ($statusValue === \App\Enums\UserStatus::ACTIVE->value)
                                border-emerald-500/60 text-emerald-200
                            @elseif ($statusValue === \App\Enums\UserStatus::PENDING->value)
                                border-amber-500/70 text-amber-200
                            @else
                                border-red-500/70 text-red-200
                            @endif
                        ">
                            {{ $statusLabel }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Plattformrolle</dt>
                    <dd class="mt-1 text-stone-100">{{ \App\Enums\UserRole::labelFor($roleValue) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Eigene Kampagnen leiten</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->can_create_campaigns ? 'Ja' : 'Nein' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Ohne Moderation posten</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->can_post_without_moderation ? 'Ja' : 'Nein' }}</dd>
                </div>
                @if ($user->status_reason)
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-stone-500">Sperrgrund</dt>
                        <dd class="mt-1 whitespace-pre-line text-stone-300">{{ $user->status_reason }}</dd>
                    </div>
                @endif
            </dl>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
            <h2 class="font-heading text-xl text-stone-100">Nutzung</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Eigene Kampagnen</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->owned_campaigns_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Kampagnen-Teilnahmen</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->campaign_memberships_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Charaktere</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->characters_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Beiträge</dt>
                    <dd class="mt-1 text-stone-100">{{ $user->posts_count }}</dd>
                </div>
            </dl>
        </article>

        <article class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-5">
            <h2 class="font-heading text-xl text-stone-100">Zustimmung und Prüfung</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Terms-Zustimmung</dt>
                    <dd class="mt-1 text-stone-100">
                        @if ($user->terms_accepted_at)
                            <x-relative-time :at="$user->terms_accepted_at" /> · {{ $user->terms_version ?? 'ohne Version' }}
                        @else
                            Nicht gespeichert
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Freischaltung</dt>
                    <dd class="mt-1 text-stone-300">
                        @if ($user->approved_at)
                            <x-relative-time :at="$user->approved_at" />
                        @else
                            Keine Freischaltung gespeichert
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-widest text-stone-500">Sperrung</dt>
                    <dd class="mt-1 text-stone-300">
                        @if ($user->suspended_at)
                            <x-relative-time :at="$user->suspended_at" />
                        @else
                            Keine Sperrung gespeichert
                        @endif
                    </dd>
                </div>
            </dl>
        </article>
    </section>

    @unless ($isDeletedUserSystemAccount)
        <section class="ui-page-wide mt-6 rounded-2xl border border-red-500/60 bg-red-950/20 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-red-300">Gefahrenzone</p>
                    <h2 class="mt-2 font-heading text-xl text-stone-100">Benutzer endgültig entfernen</h2>
                    <p class="mt-3 max-w-3xl text-sm text-stone-300">
                        Der Benutzer wird aus der Datenbank gelöscht. Erhaltenswerte Spielinhalte werden auf '{{ \App\Models\User::DELETED_USER_SYSTEM_NAME }}' übertragen.
                    </p>
                    @if ($user->owned_campaigns_count > 0)
                        <p class="mt-2 max-w-3xl text-sm text-amber-200">
                            Dieser Benutzer besitzt {{ $user->owned_campaigns_count }} {{ $user->owned_campaigns_count === 1 ? 'Kampagne' : 'Kampagnen' }}. Beim Entfernen wird die Kampagnenleitung auf '{{ \App\Models\User::DELETED_USER_SYSTEM_NAME }}' übertragen.
                        </p>
                    @endif
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.users.destroy', $user) }}"
                    data-confirm="Benutzer endgültig entfernen? Erhaltenswerte Spielinhalte werden auf {{ \App\Models\User::DELETED_USER_SYSTEM_NAME }} übertragen."
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="rounded-md border border-red-500/80 bg-red-500/15 px-4 py-2 text-sm font-semibold text-red-100 transition hover:bg-red-500/25"
                    >
                        Benutzer endgültig entfernen
                    </button>
                </form>
            </div>
        </section>
    @endunless
@endsection

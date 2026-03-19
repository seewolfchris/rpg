@php($isHtmxRequest = request()->header('HX-Request') === 'true')

<section id="notifications-inbox">
    @if ($isHtmxRequest)
        <span id="notifications-unread-count" hx-swap-oob="outerHTML" class="font-semibold text-amber-200">{{ $unreadCount }}</span>

        @if ($unreadCount > 0)
            <span id="nav-unread-notifications-badge" hx-swap-oob="outerHTML" class="absolute -right-1.5 -top-1.5 inline-flex min-w-5 items-center justify-center rounded-full border border-amber-300/80 bg-amber-500 px-1.5 text-[0.6rem] font-bold text-black">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @else
            <span id="nav-unread-notifications-badge" hx-swap-oob="outerHTML" class="hidden"></span>
        @endif
    @endif

    <h2 class="font-heading text-xl text-stone-100">Inbox</h2>

    @if ($notifications->isEmpty())
        <p class="mt-4 text-sm text-stone-400">Keine Benachrichtigungen vorhanden.</p>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($notifications as $notification)
                @php($data = $notification->data)
                @php($isUnread = $notification->read_at === null)
                <article class="rounded-xl border {{ $isUnread ? 'border-amber-700/60 bg-amber-900/10' : 'border-stone-800 bg-neutral-900/60' }} p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-100">
                                {{ $data['title'] ?? 'Benachrichtigung' }}
                            </p>
                            <p class="mt-1 text-sm text-stone-300">
                                {{ $data['message'] ?? 'Neue Aktivität.' }}
                            </p>
                            <p class="mt-2 text-xs uppercase tracking-[0.08em] text-stone-500">
                                <x-relative-time :at="$notification->created_at" />
                                @if ($isUnread)
                                    • Ungelesen
                                @else
                                    • Gelesen
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" hx-boost="false">
                                @csrf
                                <button
                                    type="submit"
                                    class="rounded-md border border-stone-600/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-200 transition hover:border-stone-400 hover:text-stone-100"
                                >
                                    Öffnen
                                </button>
                            </form>

                            @if ($isUnread)
                                <form
                                    method="POST"
                                    action="{{ route('notifications.read', $notification->id) }}"
                                    hx-post="{{ route('notifications.read', $notification->id) }}"
                                    hx-target="#notifications-inbox"
                                    hx-swap="outerHTML"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="rounded-md border border-amber-500/70 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-100 transition hover:bg-amber-500/30"
                                    >
                                        Als gelesen
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</section>

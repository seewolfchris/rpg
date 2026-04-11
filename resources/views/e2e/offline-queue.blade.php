@extends('layouts.app')

@section('title', 'E2E Offline Queue Harness | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-3xl space-y-6">
        <header class="ui-card p-6">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-300">E2E Harness</p>
            <h1 class="mt-2 font-heading text-2xl text-stone-100">Offline Queue & Retry</h1>
            <p class="mt-2 text-sm text-stone-300">
                Diese Seite existiert nur für lokale/testing E2E-Flows (Offline-Queue, Re-Signing, Privacy-Boundary).
            </p>
        </header>

        <section class="ui-card space-y-4 p-6">
            <form
                method="POST"
                action="{{ route('e2e.offline-queue.submit') }}"
                data-offline-post-form
                class="space-y-4"
            >
                @csrf

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1 text-xs uppercase tracking-[0.08em] text-stone-300">
                        Post-Typ
                        <select name="post_type" class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100">
                            <option value="ooc">OOC</option>
                        </select>
                    </label>

                    <label class="space-y-1 text-xs uppercase tracking-[0.08em] text-stone-300">
                        Format
                        <select name="content_format" class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100">
                            <option value="plain">Plain</option>
                        </select>
                    </label>
                </div>

                <label class="space-y-1 text-xs uppercase tracking-[0.08em] text-stone-300">
                    Inhalt
                    <textarea
                        name="content"
                        rows="6"
                        class="w-full rounded-md border border-stone-600/80 bg-neutral-900/80 px-3 py-2 text-sm text-stone-100"
                        placeholder="Nachricht für Offline-Queue schreiben"
                        required
                    ></textarea>
                </label>

                <button
                    type="submit"
                    class="ui-btn ui-btn-accent"
                >
                    Beitrag absenden
                </button>
            </form>

            <div
                id="offline-queue-status-panel"
                class="hidden rounded-lg border border-amber-700/40 bg-amber-900/10 p-4"
                aria-live="polite"
            ></div>

            <div
                id="offline-dead-letter-panel"
                class="hidden rounded-lg border border-amber-700/40 bg-black/20 p-4"
                aria-live="polite"
            ></div>
        </section>
    </section>
@endsection

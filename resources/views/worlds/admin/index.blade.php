@extends('layouts.auth')

@section('title', 'Weltenverwaltung | C76-RPG')

@section('content')
    <section class="rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-300/80">Admin</p>
                <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Weltenverwaltung</h1>
            </div>
            <a href="{{ route('admin.worlds.create') }}" class="ui-btn ui-btn-accent inline-flex">Neue Welt</a>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-800 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-widest text-stone-400">
                        <th class="px-3 py-3">Welt</th>
                        <th class="px-3 py-3">Slug</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Nutzung</th>
                        <th class="px-3 py-3 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-900">
                    @forelse ($worlds as $world)
                        <tr>
                            <td class="px-3 py-3">
                                <p class="font-semibold text-stone-100">{{ $world->name }}</p>
                                <p class="text-xs text-stone-400">Pos {{ $world->position }}</p>
                            </td>
                            <td class="px-3 py-3 text-stone-300">{{ $world->slug }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2 py-1 text-xs uppercase tracking-widest {{ $world->is_active ? 'border-emerald-500/60 text-emerald-200' : 'border-stone-600 text-stone-300' }}">
                                    {{ $world->is_active ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                                @if ($world->slug === \App\Models\World::defaultSlug())
                                    <span class="ml-2 inline-flex rounded-full border border-amber-500/60 px-2 py-1 text-xs uppercase tracking-widest text-amber-200">
                                        Standard
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs text-stone-400">
                                Kampagnen: {{ $world->campaigns_count }} |
                                Charaktere: {{ $world->characters_count }} |
                                Wissen: {{ $world->encyclopedia_categories_count }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.worlds.move', ['world' => $world, 'direction' => 'up']) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn inline-flex disabled:cursor-not-allowed disabled:opacity-40" @disabled($loop->first)>Hoch</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.worlds.move', ['world' => $world, 'direction' => 'down']) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn inline-flex disabled:cursor-not-allowed disabled:opacity-40" @disabled($loop->last)>Runter</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.worlds.toggle-active', $world) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn inline-flex">{{ $world->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button>
                                    </form>
                                    <a href="{{ route('admin.worlds.edit', $world) }}" class="ui-btn inline-flex">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.worlds.destroy', $world) }}" onsubmit="return confirm('Welt wirklich löschen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-danger inline-flex">Löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-stone-400">Keine Welten vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $worlds->links() }}
        </div>
    </section>
@endsection

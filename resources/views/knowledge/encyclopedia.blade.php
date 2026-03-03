@extends('layouts.auth')

@section('title', 'Enzyklopaedie · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-6xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Enzyklopaedie von Vhal'Tor</h1>
            <p class="mt-4 max-w-4xl text-base leading-relaxed text-stone-300 sm:text-lg">
                Dieser Band sammelt den aktuellen Weltkanon. Nutze ihn fuer konsistente Figuren, Orte und Machtverhaeltnisse.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">Zeitalter</h2>
                <dl class="mt-4 space-y-3 text-sm leading-relaxed text-stone-300">
                    <div>
                        <dt class="font-semibold text-stone-100">Zeitalter der Sonnenkronen</dt>
                        <dd>Imperiale Hochkultur mit zentraler Liturgie und strenger Erbfolge.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Der Aschenfall</dt>
                        <dd>Zerfall der Kronenreiche durch Blutpforten, Seuchen und Thronkriege.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Zeit der letzten Schwuere</dt>
                        <dd>Gegenwart: zerbrochene Allianzen, Restreiche und Schattenvertraege.</dd>
                    </div>
                </dl>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">Machtbloecke</h2>
                <dl class="mt-4 space-y-3 text-sm leading-relaxed text-stone-300">
                    <div>
                        <dt class="font-semibold text-stone-100">Orden der Glutrichter</dt>
                        <dd>Dogmatische Richterkaste, jagt ketzerische Ritualmagie.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Schattenhaeuser von Nerez</dt>
                        <dd>Adelsnetzwerk aus Spionage, Schuldbriefen und verdeckten Paktbuendnissen.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-stone-100">Bruderschaft der Neun Narben</dt>
                        <dd>Veteranenbund, verkauft Schutz und Kriegskunst an die Meistbietenden.</dd>
                    </div>
                </dl>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-lg text-stone-100">Region: Aschelande</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    Verbrannte Grenzprovinzen voller Restfestungen, Pilgerstrassen und verlassener Signalfeuer.
                </p>
            </article>
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-lg text-stone-100">Region: Nebelmark</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    Moorige Handelszone, in der Vertrage oft unter falschen Namen und fremden Siegeln geschlossen werden.
                </p>
            </article>
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-lg text-stone-100">Region: Schwarzgrat</h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-300">
                    Gebirgskette mit Festungskloestern, Erzminen und uralten Pfortenkammern.
                </p>
            </article>
        </section>

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Kernausdruecke</h2>
            <dl class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-semibold text-stone-100">Blutpforte</dt>
                    <dd class="mt-1 text-sm text-stone-300">Ritualtor, das Preise in Erinnerung, Blut oder Zeit fordert.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-semibold text-stone-100">Schwurbruch</dt>
                    <dd class="mt-1 text-sm text-stone-300">Rechtlich und magisch verfolgter Vertragsbruch mit sozialem Bann.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-semibold text-stone-100">Aschesiegel</dt>
                    <dd class="mt-1 text-sm text-stone-300">Brandzeichen zur Markierung gebundener Artefakte und Eidtraeger.</dd>
                </div>
                <div class="rounded-lg border border-stone-800 bg-neutral-900/70 p-4">
                    <dt class="font-semibold text-stone-100">Nachtzoll</dt>
                    <dd class="mt-1 text-sm text-stone-300">Tribut fuer sichere Durchreise in nebelverseuchten Grenzgebieten.</dd>
                </div>
            </dl>
        </section>
    </section>
@endsection

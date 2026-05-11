@extends('layouts.auth')

@section('title', 'Nutzungsbedingungen Testbetrieb | C76-RPG')

@section('content')
    <section class="mx-auto w-full max-w-3xl rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8">
        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-400/80">Rechtlicher Hinweis</p>
        <h1 class="font-heading text-3xl text-stone-100">Nutzungsbedingungen für den geschlossenen Testbetrieb</h1>

        <div class="mt-6 space-y-4 text-sm text-stone-300">
            <p>Diese Bedingungen gelten für die Nutzung von <span class="font-semibold text-stone-100">rpg.c76.org</span>.</p>
            <p>C76-RPG befindet sich im geschlossenen Testbetrieb. Registrierung und Teilnahme erfolgen nur nach Freigabe durch den Betreiber.</p>
            <p>Die Teilnahme ist nur volljährigen Nutzern gestattet.</p>
            <p>Es besteht kein Anspruch auf Freischaltung, dauerhafte Verfügbarkeit oder unveränderte Funktionen.</p>
            <p>Nutzer verpflichten sich, keine rechtswidrigen, sensiblen oder missbräuchlichen Inhalte zu veröffentlichen.</p>
            <p>Spielinhalte bleiben grundsätzlich Inhalte der Nutzer, dürfen aber technisch gespeichert, dargestellt und im Rahmen der Kampagne bereitgestellt werden.</p>
            <p>Admins und Spielleitungen können Inhalte einsehen, soweit dies für Betrieb, Support, Moderation, Fehlerbehebung und Sicherheit erforderlich ist.</p>
            <p>Testdaten können nach Testabschluss gelöscht, anonymisiert oder geändert werden.</p>
            <p>
                Es gilt ergänzend die Datenschutzerklärung:
                <a href="https://c76.org/datenschutz/" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-300 hover:text-amber-200">https://c76.org/datenschutz/</a>.
            </p>
        </div>
    </section>
@endsection

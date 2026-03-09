@extends('layouts.auth')

@section('title', 'Wie spielt man? · Wissenszentrum')

@section('content')
    <section class="mx-auto w-full max-w-5xl space-y-6">
        <header class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 backdrop-blur-sm sm:p-8">
            <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Wissenszentrum</p>
            <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">Wie spielt man {{ $world->name }}?</h1>
            <p class="mt-4 text-base leading-relaxed text-stone-300 sm:text-lg">
                Dieser Ablauf führt dich Schritt für Schritt ins Spiel. Wenn du neu bist, arbeite ihn genau in der Reihenfolge ab.
            </p>
        </header>

        @include('knowledge._nav')

        <section class="rounded-2xl border border-stone-800 bg-black/45 p-6 shadow-xl shadow-black/40 sm:p-8">
            <h2 class="font-heading text-2xl text-stone-100">Schnellstart in 7 Schritten</h2>
            <ol class="mt-5 space-y-4 text-sm leading-relaxed text-stone-300">
                <li>
                    <strong class="text-stone-100">1. Konto anlegen und einloggen</strong><br>
                    Nutze Registrierung und bestätige, dass du in Ruhe asynchron schreiben willst.
                </li>
                <li>
                    <strong class="text-stone-100">2. Charakter erstellen</strong><br>
                    Vergib Name, Eigenschaften, Biografie und ein Bild. Der Charakter braucht ein klares Ziel und ein klares Risiko.
                </li>
                <li>
                    <strong class="text-stone-100">3. Kampagne wählen</strong><br>
                    Tritt einer offenen Kampagne bei oder nimm eine Einladung an. Lies die Kampagnenbeschreibung vollständig.
                </li>
                <li>
                    <strong class="text-stone-100">4. Szene lesen, bevor du schreibst</strong><br>
                    Prüfe die letzten Posts und notiere dir offene Konflikte, damit dein Einstieg zur laufenden Handlung passt.
                </li>
                <li>
                    <strong class="text-stone-100">5. Ersten IC-Post schreiben</strong><br>
                    IC immer in Ich-Perspektive: <em>Ich hebe die Klinge und halte den Atem an.</em>
                </li>
                <li>
                    <strong class="text-stone-100">6. GM-Probe anfragen</strong><br>
                    Bei unklaren Aktionen führt der GM/Co-GM die Probe im Post aus: mit Anlass, Ziel-Held und Modifikator; das Ergebnis wird automatisch berechnet.
                </li>
                <li>
                    <strong class="text-stone-100">7. Thread weiterführen</strong><br>
                    Reagiere auf andere Posts, halte IC/OOC getrennt und markiere Spoiler sauber.
                </li>
            </ol>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">IC-Mindeststandard</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. Ich-Form statt Er-/Sie-Erzählung.</li>
                    <li>2. Klarer Handlungsimpuls pro Post.</li>
                    <li>3. Nicht für andere Figuren entscheiden.</li>
                    <li>4. Dauerhaft lesbar: keine Textwände ohne Abschnitte.</li>
                </ul>
            </article>

            <article class="rounded-xl border border-stone-800 bg-neutral-900/65 p-5">
                <h2 class="font-heading text-xl text-stone-100">Typische Fehler am Anfang</h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-stone-300">
                    <li>1. Zu lange Vorgeschichte statt aktueller Aktion.</li>
                    <li>2. OOC-Infos mitten im IC-Absatz.</li>
                    <li>3. Eigene Probenergebnisse ohne GM-Freigabe im Freitext festlegen.</li>
                    <li>4. Ohne Rückbezug auf die letzten zwei Posts schreiben.</li>
                </ul>
            </article>
        </section>
    </section>
@endsection

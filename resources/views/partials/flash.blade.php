@php($statusMessage = session('status'))
@php($postFeedback = session('post_feedback'))
@php($mediaWarning = session('media_warning'))

@if ($statusMessage)
    @if (is_array($postFeedback))
        @php($feedbackKind = (string) ($postFeedback['kind'] ?? 'ic'))
        @php($feedbackTitle = (string) ($postFeedback['title'] ?? 'Beitrag gespeichert'))
        @php($feedbackNote = (string) ($postFeedback['note'] ?? ''))
        <section
            id="app-flash"
            class="immersion-feedback mb-6 rounded-lg border px-4 py-3 text-sm"
            data-post-feedback
            data-feedback-kind="{{ $feedbackKind }}"
        >
            <p class="immersion-feedback-title">{{ $feedbackTitle }}</p>
            <p class="mt-1">{{ $statusMessage }}</p>
            @if ($feedbackNote !== '')
                <p class="immersion-feedback-note mt-1">{{ $feedbackNote }}</p>
            @endif
        </section>
    @else
        <div id="app-flash" class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-200">
            {{ $statusMessage }}
        </div>
    @endif
@endif

@if ($mediaWarning)
    <div class="mb-6 rounded-lg border border-amber-600/45 bg-amber-950/30 px-4 py-3 text-sm text-amber-100" data-media-warning>
        {{ $mediaWarning }}
    </div>
@endif

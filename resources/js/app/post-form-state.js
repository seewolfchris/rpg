export function postFormState(initialState = {}) {
    return {
        postType: String(initialState.postType || 'ic'),
        postMode: String(initialState.postMode || 'character'),
        contentFormat: String(initialState.contentFormat || 'plain'),
        probeEnabled: initialState.probeEnabled === true,

        init() {
            this.$watch('postType', () => this.syncPostModeState());
            this.$watch('postMode', () => this.syncPostModeState());
            this.syncPostModeState();
        },

        isGmMode() {
            return this.postType === 'ic' && this.postMode === 'gm';
        },

        syncPostModeState() {
            if (this.postType !== 'ic') {
                this.postMode = 'character';
            }

            if (this.isGmMode() && this.$refs.characterIdField) {
                this.$refs.characterIdField.value = '';
            }
        },

        formatHint() {
            if (this.contentFormat === 'markdown') {
                return 'Markdown aktiv: Vorschau und Format-Hotkeys sind freigeschaltet.';
            }

            if (this.contentFormat === 'bbcode') {
                return 'BBCode aktiv: Vorschau ist deaktiviert, klassische Foren-Tags bleiben nutzbar.';
            }

            return 'Klartext aktiv: roher Text ohne Markdown/BBCode-Rendering.';
        },
    };
}

export function registerPostFormStateComponent(Alpine) {
    Alpine.data('postFormState', postFormState);
}

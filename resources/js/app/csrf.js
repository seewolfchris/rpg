export function getCsrfToken() {
    const tokenNode = document.querySelector('meta[name="csrf-token"]');

    if (!(tokenNode instanceof HTMLMetaElement)) {
        return '';
    }

    return tokenNode.content || '';
}

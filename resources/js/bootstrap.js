import axios from 'axios';
import Alpine from '@alpinejs/csp';
import htmx from 'htmx.org';

window.axios = axios;
window.Alpine = Alpine;
window.htmx = htmx;

const csrfNode = document.querySelector('meta[name="csrf-token"]');
const csrfToken = csrfNode instanceof HTMLMetaElement ? (csrfNode.content || '') : '';

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
if (csrfToken !== '') {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

if (window.htmx) {
    window.htmx.config.allowEval = false;
    window.htmx.config.allowScriptTags = false;
    window.htmx.config.selfRequestsOnly = true;
    window.htmx.config.historyEnabled = true;
    window.htmx.config.globalViewTransitions = false;
    window.htmx.config.defaultSwapStyle = 'innerHTML';

    if (csrfToken !== '') {
        window.htmx.config.headers = {
            ...window.htmx.config.headers,
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    document.addEventListener('htmx:configRequest', (event) => {
        if (!event?.detail?.headers) {
            return;
        }

        event.detail.headers['X-Requested-With'] = 'XMLHttpRequest';

        if (csrfToken !== '') {
            event.detail.headers['X-CSRF-TOKEN'] = csrfToken;
        }
    });
}

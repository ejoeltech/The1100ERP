/**
 * Numeric Formatting Helpers for 1100ERP
 */

/**
 * WP4: every same-origin fetch() automatically carries the CSRF token
 * (from the csrf-token meta tag rendered by includes/header.php).
 * Third-party scripts calling cross-origin URLs are untouched.
 */
(function () {
    if (typeof window === 'undefined' || !window.fetch) return;
    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        try {
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            const sameOrigin = url === '' || url.startsWith('/') || url.startsWith(window.location.origin)
                || (!/^[a-z][a-z0-9+.-]*:/i.test(url));
            const method = ((init && init.method) || (typeof input !== 'string' && input && input.method) || 'GET').toUpperCase();
            if (sameOrigin && method !== 'GET' && method !== 'HEAD') {
                const meta = document.querySelector('meta[name="csrf-token"]');
                const token = meta && meta.getAttribute('content');
                if (token) {
                    init = init || {};
                    init.headers = init.headers || {};
                    if (init.headers instanceof Headers) {
                        if (!init.headers.has('X-CSRF-TOKEN')) init.headers.set('X-CSRF-TOKEN', token);
                    } else if (Array.isArray(init.headers)) {
                        if (!init.headers.some(h => String(h[0]).toLowerCase() === 'x-csrf-token')) {
                            init.headers.push(['X-CSRF-TOKEN', token]);
                        }
                    } else {
                        if (!init.headers['X-CSRF-TOKEN'] && !init.headers['x-csrf-token']) {
                            init.headers['X-CSRF-TOKEN'] = token;
                        }
                    }
                }
            }
        } catch (e) { /* never break the caller's request */ }
        return nativeFetch(input, init);
    };
})();

function formatNumber(num, decimals = 2) {
    if (num === null || num === undefined || isNaN(num) || num === '') return '0';
    
    // Remove .00 if not needed
    const formatted = Number(num).toLocaleString('en-NG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals
    });
    
    return formatted;
}

function parseNumber(str) {
    if (!str) return 0;
    if (typeof str === 'number') return str;
    // Remove commas and other non-numeric chars except decimal and minus
    return parseFloat(String(str).replace(/,/g, ''));
}

function formatInput(input, decimals = 2) {
    const val = parseNumber(input.value);
    input.value = formatNumber(val, decimals);
}

function unformatInput(input) {
    const val = parseNumber(input.value);
    input.value = val === 0 ? '' : val;
}

function formatCurrency(amount) {
    return '₦' + formatNumber(amount, 2);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

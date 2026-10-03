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

// Quantity boxes are whole numbers: no decimals, ever.
// Rounds fractional input, formats with grouping (parseNumber strips commas back).
function formatQtyInput(input) {
    let val = parseNumber(input.value);
    if (isNaN(val) || val < 0) val = 0;
    input.value = formatNumber(Math.round(val), 0);
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

/**
 * POST helper for state-changing actions. Builds a form carrying the CSRF
 * token from the page's meta tag (rendered by includes/header.php).
 * Use for ALL dynamic (JS-built) POSTs — plain form.submit() without the
 * token is rejected by the server gate.
 */
function postApiAction(path, params, target) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = path;
    if (target) form.target = target;
    for (const [k, v] of Object.entries(params || {})) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = k;
        inp.value = v;
        form.appendChild(inp);
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        const t = document.createElement('input');
        t.type = 'hidden';
        t.name = 'csrf_token';
        t.value = meta.getAttribute('content');
        form.appendChild(t);
    }
    document.body.appendChild(form);
    form.submit();
}

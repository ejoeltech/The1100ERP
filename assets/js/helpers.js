/**
 * Numeric Formatting Helpers for 1100ERP
 */

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

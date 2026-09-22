/**
 * Shared Quill rich-text editor loader + helper.
 *
 * Usage per page:
 *   loadQuill(function () {
 *       var editor = QuillHelper.create('myTextareaId');
 *       editor.setHTML('<p>Hello</p>');
 *       var html = editor.getHTML();
 *   }, quillAssetBase);
 *
 * quillAssetBase: URL path to /assets, e.g. '../assets' or '<?php echo $base_path; ?>/assets'.
 * Loads local vendor first, falls back to jsDelivr CDN (no API key required).
 */
function loadQuill(callback, assetBase) {
    if (typeof Quill !== 'undefined') {
        callback();
        return;
    }

    var localPath = assetBase + '/vendors/quill/quill.js';
    var cdnPath = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';

    console.log('Attempting to load local Quill...');
    var script = document.createElement('script');
    script.src = localPath;
    script.onload = function () {
        console.log('Local Quill loaded successfully.');
        callback();
    };
    script.onerror = function () {
        console.warn('Local Quill failed, falling back to CDN...');
        var backup = document.createElement('script');
        backup.src = cdnPath;
        backup.onload = function () {
            console.log('CDN Quill loaded successfully.');
            callback();
        };
        backup.onerror = function () {
            console.error('Quill loading failed completely. Check connection.');
            alert('Editor Error: The rich text editor could not be loaded. Some features may be limited.');
        };
        document.head.appendChild(backup);
    };
    document.head.appendChild(script);
}

var QuillHelper = (function () {
    var TOOLBAR = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'align': [] }],
        ['link', 'clean']
    ];

    // Turns a <textarea id> into a Quill snow editor, keeps the textarea
    // synced (for normal form POSTs) and returns get/set helpers.
    function create(textareaId, options) {
        var textarea = document.getElementById(textareaId);
        if (!textarea) {
            console.error('QuillHelper: textarea #' + textareaId + ' not found.');
            return null;
        }
        if (typeof Quill === 'undefined') return null;

        var host = document.createElement('div');
        host.className = 'quill-host bg-white';
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(host, textarea);

        var quill = new Quill(host, {
            theme: 'snow',
            modules: { toolbar: (options && options.toolbar) || TOOLBAR }
        });

        if (options && options.height) {
            quill.root.style.minHeight = options.height + 'px';
        }

        var initial = textarea.value || textarea.getAttribute('data-initial-html') || '';
        if (initial) quill.root.innerHTML = initial;

        function sync() {
            // Quill pads empty content; store '' so validation stays sane
            var text = quill.getText().trim();
            textarea.value = text.length === 0 ? '' : quill.root.innerHTML;
        }
        quill.on('text-change', sync);

        var form = textarea.closest('form');
        if (form) form.addEventListener('submit', sync);

        // Keep the host sized like the old editors
        if (!options || !options.height) quill.root.style.minHeight = '300px';

        return {
            quill: quill,
            getHTML: function () {
                sync();
                return textarea.value;
            },
            getText: function () { return quill.getText(); },
            setHTML: function (html) {
                quill.root.innerHTML = html || '';
                sync();
            }
        };
    }

    return { create: create, TOOLBAR: TOOLBAR };
})();

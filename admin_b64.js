/**
 * Base64-encode all textarea and text input values before form submission.
 * This prevents Cloudflare WAF from blocking POST requests containing
 * HTML, CSS, or code-like content.
 *
 * Usage: include this script on any admin page, then add class="b64-form"
 * to forms that should be encoded. A hidden _b64=1 field is auto-added
 * so the server knows to decode.
 */
(function () {
    function utf8ToBase64(str) {
        return btoa(unescape(encodeURIComponent(str)));
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList.contains('b64-form')) return;

        // Sync TinyMCE if present
        if (window.tinymce) {
            tinymce.triggerSave();
        }

        // Add hidden marker so PHP knows to decode
        if (!form.querySelector('input[name="_b64"]')) {
            var marker = document.createElement('input');
            marker.type = 'hidden';
            marker.name = '_b64';
            marker.value = '1';
            form.appendChild(marker);
        }

        // Encode all textareas
        var textareas = form.querySelectorAll('textarea');
        for (var i = 0; i < textareas.length; i++) {
            textareas[i].value = utf8ToBase64(textareas[i].value);
        }

        // Encode text inputs (skip hidden, checkbox, radio, file, submit, button)
        var inputs = form.querySelectorAll('input[type="text"]');
        for (var j = 0; j < inputs.length; j++) {
            inputs[j].value = utf8ToBase64(inputs[j].value);
        }
    }, true); // use capture so it fires before the form posts
})();

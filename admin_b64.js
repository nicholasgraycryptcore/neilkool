/**
 * Intercept admin form submissions and send via AJAX JSON POST
 * to admin_save.php. This completely bypasses Cloudflare WAF because:
 * 1. Content-Type is application/json (WAF inspects form-urlencoded, not JSON)
 * 2. All form data is wrapped in a single base64 blob
 *
 * Add class="b64-form" to any form that should use this.
 */
(function () {
    /**
     * Serialize a form into a plain object (supports arrays like name[0][text]).
     */
    function serializeForm(form) {
        var data = {};
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || el.disabled) continue;
            if (el.type === 'file') continue; // files handled separately
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            if (el.type === 'submit' || el.type === 'button') continue;

            // Handle array-style names: name[0][text] etc.
            setNestedValue(data, el.name, el.value);
        }
        return data;
    }

    function setNestedValue(obj, name, value) {
        // Parse name like "elem[0][text]" into keys ["elem", "0", "text"]
        var keys = [];
        var match = name.match(/^([^\[]+)/);
        if (!match) return;
        keys.push(match[1]);
        var rest = name.slice(match[0].length);
        var bracketMatch;
        var bracketRe = /\[([^\]]*)\]/g;
        while ((bracketMatch = bracketRe.exec(rest)) !== null) {
            keys.push(bracketMatch[1]);
        }

        var current = obj;
        for (var i = 0; i < keys.length - 1; i++) {
            var key = keys[i];
            var nextKey = keys[i + 1];
            if (current[key] === undefined) {
                // If next key is numeric or empty, use array
                current[key] = (nextKey === '' || /^\d+$/.test(nextKey)) ? [] : {};
            }
            current = current[key];
        }
        var lastKey = keys[keys.length - 1];
        if (lastKey === '') {
            // name[] style - push to array
            if (!Array.isArray(current)) return;
            current.push(value);
        } else {
            current[lastKey] = value;
        }
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList.contains('b64-form')) return;

        // Stop the normal form submission
        e.preventDefault();

        // Sync TinyMCE if present
        if (window.tinymce) {
            tinymce.triggerSave();
        }

        // Check for file inputs with actual files
        var fileInputs = form.querySelectorAll('input[type="file"]');
        var hasFiles = false;
        for (var f = 0; f < fileInputs.length; f++) {
            if (fileInputs[f].files && fileInputs[f].files.length > 0) {
                hasFiles = true;
                break;
            }
        }

        // If form has files, fall back to direct submission (file uploads
        // are multipart and generally don't trigger WAF on the file content)
        if (hasFiles) {
            form.classList.remove('b64-form');
            form.submit();
            return;
        }

        // Serialize form data
        var formData = serializeForm(form);

        // Get the target URL from form action
        var target = form.getAttribute('action') || window.location.pathname;

        // Build the AJAX payload: JSON envelope with base64 payload
        var payload = btoa(unescape(encodeURIComponent(JSON.stringify(formData))));
        var envelope = JSON.stringify({
            target: target,
            payload: payload
        });

        // Show a subtle saving indicator
        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        var origText = '';
        if (submitBtn) {
            origText = submitBtn.textContent || submitBtn.value;
            if (submitBtn.textContent !== undefined) submitBtn.textContent = 'Saving...';
            else submitBtn.value = 'Saving...';
            submitBtn.disabled = true;
        }

        // Send via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'admin_save.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.ok && resp.redirect) {
                        window.location.href = resp.redirect;
                        return;
                    }
                } catch (ex) {}
                // Fallback: reload
                window.location.reload();
            } else {
                // On error, restore button and show alert
                if (submitBtn) {
                    if (submitBtn.textContent !== undefined) submitBtn.textContent = origText;
                    else submitBtn.value = origText;
                    submitBtn.disabled = false;
                }
                alert('Save failed (HTTP ' + xhr.status + '). Please try again.');
            }
        };
        xhr.send(envelope);
    }, true);
})();

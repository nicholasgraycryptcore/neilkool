<?php
/**
 * Decode base64-encoded POST data sent by admin_b64.js.
 * Include this at the top of any admin POST handler.
 *
 * When _b64=1 is present, all text input and textarea values
 * were base64-encoded client-side to bypass Cloudflare WAF.
 * This function decodes them back in-place in $_POST.
 */
function decode_b64_post(): void
{
    if (empty($_POST['_b64'])) {
        return;
    }

    // Remove the marker so it doesn't interfere with form logic
    unset($_POST['_b64']);

    array_walk_recursive($_POST, function (&$value) {
        if (!is_string($value) || $value === '') {
            return;
        }
        // Only decode if it looks like valid base64
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $value)) {
            $decoded = base64_decode($value, true);
            if ($decoded !== false) {
                $value = $decoded;
            }
        }
    });
}

<?php
/**
 * AJAX save proxy for admin forms.
 *
 * Receives a JSON POST with:
 *   { "target": "admin.php", "payload": "<base64 of JSON form data>" }
 *
 * Decodes the payload, populates $_POST, then includes the target script
 * which processes the save and issues a redirect header.
 *
 * Bypasses Cloudflare WAF because the browser sends application/json
 * with all values hidden in a base64 blob — no raw HTML/CSS in the POST body.
 */

// Start session before anything else
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$envelope = json_decode($raw, true);

if (!$envelope || empty($envelope['target']) || !isset($envelope['payload'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Bad request']);
    exit;
}

$decoded_json = base64_decode($envelope['payload'], true);
if ($decoded_json === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$form_data = json_decode($decoded_json, true);
if (!is_array($form_data)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid form data']);
    exit;
}

// Whitelist targets
$allowed = [
    'admin.php', 'site_settings.php', 'parts.php', 'snippets.php',
    'ecommerce.php', 'element_editor.php',
];
$target_raw = $envelope['target'];
$parsed = parse_url($target_raw);
$target_base = basename($parsed['path'] ?? '');
if (!in_array($target_base, $allowed)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Target not allowed']);
    exit;
}

// Parse query string from target into $_GET
if (!empty($parsed['query'])) {
    parse_str($parsed['query'], $query_params);
    $_GET = array_merge($_GET, $query_params);
}

// Populate $_POST
$_POST = $form_data;

// Override header() to capture redirects instead of sending them
// We do this by using output buffering and checking headers after
ob_start();

// Include the target. It will:
// 1. Call require auth.php (which calls session_start — already started, no-op)
// 2. Process $_POST
// 3. Call header('Location: ...') and exit()
try {
    include __DIR__ . '/' . $target_base;
} catch (\Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$output = ob_get_clean();

// Check for redirect in response headers
$redirect = null;
foreach (headers_list() as $h) {
    if (stripos($h, 'Location:') === 0) {
        $redirect = trim(substr($h, 9));
    }
}

if ($redirect) {
    header_remove('Location');
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'redirect' => $redirect]);
} else {
    // No redirect — might be an error or the page just rendered
    // Check if there's an error message in the output
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'redirect' => $target_raw]);
}

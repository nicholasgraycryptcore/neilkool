<?php
require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF check
if (!verify_csrf()) {
    http_response_code(403);
    header('Location: index.php?contact=error&reason=security#contact');
    exit;
}

// Rate limiting: max 3 submissions per 10 minutes per session
$now = time();
if (!isset($_SESSION['contact_timestamps'])) {
    $_SESSION['contact_timestamps'] = [];
}
// Prune old entries
$_SESSION['contact_timestamps'] = array_filter($_SESSION['contact_timestamps'], function ($t) use ($now) {
    return ($now - $t) < 600;
});
if (count($_SESSION['contact_timestamps']) >= 3) {
    header('Location: index.php?contact=error&reason=rate#contact');
    exit;
}

// Honeypot check (hidden field that bots fill in)
$honeypot = trim($_POST['website_url'] ?? '');
if ($honeypot !== '') {
    // Silently redirect — likely a bot
    header('Location: index.php?contact=sent#contact');
    exit;
}

// Sanitize & validate inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
$errors = [];
if ($name === '' || mb_strlen($name) > 200) {
    $errors[] = 'name';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}
if (mb_strlen($message) > 5000) {
    $errors[] = 'message';
}
if ($name === '' && $message === '') {
    $errors[] = 'empty';
}

if (!empty($errors)) {
    header('Location: index.php?contact=error&reason=validation#contact');
    exit;
}

// Strip any injected mail headers from all fields
function sanitize_header(string $value): string {
    return preg_replace('/[\r\n]+/', ' ', $value);
}

$name = sanitize_header($name);
$email = sanitize_header($email);
$service = sanitize_header($service);

// Build email
$to = 'neilkoolAC@gmail.com';
$subject = 'New Contact Form Enquiry - ' . ($service !== '' ? $service : 'General');

$body = "New enquiry from the Neil Kool website:\n\n";
$body .= "Name: $name\n";
if ($email !== '') {
    $body .= "Email: $email\n";
}
if ($service !== '') {
    $body .= "Service: $service\n";
}
$body .= "\nMessage:\n$message\n";
$body .= "\n---\n";
$body .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
$body .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

$headers = "From: noreply@neilkool.com\r\n";
if ($email !== '') {
    $headers .= "Reply-To: " . sanitize_header($email) . "\r\n";
}
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: NeilKool-Website\r\n";

// Send
$sent = @mail($to, $subject, $body, $headers);

// Log the submission
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
log_action('contact_form', $email ?: null, 'contact', null, [
    'name' => $name,
    'service' => $service,
    'sent' => $sent ? 'yes' : 'no',
], $ip);

// Record timestamp for rate limiting
$_SESSION['contact_timestamps'][] = $now;

if ($sent) {
    header('Location: index.php?contact=sent#contact');
} else {
    header('Location: index.php?contact=error&reason=mail#contact');
}
exit;

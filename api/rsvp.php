<?php
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
send_security_headers(false);

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Health check: lets us confirm PHP is running here without writing anything.
if ($method === 'GET') {
    respond(200, ['ok' => true, 'service' => 'rsvp']);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

// Only accept submissions that originate from this site.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $thisHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (!$originHost || strcasecmp((string) $originHost, (string) $thisHost) !== 0) {
        respond(403, ['ok' => false, 'error' => 'Forbidden']);
    }
}

// Honeypot: real guests never see or fill this field. Pretend success for bots.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

if (rl_count('rsvp', 3600) >= 10) {
    respond(429, ['ok' => false, 'error' => 'Too many submissions. Please try again later.']);
}

$record = build_record($_POST, 'website');
if ($record === null) {
    respond(422, ['ok' => false, 'error' => 'Please enter your name.']);
}
if (!preg_match('/^[0-9+()\s-]{7,}$/', $record['phone'])) {
    respond(422, ['ok' => false, 'error' => 'Please enter a valid phone number.']);
}

if (store_append([$record]) !== 1) {
    error_log('RSVP: could not write to ' . rsvp_file());
    respond(500, ['ok' => false, 'error' => 'Could not save your RSVP.']);
}
rl_hit('rsvp', 3600);

// Courtesy email to the host. The saved RSVP is the source of truth, so a mail failure is ignored.
if (getenv('RSVP_DISABLE_MAIL') !== '1') {
    $subject = 'New RSVP: ' . $record['name'] . ' (' . ($record['attending'] === 'Yes' ? 'attending' : 'declined') . ')';
    $lines = [
        'Name: ' . $record['name'],
        'Attending: ' . $record['attending'],
        'Guests: ' . $record['guests'],
        'Phone: ' . $record['phone'],
        'Email: ' . ($record['email'] ?: '-'),
        'Dietary: ' . ($record['dietary'] ?: '-'),
        'Message: ' . ($record['message'] ?: '-'),
        '',
        'See every RSVP: https://' . SITE_HOST . '/admin/',
    ];
    $headers = [
        'From: RSVP <rsvp@' . SITE_HOST . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($record['email'] !== '') {
        $headers[] = 'Reply-To: ' . $record['email'];
    }
    @mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\r\n", $lines), implode("\r\n", $headers));
}

respond(200, ['ok' => true]);

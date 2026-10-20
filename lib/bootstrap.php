<?php
declare(strict_types=1);

/*
 * Shared helpers for the RSVP endpoint and the admin dashboard.
 * Written to run on PHP 7.4+ with no extensions beyond the usual cPanel defaults.
 */

const NOTIFY_EMAIL   = 'Ngobeni.amukelani@gmail.com';
const SITE_HOST      = 'drleratongobenirsvp.lailai.co.za';
const EVENT_TZ       = 'Africa/Johannesburg';
const MIN_PASSWORD   = 10;
const SESSION_IDLE   = 28800; // 8 hours

/* Starter accounts. Passwords are temporary: once someone changes theirs in the
   dashboard, the new hash lives in data/users.json and this seed is ignored. */
const SEED_USERS = [
    'admin' => '$2y$11$F.wzHuCdT9jIg6wOwGqRjuYucVjhzVtGRFnIO4UbXilyqQTe1ST86',
    'host'  => '$2y$11$qekoIyeUTrxeXHNN9t0ft.a10k3IIxybGODQ4a.cyzZnfkF0H95Z.',
];

date_default_timezone_set(EVENT_TZ);
ini_set('display_errors', '0');

/* ---------- storage location ---------- */

function data_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $env = getenv('RSVP_DATA_DIR');
    $dir = ($env !== false && $env !== '') ? rtrim($env, '/\\') : dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Belt and braces: never let the web server hand out anything from here.
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    $ix = $dir . '/index.html';
    if (!is_file($ix)) {
        @file_put_contents($ix, '');
    }
    return $dir;
}

/* Random suffix for data file names, so they are unguessable even if .htaccess were ignored. */
function data_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $file = data_dir() . '/.key';
    if (!is_file($file)) {
        $fh = @fopen($file, 'x');
        if ($fh) {
            fwrite($fh, bin2hex(random_bytes(12)));
            fclose($fh);
        }
    }
    $key = preg_replace('/[^a-f0-9]/', '', (string) @file_get_contents($file));
    if ($key === '') {
        $key = 'x' . substr(hash('sha256', __DIR__), 0, 24);
    }
    return $key;
}

/* ---------- text helpers ---------- */

function norm_key(string $k): string
{
    return (string) preg_replace('/[^a-z0-9]/', '', strtolower($k));
}

function clean_text($v, int $max, bool $multiline = false): string
{
    if (!is_scalar($v)) {
        return '';
    }
    $v = (string) $v;
    if (!preg_match('//u', $v)) {
        $v = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $v);
    }
    $pattern = $multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u';
    $v = (string) preg_replace($pattern, ' ', $v);
    $v = trim($v);
    if (preg_match('/^.{0,' . $max . '}/us', $v, $m)) {
        $v = $m[0];
    }
    return trim($v);
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Look up the first matching field in $src, ignoring case, spaces and punctuation. */
function pick(array $src, array $aliases): string
{
    $map = [];
    foreach ($src as $k => $v) {
        if (is_scalar($v)) {
            $map[norm_key((string) $k)] = (string) $v;
        }
    }
    foreach ($aliases as $a) {
        $n = norm_key($a);
        if (isset($map[$n]) && trim($map[$n]) !== '') {
            return $map[$n];
        }
    }
    return '';
}

/* Turn raw form / CSV / JSON fields into a clean RSVP record, or null if unusable. */
function build_record(array $src, string $source, ?int $ts = null): ?array
{
    $name = clean_text(pick($src, ['name', 'fullname', 'guestname']), 100);
    if ($name === '') {
        return null;
    }

    $att = strtolower(clean_text(pick($src, ['attending', 'attendance', 'rsvp', 'response']), 40));
    $attending = preg_match('/^(no|n|false|decline|regret|not)/', $att) ? 'No' : 'Yes';

    $guests = clean_text(pick($src, ['numberofguests', 'guests', 'numguests', 'partysize']), 10);
    if (!preg_match('/^\d{1,2}\+?$/', $guests)) {
        $guests = '1';
    }

    $phone = preg_replace('/[^0-9+()\s-]/', '', clean_text(pick($src, ['phone', 'phonenumber', 'mobile', 'cell', 'tel']), 40));
    $email = clean_text(pick($src, ['email', 'emailaddress']), 120);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }

    return [
        'id'        => bin2hex(random_bytes(6)),
        'ts'        => $ts ?? time(),
        'name'      => $name,
        'phone'     => trim((string) $phone),
        'email'     => $email,
        'attending' => $attending,
        'guests'    => $guests,
        'dietary'   => clean_text(pick($src, ['dietaryrequirements', 'dietary', 'diet', 'allergies']), 500, true),
        'message'   => clean_text(pick($src, ['congratulatorymessage', 'message', 'comments', 'note']), 1500, true),
        'source'    => $source,
    ];
}

function record_fingerprint(array $r): string
{
    return sha1(strtolower($r['name']) . '|' . preg_replace('/\D/', '', $r['phone']) . '|' . $r['attending'] . '|' . $r['ts']);
}

/* ---------- RSVP store: one JSON object per line, always under a file lock ---------- */

function rsvp_file(): string
{
    return data_dir() . '/rsvps-' . data_key() . '.jsonl';
}

function store_all(): array
{
    $file = rsvp_file();
    if (!is_file($file)) {
        return [];
    }
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return [];
    }
    flock($fh, LOCK_SH);
    $rows = [];
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (is_array($row) && isset($row['id'], $row['name'])) {
            $rows[] = $row;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    usort($rows, function ($a, $b) {
        return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
    });
    return $rows;
}

function store_append(array $records): int
{
    if (!$records) {
        return 0;
    }
    $fh = fopen(rsvp_file(), 'ab');
    if (!$fh) {
        return 0;
    }
    flock($fh, LOCK_EX);
    $n = 0;
    foreach ($records as $r) {
        if (fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n") !== false) {
            $n++;
        }
    }
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $n;
}

/* Add imported records, skipping any we already hold. Returns [added, skipped]. */
function store_import(array $records): array
{
    $fh = fopen(rsvp_file(), 'c+b');
    if (!$fh) {
        return [0, count($records)];
    }
    flock($fh, LOCK_EX);
    $seen = [];
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (is_array($row) && isset($row['name'], $row['ts'], $row['attending'])) {
            $row += ['phone' => ''];
            $seen[record_fingerprint($row)] = true;
        }
    }
    $added = 0;
    $skipped = 0;
    fseek($fh, 0, SEEK_END);
    foreach ($records as $r) {
        $fp = record_fingerprint($r);
        if (isset($seen[$fp])) {
            $skipped++;
            continue;
        }
        $seen[$fp] = true;
        fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $added++;
    }
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return [$added, $skipped];
}

function store_delete(string $id): bool
{
    $file = rsvp_file();
    if (!is_file($file)) {
        return false;
    }
    $fh = fopen($file, 'c+b');
    if (!$fh) {
        return false;
    }
    flock($fh, LOCK_EX);
    $keep = [];
    $found = false;
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (is_array($row) && ($row['id'] ?? null) === $id) {
            $found = true;
            continue;
        }
        if (trim($line) !== '') {
            $keep[] = rtrim($line, "\r\n");
        }
    }
    if ($found) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $keep ? implode("\n", $keep) . "\n" : '');
        fflush($fh);
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $found;
}

/* ---------- tiny JSON-file helpers (rate limits, users) ---------- */

function json_file_update(string $file, callable $fn)
{
    $fh = fopen($file, 'c+b');
    if (!$fh) {
        return null;
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = $raw !== '' && $raw !== false ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $result = $fn($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function json_file_read(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function client_key(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|rsvp-site');
}

/* Sliding-window counter. Returns how many hits the key has inside the window. */
function rl_count(string $bucket, int $window): int
{
    $file = data_dir() . '/rl-' . $bucket . '.json';
    $key = client_key();
    $now = time();
    $data = json_file_read($file);
    $hits = array_filter($data[$key] ?? [], function ($t) use ($now, $window) {
        return $t > $now - $window;
    });
    return count($hits);
}

function rl_hit(string $bucket, int $window): void
{
    $key = client_key();
    $now = time();
    json_file_update(data_dir() . '/rl-' . $bucket . '.json', function (&$data) use ($key, $now, $window) {
        foreach ($data as $k => $hits) {
            $data[$k] = array_values(array_filter($hits, function ($t) use ($now, $window) {
                return $t > $now - $window;
            }));
            if (!$data[$k]) {
                unset($data[$k]);
            }
        }
        $data[$key][] = $now;
    });
}

function rl_clear(string $bucket): void
{
    $key = client_key();
    json_file_update(data_dir() . '/rl-' . $bucket . '.json', function (&$data) use ($key) {
        unset($data[$key]);
    });
}

/* ---------- users & sessions ---------- */

function users_file(): string
{
    return data_dir() . '/users-' . data_key() . '.json';
}

function all_users(): array
{
    $users = SEED_USERS;
    foreach (json_file_read(users_file()) as $name => $row) {
        if (isset($users[$name]) && is_array($row) && !empty($row['hash'])) {
            $users[$name] = $row['hash'];
        }
    }
    return $users;
}

function uses_temporary_password(string $user): bool
{
    $stored = json_file_read(users_file());
    return empty($stored[$user]['hash']);
}

function set_password(string $user, string $new): void
{
    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 11]);
    json_file_update(users_file(), function (&$data) use ($user, $hash) {
        $data[$user] = ['hash' => $hash, 'changed' => time()];
    });
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = data_dir() . '/sessions';
    if (!is_dir($path)) {
        @mkdir($path, 0700, true);
    }
    if (is_dir($path) && is_writable($path)) {
        session_save_path($path);
    }
    session_name('rsvp_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE);
    session_start();
    if (isset($_SESSION['last']) && time() - (int) $_SESSION['last'] > SESSION_IDLE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

function try_login(string $user, string $pass): bool
{
    $users = all_users();
    $hash = $users[$user] ?? '$2y$11$abcdefghijklmnopqrstuuAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    $ok = password_verify($pass, $hash) && isset($users[$user]);
    return $ok;
}

/* ---------- misc ---------- */

function send_security_headers(bool $isAdmin): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    if ($isAdmin) {
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    }
}

function format_ts(int $ts): string
{
    return date('d M Y, H:i', $ts);
}

function guest_count(string $g): int
{
    return (int) rtrim($g, '+');
}

<?php
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

send_security_headers(true);
start_session();

const IMPORT_MAX_BYTES = 2097152;

function redirect(string $to = 'index.php'): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function page_head(string $title): void
{
    $css = @filemtime(__DIR__ . '/admin.css') ?: 1;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<title>' . h($title) . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Italiana&family=Poppins:wght@400;500;600&display=swap">'
        . '<link rel="stylesheet" href="admin.css?v=' . $css . '">'
        . '</head><body>';
}

function page_foot(bool $withScript): void
{
    if ($withScript) {
        $js = @filemtime(__DIR__ . '/admin.js') ?: 1;
        echo '<script src="admin.js?v=' . $js . '"></script>';
    }
    echo '</body></html>';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="flash flash-' . ($f['type'] === 'ok' ? 'ok' : 'err') . '" role="' . ($f['type'] === 'ok' ? 'status' : 'alert') . '">' . h($f['msg']) . '</div>';
    }
}

/* ---------- import parsing (Formspree CSV or JSON exports) ---------- */

function parse_import(string $raw): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $trim = ltrim($raw);
    if ($trim === '') {
        return [];
    }
    if ($trim[0] === '{' || $trim[0] === '[') {
        $data = json_decode($trim, true);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['submissions']) && is_array($data['submissions'])) {
            $data = $data['submissions'];
        }
        return array_values(array_filter($data, 'is_array'));
    }
    $firstLine = strtok($trim, "\r\n");
    $delim = ',';
    $best = substr_count((string) $firstLine, ',');
    foreach ([';', "\t"] as $d) {
        $c = substr_count((string) $firstLine, $d);
        if ($c > $best) {
            $best = $c;
            $delim = $d;
        }
    }
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $trim);
    rewind($fh);
    $header = fgetcsv($fh, 0, $delim, '"', '');
    $rows = [];
    if (!$header) {
        return [];
    }
    while (($line = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        if (count($line) === 1 && trim((string) $line[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[(string) $col] = $line[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function import_records(array $rows): array
{
    $dateKeys = ['date', '_date', 'submittedat', 'submitted', 'createdat', 'created', 'timestamp', 'submissiondate', 'time'];
    $records = [];
    $invalid = 0;
    foreach ($rows as $row) {
        $when = strtotime(pick($row, $dateKeys));
        $rec = build_record($row, 'import', $when ?: time());
        if ($rec === null) {
            $invalid++;
            continue;
        }
        $records[] = $rec;
    }
    return [$records, $invalid];
}

function csv_cell($v, bool $isPhone = false): string
{
    $v = (string) $v;
    if (!$isPhone && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false) {
        $v = "'" . $v;
    }
    return '"' . str_replace('"', '""', $v) . '"';
}

/* ---------- not signed in ---------- */

$user = $_SESSION['user'] ?? null;

if ($user === null || !isset(all_users()[$user])) {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!csrf_ok()) {
            $error = 'Your session expired. Please try again.';
        } elseif (rl_count('login', 900) >= 6) {
            $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $u = strtolower(clean_text($_POST['username'] ?? '', 40));
            $p = (string) ($_POST['password'] ?? '');
            if (try_login($u, $p)) {
                rl_clear('login');
                session_regenerate_id(true);
                $_SESSION['user'] = $u;
                $_SESSION['last'] = time();
                redirect();
            }
            rl_hit('login', 900);
            $error = 'That username or password is not right.';
        }
    }
    page_head('Sign in — RSVP Admin');
    ?>
    <main class="login-wrap">
      <form class="login-card" method="post" action="index.php" autocomplete="on">
        <div class="mark" aria-hidden="true">L N</div>
        <h1>RSVP Admin</h1>
        <p class="sub">Dr Lerato Ngobeni &middot; Graduation Party</p>
        <?php if ($error !== '') { echo '<div class="flash flash-err" role="alert">' . h($error) . '</div>'; } ?>
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn btn-primary" type="submit">Sign in</button>
      </form>
    </main>
    <?php
    page_foot(false);
    exit;
}

/* ---------- signed in: actions ---------- */

$action = (string) ($_GET['a'] ?? '');

if ($action === 'export') {
    $rows = store_all();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rsvps-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "Date,Name,Phone,Email,Attending,Guests,Dietary Requirements,Message,Source\r\n";
    foreach ($rows as $r) {
        echo implode(',', [
            csv_cell(date('Y-m-d H:i:s', (int) $r['ts'])),
            csv_cell($r['name']),
            csv_cell($r['phone'] ?? '', true),
            csv_cell($r['email'] ?? ''),
            csv_cell($r['attending'] ?? ''),
            csv_cell($r['guests'] ?? ''),
            csv_cell($r['dietary'] ?? ''),
            csv_cell($r['message'] ?? ''),
            csv_cell($r['source'] ?? ''),
        ]) . "\r\n";
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_ok()) {
        flash('err', 'Your session expired. Please try again.');
        redirect();
    }
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        redirect();
    }

    if ($do === 'delete') {
        $ok = store_delete((string) ($_POST['id'] ?? ''));
        flash($ok ? 'ok' : 'err', $ok ? 'RSVP deleted.' : 'That RSVP could not be found.');
        redirect();
    }

    if ($do === 'add') {
        $rec = build_record($_POST, 'manual');
        if ($rec === null) {
            flash('err', 'Please enter the guest\'s name.');
        } elseif (store_append([$rec]) === 1) {
            flash('ok', 'RSVP added for ' . $rec['name'] . '.');
        } else {
            flash('err', 'Could not save that RSVP.');
        }
        redirect();
    }

    if ($do === 'import') {
        $raw = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if (($_FILES['file']['size'] ?? 0) > IMPORT_MAX_BYTES) {
                flash('err', 'That file is too large (2 MB maximum).');
                redirect();
            }
            $raw = (string) file_get_contents($_FILES['file']['tmp_name']);
        } elseif (trim((string) ($_POST['paste'] ?? '')) !== '') {
            $raw = substr((string) $_POST['paste'], 0, IMPORT_MAX_BYTES);
        }
        if ($raw === '') {
            flash('err', 'Choose a CSV or JSON file, or paste its contents.');
            redirect();
        }
        $rows = parse_import($raw);
        if (!$rows) {
            flash('err', 'Nothing readable was found in that file. Use the CSV or JSON export from Formspree.');
            redirect();
        }
        list($records, $invalid) = import_records($rows);
        list($added, $skipped) = store_import($records);
        $msg = "Import finished: $added added";
        if ($skipped) {
            $msg .= ", $skipped already here (skipped)";
        }
        if ($invalid) {
            $msg .= ", $invalid without a name (ignored)";
        }
        flash($added > 0 || $skipped > 0 ? 'ok' : 'err', $msg . '.');
        redirect();
    }

    if ($do === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $again = (string) ($_POST['again'] ?? '');
        if (!try_login($user, $cur)) {
            flash('err', 'Your current password is not right.');
        } elseif (strlen($new) < MIN_PASSWORD) {
            flash('err', 'Your new password must be at least ' . MIN_PASSWORD . ' characters.');
        } elseif ($new !== $again) {
            flash('err', 'The two new passwords do not match.');
        } elseif ($new === $cur) {
            flash('err', 'Please choose a password you have not used before.');
        } else {
            set_password($user, $new);
            session_regenerate_id(true);
            flash('ok', 'Password changed.');
        }
        redirect();
    }

    redirect();
}

/* ---------- signed in: dashboard ---------- */

$rows = store_all();
$total = count($rows);
$yes = $no = $people = 0;
$plus = false;
foreach ($rows as $r) {
    if (($r['attending'] ?? 'Yes') === 'Yes') {
        $yes++;
        $people += guest_count((string) ($r['guests'] ?? '1'));
        if (substr((string) ($r['guests'] ?? ''), -1) === '+') {
            $plus = true;
        }
    } else {
        $no++;
    }
}
$latest = $rows ? format_ts((int) $rows[0]['ts']) : '—';

page_head('RSVP Admin — Dr Lerato Ngobeni');
?>
<header class="topbar">
  <div class="topbar-in">
    <div class="brand"><span class="brand-mark" aria-hidden="true">LN</span>
      <span class="brand-text"><strong>RSVP Admin</strong><small>Dr Lerato Ngobeni &middot; Graduation Party &middot; 28 Nov 2026</small></span></div>
    <div class="topbar-actions">
      <span class="who">Signed in as <b><?= h($user) ?></b></span>
      <a class="btn btn-ghost" href="index.php?a=export">Export CSV</a>
      <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="btn btn-ghost" type="submit">Sign out</button></form>
    </div>
  </div>
</header>

<main class="wrap">
  <?php show_flash(); ?>
  <?php if (uses_temporary_password($user)) { ?>
    <div class="flash flash-warn" role="status">You are still using a temporary password. Please change it under <b>Change password</b> below.</div>
  <?php } ?>

  <section class="stats" aria-label="Summary">
    <div class="stat"><span class="num"><?= $total ?></span><span class="lbl">Responses</span></div>
    <div class="stat"><span class="num"><?= $yes ?></span><span class="lbl">Attending</span></div>
    <div class="stat"><span class="num"><?= $no ?></span><span class="lbl">Declined</span></div>
    <div class="stat stat-accent"><span class="num"><?= $people ?><?= $plus ? '+' : '' ?></span><span class="lbl">Guests expected</span></div>
    <div class="stat stat-wide"><span class="num num-sm"><?= h($latest) ?></span><span class="lbl">Latest response</span></div>
  </section>

  <section class="list" aria-label="RSVP list">
    <div class="list-head">
      <h2>All RSVPs</h2>
      <div class="controls">
        <div class="chips" id="chips" hidden>
          <button type="button" class="chip is-on" data-filter="all">All</button>
          <button type="button" class="chip" data-filter="yes">Attending</button>
          <button type="button" class="chip" data-filter="no">Declined</button>
        </div>
        <label class="search"><span class="sr">Search RSVPs</span>
          <input type="search" id="q" placeholder="Search name, phone, email, message…" autocomplete="off"></label>
      </div>
    </div>
    <p class="count" id="count" aria-live="polite">Showing <?= $total ?> of <?= $total ?></p>

    <?php if (!$rows) { ?>
      <div class="empty">No RSVPs yet. New ones will appear here the moment a guest submits the form. You can also import your old Formspree RSVPs below.</div>
    <?php } else { ?>
    <div class="table-wrap">
      <table class="rsvp-table" id="tbl">
        <thead><tr><th>Date</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Guests</th><th>Dietary</th><th>Message</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) {
            $isYes = ($r['attending'] ?? 'Yes') === 'Yes';
            $hay = strtolower(($r['name'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['dietary'] ?? '') . ' ' . ($r['message'] ?? ''));
            ?>
          <tr data-status="<?= $isYes ? 'yes' : 'no' ?>" data-hay="<?= h($hay) ?>">
            <td data-label="Date" class="nowrap"><?= h(format_ts((int) $r['ts'])) ?><?php if (($r['source'] ?? '') !== 'website' && !empty($r['source'])) { echo '<span class="src">' . h($r['source']) . '</span>'; } ?></td>
            <td data-label="Name" class="strong"><?= h($r['name']) ?></td>
            <td data-label="Phone" class="nowrap"><?= ($r['phone'] ?? '') !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $r['phone'])) . '">' . h($r['phone']) . '</a>' : '—' ?></td>
            <td data-label="Email"><?= ($r['email'] ?? '') !== '' ? '<a href="mailto:' . h($r['email']) . '">' . h($r['email']) . '</a>' : '—' ?></td>
            <td data-label="Status"><span class="badge <?= $isYes ? 'badge-yes' : 'badge-no' ?>"><?= $isYes ? 'Attending' : 'Declined' ?></span></td>
            <td data-label="Guests"><?= $isYes ? h($r['guests'] ?? '1') : '—' ?></td>
            <td data-label="Dietary" class="wrap-text"><?= ($r['dietary'] ?? '') !== '' ? nl2br(h($r['dietary'])) : '—' ?></td>
            <td data-label="Message" class="wrap-text"><?= ($r['message'] ?? '') !== '' ? nl2br(h($r['message'])) : '—' ?></td>
            <td class="act"><form method="post" action="index.php" data-confirm="Delete the RSVP from <?= h($r['name']) ?>? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><button class="btn-link danger" type="submit">Delete</button></form></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <p class="empty" id="none" hidden>No RSVPs match your search.</p>
    <?php } ?>
  </section>

  <section class="tools" aria-label="Tools">
    <details class="panel">
      <summary>Add an RSVP by hand</summary>
      <p class="hint">For guests who confirmed by phone or WhatsApp.</p>
      <form method="post" action="index.php" class="grid-form">
        <?= csrf_field() ?><input type="hidden" name="do" value="add">
        <div><label for="a-name">Full name</label><input id="a-name" name="name" required maxlength="100"></div>
        <div><label for="a-phone">Phone</label><input id="a-phone" name="phone" type="tel" maxlength="40"></div>
        <div><label for="a-email">Email</label><input id="a-email" name="email" type="email" maxlength="120"></div>
        <div><label for="a-att">Attending?</label><select id="a-att" name="attending"><option value="Yes">Attending</option><option value="No">Declined</option></select></div>
        <div><label for="a-guests">Guests</label><select id="a-guests" name="guests"><option>1</option><option>2</option><option>3</option><option>4</option><option>5+</option></select></div>
        <div class="span2"><label for="a-diet">Dietary requirements</label><textarea id="a-diet" name="dietary" rows="2" maxlength="500"></textarea></div>
        <div class="span2"><label for="a-msg">Message</label><textarea id="a-msg" name="message" rows="2" maxlength="1500"></textarea></div>
        <div class="span2"><button class="btn btn-primary" type="submit">Add RSVP</button></div>
      </form>
    </details>

    <details class="panel">
      <summary>Import old RSVPs from Formspree</summary>
      <p class="hint">In Formspree, open the form, go to Submissions and export as CSV (or JSON), then upload that file here. Entries already in this list are skipped, so it is safe to import the same file twice.</p>
      <form method="post" action="index.php" enctype="multipart/form-data" class="stack-form">
        <?= csrf_field() ?><input type="hidden" name="do" value="import">
        <label for="i-file">CSV or JSON file</label>
        <input id="i-file" type="file" name="file" accept=".csv,.json,text/csv,application/json">
        <label for="i-paste">&hellip;or paste the contents</label>
        <textarea id="i-paste" name="paste" rows="4" placeholder="Name,Phone,Email,Attending,…"></textarea>
        <button class="btn btn-primary" type="submit">Import</button>
      </form>
    </details>

    <details class="panel">
      <summary>Change password</summary>
      <form method="post" action="index.php" class="stack-form" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="do" value="password">
        <label for="p-cur">Current password</label><input id="p-cur" name="current" type="password" autocomplete="current-password" required>
        <label for="p-new">New password (at least <?= MIN_PASSWORD ?> characters)</label><input id="p-new" name="new" type="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required>
        <label for="p-again">Repeat new password</label><input id="p-again" name="again" type="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required>
        <button class="btn btn-primary" type="submit">Change password</button>
      </form>
    </details>
  </section>
</main>
<?php
page_foot(true);

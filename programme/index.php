<?php
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'none'; base-uri 'none'; frame-ancestors 'none'");

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>404 Not Found</title></head>'
        . '<body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
    exit;
}

// Anyone without the secret link gets a plain 404, exactly as if the page did not exist.
$given = (string) ($_GET['k'] ?? '');
if (rl_count('prog', 3600) >= 30) {
    not_found();
}
$prog = programme_load();
if (!preg_match('/^[a-f0-9]{20}$/', $given) || !hash_equals($prog['token'], $given)) {
    rl_hit('prog', 3600);
    not_found();
}

header('Content-Type: text/html; charset=utf-8');
$css = @filemtime(__DIR__ . '/programme.css') ?: 1;

$items = [];
foreach ($prog['items'] as $it) {
    if (is_array($it) && trim((string) ($it['title'] ?? '')) !== '') {
        $items[] = $it;
    }
}
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=Courtyard%20Hotel%20Waterfall%20City%2C%2013%20Karkloof%20Cres%2C%20Midrand%2C%202066';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<title>Programme — Dr Lerato Ngobeni</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 64 64%27%3E%3Ccircle cx=%2732%27 cy=%2732%27 r=%2730%27 fill=%27%23fbf6f3%27 stroke=%27%23c69a3e%27 stroke-width=%273%27/%3E%3Ctext x=%2732%27 y=%2740%27 text-anchor=%27middle%27 font-family=%27Georgia,serif%27 font-size=%2724%27 fill=%27%23c69a3e%27%3ELN%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Italiana&family=Poppins:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="programme.css?v=<?= $css ?>">
</head>
<body>
<main class="sheet">
  <header class="head">
    <span class="brand-mark" aria-hidden="true">LN</span>
    <p class="eyebrow">Graduation Party</p>
    <h1>Programme</h1>
    <p class="who">Dr Lerato Ngobeni</p>
  </header>

  <section class="event" aria-label="Event details">
    <div><span class="k">Date</span><span class="v">Saturday, 28 November 2026</span></div>
    <div><span class="k">Time</span><span class="v">6:30 PM &ndash; 8:30 PM</span></div>
    <div><span class="k">Venue</span><span class="v">Courtyard Hotel Waterfall City<small>13 Karkloof Cres, Midrand, 2066</small></span></div>
    <div><span class="k">Dress code</span><span class="v">Elegant / Smart &mdash; Shades of Red</span></div>
    <a class="dir" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">Get directions</a>
  </section>

  <?php if (trim($prog['intro']) !== '') { ?>
    <p class="intro"><?= nl2br(h($prog['intro'])) ?></p>
  <?php } ?>

  <section class="order" aria-label="Order of the evening">
    <h2>Order of the evening</h2>
    <?php if (!$items) { ?>
      <p class="soon">The programme will be shared here soon.</p>
    <?php } else { ?>
      <ol class="timeline">
        <?php foreach ($items as $it) { ?>
          <li>
            <?php if (trim((string) ($it['time'] ?? '')) !== '') { ?><span class="t"><?= h($it['time']) ?></span><?php } else { ?><span class="t t-empty" aria-hidden="true"></span><?php } ?>
            <div class="c">
              <h3><?= h($it['title']) ?></h3>
              <?php if (trim((string) ($it['details'] ?? '')) !== '') { ?><p><?= nl2br(h($it['details'])) ?></p><?php } ?>
            </div>
          </li>
        <?php } ?>
      </ol>
    <?php } ?>
  </section>

  <footer class="foot">Dr Lerato Ngobeni &middot; 28 November 2026 &middot; Courtyard Hotel Waterfall City</footer>
</main>
</body>
</html>

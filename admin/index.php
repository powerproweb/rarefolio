<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';

$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW']   ?? '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (($u === '' || $p === '') && $auth !== '') {
    if (stripos($auth, 'basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
        }
    }
}
if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Rarefolio Admin Home"');
    http_response_code(401);
    exit('Unauthorized');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Home, Rarefolio</title>
<style>
:root {
  --bg: #050a18;
  --surface: #0d1526;
  --surface2: #121e35;
  --border: #1e2d4a;
  --gold: #d9b46c;
  --text: #c8d4e8;
  --muted: #6a7a96;
  --cyan: #5dd5ff;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 14px;
  min-height: 100vh;
  padding: 22px 24px;
}
h1 { color: var(--gold); font-size: 24px; font-weight: 700; letter-spacing: -.01em; margin-bottom: 8px; }
h2 { color: var(--gold); font-size: 17px; margin-bottom: 8px; }
p { line-height: 1.55; }
a { color: var(--cyan); text-decoration: none; }
a:hover { text-decoration: underline; }

.hero { margin-bottom: 16px; }
.muted { color: var(--muted); }

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 12px;
}
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
}
.card p { margin-bottom: 10px; }

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  padding: 8px 13px;
  font-weight: 700;
  font-size: 13px;
  text-decoration: none;
  border: 1px solid var(--border);
  color: var(--text);
  background: var(--surface2);
}
.btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
.btn.primary {
  background: var(--gold);
  border-color: var(--gold);
  color: #050a18;
}
.btn.primary:hover {
  color: #050a18;
  opacity: .88;
}
.row { display: flex; gap: 8px; flex-wrap: wrap; }

.info {
  margin-top: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 13px 16px;
}

@media (max-width: 640px) {
  body { padding: 16px 14px; }
}
</style>
</head>
<body>

<section class="hero">
  <h1>⬡ Rarefolio Admin Home</h1>
  <p class="muted">Central launcher for your local main-site admin tools.</p>
</section>

<section class="grid">
  <article class="card">
    <h2>Story Editor</h2>
    <p class="muted">Edit, save, and register collection stories and blocks.</p>
    <div class="row">
      <a class="btn primary" href="/admin/story-editor.php">Open Story Editor</a>
    </div>
  </article>

  <article class="card">
    <h2>Wallet Operations</h2>
    <p class="muted">Wallet dashboard, ownership verification tools, and market admin bridge links.</p>
    <div class="row">
      <a class="btn primary" href="/admin/wallet-dashboard.php">Open Wallet Operations</a>
    </div>
  </article>
</section>

<section class="info">
  <p class="muted">Need marketplace-side controls? Open <a href="https://market.rarefolio.io/admin/index.php" target="_blank" rel="noopener">Market Admin</a>.</p>
</section>

</body>
</html>

<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BOOK_AUTHOR_STUDIO');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

const STUDIO_AUTH_USER = 'studio';
const STUDIO_AUTH_HASH = '$2y$12$wjd8yxU8vUdujGrEqpB/n.6wBZWFsWd8ACjsigRKap/1XN.8tfYhu';

function studio_is_authenticated(): bool {
    return !empty($_SESSION['studio_authenticated']) && $_SESSION['studio_authenticated'] === true;
}

function studio_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

function studio_gate_api(): void {
    if (studio_is_authenticated()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('WWW-Authenticate: Session realm="Book Author Studio"');
    echo json_encode(['error' => 'authentication_required'], JSON_UNESCAPED_SLASHES);
    exit;
}

function studio_gate_web(): void {
    $error = false;

    if (isset($_GET['logout'])) {
        studio_logout();
        header('Location: /');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_login'])) {
        $user = (string)($_POST['username'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        if (hash_equals(STUDIO_AUTH_USER, $user) && password_verify($pass, STUDIO_AUTH_HASH)) {
            session_regenerate_id(true);
            $_SESSION['studio_authenticated'] = true;
            $_SESSION['studio_user'] = STUDIO_AUTH_USER;
            header('Location: /');
            exit;
        }
        usleep(250000);
        $error = true;
    }

    if (studio_is_authenticated()) {
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Book Author Studio — Sign in</title>
<style>
:root{--bg:#efede8;--paper:#fffdf8;--ink:#1e201f;--muted:#74736e;--line:#ddd8ce;--accent:#304d43}
*{box-sizing:border-box}html,body{height:100%;margin:0}body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 75% 0%,rgba(48,77,67,.12),transparent 34%),var(--bg);color:var(--ink);display:grid;place-items:center;padding:24px}
.card{width:min(420px,100%);background:var(--paper);border:1px solid var(--line);border-radius:24px;padding:30px;box-shadow:0 24px 70px rgba(39,34,28,.12)}
.mark{width:38px;height:38px;border-radius:12px;background:var(--ink);color:#fff;display:grid;place-items:center;font-family:Georgia,serif;font-weight:700;margin-bottom:24px}
.kicker{text-transform:uppercase;letter-spacing:.14em;font-size:10px;font-weight:800;color:var(--muted)}
h1{font-family:Georgia,"Times New Roman",serif;font-size:31px;font-weight:500;margin:6px 0 8px}
p{font-size:13px;color:var(--muted);line-height:1.55;margin:0 0 20px}
label{display:block;font-size:11px;font-weight:700;color:var(--muted);margin:12px 0 6px}
input{width:100%;border:1px solid var(--line);background:#fff;border-radius:12px;padding:11px 12px;font:inherit;outline:none}
input:focus{border-color:#8d887e;box-shadow:0 0 0 3px rgba(48,77,67,.08)}
button{width:100%;border:0;border-radius:12px;background:var(--ink);color:white;padding:12px 14px;font:inherit;font-weight:750;margin-top:18px;cursor:pointer}
.error{background:#f3e5e2;color:#7d4843;border-radius:10px;padding:9px 10px;font-size:11px;margin-bottom:10px}
.foot{font-size:10px;color:#96928a;text-align:center;margin-top:18px}
</style>
</head>
<body>
<main class="card">
  <div class="mark">A</div>
  <div class="kicker">Private workspace</div>
  <h1>Book Author Studio</h1>
  <p>Sign in to access manuscripts, story intelligence, research and translation workspaces.</p>
  <?php if ($error): ?><div class="error">The username or password is incorrect.</div><?php endif; ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="studio_login" value="1">
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Enter Studio</button>
  </form>
  <div class="foot">Protected · no public indexing</div>
</main>
</body>
</html>
    <?php
    exit;
}

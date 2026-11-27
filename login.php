<?php
define('GROWTH_SESSION_WRITE', true);
require __DIR__ . '/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php'); exit;
    }
    $error = 'Неверный логин или пароль.';
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#f6f7f4"><title>Вход — Дневник роста</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f7f4;color:#1f2a22;margin:0}.box{max-width:420px;margin:70px auto;background:white;padding:24px;border:1px solid #dde4de;border-radius:18px}
input{width:100%;padding:12px;margin:7px 0 16px;box-sizing:border-box;border:1px solid #ccd6ce;border-radius:10px;font-size:16px}
button{width:100%;padding:13px;background:#2f6b4f;color:white;border:0;border-radius:10px;font-weight:700}.err{color:#9b2c2c;margin-bottom:12px}
</style></head><body><div class="box"><h1>Дневник роста</h1>
<?php if(isset($_GET['created'])): ?><p>Пользователь создан. Войдите.</p><?php endif; ?>
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post"><label>Логин</label><input name="username" autocomplete="username" required>
<label>Пароль</label><input type="password" name="password" autocomplete="current-password" required>
<button type="submit">Войти</button></form></div></body></html>

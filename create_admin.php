<?php
// Run once in your browser, then DELETE this file from the server.
require __DIR__ . '/config.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    exit('An admin already exists. Delete this file.');
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || strlen($p) < 8) {
        $msg = 'Username required and password must be at least 8 characters.';
    } else {
        $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        exit('Admin created. Now DELETE create_admin.php from the server.');
    }
}
?>
<!DOCTYPE html>
<meta charset="utf-8">
<title>Create admin</title>
<body style="font-family:sans-serif;max-width:360px;margin:40px auto">
<h2>Create barangay admin</h2>
<p style="color:#c62828"><?= htmlspecialchars($msg) ?></p>
<form method="post">
  <p><input name="username" placeholder="Username" style="width:100%;padding:8px"></p>
  <p><input name="password" type="password" placeholder="Password (min 8)" style="width:100%;padding:8px"></p>
  <button style="padding:8px 16px">Create</button>
</form>
</body>

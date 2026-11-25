<?php
require __DIR__ . '/config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
function need_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'POST only'], 405);
}
function need_admin() {
    if (empty($_SESSION['uid'])) out(['error' => 'Login required'], 401);
}
function text($v, $max) {
    $v = trim((string)($v ?? ''));
    return mb_substr($v, 0, $max);
}

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    out(['error' => 'Database unavailable'], 500);
}

$action = $_GET['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

$TYPES = ['Missed collection', 'Illegal dumping', 'Clogged drainage', 'Overflowing bin', 'Other'];
$STATUSES = ['Received', 'In progress', 'Resolved'];

try {
    switch ($action) {

        case 'reports': // public: list latest reports
            $rows = $pdo->query('SELECT id, type, location, description, status, created_at FROM reports ORDER BY created_at DESC LIMIT 100')->fetchAll();
            out($rows);

        case 'report': // public: submit a report
            need_post();
            $type = $in['type'] ?? '';
            $loc = text($in['location'] ?? '', 120);
            $desc = text($in['description'] ?? '', 1000);
            if (!in_array($type, $TYPES, true) || $loc === '' || $desc === '') out(['error' => 'Please complete all fields'], 400);
            $pdo->prepare('INSERT INTO reports (type, location, description) VALUES (?,?,?)')->execute([$type, $loc, $desc]);
            out(['ok' => true], 201);

        case 'status': // admin: update report status
            need_post();
            need_admin();
            $id = (int)($in['id'] ?? 0);
            $status = $in['status'] ?? '';
            if ($id < 1 || !in_array($status, $STATUSES, true)) out(['error' => 'Invalid input'], 400);
            $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $id]);
            out(['ok' => true]);

        case 'survey': // public: submit survey
            need_post();
            $role = in_array($in['role'] ?? '', ['Resident', 'Barangay official'], true) ? $in['role'] : '';
            $age = in_array($in['age_group'] ?? '', ['Under 18', '18-30', '31-45', '46-60', '61+'], true) ? $in['age_group'] : '';
            $a = (int)($in['awareness'] ?? 0);
            $r = (int)($in['readiness'] ?? 0);
            $c = (int)($in['acceptance'] ?? 0);
            foreach ([$a, $r, $c] as $n) if ($n < 1 || $n > 5) out(['error' => 'Ratings must be 1 to 5'], 400);
            if ($role === '' || $age === '') out(['error' => 'Please complete all fields'], 400);
            $pdo->prepare('INSERT INTO survey_responses (role, age_group, awareness, readiness, acceptance, comments) VALUES (?,?,?,?,?,?)')
                ->execute([$role, $age, $a, $r, $c, text($in['comments'] ?? '', 500)]);
            out(['ok' => true], 201);

        case 'survey_stats': // admin: summary for the research
            need_admin();
            $row = $pdo->query('SELECT COUNT(*) AS n, ROUND(AVG(awareness),2) AS awareness, ROUND(AVG(readiness),2) AS readiness, ROUND(AVG(acceptance),2) AS acceptance FROM survey_responses')->fetch();
            out($row);

        case 'login':
            need_post();
            $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
            $st->execute([text($in['username'] ?? '', 50)]);
            $u = $st->fetch();
            if (!$u || !password_verify((string)($in['password'] ?? ''), $u['password_hash'])) {
                usleep(400000); // slow down guessing
                out(['error' => 'Wrong username or password'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            out(['ok' => true]);

        case 'logout':
            need_post();
            session_destroy();
            out(['ok' => true]);

        case 'me':
            out(['admin' => !empty($_SESSION['uid'])]);

        default:
            out(['error' => 'Unknown action'], 404);
    }
} catch (Throwable $e) {
    out(['error' => 'Server error'], 500);
}

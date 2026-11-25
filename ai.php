<?php
require __DIR__ . '/config.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'POST only'], 405);
if (ANTHROPIC_API_KEY === '' || ANTHROPIC_API_KEY === 'PASTE_YOUR_KEY_HERE') out(['error' => 'AI not configured'], 503);

// Simple per-visitor rate limit
$now = time();
$_SESSION['ai'] = array_values(array_filter($_SESSION['ai'] ?? [], function ($t) use ($now) { return $t > $now - 3600; }));
if (count($_SESSION['ai']) >= AI_LIMIT_PER_HOUR) out(['error' => 'You have reached the hourly chat limit. Please try again later.'], 429);

// Clean the incoming conversation (last 6 messages, 300 chars each)
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$msgs = [];
foreach (array_slice($in['messages'] ?? [], -6) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = trim(mb_substr((string)($m['content'] ?? ''), 0, 300));
    if ($text !== '') $msgs[] = ['role' => $role, 'content' => $text];
}
while ($msgs && $msgs[0]['role'] !== 'user') array_shift($msgs);
if (!$msgs || end($msgs)['role'] !== 'user') out(['error' => 'Invalid message'], 400);

$system = <<<TXT
You are EcoBot, the friendly waste-management assistant of a Philippine barangay website.
Reply in the same language the resident uses (English, Filipino/Tagalog, or Taglish). Keep answers short: 2 to 4 sentences, plain words, no markdown headings.

You help with:
- Waste segregation. The four bins are: Biodegradable (green: food scraps, leaves, garden waste), Recyclable (blue: clean plastic, paper, cardboard, metal, glass), Residual (black: sachets, wrappers, diapers, styrofoam, tissue, cigarette butts), Special/Hazardous (never in regular trash: batteries, bulbs, expired medicine, paint, e-waste, syringes).
- Collection schedule (sample): Mon Biodegradable, Tue Recyclable, Wed Biodegradable, Thu Residual, Fri Recyclable, Sat hazardous drop-off at 8:00 AM, Sun no collection. Regular collection is at 6:00 AM.
- Recycling and reduction tips (composting, refilling, reusing, ecobags).
- How to use this website: the Segregation Assistant checks an item, the Community Reporting form reports missed collections, illegal dumping, or clogged drains, and the Community Survey is for the barangay's research.

Rules:
- If you are unsure about a local rule or the exact schedule, say so and suggest asking the barangay hall or environment officer.
- Do not invent phone numbers, fines, or ordinances.
- If asked about something unrelated to waste, the environment, or this website, politely say you can only help with those topics.
TXT;

$payload = json_encode([
    'model' => AI_MODEL,
    'max_tokens' => 400,
    'system' => $system,
    'messages' => $msgs,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => CURL_VERIFY_SSL,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($res === false) out(['error' => 'Connection problem: ' . $err], 502);
$data = json_decode($res, true);

// Find the first text block in the reply
$reply = '';
foreach (($data['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text' && !empty($block['text'])) { $reply = $block['text']; break; }
}

if ($code !== 200 || $reply === '') {
    $detail = $data['error']['message'] ?? ('HTTP ' . $code . ': ' . mb_substr((string)$res, 0, 300));
    out(['error' => $detail], 502);
}

$_SESSION['ai'][] = $now;
out(['reply' => $reply]);

<?php
$api = rtrim($argv[1] ?? 'https://hotelsystemback.osus.network/api', '/');
$login = json_decode(file_get_contents($api . '/users/login', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => '{"job_number":"001","password":"admin123"}',
    ],
])), true);
$token = $login['data']['token'] ?? '';
$start = $argv[2] ?? '2026-08-01';
$end = $argv[3] ?? '2026-08-31';
$url = "$api/users/reports/balance-sheet?start_date=$start&end_date=$end";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Accept: application/json'],
    CURLOPT_TIMEOUT => 60,
]);
$raw = curl_exec($ch);
curl_close($ch);
$bs = json_decode($raw, true);
$data = $bs['data'] ?? [];
echo 'keys: ' . implode(', ', array_keys($data)) . PHP_EOL;
$balanced = $data['balanced'] ?? $data['is_balanced'] ?? $data['totals']['balanced'] ?? null;
echo 'success: ' . json_encode($bs['success'] ?? false) . PHP_EOL;
echo 'balanced: ' . json_encode($balanced) . PHP_EOL;
if (isset($data['summary'])) {
    echo 'summary: ' . json_encode($data['summary'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
if (isset($data['meta_lines'])) {
    echo 'meta_lines: ' . json_encode($data['meta_lines'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

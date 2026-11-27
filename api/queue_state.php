<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$queueId = filter_input(INPUT_GET, 'queue_id', FILTER_VALIDATE_INT);
$queue = $queueId ? get_queue((int)$queueId) : null;
if (!$queue) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Queue not found']);
    exit;
}
$current = current_token((int)$queue['id']);
$metrics = queue_metrics((int)$queue['id']);
$stmt = db()->prepare("SELECT token_number FROM queue_tokens WHERE queue_id = :queue_id AND status = 'waiting' ORDER BY token_number ASC LIMIT 3");
$stmt->execute(['queue_id' => $queue['id']]);
$next = array_map(fn(array $row): string => format_token($queue, (int)$row['token_number']), $stmt->fetchAll());
echo json_encode([
    'ok' => true,
    'queue_name' => $queue['name'],
    'current_token' => $current ? format_token($queue, (int)$current['token_number']) : null,
    'waiting_count' => $metrics['waiting'],
    'next_tokens' => $next,
    'updated_at' => date('H:i:s'),
], JSON_UNESCAPED_SLASHES);

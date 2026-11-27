<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ref = $_GET['ref'] ?? '';
$token = is_string($ref) && preg_match('/^[a-f0-9]{32}$/', $ref) ? token_by_ref($ref) : null;
if (!$token) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Token not found']);
    exit;
}
$queue = get_queue((int)$token['queue_id']);
$position = 0;
$peopleAhead = 0;
if ($token['status'] === 'waiting') {
    $stmt = db()->prepare("SELECT COUNT(*) FROM queue_tokens WHERE queue_id = :queue_id AND status = 'waiting' AND token_number <= :number");
    $stmt->execute(['queue_id' => $token['queue_id'], 'number' => $token['token_number']]);
    $position = (int)$stmt->fetchColumn();
    $peopleAhead = max(0, $position - 1);
}
$current = current_token((int)$token['queue_id']);
$metrics = queue_metrics((int)$token['queue_id']);
echo json_encode([
    'ok' => true,
    'status' => $token['status'],
    'position' => $position,
    'people_ahead' => $peopleAhead,
    'waiting_count' => $metrics['waiting'],
    'current_token' => $current ? format_token($queue, (int)$current['token_number']) : null,
    'called_at' => $token['called_at'],
], JSON_UNESCAPED_SLASHES);

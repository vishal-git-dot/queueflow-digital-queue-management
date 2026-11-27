<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
$queueId = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);
if (!$queueId || $queueId < 1) {
    flash('error', 'Please choose a valid service counter.');
    redirect('index.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $queueStmt = $pdo->prepare('SELECT * FROM queues WHERE id = :id AND is_active = 1 FOR UPDATE');
    $queueStmt->execute(['id' => $queueId]);
    $queue = $queueStmt->fetch();
    if (!$queue) {
        $pdo->rollBack();
        flash('error', 'That service counter is currently unavailable.');
        redirect('index.php');
    }

    $nextNumber = (int)$queue['last_token_number'] + 1;
    $update = $pdo->prepare('UPDATE queues SET last_token_number = :number WHERE id = :id');
    $update->execute(['number' => $nextNumber, 'id' => $queueId]);

    $publicCode = bin2hex(random_bytes(16));
    $insert = $pdo->prepare("INSERT INTO queue_tokens (queue_id, token_number, public_code, status)
        VALUES (:queue_id, :token_number, :public_code, 'waiting')");
    $insert->execute(['queue_id' => $queueId, 'token_number' => $nextNumber, 'public_code' => $publicCode]);
    $pdo->commit();
    redirect('queue.php?ref=' . urlencode($publicCode));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('QueueFlow token creation error: ' . $exception->getMessage());
    flash('error', 'We could not create your token. Please try again.');
    redirect('index.php');
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session has expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $messages;
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'Please sign in to access the admin workspace.');
        redirect('admin.php');
    }
}

function get_queue(int $queueId): ?array
{
    $stmt = db()->prepare('SELECT * FROM queues WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $queueId]);
    $queue = $stmt->fetch();
    return $queue ?: null;
}

function get_active_queues(): array
{
    return db()->query('SELECT * FROM queues WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
}

function queue_metrics(int $queueId): array
{
    $stmt = db()->prepare("SELECT
        SUM(status = 'waiting') AS waiting_count,
        SUM(status = 'called') AS called_count,
        SUM(status = 'served') AS served_count
        FROM queue_tokens WHERE queue_id = :queue_id");
    $stmt->execute(['queue_id' => $queueId]);
    $row = $stmt->fetch() ?: [];
    return [
        'waiting' => (int)($row['waiting_count'] ?? 0),
        'called' => (int)($row['called_count'] ?? 0),
        'served' => (int)($row['served_count'] ?? 0),
    ];
}

function current_token(int $queueId): ?array
{
    $stmt = db()->prepare("SELECT * FROM queue_tokens
        WHERE queue_id = :queue_id AND status = 'called'
        ORDER BY called_at DESC, id DESC LIMIT 1");
    $stmt->execute(['queue_id' => $queueId]);
    $token = $stmt->fetch();
    return $token ?: null;
}

function format_token(array $queue, int $number): string
{
    return $queue['token_prefix'] . str_pad((string)$number, 3, '0', STR_PAD_LEFT);
}

function token_by_ref(string $publicCode): ?array
{
    $stmt = db()->prepare('SELECT t.*, q.name AS queue_name, q.token_prefix, q.id AS queue_id
        FROM queue_tokens t INNER JOIN queues q ON q.id = t.queue_id
        WHERE t.public_code = :public_code LIMIT 1');
    $stmt->execute(['public_code' => $publicCode]);
    $token = $stmt->fetch();
    return $token ?: null;
}

/**
 * Move the active token to served/skipped and call the next waiting token.
 * The queue row is locked first so concurrent counter clicks cannot call the
 * same token twice.
 */
function advance_queue(int $queueId, bool $skipCurrent = false): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lockQueue = $pdo->prepare('SELECT id FROM queues WHERE id = :id FOR UPDATE');
        $lockQueue->execute(['id' => $queueId]);
        if (!$lockQueue->fetch()) {
            $pdo->rollBack();
            return null;
        }

        $activeStmt = $pdo->prepare("SELECT id FROM queue_tokens
            WHERE queue_id = :queue_id AND status = 'called'
            ORDER BY called_at DESC, id DESC LIMIT 1 FOR UPDATE");
        $activeStmt->execute(['queue_id' => $queueId]);
        $active = $activeStmt->fetch();

        if ($active) {
            $newStatus = $skipCurrent ? 'skipped' : 'served';
            $updateActive = $pdo->prepare("UPDATE queue_tokens SET status = :status, served_at = NOW() WHERE id = :id");
            $updateActive->execute(['status' => $newStatus, 'id' => $active['id']]);
        }

        $nextStmt = $pdo->prepare("SELECT id FROM queue_tokens
            WHERE queue_id = :queue_id AND status = 'waiting'
            ORDER BY token_number ASC LIMIT 1 FOR UPDATE");
        $nextStmt->execute(['queue_id' => $queueId]);
        $next = $nextStmt->fetch();

        if ($next) {
            $callNext = $pdo->prepare("UPDATE queue_tokens SET status = 'called', called_at = NOW() WHERE id = :id");
            $callNext->execute(['id' => $next['id']]);
        }

        $pdo->commit();
        if (!$next) {
            return null;
        }
        $result = $pdo->prepare('SELECT * FROM queue_tokens WHERE id = :id');
        $result->execute(['id' => $next['id']]);
        return $result->fetch() ?: null;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

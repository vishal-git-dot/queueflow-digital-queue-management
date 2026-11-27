<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$queueId = filter_input(INPUT_GET, 'queue_id', FILTER_VALIDATE_INT);
if (!$queueId) {
    $first = db()->query('SELECT id FROM queues WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetch();
    $queueId = $first ? (int)$first['id'] : 0;
}
$queue = $queueId ? get_queue($queueId) : null;
if (!$queue) {
    $pageTitle = 'Live queue display';
    $activePage = 'display';
    require __DIR__ . '/includes/header.php';
    echo '<section class="page-shell empty-page"><div class="empty-state"><span class="empty-icon">◷</span><h2>No queue to display</h2><p>Create or activate a service counter in the admin workspace.</p></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$metrics = queue_metrics((int)$queue['id']);
$current = current_token((int)$queue['id']);
$nextStmt = db()->prepare("SELECT token_number FROM queue_tokens WHERE queue_id = :queue_id AND status = 'waiting' ORDER BY token_number ASC LIMIT 3");
$nextStmt->execute(['queue_id' => $queue['id']]);
$nextTokens = $nextStmt->fetchAll();
$pageTitle = 'Live queue display';
$activePage = 'display';
require __DIR__ . '/includes/header.php';
?>
<section class="display-page page-shell" data-live-queue="<?= (int)$queue['id'] ?>">
    <div class="display-heading reveal"><div><div class="eyebrow"><span class="eyebrow-line"></span> REAL-TIME SERVICE BOARD</div><h1><?= e($queue['name']) ?><br><em>queue display</em></h1></div><div class="display-live"><span class="live-pulse"></span><span>Live</span><small id="lastUpdated">Updating just now</small></div></div>
    <div class="display-board reveal reveal-delay-1">
        <div class="display-current"><div class="display-current-top"><span class="metric-label">NOW SERVING</span><span class="display-counter-tag">COUNTER 01</span></div><div class="display-number" id="displayCurrent"><?= $current ? e(format_token($queue, (int)$current['token_number'])) : '— — —' ?></div><p id="displayCurrentHint"><?= $current ? 'Please proceed to the service counter.' : 'The next token will appear here.' ?></p><div class="display-wave" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div>
        <div class="display-side"><div class="display-waiting"><span class="metric-label">PEOPLE WAITING</span><strong id="displayWaiting"><?= $metrics['waiting'] ?></strong><span>in this queue</span></div><div class="display-next"><span class="metric-label">UP NEXT</span><div class="next-token-list" id="nextTokenList"><?php if ($nextTokens): foreach ($nextTokens as $next): ?><div class="next-token"><span><?= e(format_token($queue, (int)$next['token_number'])) ?></span><small>Waiting</small></div><?php endforeach; else: ?><div class="next-empty">No one waiting right now</div><?php endif; ?></div></div></div>
    </div>
    <div class="display-footerline"><span><i></i> Please listen for your token number</span><span>QueueFlow · <?= date('d M Y') ?></span></div>
    <div class="display-switcher"><label for="displayQueueSelect">View another service</label><select id="displayQueueSelect"><?php foreach (db()->query('SELECT id, name FROM queues WHERE is_active = 1 ORDER BY name')->fetchAll() as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (int)$item['id'] === (int)$queue['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$ref = $_GET['ref'] ?? '';
$token = is_string($ref) && preg_match('/^[a-f0-9]{32}$/', $ref) ? token_by_ref($ref) : null;
if (!$token) {
    flash('error', 'We could not find that token. Please check your link.');
    redirect('index.php');
}
$queue = get_queue((int)$token['queue_id']);
$position = 0;
if ($token['status'] === 'waiting') {
    $stmt = db()->prepare("SELECT COUNT(*) FROM queue_tokens WHERE queue_id = :queue_id AND status = 'waiting' AND token_number <= :token_number");
    $stmt->execute(['queue_id' => $token['queue_id'], 'token_number' => $token['token_number']]);
    $position = (int)$stmt->fetchColumn();
}
$pageTitle = 'Your queue status';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
$formattedToken = format_token($queue, (int)$token['token_number']);
?>
<section class="status-page page-shell" data-token-ref="<?= e($ref) ?>">
    <div class="status-topline reveal"><a href="index.php" class="back-link">← Back to services</a><span class="live-label"><i></i> LIVE QUEUE UPDATES</span></div>
    <div class="status-layout">
        <div class="status-main reveal">
            <div class="eyebrow"><span class="eyebrow-line"></span> YOUR DIGITAL TOKEN</div>
            <h1>You're in<br><em>the queue.</em></h1>
            <p class="status-intro">We've saved your place. You can keep this page open and follow the queue without standing near the counter.</p>
            <div class="your-token-card">
                <div class="your-token-head"><span><?= e($token['queue_name']) ?></span><span class="token-status-pill" id="tokenStatusPill"><?= e(ucfirst($token['status'])) ?></span></div>
                <div class="your-token-number" id="yourTokenNumber"><?= e($formattedToken) ?></div>
                <div class="your-token-foot"><span>YOUR TOKEN NUMBER</span><span>Issued <?= date('h:i A', strtotime($token['created_at'])) ?></span></div>
            </div>
            <div class="status-actions"><a class="button button-primary" href="display.php?queue_id=<?= (int)$token['queue_id'] ?>">View live display <span class="button-arrow">↗</span></a><button class="button button-outline" type="button" id="copyToken" data-copy="<?= e($formattedToken) ?>">Copy token</button></div>
        </div>
        <aside class="status-aside reveal reveal-delay-1">
            <div class="position-card">
                <span class="metric-label">YOUR PLACE IN LINE</span>
                <strong class="position-number" id="positionNumber"><?= $token['status'] === 'waiting' ? '#' . max(1, $position) : ($token['status'] === 'called' ? 'Now' : '—') ?></strong>
                <span class="position-caption" id="positionCaption"><?= $token['status'] === 'waiting' ? 'position in the queue' : ($token['status'] === 'called' ? 'Your turn is here. Please go to the counter.' : 'Your queue visit is complete.') ?></span>
                <div class="position-divider"></div>
                <div class="mini-metric"><span>People waiting</span><strong id="waitingCount">—</strong></div>
                <div class="mini-metric"><span>Now serving</span><strong id="nowServing">—</strong></div>
            </div>
            <div class="help-note"><span class="help-icon">i</span><div><strong>A little tip</strong><p>Keep this page handy. Queue status refreshes automatically every few seconds.</p></div></div>
        </aside>
    </div>
</section>
<script>window.QueueFlowTokenRef = <?= json_encode($ref) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

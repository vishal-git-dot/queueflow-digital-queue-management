<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            flash('success', 'Welcome back. Your queue workspace is ready.');
            redirect('admin.php');
        }
        flash('error', 'The username or password was incorrect.');
        redirect('admin.php');
    }

    require_admin();
    if ($action === 'next' || $action === 'skip') {
        $queueId = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);
        if (!$queueId || !get_queue((int)$queueId)) {
            flash('error', 'Please select a valid queue.');
        } else {
            $called = advance_queue((int)$queueId, $action === 'skip');
            if ($called) {
                $queue = get_queue((int)$queueId);
                flash('success', ($action === 'skip' ? 'Skipped current token. ' : 'Current token served. ') . 'Now calling ' . format_token($queue, (int)$called['token_number']) . '.');
            } else {
                flash('success', $action === 'skip' ? 'Current token cleared. There are no more people waiting.' : 'The queue is clear. There are no waiting tokens.');
            }
        }
        redirect('admin.php?queue_id=' . (int)($queueId ?: 0));
    }

    if ($action === 'create_queue') {
        $name = trim((string)($_POST['name'] ?? ''));
        $prefix = strtoupper(trim((string)($_POST['token_prefix'] ?? '')));
        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 120 || !preg_match('/^[A-Z0-9]{1,5}$/', $prefix)) {
            flash('error', 'Enter a queue name and a 1–5 character prefix (letters or numbers).');
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO queues (name, token_prefix, is_active) VALUES (:name, :prefix, 1)');
                $stmt->execute(['name' => $name, 'prefix' => $prefix]);
                flash('success', 'Your new service counter has been created.');
                redirect('admin.php?queue_id=' . (int)db()->lastInsertId());
            } catch (PDOException $exception) {
                flash('error', 'That token prefix is already in use. Choose a different one.');
            }
        }
        redirect('admin.php');
    }

    if ($action === 'toggle_queue') {
        $queueId = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);
        if ($queueId) {
            $stmt = db()->prepare('UPDATE queues SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $stmt->execute(['id' => $queueId]);
            flash('success', 'Service counter availability updated.');
        }
        redirect('admin.php?queue_id=' . (int)($queueId ?: 0));
    }
}

$pageTitle = 'Admin workspace';
$activePage = 'admin';
require __DIR__ . '/includes/header.php';

$adminCount = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if (!is_admin()):
?>
<section class="auth-shell page-shell">
    <div class="auth-aside reveal"><div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> STAFF WORKSPACE</div><h1>Keep things<br><em>moving.</em></h1><p>One clear view of every counter, every token, and every person waiting.</p><div class="auth-stats"><span>01</span><div><b>Stay in control</b><small>Call the next person with one click.</small></div></div><div class="auth-stats"><span>02</span><div><b>Keep it fair</b><small>Tokens are called in the order they arrive.</small></div></div></div>
    <div class="auth-card reveal reveal-delay-1">
        <span class="auth-card-kicker">WELCOME BACK</span><h2>Staff sign in</h2><p>Enter your staff credentials to manage the queue.</p>
        <form method="post" action="admin.php" class="form-stack" data-validate>
            <?= csrf_field() ?><input type="hidden" name="action" value="login">
            <label class="field-label" for="username">Username</label><div class="input-wrap"><span class="input-prefix">@</span><input id="username" name="username" type="text" autocomplete="username" placeholder="Your username" required maxlength="80"></div>
            <label class="field-label" for="password">Password</label><div class="input-wrap password-wrap"><span class="input-prefix">•</span><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Your password" required><button type="button" class="password-toggle" aria-label="Show password">Show</button></div>
            <button class="button button-primary button-block form-submit" type="submit">Sign in to workspace <span class="button-arrow">↗</span></button>
        </form>
        <div class="auth-bottom"><?php if ($adminCount === 0): ?>First time here? <a href="setup_admin.php">Create the admin account</a><?php else: ?>Need access? Contact the system administrator.<?php endif; ?></div>
    </div>
</section>
<?php else:
    $allQueues = db()->query('SELECT * FROM queues ORDER BY is_active DESC, name ASC')->fetchAll();
    $selectedId = filter_input(INPUT_GET, 'queue_id', FILTER_VALIDATE_INT);
    $selected = null;
    foreach ($allQueues as $q) { if ((int)$q['id'] === (int)$selectedId) { $selected = $q; break; } }
    if (!$selected && $allQueues) { $selected = $allQueues[0]; }
?>
<section class="admin-page page-shell">
    <div class="admin-welcome reveal"><div><div class="eyebrow"><span class="eyebrow-line"></span> CONTROL CENTER</div><h1>Good day, <em><?= e($_SESSION['admin_username'] ?? 'Admin') ?>.</em></h1><p>Here's what's happening across your service counters.</p></div><a class="button button-outline" href="display.php<?= $selected ? '?queue_id=' . (int)$selected['id'] : '' ?>" target="_blank" rel="noopener">Open display <span class="button-arrow">↗</span></a></div>
    <div class="admin-layout">
        <aside class="admin-sidebar reveal">
            <div class="sidebar-heading"><span>YOUR COUNTERS</span><span class="sidebar-count"><?= count($allQueues) ?></span></div>
            <div class="counter-list">
                <?php foreach ($allQueues as $q): ?>
                <a href="admin.php?queue_id=<?= (int)$q['id'] ?>" class="counter-link <?= $selected && (int)$selected['id'] === (int)$q['id'] ? 'is-selected' : '' ?>">
                    <span class="counter-mini-icon"><?= e(substr($q['token_prefix'], 0, 1)) ?></span><span class="counter-link-copy"><b><?= e($q['name']) ?></b><small><?= $q['is_active'] ? 'Accepting tokens' : 'Paused' ?></small></span><span class="counter-status-dot <?= $q['is_active'] ? '' : 'is-paused' ?>"></span>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="sidebar-add"><a href="#addCounter">＋ Add a service counter</a></div>
            <div class="sidebar-tip"><span>✳</span><p>Keep each counter moving by calling the next token as soon as a service is complete.</p></div>
        </aside>
        <?php if ($selected): $metrics = queue_metrics((int)$selected['id']); $current = current_token((int)$selected['id']);
            $historyStmt = db()->prepare('SELECT * FROM queue_tokens WHERE queue_id = :queue_id ORDER BY id DESC LIMIT 12');
            $historyStmt->execute(['queue_id' => $selected['id']]); $history = $historyStmt->fetchAll();
            $nextStmt = db()->prepare("SELECT * FROM queue_tokens WHERE queue_id = :queue_id AND status = 'waiting' ORDER BY token_number ASC LIMIT 1");
            $nextStmt->execute(['queue_id' => $selected['id']]); $next = $nextStmt->fetch(); ?>
        <div class="admin-content" data-admin-queue="<?= (int)$selected['id'] ?>">
            <div class="counter-title-row reveal"><div><span class="counter-active-label <?= $selected['is_active'] ? '' : 'is-off' ?>"><i></i><?= $selected['is_active'] ? 'COUNTER ACTIVE' : 'COUNTER PAUSED' ?></span><h2><?= e($selected['name']) ?></h2><p>Token prefix <b><?= e($selected['token_prefix']) ?></b> · Updated <?= date('h:i A') ?></p></div>
                <form method="post" action="admin.php" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_queue"><input type="hidden" name="queue_id" value="<?= (int)$selected['id'] ?>"><button class="button button-small <?= $selected['is_active'] ? 'button-outline' : 'button-primary' ?>" type="submit"><?= $selected['is_active'] ? 'Pause counter' : 'Activate counter' ?></button></form>
            </div>
            <div class="metric-grid reveal reveal-delay-1">
                <div class="metric-card metric-card-featured"><span class="metric-label">NOW SERVING</span><strong id="adminCurrentToken"><?= $current ? e(format_token($selected, (int)$current['token_number'])) : '—' ?></strong><small><?= $current ? 'Being attended to now' : 'No active token' ?></small><span class="metric-card-icon">↗</span></div>
                <div class="metric-card"><span class="metric-label">WAITING</span><strong id="adminWaitingCount"><?= $metrics['waiting'] ?></strong><small>People in line</small><span class="metric-card-icon">◷</span></div>
                <div class="metric-card"><span class="metric-label">SERVED</span><strong><?= $metrics['served'] ?></strong><small>Completed visits</small><span class="metric-card-icon">✓</span></div>
            </div>
            <div class="control-panel reveal reveal-delay-2">
                <div class="control-panel-copy"><span class="metric-label">QUEUE CONTROLS</span><h3>Ready for the next?</h3><p><?php if ($current): ?>Currently attending <b><?= e(format_token($selected, (int)$current['token_number'])) ?></b>. Complete the service to call the next person.<?php elseif ($next): ?>The next person waiting is <b><?= e(format_token($selected, (int)$next['token_number'])) ?></b>.<?php else: ?>No one is waiting. Your counter is all caught up.<?php endif; ?></p></div>
                <div class="control-buttons"><form method="post" action="admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="next"><input type="hidden" name="queue_id" value="<?= (int)$selected['id'] ?>"><button class="button button-primary next-button" type="submit" <?= (!$current && !$next) ? 'disabled' : '' ?>>Next token <span class="button-arrow">→</span></button></form><form method="post" action="admin.php" onsubmit="return confirm('Skip the currently called token?')"><?= csrf_field() ?><input type="hidden" name="action" value="skip"><input type="hidden" name="queue_id" value="<?= (int)$selected['id'] ?>"><button class="button button-quiet" type="submit" <?= !$current ? 'disabled' : '' ?>>Skip current</button></form></div>
            </div>
            <div class="history-panel reveal reveal-delay-3">
                <div class="panel-heading"><div><span class="metric-label">RECENT ACTIVITY</span><h3>Queue history</h3></div><span class="history-note">Latest 12 tokens</span></div>
                <?php if (!$history): ?><div class="empty-table"><span>◷</span><b>No queue activity yet</b><p>Tokens will appear here as visitors join the line.</p></div><?php else: ?>
                <div class="table-wrap"><table class="history-table"><thead><tr><th>TOKEN</th><th>STATUS</th><th>JOINED</th><th>CALLED</th></tr></thead><tbody>
                <?php foreach ($history as $item): ?><tr><td class="table-token"><?= e(format_token($selected, (int)$item['token_number'])) ?></td><td><span class="status-chip status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></td><td><?= date('d M, h:i A', strtotime($item['created_at'])) ?></td><td><?= $item['called_at'] ? date('h:i A', strtotime($item['called_at'])) : '—' ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </div>
        </div>
        <?php else: ?><div class="admin-content"><div class="empty-state"><h3>No service counters yet</h3><p>Create a counter below to start taking tokens.</p></div></div><?php endif; ?>
    </div>
    <div class="add-counter-panel reveal" id="addCounter"><div><span class="metric-label">GROW YOUR SERVICE</span><h3>Add a service counter</h3><p>Create a queue for another department or desk.</p></div><form method="post" action="admin.php" class="add-counter-form" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="create_queue"><label class="sr-only" for="newQueueName">Service counter name</label><input id="newQueueName" type="text" name="name" maxlength="120" placeholder="e.g. Student Support" required><label class="sr-only" for="newQueuePrefix">Token prefix</label><input id="newQueuePrefix" class="prefix-input" type="text" name="token_prefix" maxlength="5" pattern="[A-Za-z0-9]{1,5}" placeholder="SS" required><button class="button button-dark" type="submit">Create counter <span class="button-arrow">↗</span></button></form></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

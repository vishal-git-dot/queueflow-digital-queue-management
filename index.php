<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$queues = get_active_queues();
$pageTitle = 'Get your token';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-section page-shell">
    <div class="hero-copy reveal">
        <div class="eyebrow"><span class="eyebrow-line"></span> THE SMARTER WAY TO WAIT</div>
        <h1>Your time is<br><em>worth more</em><span class="hero-dot">.</span></h1>
        <p class="hero-description">Take a token, keep your place, and get on with your day. We'll let you know when it's your turn.</p>
        <div class="hero-proof"><span class="proof-icon">↗</span><span>No more standing in line.</span><span class="proof-divider"></span><span>Just a smoother visit.</span></div>
    </div>
    <div class="hero-art reveal reveal-delay-1" aria-label="Illustration of a digital queue ticket">
        <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
        <div class="art-spark spark-one">✳</div><div class="art-spark spark-two">✦</div>
        <div class="ticket-card">
            <div class="ticket-top"><span class="ticket-brand-dot"></span><span>YOUR PLACE IN LINE</span><span class="ticket-live"><i></i> LIVE</span></div>
            <div class="ticket-number">A<span>024</span></div>
            <div class="ticket-rule"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
            <div class="ticket-bottom"><div><small>ESTIMATED WAIT</small><strong>~ 10 min</strong></div><div class="ticket-qr" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div>
        </div>
        <div class="floating-note note-top"><span class="note-icon">✓</span><span><b>You're checked in</b><small>Your token is ready</small></span></div>
        <div class="floating-note note-bottom"><span class="pulse-ring"></span><span><b>Now serving A021</b><small>You're getting closer</small></span></div>
    </div>
</section>

<section class="queue-section page-shell" id="chooseQueue">
    <div class="section-heading reveal">
        <div><div class="eyebrow"><span class="eyebrow-line"></span> STEP 01 / CHOOSE A SERVICE</div><h2>Where can we<br><em>help you today?</em></h2></div>
        <p>Select the counter you need. Your token will be ready in seconds.</p>
    </div>
    <?php if (!$queues): ?>
        <div class="empty-state"><span class="empty-icon">○</span><h3>No counters are open right now</h3><p>Please check back a little later or contact the service desk.</p></div>
    <?php else: ?>
        <div class="queue-grid">
            <?php foreach ($queues as $index => $queue): $metrics = queue_metrics((int)$queue['id']); $now = current_token((int)$queue['id']); ?>
            <article class="queue-card reveal" style="--reveal-delay: <?= (int)$index * 70 ?>ms">
                <div class="queue-card-top"><span class="queue-icon queue-icon-<?= ($index % 3) + 1 ?>"><?= ['✳', '⌂', '＋'][$index % 3] ?></span><span class="availability"><i></i> Open now</span></div>
                <h3><?= e($queue['name']) ?></h3>
                <p class="queue-card-sub">Join the queue and follow your place in real time.</p>
                <div class="queue-card-stats"><div><strong><?= $metrics['waiting'] ?></strong><span>Waiting</span></div><div><strong><?= $now ? e(format_token($queue, (int)$now['token_number'])) : '—' ?></strong><span>Now serving</span></div></div>
                <form method="post" action="get_token.php" class="queue-action">
                    <?= csrf_field() ?>
                    <input type="hidden" name="queue_id" value="<?= (int)$queue['id'] ?>">
                    <button class="button button-primary button-block" type="submit">Get a token <span class="button-arrow">↗</span></button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="section-footnote reveal"><span class="footnote-check">✓</span> Your token is free. No account or app download needed.</div>
</section>

<section class="steps-band">
    <div class="steps-inner page-shell">
        <div class="steps-intro reveal"><div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> SIMPLE BY DESIGN</div><h2>Three steps.<br><em>Zero queue stress.</em></h2></div>
        <div class="step-item reveal reveal-delay-1"><span class="step-number">01</span><span class="step-icon">▤</span><h3>Take a token</h3><p>Choose your service and get your unique number.</p></div>
        <div class="step-item reveal reveal-delay-2"><span class="step-number">02</span><span class="step-icon">◷</span><h3>Track your place</h3><p>See the live queue and know how many are ahead.</p></div>
        <div class="step-item reveal reveal-delay-3"><span class="step-number">03</span><span class="step-icon">↗</span><h3>Come when called</h3><p>Watch the display. We'll keep things moving.</p></div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

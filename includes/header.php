<?php
$pageTitle = $pageTitle ?? 'QueueFlow';
$activePage = $activePage ?? '';
$flashes = take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f8">
    <meta name="description" content="A calmer, clearer way to manage everyday queues.">
    <title><?= e($pageTitle) ?> · QueueFlow</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.0.0">
    <script>
      (() => {
        try {
          const saved = localStorage.getItem('queueflow-theme');
          const theme = saved === 'dark' || saved === 'light' ? saved : 'light';
          document.documentElement.dataset.theme = theme;
        } catch (_) { document.documentElement.dataset.theme = 'light'; }
      })();
    </script>
</head>
<body>
<div class="scroll-progress" aria-hidden="true"><span id="scrollProgress"></span></div>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="QueueFlow home">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span>
            <span class="brand-name">queueflow<span class="brand-period">.</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="primaryNav">
            <span></span><span></span><span></span>
        </button>
        <nav class="primary-nav" id="primaryNav" aria-label="Main navigation">
            <a class="nav-link <?= $activePage === 'home' ? 'is-active' : '' ?>" href="index.php">Get a token</a>
            <a class="nav-link <?= $activePage === 'display' ? 'is-active' : '' ?>" href="display.php">Live display</a>
            <a class="nav-link <?= $activePage === 'admin' ? 'is-active' : '' ?>" href="admin.php">Admin</a>
        </nav>
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode">
                <span class="theme-icon" aria-hidden="true">☼</span>
                <span class="theme-label">Light Mode</span>
            </button>
            <?php if (is_admin()): ?>
                <a class="button button-dark button-small header-cta" href="logout.php">Sign out <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <a class="button button-dark button-small header-cta" href="admin.php">Staff login <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main id="mainContent">
<?php if ($flashes): ?>
<div class="toast-stack" aria-live="polite" aria-atomic="true">
    <?php foreach ($flashes as $message): ?>
        <div class="toast toast-<?= e($message['type']) ?>">
            <span class="toast-dot" aria-hidden="true"></span>
            <span><?= e($message['message']) ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss notification">×</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

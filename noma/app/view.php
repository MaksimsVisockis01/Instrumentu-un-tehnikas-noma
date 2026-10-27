<?php
declare(strict_types=1);

function icon(string $name, string $class = ''): string
{
    $paths = [
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'filter' => '<path d="M3 6h18M3 12h18M3 18h18M7 3v6m10 0v6M9 15v6"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'package' => '<path d="m12 3 9 5v8l-9 5-9-5V8zM3 8l9 5 9-5m-9 5v8M7.5 5.5l9 5V15"/>',
        'bolt' => '<path d="m13 2-9 12h7l-1 8L21 9h-8z"/>',
        'document' => '<path d="M14 3H5v18h14V8zM14 3v5h5M8 12h8m-8 4h6"/>',
        'zoom' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6M7 10h6m-3-3v6"/>',
        'tools' => '<path d="m14.7 6.3 3-3a5 5 0 0 0-6.4 6.4L3.4 17.6a2.1 2.1 0 0 0 3 3l7.9-7.9a5 5 0 0 0 6.4-6.4l-3 3z"/><path d="m4 3 5 5M3 4l2-2M15 16l5 5m-2-7-4 4"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 21v-2a7 7 0 0 1 14 0v2z"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 3.8M6 6.4A18 18 0 0 0 2 12s3.5 7 10 7c1.7 0 3.2-.5 4.5-1.2M10 10a3 3 0 0 0 4 4"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-5"/>',
        'pin' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0z"/><circle cx="12" cy="10" r="2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'logout' => '<path d="M9 4H4v16h5m0-8h12m-4-4 4 4-4 4"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
        'at' => '<circle cx="11" cy="12" r="4"/><path d="M15 8v7c0 3 7 2 7-3a10 10 0 1 0-4 8"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'phone' => '<path d="m8 3 2 5-3 2a15 15 0 0 0 7 7l2-3 5 2v3a2 2 0 0 1-2 2A18 18 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'id' => '<rect x="3" y="6" width="18" height="15" rx="2"/><path d="M10 6V3h4v3m5 5h-3m3 4h-3"/><circle cx="9" cy="12" r="2"/><path d="M6 18c0-4 6-4 6 0"/>',
        'repeat' => '<path d="M3 10a9 9 0 1 1 1 8M3 4v6h6"/><rect x="9" y="11" width="7" height="6" rx="1"/><path d="M11 11V9a1.5 1.5 0 0 1 3 0v2"/>',
    ];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function page_start(string $title, array $scripts = [], string $variant = ''): void
{
    ?>
    <!doctype html>
    <html lang="lv">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="NOMA.LV — instrumentu un tehnikas noma privātpersonām.">
        <meta name="theme-color" content="#143bad">
        <title><?= e($title) ?> · NOMA.LV</title>
        <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
        <link rel="stylesheet" href="assets/css/auth.css">
        <script src="assets/js/auth.js" defer></script>
        <?php foreach ($scripts as $script): ?>
        <script src="<?= e($script) ?>" defer></script>
        <?php endforeach; ?>
    </head>
    <body>
    <main class="auth-shell <?= e($variant) ?>">
    <?php
}

function brand(): void
{
    ?>
    <div class="brand" aria-label="NOMA.LV — instrumentu un tehnikas noma">
        <span class="brand-mark"><?= icon('tools') ?></span>
        <span class="brand-type"><strong>NOMA<span>.LV</span></strong><small>Instrumentu un tehnikas noma</small></span>
    </div>
    <?php
}

function site_header(array $user): void
{
    $fullName = $user['First_Name'] . ' ' . $user['Last_Name'];
    $initials = catalog_initial($user['First_Name']) . catalog_initial($user['Last_Name']);
    ?>
    <header class="header">
        <div class="header-inner container">
            <a class="brand-link" href="catalog.php"><?php brand(); ?></a>
            <nav class="navigation" aria-label="Galvenā navigācija">
                <a class="active" href="catalog.php">Meklēt preces</a>
                <span class="nav-pending" aria-disabled="true" title="Būs pieejams nākamajos posmos">Manas rezervācijas<small>Drīzumā</small></span>
                <span class="nav-pending" aria-disabled="true" title="Būs pieejams nākamajos posmos">Manas preces<small>Drīzumā</small></span>
                <a href="account.php">Profils</a>
            </nav>
            <div class="user-menu">
                <a class="user-profile" href="account.php" aria-label="<?= e($fullName) ?> — mans konts"><span class="avatar"><?= e($initials) ?></span><span><?= e($fullName) ?></span></a>
                <form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="logout-button"><?= icon('logout') ?><span>Iziet</span></button></form>
            </div>
        </div>
    </header>
    <?php
}

function site_footer(): void
{
    ?><footer class="footer container"><span>© <?= date('Y') ?> NOMA.LV <span class="footer-divider">·</span> Instrumentu un tehnikas noma</span><span><?= icon('pin') ?>Latvija · Mācību prototips</span></footer><?php
}

function page_end(): void
{
    ?>
        <footer class="site-footer">
            <span><i class="status-dot" aria-hidden="true"></i>Akadēmisks projekts</span>
            <span><?= icon('pin') ?>Latvija</span>
        </footer>
    </main>
    </body>
    </html>
    <?php
}

function service_error(): never
{
    http_response_code(503);
    header('Retry-After: 30');
    page_start('Pakalpojums nav pieejams');
    ?>
    <section class="auth-card">
        <div class="card-content">
            <?php brand(); ?>
            <div class="heading"><h1>Pašlaik nevar pieslēgties</h1><p>Lūdzu, mēģiniet vēlreiz pēc brīža.</p></div>
            <a class="primary-button" href="login.php">Mēģināt vēlreiz <?= icon('arrow') ?></a>
        </div>
    </section>
    <?php
    page_end();
    exit;
}

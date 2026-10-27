<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Atļauta tikai POST metode.');
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    http_response_code(419);
    page_start('Sesija ir beigusies');
    ?>
    <section class="auth-card"><div class="card-content">
        <?php brand(); ?>
        <div class="heading"><h1>Sesija ir beigusies</h1><p>Atveriet kontu vēlreiz un atkārtojiet darbību.</p></div>
        <a class="primary-button" href="account.php">Atgriezties kontā <?= icon('arrow') ?></a>
    </div></section>
    <?php
    page_end();
    exit;
}

try {
    if (remember_parts()) {
        forget_browser(db());
    } else {
        clear_remember_cookie();
    }
    $_SESSION = [];
    setcookie(session_name(), '', cookie_options(time() - 3600));
    session_destroy();
    redirect('login.php');
} catch (Throwable $exception) {
    error_log('NOMA logout: ' . $exception->getMessage());
    service_error();
}

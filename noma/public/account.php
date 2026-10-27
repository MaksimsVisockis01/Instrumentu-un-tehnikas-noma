<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
} catch (Throwable $exception) {
    error_log('NOMA account: ' . $exception->getMessage());
    service_error();
}

// Vienkāršs konta pārskats; profila rediģēšana būs atsevišķā posmā.
page_start('Mans konts');
?>
<section class="auth-card" aria-labelledby="account-title">
    <div class="card-content">
        <?php brand(); ?>
        <div class="success-icon"><?= icon('check') ?></div>
        <div class="heading success-heading"><h1 id="account-title">Sveiki, <?= e($user['First_Name']) ?>!</h1><p>Jūsu konta pārskats.</p></div>
        <dl class="account-details">
            <div><dt>Lietotājvārds</dt><dd><?= e($user['Username']) ?></dd></div>
            <div><dt>E-pasts</dt><dd><?= e($user['Email']) ?></dd></div>
            <div><dt>Loma</dt><dd><span class="role-badge"><?= $user['Role'] === 'admin' ? 'Administrators' : 'Klients' ?></span></dd></div>
        </dl>
        <p><a class="primary-button" href="catalog.php">Meklēt preces <?= icon('arrow') ?></a></p>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="primary-button" type="submit">Izrakstīties <?= icon('logout') ?></button>
        </form>
    </div>
    <div class="card-footer"><span class="connection-label"><?= icon('shield') ?>Piekļuve kontam ir apstiprināta</span></div>
</section>
<?php page_end(); ?>

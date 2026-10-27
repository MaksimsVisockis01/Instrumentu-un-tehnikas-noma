<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$error = '';
$identifier = '';
$remember = false;
$fieldErrors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['registration_success'])) {
    $flash = $_SESSION['registration_success'];
    if (is_array($flash) && isset($flash['email']) && is_string($flash['email'])) {
        $identifier = $flash['email'];
        $success = 'Konts ir veiksmīgi izveidots! Tagad pieslēdzieties ar savu paroli.';
    }
    unset($_SESSION['registration_success']);
}

try {
    if (current_user()) {
        redirect('catalog.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $identifier = trim(post_string('identifier'));
        $password = post_string('password');
        $remember = post_string('remember') === '1';

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(419);
            $error = 'Formas sesija ir beigusies. Lūdzu, mēģiniet vēlreiz.';
        } else {
            if ($identifier === '') {
                $fieldErrors['identifier'] = 'Ievadiet e-pastu vai lietotājvārdu.';
            } elseif (strlen($identifier) > 254) {
                $fieldErrors['identifier'] = 'E-pasts vai lietotājvārds ir pārāk garš.';
            }
            if ($password === '') {
                $fieldErrors['password'] = 'Ievadiet paroli.';
            } elseif (strlen($password) > 72) {
                $fieldErrors['password'] = 'Parole ir pārāk gara (maksimums 72 baiti).';
            }

            if ($fieldErrors) {
                http_response_code(422);
                $error = 'Pārbaudiet ievadītos datus.';
            } else {
                $result = attempt_login($identifier, $password, $remember);
                if ($result['ok']) {
                    redirect('catalog.php');
                }
                if ($result['retry_after'] > 0) {
                    http_response_code(429);
                    header('Retry-After: ' . $result['retry_after']);
                    $minutes = (int) ceil($result['retry_after'] / 60);
                    $error = 'Pārāk daudz neveiksmīgu mēģinājumu. Mēģiniet vēlreiz pēc ' . $minutes . ' min.';
                } else {
                    http_response_code(401);
                    $error = 'Lietotājvārds/e-pasts vai parole nav pareizi.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
        exit;
    }
} catch (Throwable $exception) {
    error_log('NOMA login: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'Pieslēgšanās pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz vēlāk.';
}

page_start('Pieslēgšanās');
?>
<section class="auth-card" aria-labelledby="login-title">
    <div class="card-content">
        <?php brand(); ?>
        <div class="heading">
            <h1 id="login-title">Pieslēgšanās sistēmai</h1>
            <p>Ievadiet savus datus, lai piekļūtu nomas<br class="desktop-break"> platformai</p>
        </div>

        <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status"><?= icon('check') ?><span><?= e($success) ?></span></div>
        <?php endif; ?>
        <div class="alert" id="form-error" role="alert" tabindex="-1" <?= $error === '' ? 'hidden' : '' ?>>
            <?= icon('info') ?><span><?= e($error) ?></span>
        </div>

        <form method="post" action="login.php" class="login-form" id="login-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="field">
                <div class="label-row"><label for="identifier">E-pasts vai lietotājvārds</label><span>Obligāts</span></div>
                <div class="input-wrap">
                    <?= icon('user', 'input-icon') ?>
                    <input id="identifier" name="identifier" type="text" value="<?= e($identifier) ?>" placeholder="piemēram, janis@epasts.lv vai janisb" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="254" required aria-describedby="identifier-error" <?= isset($fieldErrors['identifier']) ? 'aria-invalid="true"' : '' ?>>
                </div>
                <p id="identifier-error" class="field-error" <?= !isset($fieldErrors['identifier']) ? 'hidden' : '' ?>><?= e($fieldErrors['identifier'] ?? '') ?></p>
            </div>

            <div class="field">
                <div class="label-row"><label for="password">Parole</label></div>
                <div class="input-wrap password-wrap">
                    <?= icon('lock', 'input-icon') ?>
                    <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" maxlength="72" required aria-describedby="password-error caps-warning" <?= isset($fieldErrors['password']) ? 'aria-invalid="true"' : '' ?>>
                    <button class="password-toggle" id="password-toggle" type="button" aria-label="Rādīt paroli" aria-controls="password" aria-pressed="false" hidden>
                        <?= icon('eye', 'eye-open') ?><?= icon('eye-off', 'eye-closed') ?>
                    </button>
                </div>
                <p id="password-error" class="field-error" <?= !isset($fieldErrors['password']) ? 'hidden' : '' ?>><?= e($fieldErrors['password'] ?? '') ?></p>
                <p id="caps-warning" class="field-hint" hidden>Ir ieslēgts Caps Lock.</p>
            </div>

            <div class="form-options">
                <label class="remember"><input type="checkbox" name="remember" value="1" <?= $remember ? 'checked' : '' ?>><span>Atcerēties mani</span></label>
                <span class="unavailable-link" aria-disabled="true" title="Paroles atjaunošana būs pieejama vēlāk">Aizmirsāt paroli?</span>
            </div>

            <button class="primary-button" type="submit" id="login-button"><span class="button-text">Pieslēgties</span><?= icon('arrow', 'button-arrow') ?><span class="spinner" aria-hidden="true" hidden></span></button>

            <div class="divider"><span>vai</span></div>
            <div class="register-row">Vēl nav konta? <a href="register.php">Reģistrēties</a></div>
        </form>
    </div>
    <div class="card-footer">
        <span class="connection-label"><?= icon('shield') ?><?= is_https() ? 'Šifrēts HTTPS savienojums' : 'Lokālā izstrādes vide' ?></span>
        <p>Akadēmisks projekts · Instrumentu un tehnikas noma</p>
    </div>
</section>
<?php page_end(); ?>

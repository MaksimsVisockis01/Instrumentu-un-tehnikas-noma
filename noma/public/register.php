<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/registration.php';

$error = '';
$fieldErrors = [];
$values = registration_values([]);

try {
    if (current_user()) redirect('catalog.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $values = registration_values($_POST);
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(419);
            $error = 'Formas sesija ir beigusies. Ievadiet paroli vēlreiz un atkārtojiet reģistrāciju.';
        } else {
            $result = register_user($_POST);
            if ($result['ok']) {
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['registration_success'] = ['email' => $values['email']];
                redirect('login.php');
            }
            http_response_code($result['status']);
            $fieldErrors = $result['errors'];
            if ($result['status'] === 429) {
                header('Retry-After: ' . $result['retry_after']);
                $error = 'Pārāk daudz reģistrācijas mēģinājumu. Mēģiniet vēlreiz pēc ' . (int) ceil($result['retry_after'] / 60) . ' min.';
            } else {
                $error = 'Kontu neizdevās izveidot. Pārbaudiet atzīmētos laukus.';
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
        exit;
    }
} catch (Throwable $exception) {
    error_log('NOMA registration: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'Reģistrācija pašlaik nav pieejama. Lūdzu, mēģiniet vēlreiz vēlāk.';
}

// Paroles nekad neievietojam atpakaļ HTML formā.
$fields = [
    ['name' => 'username', 'label' => 'Lietotājvārds', 'type' => 'text', 'icon' => 'at', 'placeholder' => 'janis_b', 'autocomplete' => 'username', 'max' => 50, 'min' => 3, 'full' => true],
    ['name' => 'first_name', 'label' => 'Vārds', 'type' => 'text', 'icon' => 'id', 'placeholder' => 'Jānis', 'autocomplete' => 'given-name', 'max' => 100],
    ['name' => 'last_name', 'label' => 'Uzvārds', 'type' => 'text', 'icon' => 'user', 'placeholder' => 'Bērziņš', 'autocomplete' => 'family-name', 'max' => 100],
    ['name' => 'email', 'label' => 'E-pasts', 'type' => 'email', 'icon' => 'mail', 'placeholder' => 'janis.berzins@piemers.lv', 'autocomplete' => 'email', 'max' => 254],
    ['name' => 'phone', 'label' => 'Telefona numurs', 'type' => 'tel', 'icon' => 'phone', 'placeholder' => '+37129123456', 'autocomplete' => 'tel', 'max' => 40],
    ['name' => 'password', 'label' => 'Parole', 'type' => 'password', 'icon' => 'lock', 'placeholder' => 'Vismaz 8 rakstzīmes', 'autocomplete' => 'new-password', 'max' => 72, 'min' => 8],
    ['name' => 'password_confirmation', 'label' => 'Atkārtot paroli', 'type' => 'password', 'icon' => 'repeat', 'placeholder' => 'Ievadiet paroli vēlreiz', 'autocomplete' => 'new-password', 'max' => 72, 'min' => 8],
];

page_start('Reģistrācija', ['assets/js/registration.js'], 'registration-shell');
?>
<section class="auth-card registration-card" aria-labelledby="register-title">
    <div class="card-content">
        <?php brand(); ?>
        <span class="project-badge"><?= icon('shield') ?>Akadēmisks projekts</span>
        <div class="heading registration-heading">
            <h1 id="register-title">Jauna lietotāja reģistrācija</h1>
            <p>Izveidojiet profilu, lai iznomātu un piedāvātu instrumentus un tehniku</p>
        </div>

        <div class="alert" id="registration-error" role="alert" tabindex="-1" <?= $error === '' ? 'hidden' : '' ?>>
            <?= icon('info') ?><span><?= e($error) ?></span>
        </div>

        <form method="post" action="register.php" class="registration-form" id="registration-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <?php foreach ($fields as $field):
                    $name = $field['name'];
                    $isPassword = $field['type'] === 'password';
                ?>
                <div class="field <?= !empty($field['full']) ? 'field-full' : '' ?>">
                    <div class="label-row"><label for="<?= e($name) ?>"><?= e($field['label']) ?> <span class="required-mark" aria-hidden="true">*</span></label></div>
                    <div class="input-wrap <?= $isPassword ? 'password-wrap' : '' ?>">
                        <?= icon($field['icon'], 'input-icon') ?>
                        <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($field['type']) ?>"
                            <?= !$isPassword ? 'value="' . e($values[$name]) . '"' : '' ?>
                            placeholder="<?= e($field['placeholder']) ?>" autocomplete="<?= e($field['autocomplete']) ?>"
                            maxlength="<?= $field['max'] ?>" <?= isset($field['min']) ? 'minlength="' . $field['min'] . '"' : '' ?>
                            <?= in_array($name, ['username', 'email'], true) ? 'autocapitalize="none" spellcheck="false"' : '' ?>
                            required aria-describedby="<?= e($name) ?>-error" <?= isset($fieldErrors[$name]) ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($isPassword): ?>
                        <button class="password-toggle" type="button" data-password-toggle="<?= e($name) ?>" aria-label="<?= $name === 'password' ? 'Rādīt paroli' : 'Rādīt atkārtoto paroli' ?>" aria-controls="<?= e($name) ?>" aria-pressed="false" hidden>
                            <?= icon('eye', 'eye-open') ?><?= icon('eye-off', 'eye-closed') ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <p class="field-error" id="<?= e($name) ?>-error" <?= !isset($fieldErrors[$name]) ? 'hidden' : '' ?>><?= e($fieldErrors[$name] ?? '') ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="consent-panel">
                <label class="consent-label" for="accept_terms">
                    <input id="accept_terms" type="checkbox" name="accept_terms" value="1" required aria-describedby="accept_terms-error" <?= $values['accept_terms'] === '1' ? 'checked' : '' ?> <?= isset($fieldErrors['accept_terms']) ? 'aria-invalid="true"' : '' ?>>
                    <span>Esmu iepazinies ar <a href="#registration-information" id="notice-link">lietošanas un datu apstrādes informāciju</a> un apstiprinu ievadīto datu pareizību.</span>
                </label>
                <p class="field-error" id="accept_terms-error" <?= !isset($fieldErrors['accept_terms']) ? 'hidden' : '' ?>><?= e($fieldErrors['accept_terms'] ?? '') ?></p>
                <details class="registration-information" id="registration-information">
                    <summary>Informācija par kontu un datiem</summary>
                    <div class="notice-content">
                        <p>Šī ir NOMA.LV mācību projekta izmēģinājuma versija. Izmēģinājumam izmantojiet testa datus.</p>
                        <p>Lietotājvārdu un e-pastu izmanto pieslēgšanai. Vārdu, uzvārdu un tālruni saglabā konta datos. Parole netiek glabāta atklātā veidā.</p>
                        <p>Reģistrācija izveido klienta kontu. E-pasta adrese un tālruņa numurs šajā versijā netiek apstiprināti ar kodu. Reģistrējoties netiek noslēgts nomas līgums un netiek veikti maksājumi.</p>
                        <p class="notice-version">Informācijas versija: <?= e(REGISTRATION_NOTICE_VERSION) ?>. Tiek saglabāts iepazīšanās apstiprinājuma laiks.</p>
                    </div>
                </details>
            </div>

            <button class="primary-button" type="submit" id="register-button"><span class="button-text">Izveidot kontu</span><?= icon('arrow', 'button-arrow') ?><span class="spinner" aria-hidden="true" hidden></span></button>
            <p class="login-prompt">Jau ir reģistrēts konts? <a href="login.php">Pieslēgties šeit</a></p>
        </form>
    </div>
</section>
<?php page_end(); ?>

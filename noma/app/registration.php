<?php
declare(strict_types=1);

const REGISTRATION_NOTICE_VERSION = '1.0';

function registration_values(array $input): array
{
    $values = [];
    foreach (['username', 'first_name', 'last_name', 'email', 'phone', 'password', 'password_confirmation', 'accept_terms'] as $key) {
        $value = isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
        $values[$key] = in_array($key, ['password', 'password_confirmation'], true) ? $value : trim($value);
    }
    $values['email'] = strtolower($values['email']);
    return $values;
}

function text_length(string $value): int
{
    return preg_match_all('/./us', $value) ?: 0;
}

function normalize_phone(string $value): string
{
    $phone = preg_replace('/[\s().-]+/', '', $value) ?? '';
    if (preg_match('/\A[1-9][0-9]{7}\z/', $phone)) {
        return '+371' . $phone;
    }
    if (str_starts_with($phone, '00')) {
        return '+' . substr($phone, 2);
    }
    return $phone;
}

function registration_errors(array $values): array
{
    $errors = [];
    if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{2,49}\z/', $values['username'])) {
        $errors['username'] = 'Izmantojiet 3–50 latīņu burtus vai ciparus; atļauti arī . _ -. Sāciet ar burtu vai ciparu.';
    }
    foreach (['first_name' => 'vārdu', 'last_name' => 'uzvārdu'] as $key => $label) {
        if (text_length($values[$key]) > 100 || !preg_match("/\A[\p{L}\p{M}]+(?:[ '\x{2019}-][\p{L}\p{M}]+)*\z/u", $values[$key])) {
            $errors[$key] = 'Ievadiet ' . $label . ' (līdz 100 rakstzīmēm).';
        }
    }
    if (strlen($values['email']) > 254 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ievadiet derīgu e-pasta adresi.';
    }

    $phone = normalize_phone($values['phone']);
    if (strlen($values['phone']) > 40 || !preg_match('/\A\+[1-9][0-9]{7,14}\z/', $phone)
        || (str_starts_with($phone, '+371') && strlen($phone) !== 12)) {
        $errors['phone'] = 'Ievadiet 8 ciparu Latvijas numuru vai numuru ar valsts kodu, piemēram, +37129123456.';
    }
    if (!preg_match('//u', $values['password']) || text_length($values['password']) < 8) {
        $errors['password'] = 'Parolei jābūt vismaz 8 rakstzīmes garai.';
    } elseif (strlen($values['password']) > 72 || str_contains($values['password'], "\0")) {
        $errors['password'] = 'Parole ir pārāk gara vai satur neatļautas rakstzīmes (maksimums 72 baiti).';
    }
    if ($values['password_confirmation'] === '' || !hash_equals($values['password'], $values['password_confirmation'])) {
        $errors['password_confirmation'] = 'Paroles nesakrīt. Ievadiet paroli vēlreiz.';
    }
    if ($values['accept_terms'] !== '1') {
        $errors['accept_terms'] = 'Lai izveidotu kontu, apstipriniet iepazīšanos ar informāciju un datu pareizību.';
    }
    return $errors;
}

function registration_duplicates(PDO $connection, array $values): array
{
    $statement = $connection->prepare('SELECT CASE WHEN Username = :username THEN 1 ELSE 0 END AS username_taken, CASE WHEN Email = :email THEN 1 ELSE 0 END AS email_taken FROM Users WHERE Username = :lookup_username OR Email = :lookup_email');
    $statement->execute([
        'username' => $values['username'], 'email' => $values['email'],
        'lookup_username' => $values['username'], 'lookup_email' => $values['email'],
    ]);
    $errors = [];
    foreach ($statement->fetchAll() as $row) {
        if ((int) $row['username_taken'] === 1) $errors['username'] = 'Šis lietotājvārds jau ir aizņemts.';
        if ((int) $row['email_taken'] === 1) $errors['email'] = 'Konts ar šo e-pastu jau pastāv. Pieslēdzieties savam kontam.';
    }
    return $errors;
}

function register_user(array $input): array
{
    $values = registration_values($input);
    $errors = registration_errors($values);
    if ($errors) return ['ok' => false, 'status' => 422, 'errors' => $errors];

    $connection = db();
    $now = time();
    $bucket = 'register-ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
    $connection->beginTransaction();
    try {
        // Atsevišķs limits reģistrācijai no vienas IP adreses.
        $insert = $connection->prepare('INSERT INTO auth_login_limits (bucket_key, failures, window_started) VALUES (:key, 0, :now) ON DUPLICATE KEY UPDATE bucket_key = bucket_key');
        $insert->execute(['key' => $bucket, 'now' => $now]);
        $lock = $connection->prepare('SELECT failures, window_started FROM auth_login_limits WHERE bucket_key = :key FOR UPDATE');
        $lock->execute(['key' => $bucket]);
        $row = $lock->fetch();
        $window = config('auth', 'window_seconds');
        if ($now - (int) $row['window_started'] >= $window) {
            $row = ['failures' => 0, 'window_started' => $now];
        }
        if ((int) $row['failures'] >= config('auth', 'registration_attempts', 10)) {
            $connection->commit();
            return ['ok' => false, 'status' => 429, 'errors' => [], 'retry_after' => max(1, (int) $row['window_started'] + $window - $now)];
        }
        $update = $connection->prepare('UPDATE auth_login_limits SET failures = :attempts, window_started = :started WHERE bucket_key = :key');
        $update->execute(['attempts' => (int) $row['failures'] + 1, 'started' => $row['window_started'], 'key' => $bucket]);

        $errors = registration_duplicates($connection, $values);
        if ($errors) {
            $connection->commit();
            return ['ok' => false, 'status' => 409, 'errors' => $errors];
        }

        // Publiska reģistrācija vienmēr izveido tikai klienta kontu.
        $create = $connection->prepare("INSERT INTO Users (Username, First_Name, Last_Name, Email, Phone, Password, Role, Status) VALUES (:username, :first_name, :last_name, :email, :phone, :password, 'client', 'active')");
        $create->execute([
            'username' => $values['username'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'],
            'phone' => normalize_phone($values['phone']),
            'password' => password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        $userId = (int) $connection->lastInsertId();
        $consent = $connection->prepare('INSERT INTO auth_registration_consents (User_ID, notice_version, accepted_at) VALUES (:user, :version, UTC_TIMESTAMP())');
        $consent->execute(['user' => $userId, 'version' => REGISTRATION_NOTICE_VERSION]);
        $connection->commit();
        return ['ok' => true, 'status' => 201, 'user_id' => $userId];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        // UNIQUE indeksi pasargā arī no vienlaicīgas reģistrācijas.
        if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
            $errors = registration_duplicates($connection, $values);
            return ['ok' => false, 'status' => 409, 'errors' => $errors ?: ['username' => 'Konts ar šiem datiem jau pastāv.']];
        }
        throw $exception;
    }
}

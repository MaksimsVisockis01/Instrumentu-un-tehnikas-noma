<?php
declare(strict_types=1);

function start_authenticated_session(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $userId,
        'signed_in_at' => time(),
        'last_activity' => time(),
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

function clear_authentication(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function remember_parts(): ?array
{
    $value = $_COOKIE['noma_remember'] ?? '';
    if (!is_string($value) || !preg_match('/\A([a-f0-9]{24}):([a-f0-9]{64})\z/', $value, $matches)) {
        return null;
    }
    return [$matches[1], $matches[2]];
}

function clear_remember_cookie(): void
{
    setcookie('noma_remember', '', cookie_options(time() - 3600));
    unset($_COOKIE['noma_remember']);
}

function forget_browser(PDO $connection): void
{
    $parts = remember_parts();
    if ($parts) {
        $statement = $connection->prepare('DELETE FROM auth_remember_tokens WHERE selector = :selector AND token_hash = :hash');
        $statement->execute(['selector' => $parts[0], 'hash' => hash('sha256', $parts[1])]);
    }
    clear_remember_cookie();
}

function remember_browser(PDO $connection, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + config('auth', 'remember_days') * 86400;
    $statement = $connection->prepare('INSERT INTO auth_remember_tokens (User_ID, selector, token_hash, expires_at) VALUES (:user, :selector, :hash, :expires)');
    $statement->execute([
        'user' => $userId,
        'selector' => $selector,
        'hash' => hash('sha256', $validator),
        'expires' => gmdate('Y-m-d H:i:s', $expires),
    ]);
    setcookie('noma_remember', $selector . ':' . $validator, cookie_options($expires));
}

function restore_remembered_user(): ?array
{
    $parts = remember_parts();
    if (!$parts) {
        return null;
    }

    $connection = db();
    $statement = $connection->prepare('SELECT t.token_hash, t.expires_at, u.User_ID, u.Username, u.Email, u.First_Name, u.Last_Name, u.Role, u.Status FROM auth_remember_tokens t INNER JOIN Users u ON u.User_ID = t.User_ID WHERE t.selector = :selector LIMIT 1');
    $statement->execute(['selector' => $parts[0]]);
    $user = $statement->fetch();

    if (!$user || $user['Status'] !== 'active' || strtotime($user['expires_at']) <= time()
        || !hash_equals($user['token_hash'], hash('sha256', $parts[1]))) {
        clear_remember_cookie();
        return null;
    }

    // Mainām atslēgu pēc automātiskas pieslēgšanās.
    $newValidator = bin2hex(random_bytes(32));
    $rotate = $connection->prepare('UPDATE auth_remember_tokens SET token_hash = :new_hash WHERE selector = :selector AND token_hash = :old_hash');
    $rotate->execute([
        'new_hash' => hash('sha256', $newValidator),
        'selector' => $parts[0],
        'old_hash' => $user['token_hash'],
    ]);
    if ($rotate->rowCount() !== 1) {
        return null;
    }
    setcookie('noma_remember', $parts[0] . ':' . $newValidator, cookie_options(strtotime($user['expires_at'])));
    start_authenticated_session((int) $user['User_ID']);
    unset($user['token_hash'], $user['expires_at']);
    return $user;
}

function current_user(): ?array
{
    if (isset($_SESSION['user_id'])) {
        $idleExpired = time() - (int) ($_SESSION['last_activity'] ?? 0) >= config('auth', 'idle_seconds');
        $absoluteExpired = time() - (int) ($_SESSION['signed_in_at'] ?? 0) >= config('auth', 'absolute_seconds');
        if ($idleExpired || $absoluteExpired) {
            clear_authentication();
        } else {
            // Loma un konta statuss vienmēr nāk no datubāzes.
            $statement = db()->prepare('SELECT User_ID, Username, Email, First_Name, Last_Name, Role, Status FROM Users WHERE User_ID = :id LIMIT 1');
            $statement->execute(['id' => (int) $_SESSION['user_id']]);
            $user = $statement->fetch();
            if ($user && $user['Status'] === 'active') {
                $_SESSION['last_activity'] = time();
                return $user;
            }
            forget_browser(db());
            clear_authentication();
            return null;
        }
    }
    return restore_remembered_user();
}

function attempt_login(string $identifier, string $password, bool $remember): array
{
    $connection = db();
    $statement = $connection->prepare('SELECT User_ID, Password, Status FROM Users WHERE Email = :email OR Username = :username LIMIT 1');
    $statement->execute(['email' => $identifier, 'username' => $identifier]);
    $user = $statement->fetch();

    $accountKey = $user ? 'account:' . $user['User_ID'] : 'unknown:' . hash('sha256', strtolower($identifier));
    $ipKey = 'ip:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
    $limits = [$accountKey => config('auth', 'account_attempts'), $ipKey => config('auth', 'ip_attempts')];
    ksort($limits);
    $now = time();
    $window = config('auth', 'window_seconds');

    $connection->beginTransaction();
    try {
        $buckets = [];
        foreach ($limits as $key => $limit) {
            $insert = $connection->prepare('INSERT INTO auth_login_limits (bucket_key, failures, window_started) VALUES (:key, 0, :now) ON DUPLICATE KEY UPDATE bucket_key = bucket_key');
            $insert->execute(['key' => $key, 'now' => $now]);
            $lock = $connection->prepare('SELECT failures, window_started FROM auth_login_limits WHERE bucket_key = :key FOR UPDATE');
            $lock->execute(['key' => $key]);
            $row = $lock->fetch();
            if ($now - (int) $row['window_started'] >= $window) {
                $row = ['failures' => 0, 'window_started' => $now];
            }
            $buckets[$key] = $row;
            if ((int) $row['failures'] >= $limit) {
                $connection->commit();
                return ['ok' => false, 'retry_after' => max(1, (int) $row['window_started'] + $window - $now)];
            }
        }

        // Nezināmam kontam veicam tādu pašu paroles pārbaudi.
        $dummyHash = '$2y$12$7xn7pJPMAgxpTMJF47KFqOMACA4Jgbrwg4TTaJ7T9Rq2F2hdobAiK';
        $matches = password_verify($password, $user['Password'] ?? $dummyHash);
        if (!$user || !$matches || $user['Status'] !== 'active') {
            $retryAfter = 0;
            foreach ($buckets as $key => $row) {
                $failures = (int) $row['failures'] + 1;
                $update = $connection->prepare('UPDATE auth_login_limits SET failures = :failures, window_started = :started WHERE bucket_key = :key');
                $update->execute(['failures' => $failures, 'started' => $row['window_started'], 'key' => $key]);
                if ($failures >= $limits[$key]) {
                    $retryAfter = max($retryAfter, (int) $row['window_started'] + $window - $now);
                }
            }
            $connection->commit();
            return ['ok' => false, 'retry_after' => $retryAfter];
        }

        $reset = $connection->prepare('DELETE FROM auth_login_limits WHERE bucket_key = :key');
        $reset->execute(['key' => $accountKey]);
        if (password_needs_rehash($user['Password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $rehash = $connection->prepare('UPDATE Users SET Password = :hash WHERE User_ID = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $user['User_ID']]);
        }
        $lastLogin = $connection->prepare('UPDATE Users SET Last_Login = UTC_TIMESTAMP() WHERE User_ID = :id');
        $lastLogin->execute(['id' => $user['User_ID']]);
        forget_browser($connection);
        if ($remember) {
            remember_browser($connection, (int) $user['User_ID']);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    start_authenticated_session((int) $user['User_ID']);
    return ['ok' => true, 'retry_after' => 0];
}

<?php
declare(strict_types=1);

const PRODUCT_MONTHS = [1 => 'Janvāris', 'Februāris', 'Marts', 'Aprīlis', 'Maijs', 'Jūnijs',
    'Jūlijs', 'Augusts', 'Septembris', 'Oktobris', 'Novembris', 'Decembris'];

function product_id(mixed $value): ?int
{
    return is_string($value) && preg_match('/^[1-9]\d{0,17}$/D', $value) ? (int) $value : null;
}

function product_dates(array $raw, bool $defaults = false): array
{
    $today = catalog_today();
    $values = [
        'start_date' => $raw['start_date'] ?? ($defaults ? $today : ''),
        'end_date' => $raw['end_date'] ?? ($defaults ? (new DateTimeImmutable($today))->modify('+3 days')->format('Y-m-d') : ''),
    ];
    $filters = catalog_filters($values, []);
    if ($filters['start_date'] === '' && $filters['end_date'] === '' && !$filters['errors']) {
        $filters['errors'][] = 'Izvēlieties nomas sākumu un atgriešanas datumu.';
    }
    return ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'errors' => $filters['errors']];
}

function product_url(int $id, array $dates = [], array $extra = []): string
{
    $query = ['id' => $id];
    foreach (['start_date', 'end_date'] as $key) {
        if (isset($dates[$key]) && is_string($dates[$key]) && $dates[$key] !== '') $query[$key] = $dates[$key];
    }
    foreach (['month', 'reservation'] as $key) {
        if (isset($extra[$key])) $query[$key] = $extra[$key];
    }
    return 'product.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function product_details(int $id): array
{
    $statement = db()->prepare('SELECT * FROM Product_Details WHERE Product_ID = :id');
    $statement->execute(['id' => $id]);
    $details = $statement->fetch() ?: [
        'Specifications' => null,
        'Condition_Notes' => 'Preces stāvoklis norādīts sludinājumā.',
        'Rental_Terms' => 'Saņemt un atgriezt preci izvēlētajos datumos. Atgriezt tīru un pilnā komplektācijā.',
        'Pickup_Info' => 'Saņemšana klātienē norādītajā pilsētā.',
    ];
    $decoded = json_decode($details['Specifications'] ?? '{}', true);
    $specs = [];
    if (is_array($decoded)) foreach ($decoded as $label => $value) {
        if (is_string($label) && (is_string($value) || is_numeric($value))) $specs[$label] = (string) $value;
    }
    $details['specs'] = $specs;
    return $details;
}

function product_cents(string $amount): int
{
    // Naudas aprēķinus veicam veselos centos.
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) throw new UnexpectedValueException('Invalid stored price');
    [$whole, $fraction] = array_pad(explode('.', $amount), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}

function product_decimal(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function product_amount(int $cents): string
{
    return number_format(intdiv($cents, 100), 0, ',', ' ') . ','
        . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT) . ' €';
}

function product_quote(array $product, array $dates): ?array
{
    if ($dates['errors']) return null;
    $days = (int) (new DateTimeImmutable($dates['start_date']))->diff(new DateTimeImmutable($dates['end_date']))->days;
    $daily = product_cents((string) $product['Price']);
    $deposit = product_cents((string) $product['Deposit']);
    $rent = $daily * $days;
    return ['days' => $days, 'daily' => $daily, 'rent' => $rent, 'deposit' => $deposit, 'total' => $rent + $deposit];
}

function product_available(int $id, string $start, string $end, bool $lock = false): bool
{
    foreach (['Product_Unavailability' => 'Block_ID', 'Reservations' => 'Reservation_ID'] as $table => $key) {
        $sql = "SELECT $key FROM $table WHERE Product_ID = :id AND Start_Date < :end AND End_Date > :start";
        if ($table === 'Reservations') $sql .= " AND Status = 'demo_confirmed'";
        $statement = db()->prepare($sql . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $id, 'start' => $start, 'end' => $end]);
        if ($statement->fetchColumn()) return false;
    }
    return true;
}

function product_month(mixed $value, array $dates): DateTimeImmutable
{
    $fallback = !$dates['errors'] ? substr($dates['start_date'], 0, 7) : substr(catalog_today(), 0, 7);
    $month = is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value) && catalog_date($value . '-01') ? $value : $fallback;
    $month = max(substr(catalog_today(), 0, 7), $month);
    return new DateTimeImmutable($month . '-01');
}

function product_calendar(int $id, DateTimeImmutable $month): array
{
    $from = $month->format('Y-m-d');
    $last = $month->modify('last day of this month')->format('Y-m-d');
    $statement = db()->prepare("SELECT Start_Date, End_Date FROM Product_Unavailability
        WHERE Product_ID = :block_id AND Start_Date <= :block_last AND End_Date > :block_from
        UNION ALL SELECT Start_Date, End_Date FROM Reservations
        WHERE Product_ID = :reservation_id AND Status = 'demo_confirmed'
        AND Start_Date <= :reservation_last AND End_Date > :reservation_from");
    $statement->execute(['block_id' => $id, 'block_last' => $last, 'block_from' => $from,
        'reservation_id' => $id, 'reservation_last' => $last, 'reservation_from' => $from]);
    $blocks = $statement->fetchAll();
    $days = [];
    for ($number = 1; $number <= (int) $month->format('t'); $number++) {
        $date = $month->format('Y-m-') . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $busy = false;
        foreach ($blocks as $block) if ($block['Start_Date'] <= $date && $block['End_Date'] > $date) {
            $busy = true;
            break;
        }
        $days[] = ['date' => $date, 'number' => $number, 'busy' => $busy, 'past' => $date < catalog_today()];
    }
    return $days;
}

function product_reservation(int $id, int $productId, int $clientId): ?array
{
    $statement = db()->prepare('SELECT * FROM Reservations WHERE Reservation_ID = :id AND Product_ID = :product AND Client_ID = :client');
    $statement->execute(['id' => $id, 'product' => $productId, 'client' => $clientId]);
    return $statement->fetch() ?: null;
}

function product_my_reservations(int $productId, int $clientId): array
{
    $statement = db()->prepare("SELECT * FROM Reservations WHERE Product_ID = :product AND Client_ID = :client
        AND Status = 'demo_confirmed' AND End_Date > :today ORDER BY Start_Date, Reservation_ID");
    $statement->execute(['product' => $productId, 'client' => $clientId, 'today' => catalog_today()]);
    return $statement->fetchAll();
}

function product_reserve(int $productId, int $clientId, array $raw, string $key): array
{
    $dates = product_dates($raw);
    if ($dates['errors']) return ['ok' => false, 'status' => 422, 'error' => implode(' ', $dates['errors'])];
    if (!preg_match('/^[a-f0-9]{64}$/D', $key)) return ['ok' => false, 'status' => 422, 'error' => 'Atjaunojiet lapu un mēģiniet vēlreiz.'];
    $connection = db();
    $connection->beginTransaction();
    try {
        // Vienas preces rezervācijas apstrādājam pēc kārtas.
        $statement = $connection->prepare('SELECT * FROM Products WHERE Product_ID = :id FOR UPDATE');
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();
        if (!$product || $product['Status'] !== 'active') {
            $connection->rollBack();
            return ['ok' => false, 'status' => 404, 'error' => 'Šī prece vairs nav pieejama.'];
        }
        $statement = $connection->prepare("SELECT User_ID FROM Users WHERE Status = 'active' AND User_ID IN (:owner, :client)");
        $statement->execute(['owner' => $product['Owner_ID'], 'client' => $clientId]);
        if ((int) $product['Owner_ID'] === $clientId || count($statement->fetchAll()) !== 2) {
            $connection->rollBack();
            return ['ok' => false, 'status' => 403, 'error' => 'Šo preci nevarat rezervēt. Savu preci iznomā citiem lietotājiem.'];
        }
        $statement = $connection->prepare('SELECT * FROM Reservations WHERE Request_Key = :key FOR UPDATE');
        $statement->execute(['key' => $key]);
        $previous = $statement->fetch();
        if ($previous) {
            $same = (int) $previous['Product_ID'] === $productId && (int) $previous['Client_ID'] === $clientId
                && $previous['Start_Date'] === $dates['start_date'] && $previous['End_Date'] === $dates['end_date'];
            $connection->rollBack();
            return $same ? ['ok' => true, 'id' => (int) $previous['Reservation_ID'], 'replayed' => true]
                : ['ok' => false, 'status' => 409, 'error' => 'Šī forma jau izmantota citiem datumiem. Atjaunojiet lapu.'];
        }
        if (!product_available($productId, $dates['start_date'], $dates['end_date'], true)) {
            $connection->rollBack();
            return ['ok' => false, 'status' => 409, 'error' => 'Prece šajā periodā ir aizņemta. Izvēlieties citus datumus.'];
        }
        $quote = product_quote($product, $dates);
        $statement = $connection->prepare('INSERT INTO Reservations
            (Product_ID, Client_ID, Start_Date, End_Date, Daily_Price, Rental_Price, Deposit, Total_Price, Request_Key)
            VALUES (:product, :client, :start, :end, :daily, :rent, :deposit, :total, :key)');
        $statement->execute(['product' => $productId, 'client' => $clientId, 'start' => $dates['start_date'], 'end' => $dates['end_date'],
            'daily' => product_decimal($quote['daily']), 'rent' => product_decimal($quote['rent']),
            'deposit' => product_decimal($quote['deposit']), 'total' => product_decimal($quote['total']), 'key' => $key]);
        $id = (int) $connection->lastInsertId();
        $connection->commit();
        return ['ok' => true, 'id' => $id, 'replayed' => false];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function product_cancel(int $reservationId, int $productId, int $clientId): bool
{
    $connection = db();
    $connection->beginTransaction();
    try {
        // Tā pati bloķēšanas secība kā rezervācijā.
        $statement = $connection->prepare('SELECT Product_ID FROM Products WHERE Product_ID = :id FOR UPDATE');
        $statement->execute(['id' => $productId]);
        $statement->fetchColumn();
        $statement = $connection->prepare('SELECT Reservation_ID FROM Reservations
            WHERE Reservation_ID = :id AND Product_ID = :product AND Client_ID = :client FOR UPDATE');
        $statement->execute(['id' => $reservationId, 'product' => $productId, 'client' => $clientId]);
        if (!$statement->fetchColumn()) { $connection->rollBack(); return false; }
        $statement = $connection->prepare("UPDATE Reservations SET Status = 'demo_cancelled', Cancelled_At = COALESCE(Cancelled_At, UTC_TIMESTAMP())
            WHERE Reservation_ID = :id AND Client_ID = :client");
        $statement->execute(['id' => $reservationId, 'client' => $clientId]);
        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function product_date_label(string $date): string
{
    return (new DateTimeImmutable($date))->format('d.m.Y');
}

function product_not_found(array $user): never
{
    http_response_code(404);
    ?><!doctype html><html lang="lv"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prece nav atrasta · NOMA.LV</title><link rel="stylesheet" href="assets/css/catalog.css"></head><body><?php site_header($user); ?><main class="container main"><div class="empty-state"><?= icon('search') ?><h1>Prece nav atrasta</h1><p>Sludinājums nav pieejams vai adrese nav pareiza.</p><a class="button" href="catalog.php">Atgriezties katalogā</a></div></main><?php site_footer(); ?></body></html><?php
    exit;
}

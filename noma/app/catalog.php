<?php
declare(strict_types=1);

const CATALOG_CONDITIONS = [
    'new' => 'Jauns (oriģināls iepakojums)',
    'excellent' => 'Teicamā stāvoklī',
    'good' => 'Labs, ar lietojuma pazīmēm',
];
const CATALOG_SORTS = [
    'recommended' => 'Ieteicamie vispirms',
    'price_asc' => 'Cena: no zemākās',
    'price_desc' => 'Cena: no augstākās',
    'newest' => 'Jaunākie vispirms',
    'name' => 'Nosaukums: A–Z',
];

function catalog_today(): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Europe/Riga')))->format('Y-m-d');
}

function catalog_categories(): array
{
    return db()->query("SELECT c.*, COUNT(p.Product_ID) AS Product_Count
        FROM Categories c LEFT JOIN Products p ON p.Category_ID = c.Category_ID
        AND p.Status = 'active' AND EXISTS
            (SELECT 1 FROM Users u WHERE u.User_ID = p.Owner_ID AND u.Status = 'active')
        GROUP BY c.Category_ID, c.Slug, c.Name, c.Display_Order
        ORDER BY c.Display_Order, c.Category_ID")->fetchAll();
}

function catalog_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function catalog_decimal(string $value): ?string
{
    $value = str_replace(',', '.', $value);
    if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $value)) return null;
    [$whole, $part] = array_pad(explode('.', $value), 2, '');
    return (int) $whole . '.' . str_pad($part, 2, '0');
}

/** Validate GET before it reaches SQL or generated URLs. No mbstring dependency. */
function catalog_filters(array $raw, array $categories): array
{
    $errors = [];
    $scalar = static function (string $key, string $default = '') use ($raw, &$errors): string {
        if (!isset($raw[$key])) return $default;
        if (!is_string($raw[$key])) {
            $errors[] = 'Nederīgs filtra formāts: ' . $key . '.';
            return $default;
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw[$key])) {
            $errors[] = 'Filtrā ir neatļautas vadības rakstzīmes.';
            return $default;
        }
        return trim($raw[$key]);
    };
    $multi = static function (string $key, array $allowed) use ($raw, &$errors): array {
        if (!isset($raw[$key])) return [];
        if (!is_array($raw[$key]) || count($raw[$key]) > 20) {
            $errors[] = 'Nederīgs izvēles filtrs.';
            return [];
        }
        $values = [];
        foreach ($raw[$key] as $value) {
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                $errors[] = 'Izvēlēta nezināma kategorija vai preces stāvoklis.';
            } else {
                $values[] = $value;
            }
        }
        return array_values(array_unique($values));
    };
    $f = [
        'q' => $scalar('q'),
        'categories' => $multi('categories', array_column($categories, 'Slug')),
        'conditions' => $multi('conditions', array_keys(CATALOG_CONDITIONS)),
        'min_price' => $scalar('min_price'),
        'max_price' => $scalar('max_price'),
        'start_date' => $scalar('start_date'),
        'end_date' => $scalar('end_date'),
        'availability' => $scalar('availability', 'all'),
        'sort' => $scalar('sort', 'recommended'),
        'favorites' => $scalar('favorites', '0'),
        'page' => $scalar('page', '1'),
    ];
    if (strlen($f['q']) > 400 || !preg_match('/^.{0,100}$/usD', $f['q'])) {
        $errors[] = 'Meklēšanas tekstā drīkst būt līdz 100 rakstzīmēm.';
        $f['q'] = '';
    }
    foreach (['min_price', 'max_price'] as $key) {
        if ($f[$key] === '') continue;
        $value = catalog_decimal($f[$key]);
        if ($value === null) {
            $errors[] = 'Ievadiet cenu no 0 līdz 999999,99 € (līdz 2 cipariem aiz komata).';
            $f[$key] = '';
        } else {
            $f[$key] = $value;
        }
    }
    if ($f['min_price'] !== '' && $f['max_price'] !== '' && (float) $f['min_price'] > (float) $f['max_price']) {
        $errors[] = 'Minimālā cena nedrīkst pārsniegt maksimālo cenu.';
    }
    $hasDates = $f['start_date'] !== '' || $f['end_date'] !== '';
    if ($hasDates) {
        if (!catalog_date($f['start_date']) || !catalog_date($f['end_date'])) {
            $errors[] = 'Norādiet abus derīgus nomas datumus.';
        } elseif ($f['end_date'] <= $f['start_date']) {
            $errors[] = 'Nomas beigām jābūt pēc sākuma datuma.';
        } elseif ($f['start_date'] < catalog_today()) {
            $errors[] = 'Nomas sākums nevar būt pagātnē.';
        }
    }
    if (!in_array($f['availability'], ['all', 'available'], true)) {
        $errors[] = 'Nederīga pieejamības izvēle.';
        $f['availability'] = 'all';
    }
    if (!isset(CATALOG_SORTS[$f['sort']])) {
        $errors[] = 'Nederīga kārtošanas izvēle.';
        $f['sort'] = 'recommended';
    }
    if (!in_array($f['favorites'], ['0', '1'], true)) {
        $errors[] = 'Nederīga saglabāto preču izvēle.';
        $f['favorites'] = '0';
    }
    $f['page'] = preg_match('/^\d{1,12}$/D', $f['page']) ? max(1, min(1000000, (int) $f['page'])) : 1;
    $f['errors'] = array_values(array_unique($errors));
    return $f;
}

function catalog_period(array $f): array
{
    $start = $f['start_date'] ?: catalog_today();
    $end = $f['end_date'] ?: (new DateTimeImmutable($start))->modify('+1 day')->format('Y-m-d');
    return [$start, $end];
}

function catalog_url(array $f, array $changes = []): string
{
    $values = array_replace($f, $changes);
    $query = [];
    foreach (['q', 'categories', 'conditions', 'min_price', 'max_price', 'start_date', 'end_date', 'availability', 'sort', 'favorites', 'page', 'view'] as $key) {
        $value = $values[$key] ?? '';
        if ($value === '' || $value === [] || ($key === 'availability' && $value === 'all')
            || ($key === 'sort' && $value === 'recommended') || ($key === 'favorites' && $value === '0')
            || ($key === 'page' && (int) $value === 1)) continue;
        $query[$key] = $value;
    }
    return 'catalog.php' . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function catalog_query(array $f, int $userId): array
{
    [$start, $end] = catalog_period($f);
    $params = ['viewer' => $userId, 'period_start' => $start, 'period_end' => $end,
        'reservation_start' => $start, 'reservation_end' => $end];
    $sql = " FROM Products p
        JOIN Users u ON u.User_ID = p.Owner_ID AND u.Status = 'active'
        JOIN Categories c ON c.Category_ID = p.Category_ID
        LEFT JOIN Favorites fav ON fav.Product_ID = p.Product_ID AND fav.User_ID = :viewer
        LEFT JOIN (SELECT Product_ID FROM Product_Unavailability
            WHERE Start_Date < :period_end AND End_Date > :period_start
            UNION SELECT Product_ID FROM Reservations WHERE Status = 'demo_confirmed'
            AND Start_Date < :reservation_end AND End_Date > :reservation_start) busy ON busy.Product_ID = p.Product_ID
        WHERE p.Status = 'active'";
    $words = preg_split('/\s+/u', $f['q'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($words as $i => $word) {
        $sql .= " AND CONCAT_WS(' ', p.Name, p.Description, p.City, c.Name) LIKE :word$i ESCAPE '!'";
        $params['word' . $i] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
    }
    foreach (['categories' => 'c.Slug', 'conditions' => 'p.Item_Condition'] as $key => $column) {
        if (!$f[$key]) continue;
        $placeholders = [];
        foreach ($f[$key] as $i => $value) {
            $name = $key . $i;
            $params[$name] = $value;
            $placeholders[] = ':' . $name;
        }
        $sql .= ' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')';
    }
    if ($f['min_price'] !== '') {
        $sql .= ' AND p.Price >= :min_price';
        $params['min_price'] = $f['min_price'];
    }
    if ($f['max_price'] !== '') {
        $sql .= ' AND p.Price <= :max_price';
        $params['max_price'] = $f['max_price'];
    }
    if ($f['availability'] === 'available') $sql .= ' AND busy.Product_ID IS NULL';
    if ($f['favorites'] === '1') $sql .= ' AND fav.Product_ID IS NOT NULL';
    return [$sql, $params];
}

function catalog_results(array $f, int $userId): array
{
    if ($f['errors']) return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    [$sql, $params] = catalog_query($f, $userId);
    $count = db()->prepare('SELECT COUNT(*)' . $sql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 6));
    $page = min($pages, $f['page']);
    $sort = [
        'recommended' => 'p.Featured_Rank, p.Product_ID',
        'price_asc' => 'p.Price, p.Product_ID',
        'price_desc' => 'p.Price DESC, p.Product_ID',
        'newest' => 'p.Created_At DESC, p.Product_ID DESC',
        'name' => 'p.Name, p.Product_ID',
    ][$f['sort']];
    $select = 'SELECT p.*, c.Name AS Category_Name, u.First_Name, u.Last_Name,
        (fav.Product_ID IS NOT NULL) AS Is_Favorite, (busy.Product_ID IS NULL) AS Is_Available';
    // Only validated integer pagination values and the fixed sort allowlist enter SQL text.
    $statement = db()->prepare($select . $sql . ' ORDER BY ' . $sort . ' LIMIT 6 OFFSET ' . (($page - 1) * 6));
    $statement->execute($params);
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function catalog_product(int $id, array $f, int $userId): ?array
{
    // Quick view respects product/owner status and date availability, independently of search terms.
    $empty = catalog_filters([], []);
    $empty['start_date'] = $f['start_date'];
    $empty['end_date'] = $f['end_date'];
    [$sql, $params] = catalog_query($empty, $userId);
    $params['product_id'] = $id;
    $statement = db()->prepare('SELECT p.*, c.Name AS Category_Name, u.First_Name, u.Last_Name,
        (fav.Product_ID IS NOT NULL) AS Is_Favorite, (busy.Product_ID IS NULL) AS Is_Available' . $sql . ' AND p.Product_ID = :product_id');
    $statement->execute($params);
    return $statement->fetch() ?: null;
}

function catalog_favorite(int $userId, int $productId, bool $save): bool
{
    if ($save) {
        $statement = db()->prepare("INSERT INTO Favorites (User_ID, Product_ID)
            SELECT :viewer, p.Product_ID FROM Products p JOIN Users u ON u.User_ID = p.Owner_ID
            WHERE p.Product_ID = :product_id AND p.Status = 'active' AND u.Status = 'active'
            ON DUPLICATE KEY UPDATE Product_ID = VALUES(Product_ID)");
        $statement->execute(['viewer' => $userId, 'product_id' => $productId]);
        $check = db()->prepare('SELECT 1 FROM Favorites WHERE User_ID = :viewer AND Product_ID = :product_id');
        $check->execute(['viewer' => $userId, 'product_id' => $productId]);
        return (bool) $check->fetchColumn();
    }
    $statement = db()->prepare('DELETE FROM Favorites WHERE User_ID = :viewer AND Product_ID = :product_id');
    $statement->execute(['viewer' => $userId, 'product_id' => $productId]);
    return true;
}

function catalog_money(string|float $value): string
{
    $number = (float) $value;
    return number_format($number, floor($number) === $number ? 0 : 2, ',', ' ') . ' €';
}

function catalog_initial(string $name): string
{
    preg_match('/^./us', $name, $match);
    return $match[0] ?? '';
}

function catalog_image(?string $path): string
{
    // No remote URLs, parent-directory traversal, or user-controlled SVG assets.
    if ($path && preg_match('~^assets/images/products/[a-z0-9-]+\.(?:jpg|png|webp)$~D', $path)
        && is_file(dirname(__DIR__) . '/public/' . $path)) return $path;
    return 'assets/images/product-placeholder.svg';
}

function catalog_product_url(int $id, array $filters): string
{
    $query = ['id' => $id];
    if (!$filters['errors'] && $filters['start_date'] !== '' && $filters['end_date'] !== '') {
        $query['start_date'] = $filters['start_date'];
        $query['end_date'] = $filters['end_date'];
    }
    return 'product.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/catalog.php';
require dirname(__DIR__) . '/app/product.php';

$errors = [];
$message = '';
$receipt = null;
try {
    $user = current_user();
    if (!$user) redirect('login.php');
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        http_response_code(405);
        exit;
    }
    $id = product_id($_GET['id'] ?? null);
    if (!$id) product_not_found($user);
    $source = $method === 'POST' ? $_POST : $_GET;
    $defaults = $method === 'GET' && !array_key_exists('start_date', $source) && !array_key_exists('end_date', $source);
    $dates = product_dates($source, $defaults);
    $partialSelection = $method === 'GET' && ($_GET['selection'] ?? null) === 'end'
        && is_string($_GET['start_date'] ?? null) && catalog_date($_GET['start_date'])
        && $_GET['start_date'] >= catalog_today() && ($_GET['end_date'] ?? null) === '';
    $filters = catalog_filters([], []);
    if (!$dates['errors']) {
        $filters['start_date'] = $dates['start_date'];
        $filters['end_date'] = $dates['end_date'];
    }
    $product = catalog_product($id, $filters, (int) $user['User_ID']);
    if (!$product) product_not_found($user);
    $month = product_month($_GET['month'] ?? ($_POST['month'] ?? null), $dates);

    if ($method === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(419);
            $errors[] = 'Formas sesija ir beigusies. Atjaunojiet lapu un mēģiniet vēlreiz.';
        } else {
            $action = post_string('action');
            if ($action === 'check') {
                if (!$dates['errors']) redirect(product_url($id, $dates, ['month' => $month->format('Y-m')]));
            } elseif ($action === 'reserve') {
                $result = product_reserve($id, (int) $user['User_ID'], $_POST, post_string('request_key'));
                if ($result['ok']) {
                    $_SESSION['product_message'] = ['product' => $id, 'text' => $result['replayed']
                        ? 'Šis pieprasījums jau apstrādāts. Parādīta esošā demo rezervācija.'
                        : 'Demo rezervācija saglabāta. Nekāds maksājums nav veikts.'];
                    redirect(product_url($id, $dates, ['reservation' => $result['id']]));
                }
                http_response_code($result['status']);
                $errors[] = $result['error'];
            } elseif ($action === 'cancel') {
                $reservationId = product_id($_POST['reservation_id'] ?? null);
                if (!$reservationId || !product_cancel($reservationId, $id, (int) $user['User_ID'])) {
                    http_response_code(404);
                    $errors[] = 'Rezervācija nav atrasta.';
                } else {
                    $_SESSION['product_message'] = ['product' => $id, 'text' => 'Demo rezervācija atcelta. Datumi atkal ir brīvi, ja nav citu kalendāra ierakstu.'];
                    redirect(product_url($id, [], ['reservation' => $reservationId]));
                }
            } elseif ($action === 'favorite' && in_array(post_string('save'), ['0', '1'], true)) {
                $saved = post_string('save') === '1';
                if (catalog_favorite((int) $user['User_ID'], $id, $saved)) {
                    $_SESSION['product_message'] = ['product' => $id, 'text' => $saved ? 'Prece saglabāta izlasē.' : 'Prece noņemta no izlases.'];
                    redirect(product_url($id, $dates, ['month' => $month->format('Y-m')]));
                }
                http_response_code(404);
                $errors[] = 'Šī prece vairs nav pieejama.';
            } else {
                http_response_code(422);
                $errors[] = 'Nezināms pieprasījums.';
            }
        }
    }
    $errors = array_values(array_unique(array_merge($errors, $partialSelection ? [] : $dates['errors'])));
    if ($errors && http_response_code() < 400) http_response_code(422);
    if ($method === 'GET' && isset($_GET['reservation'])) {
        $reservationId = product_id($_GET['reservation']);
        $receipt = $reservationId ? product_reservation($reservationId, $id, (int) $user['User_ID']) : null;
        if (!$receipt) {
            http_response_code(404);
            $errors[] = 'Šī rezervācija nav atrasta jūsu kontā.';
        }
    }
    $flash = $_SESSION['product_message'] ?? null;
    if ($method === 'GET' && is_array($flash) && ($flash['product'] ?? null) === $id) {
        $message = (string) $flash['text'];
        unset($_SESSION['product_message']);
    }
    $details = product_details($id);
    $quote = product_quote($product, $dates);
    $available = $quote ? product_available($id, $dates['start_date'], $dates['end_date']) : null;
    $calendar = product_calendar($id, $month);
    $myReservations = product_my_reservations($id, (int) $user['User_ID']);
} catch (Throwable $exception) {
    error_log('NOMA product: ' . $exception->getMessage());
    service_error();
}
$selfOwned = (int) $product['Owner_ID'] === (int) $user['User_ID'];
$requestKey = bin2hex(random_bytes(32));
$previousMonth = $month->format('Y-m') > substr(catalog_today(), 0, 7) ? $month->modify('-1 month')->format('Y-m') : null;
$nextMonth = $month->format('Y-m') < '9999-12' ? $month->modify('+1 month')->format('Y-m') : null;
$calendarCells = array_merge(array_fill(0, (int) $month->format('N') - 1, null), $calendar);
while (count($calendarCells) % 7 !== 0) $calendarCells[] = null;
$ownerName = $product['First_Name'] . ' ' . catalog_initial($product['Last_Name']) . '.';
?>
<!doctype html>
<html lang="lv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2141b1">
    <title><?= e($product['Name']) ?> · NOMA.LV</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/css/catalog.css">
    <link rel="stylesheet" href="assets/css/product.css">
    <script src="assets/js/product.js" defer></script>
</head>
<body>
<a class="skip-link" href="#rental-form">Pāriet uz nomas datumiem</a>
<?php site_header($user); ?>
<div class="product-breadcrumbs"><nav class="container" aria-label="Atrašanās vieta">
    <a href="catalog.php">Preču katalogs</a><?= icon('chevron') ?>
    <span aria-current="page"><?= e($product['Name']) ?></span>
    <span class="prototype-tag">Mācību prototips</span>
</nav></div>

<main class="container product-main">
    <?php if ($message !== ''): ?>
    <div class="notice product-notice" role="status"><?= icon('check') ?><span><?= e($message) ?></span></div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="notice notice-error product-notice" role="alert"><div><strong>Pārbaudiet pieprasījumu</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>

    <?php if ($receipt): ?>
    <section class="reservation-receipt" aria-labelledby="receipt-title">
        <div class="receipt-icon"><?= icon($receipt['Status'] === 'demo_confirmed' ? 'check' : 'repeat') ?></div>
        <div class="receipt-body">
            <p class="eyebrow">Demonstrācijas rezervācija · #NM-<?= str_pad((string) $receipt['Reservation_ID'], 5, '0', STR_PAD_LEFT) ?></p>
            <h2 id="receipt-title"><?= $receipt['Status'] === 'demo_confirmed' ? 'Demo rezervācija apstiprināta' : 'Demo rezervācija atcelta' ?></h2>
            <p><?= e(product_date_label($receipt['Start_Date'])) ?> — <?= e(product_date_label($receipt['End_Date'])) ?>
                <span class="receipt-divider">·</span> Noma <?= e(product_amount(product_cents($receipt['Rental_Price']))) ?>
                <span class="receipt-divider">+</span> drošības nauda <?= e(product_amount(product_cents($receipt['Deposit']))) ?>
                <span class="receipt-divider">=</span> <strong><?= e(product_amount(product_cents($receipt['Total_Price']))) ?></strong></p>
            <small>Šis ir testa ieraksts. Maksājums netiek veikts, līgums netiek slēgts un paziņojums iznomātājam netiek sūtīts.</small>
        </div>
        <?php if ($receipt['Status'] === 'demo_confirmed'): ?>
        <form method="post" action="<?= e(product_url($id)) ?>" class="receipt-action">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="reservation_id" value="<?= (int) $receipt['Reservation_ID'] ?>">
            <button type="submit" class="button button-secondary">Atcelt demo rezervāciju</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="product-layout">
        <div class="product-column">
            <section class="product-gallery" aria-label="Preces attēls">
                <div class="main-photo">
                    <span class="photo-badge"><?= icon('info') ?>Demonstrācijas prece</span>
                    <a id="product-photo-link" href="<?= e(catalog_image($product['Image_Path'])) ?>" target="_blank" rel="noopener" aria-label="Palielināt preces attēlu">
                        <img src="<?= e(catalog_image($product['Image_Path'])) ?>" alt="<?= e($product['Name']) ?>" width="900" height="650" fetchpriority="high">
                        <span class="photo-zoom"><?= icon('zoom') ?></span>
                    </a>
                    <span class="photo-count">1 / 1 attēls</span>
                </div>
                <div class="gallery-caption"><span>Ilustratīvs foto</span><span><?= icon('zoom') ?>Nospiediet, lai palielinātu</span></div>
            </section>

            <section class="product-section product-description" aria-labelledby="product-title">
                <div class="description-top"><span class="category-pill"><?= e($product['Category_Name']) ?></span><span>Artikuls: NOMA-<?= str_pad((string) $id, 4, '0', STR_PAD_LEFT) ?></span></div>
                <h1 id="product-title"><?= e($product['Name']) ?></h1>
                <p class="product-description-text"><?= nl2br(e($product['Description'])) ?></p>

                <?php if ($details['specs']): ?>
                <div class="spec-highlights">
                    <?php foreach (array_slice($details['specs'], 0, 4, true) as $label => $value): ?>
                    <div><?= icon('tools') ?><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <h2><?= icon('filter') ?>Tehniskie parametri</h2>
                <dl class="spec-table">
                    <?php foreach ($details['specs'] as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
                </dl>
                <p class="spec-note">Parametri un komplektācija ir prototipa demonstrācijas dati.</p>
                <?php endif; ?>

                <div class="condition-grid">
                    <div><h2><?= icon('check') ?>Preces stāvoklis</h2><strong><?= e(CATALOG_CONDITIONS[$product['Item_Condition']]) ?></strong><p><?= e($details['Condition_Notes']) ?></p></div>
                    <div><h2><?= icon('document') ?>Nomas nosacījumi</h2><p><?= nl2br(e($details['Rental_Terms'])) ?></p></div>
                </div>
            </section>

            <section class="product-section calendar-section" aria-labelledby="calendar-title">
                <div class="section-heading"><div><h2 id="calendar-title"><?= icon('calendar') ?>Pieejamības kalendārs</h2><p id="calendar-instruction" aria-live="polite">Izvēlieties sākumu un pēc tam atgriešanas datumu.</p></div>
                    <div class="calendar-legend"><span><i class="legend-free"></i>Brīvs</span><span><i class="legend-busy"></i>Aizņemts</span><span><i class="legend-selected"></i>Izvēlēts</span></div>
                </div>
                <div class="calendar-box">
                    <div class="calendar-top"><h3><?= $month->format('Y') ?>. gada <?= e(PRODUCT_MONTHS[(int) $month->format('n')]) ?></h3><nav aria-label="Kalendāra mēneši">
                        <?php if ($previousMonth): ?><a class="month-link previous" href="<?= e(product_url($id, $dates, ['month' => $previousMonth])) ?>" data-month="<?= e($previousMonth) ?>" aria-label="Iepriekšējais mēnesis"><?= icon('chevron') ?></a><?php else: ?><span class="month-disabled previous" aria-hidden="true"><?= icon('chevron') ?></span><?php endif; ?>
                        <?php if ($nextMonth): ?><a class="month-link" href="<?= e(product_url($id, $dates, ['month' => $nextMonth])) ?>" data-month="<?= e($nextMonth) ?>" aria-label="Nākamais mēnesis"><?= icon('chevron') ?></a><?php endif; ?>
                    </nav></div>
                    <table class="availability-calendar"><caption class="sr-only"><?= e(PRODUCT_MONTHS[(int) $month->format('n')]) ?> <?= $month->format('Y') ?></caption><thead><tr><?php foreach (['P', 'O', 'T', 'C', 'Pk', 'S', 'Sv'] as $dayName): ?><th scope="col"><?= e($dayName) ?></th><?php endforeach; ?></tr></thead><tbody>
                        <?php foreach (array_chunk($calendarCells, 7) as $week): ?><tr><?php foreach ($week as $day): ?><td>
                            <?php if ($day): $selected = !$dates['errors'] && $day['date'] >= $dates['start_date'] && $day['date'] < $dates['end_date']; $returnDay = !$dates['errors'] && $day['date'] === $dates['end_date']; ?>
                            <button type="button" class="calendar-day <?= $day['busy'] ? 'day-busy' : 'day-free' ?> <?= $day['past'] ? 'day-past' : '' ?> <?= $selected ? 'day-selected' : '' ?> <?= $returnDay ? 'day-return' : '' ?>" data-date="<?= e($day['date']) ?>" data-past="<?= $day['past'] ? '1' : '0' ?>" data-busy="<?= $day['busy'] ? '1' : '0' ?>" aria-pressed="<?= $selected || $returnDay ? 'true' : 'false' ?>" aria-label="<?= e(product_date_label($day['date']) . ($day['past'] ? ' — pagājis datums' : ($day['busy'] ? ' — aizņemts' : ' — brīvs'))) ?>" disabled><?= $day['number'] ?></button>
                            <?php endif; ?>
                        </td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody></table>
                </div>
                <p class="calendar-footnote">Atgriešanas diena nav nomas diena. To apzīmē kontūra. Pieejamību atkārtoti pārbauda rezervējot.</p>
                <noscript><p class="small-note">Lai izvēlētos periodu, ievadiet datumus nomas formā. Mēnešus var pārslēgt ar bultiņām.</p></noscript>
            </section>

            <section class="product-section owner-section" aria-labelledby="owner-title">
                <h2 id="owner-title"><?= icon('user') ?>Iznomātāja informācija</h2>
                <div class="owner-profile"><span class="owner-avatar"><?= e(catalog_initial($product['First_Name']) . catalog_initial($product['Last_Name'])) ?></span><div><strong><?= e($ownerName) ?></strong><span><?= icon('pin') ?><?= e($product['City']) ?></span></div><span class="category-pill">Demo sludinājums</span></div>
                <p class="pickup-info"><?= icon('package') ?><?= e($details['Pickup_Info']) ?></p>
            </section>
        </div>

        <aside class="rental-sidebar" aria-label="Nomas datumi un cenas aprēķins">
            <section class="rental-panel">
                <div class="rental-price"><strong><?= e(catalog_money($product['Price'])) ?></strong><span>/ dienā</span><span class="demo-pill">DEMO</span></div>
                <div class="deposit-info"><?= icon('shield') ?><div><strong>Drošības nauda: <?= e(catalog_money($product['Deposit'])) ?></strong><p>Parādīta aprēķinā. Nauda netiek iekasēta.</p></div></div>
                <form id="rental-form" method="post" action="<?= e(product_url($id)) ?>"
                    data-price-cents="<?= product_cents($product['Price']) ?>" data-deposit-cents="<?= product_cents($product['Deposit']) ?>"
                    data-today="<?= e(catalog_today()) ?>" data-self-owned="<?= $selfOwned ? '1' : '0' ?>" data-selecting-end="<?= $partialSelection ? '1' : '0' ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="request_key" value="<?= e($requestKey) ?>">
                    <input type="hidden" name="month" value="<?= e($month->format('Y-m')) ?>">
                    <div class="rental-field"><label for="rental-start">Nomas sākums</label><input id="rental-start" type="date" name="start_date" min="<?= e(catalog_today()) ?>" max="9999-12-30" value="<?= e($dates['start_date']) ?>" required></div>
                    <div class="rental-field"><label for="rental-end">Nomas beigas (atgriešana)</label><input id="rental-end" type="date" name="end_date" min="<?= e(catalog_today()) ?>" max="9999-12-31" value="<?= e($dates['end_date']) ?>" required></div>
                    <p class="availability-message <?= $available === true ? 'available' : ($available === false ? 'unavailable' : 'pending') ?>" id="availability-message" aria-live="polite"><?= icon($available ? 'check' : 'info') ?><span id="availability-text"><?= $available === true ? 'Prece izvēlētajā periodā ir pieejama.' : ($available === false ? 'Prece izvēlētajā periodā ir aizņemta.' : 'Izvēlieties derīgu nomas periodu.') ?></span></p>
                    <dl class="price-summary" aria-live="polite">
                        <div><dt>Nomas ilgums</dt><dd><span id="quote-days"><?= $quote ? $quote['days'] : '—' ?></span> d.</dd></div>
                        <div><dt>Nomas maksa</dt><dd id="quote-rent"><?= $quote ? e(product_amount($quote['rent'])) : '—' ?></dd></div>
                        <div><dt>Drošības nauda</dt><dd><?= e(product_amount(product_cents($product['Deposit']))) ?></dd></div>
                        <div class="quote-total"><dt>Kopā <small>Ieskaitot drošības naudu</small></dt><dd id="quote-total"><?= $quote ? e(product_amount($quote['total'])) : '—' ?></dd></div>
                    </dl>
                    <p class="period-caption" id="period-caption"><?= $quote ? e(product_date_label($dates['start_date']) . ' — ' . product_date_label($dates['end_date'])) : 'Sākums → atgriešana' ?></p>
                    <button class="button reserve-button" id="reserve-button" type="submit" name="action" value="reserve" <?= $selfOwned || !$available || !$quote ? 'disabled' : '' ?>>Rezervēt (demo) <?= icon('arrow') ?></button>
                    <button class="button button-secondary check-availability" type="submit" name="action" value="check"><?= icon('calendar') ?>Pārbaudīt pieejamību</button>
                    <?php if ($selfOwned): ?><p class="self-owned-note">Šī ir jūsu prece. To var rezervēt cits lietotājs.</p><?php endif; ?>
                </form>
                <form action="<?= e(product_url($id, $dates)) ?>" method="post" class="product-favorite">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="favorite">
                    <input type="hidden" name="save" value="<?= $product['Is_Favorite'] ? '0' : '1' ?>">
                    <input type="hidden" name="start_date" value="<?= e($dates['start_date']) ?>">
                    <input type="hidden" name="end_date" value="<?= e($dates['end_date']) ?>">
                    <button type="submit" aria-pressed="<?= $product['Is_Favorite'] ? 'true' : 'false' ?>" class="<?= $product['Is_Favorite'] ? 'saved' : '' ?>"><?= icon('heart') ?><?= $product['Is_Favorite'] ? 'Saglabāts izlasē' : 'Saglabāt izlasē' ?></button>
                </form>
                <p class="prototype-note"><?= icon('info') ?><span>Testa rezervācija vietējā datubāzē. Šajā prototipā netiek veikti maksājumi vai slēgti līgumi.</span></p>
            </section>

            <?php if ($myReservations): ?>
            <section class="my-product-bookings" aria-labelledby="my-bookings-title"><h2 id="my-bookings-title"><?= icon('calendar') ?>Manas demo rezervācijas</h2><p>Šai precei saglabātie aktīvie periodi.</p>
                <?php foreach ($myReservations as $booking): ?>
                <div class="mini-booking"><a href="<?= e(product_url($id, ['start_date' => $booking['Start_Date'], 'end_date' => $booking['End_Date']], ['reservation' => $booking['Reservation_ID']])) ?>"><strong>#NM-<?= str_pad((string) $booking['Reservation_ID'], 5, '0', STR_PAD_LEFT) ?></strong><span><?= e(product_date_label($booking['Start_Date']) . ' — ' . product_date_label($booking['End_Date'])) ?></span></a>
                    <form action="<?= e(product_url($id)) ?>" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="reservation_id" value="<?= (int) $booking['Reservation_ID'] ?>"><button type="submit">Atcelt</button></form>
                </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
        </aside>
    </div>
</main>
<?php site_footer(); ?>
<dialog id="image-dialog" class="image-dialog" aria-label="Palielināts preces attēls">
    <button type="button" id="image-dialog-close" class="dialog-close" aria-label="Aizvērt attēlu"><?= icon('close') ?></button>
    <img src="<?= e(catalog_image($product['Image_Path'])) ?>" alt="<?= e($product['Name']) ?>" width="900" height="650">
</dialog>
</body>
</html>

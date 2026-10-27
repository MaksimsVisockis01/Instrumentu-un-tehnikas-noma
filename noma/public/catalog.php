<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/catalog.php';

$message = '';
$quickView = null;
try {
    $user = current_user();
    if (!$user) redirect('login.php');
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        http_response_code(405);
        exit;
    }
    $categories = catalog_categories();
    $filters = catalog_filters($_GET, $categories);
    if ($method === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            $filters['errors'][] = 'Formas sesija ir beigusies. Lūdzu, mēģiniet vēlreiz.';
            http_response_code(419);
        } elseif (post_string('action') !== 'favorite'
            || !preg_match('/^[1-9]\d{0,17}$/D', post_string('product_id'))
            || !in_array(post_string('save'), ['0', '1'], true)) {
            $filters['errors'][] = 'Neizdevās apstrādāt saglabāšanas pieprasījumu.';
            http_response_code(422);
        } elseif (!$filters['errors']) {
            $save = post_string('save') === '1';
            if (catalog_favorite((int) $user['User_ID'], (int) post_string('product_id'), $save)) {
                $_SESSION['catalog_message'] = $save ? 'Prece pievienota saglabātajām.' : 'Prece noņemta no saglabātajām.';
                redirect(catalog_url($filters));
            }
            http_response_code(404);
            $filters['errors'][] = 'Šī prece vairs nav pieejama katalogā.';
        }
    } elseif (isset($_SESSION['catalog_message'])) {
        $message = is_string($_SESSION['catalog_message']) ? $_SESSION['catalog_message'] : '';
        unset($_SESSION['catalog_message']);
    }
    if ($filters['errors'] && http_response_code() < 400) http_response_code(422);
    $result = catalog_results($filters, (int) $user['User_ID']);
    $filters['page'] = $result['page'];
    if ($method === 'GET' && isset($_GET['view']) && !$filters['errors']) {
        if (is_string($_GET['view']) && preg_match('/^[1-9]\d{0,17}$/D', $_GET['view'])) {
            $quickView = catalog_product((int) $_GET['view'], $filters, (int) $user['User_ID']);
        }
        if (!$quickView) {
            http_response_code(404);
            $message = 'Šī prece nav atrasta vai vairs nav pieejama katalogā.';
        }
    }
} catch (Throwable $exception) {
    error_log('NOMA catalog: ' . $exception->getMessage());
    service_error();
}

$selectedCategory = count($filters['categories']) === 1 ? $filters['categories'][0] : '';
$hasDates = $filters['start_date'] !== '' && $filters['end_date'] !== '';
$availabilityText = $hasDates ? 'Pieejams šajā periodā' : 'Pieejams šodien';
$unavailableText = $hasDates ? 'Aizņemts šajā periodā' : 'Šodien nav pieejams';
$sliderMax = max(50, (int) ceil((float) ($filters['max_price'] ?: 50)));
$currentUrl = catalog_url($filters);
?>
<!doctype html>
<html lang="lv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Atrodiet instrumentus un tehniku nomai NOMA.LV katalogā.">
    <meta name="theme-color" content="#2141b1">
    <title>Meklēt preces · NOMA.LV</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/css/catalog.css">
    <script src="assets/js/catalog.js" defer></script>
</head>
<body>
<a class="skip-link" href="#results">Pāriet uz precēm</a>
<?php site_header($user); ?>
<div class="platform-strip"><div class="container strip-inner"><span><?= icon('tools') ?><strong>P2P Nomas Platforma NOMA.LV</strong><span class="strip-divider">·</span><span class="strip-caption">Atrodi vajadzīgo. Iznomā uz laiku.</span></span><span class="demo-label">Mācību prototips · Demo dati</span></div></div>

<main class="container main">
    <section class="search-panel" aria-label="Preču meklēšana">
        <form id="catalog-filters" action="catalog.php" method="get" class="search-grid">
            <div class="search-field"><label for="q"><?= icon('search') ?>Meklēt preci</label><input id="q" name="q" type="search" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="piem., perforators, kamera, VR…"></div>
            <div class="search-field"><label for="category-picker"><?= icon('tools') ?>Kategorija</label><select id="category-picker" disabled aria-describedby="category-help"><option value="">Visas kategorijas</option><?php foreach ($categories as $category): ?><option value="<?= e($category['Slug']) ?>" <?= $selectedCategory === $category['Slug'] ? 'selected' : '' ?>><?= e($category['Name']) ?></option><?php endforeach; ?><option value="multiple" disabled <?= count($filters['categories']) > 1 ? 'selected' : '' ?>>Vairākas kategorijas</option></select><span id="category-help" class="sr-only">Vairākas kategorijas var izvēlēties filtros.</span></div>
            <div class="search-field"><label for="start_date"><?= icon('calendar') ?>Nomas sākums</label><input id="start_date" name="start_date" type="date" min="<?= e(catalog_today()) ?>" value="<?= e($filters['start_date']) ?>"></div>
            <div class="search-field"><label for="end_date"><?= icon('calendar') ?>Nomas beigas</label><input id="end_date" name="end_date" type="date" min="<?= e(catalog_today()) ?>" value="<?= e($filters['end_date']) ?>"></div>
            <button type="submit" class="button search-button"><?= icon('search') ?>Meklēt</button>
        </form>
        <div class="popular"><span>Populāri vaicājumi:</span><?php foreach (['Perforators', 'PlayStation 5', 'Kärcher K5', 'VR brilles', 'Makita'] as $term): ?><a href="<?= e(catalog_url(catalog_filters([], $categories), ['q' => $term])) ?>"><?= e($term) ?></a><?php endforeach; ?></div>
        <noscript><p class="small-note">Kategorijas izvēlieties filtros zemāk. Meklēšana un saglabāšana darbojas arī bez JavaScript.</p></noscript>
    </section>

    <?php if ($message !== ''): ?><div class="notice" role="status"><?= icon('info') ?><span><?= e($message) ?></span></div><?php endif; ?>
    <?php if ($filters['errors']): ?><div class="notice notice-error" role="alert"><div><strong>Pārbaudiet meklēšanas nosacījumus</strong><ul><?php foreach ($filters['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>

    <div class="catalog-layout">
        <aside class="sidebar" aria-label="Kataloga filtri">
            <section class="filter-panel">
                <div class="filter-heading"><h2><?= icon('filter') ?>Filtri</h2><a href="catalog.php">Notīrīt visu</a></div>
                <fieldset><legend>Kategorija</legend>
                    <?php foreach ($categories as $category): ?>
                    <label class="check-row"><input form="catalog-filters" type="checkbox" name="categories[]" value="<?= e($category['Slug']) ?>" <?= in_array($category['Slug'], $filters['categories'], true) ? 'checked' : '' ?>><span><?= e($category['Name']) ?></span><small><?= (int) $category['Product_Count'] ?></small></label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset><legend>Cena dienā (€)</legend>
                    <div class="price-range"><label><span>No</span><input form="catalog-filters" name="min_price" id="min_price" type="number" min="0" max="999999.99" step="0.01" value="<?= e($filters['min_price']) ?>" placeholder="0" aria-label="Minimālā cena dienā eiro"></label><span>–</span><label><span>Līdz</span><input form="catalog-filters" name="max_price" id="max_price" type="number" min="0" max="999999.99" step="0.01" value="<?= e($filters['max_price']) ?>" placeholder="Visas" aria-label="Maksimālā cena dienā eiro"></label></div>
                    <input class="price-slider" id="price-slider" type="range" min="0" max="<?= $sliderMax ?>" step="1" value="<?= e($filters['max_price'] !== '' ? $filters['max_price'] : (string) $sliderMax) ?>" aria-label="Izvēlēties maksimālo dienas cenu" disabled>
                    <div class="range-caption"><span>0 €</span><span id="price-caption"><?= $filters['max_price'] !== '' ? e(catalog_money($filters['max_price'])) : 'Bez ierobežojuma' ?></span></div>
                </fieldset>
                <fieldset><legend>Pieejamība</legend>
                    <label class="check-row"><input form="catalog-filters" type="radio" name="availability" value="all" <?= $filters['availability'] === 'all' ? 'checked' : '' ?>><span>Visas preces</span></label>
                    <label class="check-row"><input form="catalog-filters" type="radio" name="availability" value="available" <?= $filters['availability'] === 'available' ? 'checked' : '' ?>><span><?= $hasDates ? 'Tikai izvēlētajā periodā' : 'Tikai pieejamās šodien' ?></span></label>
                    <p class="filter-hint">Bez datumiem pārbaudām šodienu. Atgriešanas diena periodā neietilpst.</p>
                </fieldset>
                <fieldset><legend>Preces stāvoklis</legend>
                    <?php foreach (CATALOG_CONDITIONS as $value => $label): ?><label class="check-row"><input form="catalog-filters" type="checkbox" name="conditions[]" value="<?= e($value) ?>" <?= in_array($value, $filters['conditions'], true) ? 'checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?>
                </fieldset>
                <fieldset><legend>Mana izlase</legend><label class="check-row"><input form="catalog-filters" type="checkbox" name="favorites" value="1" <?= $filters['favorites'] === '1' ? 'checked' : '' ?>><span>Tikai saglabātās preces</span><?= icon('heart') ?></label></fieldset>
                <button class="button filter-apply" form="catalog-filters" type="submit"><?= icon('filter') ?>Lietot filtrus</button>
                <a class="clear-filters" href="catalog.php">Notīrīt filtrus</a>
            </section>
            <div class="help-card"><?= icon('info') ?><h3>Vajadzīgs tikai uz laiku?</h3><p>Atrodi instrumentus un tehniku vienuviet. Izvēlies datumus un pārbaudi pieejamību.</p><small>Šeit redzami demonstrācijas sludinājumi.</small></div>
        </aside>

        <section class="results" id="results" aria-labelledby="results-title">
            <div class="results-toolbar"><div><h1 id="results-title"><?= $filters['favorites'] === '1' ? 'Saglabātās preces' : 'Instrumenti un tehnika' ?><span><?= $result['total'] ?></span></h1><p><?= $filters['errors'] ? 'Izlabojiet filtrus, lai redzētu rezultātus.' : ($hasDates ? e($filters['start_date']) . ' — ' . e($filters['end_date']) : 'Atrodi īsto palīgu savam nākamajam darbam.') ?></p></div><div class="sort-field"><label for="sort">Kārtot pēc:</label><select form="catalog-filters" id="sort" name="sort"><?php foreach (CATALOG_SORTS as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>

            <?php if (!$result['items']): ?>
            <div class="empty-state"><?= icon('search') ?><h2><?= $filters['errors'] ? 'Pārbaudiet filtrus' : 'Neviena prece nav atrasta' ?></h2><p><?= $filters['errors'] ? 'Kļūdu apraksts ir redzams virs kataloga.' : 'Pamēģiniet citu nosaukumu, plašāku cenu diapazonu vai notīriet filtrus.' ?></p><a class="button" href="catalog.php">Rādīt visas preces <?= icon('arrow') ?></a></div>
            <?php else: ?>
            <div class="product-grid">
            <?php foreach ($result['items'] as $item): ?>
                <article class="product-card" data-product-id="<?= (int) $item['Product_ID'] ?>">
                    <div class="product-photo"><a href="<?= e(catalog_product_url((int) $item['Product_ID'], $filters)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(catalog_image($item['Image_Path'])) ?>" alt="" width="600" height="450" loading="lazy"></a><span class="availability <?= $item['Is_Available'] ? '' : 'is-busy' ?>"><i></i><?= $item['Is_Available'] ? e($availabilityText) : e($unavailableText) ?></span>
                        <form method="post" action="<?= e($currentUrl) ?>" class="favorite-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="favorite"><input type="hidden" name="product_id" value="<?= (int) $item['Product_ID'] ?>"><input type="hidden" name="save" value="<?= $item['Is_Favorite'] ? '0' : '1' ?>"><button type="submit" class="favorite-button <?= $item['Is_Favorite'] ? 'is-saved' : '' ?>" aria-pressed="<?= $item['Is_Favorite'] ? 'true' : 'false' ?>" aria-label="<?= e(($item['Is_Favorite'] ? 'Noņemt no saglabātajām: ' : 'Saglabāt: ') . $item['Name']) ?>"><?= icon('heart') ?></button></form>
                    </div>
                    <div class="product-content"><div class="category-line"><p class="product-category"><?= e($item['Category_Name']) ?></p><a class="quick-link" href="<?= e(catalog_url($filters, ['view' => $item['Product_ID']])) ?>" aria-label="<?= e('Ātrais apskats: ' . $item['Name']) ?>"><?= icon('eye') ?></a></div><h2><a href="<?= e(catalog_product_url((int) $item['Product_ID'], $filters)) ?>"><?= e($item['Name']) ?></a></h2><p class="owner"><?= icon('user') ?><span>Iznomātājs: <strong><?= e($item['First_Name'] . ' ' . catalog_initial($item['Last_Name']) . '.') ?></strong></span><span class="city"><?= e($item['City']) ?></span></p></div>
                    <div class="product-bottom"><div><p class="daily-price"><strong><?= e(catalog_money($item['Price'])) ?></strong><span>/ dienā</span></p><p class="deposit">Drošības nauda: <?= e(catalog_money($item['Deposit'])) ?></p></div><a class="button view-button" href="<?= e(catalog_product_url((int) $item['Product_ID'], $filters)) ?>" aria-label="<?= e('Skatīt: ' . $item['Name']) ?>">Skatīt <?= icon('chevron') ?></a></div>
                </article>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($result['pages'] > 1): ?><nav class="pagination" aria-label="Rezultātu lapas">
                <?php if ($result['page'] > 1): ?><a href="<?= e(catalog_url($filters, ['page' => $result['page'] - 1])) ?>">← Iepriekšējā</a><?php endif; ?>
                <span><?= $result['page'] ?>. no <?= $result['pages'] ?> lapām</span>
                <?php if ($result['page'] < $result['pages']): ?><a href="<?= e(catalog_url($filters, ['page' => $result['page'] + 1])) ?>">Nākamā →</a><?php endif; ?>
            </nav><?php endif; ?>
            <p class="results-note"><?= icon('info') ?>Pieejamība attiecas uz norādīto periodu. Demo rezervāciju var izveidot preces lapā.</p>
        </section>
    </div>
</main>
<?php site_footer(); ?>

<?php if ($quickView): ?>
<dialog class="quick-view" open aria-labelledby="quick-view-title" id="quick-view">
    <a class="dialog-close" href="<?= e($currentUrl) ?>" id="quick-view-close" aria-label="Aizvērt preces apskatu"><?= icon('close') ?></a>
    <div class="quick-view-photo"><img src="<?= e(catalog_image($quickView['Image_Path'])) ?>" alt="<?= e($quickView['Name']) ?>" width="600" height="450"></div>
    <div class="quick-view-content"><p class="product-category"><?= e($quickView['Category_Name']) ?></p><h2 id="quick-view-title"><?= e($quickView['Name']) ?></h2><p class="quick-status <?= $quickView['Is_Available'] ? '' : 'is-busy' ?>"><?= icon($quickView['Is_Available'] ? 'check' : 'calendar') ?><?= $quickView['Is_Available'] ? e($availabilityText) : e($unavailableText) ?></p><p class="description"><?= nl2br(e($quickView['Description'])) ?></p>
        <dl class="product-details"><div><dt>Cena dienā</dt><dd><?= e(catalog_money($quickView['Price'])) ?></dd></div><div><dt>Drošības nauda</dt><dd><?= e(catalog_money($quickView['Deposit'])) ?></dd></div><div><dt>Stāvoklis</dt><dd><?= e(CATALOG_CONDITIONS[$quickView['Item_Condition']] ?? $quickView['Item_Condition']) ?></dd></div><div><dt>Atrašanās vieta</dt><dd><?= e($quickView['City']) ?></dd></div><div><dt>Iznomātājs</dt><dd><?= e($quickView['First_Name'] . ' ' . catalog_initial($quickView['Last_Name']) . '.') ?></dd></div></dl>
        <p class="small-note">Demonstrācijas sludinājums. Datumus un cenu var pārbaudīt preces lapā.</p><a class="button" href="<?= e(catalog_product_url((int) $quickView['Product_ID'], $filters)) ?>">Atvērt preces lapu <?= icon('arrow') ?></a>
    </div>
</dialog>
<?php endif; ?>
</body>
</html>

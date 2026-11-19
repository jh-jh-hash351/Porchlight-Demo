<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/preferences.php';

$user = $_SESSION['user'] ?? null;
$meId = $user ? (int)$user['id'] : 0;
$prefs = get_preferences($pdo, $meId ?: null);

$savedArea = trim(implode(', ', array_filter([$prefs['city'] ?? '', $prefs['region'] ?? ''])));
$nearbyPlaces = [
    ['emoji' => '☕', 'name' => 'Cafés & coffee', 'hint' => 'Closest coffee shops', 'query' => 'cafe'],
    ['emoji' => '🥐', 'name' => 'Bakeries', 'hint' => 'Fresh bread nearby', 'query' => 'bakery'],
    ['emoji' => '🛒', 'name' => 'Groceries', 'hint' => 'Supermarkets and corner stores', 'query' => 'grocery store'],
    ['emoji' => '💊', 'name' => 'Pharmacies', 'hint' => 'Medicine close by', 'query' => 'pharmacy'],
    ['emoji' => '🍽️', 'name' => 'Restaurants', 'hint' => 'Where to eat nearby', 'query' => 'restaurant'],
    ['emoji' => '🌳', 'name' => 'Parks', 'hint' => 'Green spaces near you', 'query' => 'park'],
    ['emoji' => '🏥', 'name' => 'Hospitals & clinics', 'hint' => 'Urgent care nearby', 'query' => 'hospital'],
    ['emoji' => '⛽', 'name' => 'Gas stations', 'hint' => 'Fuel near you', 'query' => 'gas station'],
];

$nearbyHref = function (string $query) use ($savedArea): string {
    $term = $savedArea !== '' ? $query . ' near ' . $savedArea : $query . ' near me';
    return 'https://www.google.com/maps/search/' . rawurlencode($term);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Nearby - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="js/theme.js"></script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
                <span>Porchlight</span>
            </a>
            <div class="topbar-actions" style="margin-left: auto;">
                <?php if (!$user): ?>
                    <a href="login.php" class="btn btn-ghost btn-small">Sign In</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-ghost btn-small">Back to Feed</a>
                <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span>
                    <span class="icon-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </div>
    </header>

    <main class="explore-main">
        <div class="explore-head">
            <p class="policy-kicker">Around you</p>
            <h1 class="explore-title">Explore nearby</h1>
            <p class="explore-sub">
                See where you are and find the closest everyday spots. Pick a category and Google Maps opens
                results sorted from your location.
            </p>
        </div>

        <section class="panel explore-location">
            <div class="explore-location-copy">
                <span class="explore-location-icon"><?= icon('compass') ?></span>
                <div>
                    <strong id="exploreLocationTitle">Find what's close</strong>
                    <p id="exploreLocationStatus">
                        <?= $savedArea !== ''
                            ? 'Saved area: ' . security_encode($savedArea) . '. Allow location for exact results.'
                            : 'Allow location access to see the closest places around you.' ?>
                    </p>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="exploreLocateBtn"
                    data-saved-area="<?= security_encode($savedArea) ?>">Use my location</button>
        </section>

        <div class="explore-layout">
            <section class="panel explore-map-panel">
                <div class="explore-map-head">
                    <h2>Your area</h2>
                    <a id="exploreMapLink" href="https://www.google.com/maps" target="_blank" rel="noopener" hidden>Open in Google Maps</a>
                </div>
                <div class="explore-map-wrap">
                    <div class="explore-map-empty" id="exploreMapEmpty">
                        <?= icon('compass') ?>
                        <p>Allow location access to pin your area on the map.</p>
                    </div>
                    <iframe id="exploreMapFrame" title="Map of your nearby area" hidden
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </section>

            <section class="panel nearby-panel">
                <h2>Closest places</h2>
                <p class="nearby-sub">Each category opens nearby results in Google Maps.</p>
                <div class="nearby-list">
                    <?php foreach ($nearbyPlaces as $place): ?>
                        <a class="nearby-card" href="<?= security_encode($nearbyHref($place['query'])) ?>"
                           target="_blank" rel="noopener" data-nearby-query="<?= security_encode($place['query']) ?>">
                            <span class="nearby-emoji" aria-hidden="true"><?= $place['emoji'] ?></span>
                            <span class="nearby-meta">
                                <span class="nearby-name"><?= security_encode($place['name']) ?></span>
                                <span class="nearby-hint"><?= security_encode($place['hint']) ?></span>
                            </span>
                            <span class="nearby-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <p class="explore-note">
            Location is used only in your browser to center the map and nearby searches. Business results and
            distances are provided by Google Maps.
        </p>
    </main>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>

    <script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
</body>
</html>

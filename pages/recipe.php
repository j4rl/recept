<?php
declare(strict_types=1);

$recipeId = (int) ($_GET['id'] ?? 0);
$viewer = current_user();
$inventoryEnabled = $viewer && (int) $viewer['inventory_enabled'] === 1;

$recipeQuery = "
    SELECT
        r.id,
        r.user_id,
        r.title,
        r.description,
        r.image_path,
        r.instructions,
        r.prep_minutes,
        r.cook_minutes,
        r.servings,
        r.created_at,
        r.is_gluten_free,
        r.is_lactose_free,
        r.is_nut_free,
        u.name AS author_name,
        COALESCE(cat.categories, '') AS category_list,
        COALESCE(rt.avg_rating, 0) AS avg_rating,
        COALESCE(rt.rating_count, 0) AS rating_count
        " . ($viewer ? ', ur.rating AS user_rating' : ', NULL AS user_rating') . "
     FROM recipes r
     INNER JOIN users u ON u.id = r.user_id
     LEFT JOIN (
        SELECT
            rc.recipe_id,
            GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories
        FROM recipe_categories rc
        INNER JOIN categories c ON c.id = rc.category_id
        GROUP BY rc.recipe_id
     ) cat ON cat.recipe_id = r.id
     LEFT JOIN (
        SELECT
            recipe_id,
            AVG(rating) AS avg_rating,
            COUNT(*) AS rating_count
        FROM recipe_ratings
        GROUP BY recipe_id
     ) rt ON rt.recipe_id = r.id
     " . ($viewer ? 'LEFT JOIN recipe_ratings ur ON ur.recipe_id = r.id AND ur.user_id = ?' : '') . "
     WHERE r.id = ? AND r.is_published = 1
     LIMIT 1
";

if ($viewer) {
    $recipe = db_one($recipeQuery, 'ii', [(int) $viewer['id'], $recipeId]);
} else {
    $recipe = db_one($recipeQuery, 'i', [$recipeId]);
}

if (!$recipe) {
    http_response_code(404);
    $pageTitle = app_config('app_name') . ' - Recept saknas';
    ?>
    <section class="empty-card">
        <h1>Receptet hittades inte</h1>
        <p>Det kan ha tagits bort eller så är länken fel.</p>
        <a class="secondary-button" href="index.php">Till receptöversikten</a>
    </section>
    <?php
    return;
}

$pageTitle = app_config('app_name') . ' - ' . $recipe['title'];

if ($inventoryEnabled) {
    $ingredients = db_all(
        'SELECT
            i.id,
            i.name,
            ri.quantity,
            CASE WHEN COUNT(ui.ingredient_id) > 0 THEN 1 ELSE 0 END AS has_ingredient,
            GROUP_CONCAT(DISTINCT ui.location ORDER BY ui.location SEPARATOR ", ") AS locations
        FROM recipe_ingredients ri
        INNER JOIN ingredients i ON i.id = ri.ingredient_id
        LEFT JOIN user_inventory ui
            ON ui.ingredient_id = i.id
            AND ui.user_id = ?
        WHERE ri.recipe_id = ?
        GROUP BY i.id, i.name, ri.quantity
        ORDER BY i.name ASC',
        'ii',
        [(int) $viewer['id'], $recipeId]
    );
} else {
    $ingredients = db_all(
        'SELECT
            i.id,
            i.name,
            ri.quantity,
            0 AS has_ingredient,
            NULL AS locations
        FROM recipe_ingredients ri
        INNER JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ?
        ORDER BY i.name ASC',
        'i',
        [$recipeId]
    );
}

$relatedRecipes = db_all(
    'SELECT DISTINCT r2.id, r2.title, r2.description
     FROM recipes r2
     INNER JOIN recipe_categories rc2 ON rc2.recipe_id = r2.id
     WHERE r2.id <> ?
       AND r2.is_published = 1
       AND rc2.category_id IN (
            SELECT category_id
            FROM recipe_categories
            WHERE recipe_id = ?
       )
     ORDER BY r2.created_at DESC
     LIMIT 4',
    'ii',
    [$recipeId, $recipeId]
);

$haveCount = 0;
foreach ($ingredients as $ingredient) {
    $haveCount += (int) $ingredient['has_ingredient'];
}

$ingredientTotal = count($ingredients);
$completion = $ingredientTotal > 0 ? (int) floor(($haveCount / $ingredientTotal) * 100) : 0;
$missingCount = max(0, $ingredientTotal - $haveCount);

$avgRating = (float) $recipe['avg_rating'];
$ratingCount = (int) $recipe['rating_count'];
$ratingPercent = max(0, min(100, ($avgRating / 5) * 100));
$userRating = $viewer ? (int) ($recipe['user_rating'] ?? 0) : 0;
$recipeCategories = array_filter(array_map('trim', explode(',', (string) $recipe['category_list'])));
$recipeImage = recipe_image_url((string) ($recipe['image_path'] ?? ''));
$baseServings = max(1, (int) $recipe['servings']);
$canManageRecipe = can_manage_recipe($viewer, $recipe);
$isFavorite = false;
$googleKeepConnected = false;

if ($viewer) {
    $isFavorite = db_one(
        'SELECT 1 FROM recipe_favorites WHERE user_id = ? AND recipe_id = ? LIMIT 1',
        'ii',
        [(int) $viewer['id'], $recipeId]
    ) !== null;
    $googleKeepConnected = google_keep_is_configured() && google_keep_is_connected((int) $viewer['id']);
}

$locationMeta = [
    'pantry' => ['label' => 'Skafferi', 'icon' => 'assets/img/skaff.png'],
    'fridge' => ['label' => 'Kylskåp', 'icon' => 'assets/img/kyl.png'],
    'freezer' => ['label' => 'Frys', 'icon' => 'assets/img/frys.png'],
];
?>

<article class="recipe-detail">
    <header class="detail-head">
        <figure class="detail-image-wrap">
            <img src="<?= e($recipeImage) ?>" alt="<?= e($recipe['title']) ?>" class="detail-image" loading="lazy">
        </figure>

        <div>
            <?php if (count($recipeCategories) > 0): ?>
                <div class="badge-row">
                    <?php foreach ($recipeCategories as $categoryName): ?>
                        <span class="pill"><?= e($categoryName) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h1><?= e($recipe['title']) ?></h1>
            <p><?= e($recipe['description']) ?></p>

            <div class="rating-line rating-line-large">
                <span
                    class="star-rating"
                    role="img"
                    aria-label="Betyg <?= e(number_format($avgRating, 1, ',', ' ')) ?> av 5"
                >
                    <span class="star-rating-base">★★★★★</span>
                    <span class="star-rating-fill" style="width: <?= e(number_format($ratingPercent, 2, '.', '')) ?>%;">★★★★★</span>
                </span>
                <?php if ($ratingCount > 0): ?>
                    <span><?= e(number_format($avgRating, 1, ',', ' ')) ?>/5 baserat på <?= e((string) $ratingCount) ?> röster</span>
                <?php else: ?>
                    <span>Inga röster ännu</span>
                <?php endif; ?>
            </div>

            <div class="badge-row">
                <?php if ((int) $recipe['is_gluten_free'] === 1): ?>
                    <img src="assets/img/nogluten.png" alt="Glutenfri" title="Glutenfri" class="food-badge-icon" width="16" height="16" loading="lazy">
                <?php endif; ?>
                <?php if ((int) $recipe['is_lactose_free'] === 1): ?>
                    <img src="assets/img/nolactose.png" alt="Laktosfri" title="Laktosfri" class="food-badge-icon" width="16" height="16" loading="lazy">
                <?php endif; ?>
                <?php if ((int) $recipe['is_nut_free'] === 1): ?>
                    <img src="assets/img/nonut.png" alt="Utan nötter" title="Utan nötter" class="food-badge-icon" width="16" height="16" loading="lazy">
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-facts">
            <div><strong data-current-servings><?= e((string) $baseServings) ?></strong><span>port</span></div>
            <div><strong><?= e((string) minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes'])) ?></strong><span>min</span></div>
            <div><strong><?= e($recipe['author_name']) ?></strong><span>skapare</span></div>
        </div>
    </header>

    <?php if ($viewer): ?>
        <section class="recipe-action-panel">
            <form method="post" action="index.php">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_favorite">
                <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">
                <button type="submit" class="<?= $isFavorite ? 'danger-button' : 'secondary-button' ?>">
                    <?= $isFavorite ? 'Ta bort favorit' : 'Spara som favorit' ?>
                </button>
            </form>

            <form method="post" action="index.php" class="recipe-inline-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_meal_plan_item">
                <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">
                <label>
                    <span>Lägg i veckoplan</span>
                    <input type="date" name="planned_date" value="<?= e(date('Y-m-d')) ?>" required>
                </label>
                <button type="submit">Planera</button>
            </form>

            <form method="post" action="index.php">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_recipe_to_shopping_list">
                <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">
                <button type="submit">Lägg alla i inköpslista</button>
            </form>

            <?php if ($missingCount > 0): ?>
                <form method="post" action="index.php">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_recipe_to_shopping_list">
                    <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                    <input type="hidden" name="only_missing" value="1">
                    <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">
                    <button type="submit" class="secondary-button">Lägg saknade i inköpslista</button>
                </form>

                <?php if ($googleKeepConnected): ?>
                    <form method="post" action="index.php">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="send_missing_to_google_keep">
                        <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                        <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">
                        <button type="submit" class="secondary-button">Skicka saknade till Google Keep</button>
                    </form>
                <?php elseif (google_keep_is_configured()): ?>
                    <a href="index.php?page=keep_connect&amp;return_to=<?= e(rawurlencode('index.php?page=recipe&id=' . $recipeId)) ?>" class="secondary-button">Anslut Google Keep</a>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($canManageRecipe): ?>
        <section class="recipe-manage-panel">
            <div>
                <h2>Hantera recept</h2>
                <p>Du kan redigera eller ta bort det här receptet eftersom du är skapare eller administratör.</p>
            </div>
            <div class="recipe-manage-actions">
                <a href="index.php?page=edit&id=<?= e((string) $recipeId) ?>" class="secondary-button">Redigera recept</a>
                <form method="post" action="index.php" data-confirm="Ta bort receptet permanent?">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_recipe">
                    <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                    <input type="hidden" name="redirect_to" value="index.php?page=home&mine=1">
                    <button type="submit" class="danger-button">Ta bort recept</button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($viewer): ?>
        <section class="rating-panel">
            <h2>Rösta med stjärnor</h2>
            <form method="post" action="index.php" class="star-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="rate_recipe">
                <input type="hidden" name="recipe_id" value="<?= e((string) $recipeId) ?>">
                <input type="hidden" name="redirect_to" value="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">

                <div class="star-buttons">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <button
                            type="submit"
                            name="rating"
                            value="<?= e((string) $star) ?>"
                            class="star-submit <?= $userRating === $star ? 'is-active' : '' ?>"
                            title="Sätt <?= e((string) $star) ?> stjärnor"
                        >★</button>
                    <?php endfor; ?>
                </div>
            </form>
            <?php if ($userRating > 0): ?>
                <p>Din nuvarande röst: <?= e((string) $userRating) ?>/5</p>
            <?php else: ?>
                <p>Du har inte röstat än.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="rating-panel">
            <p><a href="index.php?page=login">Logga in</a> för att rösta med stjärnor.</p>
        </section>
    <?php endif; ?>

    <?php if ($inventoryEnabled && $ingredientTotal > 0): ?>
        <section class="detail-progress">
            <p>Du har <?= e((string) $haveCount) ?> av <?= e((string) $ingredientTotal) ?> ingredienser hemma.</p>
            <div class="progress">
                <span style="width: <?= e((string) max(0, min(100, $completion))) ?>%"></span>
            </div>
        </section>
    <?php endif; ?>

    <section class="detail-columns">
        <div class="detail-card">
            <h2>Ingredienser</h2>
            <div class="serving-scaler" data-serving-scaler data-base-servings="<?= e((string) $baseServings) ?>">
                <p>Anpassa portioner</p>
                <div class="serving-controls">
                    <button type="button" class="serving-step" data-serving-decrease aria-label="Minska antal portioner">-</button>
                    <input
                        type="number"
                        min="1"
                        step="1"
                        value="<?= e((string) $baseServings) ?>"
                        class="serving-input"
                        data-serving-input
                        aria-label="Antal portioner"
                    >
                    <button type="button" class="serving-step" data-serving-increase aria-label="Öka antal portioner">+</button>
                </div>
                <small>Ingrediensmängderna räknas om automatiskt.</small>
            </div>
            <ul class="ingredient-list">
                <?php foreach ($ingredients as $ingredient): ?>
                    <li class="<?= (int) $ingredient['has_ingredient'] === 1 ? 'have-it' : '' ?>">
                        <div>
                            <strong><?= e($ingredient['name']) ?></strong>
                            <?php if (!empty($ingredient['quantity'])): ?>
                                <span class="ingredient-qty" data-scalable-qty data-original-qty="<?= e((string) $ingredient['quantity']) ?>"><?= e($ingredient['quantity']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($inventoryEnabled && (int) $ingredient['has_ingredient'] === 1): ?>
                            <?php $locationKeys = array_filter(array_map('trim', explode(',', (string) $ingredient['locations']))); ?>
                            <small class="ingredient-locations">
                                <span>Finns i:</span>
                                <?php foreach ($locationKeys as $locationKey): ?>
                                    <?php if (isset($locationMeta[$locationKey])): ?>
                                        <img
                                            src="<?= e($locationMeta[$locationKey]['icon']) ?>"
                                            alt="<?= e($locationMeta[$locationKey]['label']) ?>"
                                            title="<?= e($locationMeta[$locationKey]['label']) ?>"
                                            class="location-icon location-icon-sm"
                                            width="20"
                                            height="20"
                                        >
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="detail-card">
            <h2>Gör så här</h2>
            <p class="instructions"><?= nl2br(e($recipe['instructions'])) ?></p>
        </div>
    </section>
</article>

<?php if (count($relatedRecipes) > 0): ?>
    <section class="related-section">
        <h2>Fler recept i samma kategorier</h2>
        <div class="recipe-grid compact">
            <?php foreach ($relatedRecipes as $related): ?>
                <article class="recipe-card">
                    <h3><a href="index.php?page=recipe&id=<?= e((string) $related['id']) ?>"><?= e($related['title']) ?></a></h3>
                    <p><?= e($related['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

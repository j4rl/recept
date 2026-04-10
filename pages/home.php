<?php
declare(strict_types=1);

$pageTitle = app_config('app_name') . ' - Hem';

$search = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$canCook = isset($_GET['can_cook']) && $_GET['can_cook'] === '1';
$allowMissing = isset($_GET['allow_missing']) && $_GET['allow_missing'] === '1';
$mineOnly = isset($_GET['mine']) && $_GET['mine'] === '1';

$viewer = current_user();
$inventoryEnabled = $viewer && (int) $viewer['inventory_enabled'] === 1;

$categories = db_all('SELECT id, name, slug FROM categories ORDER BY name');

$where = ['r.is_published = 1'];
$types = '';
$params = [];

$joins = "
    LEFT JOIN (
        SELECT recipe_id, COUNT(*) AS total_ingredients
        FROM recipe_ingredients
        GROUP BY recipe_id
    ) ri_total ON ri_total.recipe_id = r.id
    LEFT JOIN (
        SELECT
            rc.recipe_id,
            GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories,
            MIN(c.name) AS primary_category
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
";

$haveSelect = '0';
if ($inventoryEnabled) {
    $joins .= "
        LEFT JOIN (
            SELECT ri.recipe_id, COUNT(DISTINCT ri.ingredient_id) AS have_ingredients
            FROM recipe_ingredients ri
            INNER JOIN user_inventory ui
                ON ui.ingredient_id = ri.ingredient_id
                AND ui.user_id = ?
            GROUP BY ri.recipe_id
        ) ri_have ON ri_have.recipe_id = r.id
    ";
    $types .= 'i';
    $params[] = (int) $viewer['id'];
    $haveSelect = 'COALESCE(ri_have.have_ingredients, 0)';
}

if ($search !== '') {
    $term = '%' . $search . '%';
    $where[] = '(
        r.title LIKE ?
        OR r.description LIKE ?
        OR EXISTS (
            SELECT 1
            FROM recipe_categories rc_s
            INNER JOIN categories c_s ON c_s.id = rc_s.category_id
            WHERE rc_s.recipe_id = r.id
              AND c_s.name LIKE ?
        )
    )';
    $types .= 'sss';
    array_push($params, $term, $term, $term);
}

if ($categorySlug !== '') {
    $where[] = 'EXISTS (
        SELECT 1
        FROM recipe_categories rc_f
        INNER JOIN categories c_f ON c_f.id = rc_f.category_id
        WHERE rc_f.recipe_id = r.id
          AND c_f.slug = ?
    )';
    $types .= 's';
    $params[] = $categorySlug;
}

if ($canCook && $inventoryEnabled) {
    if ($allowMissing) {
        $where[] = $haveSelect . ' > 0';
    } else {
        $where[] = '(COALESCE(ri_total.total_ingredients, 0) = 0 OR COALESCE(ri_total.total_ingredients, 0) = ' . $haveSelect . ')';
    }
}

if ($mineOnly && $viewer) {
    $where[] = 'r.user_id = ?';
    $types .= 'i';
    $params[] = (int) $viewer['id'];
}

$sql = "
    SELECT
        r.id,
        r.title,
        r.description,
        r.image_path,
        r.prep_minutes,
        r.cook_minutes,
        r.servings,
        r.created_at,
        r.is_gluten_free,
        r.is_lactose_free,
        r.is_nut_free,
        u.name AS author_name,
        COALESCE(cat.categories, '') AS category_list,
        COALESCE(cat.primary_category, 'Okategoriserat') AS primary_category,
        COALESCE(rt.avg_rating, 0) AS avg_rating,
        COALESCE(rt.rating_count, 0) AS rating_count,
        COALESCE(ri_total.total_ingredients, 0) AS total_ingredients,
        {$haveSelect} AS have_ingredients
    FROM recipes r
    INNER JOIN users u ON u.id = r.user_id
    {$joins}
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
    LIMIT 80
";

$recipes = db_all($sql, $types, $params);
$canCookWarning = $canCook && !$inventoryEnabled;
$isCanCookMode = $canCook && $inventoryEnabled;
$isAllowMissingMode = $allowMissing && $isCanCookMode;

$popularRecipes = $recipes;
usort(
    $popularRecipes,
    static function (array $a, array $b): int {
        $avgCompare = ((float) $b['avg_rating']) <=> ((float) $a['avg_rating']);
        if ($avgCompare !== 0) {
            return $avgCompare;
        }

        $countCompare = ((int) $b['rating_count']) <=> ((int) $a['rating_count']);
        if ($countCompare !== 0) {
            return $countCompare;
        }

        return strtotime((string) $b['created_at']) <=> strtotime((string) $a['created_at']);
    }
);
$popularRecipes = array_slice($popularRecipes, 0, 8);
$latestRecipes = array_slice($recipes, 0, 10);

$recipeOfTheDay = db_one(
    'SELECT
        r.id,
        r.title,
        r.description,
        r.image_path,
        r.prep_minutes,
        r.cook_minutes,
        COALESCE(cat.categories, "") AS category_list,
        COALESCE(rt.avg_rating, 0) AS avg_rating,
        COALESCE(rt.rating_count, 0) AS rating_count
     FROM recipes r
     LEFT JOIN (
        SELECT
            rc.recipe_id,
            GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS categories
        FROM recipe_categories rc
        INNER JOIN categories c ON c.id = rc.category_id
        GROUP BY rc.recipe_id
     ) cat ON cat.recipe_id = r.id
     LEFT JOIN (
        SELECT recipe_id, AVG(rating) AS avg_rating, COUNT(*) AS rating_count
        FROM recipe_ratings
        GROUP BY recipe_id
     ) rt ON rt.recipe_id = r.id
     WHERE r.is_published = 1
     ORDER BY SHA2(CONCAT(?, "-", r.id), 256)
     LIMIT 1',
    's',
    [date('Y-m-d')]
);
?>

<section class="home-shell">
    <div class="home-toolbar">
        <form method="get" action="index.php" class="filter-bar">
            <input type="hidden" name="page" value="home">
            <?php if ($mineOnly): ?>
                <input type="hidden" name="mine" value="1">
            <?php endif; ?>

            <label>
                <span>Kategori</span>
                <select name="category">
                    <option value="">Alla</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="search-label">
                <span>Sök recept</span>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Sök recept...">
            </label>

            <?php if ($viewer && $inventoryEnabled): ?>
                <label class="can-cook-checkbox">
                    <input type="checkbox" name="can_cook" value="1" <?= $isCanCookMode ? 'checked' : '' ?> data-can-cook-checkbox>
                    <span>Filtrera på ingredienser jag har hemma</span>
                </label>
                <label class="can-cook-checkbox">
                    <input
                        type="checkbox"
                        name="allow_missing"
                        value="1"
                        <?= $isAllowMissingMode ? 'checked' : '' ?>
                        <?= $isCanCookMode ? '' : 'disabled' ?>
                        data-allow-missing-checkbox
                    >
                    <span>Visa även recept som saknar ingredienser</span>
                </label>
            <?php endif; ?>

            <button type="submit">Filtrera</button>
        </form>

        <?php if ($canCookWarning): ?>
            <p class="inline-warning">Aktivera Skafferi/Kyl/Frys för att filtrera på recept du kan laga.</p>
        <?php endif; ?>
    </div>

    <?php if ($viewer && $mineOnly): ?>
        <div class="mine-actions">
            <a href="index.php?page=create" class="secondary-button">Publicera recept</a>
        </div>
    <?php endif; ?>

    <?php if ($recipeOfTheDay): ?>
        <?php
        $dailyImage = recipe_image_url((string) ($recipeOfTheDay['image_path'] ?? ''));
        $dailyRating = (float) $recipeOfTheDay['avg_rating'];
        $dailyRatingPercent = max(0, min(100, ($dailyRating / 5) * 100));
        $dailyCategoryText = trim((string) $recipeOfTheDay['category_list']);
        ?>
        <section class="home-section">
            <div class="section-head">
                <h2>Recept av dagen</h2>
            </div>

            <article class="recipe-of-day-card">
                <img src="<?= e($dailyImage) ?>" alt="<?= e($recipeOfTheDay['title']) ?>" loading="lazy" class="recipe-of-day-image">
                <div class="recipe-of-day-body">
                    <p class="recipe-of-day-kicker">Dagens slumpade val</p>
                    <h3><a href="index.php?page=recipe&id=<?= e((string) $recipeOfTheDay['id']) ?>"><?= e($recipeOfTheDay['title']) ?></a></h3>
                    <p><?= e($recipeOfTheDay['description']) ?></p>
                    <div class="recipe-meta">
                        <span><?= e((string) minutes_total((int) $recipeOfTheDay['prep_minutes'], (int) $recipeOfTheDay['cook_minutes'])) ?> min</span>
                        <?php if ($dailyCategoryText !== ''): ?>
                            <span><?= e($dailyCategoryText) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="rating-line">
                        <span class="star-rating" role="img" aria-label="Betyg <?= e(number_format($dailyRating, 1, ',', ' ')) ?> av 5">
                            <span class="star-rating-base">★★★★★</span>
                            <span class="star-rating-fill" style="width: <?= e(number_format($dailyRatingPercent, 2, '.', '')) ?>%;">★★★★★</span>
                        </span>
                        <?php if ((int) $recipeOfTheDay['rating_count'] > 0): ?>
                            <span><?= e(number_format($dailyRating, 1, ',', ' ')) ?> (<?= e((string) $recipeOfTheDay['rating_count']) ?>)</span>
                        <?php else: ?>
                            <span>Inga röster ännu</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </section>
    <?php endif; ?>

    <section class="home-section">
        <div class="section-head">
            <h2>Populära recept</h2>
        </div>

        <div class="popular-grid">
            <?php if (count($popularRecipes) === 0): ?>
                <article class="empty-card">
                    <h3>Inga recept hittades</h3>
                    <p>Justera filtret eller publicera ett nytt recept.</p>
                </article>
            <?php endif; ?>

            <?php foreach ($popularRecipes as $recipe): ?>
                <?php
                $imagePath = recipe_image_url((string) ($recipe['image_path'] ?? ''));
                $totalTime = minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes']);
                $avgRating = (float) $recipe['avg_rating'];
                $ratingCount = (int) $recipe['rating_count'];
                $ratingPercent = max(0, min(100, ($avgRating / 5) * 100));
                ?>
                <article class="recipe-tile">
                    <a href="index.php?page=recipe&id=<?= e((string) $recipe['id']) ?>" class="recipe-tile-link">
                        <img src="<?= e($imagePath) ?>" alt="<?= e($recipe['title']) ?>" loading="lazy" class="recipe-tile-image">
                        <div class="recipe-tile-body">
                            <h3><?= e($recipe['title']) ?></h3>
                            <p class="recipe-tile-meta">Tid: <?= e((string) $totalTime) ?> min</p>
                            <div class="rating-line">
                                <span
                                    class="star-rating"
                                    role="img"
                                    aria-label="Betyg <?= e(number_format($avgRating, 1, ',', ' ')) ?> av 5"
                                >
                                    <span class="star-rating-base">★★★★★</span>
                                    <span class="star-rating-fill" style="width: <?= e(number_format($ratingPercent, 2, '.', '')) ?>%;">★★★★★</span>
                                </span>
                                <?php if ($ratingCount > 0): ?>
                                    <span><?= e(number_format($avgRating, 1, ',', ' ')) ?> (<?= e((string) $ratingCount) ?>)</span>
                                <?php else: ?>
                                    <span>Inga röster ännu</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isCanCookMode): ?>
                                <?php
                                $haveIngredients = (int) $recipe['have_ingredients'];
                                $totalIngredients = (int) $recipe['total_ingredients'];
                                $missingIngredients = max(0, $totalIngredients - $haveIngredients);
                                ?>
                                <p class="inventory-match-line">
                                    Har <?= e((string) $haveIngredients) ?> av <?= e((string) $totalIngredients) ?> ingredienser
                                    <?php if ($missingIngredients > 0): ?>
                                        | saknar <?= e((string) $missingIngredients) ?>
                                    <?php else: ?>
                                        | komplett
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
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
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="home-section">
        <div class="section-head">
            <h2>Senaste recept</h2>
        </div>

        <div class="latest-list">
            <?php foreach ($latestRecipes as $recipe): ?>
                <?php
                $totalTime = minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes']);
                $recipeCategories = array_filter(array_map('trim', explode(',', (string) $recipe['category_list'])));
                ?>
                <a href="index.php?page=recipe&id=<?= e((string) $recipe['id']) ?>" class="latest-row">
                    <div>
                        <h3><?= e($recipe['title']) ?></h3>
                        <p>
                            <?= e((string) $totalTime) ?> min
                            <?php if (count($recipeCategories) > 0): ?>
                                | <?= e(implode(', ', $recipeCategories)) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (count($latestRecipes) === 0): ?>
                <article class="empty-card">
                    <h3>Inga recept ännu</h3>
                    <p>Logga in och publicera det första receptet.</p>
                </article>
            <?php endif; ?>
        </div>
    </section>
</section>

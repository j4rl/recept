<?php
declare(strict_types=1);

$recipeId = (int) ($_GET['id'] ?? 0);
$viewer = current_user();
$inventoryEnabled = $viewer && (int) $viewer['inventory_enabled'] === 1;

$recipe = db_one(
    'SELECT
        r.id,
        r.title,
        r.description,
        r.instructions,
        r.prep_minutes,
        r.cook_minutes,
        r.servings,
        r.created_at,
        c.id AS category_id,
        c.name AS category_name,
        c.slug AS category_slug,
        u.name AS author_name
     FROM recipes r
     INNER JOIN categories c ON c.id = r.category_id
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.id = ? AND r.is_published = 1
     LIMIT 1',
    'i',
    [$recipeId]
);

if (!$recipe) {
    http_response_code(404);
    $pageTitle = app_config('app_name') . ' - Recept saknas';
    ?>
    <section class="empty-card">
        <h1>Receptet hittades inte</h1>
        <p>Det kan ha tagits bort eller sa ar lanken fel.</p>
        <a class="secondary-button" href="index.php">Till receptoversikten</a>
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
    'SELECT id, title, description
     FROM recipes
     WHERE category_id = ? AND id <> ? AND is_published = 1
     ORDER BY created_at DESC
     LIMIT 4',
    'ii',
    [(int) $recipe['category_id'], $recipeId]
);

$haveCount = 0;
foreach ($ingredients as $ingredient) {
    $haveCount += (int) $ingredient['has_ingredient'];
}

$ingredientTotal = count($ingredients);
$completion = $ingredientTotal > 0 ? (int) floor(($haveCount / $ingredientTotal) * 100) : 0;
?>

<article class="recipe-detail">
    <header class="detail-head">
        <div>
            <span class="pill"><?= e($recipe['category_name']) ?></span>
            <h1><?= e($recipe['title']) ?></h1>
            <p><?= e($recipe['description']) ?></p>
        </div>
        <div class="detail-facts">
            <div><strong><?= e((string) $recipe['servings']) ?></strong><span>port</span></div>
            <div><strong><?= e((string) minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes'])) ?></strong><span>min</span></div>
            <div><strong><?= e($recipe['author_name']) ?></strong><span>skapare</span></div>
        </div>
    </header>

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
            <ul class="ingredient-list">
                <?php foreach ($ingredients as $ingredient): ?>
                    <li class="<?= (int) $ingredient['has_ingredient'] === 1 ? 'have-it' : '' ?>">
                        <div>
                            <strong><?= e($ingredient['name']) ?></strong>
                            <?php if (!empty($ingredient['quantity'])): ?>
                                <span><?= e($ingredient['quantity']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($inventoryEnabled && (int) $ingredient['has_ingredient'] === 1): ?>
                            <small>Finns i: <?= e((string) $ingredient['locations']) ?></small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="detail-card">
            <h2>Gor sa har</h2>
            <p class="instructions"><?= nl2br(e($recipe['instructions'])) ?></p>
        </div>
    </section>
</article>

<?php if (count($relatedRecipes) > 0): ?>
    <section class="related-section">
        <h2>Fler recept i samma kategori</h2>
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


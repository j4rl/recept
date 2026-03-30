<?php
declare(strict_types=1);

$pageTitle = app_config('app_name') . ' - Utforska recept';

$search = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$canCook = isset($_GET['can_cook']) && $_GET['can_cook'] === '1';

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
    $where[] = '(r.title LIKE ? OR r.description LIKE ? OR c.name LIKE ?)';
    $types .= 'sss';
    array_push($params, $term, $term, $term);
}

if ($categorySlug !== '') {
    $where[] = 'c.slug = ?';
    $types .= 's';
    $params[] = $categorySlug;
}

if ($canCook && $inventoryEnabled) {
    $where[] = '(COALESCE(ri_total.total_ingredients, 0) = 0 OR COALESCE(ri_total.total_ingredients, 0) = ' . $haveSelect . ')';
}

$sql = "
    SELECT
        r.id,
        r.title,
        r.slug,
        r.description,
        r.prep_minutes,
        r.cook_minutes,
        r.servings,
        r.created_at,
        c.name AS category_name,
        c.slug AS category_slug,
        u.name AS author_name,
        COALESCE(ri_total.total_ingredients, 0) AS total_ingredients,
        {$haveSelect} AS have_ingredients
    FROM recipes r
    INNER JOIN categories c ON c.id = r.category_id
    INNER JOIN users u ON u.id = r.user_id
    {$joins}
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
    LIMIT 60
";

$recipes = db_all($sql, $types, $params);
$canCookWarning = $canCook && !$inventoryEnabled;
?>

<section class="hero">
    <div class="hero-copy">
        <h1>Ditt nya matarkiv</h1>
        <p>Sok, filtrera och hitta recept for vardag, helg och allt dar emellan.</p>
    </div>
    <div class="hero-stats">
        <div class="hero-stat">
            <strong><?= e((string) count($recipes)) ?></strong>
            <span>matchande recept</span>
        </div>
        <div class="hero-stat">
            <strong><?= e((string) count($categories)) ?></strong>
            <span>kategorier</span>
        </div>
    </div>
</section>

<section class="search-panel">
    <form method="get" action="index.php" class="search-form">
        <input type="hidden" name="page" value="home">
        <input
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Sok pa recept, ingrediens eller kategori"
            autocomplete="off"
        >
        <?php if ($categorySlug !== ''): ?>
            <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
        <?php endif; ?>
        <?php if ($canCook && $inventoryEnabled): ?>
            <input type="hidden" name="can_cook" value="1">
        <?php endif; ?>
        <button type="submit">Sok</button>
    </form>

    <div class="chip-row">
        <a href="index.php?page=home<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="chip <?= $categorySlug === '' ? 'is-active' : '' ?>">Alla</a>
        <?php foreach ($categories as $category): ?>
            <?php
            $categoryLink = 'index.php?page=home&category=' . urlencode($category['slug']);
            if ($search !== '') {
                $categoryLink .= '&q=' . urlencode($search);
            }
            if ($canCook && $inventoryEnabled) {
                $categoryLink .= '&can_cook=1';
            }
            ?>
            <a
                href="<?= e($categoryLink) ?>"
                class="chip <?= $categorySlug === $category['slug'] ? 'is-active' : '' ?>"
            ><?= e($category['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($viewer): ?>
        <?php
        $cookLink = 'index.php?page=home';
        $queryBits = [];
        if ($search !== '') {
            $queryBits[] = 'q=' . urlencode($search);
        }
        if ($categorySlug !== '') {
            $queryBits[] = 'category=' . urlencode($categorySlug);
        }
        if ($canCook && $inventoryEnabled) {
            $canCook = false;
        } else {
            $queryBits[] = 'can_cook=1';
        }
        if (count($queryBits) > 0) {
            $cookLink .= '&' . implode('&', $queryBits);
        }
        ?>
        <a class="secondary-button" href="<?= e($cookLink) ?>">
            <?= $canCook && $inventoryEnabled ? 'Visa alla recept' : 'Visa recept jag kan laga nu' ?>
        </a>
    <?php endif; ?>

    <?php if ($canCookWarning): ?>
        <p class="inline-warning">Aktivera Skafferi/Kyl/Frys for att filtrera pa recept du kan laga.</p>
    <?php endif; ?>
</section>

<section class="recipe-grid">
    <?php if (count($recipes) === 0): ?>
        <article class="empty-card">
            <h2>Inga recept matchade</h2>
            <p>Testa en annan sokfras eller valj en annan kategori.</p>
        </article>
    <?php endif; ?>

    <?php foreach ($recipes as $recipe): ?>
        <?php
        $totalTime = minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes']);
        $completion = 0;
        if ((int) $recipe['total_ingredients'] > 0) {
            $completion = (int) floor(((int) $recipe['have_ingredients'] / (int) $recipe['total_ingredients']) * 100);
        }
        ?>
        <article class="recipe-card">
            <div class="recipe-meta">
                <span class="pill"><?= e($recipe['category_name']) ?></span>
                <span><?= e((string) $totalTime) ?> min</span>
            </div>
            <h2><a href="index.php?page=recipe&id=<?= e((string) $recipe['id']) ?>"><?= e($recipe['title']) ?></a></h2>
            <p><?= e($recipe['description']) ?></p>
            <div class="recipe-bottom">
                <span><?= e($recipe['servings']) ?> port</span>
                <span>Av <?= e($recipe['author_name']) ?></span>
            </div>

            <?php if ($inventoryEnabled && (int) $recipe['total_ingredients'] > 0): ?>
                <div class="progress-row">
                    <span>Du har <?= e((string) $recipe['have_ingredients']) ?>/<?= e((string) $recipe['total_ingredients']) ?> ingredienser</span>
                    <div class="progress">
                        <span style="width: <?= e((string) max(0, min(100, $completion))) ?>%"></span>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

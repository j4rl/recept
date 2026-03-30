<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$pageTitle = app_config('app_name') . ' - Skafferi/Kyl/Frys';
$inventoryEnabled = (int) $viewer['inventory_enabled'] === 1;

$ingredients = db_all(
    'SELECT i.id, i.name, COUNT(ri.recipe_id) AS recipe_count
     FROM ingredients i
     LEFT JOIN recipe_ingredients ri ON ri.ingredient_id = i.id
     GROUP BY i.id, i.name
     ORDER BY recipe_count DESC, i.name ASC'
);

$inventoryRows = db_all(
    'SELECT ingredient_id, location FROM user_inventory WHERE user_id = ?',
    'i',
    [(int) $viewer['id']]
);

$inventoryMap = [];
foreach ($inventoryRows as $row) {
    $ingredientId = (int) $row['ingredient_id'];
    $location = (string) $row['location'];
    if (!isset($inventoryMap[$ingredientId])) {
        $inventoryMap[$ingredientId] = [];
    }
    $inventoryMap[$ingredientId][$location] = true;
}
?>

<section class="inventory-shell">
    <h1>Skafferi, kylskap och frys</h1>
    <p>Slag pa funktionen och kryssa i ingredienser du har hemma.</p>

    <form method="post" action="index.php" class="toggle-panel">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="toggle_inventory">
        <input type="hidden" name="redirect_to" value="index.php?page=inventory">

        <label class="switch-row">
            <input type="checkbox" name="inventory_enabled" value="1" <?= $inventoryEnabled ? 'checked' : '' ?>>
            <span>Aktivera Skafferi/Kyl/Frys</span>
        </label>

        <button type="submit">Spara installning</button>
    </form>

    <?php if (!$inventoryEnabled): ?>
        <article class="empty-card">
            <h2>Funktionen ar avstangd</h2>
            <p>Aktivera den ovan for att kunna kryssa i ingredienser och filtrera recept du kan laga direkt.</p>
        </article>
    <?php else: ?>
        <section class="inventory-panel">
            <div class="inventory-head">
                <h2>Dina ingredienser</h2>
                <input type="search" placeholder="Filtrera ingrediens..." data-inventory-filter>
            </div>

            <form method="post" action="index.php" class="inventory-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_inventory">
                <input type="hidden" name="redirect_to" value="index.php?page=inventory">

                <div class="inventory-grid">
                    <div class="inventory-grid-head">Ingrediens</div>
                    <div class="inventory-grid-head">Skafferi</div>
                    <div class="inventory-grid-head">Kylskap</div>
                    <div class="inventory-grid-head">Frys</div>

                    <?php foreach ($ingredients as $ingredient): ?>
                        <?php
                        $id = (int) $ingredient['id'];
                        $flags = $inventoryMap[$id] ?? [];
                        ?>
                        <div class="inventory-name" data-inventory-name="<?= e(strtolower($ingredient['name'])) ?>">
                            <strong><?= e($ingredient['name']) ?></strong>
                            <small>anvands i <?= e((string) $ingredient['recipe_count']) ?> recept</small>
                        </div>
                        <div>
                            <input
                                type="checkbox"
                                name="inventory[<?= e((string) $id) ?>][]"
                                value="pantry"
                                <?= isset($flags['pantry']) ? 'checked' : '' ?>
                            >
                        </div>
                        <div>
                            <input
                                type="checkbox"
                                name="inventory[<?= e((string) $id) ?>][]"
                                value="fridge"
                                <?= isset($flags['fridge']) ? 'checked' : '' ?>
                            >
                        </div>
                        <div>
                            <input
                                type="checkbox"
                                name="inventory[<?= e((string) $id) ?>][]"
                                value="freezer"
                                <?= isset($flags['freezer']) ? 'checked' : '' ?>
                            >
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit">Spara lager</button>
            </form>
        </section>
    <?php endif; ?>
</section>


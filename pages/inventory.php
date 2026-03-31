<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$pageTitle = app_config('app_name') . ' - Skafferi/Kyl/Frys';
$inventoryEnabled = (int) $viewer['inventory_enabled'] === 1;
$userId = (int) $viewer['id'];
$locationMeta = [
    'pantry' => ['label' => 'Skafferi', 'icon' => 'assets/img/skaff.png'],
    'fridge' => ['label' => 'Kylskåp', 'icon' => 'assets/img/kyl.png'],
    'freezer' => ['label' => 'Frys', 'icon' => 'assets/img/frys.png'],
];

$suggestionIngredients = db_all(
    'SELECT i.name, COUNT(DISTINCT ri.recipe_id) AS recipe_count
     FROM ingredients i
     INNER JOIN recipe_ingredients ri ON ri.ingredient_id = i.id
     GROUP BY i.id, i.name
     ORDER BY i.name ASC'
);

$inventoryRows = db_all(
    'SELECT ingredient_id, location FROM user_inventory WHERE user_id = ?',
    'i',
    [$userId]
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

$selectedIngredients = db_all(
    'SELECT
        i.id,
        i.name,
        COALESCE(rc.recipe_count, 0) AS recipe_count
     FROM ingredients i
     INNER JOIN (
        SELECT DISTINCT ingredient_id
        FROM user_inventory
        WHERE user_id = ?
     ) ui_sel ON ui_sel.ingredient_id = i.id
     LEFT JOIN (
        SELECT ingredient_id, COUNT(DISTINCT recipe_id) AS recipe_count
        FROM recipe_ingredients
        GROUP BY ingredient_id
     ) rc ON rc.ingredient_id = i.id
     ORDER BY i.name ASC',
    'i',
    [$userId]
);
?>

<section class="inventory-shell">
    <h1>Skafferi, kylskåp och frys</h1>
    <p>Slå på funktionen och kryssa i ingredienser du har hemma.</p>

    <form method="post" action="index.php" class="toggle-panel">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="toggle_inventory">
        <input type="hidden" name="redirect_to" value="index.php?page=inventory">

        <label class="switch-row">
            <input type="checkbox" name="inventory_enabled" value="1" <?= $inventoryEnabled ? 'checked' : '' ?>>
            <span>Aktivera Skafferi/Kyl/Frys</span>
        </label>

        <button type="submit">Spara inställning</button>
    </form>

    <?php if (!$inventoryEnabled): ?>
        <article class="empty-card">
            <h2>Funktionen är avstängd</h2>
            <p>Aktivera den ovan för att kunna kryssa i ingredienser och filtrera recept du kan laga direkt.</p>
        </article>
    <?php else: ?>
        <section class="inventory-panel inventory-add-panel">
            <h2>Lägg till ingrediens</h2>
            <p class="helper-text">Börja skriva för att få förslag från receptens ingredienser, eller skriv en egen.</p>
            <form method="post" action="index.php" class="inventory-add-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_inventory_item">
                <input type="hidden" name="redirect_to" value="index.php?page=inventory">

                <label>
                    <span>Ingrediens</span>
                    <input
                        type="text"
                        name="ingredient_name"
                        list="inventory-ingredient-suggestions"
                        placeholder="Ex: Dill"
                        autocomplete="off"
                        required
                    >
                </label>

                <label>
                    <span>Plats</span>
                    <select name="location">
                        <option value="pantry"><?= e($locationMeta['pantry']['label']) ?></option>
                        <option value="fridge"><?= e($locationMeta['fridge']['label']) ?></option>
                        <option value="freezer"><?= e($locationMeta['freezer']['label']) ?></option>
                    </select>
                </label>

                <button type="submit">Lägg till</button>
            </form>

            <datalist id="inventory-ingredient-suggestions">
                <?php foreach ($suggestionIngredients as $ingredient): ?>
                    <option value="<?= e($ingredient['name']) ?>"><?= e((string) $ingredient['recipe_count']) ?> recept</option>
                <?php endforeach; ?>
            </datalist>
        </section>

        <section class="inventory-panel">
            <div class="inventory-head">
                <h2>Dina ingredienser</h2>
                <input type="search" placeholder="Filtrera ingrediens..." data-inventory-filter>
            </div>

            <?php if (count($selectedIngredients) === 0): ?>
                <article class="empty-card">
                    <h3>Inga ingredienser i listan ännu</h3>
                    <p>Lägg till ingredienser ovan. Endast valda ingredienser visas här.</p>
                </article>
            <?php else: ?>
                <div class="inventory-grid">
                    <div class="inventory-grid-head">Ingrediens</div>
                    <div class="inventory-grid-head inventory-grid-head-location">Skafferi</div>
                    <div class="inventory-grid-head inventory-grid-head-location">Kyl</div>
                    <div class="inventory-grid-head inventory-grid-head-location">Frys</div>
                    <div class="inventory-grid-head inventory-grid-head-actions">Ändra</div>

                    <?php foreach ($selectedIngredients as $ingredient): ?>
                        <?php
                        $id = (int) $ingredient['id'];
                        $flags = $inventoryMap[$id] ?? [];
                        $currentLocation = isset($flags['pantry']) ? 'pantry' : (isset($flags['fridge']) ? 'fridge' : 'freezer');
                        ?>
                        <div
                            class="inventory-name"
                            data-inventory-name="<?= e(mb_strtolower($ingredient['name'], 'UTF-8')) ?>"
                            data-inventory-item="<?= e((string) $id) ?>"
                        >
                            <strong><?= e($ingredient['name']) ?></strong>
                            <small>används i <?= e((string) $ingredient['recipe_count']) ?> recept</small>
                        </div>
                        <div class="inventory-cell" data-inventory-item="<?= e((string) $id) ?>">
                            <?php if (isset($flags['pantry'])): ?>
                                <img
                                    src="<?= e($locationMeta['pantry']['icon']) ?>"
                                    alt="<?= e($locationMeta['pantry']['label']) ?>"
                                    title="<?= e($locationMeta['pantry']['label']) ?>"
                                    class="location-icon location-icon-list"
                                    width="24"
                                    height="24"
                                >
                            <?php endif; ?>
                        </div>
                        <div class="inventory-cell" data-inventory-item="<?= e((string) $id) ?>">
                            <?php if (isset($flags['fridge'])): ?>
                                <img
                                    src="<?= e($locationMeta['fridge']['icon']) ?>"
                                    alt="<?= e($locationMeta['fridge']['label']) ?>"
                                    title="<?= e($locationMeta['fridge']['label']) ?>"
                                    class="location-icon location-icon-list"
                                    width="24"
                                    height="24"
                                >
                            <?php endif; ?>
                        </div>
                        <div class="inventory-cell" data-inventory-item="<?= e((string) $id) ?>">
                            <?php if (isset($flags['freezer'])): ?>
                                <img
                                    src="<?= e($locationMeta['freezer']['icon']) ?>"
                                    alt="<?= e($locationMeta['freezer']['label']) ?>"
                                    title="<?= e($locationMeta['freezer']['label']) ?>"
                                    class="location-icon location-icon-list"
                                    width="24"
                                    height="24"
                                >
                            <?php endif; ?>
                        </div>
                        <div class="inventory-actions" data-inventory-item="<?= e((string) $id) ?>">
                            <form method="post" action="index.php" class="inventory-edit-form">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_inventory_item">
                                <input type="hidden" name="redirect_to" value="index.php?page=inventory">
                                <input type="hidden" name="ingredient_id" value="<?= e((string) $id) ?>">

                                <input type="text" name="ingredient_name" value="<?= e($ingredient['name']) ?>" aria-label="Ingrediensnamn">
                                <select name="location" aria-label="Plats">
                                    <option value="pantry" <?= $currentLocation === 'pantry' ? 'selected' : '' ?>>Skafferi</option>
                                    <option value="fridge" <?= $currentLocation === 'fridge' ? 'selected' : '' ?>>Kyl</option>
                                    <option value="freezer" <?= $currentLocation === 'freezer' ? 'selected' : '' ?>>Frys</option>
                                </select>
                                <button type="submit">Spara</button>
                            </form>

                            <form method="post" action="index.php" class="inventory-delete-form" onsubmit="return confirm('Ta bort ingrediensen från ditt lager?');">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_inventory_item">
                                <input type="hidden" name="redirect_to" value="index.php?page=inventory">
                                <input type="hidden" name="ingredient_id" value="<?= e((string) $id) ?>">
                                <button type="submit" class="danger-button">Ta bort</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

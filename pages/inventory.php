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

$editingIngredientId = (int) ($_GET['edit_ingredient_id'] ?? 0);
$editingItem = null;

if ($editingIngredientId > 0) {
    $editingItem = db_one(
        'SELECT ui.ingredient_id, ui.location, i.name
         FROM user_inventory ui
         INNER JOIN ingredients i ON i.id = ui.ingredient_id
         WHERE ui.user_id = ? AND ui.ingredient_id = ?
         LIMIT 1',
        'ii',
        [$userId, $editingIngredientId]
    );
}

$isEditingInventoryItem = is_array($editingItem);
$inventoryFormAction = $isEditingInventoryItem ? 'update_inventory_item' : 'add_inventory_item';
$inventoryFormIngredientName = $isEditingInventoryItem ? (string) $editingItem['name'] : '';
$inventoryFormLocation = $isEditingInventoryItem ? (string) $editingItem['location'] : 'pantry';
$inventorySubmitLabel = $isEditingInventoryItem ? 'Spara ändring' : 'Lägg till';
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
            <h2><?= $isEditingInventoryItem ? 'Redigera ingrediens' : 'Lägg till ingrediens' ?></h2>
            <p class="helper-text">
                <?= $isEditingInventoryItem ? 'Ändra värdena och spara. Du kan avbryta för att lägga till en ny ingrediens.' : 'Börja skriva för att få förslag från receptens ingredienser, eller skriv en egen.' ?>
            </p>
            <form method="post" action="index.php" class="inventory-add-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= e($inventoryFormAction) ?>">
                <input type="hidden" name="redirect_to" value="index.php?page=inventory">
                <?php if ($isEditingInventoryItem): ?>
                    <input type="hidden" name="ingredient_id" value="<?= e((string) $editingItem['ingredient_id']) ?>">
                <?php endif; ?>

                <label>
                    <span>Ingrediens</span>
                    <input
                        type="text"
                        name="ingredient_name"
                        list="inventory-ingredient-suggestions"
                        placeholder="Ex: Dill"
                        autocomplete="off"
                        value="<?= e($inventoryFormIngredientName) ?>"
                        required
                    >
                </label>

                <label>
                    <span>Plats</span>
                    <select name="location">
                        <option value="pantry" <?= $inventoryFormLocation === 'pantry' ? 'selected' : '' ?>><?= e($locationMeta['pantry']['label']) ?></option>
                        <option value="fridge" <?= $inventoryFormLocation === 'fridge' ? 'selected' : '' ?>><?= e($locationMeta['fridge']['label']) ?></option>
                        <option value="freezer" <?= $inventoryFormLocation === 'freezer' ? 'selected' : '' ?>><?= e($locationMeta['freezer']['label']) ?></option>
                    </select>
                </label>

                <div class="inventory-add-actions">
                    <button type="submit"><?= e($inventorySubmitLabel) ?></button>
                    <?php if ($isEditingInventoryItem): ?>
                        <a href="index.php?page=inventory" class="inventory-cancel-link">Avbryt</a>
                    <?php endif; ?>
                </div>
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
                            <form method="get" action="index.php" class="inventory-trigger-form">
                                <input type="hidden" name="page" value="inventory">
                                <input type="hidden" name="edit_ingredient_id" value="<?= e((string) $id) ?>">
                                <button type="submit" class="icon-action-button" aria-label="Redigera ingrediens" title="Redigera">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                                    </svg>
                                    <span class="visually-hidden">Redigera</span>
                                </button>
                            </form>

                            <form method="post" action="index.php" class="inventory-delete-form" onsubmit="return confirm('Ta bort ingrediensen från ditt lager?');">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_inventory_item">
                                <input type="hidden" name="redirect_to" value="index.php?page=inventory">
                                <input type="hidden" name="ingredient_id" value="<?= e((string) $id) ?>">
                                <button type="submit" class="danger-button icon-action-button" aria-label="Ta bort ingrediens" title="Ta bort">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="m6 6 1 14h10l1-14"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                    <span class="visually-hidden">Ta bort</span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

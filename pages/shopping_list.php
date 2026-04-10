<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$pageTitle = app_config('app_name') . ' - Inköpslista';
$shoppingItems = db_all(
    'SELECT
        sli.id,
        sli.ingredient_name,
        sli.quantity,
        sli.is_checked,
        sli.created_at,
        sli.recipe_id,
        r.title AS recipe_title,
        CASE WHEN COUNT(ui.ingredient_id) > 0 THEN 1 ELSE 0 END AS have_at_home
     FROM shopping_list_items sli
     LEFT JOIN recipes r ON r.id = sli.recipe_id
     LEFT JOIN user_inventory ui
        ON ui.user_id = ?
        AND ui.ingredient_id = sli.ingredient_id
     WHERE sli.user_id = ?
     GROUP BY sli.id, sli.ingredient_name, sli.quantity, sli.is_checked, sli.created_at, sli.recipe_id, r.title
     ORDER BY sli.is_checked ASC, sli.created_at DESC, sli.ingredient_name ASC',
    'ii',
    [(int) $viewer['id'], (int) $viewer['id']]
);

$activeCount = 0;
$checkedCount = 0;
foreach ($shoppingItems as $item) {
    if ((int) $item['is_checked'] === 1) {
        $checkedCount++;
    } else {
        $activeCount++;
    }
}

$googleKeepConfigured = google_keep_is_configured();
$googleKeepConnected = google_keep_is_connected((int) $viewer['id']);
?>

<section class="inventory-shell">
    <h1>Inköpslista</h1>
    <p>Bygg listan från recept och kryssa av när du har handlat klart.</p>

    <section class="inventory-panel keep-status-panel">
        <div>
            <h2>Google Keep</h2>
            <p class="helper-text">
                <?= $googleKeepConnected ? 'Google Keep är anslutet.' : 'Anslut Google Keep för att kunna skicka saknade ingredienser från en receptsida.' ?>
            </p>
        </div>
        <div class="recipe-manage-actions">
            <?php if (!$googleKeepConfigured): ?>
                <span class="helper-text">Sätt `RECEPT_GOOGLE_KEEP_CLIENT_ID` och `RECEPT_GOOGLE_KEEP_CLIENT_SECRET` först.</span>
            <?php elseif ($googleKeepConnected): ?>
                <form method="post" action="index.php">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="disconnect_google_keep">
                    <input type="hidden" name="redirect_to" value="index.php?page=shopping_list">
                    <button type="submit" class="secondary-button">Koppla från</button>
                </form>
            <?php else: ?>
                <a href="index.php?page=keep_connect&amp;return_to=<?= e(rawurlencode('index.php?page=shopping_list')) ?>" class="secondary-button">Anslut Google Keep</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="inventory-panel">
        <div class="shopping-head">
            <h2>Sammanställd lista</h2>
            <div class="shopping-summary">
                <span><?= e((string) $activeCount) ?> aktiva</span>
                <span><?= e((string) $checkedCount) ?> avklarade</span>
            </div>
        </div>

        <?php if (count($shoppingItems) === 0): ?>
            <article class="empty-card">
                <h3>Listan är tom</h3>
                <p>Lägg till ingredienser från ett recept för att börja samla inköp.</p>
            </article>
        <?php else: ?>
            <div class="shopping-list">
                <?php foreach ($shoppingItems as $item): ?>
                    <article class="shopping-item <?= (int) $item['is_checked'] === 1 ? 'is-checked' : '' ?>">
                        <form method="post" action="index.php">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_shopping_list_item">
                            <input type="hidden" name="shopping_item_id" value="<?= e((string) $item['id']) ?>">
                            <input type="hidden" name="redirect_to" value="index.php?page=shopping_list">
                            <button type="submit" class="shopping-check-button"><?= (int) $item['is_checked'] === 1 ? 'Återställ' : 'Klar' ?></button>
                        </form>

                        <div class="shopping-item-body">
                            <strong><?= e($item['ingredient_name']) ?></strong>
                            <?php if ((string) ($item['quantity'] ?? '') !== ''): ?>
                                <span><?= e($item['quantity']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['recipe_title'])): ?>
                                <small>Från <a href="index.php?page=recipe&id=<?= e((string) $item['recipe_id']) ?>"><?= e($item['recipe_title']) ?></a></small>
                            <?php endif; ?>
                            <?php if ((int) $item['have_at_home'] === 1): ?>
                                <small>Redan hemma i Skafferi/Kyl/Frys</small>
                            <?php endif; ?>
                        </div>

                        <form method="post" action="index.php">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_shopping_list_item">
                            <input type="hidden" name="shopping_item_id" value="<?= e((string) $item['id']) ?>">
                            <input type="hidden" name="redirect_to" value="index.php?page=shopping_list">
                            <button type="submit" class="danger-button">Ta bort</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($checkedCount > 0): ?>
                <form method="post" action="index.php" class="shopping-clear-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="clear_checked_shopping_items">
                    <input type="hidden" name="redirect_to" value="index.php?page=shopping_list">
                    <button type="submit" class="secondary-button">Rensa avklarade</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>

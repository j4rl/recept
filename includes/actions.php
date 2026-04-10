<?php
declare(strict_types=1);

function handle_actions(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = $_POST['action'] ?? '';
    if ($action === '') {
        redirect('index.php');
    }

    if (!verify_csrf((string) ($_POST['_csrf'] ?? ''))) {
        flash('error', 'Sessionen kunde inte verifieras. Prova igen.');
        redirect('index.php');
    }

    try {
        match ($action) {
            'register' => action_register(),
            'login' => action_login(),
            'logout' => action_logout(),
            'create_recipe' => action_create_recipe(),
            'update_recipe' => action_update_recipe(),
            'delete_recipe' => action_delete_recipe(),
            'toggle_favorite' => action_toggle_favorite(),
            'add_meal_plan_item' => action_add_meal_plan_item(),
            'remove_meal_plan_item' => action_remove_meal_plan_item(),
            'add_recipe_to_shopping_list' => action_add_recipe_to_shopping_list(),
            'toggle_shopping_list_item' => action_toggle_shopping_list_item(),
            'delete_shopping_list_item' => action_delete_shopping_list_item(),
            'clear_checked_shopping_items' => action_clear_checked_shopping_items(),
            'disconnect_google_keep' => action_disconnect_google_keep(),
            'send_missing_to_google_keep' => action_send_missing_to_google_keep(),
            'rate_recipe' => action_rate_recipe(),
            'toggle_inventory' => action_toggle_inventory(),
            'add_inventory_item' => action_add_inventory_item(),
            'update_inventory_item' => action_update_inventory_item(),
            'delete_inventory_item' => action_delete_inventory_item(),
            'save_inventory' => action_save_inventory(),
            default => flash('error', 'Okänd handling.'),
        };
    } catch (Throwable $exception) {
        flash('error', 'Ett fel uppstod: ' . $exception->getMessage());
    }

    redirect(safe_redirect((string) ($_POST['redirect_to'] ?? 'index.php')));
}

function safe_redirect(string $target): string
{
    if ($target === '' || str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
        return 'index.php';
    }

    if (!str_starts_with($target, 'index.php')) {
        return 'index.php';
    }

    return $target;
}

function action_register(): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($password !== $passwordConfirm) {
        flash('error', 'Lösenorden matchar inte.');
        return;
    }

    if (!register_user($name, $email, $password)) {
        return;
    }

    $user = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', 's', [strtolower($email)]);
    if ($user) {
        login_user((int) $user['id']);
    }

    flash('success', 'Konto skapat. Du är nu inloggad.');
}

function action_login(): void
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!attempt_login($email, $password)) {
        return;
    }

    flash('success', 'Inloggning lyckades.');
}

function action_logout(): void
{
    require_login();
    logout_user();
    flash('success', 'Du är utloggad.');
}

function action_create_recipe(): void
{
    require_login();
    $data = recipe_form_payload();
    if ($data === null) {
        return;
    }

    $author = current_user();
    if (!$author) {
        flash('error', 'Du måste vara inloggad.');
        return;
    }

    $uploadedImagePath = store_recipe_image($_FILES['dish_image'] ?? []);

    $db = db();
    $db->begin_transaction();

    try {
        $slug = unique_recipe_slug($data['title']);
        $primaryCategoryId = $data['category_ids'][0];

        db_execute(
            'INSERT INTO recipes (user_id, category_id, title, slug, description, image_path, instructions, prep_minutes, cook_minutes, servings, is_gluten_free, is_lactose_free, is_nut_free, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            'iisssssiiiiii',
            [
                (int) $author['id'],
                $primaryCategoryId,
                $data['title'],
                $slug,
                $data['description'],
                $uploadedImagePath,
                $data['instructions'],
                $data['prep_minutes'],
                $data['cook_minutes'],
                $data['servings'],
                $data['is_gluten_free'],
                $data['is_lactose_free'],
                $data['is_nut_free'],
            ]
        );

        $recipeId = (int) $db->insert_id;
        sync_recipe_categories($recipeId, $data['category_ids']);
        sync_recipe_ingredients($recipeId, $data['ingredients']);

        $db->commit();
        flash('success', 'Receptet publicerades.');
        $_POST['redirect_to'] = 'index.php?page=recipe&id=' . $recipeId;
    } catch (Throwable $exception) {
        $db->rollback();
        delete_recipe_image_file($uploadedImagePath);
        throw $exception;
    }
}

function action_update_recipe(): void
{
    require_login();

    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    if ($recipeId <= 0) {
        flash('error', 'Ogiltigt recept.');
        return;
    }

    $viewer = current_user();
    $recipe = db_one(
        'SELECT id, user_id, title, image_path FROM recipes WHERE id = ? LIMIT 1',
        'i',
        [$recipeId]
    );

    if (!$viewer || !$recipe) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    if (!can_manage_recipe($viewer, $recipe)) {
        flash('error', 'Du har inte behörighet att redigera det här receptet.');
        return;
    }

    $data = recipe_form_payload();
    if ($data === null) {
        return;
    }

    $existingImagePath = (string) ($recipe['image_path'] ?? '');
    $replacementImagePath = store_recipe_image($_FILES['dish_image'] ?? []);
    $nextImagePath = $replacementImagePath ?: ($existingImagePath !== '' ? $existingImagePath : null);

    $db = db();
    $db->begin_transaction();

    try {
        db_execute(
            'UPDATE recipes
             SET category_id = ?, title = ?, slug = ?, description = ?, image_path = ?, instructions = ?, prep_minutes = ?, cook_minutes = ?, servings = ?, is_gluten_free = ?, is_lactose_free = ?, is_nut_free = ?
             WHERE id = ?',
            'isssssiiiiiii',
            [
                $data['category_ids'][0],
                $data['title'],
                unique_recipe_slug($data['title'], $recipeId),
                $data['description'],
                $nextImagePath,
                $data['instructions'],
                $data['prep_minutes'],
                $data['cook_minutes'],
                $data['servings'],
                $data['is_gluten_free'],
                $data['is_lactose_free'],
                $data['is_nut_free'],
                $recipeId,
            ]
        );

        sync_recipe_categories($recipeId, $data['category_ids']);
        sync_recipe_ingredients($recipeId, $data['ingredients']);

        $db->commit();

        if ($replacementImagePath && $existingImagePath !== '' && $existingImagePath !== $replacementImagePath) {
            delete_recipe_image_file($existingImagePath);
        }

        flash('success', 'Receptet uppdaterades.');
        $_POST['redirect_to'] = 'index.php?page=recipe&id=' . $recipeId;
    } catch (Throwable $exception) {
        $db->rollback();
        delete_recipe_image_file($replacementImagePath);
        throw $exception;
    }
}

function action_delete_recipe(): void
{
    require_login();

    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    if ($recipeId <= 0) {
        flash('error', 'Ogiltigt recept.');
        return;
    }

    $viewer = current_user();
    $recipe = db_one(
        'SELECT id, user_id, image_path FROM recipes WHERE id = ? LIMIT 1',
        'i',
        [$recipeId]
    );

    if (!$viewer || !$recipe) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    if (!can_manage_recipe($viewer, $recipe)) {
        flash('error', 'Du har inte behörighet att ta bort det här receptet.');
        return;
    }

    $db = db();
    $db->begin_transaction();

    try {
        db_execute('DELETE FROM recipes WHERE id = ?', 'i', [$recipeId]);
        $db->commit();
        delete_recipe_image_file((string) ($recipe['image_path'] ?? ''));
        flash('success', 'Receptet togs bort.');
        $_POST['redirect_to'] = 'index.php?page=home&mine=1';
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function store_recipe_image(array $file): ?string
{
    if (!isset($file['error'])) {
        return null;
    }

    $uploadError = (int) $file['error'];
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Bilduppladdning misslyckades. Prova igen.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Bilden måste vara mellan 1 byte och 5 MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Ogiltig bilduppladdning.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpName);

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('Endast JPG, PNG, WEBP eller GIF är tillåtet.');
    }

    $uploadDirectory = recipe_upload_dir();
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Kunde inte skapa bildmappen.');
    }

    $filename = 'recipe-' . bin2hex(random_bytes(12)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Kunde inte spara bilden.');
    }

    return recipe_image_db_path($filename);
}

function recipe_form_payload(): ?array
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $instructions = trim((string) ($_POST['instructions'] ?? ''));
    $prepMinutes = max(0, (int) ($_POST['prep_minutes'] ?? 0));
    $cookMinutes = max(0, (int) ($_POST['cook_minutes'] ?? 0));
    $servings = max(1, (int) ($_POST['servings'] ?? 1));
    $categoryIdsRaw = $_POST['category_ids'] ?? [];
    $badges = $_POST['badges'] ?? [];
    $ingredientNames = $_POST['ingredient_name'] ?? [];
    $ingredientQty = $_POST['ingredient_qty'] ?? [];

    if ($title === '' || mb_strlen($title) < 3) {
        flash('error', 'Titel måste vara minst 3 tecken.');
        return null;
    }

    $categoryIds = [];
    if (is_array($categoryIdsRaw)) {
        foreach ($categoryIdsRaw as $rawCategoryId) {
            $categoryId = (int) $rawCategoryId;
            if ($categoryId <= 0 || in_array($categoryId, $categoryIds, true)) {
                continue;
            }
            $categoryIds[] = $categoryId;
        }
    }

    if (count($categoryIds) === 0) {
        flash('error', 'Välj minst en kategori.');
        return null;
    }

    if ($description === '' || mb_strlen($description) < 15) {
        flash('error', 'Beskrivningen måste vara minst 15 tecken.');
        return null;
    }

    if ($instructions === '' || mb_strlen($instructions) < 20) {
        flash('error', 'Instruktionerna måste vara minst 20 tecken.');
        return null;
    }

    $categoryPlaceholders = implode(', ', array_fill(0, count($categoryIds), '?'));
    $existingCategories = db_all(
        "SELECT id FROM categories WHERE id IN ({$categoryPlaceholders})",
        str_repeat('i', count($categoryIds)),
        $categoryIds
    );

    if (count($existingCategories) !== count($categoryIds)) {
        flash('error', 'En eller flera kategorier finns inte.');
        return null;
    }

    $ingredients = [];
    if (!is_array($ingredientNames)) {
        $ingredientNames = [];
    }
    if (!is_array($ingredientQty)) {
        $ingredientQty = [];
    }

    foreach ($ingredientNames as $idx => $rawName) {
        $name = normalize_ingredient_name((string) $rawName);
        $quantity = trim((string) ($ingredientQty[$idx] ?? ''));

        if ($name === '') {
            continue;
        }

        $ingredients[] = [
            'name' => $name,
            'quantity' => $quantity !== '' ? $quantity : null,
        ];
    }

    if (count($ingredients) === 0) {
        flash('error', 'Lägg till minst en ingrediens.');
        return null;
    }

    return [
        'title' => $title,
        'description' => $description,
        'instructions' => $instructions,
        'prep_minutes' => $prepMinutes,
        'cook_minutes' => $cookMinutes,
        'servings' => $servings,
        'category_ids' => $categoryIds,
        'ingredients' => $ingredients,
        'is_gluten_free' => is_array($badges) && isset($badges['gluten_free']) ? 1 : 0,
        'is_lactose_free' => is_array($badges) && isset($badges['lactose_free']) ? 1 : 0,
        'is_nut_free' => is_array($badges) && isset($badges['nut_free']) ? 1 : 0,
    ];
}

function sync_recipe_categories(int $recipeId, array $categoryIds): void
{
    db_execute('DELETE FROM recipe_categories WHERE recipe_id = ?', 'i', [$recipeId]);

    foreach ($categoryIds as $categoryId) {
        db_execute(
            'INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)',
            'ii',
            [$recipeId, (int) $categoryId]
        );
    }
}

function sync_recipe_ingredients(int $recipeId, array $ingredients): void
{
    db_execute('DELETE FROM recipe_ingredients WHERE recipe_id = ?', 'i', [$recipeId]);

    foreach ($ingredients as $ingredient) {
        $stmt = db_query(
            'INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)',
            's',
            [(string) $ingredient['name']]
        );
        $stmt->close();

        $ingredientId = (int) db()->insert_id;
        db_execute(
            'INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)',
            'iis',
            [$recipeId, $ingredientId, $ingredient['quantity']]
        );
    }
}

function recipe_summary(int $recipeId): ?array
{
    return db_one(
        'SELECT id, user_id, title, is_published
         FROM recipes
         WHERE id = ?
         LIMIT 1',
        'i',
        [$recipeId]
    );
}

function recipe_ingredients_with_inventory(int $recipeId, ?int $userId = null): array
{
    if ($userId !== null) {
        return db_all(
            'SELECT
                i.id AS ingredient_id,
                i.name,
                ri.quantity,
                CASE WHEN COUNT(ui.ingredient_id) > 0 THEN 1 ELSE 0 END AS has_ingredient
             FROM recipe_ingredients ri
             INNER JOIN ingredients i ON i.id = ri.ingredient_id
             LEFT JOIN user_inventory ui
                ON ui.ingredient_id = i.id
                AND ui.user_id = ?
             WHERE ri.recipe_id = ?
             GROUP BY i.id, i.name, ri.quantity
             ORDER BY i.name ASC',
            'ii',
            [$userId, $recipeId]
        );
    }

    return db_all(
        'SELECT
            i.id AS ingredient_id,
            i.name,
            ri.quantity,
            0 AS has_ingredient
         FROM recipe_ingredients ri
         INNER JOIN ingredients i ON i.id = ri.ingredient_id
         WHERE ri.recipe_id = ?
         ORDER BY i.name ASC',
        'i',
        [$recipeId]
    );
}

function action_toggle_favorite(): void
{
    require_login();

    $user = current_user();
    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    $recipe = recipe_summary($recipeId);

    if (!$user || !$recipe || (int) $recipe['is_published'] !== 1) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    $exists = db_one(
        'SELECT 1 FROM recipe_favorites WHERE user_id = ? AND recipe_id = ? LIMIT 1',
        'ii',
        [(int) $user['id'], $recipeId]
    );

    if ($exists) {
        db_execute(
            'DELETE FROM recipe_favorites WHERE user_id = ? AND recipe_id = ?',
            'ii',
            [(int) $user['id'], $recipeId]
        );
        flash('success', 'Receptet togs bort från favoriter.');
        return;
    }

    db_execute(
        'INSERT INTO recipe_favorites (user_id, recipe_id) VALUES (?, ?)',
        'ii',
        [(int) $user['id'], $recipeId]
    );
    flash('success', 'Receptet sparades i favoriter.');
}

function action_add_meal_plan_item(): void
{
    require_login();

    $user = current_user();
    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    $plannedDate = parse_date_input((string) ($_POST['planned_date'] ?? ''));
    $recipe = recipe_summary($recipeId);

    if (!$user || !$recipe || (int) $recipe['is_published'] !== 1) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    if (!$plannedDate) {
        flash('error', 'Välj ett giltigt datum för veckoplanen.');
        return;
    }

    db_execute(
        'INSERT IGNORE INTO meal_plan_items (user_id, recipe_id, planned_date) VALUES (?, ?, ?)',
        'iis',
        [(int) $user['id'], $recipeId, $plannedDate->format('Y-m-d')]
    );

    flash('success', 'Receptet lades till i veckoplanen.');
}

function action_remove_meal_plan_item(): void
{
    require_login();

    $user = current_user();
    $itemId = (int) ($_POST['meal_plan_item_id'] ?? 0);

    if (!$user || $itemId <= 0) {
        flash('error', 'Ogiltig planpost.');
        return;
    }

    $deleted = db_execute(
        'DELETE FROM meal_plan_items WHERE id = ? AND user_id = ?',
        'ii',
        [$itemId, (int) $user['id']]
    );

    flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Planposten togs bort.' : 'Planposten hittades inte.');
}

function action_add_recipe_to_shopping_list(): void
{
    require_login();

    $user = current_user();
    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    $onlyMissing = isset($_POST['only_missing']) && $_POST['only_missing'] === '1';
    $recipe = recipe_summary($recipeId);

    if (!$user || !$recipe || (int) $recipe['is_published'] !== 1) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    $ingredients = recipe_ingredients_with_inventory($recipeId, (int) $user['id']);
    $addedCount = 0;

    foreach ($ingredients as $ingredient) {
        if ($onlyMissing && (int) $ingredient['has_ingredient'] === 1) {
            continue;
        }

        $alreadyExists = db_one(
            'SELECT id
             FROM shopping_list_items
             WHERE user_id = ? AND recipe_id <=> ? AND ingredient_id <=> ? AND quantity <=> ? AND is_checked = 0
             LIMIT 1',
            'iiis',
            [
                (int) $user['id'],
                $recipeId,
                (int) $ingredient['ingredient_id'],
                (string) ($ingredient['quantity'] ?? ''),
            ]
        );

        if ($alreadyExists) {
            continue;
        }

        db_execute(
            'INSERT INTO shopping_list_items (user_id, recipe_id, ingredient_id, ingredient_name, quantity)
             VALUES (?, ?, ?, ?, ?)',
            'iiiss',
            [
                (int) $user['id'],
                $recipeId,
                (int) $ingredient['ingredient_id'],
                (string) $ingredient['name'],
                (string) ($ingredient['quantity'] ?? ''),
            ]
        );
        $addedCount++;
    }

    if ($addedCount === 0) {
        flash('success', $onlyMissing ? 'Inga nya saknade ingredienser behövde läggas till.' : 'Inga nya ingredienser behövde läggas till.');
        return;
    }

    flash('success', $addedCount . ' ingrediens' . ($addedCount === 1 ? '' : 'er') . ' lades till i inköpslistan.');
}

function action_toggle_shopping_list_item(): void
{
    require_login();

    $user = current_user();
    $itemId = (int) ($_POST['shopping_item_id'] ?? 0);

    if (!$user || $itemId <= 0) {
        flash('error', 'Ogiltig inköpspost.');
        return;
    }

    $item = db_one(
        'SELECT is_checked FROM shopping_list_items WHERE id = ? AND user_id = ? LIMIT 1',
        'ii',
        [$itemId, (int) $user['id']]
    );

    if (!$item) {
        flash('error', 'Inköpsposten hittades inte.');
        return;
    }

    $nextValue = (int) $item['is_checked'] === 1 ? 0 : 1;
    db_execute(
        'UPDATE shopping_list_items SET is_checked = ? WHERE id = ? AND user_id = ?',
        'iii',
        [$nextValue, $itemId, (int) $user['id']]
    );

    flash('success', $nextValue === 1 ? 'Inköpsposten markerades som klar.' : 'Inköpsposten markerades som aktiv.');
}

function action_delete_shopping_list_item(): void
{
    require_login();

    $user = current_user();
    $itemId = (int) ($_POST['shopping_item_id'] ?? 0);

    if (!$user || $itemId <= 0) {
        flash('error', 'Ogiltig inköpspost.');
        return;
    }

    $deleted = db_execute(
        'DELETE FROM shopping_list_items WHERE id = ? AND user_id = ?',
        'ii',
        [$itemId, (int) $user['id']]
    );

    flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Inköpsposten togs bort.' : 'Inköpsposten hittades inte.');
}

function action_clear_checked_shopping_items(): void
{
    require_login();

    $user = current_user();
    if (!$user) {
        return;
    }

    $deleted = db_execute(
        'DELETE FROM shopping_list_items WHERE user_id = ? AND is_checked = 1',
        'i',
        [(int) $user['id']]
    );

    flash('success', $deleted > 0 ? 'Avklarade inköpsposter rensades.' : 'Det fanns inga avklarade inköpsposter att rensa.');
}

function action_disconnect_google_keep(): void
{
    require_login();

    $user = current_user();
    if (!$user) {
        return;
    }

    google_keep_disconnect((int) $user['id']);
    flash('success', 'Google Keep-kopplingen togs bort.');
}

function action_send_missing_to_google_keep(): void
{
    require_login();

    $user = current_user();
    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    $recipe = recipe_summary($recipeId);

    if (!$user || !$recipe || (int) $recipe['is_published'] !== 1) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    if (!google_keep_is_configured()) {
        flash('error', 'Google Keep är inte konfigurerat ännu.');
        return;
    }

    if (!google_keep_is_connected((int) $user['id'])) {
        flash('error', 'Anslut Google Keep först.');
        return;
    }

    $ingredients = recipe_ingredients_with_inventory($recipeId, (int) $user['id']);
    $missingItems = [];

    foreach ($ingredients as $ingredient) {
        if ((int) $ingredient['has_ingredient'] === 1) {
            continue;
        }

        $missingItems[] = trim((string) ($ingredient['quantity'] ?? '')) !== ''
            ? (string) $ingredient['quantity'] . ' ' . (string) $ingredient['name']
            : (string) $ingredient['name'];
    }

    if (count($missingItems) === 0) {
        flash('success', 'Du har redan alla ingredienser hemma.');
        return;
    }

    google_keep_create_list_note(
        (int) $user['id'],
        'Saknade ingredienser: ' . (string) $recipe['title'],
        $missingItems
    );

    flash('success', 'Saknade ingredienser skickades till Google Keep.');
}

function action_rate_recipe(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        flash('error', 'Du måste vara inloggad för att kunna rösta.');
        return;
    }

    $recipeId = (int) ($_POST['recipe_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);

    if ($recipeId <= 0) {
        flash('error', 'Ogiltigt recept.');
        return;
    }

    if ($rating < 1 || $rating > 5) {
        flash('error', 'Betyget måste vara mellan 1 och 5 stjärnor.');
        return;
    }

    $recipe = db_one('SELECT id FROM recipes WHERE id = ? AND is_published = 1 LIMIT 1', 'i', [$recipeId]);
    if (!$recipe) {
        flash('error', 'Receptet finns inte.');
        return;
    }

    db_execute(
        'INSERT INTO recipe_ratings (recipe_id, user_id, rating)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP',
        'iii',
        [$recipeId, (int) $user['id'], $rating]
    );

    flash('success', 'Din röst har sparats.');
    $_POST['redirect_to'] = 'index.php?page=recipe&id=' . $recipeId;
}

function action_toggle_inventory(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    $enabled = bool_from_post('inventory_enabled') ? 1 : 0;
    db_execute('UPDATE users SET inventory_enabled = ? WHERE id = ?', 'ii', [$enabled, (int) $user['id']]);

    flash('success', $enabled ? 'Skafferifunktionen är på.' : 'Skafferifunktionen är av.');
}

function action_save_inventory(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    if ((int) $user['inventory_enabled'] !== 1) {
        flash('error', 'Aktivera skafferifunktionen först.');
        return;
    }

    $inventory = $_POST['inventory'] ?? [];
    $validLocations = ['pantry', 'fridge', 'freezer'];
    $userId = (int) $user['id'];
    $db = db();

    $db->begin_transaction();

    try {
        db_execute('DELETE FROM user_inventory WHERE user_id = ?', 'i', [$userId]);

        if (is_array($inventory)) {
            foreach ($inventory as $ingredientId => $locations) {
                $ingredientId = (int) $ingredientId;
                if ($ingredientId <= 0 || !is_array($locations)) {
                    continue;
                }

                foreach ($locations as $location) {
                    $location = (string) $location;
                    if (!in_array($location, $validLocations, true)) {
                        continue;
                    }

                    db_execute(
                        'INSERT INTO user_inventory (user_id, ingredient_id, location) VALUES (?, ?, ?)',
                        'iis',
                        [$userId, $ingredientId, $location]
                    );
                }
            }
        }

        $db->commit();
        flash('success', 'Ditt lager är uppdaterat.');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function action_add_inventory_item(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    if ((int) $user['inventory_enabled'] !== 1) {
        flash('error', 'Aktivera skafferifunktionen först.');
        return;
    }

    $rawName = (string) ($_POST['ingredient_name'] ?? '');
    $ingredientName = normalize_ingredient_name($rawName);
    $location = (string) ($_POST['location'] ?? 'pantry');
    $validLocations = ['pantry', 'fridge', 'freezer'];

    if ($ingredientName === '') {
        flash('error', 'Skriv en ingrediens att lägga till.');
        return;
    }

    if (!in_array($location, $validLocations, true)) {
        flash('error', 'Ogiltig plats för ingrediensen.');
        return;
    }

    $db = db();
    $db->begin_transaction();

    try {
        $statement = db_query(
            'INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)',
            's',
            [$ingredientName]
        );
        $statement->close();

        $ingredientId = (int) $db->insert_id;
        db_execute(
            'INSERT INTO user_inventory (user_id, ingredient_id, location)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE location = VALUES(location)',
            'iis',
            [(int) $user['id'], $ingredientId, $location]
        );

        $db->commit();
        flash('success', $ingredientName . ' är tillagd i ditt lager.');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function action_update_inventory_item(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    if ((int) $user['inventory_enabled'] !== 1) {
        flash('error', 'Aktivera skafferifunktionen först.');
        return;
    }

    $originalIngredientId = (int) ($_POST['ingredient_id'] ?? 0);
    $rawName = (string) ($_POST['ingredient_name'] ?? '');
    $ingredientName = normalize_ingredient_name($rawName);
    $location = (string) ($_POST['location'] ?? 'pantry');
    $validLocations = ['pantry', 'fridge', 'freezer'];
    $userId = (int) $user['id'];

    if ($originalIngredientId <= 0) {
        flash('error', 'Ogiltig ingrediens.');
        return;
    }

    if ($ingredientName === '') {
        flash('error', 'Ingrediensnamnet kan inte vara tomt.');
        return;
    }

    if (!in_array($location, $validLocations, true)) {
        flash('error', 'Ogiltig plats för ingrediensen.');
        return;
    }

    $existing = db_one(
        'SELECT 1
         FROM user_inventory
         WHERE user_id = ? AND ingredient_id = ?
         LIMIT 1',
        'ii',
        [$userId, $originalIngredientId]
    );

    if (!$existing) {
        flash('error', 'Ingrediensen finns inte i ditt lager.');
        return;
    }

    $db = db();
    $db->begin_transaction();

    try {
        $statement = db_query(
            'INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)',
            's',
            [$ingredientName]
        );
        $statement->close();

        $newIngredientId = (int) $db->insert_id;

        db_execute(
            'DELETE FROM user_inventory WHERE user_id = ? AND ingredient_id = ?',
            'ii',
            [$userId, $originalIngredientId]
        );

        db_execute(
            'INSERT INTO user_inventory (user_id, ingredient_id, location)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE location = VALUES(location)',
            'iis',
            [$userId, $newIngredientId, $location]
        );

        $db->commit();
        flash('success', 'Ingrediensen är uppdaterad.');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}

function action_delete_inventory_item(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    if ((int) $user['inventory_enabled'] !== 1) {
        flash('error', 'Aktivera skafferifunktionen först.');
        return;
    }

    $ingredientId = (int) ($_POST['ingredient_id'] ?? 0);
    $userId = (int) $user['id'];

    if ($ingredientId <= 0) {
        flash('error', 'Ogiltig ingrediens.');
        return;
    }

    $deleted = db_execute(
        'DELETE FROM user_inventory WHERE user_id = ? AND ingredient_id = ?',
        'ii',
        [$userId, $ingredientId]
    );

    if ($deleted > 0) {
        flash('success', 'Ingrediensen togs bort från ditt lager.');
    } else {
        flash('error', 'Ingrediensen fanns inte i ditt lager.');
    }
}

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

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $instructions = trim((string) ($_POST['instructions'] ?? ''));
    $prepMinutes = (int) ($_POST['prep_minutes'] ?? 0);
    $cookMinutes = (int) ($_POST['cook_minutes'] ?? 0);
    $servings = (int) ($_POST['servings'] ?? 1);
    $categoryIdsRaw = $_POST['category_ids'] ?? [];
    $badges = $_POST['badges'] ?? [];
    $isGlutenFree = is_array($badges) && isset($badges['gluten_free']) ? 1 : 0;
    $isLactoseFree = is_array($badges) && isset($badges['lactose_free']) ? 1 : 0;
    $isNutFree = is_array($badges) && isset($badges['nut_free']) ? 1 : 0;

    $ingredientNames = $_POST['ingredient_name'] ?? [];
    $ingredientQty = $_POST['ingredient_qty'] ?? [];

    if ($title === '' || mb_strlen($title) < 3) {
        flash('error', 'Titel måste vara minst 3 tecken.');
        return;
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
        return;
    }

    if ($description === '' || mb_strlen($description) < 15) {
        flash('error', 'Beskrivningen måste vara minst 15 tecken.');
        return;
    }

    if ($instructions === '' || mb_strlen($instructions) < 20) {
        flash('error', 'Instruktionerna måste vara minst 20 tecken.');
        return;
    }

    $categoryPlaceholders = implode(', ', array_fill(0, count($categoryIds), '?'));
    $existingCategories = db_all(
        "SELECT id FROM categories WHERE id IN ({$categoryPlaceholders})",
        str_repeat('i', count($categoryIds)),
        $categoryIds
    );

    if (count($existingCategories) !== count($categoryIds)) {
        flash('error', 'En eller flera kategorier finns inte.');
        return;
    }

    $ingredients = [];
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
        $slug = unique_recipe_slug($title);
        $primaryCategoryId = $categoryIds[0];

        db_execute(
            'INSERT INTO recipes (user_id, category_id, title, slug, description, image_path, instructions, prep_minutes, cook_minutes, servings, is_gluten_free, is_lactose_free, is_nut_free, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            'iisssssiiiiii',
            [
                (int) $author['id'],
                $primaryCategoryId,
                $title,
                $slug,
                $description,
                $uploadedImagePath,
                $instructions,
                max(0, $prepMinutes),
                max(0, $cookMinutes),
                max(1, $servings),
                $isGlutenFree,
                $isLactoseFree,
                $isNutFree,
            ]
        );

        $recipeId = (int) $db->insert_id;

        foreach ($categoryIds as $categoryId) {
            db_execute(
                'INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)',
                'ii',
                [$recipeId, $categoryId]
            );
        }

        foreach ($ingredients as $ingredient) {
            $stmt = db_query(
                'INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = VALUES(name)',
                's',
                [$ingredient['name']]
            );
            $stmt->close();

            $ingredientId = (int) $db->insert_id;
            db_execute(
                'INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)',
                'iis',
                [$recipeId, $ingredientId, $ingredient['quantity']]
            );
        }

        $db->commit();
        flash('success', 'Receptet publicerades.');
        $_POST['redirect_to'] = 'index.php?page=recipe&id=' . $recipeId;
    } catch (Throwable $exception) {
        $db->rollback();
        if ($uploadedImagePath) {
            $absoluteImagePath = uploads_base_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($uploadedImagePath, '/'));
            if (is_file($absoluteImagePath)) {
                @unlink($absoluteImagePath);
            }
        }
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

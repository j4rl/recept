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
            'toggle_inventory' => action_toggle_inventory(),
            'save_inventory' => action_save_inventory(),
            default => flash('error', 'Okand handling.'),
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
        flash('error', 'Losenorden matchar inte.');
        return;
    }

    if (!register_user($name, $email, $password)) {
        return;
    }

    $user = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', 's', [strtolower($email)]);
    if ($user) {
        login_user((int) $user['id']);
    }

    flash('success', 'Konto skapat. Du ar nu inloggad.');
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
    flash('success', 'Du ar utloggad.');
}

function action_create_recipe(): void
{
    require_login();

    $title = trim((string) ($_POST['title'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $instructions = trim((string) ($_POST['instructions'] ?? ''));
    $prepMinutes = (int) ($_POST['prep_minutes'] ?? 0);
    $cookMinutes = (int) ($_POST['cook_minutes'] ?? 0);
    $servings = (int) ($_POST['servings'] ?? 1);

    $ingredientNames = $_POST['ingredient_name'] ?? [];
    $ingredientQty = $_POST['ingredient_qty'] ?? [];

    if ($title === '' || mb_strlen($title) < 3) {
        flash('error', 'Titel maste vara minst 3 tecken.');
        return;
    }

    if ($categoryId <= 0) {
        flash('error', 'Valj en kategori.');
        return;
    }

    if ($description === '' || mb_strlen($description) < 15) {
        flash('error', 'Beskrivningen maste vara minst 15 tecken.');
        return;
    }

    if ($instructions === '' || mb_strlen($instructions) < 20) {
        flash('error', 'Instruktionerna maste vara minst 20 tecken.');
        return;
    }

    $category = db_one('SELECT id FROM categories WHERE id = ? LIMIT 1', 'i', [$categoryId]);
    if (!$category) {
        flash('error', 'Kategorin finns inte.');
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
        flash('error', 'Lagg till minst en ingrediens.');
        return;
    }

    $author = current_user();
    if (!$author) {
        flash('error', 'Du maste vara inloggad.');
        return;
    }

    $db = db();
    $db->begin_transaction();

    try {
        $slug = unique_recipe_slug($title);

        db_execute(
            'INSERT INTO recipes (user_id, category_id, title, slug, description, instructions, prep_minutes, cook_minutes, servings, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            'iissssiii',
            [
                (int) $author['id'],
                $categoryId,
                $title,
                $slug,
                $description,
                $instructions,
                max(0, $prepMinutes),
                max(0, $cookMinutes),
                max(1, $servings),
            ]
        );

        $recipeId = (int) $db->insert_id;

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
        throw $exception;
    }
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

    flash('success', $enabled ? 'Skafferifunktionen ar pa.' : 'Skafferifunktionen ar av.');
}

function action_save_inventory(): void
{
    require_login();
    $user = current_user();

    if (!$user) {
        return;
    }

    if ((int) $user['inventory_enabled'] !== 1) {
        flash('error', 'Aktivera skafferifunktionen forst.');
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
        flash('success', 'Ditt lager ar uppdaterat.');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
}


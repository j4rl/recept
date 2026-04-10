<?php
declare(strict_types=1);

require_login();

$recipeId = (int) ($_GET['id'] ?? 0);
$viewer = current_user();
$recipe = db_one(
    'SELECT id, user_id, title, description, image_path, instructions, prep_minutes, cook_minutes, servings, is_gluten_free, is_lactose_free, is_nut_free
     FROM recipes
     WHERE id = ?
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
        <p>Det kan ha tagits bort eller så är länken fel.</p>
        <a class="secondary-button" href="index.php">Till receptöversikten</a>
    </section>
    <?php
    return;
}

if (!can_manage_recipe($viewer, $recipe)) {
    http_response_code(403);
    $pageTitle = app_config('app_name') . ' - Ingen behörighet';
    ?>
    <section class="empty-card">
        <h1>Ingen behörighet</h1>
        <p>Endast skaparen av receptet eller en administratör får redigera det.</p>
        <a class="secondary-button" href="index.php?page=recipe&id=<?= e((string) $recipeId) ?>">Tillbaka till receptet</a>
    </section>
    <?php
    return;
}

$categories = db_all('SELECT id, name FROM categories ORDER BY name');
$selectedCategoryRows = db_all(
    'SELECT category_id FROM recipe_categories WHERE recipe_id = ? ORDER BY category_id',
    'i',
    [$recipeId]
);
$ingredientRows = db_all(
    'SELECT i.name, ri.quantity
     FROM recipe_ingredients ri
     INNER JOIN ingredients i ON i.id = ri.ingredient_id
     WHERE ri.recipe_id = ?
     ORDER BY i.name ASC',
    'i',
    [$recipeId]
);

$pageTitle = app_config('app_name') . ' - Redigera ' . $recipe['title'];
$recipeFormTitle = 'Redigera recept';
$recipeFormLead = 'Uppdatera innehåll, ingredienser eller bild. Lämna bildfältet tomt för att behålla nuvarande bild.';
$recipeFormAction = 'update_recipe';
$recipeFormSubmitLabel = 'Spara ändringar';
$recipeFormRedirect = 'index.php?page=edit&id=' . $recipeId;
$recipeFormValues = [
    'title' => (string) $recipe['title'],
    'description' => (string) $recipe['description'],
    'instructions' => (string) $recipe['instructions'],
    'prep_minutes' => (int) $recipe['prep_minutes'],
    'cook_minutes' => (int) $recipe['cook_minutes'],
    'servings' => (int) $recipe['servings'],
    'is_gluten_free' => (int) $recipe['is_gluten_free'],
    'is_lactose_free' => (int) $recipe['is_lactose_free'],
    'is_nut_free' => (int) $recipe['is_nut_free'],
    'image_path' => (string) ($recipe['image_path'] ?? ''),
];
$recipeFormSelectedCategoryIds = array_map(
    static fn (array $row): int => (int) $row['category_id'],
    $selectedCategoryRows
);
$recipeFormIngredientRows = array_map(
    static fn (array $row): array => [
        'name' => (string) $row['name'],
        'quantity' => (string) ($row['quantity'] ?? ''),
    ],
    $ingredientRows
);

if (count($recipeFormIngredientRows) === 0) {
    $recipeFormIngredientRows[] = ['name' => '', 'quantity' => ''];
}
?>

<?php require __DIR__ . '/../partials/recipe_form.php'; ?>

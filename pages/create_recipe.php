<?php
declare(strict_types=1);

require_login();

$pageTitle = app_config('app_name') . ' - Publicera recept';
$categories = db_all('SELECT id, name FROM categories ORDER BY name');
$recipeFormTitle = 'Publicera nytt recept';
$recipeFormLead = 'Beskriv tydligt, lägg till ingredienser och publicera direkt.';
$recipeFormAction = 'create_recipe';
$recipeFormSubmitLabel = 'Publicera recept';
$recipeFormRedirect = 'index.php?page=create';
$recipeFormValues = [
    'title' => '',
    'description' => '',
    'instructions' => '',
    'prep_minutes' => 10,
    'cook_minutes' => 20,
    'servings' => 4,
    'is_gluten_free' => 0,
    'is_lactose_free' => 0,
    'is_nut_free' => 0,
    'image_path' => '',
];
$recipeFormSelectedCategoryIds = [];
$recipeFormIngredientRows = [
    ['name' => '', 'quantity' => ''],
    ['name' => '', 'quantity' => ''],
];
?>
<?php require __DIR__ . '/../partials/recipe_form.php'; ?>

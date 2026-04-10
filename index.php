<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

handle_actions();

$routes = [
    'home' => __DIR__ . '/pages/home.php',
    'recipe' => __DIR__ . '/pages/recipe.php',
    'login' => __DIR__ . '/pages/login.php',
    'register' => __DIR__ . '/pages/register.php',
    'create' => __DIR__ . '/pages/create_recipe.php',
    'edit' => __DIR__ . '/pages/edit_recipe.php',
    'inventory' => __DIR__ . '/pages/inventory.php',
    'favorites' => __DIR__ . '/pages/favorites.php',
    'planner' => __DIR__ . '/pages/planner.php',
    'shopping_list' => __DIR__ . '/pages/shopping_list.php',
    'history' => __DIR__ . '/pages/history.php',
    'keep_connect' => __DIR__ . '/pages/keep_connect.php',
    'keep_callback' => __DIR__ . '/pages/keep_callback.php',
];

$page = (string) ($_GET['page'] ?? 'home');
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'home';
    flash('error', 'Sidan hittades inte.');
}

$pageTitle = app_config('app_name') . ' - Recept';

ob_start();
require $routes[$page];
$content = ob_get_clean();

require __DIR__ . '/partials/header.php';
echo $content;
require __DIR__ . '/partials/footer.php';

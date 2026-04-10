<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$requestedWeek = parse_date_input((string) ($_GET['week'] ?? ''));
$weekStart = week_start($requestedWeek);
$weekEnd = $weekStart->modify('+6 day');
$days = week_dates($weekStart);

$pageTitle = app_config('app_name') . ' - Veckoplan';
$mealPlanRows = db_all(
    'SELECT
        mpi.id,
        mpi.planned_date,
        r.id AS recipe_id,
        r.title,
        r.description,
        r.prep_minutes,
        r.cook_minutes
     FROM meal_plan_items mpi
     INNER JOIN recipes r ON r.id = mpi.recipe_id
     WHERE mpi.user_id = ?
       AND mpi.planned_date BETWEEN ? AND ?
     ORDER BY mpi.planned_date ASC, mpi.created_at ASC',
    'iss',
    [(int) $viewer['id'], $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]
);

$planMap = [];
foreach ($mealPlanRows as $row) {
    $dateKey = (string) $row['planned_date'];
    if (!isset($planMap[$dateKey])) {
        $planMap[$dateKey] = [];
    }
    $planMap[$dateKey][] = $row;
}

$recipeOptions = db_all(
    'SELECT id, title
     FROM recipes
     WHERE is_published = 1
     ORDER BY created_at DESC
     LIMIT 80'
);
?>

<section class="planner-shell">
    <div class="planner-toolbar">
        <div>
            <h1>Veckoplanerare</h1>
            <p class="helper-text">Planera veckan genom att lägga recept på en dag i taget.</p>
        </div>
        <div class="planner-week-nav">
            <a class="secondary-button" href="index.php?page=planner&week=<?= e($weekStart->modify('-7 day')->format('Y-m-d')) ?>">Föregående vecka</a>
            <span><?= e($weekStart->format('Y-m-d')) ?> till <?= e($weekEnd->format('Y-m-d')) ?></span>
            <a class="secondary-button" href="index.php?page=planner&week=<?= e($weekStart->modify('+7 day')->format('Y-m-d')) ?>">Nästa vecka</a>
        </div>
    </div>

    <section class="inventory-panel">
        <h2>Lägg till recept i veckan</h2>
        <form method="post" action="index.php" class="planner-add-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_meal_plan_item">
            <input type="hidden" name="redirect_to" value="index.php?page=planner&week=<?= e($weekStart->format('Y-m-d')) ?>">

            <label>
                <span>Recept</span>
                <select name="recipe_id" required>
                    <option value="">Välj recept</option>
                    <?php foreach ($recipeOptions as $recipe): ?>
                        <option value="<?= e((string) $recipe['id']) ?>"><?= e($recipe['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Datum</span>
                <input type="date" name="planned_date" value="<?= e($weekStart->format('Y-m-d')) ?>" required>
            </label>

            <button type="submit">Lägg till</button>
        </form>
    </section>

    <div class="planner-grid">
        <?php foreach ($days as $day): ?>
            <?php $items = $planMap[$day->format('Y-m-d')] ?? []; ?>
            <section class="detail-card planner-day">
                <div class="planner-day-head">
                    <h2><?= e(swedish_weekday_name($day)) ?></h2>
                    <span><?= e(short_swedish_date($day)) ?></span>
                </div>

                <?php if (count($items) === 0): ?>
                    <p class="helper-text">Inget planerat.</p>
                <?php else: ?>
                    <div class="planner-day-items">
                        <?php foreach ($items as $item): ?>
                            <article class="planner-item">
                                <div>
                                    <h3><a href="index.php?page=recipe&id=<?= e((string) $item['recipe_id']) ?>"><?= e($item['title']) ?></a></h3>
                                    <p><?= e($item['description']) ?></p>
                                    <small><?= e((string) minutes_total((int) $item['prep_minutes'], (int) $item['cook_minutes'])) ?> min</small>
                                </div>
                                <form method="post" action="index.php">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="remove_meal_plan_item">
                                    <input type="hidden" name="meal_plan_item_id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="redirect_to" value="index.php?page=planner&week=<?= e($weekStart->format('Y-m-d')) ?>">
                                    <button type="submit" class="danger-button">Ta bort</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</section>

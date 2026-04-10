<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$pageTitle = app_config('app_name') . ' - Favoriter';
$favorites = db_all(
    'SELECT
        r.id,
        r.title,
        r.description,
        r.image_path,
        r.prep_minutes,
        r.cook_minutes,
        r.created_at,
        f.created_at AS favorited_at,
        COALESCE(cat.categories, "") AS category_list,
        COALESCE(rt.avg_rating, 0) AS avg_rating,
        COALESCE(rt.rating_count, 0) AS rating_count
     FROM recipe_favorites f
     INNER JOIN recipes r ON r.id = f.recipe_id
     LEFT JOIN (
        SELECT
            rc.recipe_id,
            GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS categories
        FROM recipe_categories rc
        INNER JOIN categories c ON c.id = rc.category_id
        GROUP BY rc.recipe_id
     ) cat ON cat.recipe_id = r.id
     LEFT JOIN (
        SELECT recipe_id, AVG(rating) AS avg_rating, COUNT(*) AS rating_count
        FROM recipe_ratings
        GROUP BY recipe_id
     ) rt ON rt.recipe_id = r.id
     WHERE f.user_id = ? AND r.is_published = 1
     ORDER BY f.created_at DESC',
    'i',
    [(int) $viewer['id']]
);
?>

<section class="home-shell">
    <div class="section-head">
        <h2>Favoriter</h2>
    </div>

    <?php if (count($favorites) === 0): ?>
        <article class="empty-card">
            <h3>Inga favoriter ännu</h3>
            <p>Öppna ett recept och spara det som favorit för att bygga din egen lista.</p>
        </article>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($favorites as $recipe): ?>
                <?php
                $imagePath = recipe_image_url((string) ($recipe['image_path'] ?? ''));
                $totalTime = minutes_total((int) $recipe['prep_minutes'], (int) $recipe['cook_minutes']);
                $avgRating = (float) $recipe['avg_rating'];
                $ratingCount = (int) $recipe['rating_count'];
                $ratingPercent = max(0, min(100, ($avgRating / 5) * 100));
                ?>
                <article class="recipe-card favorite-card">
                    <img src="<?= e($imagePath) ?>" alt="<?= e($recipe['title']) ?>" loading="lazy" class="recipe-tile-image">
                    <h3><a href="index.php?page=recipe&id=<?= e((string) $recipe['id']) ?>"><?= e($recipe['title']) ?></a></h3>
                    <p><?= e($recipe['description']) ?></p>
                    <p class="category-line"><?= e((string) $recipe['category_list']) ?></p>
                    <div class="recipe-meta">
                        <span><?= e((string) $totalTime) ?> min</span>
                        <span>Sparad <?= e(date('Y-m-d', strtotime((string) $recipe['favorited_at']))) ?></span>
                    </div>
                    <div class="rating-line">
                        <span class="star-rating" role="img" aria-label="Betyg <?= e(number_format($avgRating, 1, ',', ' ')) ?> av 5">
                            <span class="star-rating-base">★★★★★</span>
                            <span class="star-rating-fill" style="width: <?= e(number_format($ratingPercent, 2, '.', '')) ?>%;">★★★★★</span>
                        </span>
                        <span><?= $ratingCount > 0 ? e(number_format($avgRating, 1, ',', ' ')) . ' (' . e((string) $ratingCount) . ')' : 'Inga röster ännu' ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

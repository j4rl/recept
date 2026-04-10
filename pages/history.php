<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$pageTitle = app_config('app_name') . ' - Historik';
$historyRows = db_all(
    'SELECT
        rr.recipe_id,
        rr.rating,
        rr.created_at,
        rr.updated_at,
        r.title,
        r.description
     FROM recipe_ratings rr
     INNER JOIN recipes r ON r.id = rr.recipe_id
     WHERE rr.user_id = ?
     ORDER BY COALESCE(rr.updated_at, rr.created_at) DESC',
    'i',
    [(int) $viewer['id']]
);
?>

<section class="inventory-shell">
    <h1>Recepthistorik</h1>
    <p>Här ser du vilka recept du har röstat på och när senaste ändringen gjordes.</p>

    <section class="inventory-panel">
        <?php if (count($historyRows) === 0): ?>
            <article class="empty-card">
                <h3>Ingen historik ännu</h3>
                <p>Rösta på ett recept för att få en egen historik här.</p>
            </article>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($historyRows as $row): ?>
                    <article class="history-item">
                        <div>
                            <h2><a href="index.php?page=recipe&id=<?= e((string) $row['recipe_id']) ?>"><?= e($row['title']) ?></a></h2>
                            <p><?= e($row['description']) ?></p>
                        </div>
                        <div class="history-meta">
                            <strong><?= e((string) $row['rating']) ?>/5</strong>
                            <span>Först röstat: <?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></span>
                            <span>Senast ändrat: <?= e(date('Y-m-d H:i', strtotime((string) $row['updated_at']))) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

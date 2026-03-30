<?php
declare(strict_types=1);

require_login();

$pageTitle = app_config('app_name') . ' - Publicera recept';
$categories = db_all('SELECT id, name FROM categories ORDER BY name');
?>

<section class="form-shell">
    <h1>Publicera nytt recept</h1>
    <p>Beskriv tydligt, lagg till ingredienser och publicera direkt.</p>

    <?php if (count($categories) === 0): ?>
        <article class="empty-card">
            <h2>Inga kategorier hittades</h2>
            <p>Importera databasens standardkategorier via <code>database/schema.sql</code>.</p>
        </article>
    <?php else: ?>
        <form method="post" action="index.php" class="stack-form" data-recipe-form>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_recipe">
            <input type="hidden" name="redirect_to" value="index.php?page=create">

            <div class="form-row">
                <div>
                    <label for="title">Titel</label>
                    <input id="title" name="title" type="text" required minlength="3" maxlength="140" placeholder="Ex: Kramig svamppasta">
                </div>
                <div>
                    <label for="category_id">Kategori</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Valj kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="description">Kort beskrivning</label>
            <textarea id="description" name="description" rows="3" required minlength="15" placeholder="Vad gor receptet gott och nar passar det?"></textarea>

            <label for="instructions">Tillagningsinstruktion</label>
            <textarea id="instructions" name="instructions" rows="7" required minlength="20" placeholder="Skriv steg for steg."></textarea>

            <div class="form-row">
                <div>
                    <label for="prep_minutes">Forberedelsetid (min)</label>
                    <input id="prep_minutes" name="prep_minutes" type="number" min="0" step="1" value="10">
                </div>
                <div>
                    <label for="cook_minutes">Tillagningstid (min)</label>
                    <input id="cook_minutes" name="cook_minutes" type="number" min="0" step="1" value="20">
                </div>
                <div>
                    <label for="servings">Portioner</label>
                    <input id="servings" name="servings" type="number" min="1" step="1" value="4">
                </div>
            </div>

            <div class="ingredient-section">
                <div class="section-heading">
                    <h2>Ingredienser</h2>
                    <button type="button" class="secondary-button" data-add-ingredient-row>Lagg till rad</button>
                </div>

                <div class="ingredient-grid" data-ingredient-grid>
                    <div class="ingredient-row">
                        <input type="text" name="ingredient_name[]" placeholder="Ingrediens (ex: Morot)">
                        <input type="text" name="ingredient_qty[]" placeholder="Mangd (ex: 2 st)">
                        <button type="button" class="icon-button" data-remove-ingredient-row aria-label="Ta bort">x</button>
                    </div>
                    <div class="ingredient-row">
                        <input type="text" name="ingredient_name[]" placeholder="Ingrediens (ex: Vitlok)">
                        <input type="text" name="ingredient_qty[]" placeholder="Mangd (ex: 2 klyftor)">
                        <button type="button" class="icon-button" data-remove-ingredient-row aria-label="Ta bort">x</button>
                    </div>
                </div>
            </div>

            <button type="submit">Publicera recept</button>
        </form>
    <?php endif; ?>
</section>


(() => {
    const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
    const mobileNav = document.querySelector('[data-mobile-nav]');

    if (mobileToggle && mobileNav) {
        mobileToggle.addEventListener('click', () => {
            mobileNav.classList.toggle('is-open');
        });
    }

    const recipeForm = document.querySelector('[data-recipe-form]');
    if (recipeForm) {
        const grid = recipeForm.querySelector('[data-ingredient-grid]');
        const addButton = recipeForm.querySelector('[data-add-ingredient-row]');

        const wireRemoveButtons = () => {
            grid.querySelectorAll('[data-remove-ingredient-row]').forEach((button) => {
                button.onclick = () => {
                    const rows = grid.querySelectorAll('.ingredient-row');
                    if (rows.length <= 1) {
                        const inputs = button.closest('.ingredient-row')?.querySelectorAll('input');
                        inputs?.forEach((input) => {
                            input.value = '';
                        });
                        return;
                    }
                    button.closest('.ingredient-row')?.remove();
                };
            });
        };

        addButton?.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <input type="text" name="ingredient_name[]" placeholder="Ingrediens (ex: Broccoli)">
                <input type="text" name="ingredient_qty[]" placeholder="Mangd (ex: 250 g)">
                <button type="button" class="icon-button" data-remove-ingredient-row aria-label="Ta bort">x</button>
            `;
            grid.appendChild(row);
            wireRemoveButtons();
        });

        wireRemoveButtons();
    }

    const inventoryFilter = document.querySelector('[data-inventory-filter]');
    if (inventoryFilter) {
        const names = document.querySelectorAll('[data-inventory-name]');
        inventoryFilter.addEventListener('input', () => {
            const term = inventoryFilter.value.trim().toLowerCase();
            names.forEach((item) => {
                const rowVisible = item.dataset.inventoryName?.includes(term) ?? false;
                const row = item;
                row.style.display = rowVisible ? '' : 'none';

                let next = row.nextElementSibling;
                for (let i = 0; i < 3; i++) {
                    if (!next) {
                        break;
                    }
                    next.style.display = rowVisible ? '' : 'none';
                    next = next.nextElementSibling;
                }
            });
        });
    }
})();


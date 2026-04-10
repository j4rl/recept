(() => {
    const root = document.documentElement;
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const themeSwitch = document.querySelector('[data-theme-switch]');
    const themeOptions = Array.from(document.querySelectorAll('[data-theme-option]'));

    const normalizePreference = (value) => {
        if (value === 'light' || value === 'dark' || value === 'auto') {
            return value;
        }
        return 'auto';
    };

    const applyTheme = (preference) => {
        const normalized = normalizePreference(preference);
        const theme = normalized === 'auto'
            ? (mediaQuery.matches ? 'dark' : 'light')
            : normalized;

        root.setAttribute('data-theme-preference', normalized);
        root.setAttribute('data-theme', theme);
        themeOptions.forEach((button) => {
            const isActive = button.dataset.themeOption === normalized;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const getStoredPreference = () => {
        try {
            return normalizePreference(localStorage.getItem('theme_preference') || 'auto');
        } catch (error) {
            return 'auto';
        }
    };

    const setStoredPreference = (preference) => {
        try {
            localStorage.setItem('theme_preference', preference);
        } catch (error) {
            // Ignorera om localStorage inte är tillgängligt.
        }
    };

    let themePreference = getStoredPreference();
    applyTheme(themePreference);

    if (themeSwitch) {
        themeSwitch.addEventListener('click', (event) => {
            const button = event.target.closest('[data-theme-option]');
            if (!button) {
                return;
            }

            themePreference = normalizePreference(button.dataset.themeOption || 'auto');
            setStoredPreference(themePreference);
            applyTheme(themePreference);
        });
    }

    mediaQuery.addEventListener('change', () => {
        if (themePreference === 'auto') {
            applyTheme('auto');
        }
    });

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
                <input type="text" name="ingredient_qty[]" placeholder="Mängd (ex: 250 g)">
                <button type="button" class="icon-button" data-remove-ingredient-row aria-label="Ta bort">x</button>
            `;
            grid.appendChild(row);
            wireRemoveButtons();
        });

        wireRemoveButtons();
    }

    const canCookCheckbox = document.querySelector('[data-can-cook-checkbox]');
    const allowMissingCheckbox = document.querySelector('[data-allow-missing-checkbox]');
    if (canCookCheckbox && allowMissingCheckbox) {
        const syncAllowMissingState = () => {
            const enabled = canCookCheckbox.checked;
            allowMissingCheckbox.disabled = !enabled;
            if (!enabled) {
                allowMissingCheckbox.checked = false;
            }
        };

        canCookCheckbox.addEventListener('change', syncAllowMissingState);
        syncAllowMissingState();
    }

    const servingScaler = document.querySelector('[data-serving-scaler]');
    if (servingScaler) {
        const servingInput = servingScaler.querySelector('[data-serving-input]');
        const decreaseButton = servingScaler.querySelector('[data-serving-decrease]');
        const increaseButton = servingScaler.querySelector('[data-serving-increase]');
        const servingsOutput = document.querySelector('[data-current-servings]');
        const scalableQuantities = Array.from(document.querySelectorAll('[data-scalable-qty]'));
        const baseServingsValue = Number.parseInt(servingScaler.dataset.baseServings || '1', 10);
        const baseServings = Number.isFinite(baseServingsValue) && baseServingsValue > 0 ? baseServingsValue : 1;
        const originalQuantities = scalableQuantities.map((item) => item.dataset.originalQty ?? item.textContent ?? '');

        const unicodeFractions = {
            '¼': '1/4',
            '½': '1/2',
            '¾': '3/4',
            '⅓': '1/3',
            '⅔': '2/3',
            '⅛': '1/8',
            '⅜': '3/8',
            '⅝': '5/8',
            '⅞': '7/8',
        };

        const normalizeFractions = (value) => value.replace(/[¼½¾⅓⅔⅛⅜⅝⅞]/g, (match, offset, text) => {
            const mapped = unicodeFractions[match] || match;
            const prevChar = offset > 0 ? text[offset - 1] : '';
            return /\d/.test(prevChar) ? ` ${mapped}` : mapped;
        });

        const parseNumericToken = (token) => {
            const trimmed = token.trim();

            const mixed = trimmed.match(/^(\d+)\s+(\d+)\/(\d+)$/);
            if (mixed) {
                const denominator = Number.parseInt(mixed[3], 10);
                if (denominator === 0) {
                    return null;
                }
                return Number.parseInt(mixed[1], 10) + (Number.parseInt(mixed[2], 10) / denominator);
            }

            const fraction = trimmed.match(/^(\d+)\/(\d+)$/);
            if (fraction) {
                const denominator = Number.parseInt(fraction[2], 10);
                if (denominator === 0) {
                    return null;
                }
                return Number.parseInt(fraction[1], 10) / denominator;
            }

            const numeric = Number.parseFloat(trimmed.replace(',', '.'));
            return Number.isFinite(numeric) ? numeric : null;
        };

        const formatNumericValue = (value) => {
            const rounded = Math.round(value * 100) / 100;
            if (Math.abs(rounded - Math.round(rounded)) < 0.0001) {
                return String(Math.round(rounded));
            }
            return rounded.toFixed(2).replace('.', ',').replace(/,?0+$/, '');
        };

        const scaleQuantityText = (quantity, factor) => {
            if (Math.abs(factor - 1) < 0.0001) {
                return quantity;
            }

            const normalized = normalizeFractions(quantity);
            const numberPattern = '(\\d+\\s+\\d+/\\d+|\\d+/\\d+|\\d+(?:[.,]\\d+)?)';
            const rangePattern = new RegExp(`${numberPattern}(\\s*[-–]\\s*)${numberPattern}`);
            const rangeMatch = normalized.match(rangePattern);

            if (rangeMatch) {
                const from = parseNumericToken(rangeMatch[1]);
                const to = parseNumericToken(rangeMatch[3]);
                if (from !== null && to !== null) {
                    const scaledFrom = formatNumericValue(from * factor);
                    const scaledTo = formatNumericValue(to * factor);
                    return normalized.replace(rangeMatch[0], `${scaledFrom}${rangeMatch[2]}${scaledTo}`);
                }
            }

            const valuePattern = new RegExp(numberPattern);
            const valueMatch = normalized.match(valuePattern);
            if (!valueMatch) {
                return quantity;
            }

            const parsed = parseNumericToken(valueMatch[1]);
            if (parsed === null) {
                return quantity;
            }

            const scaled = formatNumericValue(parsed * factor);
            return normalized.replace(valueMatch[1], scaled);
        };

        const clampServings = (value) => {
            if (!Number.isFinite(value)) {
                return baseServings;
            }
            return Math.max(1, Math.min(200, Math.round(value)));
        };

        const renderScaledServings = (nextServings) => {
            const servings = clampServings(nextServings);
            const factor = servings / baseServings;

            if (servingInput) {
                servingInput.value = String(servings);
            }
            if (servingsOutput) {
                servingsOutput.textContent = String(servings);
            }

            scalableQuantities.forEach((item, index) => {
                item.textContent = scaleQuantityText(originalQuantities[index], factor);
            });
        };

        decreaseButton?.addEventListener('click', () => {
            const currentValue = Number.parseInt(servingInput?.value ?? String(baseServings), 10);
            renderScaledServings(currentValue - 1);
        });

        increaseButton?.addEventListener('click', () => {
            const currentValue = Number.parseInt(servingInput?.value ?? String(baseServings), 10);
            renderScaledServings(currentValue + 1);
        });

        servingInput?.addEventListener('input', () => {
            const nextValue = Number.parseInt(servingInput.value, 10);
            if (Number.isFinite(nextValue)) {
                renderScaledServings(nextValue);
            }
        });

        servingInput?.addEventListener('blur', () => {
            const nextValue = Number.parseInt(servingInput.value, 10);
            renderScaledServings(nextValue);
        });

        renderScaledServings(baseServings);
    }

    const inventoryFilter = document.querySelector('[data-inventory-filter]');
    if (inventoryFilter) {
        const names = Array.from(document.querySelectorAll('[data-inventory-name]'));
        inventoryFilter.addEventListener('input', () => {
            const term = inventoryFilter.value.trim().toLowerCase();
            names.forEach((item) => {
                const rowVisible = item.dataset.inventoryName?.includes(term) ?? false;
                const itemId = item.dataset.inventoryItem;
                if (!itemId) {
                    return;
                }

                document.querySelectorAll(`[data-inventory-item="${itemId}"]`).forEach((cell) => {
                    cell.style.display = rowVisible ? '' : 'none';
                });
            });
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm || 'Är du säker?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();

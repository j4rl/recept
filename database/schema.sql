SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS receptdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE receptdb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    inventory_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(90) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(140) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    instructions TEXT NOT NULL,
    prep_minutes INT NOT NULL DEFAULT 0,
    cook_minutes INT NOT NULL DEFAULT 0,
    servings INT NOT NULL DEFAULT 1,
    is_gluten_free TINYINT(1) NOT NULL DEFAULT 0,
    is_lactose_free TINYINT(1) NOT NULL DEFAULT 0,
    is_nut_free TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipes_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT,
    INDEX idx_recipes_category (category_id),
    INDEX idx_recipes_published_created (is_published, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE recipes ADD COLUMN IF NOT EXISTS is_gluten_free TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS is_lactose_free TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS is_nut_free TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS recipe_categories (
    recipe_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (recipe_id, category_id),
    CONSTRAINT fk_recipe_categories_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_categories_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE,
    INDEX idx_recipe_categories_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity VARCHAR(80) DEFAULT NULL,
    PRIMARY KEY (recipe_id, ingredient_id),
    CONSTRAINT fk_recipe_ingredients_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_ingredients_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_inventory (
    user_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    location ENUM('pantry', 'fridge', 'freezer') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, ingredient_id, location),
    CONSTRAINT fk_user_inventory_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_inventory_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
        ON DELETE CASCADE,
    INDEX idx_user_inventory_user (user_id),
    INDEX idx_user_inventory_ingredient (ingredient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ratings (
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (recipe_id, user_id),
    CONSTRAINT fk_recipe_ratings_recipe
        FOREIGN KEY (recipe_id) REFERENCES recipes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_recipe_ratings_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_recipe_rating_range CHECK (rating BETWEEN 1 AND 5),
    INDEX idx_recipe_ratings_recipe (recipe_id),
    INDEX idx_recipe_ratings_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, slug) VALUES
    ('Förrätter', 'forratter'),
    ('Varmrätter', 'varmratter'),
    ('Efterrätter', 'efterratter'),
    ('Såser', 'saser'),
    ('Sallader', 'sallader'),
    ('Soppor', 'soppor'),
    ('Vegetariskt', 'vegetariskt'),
    ('Bakverk', 'bakverk')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO recipe_categories (recipe_id, category_id)
SELECT id, category_id
FROM recipes
ON DUPLICATE KEY UPDATE category_id = VALUES(category_id);

ALTER DATABASE receptdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE recipes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE recipe_categories CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ingredients CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE recipe_ingredients CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE user_inventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE recipe_ratings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

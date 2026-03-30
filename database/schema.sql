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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(90) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(140) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    instructions TEXT NOT NULL,
    prep_minutes INT NOT NULL DEFAULT 0,
    cook_minutes INT NOT NULL DEFAULT 0,
    servings INT NOT NULL DEFAULT 1,
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

INSERT INTO categories (name, slug) VALUES
    ('Forratter', 'forratter'),
    ('Varmratter', 'varmratter'),
    ('Efterratter', 'efterratter'),
    ('Saser', 'saser'),
    ('Sallader', 'sallader'),
    ('Soppor', 'soppor'),
    ('Vegetariskt', 'vegetariskt'),
    ('Bakverk', 'bakverk')
ON DUPLICATE KEY UPDATE name = VALUES(name);


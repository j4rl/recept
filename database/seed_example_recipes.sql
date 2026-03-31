USE receptdb;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO users (name, email, password_hash, inventory_enabled)
VALUES ('Demo Kock', 'demo@matarkiv.se', '$2y$10$crNXioncf2ahYPBqElspReXer7ocuRN.aGmOxUL26QVo1R3sDnZde', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    inventory_enabled = VALUES(inventory_enabled),
    id = LAST_INSERT_ID(id);

DROP TEMPORARY TABLE IF EXISTS seed_recipes;
CREATE TEMPORARY TABLE seed_recipes (
    slug VARCHAR(180) PRIMARY KEY,
    title VARCHAR(140) NOT NULL,
    category_slug VARCHAR(90) NOT NULL,
    description VARCHAR(255) NOT NULL,
    instructions TEXT NOT NULL,
    prep_minutes INT NOT NULL,
    cook_minutes INT NOT NULL,
    servings INT NOT NULL,
    is_gluten_free TINYINT(1) NOT NULL DEFAULT 0,
    is_lactose_free TINYINT(1) NOT NULL DEFAULT 0,
    is_nut_free TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO seed_recipes (
    slug, title, category_slug, description, instructions, prep_minutes, cook_minutes, servings, is_gluten_free, is_lactose_free, is_nut_free
) VALUES
    ('bruschetta-tomat-basilika', 'Bruschetta med tomat och basilika', 'forratter', 'Krispig bruschetta med vitlök, tomat och basilika.', 'Rosta brödskivor, gnid med vitlök och toppa med tomat, basilika och olivolja. Servera direkt.', 10, 5, 4, 0, 1, 1),
    ('rodbetstartar-getost', 'Rödbetstartar med getost', 'forratter', 'Frisk förrätt med rödbetor, getost och honung.', 'Skiva kokta rödbetor, varva med getost, ringla honung och toppa med rostade frön.', 15, 0, 4, 1, 1, 1),
    ('avokado-rakrora', 'Avokado med räkröra', 'forratter', 'Krämig avokado fylld med klassisk räkröra.', 'Blanda räkor, majonnäs, dill och citron. Fyll avokadohalvor och servera kallt.', 15, 0, 2, 1, 1, 1),

    ('kyckling-i-currysas', 'Kyckling i currysås', 'varmratter', 'Snabb vardagsrätt med mild currysås och ris.', 'Stek kyckling, fräs lök och curry, tillsätt grädde och låt sjuda. Servera med ris.', 15, 25, 4, 1, 0, 1),
    ('ugnsbakad-lax-med-dill', 'Ugnsbakad lax med dill', 'varmratter', 'Saftig lax med citron, dill och potatis.', 'Lägg lax i form, krydda med citron och dill. Baka i ugn tills fisken är klar.', 10, 20, 4, 1, 1, 1),
    ('kottbullar-med-potatismos', 'Köttbullar med potatismos', 'varmratter', 'Svensk klassiker med gräddsås och lingon.', 'Stek köttbullar, koka potatis och mosa med mjölk. Servera med sås och lingon.', 20, 30, 4, 0, 0, 1),

    ('kladdkaka-med-gradde', 'Kladdkaka med grädde', 'efterratter', 'Klassisk kladdig chokladkaka.', 'Rör ihop smet, grädda kort tid så kakan förblir kladdig. Servera med vispad grädde.', 10, 18, 8, 0, 0, 1),
    ('hallonpaj-vaniljsas', 'Hallonpaj med vaniljsås', 'efterratter', 'Smulpaj med syrliga hallon och len vaniljsås.', 'Blanda smuldeg, täck hallon i form och grädda gyllene. Servera med vaniljsås.', 15, 30, 6, 0, 0, 1),
    ('chokladmousse-klassisk', 'Klassisk chokladmousse', 'efterratter', 'Luftig mousse med mörk choklad.', 'Smält choklad, vänd ner äggulor och vispad grädde. Låt stelna kallt innan servering.', 20, 0, 4, 1, 0, 1),

    ('kall-romsas', 'Kall romsås', 'saser', 'Enkel sås till fisk med rom, dill och citron.', 'Blanda crème fraiche, rom, dill och citron. Smaka av med salt och peppar.', 10, 0, 4, 1, 0, 1),
    ('pepparsas-kramig', 'Krämig pepparsås', 'saser', 'Mustig pepparsås till kött och potatis.', 'Fräs krossad peppar, tillsätt fond och grädde. Låt reducera till krämig konsistens.', 10, 15, 4, 1, 0, 1),
    ('tomatsas-basilika', 'Tomatsås med basilika', 'saser', 'Snabb tomatsås till pasta eller köttbullar.', 'Fräs vitlök i olivolja, tillsätt tomater och låt puttra. Avsluta med basilika.', 10, 20, 4, 1, 1, 1),

    ('grekisk-sallad-klassisk', 'Grekisk sallad', 'sallader', 'Klassisk sallad med fetaost, oliver och gurka.', 'Blanda grönsaker och oliver, toppa med fetaost och oregano. Ringla olivolja över.', 15, 0, 4, 1, 1, 1),
    ('quinoa-sallad-citron', 'Quinoasallad med citron', 'sallader', 'Fräsch sallad med quinoa, örter och citron.', 'Koka quinoa, blanda med tomat, gurka och persilja. Smaksätt med citron och olivolja.', 15, 15, 4, 1, 1, 1),
    ('caesarsallad-kyckling', 'Caesarsallad med kyckling', 'sallader', 'Mättande sallad med kyckling och parmesan.', 'Stek kyckling, blanda sallad med dressing och toppa med krutonger och parmesan.', 15, 20, 4, 0, 0, 1),

    ('linsoppa-rod', 'Röd linssoppa', 'soppor', 'Värmende soppa med röda linser och morot.', 'Fräs lök och morot, tillsätt linser och buljong. Låt koka mjukt och mixa lätt.', 10, 25, 4, 1, 1, 1),
    ('tomatsoppa-rostad', 'Rostad tomatsoppa', 'soppor', 'Djup smak av ugnsrostade tomater och vitlök.', 'Rosta tomater och vitlök i ugn, mixa med buljong och låt sjuda. Toppa med basilika.', 15, 30, 4, 1, 1, 1),
    ('svampsoppa-kramig', 'Krämig svampsoppa', 'soppor', 'Len soppa på champinjoner och timjan.', 'Fräs svamp och lök, tillsätt buljong och grädde. Mixa slätt och smaka av.', 15, 20, 4, 1, 0, 1),

    ('chili-sin-carne', 'Chili sin carne', 'vegetariskt', 'Mustig vegetarisk chili med bönor.', 'Fräs lök och kryddor, tillsätt tomat och bönor. Låt sjuda och servera med ris.', 15, 30, 4, 1, 1, 1),
    ('halloumiwok-gronsaker', 'Halloumiwok med grönsaker', 'vegetariskt', 'Snabb wok med halloumi och krispiga grönsaker.', 'Stek halloumi gyllene, woka grönsaker och vänd ihop med soja och lime.', 15, 15, 4, 1, 0, 1),
    ('blomkalscurry-kokos', 'Blomkålscurry med kokos', 'vegetariskt', 'Kryddig curry med kokosmjölk och blomkål.', 'Fräs curry och lök, tillsätt blomkål och kokosmjölk. Låt sjuda tills mjukt.', 15, 25, 4, 1, 1, 1),

    ('kanelbullar-klassiska', 'Kanelbullar klassiska', 'bakverk', 'Saftiga kanelbullar med kardemumma.', 'Baka vetedeg, fyll med smör, socker och kanel. Jäs och grädda gyllene.', 35, 15, 20, 0, 0, 1),
    ('morotskaka-frosting', 'Morotskaka med frosting', 'bakverk', 'Mjuk kaka med kryddor och färskostfrosting.', 'Blanda smet med rivna morötter, grädda och toppa med frosting när kakan svalnat.', 20, 35, 12, 0, 0, 1),
    ('kardemummakaka-saftig', 'Saftig kardemummakaka', 'bakverk', 'Mjuk kaka med tydlig smak av kardemumma.', 'Vispa smet, tillsätt kardemumma och grädda i form. Pudra med florsocker vid servering.', 15, 30, 10, 0, 0, 1);

INSERT INTO recipes (
    user_id, category_id, title, slug, description, image_path, instructions, prep_minutes, cook_minutes, servings,
    is_gluten_free, is_lactose_free, is_nut_free, is_published
)
SELECT
    u.id,
    c.id,
    sr.title,
    sr.slug,
    sr.description,
    NULL,
    sr.instructions,
    sr.prep_minutes,
    sr.cook_minutes,
    sr.servings,
    sr.is_gluten_free,
    sr.is_lactose_free,
    sr.is_nut_free,
    1
FROM seed_recipes sr
INNER JOIN categories c ON c.slug = sr.category_slug
INNER JOIN users u ON u.email = 'demo@matarkiv.se'
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    category_id = VALUES(category_id),
    title = VALUES(title),
    description = VALUES(description),
    instructions = VALUES(instructions),
    prep_minutes = VALUES(prep_minutes),
    cook_minutes = VALUES(cook_minutes),
    servings = VALUES(servings),
    is_gluten_free = VALUES(is_gluten_free),
    is_lactose_free = VALUES(is_lactose_free),
    is_nut_free = VALUES(is_nut_free),
    is_published = VALUES(is_published);

DROP TEMPORARY TABLE IF EXISTS seed_recipe_categories;
CREATE TEMPORARY TABLE seed_recipe_categories (
    recipe_slug VARCHAR(180) NOT NULL,
    category_slug VARCHAR(90) NOT NULL,
    PRIMARY KEY (recipe_slug, category_slug)
) ENGINE=InnoDB;

INSERT INTO seed_recipe_categories (recipe_slug, category_slug)
SELECT slug, category_slug FROM seed_recipes;

INSERT INTO seed_recipe_categories (recipe_slug, category_slug) VALUES
    ('linsoppa-rod', 'vegetariskt'),
    ('tomatsoppa-rostad', 'vegetariskt'),
    ('quinoa-sallad-citron', 'vegetariskt'),
    ('blomkalscurry-kokos', 'varmratter')
;

DELETE rc
FROM recipe_categories rc
INNER JOIN recipes r ON r.id = rc.recipe_id
INNER JOIN seed_recipes sr ON sr.slug = r.slug;

INSERT INTO recipe_categories (recipe_id, category_id)
SELECT
    r.id,
    c.id
FROM seed_recipe_categories src
INNER JOIN recipes r ON r.slug = src.recipe_slug
INNER JOIN categories c ON c.slug = src.category_slug
;

DROP TEMPORARY TABLE IF EXISTS seed_ingredients;
CREATE TEMPORARY TABLE seed_ingredients (
    recipe_slug VARCHAR(180) NOT NULL,
    ingredient_name VARCHAR(120) NOT NULL,
    quantity VARCHAR(80) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO seed_ingredients (recipe_slug, ingredient_name, quantity) VALUES
    ('bruschetta-tomat-basilika', 'Surdegsbröd', '8 skivor'),
    ('bruschetta-tomat-basilika', 'Tomater', '4 st'),
    ('bruschetta-tomat-basilika', 'Färsk basilika', '1 kruka'),
    ('bruschetta-tomat-basilika', 'Vitlök', '1 klyfta'),

    ('rodbetstartar-getost', 'Kokta rödbetor', '4 st'),
    ('rodbetstartar-getost', 'Getost', '120 g'),
    ('rodbetstartar-getost', 'Honung', '1 msk'),
    ('rodbetstartar-getost', 'Pumpafrön', '2 msk'),

    ('avokado-rakrora', 'Avokado', '2 st'),
    ('avokado-rakrora', 'Handskalade räkor', '200 g'),
    ('avokado-rakrora', 'Majonnäs', '2 msk'),
    ('avokado-rakrora', 'Dill', '1 msk'),

    ('kyckling-i-currysas', 'Kycklingfilé', '600 g'),
    ('kyckling-i-currysas', 'Gul lök', '1 st'),
    ('kyckling-i-currysas', 'Currypulver', '1 msk'),
    ('kyckling-i-currysas', 'Matlagningsgrädde', '3 dl'),

    ('ugnsbakad-lax-med-dill', 'Laxfilé', '700 g'),
    ('ugnsbakad-lax-med-dill', 'Citron', '1 st'),
    ('ugnsbakad-lax-med-dill', 'Färsk dill', '1 knippe'),
    ('ugnsbakad-lax-med-dill', 'Potatis', '800 g'),

    ('kottbullar-med-potatismos', 'Blandfärs', '500 g'),
    ('kottbullar-med-potatismos', 'Potatis', '1 kg'),
    ('kottbullar-med-potatismos', 'Mjölk', '2 dl'),
    ('kottbullar-med-potatismos', 'Ägg', '1 st'),

    ('kladdkaka-med-gradde', 'Smör', '150 g'),
    ('kladdkaka-med-gradde', 'Kakao', '4 msk'),
    ('kladdkaka-med-gradde', 'Socker', '3 dl'),
    ('kladdkaka-med-gradde', 'Ägg', '2 st'),

    ('hallonpaj-vaniljsas', 'Hallon', '400 g'),
    ('hallonpaj-vaniljsas', 'Smör', '150 g'),
    ('hallonpaj-vaniljsas', 'Vetemjöl', '2 dl'),
    ('hallonpaj-vaniljsas', 'Vaniljsås', '3 dl'),

    ('chokladmousse-klassisk', 'Mörk choklad', '200 g'),
    ('chokladmousse-klassisk', 'Vispgrädde', '3 dl'),
    ('chokladmousse-klassisk', 'Ägg', '2 st'),
    ('chokladmousse-klassisk', 'Socker', '1 msk'),

    ('kall-romsas', 'Crème fraiche', '2 dl'),
    ('kall-romsas', 'Röd stenbitsrom', '80 g'),
    ('kall-romsas', 'Citron', '0.5 st'),
    ('kall-romsas', 'Dill', '2 msk'),

    ('pepparsas-kramig', 'Kalvfond', '2 msk'),
    ('pepparsas-kramig', 'Vispgrädde', '3 dl'),
    ('pepparsas-kramig', 'Svartpeppar', '1 msk'),
    ('pepparsas-kramig', 'Smör', '1 msk'),

    ('tomatsas-basilika', 'Krossade tomater', '400 g'),
    ('tomatsas-basilika', 'Vitlök', '2 klyftor'),
    ('tomatsas-basilika', 'Olivolja', '2 msk'),
    ('tomatsas-basilika', 'Färsk basilika', '1 kruka'),

    ('grekisk-sallad-klassisk', 'Tomater', '3 st'),
    ('grekisk-sallad-klassisk', 'Gurka', '1 st'),
    ('grekisk-sallad-klassisk', 'Fetaost', '150 g'),
    ('grekisk-sallad-klassisk', 'Kalamataoliver', '1 dl'),

    ('quinoa-sallad-citron', 'Quinoa', '3 dl'),
    ('quinoa-sallad-citron', 'Gurka', '1 st'),
    ('quinoa-sallad-citron', 'Körsbärstomater', '250 g'),
    ('quinoa-sallad-citron', 'Citron', '1 st'),

    ('caesarsallad-kyckling', 'Kycklingfilé', '500 g'),
    ('caesarsallad-kyckling', 'Romansallad', '2 huvuden'),
    ('caesarsallad-kyckling', 'Parmesan', '80 g'),
    ('caesarsallad-kyckling', 'Krutonger', '2 dl'),

    ('linsoppa-rod', 'Röda linser', '3 dl'),
    ('linsoppa-rod', 'Morot', '2 st'),
    ('linsoppa-rod', 'Gul lök', '1 st'),
    ('linsoppa-rod', 'Grönsaksbuljong', '1 liter'),

    ('tomatsoppa-rostad', 'Tomater', '1 kg'),
    ('tomatsoppa-rostad', 'Vitlök', '4 klyftor'),
    ('tomatsoppa-rostad', 'Gul lök', '1 st'),
    ('tomatsoppa-rostad', 'Grönsaksbuljong', '8 dl'),

    ('svampsoppa-kramig', 'Champinjoner', '500 g'),
    ('svampsoppa-kramig', 'Gul lök', '1 st'),
    ('svampsoppa-kramig', 'Matlagningsgrädde', '2 dl'),
    ('svampsoppa-kramig', 'Timjan', '1 tsk'),

    ('chili-sin-carne', 'Kidneybönor', '2 burkar'),
    ('chili-sin-carne', 'Krossade tomater', '400 g'),
    ('chili-sin-carne', 'Paprika', '1 st'),
    ('chili-sin-carne', 'Gul lök', '1 st'),

    ('halloumiwok-gronsaker', 'Halloumi', '400 g'),
    ('halloumiwok-gronsaker', 'Broccoli', '1 huvud'),
    ('halloumiwok-gronsaker', 'Paprika', '1 st'),
    ('halloumiwok-gronsaker', 'Sojasås', '2 msk'),

    ('blomkalscurry-kokos', 'Blomkål', '1 huvud'),
    ('blomkalscurry-kokos', 'Kokosmjölk', '400 ml'),
    ('blomkalscurry-kokos', 'Röd curry', '1 msk'),
    ('blomkalscurry-kokos', 'Gul lök', '1 st'),

    ('kanelbullar-klassiska', 'Vetemjöl', '13 dl'),
    ('kanelbullar-klassiska', 'Smör', '150 g'),
    ('kanelbullar-klassiska', 'Mjölk', '5 dl'),
    ('kanelbullar-klassiska', 'Kanel', '2 msk'),

    ('morotskaka-frosting', 'Morot', '4 st'),
    ('morotskaka-frosting', 'Vetemjöl', '3 dl'),
    ('morotskaka-frosting', 'Färskost', '200 g'),
    ('morotskaka-frosting', 'Florsocker', '2 dl'),

    ('kardemummakaka-saftig', 'Vetemjöl', '3 dl'),
    ('kardemummakaka-saftig', 'Smör', '150 g'),
    ('kardemummakaka-saftig', 'Socker', '2.5 dl'),
    ('kardemummakaka-saftig', 'Kardemumma', '2 tsk');

INSERT INTO ingredients (name)
SELECT DISTINCT ingredient_name
FROM seed_ingredients
ON DUPLICATE KEY UPDATE name = VALUES(name);

DELETE ri
FROM recipe_ingredients ri
INNER JOIN recipes r ON r.id = ri.recipe_id
INNER JOIN seed_recipes sr ON sr.slug = r.slug;

INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity)
SELECT
    r.id,
    i.id,
    si.quantity
FROM seed_ingredients si
INNER JOIN recipes r ON r.slug = si.recipe_slug
INNER JOIN ingredients i ON i.name = si.ingredient_name
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

DROP TEMPORARY TABLE IF EXISTS seed_ingredients;
DROP TEMPORARY TABLE IF EXISTS seed_recipe_categories;
DROP TEMPORARY TABLE IF EXISTS seed_recipes;

COMMIT;

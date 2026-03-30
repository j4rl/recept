# Matarkiv (PHP + mysqli)
En receptdatabas med nytt UI/UX i ren PHP, CSS och JavaScript.

## Funktioner
- Anonym användare: bläddra recept, sök och filtrera per kategori.
- Inloggad användare: skapa konto, logga in och publicera recept.
- Valbar ingrediensfunktion: aktivera `Skafferi/Kyl/Frys` och kryssa i vad du har hemma.
- Filter: visa recept du kan laga med ingredienserna du har markerat.

## Teknik
- PHP 8+
- MySQL/MariaDB
- `mysqli` (inga ramverk)

## Start i XAMPP
1. Starta Apache och MySQL i XAMPP.
2. Importera databasen:
   - Öppna phpMyAdmin och kör `database/schema.sql`
   - Eller i terminal: `mysql -u root -p < database/schema.sql`
3. Säkerställ databasinställningar i `includes/config.php`.
   - Standard är:
     - host: `127.0.0.1`
     - user: `root`
     - pass: ``
     - db: `receptdb`
4. Besök: `http://localhost/recept/index.php`

## Struktur
- `index.php`: routing och sidrendering
- `includes/`: bootstrap, auth, db, helpers, actions
- `pages/`: UI-sidor
- `partials/`: header/footer
- `assets/css/styles.css`: design
- `assets/js/app.js`: interaktioner
- `database/schema.sql`: tabeller och standardkategorier

## Arbetsregel
- Vid varje kodändring ska `README.md` också uppdateras.

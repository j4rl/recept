# Matarkiv (PHP + mysqli)
En receptdatabas med nytt UI/UX i ren PHP, CSS och JavaScript.

## Funktioner
- Anonym användare: bläddra recept, sök och filtrera per kategori.
- Inloggad användare: skapa konto, logga in, publicera recept och redigera eller ta bort egna recept.
- Administratörer kan hantera alla recept via kolumnen `users.role = 'admin'`.
- Favoriter: spara recept du vill hitta snabbt igen.
- Startsidan visar `Recept av dagen`, `Populära recept` och `Senaste recept`.
- Veckoplanerare: lägg recept på specifika datum och bygg en enkel veckomeny.
- Inköpslista: lägg till ingredienser från recept, markera klart och se om något redan finns hemma.
- Recepthistorik: se vilka recept du röstat på och när rösten senast ändrades.
- Google Keep: skicka saknade ingredienser från en receptsida via OAuth2 om API-nycklar är konfigurerade.
- Recept kan ligga i flera kategorier samtidigt (t.ex. soppor + vegetariskt).
- Stjärnrating (1-5): en röst per användare och rösten kan ändras när som helst.
- Betygsvisning: alltid 5 stjärnor, tomma vid 0 röster och steglöst ifyllda baserat på snittbetyget.
- Recept-badges visas som ikoner (`nogluten.png`, `nolactose.png`, `nonut.png`) i 16x16 med svensk text i `alt`/`title`.
- Valfri uppladdning av bild på färdig maträtt per recept.
- Stöd för ljust och mörkt tema med en 3-läges ikonbrytare (`☀`/`⚙`/`🌙`), där `Auto` är standard och följer enhetens tema.
- Portionskalkylator på receptsidan: öka/minska antal personer och få ingrediensmängder automatiskt omräknade.
- Valbar ingrediensfunktion: aktivera `Skafferi/Kyl/Frys` och markera vad du har hemma.
- I `Skafferi/Kyl/Frys` kan du lägga till egna ingredienser med autocomplete-förslag från receptens ingredienser.
- I lagerlistan visas endast ingredienser du har valt att ha hemma.
- I lagerlistan visas kolumnerna `Skafferi`, `Kyl`, `Frys` med ikonmarkeringar istället för kryssrutor, i tätare kolumnlayout.
- Egna lageringredienser kan redigeras i listan: ändra stavning, byt plats (`Skafferi/Kyl/Frys`) eller ta bort helt.
- Filter: visa recept du kan laga med ingredienserna du har markerat, eller välj att även visa recept som saknar en eller flera ingredienser.


## Bilduppladdning
- Uppladdade bilder sparas på servern i `uploads/recipes/` (eller i valfri mapp via config).
- Tillåtna format: `JPG`, `PNG`, `WEBP`, `GIF`.
- Maxstorlek: `5 MB`.
- Sökvägen till uppladdad bild sparas i `recipes.image_path`.

## Upload-konfiguration
- `RECEPT_UPLOAD_BASE_DIR`: servermapp för uppladdningar (default: `<projekt>/uploads`)
- `RECEPT_UPLOAD_BASE_URL`: publik sökvägsbas som sparas i DB (default: `uploads`)

## Google Keep-konfiguration
- `RECEPT_GOOGLE_KEEP_CLIENT_ID`: OAuth2 Client ID för Google Keep-integrationen
- `RECEPT_GOOGLE_KEEP_CLIENT_SECRET`: OAuth2 Client Secret för Google Keep-integrationen
- `RECEPT_GOOGLE_KEEP_REDIRECT_URI`: valfri explicit callback-URL, annars används aktuell `index.php?page=keep_callback`

## Struktur
- `index.php`: routing och sidrendering
- `includes/`: bootstrap, auth, db, helpers, actions
- `pages/`: UI-sidor
- `partials/`: header/footer
- `assets/css/styles.css`: design
- `assets/js/app.js`: interaktioner
- `database/schema.sql`: tabeller och standardkategorier
- `database/seed_example_recipes.sql`: seed med 24 exempelrecept (3 per kategori), uppdaterad för bred MySQL-kompatibilitet
- `recipe_categories`: koppling mellan recept och flera kategorier
- `recipe_ratings`: användarröster (1-5 stjärnor)
- `recipe_favorites`: sparade favoritrecept per användare
- `meal_plan_items`: veckoplanens recept per datum
- `shopping_list_items`: användarens inköpslista
- `google_keep_tokens`: OAuth2-tokenlagring för Google Keep

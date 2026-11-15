# Entwicklung

## Aufbau

`public/` enthält den Frontcontroller und öffentliche Assets. `src/AppFactory.php` verdrahtet Slim, Twig und Middleware explizit. `src/Config/` validiert die Einstellungen; `src/Infrastructure/` enthält PDO/NotORM, Laufzeitverzeichnisse und den Releasewechsel. `src/Validation/` stellt strikte Parser und Symfony-Validierung bereit. `templates/` enthält die HTML-Ausgabe, `migrations/` zukünftige Schemaänderungen.

Ein Seitenaufruf führt keine Migration aus. Die öffentliche Startseite ohne Sitzung und `/health` öffnen keine Datenbankverbindung. Loginformulare benötigen eine Session; angemeldete Aufrufe prüfen die Identität anhand der Datenbank. `/health` gibt die nicht geheime Release-ID aus. `php bin/doctor.php` prüft zusätzlich Datenbank und Schreibrechte über CLI.

`APP_ENV` erlaubt `development`, `test` und `production`; in Produktion ist `APP_DEBUG=true` ungültig. Pflichtwerte fehlen nicht stillschweigend. Echte Prozessvariablen haben Vorrang vor `.env`. Produktionsausgaben enthalten keine internen Fehlermeldungen. Die Logs unter `var/log/` enthalten Request-ID, Fehlertyp und Codeposition, aber keine Request-Bodies oder Exception-Nachrichten mit möglichen Geheimnissen. Es werden sieben Tagesdateien aufbewahrt; Schreibbarkeit und Speicherverbrauch gehören zur Betriebsüberwachung.

## Datenbank

Die Runtime verwendet ein DML-Konto, Phinx ein eigenes Migrationskonto. Lokal werden beide Konten und die separate Testdatenbank beim ersten Volumestart angelegt. Produktiv werden Datenbank und Konten über das Hostingpanel eingerichtet.

```sh
docker compose run --rm php vendor/bin/phinx create ExampleMigration -c phinx.php
docker compose run --rm php composer db:status
docker compose run --rm php composer db:migrate
```

Die erste Anwendungsmigration legt `users` und `login_attempts` an. `composer app:install` führt Migrationen und die wiederholbare Admin-Ersteinrichtung aus; `db:migrate` verwaltet nur das Schema. Tests verändern ausschließlich `cama_test`; ihre Accounttabellen können dort für weitere Testläufe bestehen bleiben. `Database::transaction()` übernimmt Commit/Rollback; verschachtelte Transaktionen werden explizit abgelehnt. DDL gehört in Migrationen, nicht in fachliche Transaktionscallbacks. Jede Anwendungsverbindung verwendet UTC und `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.

Lokaler Export ohne Passwörter in der Shell-Historie:

```sh
mkdir -p var/backups
docker compose exec -T db sh -c 'MYSQL_PWD="$DB_MIGRATION_PASSWORD" mariadb-dump -ucama_migration --single-transaction --no-tablespaces cama' > var/backups/cama.sql
```

Ein Import verändert vorhandene lokale Entwicklungsdaten. Vorher sichern; Ziel ist hier ausdrücklich `cama`:

```sh
docker compose exec -T db sh -c 'MYSQL_PWD="$DB_MIGRATION_PASSWORD" mariadb -ucama_migration cama' < var/backups/cama.sql
```

Alternativ können lokale Exporte und Importe über phpMyAdmin erfolgen. Ein vollständiger lokaler Reset mit `docker compose down --volumes` entfernt alle Entwicklungs- und Testdaten im Projektvolume; er gehört nicht zum normalen Start oder Stop.

## Eingaben und Berechtigungen

Die Anwendung begrenzt Request-Bodies auf 64 KiB und Querystrings auf 8 KiB. Zukünftige Upload-Routen benötigen ein eigenes geprüftes Limitkonzept. `FormValidator` akzeptiert nur die ausdrücklich definierten Felder. Normalisierung wird vom jeweiligen Fachmodul bewusst entschieden; die allgemeinen Bausteine trimmen und konvertieren nicht automatisch.

`InputParser::integer()` akzeptiert Ganzzahlen oder kanonische Dezimalstrings innerhalb des PHP-Integerbereichs. `boolean()` akzeptiert echte Boolesche Werte oder die Strings `0` und `1`; beispielsweise `'false'` wird nicht fälschlich als wahr behandelt. `date()` prüft gültige ISO-Kalenderdaten. Symfony-Constraints beschreiben danach Wertebereiche, Pflichtfelder und Beziehungen. Vor einem Schreibzugriff müssen alle Fehler behandelt sein; die dafür festgelegte HTML-Antwort wird HTTP 422 verwenden.

`WriteAccess` erlaubt ausschließlich ausdrücklich registrierte POST-Aktionen für Login, Logout, Passwortwechsel und Admin-Accountanlage. `Authentication` prüft die gespeicherte Identität und Adminrechte; neue Leserouten sind ebenfalls geschützt, bis sie ausdrücklich öffentlich freigegeben werden. Ein HTMX-Header oder ein gültiges CSRF-Token ist keine Anmeldung. Die CSRF-Middleware erwartet `_csrf` im Formularbody. Produktionscookies sind `Secure`, `HttpOnly` und `SameSite=Lax`. Session-IDs und Tokens wechseln bei Login und Passwortwechsel. Einzelheiten zu Ersteinrichtung, Limits und Accountverwaltung stehen in [accounts.md](accounts.md).

Die zusätzliche MySQL-Prüfung verwendet `docker compose -f compose.yaml -f compose.mysql.yaml run --rm test composer test`. Das eigene Compose-Projekt und Volume verhindern, dass MySQL das MariaDB-Volume öffnet. Vorher `docker compose build test` ausführen. Stoppen mit `docker compose -f compose.yaml -f compose.mysql.yaml down`; die Testdaten bleiben erhalten.

## Suche und große Datenmengen

Spätere Suchabfragen gehören in die Datenzugriffsschicht, mit gebundenen Werten und erlaubten Sortierspalten. Seiten werden in SQL begrenzt; Ergebnisbestände werden nicht vollständig in PHP geladen. Facetten sollen vorläufig ODER innerhalb einer Auswahlgruppe und UND zwischen Gruppen verwenden. Facettenzählungen berücksichtigen die übrigen Gruppen und zählen eindeutige Haupteinträge.

Vor der Suchimplementierung werden repräsentative Datenmodelle, Parallelzugriffe und Antwortzeitziele festgelegt. Messstufen oberhalb 100.000 Einträgen müssen auch Beziehungen und Verteilung der Filterwerte abbilden. Erst diese Messungen können die spätere Suchleistung auf Webhosting 2000 belegen. Redis, externe Suchserver und dauerhafte Worker sind keine Voraussetzung des Grundgerüsts.

## Debugging und Updates

Xdebug ist im Entwicklungsimage installiert, aber standardmäßig deaktiviert. Der Test-Service aktiviert den Coverage-Modus; für interaktives Debugging können `XDEBUG_MODE=debug` und eine lokale IDE-Konfiguration verwendet werden. Das Runtime-Buildtarget enthält kein Xdebug.

Abhängigkeitsupdates werden im Container durchgeführt, danach Lockdatei und Tests geprüft. Digest-Updates der Images werden ebenfalls bewusst eingecheckt. `.env`, `vendor/`, `var/` und `build/` bleiben lokal. Die Testdatenbank darf niemals auf Produktionszugänge umgebogen werden.

# CAMA Color Database

Technisches Grundgerüst für ein Informationssystem mit PHP Slim 4, NotORM und Twig. Zielbetrieb ist ein vorhandenes **Netcup Webhosting 2000**. Die Entwicklung einschließlich Datenbankverwaltung und Tests läuft vollständig in Docker.

## Lokal starten

Voraussetzungen: Git, Docker Engine/Docker Desktop mit Docker Compose und eine POSIX-Shell (unter Windows beispielsweise WSL2). PHP, Composer und Node.js müssen nicht auf dem Rechner installiert sein.

```sh
bin/setup
docker compose build php test
docker compose run --rm --no-deps php composer install
docker compose up -d
docker compose run --rm php composer db:migrate
docker compose run --rm php php bin/doctor.php
```

Die Anwendung steht unter **http://127.0.0.1:8080**, phpMyAdmin unter **http://127.0.0.1:8081** bereit. Falls diese Ports belegt sind, `WEB_PORT` und `PMA_PORT` in `.env` ändern. Beide HTTP-Dienste sind ausschließlich an Loopback gebunden; die Datenbank veröffentlicht keinen Port.

`bin/setup` erzeugt einmalig `.env` mit zufälligen lokalen Passwörtern und den lokalen Benutzer-/Gruppen-IDs. Eine vorhandene `.env` bleibt erhalten. Unter macOS/WSL bei Bedarf `LOCAL_UID` und `LOCAL_GID` anpassen.

Für phpMyAdmin stehen diese lokalen Konten zur Verfügung; die Passwörter stehen ausschließlich in `.env`:

| Benutzer | Passwortvariable | Zweck |
| --- | --- | --- |
| `cama` | `DB_PASSWORD` | Entwicklungsdaten lesen und bearbeiten |
| `cama_migration` | `DB_MIGRATION_PASSWORD` | Entwicklungsschema verwalten |
| `cama_test` | `DB_TEST_PASSWORD` | Isolierte, durch Tests veränderte Datenbank |

Ein Serverwechsel oder eine automatische Root-Anmeldung wird in phpMyAdmin nicht angeboten. Strukturelle Änderungen müssen als Migration in Git nachvollziehbar sein. Die Passwörter werden bei der **ersten Initialisierung** des Datenbankvolumes gesetzt; eine spätere Änderung in `.env` ändert vorhandene Datenbankkonten nicht automatisch.

## Tests und Qualität

```sh
docker compose run --rm test composer check
docker compose run --rm test composer test:coverage
docker compose run --rm test php bin/check-coverage.php
docker compose run --rm test sh tests/smoke.sh
docker compose run --rm test php tests/phpmyadmin-smoke.php
docker compose run --rm --no-deps php composer audit
```

Die HTTP-Prüfungen benötigen die laufenden Services `web` und `phpmyadmin`. Der Test-Service setzt verbindlich `APP_ENV=test` sowie die Datenbank und den Benutzer `cama_test`. Ein Bootstrap-Guard verweigert Tests mit anderen Einstellungen. PHPUnit testet Konfiguration, Eingabeprüfung, HTTP-Antworten, Session-/CSRF-Verhalten, Zugriffsverweigerung, echtes NotORM/PDO-Verhalten, Migrationen und Releasewechsel.

Der HTML-Coveragebericht liegt in `build/coverage/index.html`, die maschinenlesbare Ausgabe in `build/coverage.xml`. CI fordert mindestens **95 % Zeilenabdeckung für `src/`**. Abhängigkeiten, Templates und CLI-/Shellskripte sind nicht Teil dieses Prozentwerts; ihre relevanten Abläufe werden zusätzlich durch HTTP-, Paket- und Deploymentprüfungen abgedeckt.

```sh
# Codeformatierung anwenden
docker compose run --rm --no-deps php composer format

# Logs und normaler Stop; Datenbankvolume bleibt erhalten
docker compose logs -f
docker compose down
```

## Umfang und Weiterentwicklung

Enthalten sind eine Startseite, Health-Endpunkt, sichere Fehlerseiten, zentrale Konfiguration, begrenzte Logs, Datenbankanbindung, Migrationen, Validierungsbausteine und Docker/CI/CD. Schreibende Routen sind standardmäßig gesperrt. Login, Rollenverwaltung und Fachmodule werden später ergänzt; die geplante Oberfläche ist öffentlich lesbar und durch angemeldete Redakteure/Administratoren bearbeitbar.

Twig bildet die Oberfläche. HTMX wird erst mit der ersten dynamischen Fachfunktion eingebunden, einschließlich Fehlerdarstellung für HTTP 422 und normaler HTML-Fallbacks. Die fachlichen Tabellen und die Suche mit mehr als 100.000 Einträgen bleiben einem eigenen Entwicklungsschritt vorbehalten.

Die lokale Laufzeit verwendet **PHP 8.5.12 und MariaDB 10.11.19**. PHP 8.5 ist laut Hostingpanel verfügbar; Patchstand, CLI-Pfad und Datenbankversion müssen vor dem ersten Deployment abgeglichen werden. Offizielle Container werden per Digest fixiert; Composer-Abhängigkeiten sind in `composer.lock` festgeschrieben. Eine Änderung der PHP-Version umfasst Docker-Basis, Composer-PHP-Vorgabe, Lockdatei und erneute Tests; für die Datenbank müssen auch Initialisierung und SQL-Verhalten geprüft werden.

Die Bereitstellung für **https://cama.cats4future.de** verwendet die Git-Anbindung des Netcup-/Plesk-Panels. Nach erfolgreichen Tests eines Pushes oder PR-Merges nach `main` schreibt CI das fertige Paket samt Produktionsabhängigkeiten auf den Branch `deploy` und benachrichtigt Plesk. Offene PRs veröffentlichen nichts. Die einmalige Einrichtung und Freischaltung stehen in der [Deploymentanleitung](docs/deployment.md); ein Hosting-Upgrade ist dafür nicht vorgesehen.

Weitere Dokumentation: [Entwicklung](docs/development.md), [Hostingabgleich](docs/hosting.md), [Deployment und Wiederherstellung](docs/deployment.md), [Changelog](CHANGELOG.md).

Lizenz: [GPL-3.0](LICENSE).

# Changelog

## [Unreleased]

### Behoben – 2026-11-15

- Passwortfelder bei Anmeldung, Passwortwechsel und Accountanlage kürzen gültige Unicode-Passwörter nicht mehr durch das HTML-`maxlength`; die serverseitige Grenze bleibt bei 128 Unicode-Zeichen.

### Hinzugefügt – Accounts – 2026-11-15

- Login und Logout mit CSRF-Schutz, erneuerten Session-IDs, Sitzungsablauf und Anmeldebegrenzung; Startseite bleibt öffentlich, Accountdaten sind geschützt.
- Einmalige automatische Admin-Erstellung aus privater Konfiguration, ausschließlich durch diesen Admin mögliche Accountanlage und verpflichtender Wechsel von Startpasswörtern; keine Selbstregistrierung.
- Eigener Passwortwechsel, Argon2id-Hashes und Ungültigkeit älterer Sitzungen nach Passwortänderungen; wiederholte Installation überschreibt keine Zugangsdaten.
- Accountmigrationen, strikter SQL-Modus pro Verbindung, zusätzliche CI-Tests auf MySQL 8.4.13 sowie Integrations- und HTTP-Tests für Berechtigungen, Cookies, Installation und Passwortabläufe.
- Anleitung zur lokalen Ersteinrichtung und kontrollierten ersten Accountmigration auf Netcup; Adminprüfung ohne Schemaänderung als Deployment-Voraussetzung.

### Geändert – 2026-11-15

- Anwendung auf dem vorhandenen Netcup Webhosting 2000 unter `https://cama.cats4future.de` in Betrieb genommen; erfolgreiche CI, Plesk-Webhook, automatische Aktivierung und anschließende HTTPS-Prüfung bestätigt.
- Hostingbefund und Einrichtungsanleitung aktualisiert: PHP 8.5.12, MySQL 8.4.13 mit utf8mb4, gemeinsame private Konfiguration, öffentlicher Symlink und erforderliche `open_basedir`-Dropdownauswahl. Abweichungen zur lokalen MariaDB und noch ausstehender Datenbank-Restoretest sind dokumentiert.
- Deployment über die Git-Anbindung im Netcup-/Plesk-Panel: Erfolgreich geprüfte Pushes und PR-Merges nach `main` veröffentlichen das fertige Produktionspaket auf `deploy`; offene PRs führen ausschließlich CI-Prüfungen aus.
- Plesk-Pull per HTTPS-Webhook mit anschließender Prüfung der tatsächlich ausgelieferten Release-ID; Repository-Secret statt SSH-Zugangsdaten in GitHub Actions. Ein manueller CI-Lauf auf `main` ermöglicht Wiederholungen.
- Schutz vor überholten CI-Läufen, Deployment ohne Force-Push, SHA-256-Manifest und atomarer Import aus dem Plesk-Zielordner vor dem bestehenden Releasewechsel mit Rollback.
- Relative Verknüpfung der Hosting-Konfiguration und automatisch daraus abgeleiteter gemeinsamer Laufzeitordner für unterschiedliche Dateisystempfade in CLI und Web-PHP.
- Tests für Plesk-Dateiimport, echte Git-Publikation gegen ein lokales Remote und simulierte Webhook-/HTTP-Fehler; Anleitung zur Einrichtung von `cama.cats4future.de` im Panel.

### Hinzugefügt – 2026-11-15

- Technisches Grundgerüst der CAMA Color Database mit Slim 4, NotORM 2.1, Twig, validierter Konfiguration und datenbankunabhängiger Startseite.
- Docker-Entwicklungsumgebung mit Apache, PHP-FPM, MariaDB und lokal gebundenem phpMyAdmin; getrennte Konten und Datenbank für Anwendung, Migrationen und Tests.
- Strikte Eingabeparser und Symfony-Validierung, HTML-Escaping, Größenlimits, Session-/CSRF-Grundlagen und standardmäßig gesperrte Schreibzugriffe.
- PDO-/NotORM-Anbindung mit Transaktionen, Phinx-Migrationen, technische Diagnose und sichere Fehlerbehandlung mit Request-IDs.
- PHPUnit-, Datenbank-, HTTP- und Deploymenttests, echte phpMyAdmin-Anmeldung sowie Prüfung des Produktionspakets ohne Xdebug; Coveragebericht mit CI-Mindestwert von 95 % für Anwendungscode, PHPStan und Formatierungsprüfung.
- GitHub Actions für CI und vorbereitete Netcup-Bereitstellung: geprüftes Produktionspaket, verifizierter SSH-Transport, atomarer Releasewechsel und Code-Rollback bei fehlgeschlagener HTTP-Prüfung.
- Anleitungen für Erststart, Entwicklung, Hostingabgleich, Deployment und Wiederherstellung. Produktive Hostingdaten müssen noch abgeglichen werden.

# Changelog

## [Unreleased]

### Geändert – 2026-11-15

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

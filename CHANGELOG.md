# Changelog

## [Unreleased]

### Hinzugefügt – 2026-11-15

- Technisches Grundgerüst der CAMA Color Database mit Slim 4, NotORM 2.1, Twig, validierter Konfiguration und datenbankunabhängiger Startseite.
- Docker-Entwicklungsumgebung mit Apache, PHP-FPM, MariaDB und lokal gebundenem phpMyAdmin; getrennte Konten und Datenbank für Anwendung, Migrationen und Tests.
- Strikte Eingabeparser und Symfony-Validierung, HTML-Escaping, Größenlimits, Session-/CSRF-Grundlagen und standardmäßig gesperrte Schreibzugriffe.
- PDO-/NotORM-Anbindung mit Transaktionen, Phinx-Migrationen, technische Diagnose und sichere Fehlerbehandlung mit Request-IDs.
- PHPUnit-, Datenbank-, HTTP- und Deploymenttests, echte phpMyAdmin-Anmeldung sowie Prüfung des Produktionspakets ohne Xdebug; Coveragebericht mit CI-Mindestwert von 95 % für Anwendungscode, PHPStan und Formatierungsprüfung.
- GitHub Actions für CI und vorbereitete Netcup-Bereitstellung: geprüftes Produktionspaket, verifizierter SSH-Transport, atomarer Releasewechsel und Code-Rollback bei fehlgeschlagener HTTP-Prüfung.
- Anleitungen für Erststart, Entwicklung, Hostingabgleich, Deployment und Wiederherstellung. Produktive Hostingdaten müssen noch abgeglichen werden.

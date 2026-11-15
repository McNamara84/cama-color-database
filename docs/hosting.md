# Hostingabgleich: Netcup Webhosting 2000

Stand: 15. November 2026. Das Paket ist laut Projektvorgabe vorhanden. Es liegen noch keine WCP-/SSH-Befunde oder Zugangsdaten vor. Die folgende lokale Referenz ist deshalb **keine bestätigte Produktionskonfiguration**.

| Merkmal | Lokale Referenz / Anforderung | Vorhandenes Paket |
| --- | --- | --- |
| Tarifgeneration | Webhosting 2000 | Offen |
| PHP | 8.5.12; Composer-Plattform 8.5.0 | Offen |
| CLI | Passende PHP-8.5-Version, 64 Bit, absoluter Pfad | Offen |
| Erweiterungen | PDO, pdo_mysql, mbstring, intl, session; Composer prüft weitere transitive Anforderungen | Offen |
| Datenbank | MariaDB 10.11.19, InnoDB, utf8mb4_unicode_ci | Typ und Version offen |
| SQL-Modus | STRICT_TRANS_TABLES, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION | Offen |
| Speicher/Laufzeit | PHP lokal 256 MiB / 30 s | Offen |
| Document-Root | Nur `current/public` | Konfigurierbarkeit und Chroot-Pfad offen |
| Routing | Apache-Rewrite / `.htaccess` | Offen |
| SSH | Schlüsselanmeldung, verifizierter Hostschlüssel | Offen |
| Deploymentwerkzeuge | tar, sha256sum, PHP-CLI mit proc_open, symlink und rename | Offen |
| HTTP-Prüfung vom Hosting | HTTPS-Dateizugriff über PHP (`allow_url_fopen`), funktionierende CA-Zertifikate | Offen |
| Releasewechsel | Atomarer Symlinkwechsel; Web-PHP/OPcache erkennt neues Release | Offen |
| Persistente Dateien | `shared/.env`, schreibbares `shared/var` außerhalb der öffentlichen Wurzel | Offen |
| Datenbankrechte | DML für Anwendung; Schema-Rechte für Migrationen, soweit getrennt verfügbar | Offen |
| Zieladresse | Eigene Domain/Subdomain, HTTPS, Betrieb am Domain-Wurzelpfad | Offen |
| Backup/Restore | Eigener geprüfter Export-/Restoreablauf | Offen |

Die aktuelle [Netcup-Produktbeschreibung](https://www.netcup.com/de/hosting/webhosting/webhosting-2000-nue) bestätigt grundsätzlich PHP, SSH und einen anpassbaren Document-Root. Diese Angaben ersetzen den Abgleich des vorhandenen Vertrags nicht.

## Prüfung vor Aktivierung

Im WCP Tarifgeneration, Web-PHP-Version, Extensions, Handler, Document-Root und Limits notieren. Über SSH den richtigen PHP-CLI-Pfad und seine Extensions prüfen. Datenbanktyp, Version, Kollation und SQL-Modus können über das Hostingpanel/phpMyAdmin abgefragt werden. Keine öffentlich erreichbare `phpinfo()`-Datei hinterlassen.

```sql
SELECT VERSION();
SELECT @@sql_mode, @@character_set_database, @@collation_database;
```

Anschließend das Veröffentlichungspaket in einem eigenen Testpfad verwenden, mit der passenden CLI `composer.phar check-platform-reqs --no-dev` und `bin/doctor.php` ausführen und dieselbe Anwendung per HTTPS prüfen. CLI-PHP und Web-PHP müssen beide kompatibel sein. Erforderliche Funktionen wie `proc_open`, Symlinks und HTTPS-Zugriff müssen für den Deploymentablauf verfügbar sein.

Der Webserver darf ausschließlich `public/` ausliefern. HTTPS wird im Hostingpanel eingerichtet; die Anwendung vertraut absichtlich keinen beliebigen `X-Forwarded-*`-Headern. Ein notwendiger Proxyabgleich muss anhand der echten Umgebung erfolgen. Der aktuelle Aufbau ist für eine eigene Domain/Subdomain am Wurzelpfad vorgesehen.

Wenn Symlinks im vorhandenen Paket nicht funktionieren, stoppt das Deployment mit einer Fehlermeldung. Vor Freischaltung ist dann ein dort überprüfter alternativer Verzeichniswechsel zu ergänzen. Das ist kein Anlass für ein Upgrade auf Webhosting 4000. Ebenso wird bei abweichender PHP-/Datenbankversion die lokale Referenz angepasst und erneut geprüft.

Docker, Node.js, Composer-Installation auf dem Server, Redis und Root-Rechte sind für die Anwendung nicht nötig. Das Paket enthält seine PHP-Abhängigkeiten und ein Composer-PHAR ausschließlich zur Plattformprüfung. phpMyAdmin aus dieser Compose-Konfiguration bleibt ein lokales Entwicklungswerkzeug.

# Hostingabgleich: Netcup Webhosting 2000

Stand: 15. November 2026. Das vorhandene Webhosting 2000 wurde über den bereitgestellten SSH-Zugang geprüft. Im SSH-Chroot ist das Benutzerverzeichnis `/`; die Anwendung liegt unter `/cama.cats4future.de`. Web-PHP sieht dieselben Dateien unter `/var/www/vhosts/hosting128274.a2fdf.netcup.net/cama.cats4future.de`. Zugangsdaten stehen ausschließlich in der ignorierten lokalen `.env` beziehungsweise in `shared/.env` auf dem Hosting.

| Merkmal | Lokale Referenz / Anforderung | Vorhandenes Paket |
| --- | --- | --- |
| Tarifgeneration | Webhosting 2000 | Laut Nutzer Webhosting 2000; genaue Tarifgeneration nicht über SSH ermittelbar |
| PHP | 8.5.12; Composer-Plattform 8.5.0 | CLI und Web-PHP 8.5.12 bestätigt, Web-SAPI `cgi-fcgi` |
| CLI | Passende PHP-8.5-Version, 64 Bit, absoluter Pfad | `/usr/local/php85/bin/php`; 64-Bit-Plattformprüfung erfolgreich |
| Erweiterungen | PDO, pdo_mysql, mbstring, intl, session; Composer prüft weitere transitive Anforderungen | Vorhanden; zusätzlich OpenSSL und Zip bestätigt; Produktionspaket besteht Composer-Plattformprüfung |
| Datenbank | MariaDB 10.11.19, InnoDB, utf8mb4_unicode_ci | **MySQL 8.4.13**; zuvor leere CAMA-Datenbank auf `utf8mb4` / `utf8mb4_unicode_ci` eingestellt |
| SQL-Modus | STRICT_TRANS_TABLES, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION | Serverstandard enthält `IGNORE_SPACE,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`; kein globaler Strict-Modus |
| Speicher/Laufzeit | PHP lokal 256 MiB / 30 s | Offen |
| Document-Root | Nur `current/public` | Panel bleibt auf `httpdocs`; `httpdocs -> current/public` eingerichtet, alte Inhalte privat gesichert |
| Routing | Apache-Rewrite / `.htaccess` | Startseite, `/health` und CSS über HTTPS erfolgreich; private Pfade liefern 403/404; POST wird mit 405 abgewiesen |
| Git-Anbindung | Plesk zieht den fertigen Branch `deploy` von GitHub | Repository `cama-color-database.git`, Branch `deploy`, Webhook und automatische Bereitstellungsaktion durch erfolgreichen CI-Lauf samt anschließender Aktivierung bestätigt |
| SSH | Optional zur Diagnose; kein CI-Upload | Anmeldung erfolgreich, Netcup-Hostschlüssel lokal für die Sitzung festgehalten |
| Deploymentwerkzeuge | PHP-8.5-CLI mit proc_open, flock, symlink und rename; zusätzliche Plesk-Bereitstellungsaktionen | CLI-Funktionen vorhanden; Git und tar vorhanden; Releaseimport samt Dateiprüfsummen erfolgreich |
| HTTP-Prüfung vom Hosting | HTTPS-Dateizugriff über PHP (`allow_url_fopen`), funktionierende CA-Zertifikate | PHP-CLI erreicht die eigene Subdomain per HTTPS mit eingeschalteter Zertifikatsprüfung |
| Releasewechsel | Atomarer Symlinkwechsel; Web-PHP/OPcache erkennt neues Release | Aktivierung erfolgreich; CLI- und externe HTTPS-Prüfung bestätigen Commit `2977ff838b730ed69e59abca78da8edf825362a2` |
| Persistente Dateien | `shared/.env`, schreibbares `shared/var` außerhalb der öffentlichen Wurzel | Konfiguration mit Modus `0600` eingerichtet; gemeinsame Laufzeitverzeichnisse angelegt und CLI-Diagnose erfolgreich |
| Datenbankrechte | DML für Anwendung; Schema-Rechte für Migrationen, soweit getrennt verfügbar | Bereitgestellter Benutzer für Anwendung und Migrationen; eigene Datenbank, Unicode/InnoDB/NotORM/Commit/Rollback anhand temporärer Tabelle geprüft |
| Zieladresse | Eigene Domain/Subdomain, HTTPS, Betrieb am Domain-Wurzelpfad | **https://cama.cats4future.de** ist mit der Anwendung erreichbar |
| Backup/Restore | Eigener geprüfter Export-/Restoreablauf | Frühere Webdateien vor Umstellung gesichert; Datenbank-Restore noch nicht erprobt |

Die aktuelle [Netcup-Produktbeschreibung](https://www.netcup.com/de/hosting/webhosting/webhosting-2000-nue) bestätigt grundsätzlich PHP, SSH und einen anpassbaren Document-Root. Diese Angaben ersetzen den Abgleich des vorhandenen Vertrags nicht.

[CI-Lauf 43728179882](https://github.com/McNamara84/cama-color-database/actions/runs/43728179882) wurde manuell auf `main` gestartet und bestand sämtliche Prüfungen einschließlich Veröffentlichung und Plesk-Benachrichtigung. Der Webhook-Schritt begann am 15. November 2026 um 15:10:43 UTC; der `current`-Link wurde auf dem Hosting um 15:10:46 UTC erneut aktiviert. Damit ist die tatsächliche Ausführung der Plesk-Aktion auch bei diesem bereits zuvor installierten Release nachgewiesen. Anschließend bestätigten externe HTTPS-Aufrufe die erwartete Release-ID, Startseite und CSS sowie den gesperrten Zugriff auf Konfiguration und interne PHP-Dateien.

Die lokale MariaDB bleibt die Entwicklungsdatenbank. Für die Accountfunktionen wird dieselbe Testsuite zusätzlich gegen MySQL 8.4.13 in Docker ausgeführt. Jede Anwendungsverbindung setzt nun einen strikten SQL-Modus für ihre eigene Sitzung. Globale Servereinstellungen werden nicht geändert. Neue Fachmigrationen müssen weiterhin auf beiden Datenbanksystemen geprüft werden.

Für Passwörter wurde Argon2id auch in der vorhandenen Netcup-PHP-8.5.12-Laufzeit per SSH bestätigt. Die erste Bereitstellung der Accounttabellen und des Admins erfolgt nach dem Ablauf in [accounts.md](accounts.md).

Der veraltete GitHub-RSA-Hostschlüssel in `/.ssh/git_known_hosts` wurde nach Sicherung durch die [von GitHub offiziell veröffentlichten Hostschlüssel](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints) ersetzt. Der anschließende Git-Lesezugriff mit eingeschalteter Hostschlüsselprüfung war erfolgreich. Der frühere Fehler `REMOTE HOST IDENTIFICATION HAS CHANGED` ist damit behoben.

## Panel-Einstellungen

Bei `open_basedir` stellt dieses Paket zwei Optionen bereit. Der Eintrag **beginnend mit `{WEBSPACEROOT}`** wurde gewählt und seine Wirkung über Web-PHP bestätigt. Der Standard beginnend mit `{DOCROOT}` verhindert den Zugriff auf Anwendungscode und Konfiguration außerhalb des öffentlichen Ordners. Eine freie Pfadangabe bietet dieses Panel nicht an; ein Test mit `.user.ini` konnte die erzwungene Einstellung nicht überschreiben. Temporäre PHP-Prüfdateien wurden anschließend entfernt.

Plesk-Git-Registrierung, Branchwahl, zusätzliche Bereitstellungsaktionen und Webhook-URL werden vom Panel verwaltet und sind über diesen eingeschränkten SSH-Zugang nicht administrierbar. Die [Deploymentanleitung](deployment.md) enthält die einzutragenden Werte.

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

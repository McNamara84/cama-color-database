# Anmeldung und Accounts

Die Startseite und `/health` bleiben öffentlich. Spätere Fachmodule können ausdrücklich öffentliche Leserouten erhalten. `/admin/users` ist ausschließlich für den automatisch eingerichteten Administrator zugänglich. Es gibt keine Selbstregistrierung und keine öffentliche Accountliste. Neue Accounts sind normale Benutzer; ihre Rolle kann nicht über Formulare verändert werden.

## Ersteinrichtung

In der ignorierten lokalen `.env` beziehungsweise auf Netcup in `shared/.env` eintragen:

```dotenv
ADMIN_EMAIL='deine-adresse@example.org'
ADMIN_PASSWORD='hier-ein-eigenes-langes-startpasswort-eintragen'
```

Die Beispiele ersetzen, nicht als Zugangsdaten übernehmen. Das Passwort muss 15 bis 128 Zeichen umfassen; Leerzeichen und Unicode sind erlaubt. `.env` mit Dateirechten `0600` schützen. Passwörter weder im Chat noch als Kommandozeilenargument oder GitHub-Secret für das Deployment hinterlegen. Die Konfiguration auf Netcup wird unabhängig von Docker gepflegt.

Lokal ausführen:

```sh
docker compose run --rm php composer app:install
```

Der Installer führt die Phinx-Migrationen mit dem Migrationskonto aus und legt den Administrator mit dem Anwendungskonto an. Ein eindeutiger Datenbankschlüssel erlaubt nur einen solchen Admin. Wiederholte Aufrufe erhalten ID, E-Mail, Passwort und Änderungsstatus eines vorhandenen Admins, auch wenn sich die Konfigurationswerte geändert haben oder fehlen. Der Installer befördert keine vorhandenen normalen Accounts. Die Webanwendung führt niemals Migrationen oder die Ersteinrichtung während eines Seitenaufrufs aus.

Nach erfolgreicher Einrichtung kann `ADMIN_PASSWORD` aus der jeweiligen Konfiguration entfernt werden. Es ist kein dauerhafter Zugangsschlüssel und kein Mechanismus zum Zurücksetzen eines Passworts.

## Nutzung

1. `/login` öffnen und mit E-Mail-Adresse und Startpasswort anmelden.
2. Beim ersten Login ein eigenes Passwort setzen. Dazu ist das aktuelle Startpasswort erforderlich.
3. Als Admin unter **Accounts** Name, E-Mail-Adresse und Startpasswort für einen neuen Benutzer eingeben. Das Startpasswort sicher persönlich weitergeben; die Anwendung versendet keine E-Mails.
4. Der neue Benutzer meldet sich mit diesen Zugangsdaten an und ändert ebenfalls zunächst sein Startpasswort.
5. Über **Passwort ändern** kann jeder angemeldete Benutzer sein eigenes Passwort ändern. **Abmelden** beendet die Sitzung und löscht das Cookie.

E-Mail-Adressen werden getrimmt und für Anmeldung und Speicherung in Kleinbuchstaben umgewandelt; doppelte Adressen werden abgewiesen. Namen werden als Text behandelt und in HTML escaped. Passwörter werden niemals normalisiert, zurück in Formulare geschrieben oder protokolliert. Derzeit gibt es keine Funktionen zum Löschen, Sperren oder administrativen Zurücksetzen von Accounts.

## Schutz und Prüfungen

- Argon2id mit 19 MiB Speicher, zwei Durchläufen und einem Thread; Unterstützung in Docker und in Netcups PHP 8.5.12 bestätigt. Bestehende schwächere Hashes werden nach einer erfolgreichen Anmeldung aktualisiert.
- Neue Session-ID und neues CSRF-Token bei Anmeldung und Passwortwechsel; vorherige Sessiondatei wird entfernt. Logout erfordert POST und ein gültiges CSRF-Token.
- `HttpOnly`, `SameSite=Lax` und produktiv `Secure`. Inaktivität beendet die Anmeldung nach 30 Minuten, die absolute Laufzeit beträgt zwölf Stunden. Passwortänderungen machen andere Sitzungen beim nächsten geschützten Aufruf ungültig.
- Höchstens fünf Anmeldeversuche je normalisierter E-Mail und 30 je IP innerhalb eines 15-Minuten-Fensters. Erfolgreiche Anmeldung setzt den E-Mail-Zähler zurück. Der IP-Zähler bleibt bestehen. Unbekannte Adressen liefern dieselbe Meldung wie falsche Passwörter. Auch das Prüfen des bisherigen Passworts beim Passwortwechsel ist begrenzt.
- Zähler liegen in MySQL/MariaDB und wirken über PHP-Prozesse und Deployments hinweg. Die Schlüssel enthalten SHA-256-Hashes, keine E-Mail- oder IP-Klartexte. Abgelaufene Zähler werden bei weiteren Versuchen entfernt. Ungeprüfte Proxyheader werden nicht als Client-IP übernommen.
- Die Datenbankverbindung setzt einen einheitlichen strikten SQL-Modus pro Sitzung; globale Hosting-Einstellungen bleiben unverändert.

Grundlagen: [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [PHP-Passworthashing](https://www.php.net/manual/en/function.password-hash.php) und [PHP-Sessionsicherheit](https://www.php.net/manual/en/features.session.security.management.php).

PHPUnit prüft gültige und ungültige Anmeldungen, Eingabegrenzen, unbekannte Felder, Rollenmanipulation, CSRF, Sessionablauf, Passwortwechsel, Logout, Datenbankregeln und wiederholte Installation. `tests/auth-smoke.php` prüft zusätzlich echte HTTP-Cookies und den gesamten Admin-/Benutzerablauf. Das Produktionspaket wird ohne Xdebug und Entwicklungsabhängigkeiten installiert und geprüft. CI führt die Suite auf MariaDB 10.11 und zusätzlich MySQL 8.4.13 aus.

## Erste Bereitstellung auf Netcup

Diese Erweiterung benötigt erstmals die Tabellen `users` und `login_attempts`. Vor der Schemaänderung einen Datenbankexport sichern. Die Migration ergänzt ausschließlich neue Tabellen und verändert keine vorhandenen Fachdaten.

1. Eigene Hosting-Adminwerte in `shared/.env` eintragen; sie werden nicht aus der lokalen Docker-Konfiguration übernommen.
2. Den geprüften Commit auf `main` veröffentlichen. Plesk importiert das neue Paket; die Aktivierung stoppt zunächst wegen der ausstehenden Migration. Das bisherige Release bleibt aktiv und das neue liegt unter `releases/<vollständige-Commit-ID>` bereit, bereits mit der privaten Konfiguration verknüpft.
3. Nach Prüfung des Zielpfads über SSH genau diesen Installer ausführen:

```sh
/usr/local/php85/bin/php /cama.cats4future.de/releases/<vollständige-Commit-ID>/bin/install.php
```

4. Den CI-Lauf erneut starten. Die Bereitstellung prüft nun den Migrationsstatus, führt die wiederholbare Adminprüfung ohne Schemaänderungen aus und aktiviert das Release. Anschließend `/login` verwenden.

Auch spätere Deployments überschreiben keine Adminzugänge. Weitere Migrationen bleiben ausdrücklich kontrollierte Arbeitsschritte; `--bootstrap-only` führt keine Migration aus. Ein Zurückrollen der Accountmigration löscht Accounts und Anmeldezähler und ist deshalb ausschließlich für isolierte Tests vorgesehen. Ein Code-Rollback ersetzt keine Datenbankwiederherstellung.

# Bereitstellung und Wiederherstellung

## Freischaltung

CI läuft bei Pull Requests und Pushes auf `main`. Ein veröffentlichtes GitHub-Release oder ein manueller Aufruf des Deployment-Workflows wählt einen vorhandenen Tag. Der Commit muss von `main` erreichbar sein. Der wiederverwendete CI-Workflow prüft genau diesen Commit, baut das Paket und prüft das Paket erneut ohne Entwicklungsabhängigkeiten.

Produktionsdeployments bleiben durch die Repositoryvariable `NETCUP_DEPLOY_ENABLED` ausgeschaltet, bis der [Hostingabgleich](hosting.md) abgeschlossen ist. Nach Einrichtung wird sie auf `true` gesetzt. Es wurden in dieser Umsetzung keine GitHub-Secrets, Environments oder Netcup-Einstellungen geändert.

Das Workflow-Environment heißt `production`. GitHub muss Environments für das betreffende Repository unterstützen; andernfalls ist die Environment-Zeile vor Aktivierung zu entfernen und die Konfiguration als Repository-Secrets/-Variablen zu hinterlegen. [GitHub: Verfügbarkeit von Environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments)

| Art | Name | Inhalt |
| --- | --- | --- |
| Secret | `NETCUP_SSH_KEY` | Privater Deploy-Schlüssel, dessen öffentlicher Teil auf Netcup eingerichtet ist |
| Secret | `NETCUP_KNOWN_HOSTS` | Separat verifizierter OpenSSH-Hostschlüsseleintrag; kein ungeprüftes Laufzeit-ssh-keyscan |
| Secret | `NETCUP_HOST` | SSH-Hostname oder IPv4-Adresse |
| Secret | `NETCUP_USER` | SSH-Benutzer |
| Variable | `NETCUP_PATH` | Absoluter kanonischer Pfad zum Anwendungsverzeichnis im SSH-Kontext |
| Variable | `NETCUP_PHP` | Absoluter Pfad zur passenden PHP-CLI |
| Variable | `NETCUP_ORIGIN` | HTTPS-Origin ohne abschließenden Schrägstrich oder Unterpfad |

Der Transport verwendet SSH-Port 22. Pfade mit Leerzeichen und IPv6-Literale werden vom einfachen Transportskript derzeit nicht unterstützt. Zielparameter werden vor Verwendung im Remote-Shellbefehl validiert. GitHub Actions sind auf feste Commit-IDs gepinnt; Pull-Request-Checks erhalten keine Produktionszugänge. Ein neuer Deploy-Lauf bricht einen laufenden nicht ab.

## Einmalige Vorbereitung auf Netcup

Unter dem bestätigten Anwendungspfad folgende Struktur anlegen:

```text
application/
  releases/
  shared/
    .env
    var/
  current -> releases/<vollständige-commit-id>
```

`shared/.env` enthält `APP_ENV=production`, `APP_DEBUG=false`, den Projektnamen, den absoluten Pfad von `shared/var` als `APP_RUNTIME_DIR`, die echten `DB_*`-Werte und die getrennten `DB_MIGRATION_*`-Zugänge. `DB_ROOT_PASSWORD`, Testzugänge und Docker-Ports werden dort nicht benötigt. Die Konfiguration mit restriktiven Dateirechten außerhalb des Document-Roots ablegen. `shared/var` enthält Cache, Sessions und Logs; es muss für Web-PHP und die Deployment-CLI schreibbar sein. Das Hostingpanel auf `application/current/public` konfigurieren.

## Paket und Aktivierung

Ein Paket kann lokal mit einem vollständigen Git-Commit als Kennung gebaut und geprüft werden:

```sh
docker compose run --rm php sh bin/package "$(git rev-parse HEAD)"
docker compose run --rm --build package-test
```

`build/release.tar.gz` enthält eine explizite Auswahl der Laufzeitdateien, frisch aus `composer.lock` installierte Produktionsabhängigkeiten, das Composer-PHAR zur Plattformprüfung und eine `RELEASE`-Datei. Lokale Konfiguration, Tests, Docker, phpMyAdmin und der Implementierungsplan werden nicht mitgeliefert. Das Archiv verwendet normalisierte Dateizeiten und Eigentümer; die SHA-256-Prüfsumme wird separat gespeichert.

`bin/deploy` überträgt das Archiv per SSH/SCP, prüft die Prüfsumme und entpackt in ein neues Releaseverzeichnis. Ein bereits vorhandener Stand wird nur erneut verwendet, wenn dessen gespeicherte Archivprüfsumme übereinstimmt. Ein fehlgeschlagener Transfer oder eine unvollständige Extraktion verändert das aktive Release nicht; ein unvollständiges Zielverzeichnis wird vor einem erneuten Versuch nach Prüfung separat entfernt.

`bin/activate.php` sperrt parallele Aktivierungen, bindet die vorhandene `.env` an und prüft Konfiguration, Datenbankverbindung, Verzeichnisrechte, Plattformanforderungen und Phinx-Status. Es führt **keine automatischen Schemaänderungen** aus. Ausstehende oder im Paket fehlende Migrationen stoppen das Deployment. Im Grundgerüst gibt es noch keine Fachmigrationen; beim späteren Einführen ist der kompatible Migrationsablauf ausdrücklich zu ergänzen.

Danach wird `current` atomar auf das neue Release gesetzt. Ein HTTPS-Test muss die erwartete Release-ID und eine funktionierende Startseite liefern; damit wird auch ein veralteter OPcache-Stand erkannt. Schlägt die Prüfung fehl, wird der vorherige Link wiederhergestellt. Bei einem fehlgeschlagenen allerersten Deployment gibt es anschließend keinen aktiven Link. Der Workflow meldet den Fehler.

Ein Wiederholungsaufruf desselben Tags durchläuft die Prüfungen erneut. Das Paket muss bytegleich sein, wenn das Releaseverzeichnis bereits vorhanden ist. Die Migrationen dürfen keinen abweichenden Stand voraussetzen. Eine neue Konfiguration kann mit demselben Release geprüft werden, ohne Secrets in das Archiv zu schreiben.

## Migrationen, Backups und Rollback

Vor Schemaänderungen einen aktuellen Datenbankexport sichern und die Wiederherstellung in einer separaten Datenbank erproben. Neue Migrationen müssen während des Releasewechsels mit dem alten und neuen Anwendungscode kompatibel sein. Änderungen mit Ausfallzeit oder Datenverlust gehören in einen eigenen Wartungsablauf. Datenbankrechte und DDL-Verhalten sind auf dem vorhandenen Hosting zu prüfen.

Für einen Code-Rollback auf ein bereits geprüftes Release kann über SSH dessen `bin/activate.php` mit Basisverzeichnis, alter Release-ID und HTTPS-Origin aufgerufen werden. Der Phinx-Status muss auch dafür passen; fehlende alte Migrationen blockieren den Rollback. Ein Code-Rollback stellt keine Datenbank wieder her.

Bei einem notwendigen Datenrestore Schreibzugriffe im Hosting vorübergehend sperren, Konfiguration und aktuellen Zustand sichern, einen passenden Datenbankexport in eine getrennte Datenbank zurückspielen und überprüfen. Erst danach Anwendung und Datenbank gemeinsam auf den konsistenten Stand umstellen. Kein automatisches Einspielen alter Backups nach einem HTTP-Fehler.

Alte Releaseverzeichnisse werden vorerst bewusst manuell aufgeräumt: mindestens aktives und vorheriges erfolgreiches Release behalten; zusätzlich vor dem Entfernen prüfen, ob sie für Rollback oder Datenmigrationen gebraucht werden. `shared/` darf dabei nicht entfernt werden. Der aktive Cache ist nach Releasepfad getrennt; nicht mehr verwendete Cacheverzeichnisse können gemeinsam mit alten Releases aufgeräumt werden.

Die automatisierten Tests prüfen den lokalen Dateisystemwechsel und seine Fehlerpfade sowie das Produktionspaket. SSH-Anmeldung, Netcup-Chroot, reale Hostschlüssel und Web-PHP/OPcache auf dem vorhandenen Paket können erst mit der eingerichteten Zielumgebung verifiziert werden.

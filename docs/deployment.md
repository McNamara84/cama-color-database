# Bereitstellung über Netcup / Plesk Git

Ziel: **https://cama.cats4future.de**, vorhandenes Webhosting 2000. Die Anwendung wurde am 15. November 2026 erfolgreich aktiviert und per HTTPS geprüft. CLI und Web-PHP verwenden 8.5.12; die Datenbank ist MySQL 8.4.13. Die Git-Anbindung im Webhostingpanel übernimmt den Download; GitHub Actions braucht keinen SSH-Zugang zum Hosting. Den konkreten Befund dokumentiert der [Hostingabgleich](hosting.md).

Die vollständige Kette ist mit [CI-Lauf 43728179882](https://github.com/McNamara84/cama-color-database/actions/runs/43728179882) auf `main` bestätigt: Prüfungen und Veröffentlichung waren erfolgreich, der Webhook wurde um 15:10:43 UTC aufgerufen und Plesk aktivierte das Release um 15:10:46 UTC. Die anschließende externe HTTPS-Prüfung meldete `status: ok` und Release `2977ff838b730ed69e59abca78da8edf825362a2`. Die folgenden Einrichtungsschritte sind für dieses Hosting bereits abgeschlossen; weitere PR-Merges und direkte Pushes nach `main` verwenden denselben Ablauf.

## Ablauf

1. Ein offener Pull Request nach `main` führt ausschließlich die CI-Prüfungen aus.
2. Nach einem PR-Merge oder direkten Push auf `main` laufen dieselben Prüfungen. CI baut ein Produktionspaket inklusive `vendor/`, testet es ohne Entwicklungsabhängigkeiten und speichert es als Artefakt.
3. Nur nach erfolgreicher CI veröffentlicht der Deploymentjob genau dieses Artefakt auf dem Branch **`deploy`**. Er prüft vorher, ob der getestete Commit noch der aktuelle `main`-Stand ist. Überholte Läufe werden übersprungen; Veröffentlichungen laufen nacheinander und verwenden keinen Force-Push.
4. CI ruft den HTTPS-Webhook des Plesk-Git-Repositories auf. Plesk zieht `deploy` und kopiert dessen Dateien in den vorbereiteten Ordner `deploy/` auf dem Hosting.
5. Die zusätzliche Bereitstellungsaktion importiert anhand des SHA-256-Manifests ein vollständiges Release, prüft PHP-Plattform, Datenbank und Migrationsstatus und schaltet erst dann `current` um. Fehler beim HTTPS-Test stellen den vorherigen Link wieder her.
6. Auch CI wartet darauf, dass `/health` exakt die erwartete Commit-ID meldet und die Startseite erreichbar ist. Ein angenommener Webhook allein gilt nicht als erfolgreicher Live-Test.

`deploy` ist ein automatisch erzeugter Veröffentlichungsbranch. Dort nicht entwickeln, keine PRs zurück nach `main` erstellen und keine manuellen Änderungen ablegen. Der Branch enthält Produktionsabhängigkeiten, keine `.env`, Tests, Dockerdateien, Workflows oder Implementierungspläne. Plesk muss **`deploy`** auswählen: Ein direktes Ausrollen von `main` würde die CI-Prüfungen umgehen und keine Composer-Abhängigkeiten enthalten.

## 1. GitHub vorbereiten und den Branch erzeugen

Die Änderungen an den Workflows zunächst auf `main` pushen. Unter [Settings → Secrets and variables → Actions → Variables](https://github.com/McNamara84/cama-color-database/settings/variables/actions) die Repositoryvariable **`NETCUP_DEPLOY_ENABLED` = `true`** anlegen. Anschließend unter **Actions → CI → Run workflow → main** einen Lauf starten, falls der Push bereits davor lief.

Beim ersten Lauf darf `PLESK_WEBHOOK_URL` noch fehlen: Die CI erzeugt dann nur den geprüften Branch `deploy` und weist in der Zusammenfassung auf die ausstehende Hosting-Einrichtung hin. Sie behauptet in diesem Zustand keinen erfolgreichen Live-Deploy. Bei ausgeschalteter oder fehlender Variable wird auch der Branch nicht veröffentlicht.

Nur der Deploymentjob erhält `contents: write`; der Testjob erhält Leserechte. Der Job verwendet den kurzlebigen `GITHUB_TOKEN`, keinen persönlichen Zugriffstoken. Repositoryregeln müssen diesem Job das Schreiben auf `deploy` erlauben. Der Schutz von `main` bleibt bestehen. Ein kostenpflichtiges GitHub-Environment ist nicht erforderlich.

## 2. Den Dialog aus dem Screenshot ausfüllen

| Feld | Wert |
| --- | --- |
| Code-Speicherort | **Remote-Repository** |
| Repository-URL | `git@github.com:McNamara84/cama-color-database.git` |
| Repository-Name | `cama-color-database.git` (tatsächlich angelegter Name) |
| Bereitstellungsmodus beim Einrichten | **Deaktiviert** |
| Serverpfad | **`/cama.cats4future.de/deploy`** |
| Zusätzliche Bereitstellungsaktionen | Nach Vorbereitung wie unten beschrieben aktivieren |

Der Serverpfad bezeichnet den Ordner, in den Plesk Git-Dateien kopiert. Als Git-Ziel dient `deploy`; `httpdocs` bleibt die öffentliche Wurzel und wird wie in Schritt 3 beschrieben mit dem aktiven Release verknüpft. Vorhandene Inhalte wurden dabei gesichert.

Bei Eingabe der SSH-URL zeigt Plesk einen öffentlichen SSH-Schlüssel. Diesen unter [GitHub → Settings → Deploy keys](https://github.com/McNamara84/cama-color-database/settings/keys) als `Netcup CAMA read-only` hinzufügen. **Allow write access ausgeschaltet lassen.** Der private Schlüssel verbleibt bei Plesk. Danach das Repository erstellen. Nach dem ersten erfolgreichen CI-Lauf in Plesk **Updates abrufen / Pull Updates** und unter **Branch und Pfad ändern** den Branch **`deploy`** wählen.

Die SSH-Authentifizierung für private Repositories, Branchwahl und zusätzlichen Aktionen sind in der [Plesk-Dokumentation](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/git-support/using-remote-git-hosting.75848/) beschrieben.

## 3. Dateien, Datenbank und öffentliche Wurzel vorbereiten

Im Dateimanager des Hostingpanels unter `cama.cats4future.de` die Verzeichnisse `shared` und `shared/var` anlegen. Eine eigene Datenbank für CAMA samt Benutzer im Hostingpanel erstellen. [production.env.example](production.env.example) als **`shared/.env`** speichern und die Platzhalter durch die Hosting-Datenbankzugänge ersetzen. Diese Datei nicht ins Git-Repository einchecken oder in den Chat kopieren. Dateirechte `0600`; `shared/var` muss für Web-PHP und CLI schreibbar sein.

`APP_RUNTIME_DIR` in dieser Konfiguration weglassen: Cache, Sessions und Logs werden automatisch in `var` neben der tatsächlichen `.env` gespeichert. Relative Konfigurationslinks funktionieren auch, wenn CLI und Web-PHP unterschiedliche Chroot-Pfade sehen. Docker-Ports, Root-Passwort und lokale Testzugänge werden nicht übernommen. Falls der Tarif keine getrennten Datenbankbenutzer zulässt, können die beiden Benutzer-/Passwortpaare denselben bereitgestellten Datenbankzugang verwenden; dies im Hostingabgleich vermerken.

Die spätere Struktur lautet:

```text
cama.cats4future.de/
  deploy/                     ← Plesk-Git-Ziel, nicht öffentlich
    RELEASE
    MANIFEST.sha256
    vendor/
    bin/plesk-deploy.php
    ...
  shared/
    .env                      ← nur auf dem Hosting
    var/                      ← Cache, Sessions, Logs
  releases/
    <vollständige-main-sha>/   ← verifizierte, unveränderte Laufzeitdateien
  current -> releases/<sha>
  httpdocs -> current/public   ← vorhandener Document-Root dieses Netcup-Pakets
```

Die Subdomain auf **PHP 8.5**, HTTPS mit gültigem Zertifikat und einen ausschließlich auf `current/public` zeigenden Document-Root einstellen. Auf dem vorhandenen Paket wurde am 15. November 2026 der Link **`httpdocs -> current/public`** eingerichtet; im Panel kann deshalb **`/cama.cats4future.de/httpdocs`** bleiben. Die früheren Inhalte von `httpdocs` sind unter `shared/previous-httpdocs-20261115T144539Z` gesichert. `.htaccess`/Rewrite müssen aktiv sein.

In den **PHP-Einstellungen → open_basedir** den zweiten Dropdown-Eintrag **beginnend mit `{WEBSPACEROOT}`** wählen und speichern. Der Standardwert mit `{DOCROOT}` ist für diesen Aufbau zu eng. Das vorhandene Netcup-Panel erlaubt hier keine freie Eingabe; eine `.user.ini` kann die Beschränkung nicht aufheben. Dieser Panel-Schritt benötigt keinen Root-Zugang.

`current` muss ein symbolischer Link sein, kein normales Verzeichnis. Falls das Panel vor dem Speichern einen existierenden Document-Root verlangt, einmalig über die vorhandene SSH-Shell einen Platzhalter vorbereiten. Die folgenden Befehle setzen den im Screenshot sichtbaren Chroot-Pfad voraus; bei abweichendem Shellpfad diesen anpassen:

```sh
cd /cama.cats4future.de
mkdir -p releases/bootstrap/public
test ! -e current && test ! -L current && ln -s releases/bootstrap current
```

Ein vorhandenes `current` dabei nicht löschen oder überschreiben. Beim ersten erfolgreichen Deployment ersetzt die Anwendung den Platzhalterlink. Dafür sind keine Root-Rechte erforderlich. Das Verzeichnis `current` niemals vom Dateimanager als echten Ordner anlegen lassen.

## 4. Zusätzliche Bereitstellungsaktion eintragen

Den absoluten PHP-8.5-CLI-Pfad in der Hosting-Shell prüfen, beispielsweise:

```sh
/usr/local/php85/bin/php -v
```

Dieser Pfad und PHP **8.5.12** sind auf dem konkreten Paket per SSH bestätigt; auch Web-PHP verwendet 8.5.12. Die [Netcup-Dokumentation zu PHP in der Shell](https://www.netcup.com/en/helpcenter/documentation/web-hosting/php-shell) beschreibt versionsspezifische Pfade und weist darauf hin, dass ein bloßes `php` eine andere Standardversion verwenden kann.

Unter **Zusätzliche Bereitstellungsaktionen aktivieren** folgende einzelne Zeile mit dem verifizierten PHP- und Chroot-Pfad eintragen:

```sh
/usr/local/php85/bin/php /cama.cats4future.de/deploy/bin/plesk-deploy.php https://cama.cats4future.de
```

Das Skript leitet das Basisverzeichnis aus seinem eigenen Speicherort ab; ein bestimmtes Arbeitsverzeichnis ist nicht erforderlich. Es benötigt `proc_open`, Dateisperren, symbolische Links und HTTPS-Zugriff mit gültigen CA-Zertifikaten. `open_basedir` muss `deploy`, `releases` und `shared` innerhalb dieses Anwendungsverzeichnisses zulassen. Ein Fehler ist im Plesk-Bereitstellungsprotokoll sichtbar. Auf dem Hosting werden weder Composer-Pakete heruntergeladen noch Docker, Node.js oder Root benötigt.

Für einen ersten kontrollierten Versuch den Modus **Manuell** wählen und den Branch `deploy` bereitstellen. Bei erfolgreichem Aufruf von `https://cama.cats4future.de/health` muss `release` den getesteten `main`-Commit enthalten. Danach den Bereitstellungsmodus auf **Automatisch** umstellen.

## 5. Automatische Benachrichtigung einschalten

In den Einstellungen des Plesk-Git-Repositories die erzeugte **Webhook-URL** kopieren. Die vollständige HTTPS-URL unter [GitHub → Actions secrets](https://github.com/McNamara84/cama-color-database/settings/secrets/actions) als Repository-Secret **`PLESK_WEBHOOK_URL`** speichern. Sie enthält einen Zugangstoken; nicht öffentlich dokumentieren. HTTPS-Zertifikatsprüfung bleibt eingeschaltet, HTTP-URLs und Weiterleitungen werden abgelehnt.

**Keinen zusätzlichen GitHub-Push-Webhook einrichten.** In diesem Projekt benachrichtigt ausschließlich der erfolgreiche CI-Deploymentjob Plesk. Die Plesk-Webhook-URL ist dafür der Endpunkt; die automatische GitHub-Webhook-Einrichtung aus allgemeinen Plesk-Anleitungen ist hier nicht nötig.

Erneut **Actions → CI → Run workflow → main** ausführen. Danach lösen PR-Merges und direkte Pushes nach `main` die komplette Kette aus. Bei einer Wiederholung desselben Pakets bleibt der Branch-Commit unverändert; Plesk wird trotzdem erneut benachrichtigt. Ein veralteter Lauf wird übersprungen. Falls ein neuerer `main`-Commit fehlschlägt, bleibt der zuletzt veröffentlichte Stand aktiv.

Die vorhandene GitHub-Anbindung des Assistenten kann Repositorydateien lesen und bearbeiten, aber keine Actions-Secrets oder Netcup-Panel-Einstellungen setzen. Diese einmaligen Einstellungen sind deshalb im jeweiligen Panel vorzunehmen.

## Lokale Prüfung und Fehlerdiagnose

```sh
docker compose build test
docker compose run --rm test composer check
docker compose run --rm test composer test:coverage
docker compose run --rm test php bin/check-coverage.php
docker compose run --rm php sh bin/package "$(git rev-parse HEAD)"
docker compose run --rm --build package-test
docker compose run --rm test bash tests/plesk-publish-smoke.sh
docker compose run --rm test bash tests/plesk-notify-smoke.sh
```

Die Git-Tests verwenden ein echtes lokales Bare-Repository, keine GitHub-Schreibzugänge. Webhooktests simulieren HTTP-Antworten einschließlich Fehlern und falscher Release-ID. Pakettests importieren das vollständige Produktionspaket und prüfen die Anwendung ohne Entwicklungsabhängigkeiten. Der oben verlinkte CI-Lauf und die anschließende Hostingprüfung bestätigen zusätzlich die vollständige Bereitstellung über das echte Plesk.

Bei einem fehlgeschlagenen Lauf zuerst die CI-Zusammenfassung und das Plesk-Git-Bereitstellungsprotokoll prüfen. Ein fehlender Deploy-Key führt zum Git-Fehler; eine fehlende oder falsche `shared/.env` stoppt vor Aktivierung; eine falsche PHP-CLI oder ausstehende Migrationen stoppen beim Preflight. HTTPS-, Document-Root- oder OPcache-Probleme führen zu fehlgeschlagener Releaseprüfung und Rückfall auf den vorherigen Stand. Bei Fehlern nach Veröffentlichung des Branches kann derselbe CI-Lauf erneut gestartet werden.

`bin/deploy` bleibt als separat getesteter manueller SSH/SCP-Transport erhalten, wird vom Plesk-Workflow aber nicht verwendet. Die früheren `NETCUP_SSH_KEY`, `NETCUP_KNOWN_HOSTS`, `NETCUP_HOST`, `NETCUP_USER`, `NETCUP_PATH`, `NETCUP_PHP` und `NETCUP_ORIGIN` werden für diesen Workflow nicht benötigt.

## Migrationen, Backups und Rollback

Die Aktivierung führt keine automatischen Schemaänderungen aus. Ausstehende oder im Paket fehlende Migrationen stoppen das Deployment. Die Account-Erweiterung benötigt erstmals zwei Tabellen; die [Anleitung zur ersten Account-Bereitstellung](accounts.md#erste-bereitstellung-auf-netcup) beschreibt Datenbankexport, private Admin-Konfiguration, kontrollierte Installation und erneuten CI-Lauf. Nach erfolgreicher Schemaprüfung führt jede Aktivierung `bin/install.php --bootstrap-only` aus; vorhandene Adminzugänge bleiben dabei unverändert.

Vor Schemaänderungen einen aktuellen Datenbankexport sichern und die Wiederherstellung in einer separaten Datenbank erproben. Neue Migrationen müssen während des Releasewechsels mit dem alten und neuen Anwendungscode kompatibel sein. Änderungen mit Ausfallzeit oder Datenverlust gehören in einen eigenen Wartungsablauf. Datenbankrechte und DDL-Verhalten sind auf dem vorhandenen Hosting zu prüfen.

Für einen Code-Rollback auf ein bereits geprüftes Release kann über SSH dessen `bin/activate.php` mit Basisverzeichnis, alter Release-ID und HTTPS-Origin aufgerufen werden. Der Phinx-Status muss auch dafür passen; fehlende alte Migrationen blockieren den Rollback. Ein Code-Rollback stellt keine Datenbank wieder her.

Bei einem notwendigen Datenrestore Schreibzugriffe im Hosting vorübergehend sperren, Konfiguration und aktuellen Zustand sichern, einen passenden Datenbankexport in eine getrennte Datenbank zurückspielen und überprüfen. Erst danach Anwendung und Datenbank gemeinsam auf den konsistenten Stand umstellen. Kein automatisches Einspielen alter Backups nach einem HTTP-Fehler.

Alte Releaseverzeichnisse werden vorerst bewusst manuell aufgeräumt: mindestens aktives und vorheriges erfolgreiches Release behalten; zusätzlich vor dem Entfernen prüfen, ob sie für Rollback oder Datenmigrationen gebraucht werden. `shared/` darf dabei nicht entfernt werden. Der aktive Cache ist nach Releasepfad getrennt; nicht mehr verwendete Cacheverzeichnisse können gemeinsam mit alten Releases aufgeräumt werden.

Die automatisierten Tests prüfen den lokalen Dateisystemwechsel und seine Fehlerpfade sowie das Produktionspaket. Zusätzlich wurde die erfolgreiche Aktivierung über Plesk im Netcup-Chroot mit anschließender Prüfung über Web-PHP bestätigt. Ein fehlgeschlagenes Deployment mit Rollback wurde auf dem Live-Hosting nicht absichtlich ausgelöst; diese Fehlerpfade werden lokal getestet.

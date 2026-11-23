# Zimmer auf dem Dach

Spendenseite für die Adventistische Privatschule Klagenfurt. Ziel 40.000 €. Öffentliche Seite unter `/`, Verwaltung unter `/admin`. PHP ab 8.1 mit PDO MySQL und mbstring, MySQL/MariaDB mit InnoDB, HTTPS und funktionierendem PHP-Mailversand.

## Einrichtung bei Netcup mit Plesk

1. Lege die Dateien aus diesem Ordner in dein öffentliches GitHub-Repository. Sie enthalten keine Beispieldatensätze oder Zugangsdaten.
2. Wähle in Plesk beim gewünschten Host „Remote-Repository“, die HTTPS-Klonadresse von GitHub und den Branch `main`. Beispiel für den Bereitstellungspfad: `/apk.toak.de/httpdocs`. Stelle in den Hosting-Einstellungen als **Dokumentenstamm** dann **`/apk.toak.de/httpdocs/public`** ein. Nur der Inhalt von `public/` darf über den Browser erreichbar sein. Prüfe vor dem ersten Deployment, ob der Zielordner ausschließlich für dieses Projekt vorgesehen ist.
3. Lege eine eigene Datenbank und einen Datenbankbenutzer an. Importiere einmalig `install/schema.sql` in phpMyAdmin. Bei späteren Updates bleibt die Datenbank bestehen.
4. Kopiere auf dem Server `env.php.example` als `env.php` neben den Ordner `app` und trage Datenbankzugang, HTTPS-Adresse der Seite, Absenderadresse und einen zufälligen Schlüssel ein. Den Schlüssel kannst du mit `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` erzeugen. Die private Datei ist per .gitignore ausgeschlossen. Die konfigurierte Absenderadresse muss für den Mailversand über deinen Webspace geeignet sein. Die App verwendet PHP mail() und den Mailtransport des Hosters.
5. Schalte die erste echte Adminadresse in phpMyAdmin mit dem folgenden SQL frei. Ersetze die Beispieladresse vorher. Weitere Konten verwaltest du anschließend unter `/admin`.

       INSERT INTO admins (email, identity_key)
       VALUES (LOWER('deine-adresse@example.org'), HEX(RANDOM_BYTES(32)));

6. Öffne `/admin`, fordere den Link an und bestätige die Anmeldung im Browser. Prüfe die Zustellung mit deiner echten Mailbox. Trage dann den ersten Gesamtstand samt Stichtag ein. Bis dahin zeigt die Seite nur das Ziel und die offenen Zusagen.
7. Für automatische Updates hinterlegst du die Webhook-URL aus Plesk im GitHub-Repository für Push-Ereignisse und aktivierst in Plesk die automatische Bereitstellung. Änderungen des Datenbankschemas gesondert migrieren. Vor Datenbankänderungen ein Backup erstellen.

## Bedienung

- Normal zugesagte Beträge erscheinen ab Abgabe 28 Tage lang öffentlich in der Summe „Zugesagt“. Mit der Option zur Spendenabsetzbarkeit gilt keine Frist. Die Verwaltung zeigt auch abgelaufene Zusagen für spätere Zahlungseingänge.
- „Erinnert“ hält den Zeitpunkt des manuellen Anrufs oder der E-Mail fest. „Eingegangen“ bucht den zugesagten Betrag genau einmal und verwendet das heutige Datum in Österreich. Ist eine Zahlung bereits im erfassten Kontoauszug enthalten, beim Abgleich zuerst die Zusage als eingegangen markieren und anschließend den Gesamtstand laut Auszug eintragen.
- Der Gesamtstand zum Stichtag enthält alle bis zu diesem Tag gebuchten Eingänge. Später gebuchte Eingänge addiert die Anwendung automatisch. Eine inzwischen geänderte Zahl wird beim Speichern gemeldet.
- Freigeschaltete Personen haben dieselben Verwaltungsrechte. Ein entzogener Zugang verliert auch mit bereits geöffnetem Browser seine Berechtigung. Anmeldelinks gelten einmalig 15 Minuten, Sitzungen maximal acht Stunden.
- Projektfoto und Logo stammen von der Schulwebsite. Das Baustellenfoto kann in public/index.php durch das fertige Zimmer ersetzt werden. Für einen Datenabgleich mit realen Kontobewegungen nutzt du den tatsächlichen Kontoauszug.

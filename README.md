# Damas Market – Website mit Angebots-Katalog und Admin-Bereich

Website für **Damas Market – Arabische & Türkische Lebensmittel**:

* **Angebote** wie ein Prospekt (mit Gültigkeit, Preis, durchgestrichenem Altpreis, „−20 %“-Banderole, Live-Suche)
* **Sortiment** mit Bereichen (Lebensmittel, Getränke, Haushaltswaren, Geschenkartikel), Kategorien, Suche, Produktseiten, Name auch auf Arabisch/Türkisch
* **Kontakt & Öffnungszeiten** mit automatischem „Jetzt geöffnet / Geschlossen“
* **Admin-Bereich** (`/admin`) – alles ohne Programmierkenntnisse bearbeiten

Design in den Logo-Farben (Waldgrün, Gold, Hellgrün, Creme). Schrift Barlow wird vom eigenen Server geladen (keine Google-Fonts → DSGVO-freundlich). Keine Cookies für Besucher, keine Tracker.

## Was der Admin-Bereich kann

| Bereich | Funktionen |
|---|---|
| Übersicht | Kennzahlen, „Erste Schritte“-Checkliste, Beispieldaten laden/entfernen |
| Produkte | Anlegen/Bearbeiten/Verstecken/Löschen, Bild-Upload (wird automatisch verkleinert), Preis optional, „Neu“-Marker, „nicht verfügbar“, Suche & Filter, **Excel/CSV-Import und -Export** |
| Angebote | Produkt wählen oder freies Angebot, Preis/Altpreis, **Zeitraum** (läuft automatisch ab), eigene Banderole („2 für 5 €“), pausieren, kopieren, Abgelaufene aufräumen |
| Kategorien | Anlegen, umbenennen, sortieren, verstecken |
| Seiten | Impressum, Datenschutz, Über uns und eigene Seiten (einfache Formatierung) |
| Einstellungen | Name/Slogan, Banner-Hinweis, Startseiten-Texte & Titelbild, Adresse/Telefon/WhatsApp/Social, **Öffnungszeiten**, **Farben & Logo**, Prospekt-PDF, Google-Beschreibung |
| Konto & Backup | Benutzername/Passwort ändern, Datenbank- und Bilder-Backup herunterladen |

## Installation auf deiner Domain

**Voraussetzung:** Webhosting mit **PHP 8.1 oder neuer** (Erweiterungen `pdo_sqlite`, `gd` mit WebP, `fileinfo` – bei fast jedem Hoster Standard, z. B. IONOS, Strato, all-inkl, Hostinger). Eine Datenbank muss nicht eingerichtet werden (SQLite).
GitHub Pages reicht **nicht**, weil die Seite PHP braucht.

1. **Dateien hochladen** (FTP oder Dateimanager des Hosters):
   * Inhalt des Ordners `public/` → in das Webverzeichnis der Domain (oft `public_html` oder `htdocs`)
   * Ordner `app/` → **neben** das Webverzeichnis (eine Ebene darüber, also *nicht* öffentlich erreichbar). Liegt `app/` woanders, den Pfad in `_boot.php` anpassen (`APP_DIR`).
   * Ist das nicht möglich, `app/` in das Webverzeichnis legen – dann schützen die mitgelieferten `.htaccess`-Dateien den Ordner (nur Apache; bei nginx-Hostern bitte `app/` außerhalb ablegen!).
2. **Schreibrechte:** Die Ordner `app/data` und `uploads` müssen für PHP beschreibbar sein (meist Rechte 755 oder 775).
3. **HTTPS** beim Hoster aktivieren (kostenloses Zertifikat) und die Domain auf `https://` stellen.
4. **Admin einrichten:** `https://deine-domain.de/admin/` öffnen. Der **Installationscode** steht in der Datei `app/data/setup-code.txt` (per FTP ansehen). Benutzername und Passwort festlegen (mind. 10 Zeichen, Buchstaben + Zahlen). Danach ist die Einrichtung gesperrt und die Datei wird gelöscht.
5. **Inhalte pflegen:** Auf der Übersicht den Punkten unter „Erste Schritte“ folgen. Unter **Seiten** unbedingt **Impressum** und **Datenschutz** ausfüllen und rechtlich prüfen lassen (Pflicht in Deutschland).

> Nach der Einrichtung: Wer `app/data/` (Datenbank) hat, hat alle Daten. Backups nicht öffentlich ablegen.

## Online-Vorschau auf GitHub Pages (nur zum Zeigen)

Der Ordner `docs/` enthält eine **statische Vorschau** der öffentlichen Seiten mit Beispieldaten (kein Admin, Suche/Filter-Formulare ohne Funktion).
Einschalten: Repo → **Settings → Pages → Source: „Deploy from a branch“ → Branch wählen → Ordner `/docs` → Save**.
Neu erzeugen nach Änderungen am Design: `python3 tools/build_preview.py`.
Die echte Seite mit Admin-Bereich braucht PHP-Hosting (siehe oben).

## Backup / Umzug

Konto & Backup → Datenbank und Bilder herunterladen. Zum Wiederherstellen `damas.sqlite` nach `app/data/` und den Ordner `uploads/` zurückkopieren.

## Lokal testen

```bash
DAMAS_DATA_DIR=/tmp/damas-data php -S localhost:8000 -t public
```

`http://localhost:8000` (Website) und `http://localhost:8000/admin/` (Admin; Code steht in `/tmp/damas-data/setup-code.txt`).

```bash
php tests/run.php   # Tests der Logik (Preise, Angebote, Öffnungszeiten, Markdown, Sicherheit …)
```

## Sicherheit (eingebaut)

Passwörter gehasht (bcrypt/Argon), CSRF-Schutz auf allen Formularen, Login-Bremse (6 Fehlversuche / 15 Min.), Session-Cookie `HttpOnly`/`SameSite`, vorbereitete SQL-Abfragen, alle Ausgaben escaped, strenge Content-Security-Policy (keine Inline-Skripte), Bild-Uploads werden geprüft und neu erzeugt (kein Schadcode), PDF-Upload geprüft, Upload-Ordner führt keine Skripte aus.

## Aufbau

```
app/            Programmcode + Datenbank (nicht öffentlich)
  lib/          helpers, db, auth, upload, layout, demo
  data/         damas.sqlite, Sessions (wird automatisch angelegt)
public/         das Webverzeichnis
  index.php, angebote.php, produkte.php, produkt.php, kontakt.php, seite.php
  admin/        Admin-Bereich
  assets/       CSS, JS, Schriften (Barlow, SIL OFL)
  uploads/      hochgeladene Bilder/PDF
tests/          run.php
```

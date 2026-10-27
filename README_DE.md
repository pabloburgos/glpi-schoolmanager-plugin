<p align="center">
  <img src="docs/assets/banner.png" alt="School Manager Banner" width="100%">
</p>

<h1 align="center">School Manager für GLPI</h1>

<p align="center">
  GLPI-Plugin für Schulen und Bildungseinrichtungen: geführtes Ticket-System, IT-Lagerbestand, Raumpläne, vereinfachtes Asset-Management und Techniker-Workflows.
</p>

<p align="center">
  <a href="README.md">English</a> ·
  <a href="README_ES.md">Español</a> ·
  <a href="README_FR.md">Français</a> ·
  <b>Deutsch</b> ·
  <a href="README_IT.md">Italiano</a> ·
  <a href="#uebersicht">Übersicht</a> ·
  <a href="#sprachen">Sprachen</a> ·
  <a href="#voraussetzungen">Voraussetzungen</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#screenshots">Screenshots</a> ·
  <a href="#dokumentation">Dokumentation</a> ·
  <a href="CREDITS.md">Danksagung</a>
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/Version-1.1.0-0f172a?style=for-the-badge">
  <img alt="GLPI" src="https://img.shields.io/badge/GLPI-10%20%2F%2011-1f6feb?style=for-the-badge">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge">
  <img alt="Lizenz" src="https://img.shields.io/badge/Lizenz-GPLv3%2B-c1121f?style=for-the-badge">
</p>

<p align="center">
  <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml">
    <img alt="CI" src="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml/badge.svg">
  </a>
</p>

> School Manager ist ein inoffizielles GLPI-Plugin. Es wird nicht vom offiziellen GLPI-Projektteam gewartet.

<a id="uebersicht"></a>

## Übersicht

School Manager ergänzt GLPI um eine schlanke, zielgruppengerechte Oberfläche für Schulen, Kollegien und Bildungsträger. GLPI dient weiterhin als stabiles Fundament für ITSM und Inventarisierung, während School Manager Lehrkräften, IT-Beauftragten und Technikern benutzerfreundliche Werkzeuge bereitstellt.

## Hauptfunktionen

| Bereich | Beschreibung |
|---|---|
| Geführte Störungsmeldung | Schritt-für-Schritt-Ticket-Erstellung für Lehrkräfte mit Auswahl von Kategorie, Raum, Dringlichkeit und betroffener Hardware. |
| IT-Dashboard | Zentrale Arbeitsumgebung für IT-Verantwortliche zur Bearbeitung, Zuweisung und Dokumentation von Anfragen. |
| Lagerbestand & Verbrauchsmaterial | Verwaltung von Tonern, Kabeln, Ersatzteilen, Warnungen bei Mindestbeständen und direkte Buchung auf Tickets. |
| Räume & interaktive Pläne | Gebäude, Stockwerke, Klassenzimmer, interaktive Raumpläne und automatische Verknüpfung mit GLPI-Standorten. |
| Asset-Verwaltung | Übersichtliche Verwaltung von Computern, Bildschirmen, Beamern, Peripheriegeräten und Netzwerktechnik. |
| Einstellungs-Center | Verwaltung von Branding, Logo, aktiven Modulen, Sprachen und Gebäudestrukturen direkt in GLPI. |

<a id="sprachen"></a>

## Sprachen & Internationalisierung (i18n)

GLPI School Manager bietet vollständige native Mehrsprachigkeit mit **Englisch (`en_GB`)** als primärer Basissprache und Vorlagen-Standard:

| Sprache | Code | Status | Abdeckung |
|---|---|---|---|
| 🇬🇧 **English** | `en_GB` | Nativ (Standardbasis & Vorlage) | 100% |
| 🇪🇸 **Español** | `es_ES` | Nativ | 100% |
| 🇫🇷 **Français** | `fr_FR` | Nativ | 100% |
| 🇩🇪 **Deutsch** | `de_DE` | Nativ | 100% |
| 🇮🇹 **Italiano** | `it_IT` | Nativ | 100% |

### Integriertes Sprachen-Center & visueller Editor in GLPI

Kein Programmieren oder Terminalzugriff erforderlich! Unter **GLPI > Werkzeuge > School Manager > Einstellungen > 🌐 Sprachen**:
- **Automatische Spracherkennung**: Erkennt die Sprache der aktuellen GLPI-Benutzersitzung und schaltet nahtlos um (mit sicherem Fallback auf Englisch).
- **Visueller In-Browser-Editor**: Texte und Bezeichnungen direkt im Browser bearbeiten, mit Echtzeit-Suche filtern und sofort speichern.
- **Offizielle Vorlage herunterladen (`template.php`)**: Enthält alle 183 Schlüssel auf Englisch mit optimiertem Prompt für die Übersetzung mittels KI (ChatGPT, Claude, Gemini).
- **Eigene Sprachdateien hochladen**: Laden Sie angepasste `.php`- oder `.json`-Dateien hoch (z.B. `pt_PT`, `ca_ES`, `nl_NL`). Das Plugin bindet neue Sprachen automatisch in die Auswahlliste ein.

<a id="voraussetzungen"></a>

## Voraussetzungen

| Komponente | Empfohlen |
|---|---|
| GLPI | 10.x oder 11.x |
| PHP | 8.x |
| Webserver | Apache empfohlen |
| Datenbank | Identisch mit der GLPI-Datenbank |
| Betriebssystem | Linux empfohlen |
| Browser | Aktuelle Versionen von Chrome, Firefox, Edge oder Safari |
| Berechtigungen | GLPI-Administratorrechte für Installation und Konfiguration |

Der Plugin-Ordner muss exakt wie folgt heißen:

```text
schoolmanager
```

Vollständiger Pfad auf dem Server:

```text
/var/www/glpi/plugins/schoolmanager
```

<a id="installation"></a>

## Installation und Aktualisierung

Führen Sie folgenden Befehl auf dem GLPI-Server aus:

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/pabloburgos/glpi-schoolmanager-plugin/main/scripts/install-or-update.sh)"
```

Leeren Sie anschließend den GLPI-Cache und starten Sie Apache neu:

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Laden Sie Ihren Browser mit `Strg + F5` neu.

<a id="screenshots"></a>

## Screenshots

### Dashboard
<img src="docs/assets/screenshots/01-dashboard.png" alt="Dashboard" width="100%">

### Einstellungen
<img src="docs/assets/screenshots/02-configuration.png" alt="Konfiguration" width="100%">

### Geführte Tickets
<img src="docs/assets/screenshots/03-tickets.png" alt="Tickets" width="100%">

### Lagerbestand
<img src="docs/assets/screenshots/04-stock.png" alt="Lager" width="100%">

### Räume
<img src="docs/assets/screenshots/05-classrooms.png" alt="Räume" width="100%">

### Raumpläne
<img src="docs/assets/screenshots/06-maps.png" alt="Pläne" width="100%">

<a id="dokumentation"></a>

## Dokumentation

| Dokument | Beschreibung |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installations- und Updateanleitung. |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Konfigurationshandbuch. |
| [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md) | Kompatibilitätshinweise (GLPI, PHP). |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Leitfaden für Mitwirkende und Übersetzungen. |
| [SECURITY.md](SECURITY.md) | Sicherheitsrichtlinie. |
| [CREDITS.md](CREDITS.md) | Autoren und Danksagung. |
| [CHANGELOG.md](CHANGELOG.md) | Versionsverlauf. |

## Autoren

- **Pablo Burgos** (<pabloburgostena@faltax.app>)
- **Alejandro Galán**

## Lizenz

School Manager steht unter der GNU General Public License v3.0 oder höher.

SPDX-License-Identifier: `GPL-3.0-or-later`

Siehe [LICENSE](LICENSE).

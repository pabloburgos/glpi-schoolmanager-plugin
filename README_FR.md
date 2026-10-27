<p align="center">
  <img src="docs/assets/banner.png" alt="Bannière School Manager" width="100%">
</p>

<h1 align="center">School Manager pour GLPI</h1>

<p align="center">
  Plugin GLPI dédié aux établissements scolaires : gestion guidée des incidents, stock informatique, plans de salles, inventaire simplifié et suivi technique.
</p>

<p align="center">
  <a href="README.md">English</a> ·
  <a href="README_ES.md">Español</a> ·
  <b>Français</b> ·
  <a href="README_DE.md">Deutsch</a> ·
  <a href="README_IT.md">Italiano</a> ·
  <a href="#apercu">Aperçu</a> ·
  <a href="#langues">Langues</a> ·
  <a href="#prerequis">Prérequis</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#captures">Captures</a> ·
  <a href="#documentation">Documentation</a> ·
  <a href="CREDITS.md">Crédits</a>
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.1.0-0f172a?style=for-the-badge">
  <img alt="GLPI" src="https://img.shields.io/badge/GLPI-10%20%2F%2011-1f6feb?style=for-the-badge">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge">
  <img alt="Licence" src="https://img.shields.io/badge/licence-GPLv3%2B-c1121f?style=for-the-badge">
</p>

<p align="center">
  <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml">
    <img alt="CI" src="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml/badge.svg">
  </a>
</p>

> School Manager est un plugin non officiel pour GLPI. Il n'est pas maintenu par l'équipe officielle de GLPI.

<a id="apercu"></a>

## Aperçu

School Manager ajoute une interface simplifiée et intuitive au-dessus de GLPI pour les écoles, collèges, lycées et centres de formation. GLPI reste le moteur central de gestion de parc et ITSM, tandis que School Manager fournit des écrans adaptés aux enseignants, aux techniciens et aux référents numériques.

## Fonctionnalités principales

| Domaine | Description |
|---|---|
| Création guidée d'incidents | Signalement pas-à-pas pour les enseignants avec sélection de catégorie, de salle, d'urgence et d'équipement concerné. |
| Tableau de bord TIC | Espace de travail technique pour le suivi, l'attribution et la résolution des demandes. |
| Gestion des stocks | Consommables, toners/cartouches, mouvements de stock, alertes de seuil bas et pièces liées aux tickets. |
| Salles et plans interactifs | Gestion des bâtiments, étages, salles de classe, plans interactifs et liaisons automatiques avec les lieux GLPI. |
| Gestion simplifiée du parc | Vues claires pour ordinateurs, écrans, vidéoprojecteurs, imprimantes et équipements réseau. |
| Centre de configuration | Personnalisation du logo, des modules actifs, des langues et de la structure du centre depuis GLPI. |

<a id="langues"></a>

## Langues et Internationalisation (i18n)

GLPI School Manager intègre un support multilingue complet avec **l'anglais (`en_GB`)** comme langue source principale et standard de référence :

| Langue | Code | Statut | Couverture |
|---|---|---|---|
| 🇬🇧 **English** | `en_GB` | Natif (Base standard & modèle) | 100% |
| 🇪🇸 **Español** | `es_ES` | Natif | 100% |
| 🇫🇷 **Français** | `fr_FR` | Natif | 100% |
| 🇩🇪 **Deutsch** | `de_DE` | Natif | 100% |
| 🇮🇹 **Italiano** | `it_IT` | Natif | 100% |

### Centre de langues et éditeur visuel intégré dans GLPI

Aucun accès au serveur ou modification de code n'est requis ! Depuis **GLPI > Outils > School Manager > Configuration > 🌐 Langues** :
- **Détection automatique** : S'adapte instantanément à la langue de votre session GLPI, avec repli transparent sur l'anglais.
- **Éditeur visuel dans le navigateur** : Modifiez n'importe quel texte ou expression directement à l'écran grâce à un filtre de recherche en temps réel et un enregistrement immédiat.
- **Téléchargement du modèle officiel (`template.php`)** : Téléchargez le modèle en anglais de 183 clés avec une consigne optimisée pour la traduction automatique par IA (ChatGPT, Claude, Gemini).
- **Importation de fichiers de langue** : Importez vos fichiers `.php` ou `.json` personnalisés (ex. `pt_PT`, `ca_ES`, `nl_NL`). Le plugin les intègre automatiquement à la liste déroulante des langues.

<a id="prerequis"></a>

## Prérequis

| Composant | Recommandé |
|---|---|
| GLPI | 10.x ou 11.x |
| PHP | 8.x |
| Serveur Web | Apache recommandé |
| Base de données | Même base que celle de GLPI |
| Système d'exploitation | Linux recommandé |
| Navigateur | Chrome, Firefox, Edge ou Safari récents |
| Droits | Accès administrateur GLPI pour l'installation |

Le répertoire du plugin doit être obligatoirement nommé :

```text
schoolmanager
```

Chemin final sur le serveur :

```text
/var/www/glpi/plugins/schoolmanager
```

<a id="installation"></a>

## Installation et mise à jour

Exécutez cette commande sur le serveur GLPI :

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/pabloburgos/glpi-schoolmanager-plugin/main/scripts/install-or-update.sh)"
```

Videz ensuite le cache GLPI et rechargez Apache :

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Rafraîchissez votre navigateur avec `Ctrl + F5`.

<a id="captures"></a>

## Captures d'écran

### Tableau de bord
<img src="docs/assets/screenshots/01-dashboard.png" alt="Tableau de bord" width="100%">

### Configuration
<img src="docs/assets/screenshots/02-configuration.png" alt="Configuration" width="100%">

### Création guidée d'incidents
<img src="docs/assets/screenshots/03-tickets.png" alt="Incidents guidés" width="100%">

### Gestion des stocks
<img src="docs/assets/screenshots/04-stock.png" alt="Stocks" width="100%">

### Salles de classe
<img src="docs/assets/screenshots/05-classrooms.png" alt="Salles" width="100%">

### Plans
<img src="docs/assets/screenshots/06-maps.png" alt="Plans" width="100%">

<a id="documentation"></a>

## Documentation

| Document | Description |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Guide d'installation et de mise à jour. |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Guide de configuration du plugin. |
| [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md) | Notes de compatibilité GLPI & PHP. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Guide de contribution et traductions. |
| [SECURITY.md](SECURITY.md) | Politique de sécurité. |
| [CREDITS.md](CREDITS.md) | Auteurs et remerciements. |
| [CHANGELOG.md](CHANGELOG.md) | Historique des versions. |

## Auteurs

- **Pablo Burgos** (<pabloburgostena@faltax.app>)
- **Alejandro Galán**

## Licence

School Manager est distribué sous la licence GNU General Public License v3.0 ou ultérieure.

SPDX-License-Identifier: `GPL-3.0-or-later`

Consultez [LICENSE](LICENSE).

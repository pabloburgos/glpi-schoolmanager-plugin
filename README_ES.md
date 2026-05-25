<p align="center">
  <img src="docs/assets/banner.png" alt="Banner de School Manager" width="100%">
</p>

<h1 align="center">School Manager para GLPI</h1>

<p align="center">
  Plugin de GLPI orientado a centros educativos para incidencias guiadas, stock TIC, planos, activos y trabajo técnico.
</p>

<p align="center">
  <a href="README.md">English</a> ·
  <a href="#descripcion">Descripción</a> ·
  <a href="#requisitos">Requisitos</a> ·
  <a href="#instalacion">Instalación</a> ·
  <a href="#capturas">Capturas</a> ·
  <a href="#documentacion">Documentación</a> ·
  <a href="CREDITS.md">Créditos</a>
</p>

<p align="center">
  <img alt="Versión" src="https://img.shields.io/badge/versión-1.0.0-0f172a?style=for-the-badge">
  <img alt="GLPI" src="https://img.shields.io/badge/GLPI-10%20%2F%2011-1f6feb?style=for-the-badge">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge">
  <img alt="Licencia" src="https://img.shields.io/badge/licencia-GPLv3%2B-c1121f?style=for-the-badge">
</p>

<p align="center">
  <a href="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml">
    <img alt="CI" src="https://github.com/pabloburgos/glpi-schoolmanager-plugin/actions/workflows/ci.yml/badge.svg">
  </a>
</p>

> School Manager es un plugin no oficial de GLPI. No está mantenido por el proyecto oficial de GLPI.

<a id="descripcion"></a>

## Descripción

School Manager añade una capa de gestión más clara sobre GLPI para colegios e institutos. GLPI sigue siendo la plataforma base de ITSM e inventario, pero el plugin ofrece páginas más sencillas para profesorado, técnicos TIC y administradores del centro.

## Funciones principales

| Área | Descripción |
|---|---|
| Incidencias guiadas | Creación simplificada de tickets con categoría, aula, prioridad y activo afectado. |
| Panel TIC | Espacio de trabajo técnico para incidencias abiertas, asignación, seguimiento y resolución. |
| Control de stock | Consumibles, cartuchos, movimientos, avisos de stock bajo y uso vinculado a tickets. |
| Aulas y planos | Edificios, plantas, aulas, recursos de mapas y enlaces directos a ubicaciones GLPI. |
| Gestión de activos | Vistas simplificadas para ordenadores, monitores, impresoras, periféricos y red. |
| Configuración | Marca, logo, módulos, idioma, edificios, aulas y planos desde GLPI. |

<a id="requisitos"></a>

## Requisitos

| Componente | Soportado / recomendado |
|---|---|
| GLPI | 10.x u 11.x |
| PHP | 8.x |
| Servidor web | Apache recomendado |
| Base de datos | La misma pila de base de datos usada por GLPI |
| Sistema operativo | Servidor Linux recomendado |
| Navegador | Chrome, Edge, Firefox o Safari actual |
| Permisos | Acceso administrador de GLPI para instalación y configuración |

La carpeta del plugin debe llamarse exactamente:

```text
schoolmanager
```

Ruta final:

```text
/var/www/glpi/plugins/schoolmanager
```

<a id="instalacion"></a>

## Instalación y actualización

Ejecuta este comando en el servidor GLPI:

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/pabloburgos/glpi-schoolmanager-plugin/main/scripts/install-or-update.sh)"
```

Después limpia caché y reinicia Apache:

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

Recarga el navegador con `Ctrl + F5`.

## Estructura del repositorio

```text
glpi-schoolmanager-plugin/
├── schoolmanager/          # Carpeta real del plugin GLPI
├── public_maps/            # Recursos públicos opcionales para mapas
├── docs/                   # Documentación, banner y capturas
├── scripts/                # Scripts de instalación y actualización
├── .github/                # Workflows y plantillas de GitHub
├── README.md               # README principal en inglés
├── README_ES.md            # README en español
├── AUTHORS.md              # Autores
├── CREDITS.md              # Créditos y agradecimientos
├── CHANGELOG.md            # Historial de cambios
├── SECURITY.md             # Política de seguridad
└── LICENSE                 # GPL-3.0-or-later
```

<a id="capturas"></a>

## Capturas

### Panel principal

Página principal del plugin.

<img src="docs/assets/screenshots/01-dashboard.png" alt="Panel principal" width="100%">

### Configuración

Ajustes del plugin, marca y módulos.

<img src="docs/assets/screenshots/02-configuration.png" alt="Configuración" width="100%">

### Incidencias guiadas

Flujo de creación de tickets para profesorado y personal.

<img src="docs/assets/screenshots/03-tickets.png" alt="Incidencias guiadas" width="100%">

### Control de stock

Consumibles, movimientos y acciones de inventario.

<img src="docs/assets/screenshots/04-stock.png" alt="Control de stock" width="100%">

### Aulas

Listado de aulas, ubicaciones GLPI y acciones rápidas.

<img src="docs/assets/screenshots/05-classrooms.png" alt="Aulas" width="100%">

### Planos

Navegación por planos y ubicaciones.

<img src="docs/assets/screenshots/06-maps.png" alt="Planos" width="100%">

<a id="documentacion"></a>

## Documentación

| Documento | Descripción |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Guía de instalación y actualización. |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Guía de configuración del plugin. |
| [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md) | Notas de compatibilidad. |
| [docs/PAGES.md](docs/PAGES.md) | Páginas PHP principales. |
| [docs/STRUCTURE.md](docs/STRUCTURE.md) | Estructura del repositorio. |
| [docs/LEGAL.md](docs/LEGAL.md) | Notas legales y de licencia. |
| [docs/GITHUB_SETUP.md](docs/GITHUB_SETUP.md) | Notas de configuración en GitHub. |
| [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) | Checklist de release. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Guía de contribución. |
| [SECURITY.md](SECURITY.md) | Política de seguridad. |
| [SUPPORT.md](SUPPORT.md) | Guía de soporte. |
| [CREDITS.md](CREDITS.md) | Créditos y agradecimientos. |
| [CHANGELOG.md](CHANGELOG.md) | Historial de cambios. |

## Licencia

School Manager se distribuye bajo GNU General Public License v3.0 or later.

SPDX-License-Identifier: `GPL-3.0-or-later`

Consulta [LICENSE](LICENSE).

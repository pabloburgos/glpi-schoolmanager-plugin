# Contributing

Thank you for your interest in contributing to **School Manager**!

## Ways to contribute

You can help with:

- 🐛 Bug reports
- 💡 Feature proposals
- 🌐 Translations & language packs
- 📖 Documentation improvements
- 🎨 UI/UX improvements
- 🧪 GLPI compatibility testing
- ♿ Accessibility improvements

## Contribution rules

- Keep the plugin folder name as `schoolmanager`.
- Do not hardcode school names or private internal data.
- Keep user-facing text translatable using `plugin_schoolmanager_tr('key', 'Fallback')`.
- Test changes in GLPI before opening a pull request.
- Preserve compatibility with **GLPI 10 / GLPI 11** where possible.
- If a change affects behaviour or setup, update the documentation too.

## Translation contributions

**English (`en_GB`)** is the primary base language for the plugin, its templates, and fallback cascade.

Supported official languages:
- 🇬🇧 **English (`en_GB`)** (Canonical base)
- 🇪🇸 **Español (`es_ES`)**
- 🇫🇷 **Français (`fr_FR`)**
- 🇩🇪 **Deutsch (`de_DE`)**
- 🇮🇹 **Italiano (`it_IT`)**
- 🇵🇹 **Português (`pt_PT`)**

### Adding a new language or updating existing translations

1. **Via the GLPI Settings UI**:
   - Go to **School Manager Settings > Languages (🌐)**.
   - You can edit any existing language directly in your browser.
   - Or click **Download template.php** to get the official English base file.
   - Upload your `.php` or `.json` file back into GLPI.

2. **Via Pull Request**:
   - Copy `schoolmanager/locales/template.php` to `schoolmanager/locales/{code}.php` (e.g. `ca_ES.php`).
   - Translate values while keeping keys intact. Set `_language_name` => `'Your Language'`.
   - You can use an AI model (ChatGPT, Claude, Gemini) with the prompt included in `template.php`.
   - Submit your PR with the new locale file.

## Pull request checklist

- [ ] I tested the plugin in GLPI.
- [ ] I cleared GLPI cache (`sudo -u www-data php bin/console cache:clear`).
- [ ] I checked translations (English, Spanish, French, German, Italian, Portuguese or new language).
- [ ] I did not include private data.
- [ ] I updated documentation if needed.

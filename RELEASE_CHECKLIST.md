# Release Checklist

Use this checklist when preparing a GitHub release.

## Before releasing

- [ ] Test the plugin in a real GLPI installation.
- [ ] Confirm the plugin folder is named `schoolmanager`.
- [ ] Clear GLPI cache and refresh the browser.
- [ ] Check the main dashboard.
- [ ] Check guided ticket creation.
- [ ] Check the IT dashboard.
- [ ] Check stock input, output and exact adjustment.
- [ ] Check classroom list and classroom detail pages.
- [ ] Check maps and direct GLPI location links.
- [ ] Check configuration save/load.
- [ ] Check Spanish texts.
- [ ] Check English texts.
- [ ] Confirm no private school data is included.
- [ ] Confirm no credentials, tokens or internal IPs are included.
- [ ] Confirm `LICENSE` is the only top-level license file.
- [ ] Run the GitHub Actions validation workflow.

## Packaging

- [ ] Build a clean ZIP from the repository root.
- [ ] Verify the ZIP contains `schoolmanager/`, `docs/`, `scripts/`, `.github/` and root documentation files.
- [ ] Verify the ZIP extracts correctly.
- [ ] Generate a SHA256 checksum.

## Release notes

Include:

- Main improvements.
- Compatibility notes.
- Upgrade notes.
- Known limitations.
- Installation/update command.

## After publishing

- [ ] Test the one-command installer from GitHub.
- [ ] Confirm the README renders correctly.
- [ ] Confirm GitHub only shows one license tab.
- [ ] Confirm screenshots load correctly.

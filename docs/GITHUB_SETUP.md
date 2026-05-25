# GitHub Setup

This guide explains the recommended repository setup for School Manager.

## Recommended repository files

| File or folder | Purpose |
|---|---|
| `README.md` | Main public presentation. |
| `README_ES.md` | Spanish presentation. |
| `LICENSE` | Canonical GPLv3+ license file. |
| `.github/ISSUE_TEMPLATE/` | Issue forms for bug reports, features and translations. |
| `.github/workflows/ci.yml` | Validation workflow. |
| `.github/workflows/package.yml` | Manual ZIP packaging workflow. |
| `.github/release.yml` | GitHub generated release notes categories. |
| `RELEASE_CHECKLIST.md` | Release preparation checklist. |

## Suggested repository settings

- Enable Issues.
- Enable Discussions only if you want community questions outside issues.
- Use `main` as the default branch.
- Protect `main` when the project grows.
- Require the CI workflow before merging pull requests once contributors start using the repo.

## Same-version documentation or polish updates

For small polish updates, documentation improvements or repository cleanup, keep the plugin version unchanged and publish a normal commit to `main`.

Suggested commit message:

```text
Polish GitHub repository and documentation
```

Do not create a new tag or GitHub Release unless you want users to treat it as a new packaged version.

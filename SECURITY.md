#  Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.0.0 | Yes |

## Reporting a vulnerability

If you find a security issue, please avoid publishing sensitive details publicly.

- For **non-sensitive** security concerns, open a GitHub issue.
- For **sensitive** issues, contact the maintainers privately if possible.

## Please include

```text
Plugin version:
GLPI version:
PHP version:
Server OS:
Web server:
Description:
Steps to reproduce:
Possible impact:
Suggested fix:
```

## Never publish

- Passwords
- API keys
- Session tokens
- Internal IPs
- Student data
- Teacher personal data
- Private tickets
- Database dumps
- Server configuration files containing secrets

## Security recommendations

- Use HTTPS in production.
- Keep GLPI updated.
- Keep PHP updated.
- Restrict plugin configuration access to trusted administrators.
- Review filesystem permissions after installation.
- Keep backups before updating.
- Do not expose the GLPI server directly without proper security controls.

## Disclaimer

School Manager is an unofficial GLPI plugin. Security responsibility also depends on the deployment environment, GLPI configuration and server administration.

#  Support

For help, bugs or suggestions, please use **GitHub Issues**.

## Before opening an issue

Check that:

- You are using the latest plugin package.
- The plugin folder is named `schoolmanager`.
- GLPI cache has been cleared.
- Apache or your web server has been restarted.
- Browser cache has been refreshed with `Ctrl + F5`.

## Useful commands

```bash
cd /var/www/glpi
sudo -u www-data php bin/console cache:clear
sudo systemctl restart apache2
```

## Include this information

```text
GLPI version:
Plugin version:
PHP version:
Web server:
Operating system:
Browser:
Plugin language:
Steps to reproduce:
Expected result:
Actual result:
Screenshots:
```

## Do not share

- Passwords
- Tokens
- Student data
- Teacher personal data
- Internal IPs
- Private tickets
- Database dumps

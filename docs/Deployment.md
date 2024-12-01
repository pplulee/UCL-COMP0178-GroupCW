# System Requirements
- PHP >= 8.2
- MySQL >= 8.0 or MariaDB >= 10.6

# Installation

Copy `config.example.php` to `config.php` and fill in the database credentials.

```bash
php composer.phar install
vendor/bin/phinx migrate
```

Then the website can be accessed by configuring a web server such as Apache or Nginx.
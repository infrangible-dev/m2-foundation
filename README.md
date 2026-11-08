# Infrangible Foundation

Base module for the Infrangible Magento 2 modules. It provides:

- the **Infrangible** menu in the admin and the **Infrangible Solutions** tab in the configuration, which the other
  Infrangible modules use for their own entries
- an admin page that lists all Infrangible modules available on [Packagist](https://packagist.org/packages/infrangible/)
  together with the installed version
- a cron job that adds an admin notification when a new Infrangible module or a new release is published
- a contact form in the admin that sends a message to Infrangible together with information about the installation
- two console commands to encrypt and decrypt values with the encryption key of the installation

## Requirements

- Magento Open Source or Adobe Commerce 2.4.6 or newer
- PHP 8.2 or newer
- Outgoing HTTPS connections to `packagist.org` and `repo.packagist.org` (only for the solutions page and the cron job)
- A working mail setup of the shop (only for the contact form)

## Installation

```bash
composer require infrangible/m2-foundation
bin/magento module:enable Infrangible_Foundation
bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

## Usage

### Infrangible Solutions page

**Infrangible > Infrangible Solutions** in the admin lists every Infrangible module with its latest version, the release
date and the installed version. Outdated modules are listed first, modules that are not installed last.

The list is cached and kept up to date by the cron job. Until the cron job has run for the first time, the page shows a
**Load from Packagist** button that loads the list immediately. Loading all modules takes about half a minute.

The installed version is read from Composer. For modules installed in `app/code`, it is read from the `composer.json`
in the module directory or the directory above it. Modules without a `composer.json` there are shown as not installed.

Access is controlled by the ACL resource `Infrangible_Foundation::infrangible_solutions`.

### Update notifications

The cron job `infrangible_check_package_update` runs at minute 30 of every hour in the `default` group. Each run:

1. loads the list of Infrangible modules from Packagist, adds new modules and removes deleted ones
2. checks the Packagist release feed for releases since the previous run
3. loads every new or released module and adds an admin notification
   (**System > Other Settings > Notifications**) when its release is newer than the cached one

The first run only fills the cache and does not add notifications. A module that cannot be loaded is skipped and logged.

The cache and the time of the last check are stored as the Magento flags `infrangible_foundation_available_packages`
and `infrangible_foundation_check_time`.

### Contact form

**Infrangible > Contact** sends a message to `publish@infrangible.dev`. The mail is sent with the mail setup of the
shop, so SMTP modules and other mail transports configured in the shop are used. The sender is the general contact of
the shop (**Stores > Configuration > General > Store Email Addresses**), replies go to the email address of the admin
user who wrote the message.

The following information about the installation is added to the message and shown below the form before sending:

- Magento edition, version and mode, PHP version
- installed Infrangible modules with their versions
- all websites, stores and store views with their URLs, language, time zone and currencies

If the message cannot be sent, it is kept in the form. If email communication is disabled
(**Stores > Configuration > Advanced > System > Mail Sending Settings**), the form shows an error instead of
discarding the mail silently.

Access is controlled by the ACL resource `Infrangible_Foundation::infrangible_contact`. The mail uses the email
template `infrangible_foundation_contact`, which can be customized in **Marketing > Email Templates**.

### Console commands

Encrypt a value with the encryption key of the installation, for example to put a password into `app/etc/env.php` or a
configuration value:

```bash
bin/magento encryption:encrypt --value "my secret"
```

Decrypt a value encrypted by Magento:

```bash
bin/magento encryption:decrypt --value "0:3:..."
```

The result is written to the output without any formatting. Both commands exit with an error if `--value` is missing.

## For module developers

Other Infrangible modules can add their entries below the shared admin elements. The menu entries of this module use
the sort orders 0 (Solutions) and 10 (Contact), so the entries of other modules start at 20.

| Element             | Identifier                         |
|---------------------|------------------------------------|
| Admin menu          | `Infrangible_Foundation::menu`     |
| ACL resource        | `Infrangible_Foundation::menu`     |
| Configuration tab   | `infrangible`                      |

## Development

The module comes with an [Anodosys](https://github.com/anodosys/app) configuration (`ads.json`) for a local Magento
installation with the module mounted at `app/code/Infrangible/Foundation`. Start it with:

```bash
ads rise
```

The shop is then available at `https://dev.infrangible.dev/`. Run the checks in the `cli` container:

```bash
# coding standard (Magento2 and PHP 8.2 compatibility)
cd /var/www/module && /var/www/magento/vendor/bin/phpcs

# static analysis (PHPStan level 9)
cd /var/www/magento && vendor/bin/phpstan analyse -c /var/www/module/phpstan.neon

# unit tests
cd /var/www/magento && vendor/bin/phpunit -c /var/www/module/phpunit.xml
```

PHPStan and PHPUnit have to be started in the Magento directory, because the `vendor` directory of the module is the
mounted `vendor` directory of Magento.

### Code coverage

The unit tests cover all lines of the module. To measure the coverage, load Xdebug in coverage mode for this one run;
the configuration of the container stays unchanged:

```bash
cd /var/www/magento
M=app/code/Infrangible/Foundation
php -d zend_extension=xdebug.so -d xdebug.mode=coverage vendor/bin/phpunit -c /var/www/module/phpunit.xml \
    --coverage-filter $M/Block --coverage-filter $M/Command --coverage-filter $M/Controller \
    --coverage-filter $M/Helper --coverage-filter $M/Model \
    --coverage-text --coverage-html /var/www/module/var/coverage
```

The HTML report is then in `var/coverage/index.html` of the module directory.

The filter has to point to `app/code/Infrangible/Foundation`, because Magento loads the module classes from there and
not from `/var/www/module/src`. With PHPUnit 9 (Magento 2.4.6), use `--whitelist` instead of `--coverage-filter`.

### Continuous integration and releases

Every push and pull request runs the checks above on GitHub Actions with Magento 2.4.6 (PHP 8.2), 2.4.7 (PHP 8.3),
2.4.8 (PHP 8.4) and 2.4.9 (PHP 8.5), using the shared workflow of
[infrangible-dev/workflows](https://github.com/infrangible-dev/workflows). Outside the development environment, the
PHPStan and PHPUnit configuration find Magento through the environment variable `MAGENTO_ROOT`.

To release a new version, raise `version` in `composer.json`, describe the changes in `CHANGELOG.md` and push to
`main`. When all checks pass, the workflow creates the tag and the GitHub release, and Packagist picks up the new
version. Tags are only created by the workflow.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Infrangible Foundation is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

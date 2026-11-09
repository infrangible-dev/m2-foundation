# Changelog

## 3.0.1

### Changed

- Dependencies only allow patch releases of the tested minor versions (`~X.Y.Z`): `fewedev/base` 2.4, `psr/log` 1.1,
  2.0 or 3.0, `symfony/console` 5.4, 6.4 or 7.4.
- PHPStan checks with the real Magento factories instead of the stubs of the Magento test framework.

## 3.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.2 or newer. Magento 2.2 to 2.4.5 and PHP 7.1 to 8.1 are no longer
  supported; use version 2.x for these.
- No longer requires `infrangible/m2-core`. Modules that use classes of `Infrangible\Core` have to require
  `infrangible/m2-core` themselves.
- No longer requires `educoder/pest`.
- The methods of `Infrangible\Foundation\Helper\Data` changed. Loading from Packagist moved to
  `Infrangible\Foundation\Model\Packagist`, the installed versions to `Infrangible\Foundation\Model\InstalledPackages`.

### Fixed

- `encryption:encrypt` and `encryption:decrypt` failed with a `TypeError` after printing the result. They now return
  an exit code, write the result without formatting and also accept the value `0`.
- The solutions page did not escape the data loaded from Packagist.
- The cron job stopped at the first module that could not be loaded from Packagist.
- New modules were only added to the list if they appeared in the Packagist release feed.

### Changed

- The solutions page no longer loads data from Packagist while it is opened. Until the cron job has run, it shows a
  button to load the list.
- Module data is loaded from the Packagist metadata repository (`repo.packagist.org`) with the Magento HTTP client.
- Installed versions are read from Composer, with a fallback to the `composer.json` of modules in `app/code`.
- Development versions such as `dev-master` count as up to date.
- The required Magento modules are declared in `composer.json` and `module.xml`.

### Added

- Contact form in the admin (**Infrangible > Contact**) that sends a message to Infrangible with information about the
  installation, using the mail setup of the shop.
- Translations for Germany and its neighboring countries as well as Italy, Spain, Portugal, Brazil, Sweden, Norway,
  Finland and Ukraine: German (de_DE, de_AT, de_CH, de_LU), French (fr_FR, fr_BE, fr_CH, fr_LU), Dutch (nl_NL,
  nl_BE), Italian (it_IT, it_CH), Spanish (es_ES), Portuguese (pt_PT, pt_BR), Czech (cs_CZ), Danish (da_DK), Polish
  (pl_PL), Swedish (sv_SE, sv_FI), Norwegian (nb_NO, nn_NO), Finnish (fi_FI) and Ukrainian (uk_UA).
- Unit tests, PHPCS (Magento2 coding standard) and PHPStan (level 9) configuration.
- GitHub Actions pipeline that runs the checks with Magento 2.4.6 to 2.4.9 and releases new versions when they pass.
- Documentation in the README.

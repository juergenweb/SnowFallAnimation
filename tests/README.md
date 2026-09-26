# Tests for SnowFallAnimation

The tests run without a ProcessWire installation or database. The used ProcessWire classes
are replaced by small stubs (`stubs/ProcessWireStubs.php`), and "today" is fixed to
2026-06-15 so the results do not depend on the current date.

## Requirements

- PHP 8.1 or higher and Composer (for the PHP tests)
- Node.js 18 or higher and npm (for the JavaScript tests)

## Installation

Run once inside this `tests` folder:

```
composer install
npm install
```

## Run the tests

Inside this `tests` folder:

```
vendor/bin/phpunit
npm test
```

On Windows use `vendor\bin\phpunit`.

If PHPUnit is installed in the module folder instead (`composer.json` and `vendor` next to
`SnowFallAnimation.module`), run it from the module folder with `vendor/bin/phpunit -c tests`.

## What is tested

| File | Content |
|------|---------|
| `Unit/DateLogicTest.php` | Date conversion, adding years (incl. 29.02.), active/inactive state |
| `Unit/JsConfigTest.php` | Config for the JavaScript: defaults, limits, min/max swap, color and z-index validation |
| `Unit/AddScriptTest.php` | Frontend output: when the script is added, position, no inline script, escaping (XSS) |
| `Unit/ValidateConfigTest.php` | Validation before saving: invalid dates, old dates are kept, limits, recurrence |
| `Unit/GenerateDatesTest.php` | Yearly recurrence via LazyCron |
| `Unit/ConfigInputfieldsTest.php` | Config form fields, status message, opening of the fieldset after an error |
| `js/snow.test.js` | `snow.js` and `snow.min.js`: config via data-config, XSS protection, density, interval |

The JavaScript tests run against `snow.js` and `snow.min.js`, so the minified file must be
rebuilt after every change of `snow.js`.

## Security

This folder is protected by a `.htaccess` file (Apache). On other web servers (e.g. Nginx)
deny the access to `site/modules/SnowFallAnimation/tests/` in the server configuration or
do not upload this folder to the live server. The folder is excluded from the release
archives via `.gitattributes`.

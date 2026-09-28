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

## Browser tests (Playwright)

`e2e/snowfall.spec.js` opens a real page of your website in Chromium (desktop and mobile) and checks:

- `snow.min.js` and `snowfall.min.css` load with HTTP 200 and there are no JavaScript errors
- snowflakes appear with the configured symbols and color, and they fall down
- the number of snowflakes never exceeds the configured density
- the container is fixed, uses the configured z-index and does not catch clicks
- the snowflakes do not block clicks on the page and do not cause a horizontal scrollbar

The snowfall must be active on the tested page (module config: "On"). Otherwise all tests are skipped.

Install once inside this `tests` folder:

```
npm install
npx playwright install chromium
```

Run (default URL is `http://webseite2.test/`, change it with the variable `SNOWFALL_URL`):

```
npm run test:e2e

set SNOWFALL_URL=http://my-site.test/ && npm run test:e2e      (Windows cmd)
$env:SNOWFALL_URL="http://my-site.test/"; npm run test:e2e    (PowerShell)
```

## Live tests with WireTests

In addition to the unit tests, `SnowFallAnimation.test.php` in the module folder tests the module
inside a real ProcessWire installation (real database, hooks, templates and web server).
WireTests is part of the ProcessWire core since 3.0.267. Install the module "Wire Tests" once
(Modules > Refresh > Wire Tests > Install), then run from the ProcessWire root directory:

```
php index.php test SnowFallAnimation
```

The file must stay in the module folder (not in `tests/`), because WireTests does not search
subfolders of site modules.

What is tested:

- Installation, version, required files and the four hooks
- Validation when saving the config (invalid dates, limits, min/max) incl. the database content
- Yearly recurrence (1 year and several years)
- Frontend output on the real home page, and no output when off, on admin pages or outside the date range
- Config form, status alert colors and opening of the fieldset after an error
- Web server rules: `.module`, test files and `tests/` must return 403 (skipped if the site is not
  reachable via HTTP from the command line)

The test changes the module config temporarily and restores the original config at the end,
also if a check fails. Nevertheless, run it on a development or staging copy first.

## Security

This folder is protected by a `.htaccess` file (Apache). On other web servers (e.g. Nginx)
deny the access to `site/modules/SnowFallAnimation/tests/` in the server configuration or
do not upload this folder to the live server. The folder is excluded from the release
archives via `.gitattributes`.

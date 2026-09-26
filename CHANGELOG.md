# Changelog
All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-09-26

### Fixed
- The z-index field was not shown in the module configuration (snowflake symbols field was added twice)
- Invalid dates were saved despite the validation error - the previous dates are now kept
- Snowfall was shown although only a future start date or a past end date was set
- Error fieldset was opened by the wrong condition (&& instead of ||, fieldset had no name)
- Recurrence validation used the previously saved value instead of the submitted one
- Yearly recurrence now also works after longer downtimes and for February 29
- Default values now match the documented defaults (density 50, duration 5-15 s)
- Config is passed to the script before it starts (first snowflakes used default values, z-index was ignored)
- Density setting is now reachable (creation interval is derived from density and duration)
- JavaScript is cached again (no more time() in the version parameter)

### Security
- Snowflake symbols are inserted as text (textContent) instead of HTML (prevents stored XSS)
- Config values are passed as escaped JSON (no broken or injected JavaScript because of quotes or special characters)
- Upper limits for density (1000), size (10 em) and duration (120 s) to protect the visitors' browsers

### Added
- Unit tests for PHP (PHPUnit) and JavaScript (Vitest) in the tests folder

### Changed
- Status message in the module configuration is shown as a coloured alert box (green: active, blue: scheduled, grey: off)
- License changed to MIT (LICENSE.md), matching the README
- Config is passed via a data-config attribute instead of an inline script (works with a strict Content-Security-Policy)
- Config values are read the ProcessWire way (no TypeErrors with strict_types)

## [1.0.0] - 2025-11-19

First version of the SnowFallAnimation module launched.

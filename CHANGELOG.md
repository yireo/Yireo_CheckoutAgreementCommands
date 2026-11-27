# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 27 November 2026
### Changed
- The `configure()` method of the console commands `checkout_agreement:create` and `checkout_agreement:list` now declares a `void` return type, matching the Symfony Console signature

## [2.0.0] - 25 November 2026
### Added
- Support for PHP 8.5
- New `--store_id` option for `checkout_agreement:create` (default `0`, all store views)
- `checkout_agreement:create` now prints the ID of the created agreement

### Changed
- BREAKING: `checkout_agreement:create` now defaults `--is_html` to `0` instead of `1` and creates agreements for all store views instead of only the default store view
- `checkout_agreement:create` validates its input: `is_active`, `is_html` and `mode` must be 0 or 1, `name` and `checkbox_text` may be at most 255 characters, and save errors are shown as clean error messages
- Module name corrected from `Yireo_AgreementCommands` to `Yireo_CheckoutAgreementCommands` (run `bin/magento setup:upgrade` after updating)

### Removed
- Support for PHP 8.1

### Fixed
- Terminal escape sequences and console formatter tags in agreement names could manipulate the `checkout_agreement:list` output
- Missing dependencies on Magento_CheckoutAgreements, Magento_Store, symfony/console and ext-pcre

## [1.0.0] - 10 November 2025
### Fixed
- Copy generic CI/CD files
- Add CHANGELOG
- Adding all files to git via Yireo Command

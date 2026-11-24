# Yireo_CheckoutAgreementCommands

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-checkout-agreement-commands)
<!-- badges.specs.end -->


> This Magento 2 module adds CLI commands to manage checkout agreements

### Installation
Install this package via composer:
```bash
composer require yireo/magento2-checkout-agreement-commands
```

Next, enable this module:
```bash
bin/magento module:enable Yireo_CheckoutAgreementCommands
```

### Usage
List all agreements:
```bash
bin/magento checkout_agreement:list
```

Create an agreement:
```bash
bin/magento checkout_agreement:create --name="Terms" --content="Our terms" --checkbox_text="I agree"
```

Options of `checkout_agreement:create`:
- `--is_active`: `1` (default) or `0`
- `--is_html`: `0` (default) or `1`. HTML content and checkbox texts are rendered unescaped in the checkout, so only use trusted HTML
- `--mode`: `1` (manual, default) or `0` (automatic)
- `--store_id`: the store ID, `0` (all store views) by default

Breaking change after 1.0.0: `--is_html` defaults to `0` (it was `1`), the options `--is_active`, `--is_html` and `--mode` require a value of `0` or `1`, and agreements are created for all store views (instead of only the default store view).

## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CheckoutAgreementCommands/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CheckoutAgreementCommands/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CheckoutAgreementCommands/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CheckoutAgreementCommands/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_CheckoutAgreementCommands/compile.yml?label=compile)
<!-- badges.test.end -->

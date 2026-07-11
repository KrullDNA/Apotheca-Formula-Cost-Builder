# Apotheca Formula Cost Builder

WordPress plugin (**Product Costings**) — a cosmetic product formula builder and costing
calculator. Adds a Formula Ingredients repeater and Cost Summary to the `products` CPT,
pulling ingredient data (pH, price/kg, MOQ, natural origin) from the `trade-names` CPT,
plus two Elementor widgets for the front end.

## Structure

```
product-costings/                  Plugin source
├── product-costings.php           Main plugin file (bootstrap, assets, admin menu)
├── includes/
│   ├── class-formula-functions.php    Formula Functions settings page (option-backed list)
│   ├── class-product-metaboxes.php    Formula Ingredients repeater + Cost Summary metaboxes
│   ├── class-ajax-handler.php         Trade Name search + meta lookup AJAX endpoints
│   ├── class-elementor-widget.php     Elementor widget registration
│   ├── widget-formula-table.php       Elementor widget: Formula Ingredients Table
│   └── widget-batch-costings.php      Elementor widget: Batch Costings
└── assets/
    ├── js/admin.js                    Repeater UI, autocomplete, live cost summary
    └── css/                           Admin + front-end widget styles
```

## Requirements

- WordPress with a `products` CPT and a `trade-names` CPT (created outside this plugin).
- Elementor (for the two front-end widgets; the admin builder works without it).

## Building a release zip

From the repository root:

```sh
zip -r Apotheca-product-costings-<version>.zip product-costings
```

Upload the zip via **Plugins → Add New → Upload Plugin**.

## Changelog

### 1.0.1
- Fixed broken redirect after saving Formula Functions (`post_type=product` → `products`).
- Fixed the Duplicate Row button losing unsaved input values (typed text, dropdowns, checkboxes).
- Admin Cost Summary now matches the Batch Costings widget: MOQ round-up per ingredient and a
  Waste % allowance (new field on the Cost Summary metabox, saved per product).
- Added `wp_unslash()` when saving formula rows so quotes no longer accumulate backslashes.
- Added capability checks (`edit_posts`) to the AJAX endpoints.

### 1.0.0
- Initial release.

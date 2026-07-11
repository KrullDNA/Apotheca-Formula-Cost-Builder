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
│   ├── class-trade-data.php           Shared Trade Name meta accessor (multi-key fallback)
│   ├── class-costing-calculator.php   Shared costing maths (widgets, dashboard, versions)
│   ├── class-formula-functions.php    Formula Functions settings page (option-backed list)
│   ├── class-product-metaboxes.php    Formula Ingredients repeater + Cost Summary metaboxes
│   ├── class-ajax-handler.php         Trade Name search + meta lookup AJAX endpoints
│   ├── class-trade-name-fields.php    Trade Name INCI composition, usage limits, Where Used
│   ├── class-inci.php                 INCI label declaration generator + allergen flagging
│   ├── class-batch-sheet.php          Printable batch manufacturing record
│   ├── class-margin-dashboard.php     Costings Dashboard (margins across all products)
│   ├── class-versions.php             Formula version snapshots, compare, restore
│   ├── class-elementor-widget.php     Elementor widget registration
│   ├── widget-formula-table.php       Elementor widget: Formula Ingredients Table
│   ├── widget-batch-costings.php      Elementor widget: Batch Costings
│   └── widget-inci-list.php           Elementor widget: INCI Ingredients List
└── assets/
    ├── js/admin.js                    Repeater UI, autocomplete, live cost summary,
    │                                  guardrails, insight panels, versions UI
    └── css/                           Admin + front-end widget styles
```

See **USER-GUIDE.md** for the full feature documentation.

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

### 1.1.1
- New **INCI Ingredients List** Elementor widget — front-end display of the
  auto-generated INCI declaration for product page templates, with inline/list
  format, uppercase option, allergen emphasis, and full styling controls.

### 1.1.0
- **INCI Label Declaration** — auto-generated from formula rows + per-Trade-Name INCI
  compositions, descending order with 1% threshold divider, EU fragrance allergen
  flagging, copy-ready output.
- **Printable Batch Sheet** — GMP-style manufacturing record at any batch size with
  target weights, lot number / actual weight columns, method, QC and sign-off.
- **Formulation guardrails** — live warnings for total ≠ 100%, usage-rate violations
  (min/max per Trade Name), missing preservative, and pH compatibility window vs
  target final pH.
- **Cost Drivers panel** — % of formula weight vs % of raw material cost per ingredient.
- **Batch Size Sweet Spot panel** — unit cost at 0.25×–5× batch size, exposing MOQ cliffs.
- **Costings Dashboard** — margins across all products with target-margin highlighting
  and stale-price flags.
- **Refresh Ingredient Data** — one-click re-sync of prices/pH/MOQ/natural origin from
  Trade Names, with stale-price badges on out-of-date rows.
- **Where Used** — reverse lookup on each Trade Name showing every product using it.
- **Formula Versions** — automatic snapshots on change (with notes), compare with cost
  delta, one-click restore.
- New Trade Name fields: INCI composition and usage min/max (Formulation Data metabox).
- Shared costing calculator — widgets, dashboard, and versions all use identical maths.

### 1.0.1
- Fixed broken redirect after saving Formula Functions (`post_type=product` → `products`).
- Fixed the Duplicate Row button losing unsaved input values (typed text, dropdowns, checkboxes).
- Admin Cost Summary now matches the Batch Costings widget: MOQ round-up per ingredient and a
  Waste % allowance (new field on the Cost Summary metabox, saved per product).
- Added `wp_unslash()` when saving formula rows so quotes no longer accumulate backslashes.
- Added capability checks (`edit_posts`) to the AJAX endpoints.

### 1.0.0
- Initial release.

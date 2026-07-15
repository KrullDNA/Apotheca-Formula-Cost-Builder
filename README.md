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

### Temporary migration helper

`pc-bulk-pricing-migrator/` is a standalone, one-time plugin. Install it (upload
`pc-bulk-pricing-migrator-1.0.0.zip`), open **Tools → Bulk Pricing Migrator**, review
the preview, and click **Apply** to create the first Bulk Pricing pack on each Trade
Name from its existing `tn_price_per_kg` and `tn_moq` fields (MOQ becomes the pack
size, or 1 kg if blank). Trade Names that already have bulk pricing are left untouched,
so it is safe to re-run. Delete the plugin once done — the pricing it writes is stored
on the Trade Names and read by the main plugin.

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

### 1.9.3
- **Fix: bulk pricing "Price" is the total pack price, not per-kg.** The column is now
  "Pack price (total)" and the "≈ Price / kg" column correctly shows `total ÷ pack
  size` (e.g. a 20 kg pack for 3750 → 187.50/kg, previously shown as 3750/kg). Costing
  uses the total pack price throughout. Import / migrator now store total pack price
  (per-kg × pack size).

### 1.9.2
- **Import initial pricing** button on the Costings Dashboard: seeds the first bulk
  pricing pack on every Trade Name that has a Price/kg but no bulk pricing yet, from its
  MOQ (pack size, default 1 kg) and Price/kg. Idempotent; makes the separate migrator
  plugin optional.
- **Drag-to-reorder** rows in the Bulk Pricing table (display only — costing sorts by
  pack size regardless).

### 1.9.1
- Added a live **Kg / batch** column to the Formula Ingredients table showing each
  ingredient's quantity needed for the current batch (with a footer total), alongside
  the fuller Batch Requirements table.

### 1.9.0
- **Batch Requirements** table in the Cost Summary: a live per-ingredient purchasing
  list showing kg needed, kg to buy (cheapest pack combination, with any spare stock
  noted), and line cost, with totals.

### 1.8.2
- Cost-tie rule reversed: when two pack combinations cost the same, the optimiser now
  buys the **larger** quantity (the extra is free usable stock for another product).
  Cost still takes priority — a cheaper option always wins.

### 1.8.1
- Bulk pricing optimiser breaks cost ties by quantity (superseded by 1.8.2, which
  prefers the larger quantity).

### 1.8.0
- Bulk pricing now finds the **cheapest combination of pack sizes** (packs may be
  mixed): 6 kg → 1 × 5 kg + 1 × 1 kg = $250; 25 kg → 1 × 20 kg + 1 × 5 kg = $800; a
  single large pack is used whenever it's cheapest. Solved with a GCD-reduced DP
  (min-cost cover) shared between PHP costing and the admin live calc.

### 1.7.1
- Bulk pricing tiers are now treated as **pack sizes bought in whole multiples**: an
  ingredient needing 2.2 kg on a 1 kg pack buys 3 × 1 kg = $150 (not 2.2 × price). The
  cheapest single pack size is chosen per ingredient. "Quantity from" column renamed to
  **Pack size**.

### 1.7.0
- **Cheapest-total bulk purchasing**: ingredient costing now picks the cheapest way to
  buy at least the kg needed, buying up to a cheaper price break when that beats buying
  less (e.g. 4.2 kg needed → buy 5 kg @ the 5 kg price when that's cheaper). The price
  tiers are the purchase increments; the **MOQ field no longer affects costing** (a
  material's smallest tier acts as its minimum purchase). Without tiers, cost is simply
  kg needed × Price/KG. Applies to the admin Cost Summary, Batch Costings widget,
  Costings Dashboard, and the Batch Size Sweet Spot (which now shows real tier savings
  as batches scale).

### 1.6.0
- **Bulk pricing now supports litre/volume pricing**: each price break has a Unit
  dropdown (Kg or L) and there's a Specific Gravity (kg/L) field that activates when a
  litre break is used. Litre tiers are converted to a per-kg basis automatically
  (`price/kg = price/L ÷ SG`, `qty(kg) = qty(L) × SG`), with a live "≈ Price/kg"
  column in the editor. Existing kg tiers are unaffected.

### 1.5.0
- **INCI % ranges (Min–Max) from SDS**: each INCI constituent now takes a Min/Max
  percentage of the raw material; the midpoint drives label ordering. Available in
  both the per-ingredient INCI breakdown panel and the Trade Name Formulation Data box.
- **Auto-normalisation to 100%**: each material's constituent midpoints are normalised
  to total 100% of the material, and the whole declaration is normalised to total
  100% — so the ingredients list always adds up correctly regardless of SDS ranges.

### 1.4.0
- **Bulk pricing (quantity breaks) on Trade Names**: define price-per-kg tiers
  (e.g. 1 kg = 50, 5 kg = 40, 20 kg = 30). Batch costing prices each ingredient's
  purchased quantity against the applicable tier, so scale-up figures (Batch Costings
  widget, Costings Dashboard, admin Cost Summary, and the Batch Size Sweet Spot panel)
  reflect real bulk pricing. Falls back to the single Price/KG when no tiers are set.
- **Anhydrous / self-preserving acknowledgement checkbox** on the Formula Ingredients
  box to suppress the "no preservative" reminder (saved per product).

### 1.3.0
- **Editable Name field on Formula Versions** — label any version (e.g. `v23094`,
  `Without Glycerine`) inline; saves automatically as you type, on blur, or on Enter.
  Backed by a `pc_version_rename` AJAX endpoint. Distinct from the auto-captured Note.

### 1.2.2
- **INCI synonym merging**: `Water`, `Aqua`, `Eau` and combined forms
  (`Aqua/Water/Eau`, `Aqua (Water)`) now total as a single `Aqua` line in the
  declaration, while distinct names like `Rosa Damascena Flower Water` are preserved.
  Filterable via `pc_inci_synonym_groups` (change the canonical term or add groups
  such as Parfum/Fragrance).

### 1.2.1
- INCI splitter no longer breaks single INCI names that contain a slash
  (`Acrylates/C10-30 Alkyl Acrylate Crosspolymer`), an internal comma
  (`1,2-Hexanediol`), or a parenthetical qualifier. It now splits only on true
  blend connectors: `(and)`, the word `and`, `&`, `;`, and a comma followed by a space.

### 1.2.0
- **Per-ingredient INCI breakdown** in the formula builder: an INCI button on each
  ingredient row expands editable sub-rows (INCI name + % of material) with a live
  "≈ % in formula" contribution and a 100% total check. Saves the exact split back to
  the raw material (Trade Name) so the label declaration orders correctly across every
  product using it. Backed by `pc_get_inci_composition` / `pc_save_inci_composition`
  AJAX endpoints.

### 1.1.3
- INCI blend detection now splits on `(and)`, the word `and`, and `&` (in addition
  to commas/semicolons/slashes), so multi-INCI raw materials break into their
  individual label names. Genuine parentheticals like `(Jojoba)` are preserved. The
  same splitting applies to single rows in the Formulation Data box.

### 1.1.2
- Central **currency symbol** setting (Costings Dashboard) used by the admin Cost
  Summary, dashboard, and as the default for the Elementor costing widgets.
- **Auto-detect existing INCI fields** on Trade Names (`inci`, `inci_name`, etc.) as
  a fallback for the INCI generator, with pre-fill in the Formulation Data metabox.
- **Delete button** on Formula Versions.

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

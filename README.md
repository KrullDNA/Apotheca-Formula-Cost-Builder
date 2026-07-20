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

### Importing existing pricing

To seed bulk pricing from your existing per-kg / MOQ fields, use the **Import initial
pricing** button on **Products → Costings Dashboard**. It creates a first per-kg
quantity break on every Trade Name that has a Price/kg but no bulk pricing yet (from
its `tn_price_per_kg` and `tn_moq` fields), skipping any that already have bulk pricing.

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

### 1.11.17
- **Mixed pricing now combines schemes.** When a material has both per-kg quantity
  breaks and pack prices, costing can buy the bulk at the per-kg rate and top up the
  remainder with the cheapest pack (or vice versa) instead of picking one whole scheme.
  Example (ALGAKTIV RetinART, 1 kg = $1480/kg per-kg break plus 0.017 kg = $70 packs):
  1.02 kg now costs **1 kg × $1480 + 1 × 0.017 kg pack = $1550**, not $2720 (two 0.5 kg
  packs + a top-up). Pure per-kg and pure pack materials are unaffected.

### 1.11.16
- **Packaging size can now be g or mL.** A unit dropdown sits next to Packaging Size.
  For **mL** (volume) fills, units per batch account for the product's density: the
  plugin estimates the finished-product **specific gravity** from the ingredients
  (mass-weighted harmonic mean of each ingredient's SG; a missing SG is assumed 1.0),
  so a lighter product yields more units — e.g. a 30 kg batch of an SG-0.963 product in
  1 mL units gives ~1038 per kg rather than 1000. Gram fills are unchanged. The estimated
  Product SG is shown in the Cost Summary, and applied to the Batch Size Sweet Spot,
  Batch Costings widget and Dashboard.

### 1.11.15
- **Fix: Costing & Pricing fields now save their own data.** The box previously wrote to
  the same plain meta keys as JetEngine/ACF (`batch_size`, …), so those plugins' save
  handlers overwrote the values and deleting the external field left the box blank. It
  now stores in dedicated `_pc_cost_*` meta keys that JetEngine/ACF can't touch. Existing
  values still pre-fill each field until you first save through the box (then the plugin
  owns it), and the calculator reads the plugin's keys first. Cleared fields stay cleared.

### 1.11.14
- The **Final pH** field now shows the formula's **pH compatibility window** inline next
  to its label (e.g. “· compatible 4.5–7.0”), updating live from the ingredients' pH
  ranges. It turns red if the entered Final pH falls outside the window (or if the
  ingredient ranges don't overlap), so you can pick a compatible pH at a glance.

### 1.11.13
- Costing & Pricing box laid out in grouped rows: **Batch Size + Final pH** · **Packaging
  Size + Packaging unit cost** · **Labour + Facility running cost + Miscellaneous costs** ·
  **Cost price / Wholesale / RRP multipliers**.
- Removed the **Packaging units per batch** field — units per batch are always calculated
  automatically from Batch Size ÷ Packaging Size (the calculator no longer treats a stored
  value as an override).

### 1.11.12
- Moved the **Method** WYSIWYG editor into the **Formula Ingredients** box (renamed
  **Formula Ingredients & Method**), shown beneath the ingredients table. Same `method`
  meta key and save behaviour — no data change.

### 1.11.11
- The **Method** field in the Costing & Pricing box is now a **WYSIWYG editor**
  (formatting, lists, links). Content is saved as sanitised HTML under the same
  `method` meta key and rendered on the batch sheet, so existing plain-text methods
  carry over unchanged.

### 1.11.10
- Removed the temporary `?pc_debug=1` diagnostic added in 1.11.8 (it did its job:
  confirming a formula row was linked to the intended trade name). No user-facing change.

### 1.11.9
- **New "Costing & Pricing" metabox** on the product edit screen — the plugin now owns
  the product costing inputs instead of relying on external custom fields
  (JetEngine/ACF): Batch Size, Packaging Size, Labour, Facility Running Costs,
  Miscellaneous Costs, Packaging unit cost, Packaging units per batch, Cost price /
  Wholesale / RRP multipliers, Final pH and Method. Values save under the same plain
  meta keys the calculator already reads (`batch_size`, `labour`, `unit_size`,
  `cost_price`, …), so existing data carries over — just delete the old external field
  group. The box's save runs at priority 99 so it wins over a legacy field plugin during
  the transition, and the live Cost Summary now reads these fields directly.
- `packaging_units_per_batch` is now honoured as an explicit override of Units per Batch
  (blank = auto-calculate from Batch Size ÷ Packaging Size), matching the live admin
  calculation.

### 1.11.8
- Diagnostic: logged-in admins can append `?pc_debug=1` to a page URL with the Formula
  Ingredients Table to dump each row's pricing inputs (linked trade name + ID, whether
  it exists, saved price/MOQ snapshot, specific gravity, effective MOQ, raw and resolved
  price tiers, and the resulting cheapest-purchase). Used to trace a row whose cost
  doesn't match its trade name's bulk pricing (e.g. a row linked to a duplicate/old
  trade name). Admin-only and off unless the query flag is present.

### 1.11.7
- Bulk Pricing metabox: new **≈ Kg** column showing each row's **Qty from** converted
  to kilograms (litres × Specific Gravity; kg rows show as-is). Updates live as you
  type, e.g. `1 L` at SG 0.896 shows `0.896 kg`. Shows *set SG* for a litre row until a
  Specific Gravity is entered.

### 1.11.6
- **Coverage tolerance fixes a rounding artefact in pack costing.** A purchase may
  now fall up to 0.5% short of the exact kg needed and still count as covering it,
  so unit-conversion / whole-gram rounding no longer forces a dearer near-exact
  combination. Example (Squalane, litre pricing): a batch needing 2.04 kg was
  buying 5×0.5 L = $420, when **2×1 L + 1×0.5 L** (same 2.5 L, and 1 L is cheaper
  per litre) is **$402** — now correctly chosen. The batch's waste allowance
  covers the sub-gram remainder. Applies to per-kg breaks and pack combinations.

### 1.11.5
- **Free-stock allowance.** Purchase costing no longer always picks the strict
  cheapest option: when a larger purchase costs only marginally more, buying it
  leaves usable spare stock for the next batch, so it now wins. New **Free-stock
  allowance %** setting on the Costings Dashboard (default **5%**) defines
  "marginally" — the largest quantity whose cost is within `cheapest × (1 +
  allowance)` is chosen. Example: a batch needing 1.02 kg of a material sold as a
  1 kg pack ($530), 0.1 kg pack ($85) and 0.017 L pack ($40) costs **$610**
  strictly cheapest (1 kg + 2×0.017 L), but at 5% prefers **$615** (1 kg + 0.1 L)
  for the extra usable stock. Set the allowance to **0** to always buy the strict
  cheapest. Applies across the Cost/Batch column, Cost Summary, Batch
  Requirements, Batch Sweet Spot, Batch Costings widget and Dashboard.

### 1.11.4
- **Per-kg quantity-break costing fix.** For materials priced with per-kg
  quantity breaks, purchases now round **up to whole multiples of the MOQ
  increment** (the smallest quantity break) before the rate is applied. A batch
  needing 1.53 kg of a material with a 1 kg break now costs 2 kg × rate (e.g.
  2 × $1160 = **$2320**), not 1.53 × rate. Buying up to a higher, cheaper break
  is still weighed. Applied everywhere costs are shown — the Cost/Batch column,
  the admin Cost Summary, Batch Requirements, Batch Sweet Spot, the Batch
  Costings widget and the Dashboard. (Pack pricing already bought whole packs;
  materials sold in several pack sizes still combine sizes for the cheapest,
  least-wasteful covering purchase.)

### 1.11.3
- Formula Ingredients Table widget: replaced the **Cost/Kg** column with **Cost/Batch**
  (each ingredient's cheapest-purchase cost for its kg-per-batch) and moved it to the
  end, after Kg per batch. Column style controls (key `cost`) carry over.

### 1.11.2
- Trade name links now take the column's Text Color (overriding any theme link colour),
  so they match the rest of the table text.

### 1.11.1
- Front-end Formula Ingredients Table: trade names are now links to their individual
  Trade Name pages, opening in a new tab. Links inherit the column's text colour.

### 1.11.0
- **Formula versions are now saved on demand**, not automatically on every save. Tick
  "Save this as a new formula version" (with an optional note) in the Formula
  Ingredients box to keep a version; quick edits no longer create clutter.

### 1.10.7
- Stricter %w/w validation: on leaving the field, the value must be a plain number or
  exactly "q.s." — anything else (including partial-number text like "12abc") is
  cleared. Server-side, non-numeric input was already coerced, so no bad data was
  stored.

### 1.10.6
- The **% w/w** column accepts `q.s.` (quantum satis) as well as numbers — the only
  non-numeric value allowed; other text is rejected. q.s. rows count as 0 in every
  calculation and show as "q.s." on the admin/front-end formula tables and the batch
  sheet.

### 1.10.5
- Admin Formula Ingredients table now reflects bulk pricing: the **MOQ** and **Price/KG**
  columns derive from the Trade Name's bulk pricing (smallest quantity and its per-kg
  rate) on render and on Refresh Ingredient Data / ingredient select, falling back to
  the `tn_moq` / `tn_price_per_kg` fields when a material has no bulk pricing.

### 1.10.4
- Cost Summary: clarified the Waste % note — ingredient quantities/costs use batch ×
  (1 + waste%), units use the batch size without waste, so wasted material is paid for
  and spread across the sellable units (raising the final unit cost).

### 1.10.3
- Front-end MOQ column shows the actual value instead of rounding to 2 dp (e.g. 0.017
  kg now displays as "0.017 kg", not "0.02 kg"); trailing zeros are stripped.

### 1.10.2
- Front-end Formula Ingredients Table: MOQ column widened slightly and set to not wrap,
  so values like "0.09 kg" stay on one line.

### 1.10.1
- The front-end Formula Ingredients Table **MOQ column now reflects the bulk pricing
  table** — it shows the smallest quantity across the material's price breaks/packs
  (the real minimum order), falling back to the old MOQ snapshot when a material has no
  bulk pricing.

### 1.10.0
- **Dual bulk-pricing styles per row: Price / kg OR Pack price.** Supports supplier
  lists quoted per kg at quantity ranges (e.g. 1–4 kg = 1053.08/kg, 5–9 = 956.92/kg) —
  costing buys the exact kg needed at the applicable rate, buying up to a cheaper break
  when worthwhile — as well as the existing fixed pack sizes. Fill only one price per
  row; a warning shows if both are entered. The optimiser evaluates both styles and
  picks the cheapest overall. Import/migrator now seed a per-kg break from the existing
  Price/kg + MOQ.

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

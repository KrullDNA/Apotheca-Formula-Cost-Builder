# Product Costings — User Guide (v1.2.1)

A formula builder, costing calculator, and formulation-insight toolkit for cosmetic
brands, built around two custom post types on your site:

- **Products** (`products`) — your finished products, each carrying a formula.
- **Trade Names** (`trade-names`) — your raw materials library, each carrying price,
  pH range, MOQ, natural origin, and (new in 1.1.0) INCI composition and usage limits.

---

## 1. Getting Started

### 1.1 Install / update

Upload `Apotheca-product-costings-<version>.zip` via **Plugins → Add New → Upload
Plugin** and activate. Updating over an older version is safe — all your saved
formulas, versions, and settings are kept.

### 1.2 The five-minute setup for the new features

1. Open a few of your most-used **Trade Names** and fill in the new
   **Formulation Data** box (INCI composition + usage limits — see §3).
2. Open a **Product**, check the **Waste %** in the Cost Summary box matches what
   you use on the front-end Batch Costings widget (default 2%).
3. Visit **Products → Costings Dashboard** and set your **Target margin %**.

Everything else works automatically from your existing data.

---

## 2. The Formula Builder (Products → edit a product)

### 2.1 Formula Ingredients table

Each row is one raw material in the formula:

| Column | What it does |
|---|---|
| ☰ | Drag handle — reorder rows within the table. |
| **To 100%** | Tick on exactly one row (usually water). Its %w/w is calculated automatically as `100 − (sum of all other rows)` and updates live as you edit. |
| **Phase** | Phase letter (A, B, C…). The front-end table groups and colour-codes by phase. |
| **% w/w** | The inclusion rate. The footer shows the running total — green at 100%, red otherwise. |
| **Trade Name** | Type 2+ characters to search your Trade Names library. Picking one auto-fills pH, Price/KG, MOQ and Nat. Origin from the material's record. |
| **Function** | Dropdown managed under **Products → Formula Functions**. Pre-selected if the Trade Name has a default function. |
| pH / Price/KG / MOQ / Nat. Origin | Read-only snapshots pulled from the Trade Name when you picked it. |

Row buttons: **⎘ duplicate** (copies everything, including unsaved edits) and
**🗑 remove**.

### 2.2 ↻ Refresh Ingredient Data *(new)*

Prices, pH, MOQ and natural origin are **snapshots** taken when you picked each
ingredient. When a supplier reprices a material, your saved formulas don't change by
themselves — instead:

- A red **!** badge appears next to any row whose saved price no longer matches the
  Trade Name's current price. Hover it to see the current price.
- Click **↻ Refresh Ingredient Data** to re-pull current data into every row. Changed
  prices flash yellow and a status message tells you how many changed.
- **Save the product** to store the refreshed values (until you save, nothing is
  committed).

### 2.3 Live formulation warnings *(new)*

A warning panel under the table checks your formula as you type:

- **Total ≠ 100%** — the batch won't be right; fix before manufacturing.
- **Usage-rate violations** — any row outside the min/max usage range set on its
  Trade Name is flagged and its row is tinted red (see §3.2).
- **No preservative** — no row has the Function "Preservative". Ignore for anhydrous
  or self-preserving formulas; otherwise, add one.
- **pH compatibility window** — the plugin intersects every ingredient's pH range and
  shows you the window in which *all* ingredients are stable (e.g. "4.5 – 6.0"). You
  get a warning if the ranges don't overlap at all, or if your product's target
  `final_ph` falls outside the window.

These are guardrails, not a safety assessment — your CPSR still governs.

### 2.4 INCI breakdown per ingredient — for a perfect label *(new in 1.2.0)*

Each ingredient row has an **INCI** button (next to Duplicate/Remove). Click it to
expand a sub-panel that pulls in that raw material's individual INCI names as editable
rows, so you can perfect the label declaration straight from the SDS:

- **INCI Name** and **% of material** — each constituent INCI and its percentage of
  the raw material (not of the whole formula). These should total **100%** — a live
  total tells you if they don't (green at 100%, red otherwise).
- **≈ % in formula** — the resulting contribution to the finished product
  (`row %w/w × % of material`), updated live. This is the number that drives label
  ordering.
- **+ Add INCI** / **×** — add or remove constituents (e.g. break a preservative
  blend into its parts).
- **Save to raw material** — writes the breakdown back to the **Trade Name**, because
  a raw material's INCI split is a property of the material itself (from its SDS), not
  of one product. **This corrects the INCI declaration for every product that uses
  this material**, so you only enter it once. Reload the product afterwards to refresh
  the INCI Label Declaration preview (§5).

The panel is seeded from whatever the plugin already knows — your structured
composition if you've set one, otherwise the auto-detected/­split INCI field (§3.1).
So the typical workflow is: open the breakdown, replace the even-split estimates with
the real SDS percentages, and Save. If the SDS gives a **range** for a constituent,
enter the nominal (typical) value — a single number is what produces a deterministic,
correctly-ordered label.

> Tip: you only need to do this for **blends** (materials with more than one INCI).
> Single-substance materials are already 100% one name and need no attention.

### 2.5 Version note *(new)*

Free-text field below the table (e.g. *"Increased glycerin to 3%, swapped
preservative"*). It's stored with the automatic version snapshot when you save —
see §6.

---

## 3. The Raw Materials Library (Trade Names → edit a trade name)

### 3.1 Formulation Data box *(new)*

**INCI Composition** — what this material contributes to a label declaration:

- Single-substance material (e.g. vegetable glycerin): one row — `Glycerin`, `100`%.
- Blend (e.g. a preservative system): one row per constituent with its percentage of
  the raw material, e.g. `Benzyl Alcohol` 70%, `Dehydroacetic Acid` 8%, `Aqua` 22%.
  Check your supplier's composition/SDS documentation for the split; if unknown, an
  even split is better than nothing but mark it for follow-up.

**Already have an INCI field on your Trade Names?** It's detected automatically
(supported keys include `inci`, `inci_name`, `inci-name`, `inci_list`, `tn_inci`,
with and without an underscore prefix). A single name is treated as 100%; blends are
split into their individual INCI names on genuine blend connectors — **`(and)`**, the
standalone word **`and`**, **`&`**, **`;`**, or a **comma followed by a space** — and
the percentage is divided evenly across the parts. It deliberately does **not** split
on characters that occur *within* single INCI names, so these stay intact:

- **Slashes** — `Acrylates/C10-30 Alkyl Acrylate Crosspolymer`, `PEG-8/SMDI Copolymer`.
- **Commas inside a name** (no following space) — `1,2-Hexanediol`,
  `2-Bromo-2-Nitropropane-1,3-Diol`.
- **Parenthetical qualifiers** — `Simmondsia Chinensis (Jojoba) Seed Oil`
  (only the literal `(and)` counts as a separator).

The detected data pre-fills the repeater when you open the Trade Name — for blends,
correct the even split to the real percentages and save, since the split affects label
ordering. Developers can add more meta keys via the `pc_inci_text_meta_keys` filter.

The same splitting applies if you paste a full blend string into a single row of the
Formulation Data box — it's expanded into its individual INCI names automatically.

You can edit the same composition without leaving the formula builder — see the
**INCI breakdown per ingredient** panel (§2.4), which reads and writes this exact
data.

**Usage Rate Limits** — the recommended usage range in a finished formula (% w/w),
from the supplier's documentation. Leave blank for no limit. The formula builder
warns live whenever this material is used outside the range.

### 3.2 Where Used box *(new)*

Lists every product whose formula contains this material, with its %w/w and kg per
batch, plus a total. Check it **before** discontinuing, re-sourcing, or accepting a
price increase on a material — every product listed will be affected, and the total
kg is your negotiating position on annual volume.

---

## 4. Cost Summary (on the product edit screen)

Live calculations that update as you edit the formula or the product's cost fields
(`batch_size`, `labour`, `facility_running_costs`, `misc_costs`,
`packaging_unit_cost`, `unit_size`, …):

| Line | Meaning |
|---|---|
| **Raw Material Cost per KG** | Σ (%w/w ÷ 100 × price/kg). The pure formula cost — what 1 kg of product costs in materials, ignoring purchasing effects. |
| **Ingredient Cost per Batch (MOQ purchase)** | What you actually pay: each ingredient's required kg (at batch size + waste %) rounded **up** to its MOQ multiple, × price. |
| **Units per Batch** | From `packaging_units_per_batch`, or `floor(batch size × 1000 ÷ unit size)`. |
| **Total Batch Cost** | Ingredients + labour + facility + misc + packaging. |
| **Cost per Unit** | Total batch cost ÷ units. |

**Waste %** (top of the box) is the manufacturing loss allowance added to the batch
size. It's saved per product. Set it to the same value as your front-end Batch
Costings widget so back end and front end always agree.

### 4.1 Cost Drivers *(new)*

For each ingredient, two bars: **blue = % of formula weight**, **red = % of raw
material cost**, sorted by cost. A long red bar over a short blue bar is your
reformulation target — that ingredient costs far more than its share of the formula.
(This panel uses pure formula cost, before MOQ purchasing effects, so it reflects the
formula itself rather than your purchasing pattern.)

### 4.2 Batch Size Sweet Spot *(new)*

Unit cost calculated at 0.25× to 5× your current batch size. Because ingredient
purchasing rounds up to MOQ multiples, unit cost is **not linear** — small batches
can carry huge MOQ overhead, and there are "cliffs" where one more MOQ unit of an
expensive material kicks in. The cheapest row is highlighted with a ★. Assumes
labour, facility and misc costs are fixed per batch — if yours scale with batch size,
read the small sizes optimistically.

---

## 5. INCI Label Declaration (on the product edit screen) *(new)*

Generated automatically from the saved formula + each Trade Name's INCI composition:

- Contributions are summed per INCI name across all ingredients (water from three
  different materials becomes one `Aqua` entry at the combined percentage).
- Sorted in **descending order**, with a divider at the **1% threshold** — below it,
  ingredients may legally be listed in any order (EU/UK rules).
- The 26 EU fragrance allergens are flagged with a red **allergen** badge.
  (Developers: extend the list via the `pc_fragrance_allergens` filter as
  EU 2023/1545 phases in.)
- A **copy-ready** comma-separated declaration sits in the textarea — click to
  select all.

If any ingredient's Trade Name has no INCI composition yet, a yellow notice names
them — the declaration is incomplete until they're filled in. The percentages shown
are for your reference only and are **not** part of the label. Always verify allergen
declarations against your CPSR (declare > 0.001% leave-on / > 0.01% rinse-off).

The box updates when the product is **saved** (it reads the saved formula).

To show the declaration on the **front end** of your site, use the **INCI Ingredients
List** Elementor widget — see §9.

---

## 6. Formula Versions (on the product edit screen) *(new)*

Every time you save the product with a **changed** formula, a version snapshot is
stored automatically (up to 25, oldest dropped first) with the date, user, your
version note, and the row data.

- **Compare** — expands a panel showing exactly what changed between that version and
  the current formula: ingredients added / removed / changed (old % → new %), plus
  the ingredient batch cost of each and the delta. Costs are computed with **current**
  prices for both sides, so the delta isolates the *formulation* change from price
  drift.
- **Restore** — replaces the current saved formula with that version and reloads the
  page. Your current formula is snapshotted first, so restore is always reversible.
- **🗑 Delete** — permanently removes that version snapshot (with confirmation).
  Deleting a version never affects the current formula.

---

## 7. Costings Dashboard (Products → Costings Dashboard) *(new)*

One table across your entire range: unit cost, my cost price, wholesale price and
margin, RRP and margin, % natural origin, and a **Stale** flag for any product whose
saved ingredient prices no longer match current Trade Name prices.

- **Margin** = (price − unit cost) ÷ price. Set your **Target margin %** at the top;
  anything below it is highlighted red.
- **Currency symbol** — also set at the top of this page. It's used across the admin
  Cost Summary, this dashboard, and as the default for new Elementor costing widgets
  (existing widgets keep their own per-widget symbol until you change them).
- Workflow after a supplier price rise: refresh prices on the affected products
  (§2.2), then scan this page for red cells — those are the products that need a
  price review or reformulation.
- Calculations use each product's saved formula, waste %, and pricing multipliers —
  the same maths as the front-end widgets.

---

## 8. Batch Sheet (side panel on the product edit screen) *(new)*

A print-ready batch manufacturing record:

1. Enter the **batch size in kg** — any size, from a 0.5 kg lab trial to full
   production (defaults to the product's batch size).
2. Optionally enter a **batch code** (or leave blank for a hand-written line).
3. Tick/untick **Add waste allowance** (uses the product's saved Waste %).
4. **Open Printable Batch Sheet** → a new tab with a Print button.

The sheet contains: target weight in grams per ingredient at your chosen size, blank
**Actual (g)**, **Lot No.** and **Added ✓** columns to fill in at the bench, the
product's **Method**, a **QC section** (target pH from `final_ph`, blanks for
measured pH, appearance, odour, viscosity, units filled), and **sign-off lines** —
your ISO 22716 traceability record for every batch.

---

## 9. Front-End Display (Elementor)

Unchanged from 1.0.x, but now computed by the same shared calculator as everything
above:

- **Formula Ingredients Table** — the formula as a styled table, phase-sorted and
  colour-coded, with per-column styling controls and a waste-adjusted "Kg per batch"
  column.
- **Batch Costings** — pick any of 17 metrics (batch cost, final unit cost,
  wholesale, RRP, % natural origin, …) with label overrides, currency symbol, and
  full styling controls.
- **INCI Ingredients List** *(new in 1.1.1)* — the auto-generated INCI declaration
  as a front-end "Ingredients" section for your product page template. Options:
  - **Heading** text (blank to hide), **inline** (comma-separated label style) or
    **bulleted list** format, and an **UPPERCASE** toggle.
  - **Emphasise fragrance allergens** — style the EU allergens in italic/bold and a
    custom colour.
  - Full typography and colour controls for heading, text, and allergens.
  - Shows nothing to visitors when INCI data is missing (set an optional Empty
    Message if you prefer); in the Elementor editor it shows a completeness notice
    listing any Trade Names still missing INCI data — visitors never see that notice.
  - Percentages are never shown on the front end — names only, in the correct order.

All three take an optional Product ID (blank = current product). Use it on a single
product template so each product shows its own data. The two costing widgets also
have a Waste % setting — keep it equal to the product's Waste % for identical
front/back numbers.

---

## 10. Reference

### 10.1 Where data lives

| Data | Location |
|---|---|
| Formula rows | `_pc_formula_rows` (product meta) |
| Waste % | `_pc_waste_percent` (product meta) |
| Formula versions | `_pc_formula_versions` (product meta, max 25) |
| INCI composition | `_pc_inci_composition` (trade name meta) |
| Usage limits | `_pc_usage_min` / `_pc_usage_max` (trade name meta) |
| Function list | `pc_formula_functions` (option) |
| Target margin | `pc_target_margin` (option) |
| Currency symbol | `pc_currency_symbol` (option, default `$`) |

Product cost fields (`batch_size`, `labour`, `unit_size`, `cost_price`, `wholesale`,
`rrp`, `final_ph`, `method`, …) are **read** from your existing CPT fields (plain,
underscore, or ACF keys), never written.

### 10.2 The costing model in one paragraph

Batch size is grossed up by Waste %. Each ingredient's required kg is rounded **up**
to its MOQ multiple and multiplied by price/kg → ingredient batch cost. Add labour,
facility, misc, and packaging (unit cost × units per batch) → total batch cost.
Divide by units per batch → unit cost. Multiply by your `cost_price`, `wholesale`
and `rrp` multipliers for prices (RRP is rounded up to a whole number).

### 10.3 Troubleshooting

- **Autocomplete finds nothing** — Trade Names must be *published*; search matches
  the title only.
- **pH/price fields stay empty** — the Trade Name record is missing that field, or
  it's under an unrecognised meta key (supported: plain, `_`-prefixed and common ACF
  patterns, e.g. `price_per_kg`, `tn_price_per_kg`).
- **INCI box says "Missing INCI data"** — add an INCI Composition to the named Trade
  Names, then re-save the product.
- **Dashboard shows "No formula / costing data"** — the product has no saved formula
  rows, or `batch_size`/`unit_size` are missing so a unit cost can't be computed.
- **Front and back numbers differ** — the widget's Waste % setting differs from the
  product's saved Waste %.

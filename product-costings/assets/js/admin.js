(function ($) {
    'use strict';

    var PC = {
        nextIndex: 0,

        init: function () {
            this.cacheElements();
            this.nextIndex = this.$body.find('.pc-row').length;
            this.initSortable();
            this.initTradeNameSelects();
            this.bindEvents();
            this.recalcTo100();
            this.recalcCostSummary();
            this.recalcWarnings();
        },

        cacheElements: function () {
            this.$wrap  = $('#pc-formula-wrap');
            this.$body  = $('#pc-formula-body');
            this.$total = $('#pc-total-ww');
        },

        /* ──────────────────────────────
         * Sortable (drag & drop)
         * ────────────────────────────── */
        initSortable: function () {
            var self = this;
            this.$body.sortable({
                handle: '.pc-drag-handle',
                axis: 'y',
                opacity: 0.65,
                items: '> tr.pc-row',
                placeholder: 'pc-sortable-placeholder',
                start: function () {
                    // Collapse any open INCI panels so they don't detach from their row.
                    self.collapseAllInci();
                },
                update: function () {
                    self.reindexRows();
                    self.recalcTo100();
                }
            });
        },

        /* ──────────────────────────────
         * Trade Name autocomplete search
         * ────────────────────────────── */
        initTradeNameSelects: function () {
            this.$body.find('.pc-field-trade-name').each(function () {
                PC.initSingleTradeSelect($(this));
            });
        },

        initSingleTradeSelect: function ($select) {
            if ($select.data('pc-init')) return;
            $select.data('pc-init', true);

            var $row = $select.closest('.pc-row');
            var $wrapper = $('<div class="pc-trade-search-wrap"></div>');
            var $input   = $('<input type="text" class="pc-trade-search" placeholder="Search trade names…">');
            var $list    = $('<ul class="pc-trade-results"></ul>');
            $wrapper.append($input).append($list);
            $select.after($wrapper);
            $select.hide();

            // Show current selected value.
            if ($select.val()) {
                $input.val($select.find('option:selected').text());
            }

            var searchTimer;
            $input.on('input', function () {
                clearTimeout(searchTimer);
                var q = $(this).val();
                if (q.length < 2) {
                    $list.empty().hide();
                    return;
                }
                searchTimer = setTimeout(function () {
                    $.ajax({
                        url: pcData.ajaxUrl,
                        data: { action: 'pc_search_trade_names', nonce: pcData.nonce, q: q },
                        success: function (res) {
                            $list.empty();
                            if (res.success && res.data.length) {
                                $.each(res.data, function (_, item) {
                                    $list.append(
                                        $('<li></li>').text(item.text).data('id', item.id)
                                    );
                                });
                                $list.show();
                            } else {
                                $list.append('<li class="pc-no-results">No results</li>').show();
                            }
                        }
                    });
                }, 300);
            });

            // Select a result — fetch meta data.
            $list.on('click', 'li:not(.pc-no-results)', function () {
                var id   = $(this).data('id');
                var text = $(this).text();
                $select.html('<option value="' + id + '" selected>' + $('<span>').text(text).html() + '</option>');
                $input.val(text);
                $list.empty().hide();

                // A different material means a different INCI breakdown — close any open panel.
                $row.next('.pc-inci-subrow').remove();
                $row.removeClass('pc-inci-open');
                $row.find('.pc-inci-toggle').removeClass('active');

                // Fetch pH, price_per_kg, moq from trade name.
                PC.fetchTradeMeta(id, $row);
            });

            $input.on('blur', function () {
                setTimeout(function () { $list.empty().hide(); }, 200);
            });

            $input.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $select.val('').html('<option value="">— Select —</option>');
                    $input.val('');
                    $row.find('.pc-field-ph').val('');
                    $row.find('.pc-field-price').val('');
                    $row.find('.pc-field-moq').val('');
                    $row.find('.pc-field-natural-origin').val('');
                    $list.empty().hide();
                }
            });
        },

        /**
         * Fetch meta from a Trade Name post and populate the row fields.
         */
        fetchTradeMeta: function (postId, $row, done) {
            $.ajax({
                url: pcData.ajaxUrl,
                data: { action: 'pc_get_trade_name_meta', nonce: pcData.nonce, post_id: postId },
                success: function (res) {
                    if (res.success && res.data) {
                        $row.find('.pc-field-ph').val(res.data.ph || '');
                        $row.find('.pc-field-price').val(res.data.price_per_kg || '');
                        $row.find('.pc-field-moq').val(res.data.moq || '');
                        $row.find('.pc-field-natural-origin').val(res.data.natural_origin || '');

                        // Usage limits for live guardrail warnings.
                        $row.attr('data-usage-min', res.data.usage_min || '');
                        $row.attr('data-usage-max', res.data.usage_max || '');

                        // Bulk pricing tiers for scale-up costing.
                        $row.attr('data-price-tiers', JSON.stringify(res.data.price_tiers || []));

                        // A fresh fetch is by definition not stale.
                        $row.find('.pc-stale-badge').remove();

                        // Pre-select function if trade name has one.
                        if (res.data.function1) {
                            var $fnSelect = $row.find('.pc-field-function');
                            if ($fnSelect.find('option[value="' + res.data.function1 + '"]').length) {
                                $fnSelect.val(res.data.function1);
                            }
                        }

                        PC.recalcCostSummary();
                        PC.recalcWarnings();
                    }
                    if (done) done(res);
                },
                error: function () {
                    if (done) done(null);
                }
            });
        },

        /* ──────────────────────────────
         * Events
         * ────────────────────────────── */
        bindEvents: function () {
            var self = this;

            // Add row.
            $('#pc-add-row').on('click', function () {
                self.addRow();
            });

            // Remove row.
            this.$wrap.on('click', '.pc-remove-row', function () {
                var $row = $(this).closest('.pc-row');
                $row.next('.pc-inci-subrow').remove();
                $row.remove();
                self.reindexRows();
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // Toggle the per-ingredient INCI breakdown panel.
            this.$wrap.on('click', '.pc-inci-toggle', function () {
                self.toggleInci($(this).closest('.pc-row'));
            });

            // INCI sub-row: edit %, add, remove, save.
            this.$wrap.on('input change', '.pc-inci-sub-min, .pc-inci-sub-max, .pc-inci-sub-name', function () {
                var $panel = $(this).closest('.pc-inci-panel');
                self.recalcInciTotals($panel, $panel.closest('.pc-inci-subrow').prev('.pc-row'));
            });
            this.$wrap.on('click', '.pc-inci-sub-add', function () {
                var $panel = $(this).closest('.pc-inci-panel');
                self.addInciSubRow($panel.find('.pc-inci-sub-body'), '', '');
                self.recalcInciTotals($panel, $panel.closest('.pc-inci-subrow').prev('.pc-row'));
            });
            this.$wrap.on('click', '.pc-inci-sub-remove', function () {
                var $panel = $(this).closest('.pc-inci-panel');
                var $row   = $panel.closest('.pc-inci-subrow').prev('.pc-row');
                $(this).closest('.pc-inci-sub-row').remove();
                self.recalcInciTotals($panel, $row);
            });
            this.$wrap.on('click', '.pc-inci-sub-save', function () {
                self.saveInci($(this));
            });

            // Duplicate row.
            this.$wrap.on('click', '.pc-duplicate-row', function () {
                var $row   = $(this).closest('.pc-row');

                // Collapse any open INCI panel first so it isn't half-cloned.
                $row.next('.pc-inci-subrow').remove();
                $row.removeClass('pc-inci-open');
                $row.find('.pc-inci-toggle').removeClass('active');

                var $clone = $row.clone();
                var newIdx = self.nextIndex++;

                // clone() copies attributes, not current input state — copy
                // live values (typed text, dropdowns, checkboxes) explicitly.
                var $srcFields = $row.find('input, select, textarea');
                var $dstFields = $clone.find('input, select, textarea');
                $srcFields.each(function (i) {
                    var $src = $(this);
                    var $dst = $dstFields.eq(i);
                    if ($src.is(':checkbox') || $src.is(':radio')) {
                        $dst.prop('checked', $src.prop('checked'));
                    } else {
                        $dst.val($src.val());
                    }
                });

                $clone.attr('data-index', newIdx);
                $clone.find('[name]').each(function () {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace(/pc_rows\[\d+\]/, 'pc_rows[' + newIdx + ']'));
                });

                // Re-init trade name search on clone.
                $clone.find('.pc-trade-search-wrap').remove();
                $clone.find('.pc-field-trade-name').show().data('pc-init', false);
                $row.after($clone);
                self.initSingleTradeSelect($clone.find('.pc-field-trade-name'));
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // "To 100%" checkbox — only one row can be checked at a time.
            this.$wrap.on('change', '.pc-field-to100', function () {
                var $checkbox = $(this);
                var $row = $checkbox.closest('.pc-row');

                if ($checkbox.is(':checked')) {
                    // Uncheck all other to-100% checkboxes.
                    self.$body.find('.pc-field-to100').not($checkbox).each(function () {
                        $(this).prop('checked', false);
                        var $otherRow = $(this).closest('.pc-row');
                        $otherRow.removeClass('pc-row-to100');
                        $otherRow.find('.pc-field-ww').removeAttr('readonly');
                        $otherRow.find('.pc-to100-badge').remove();
                    });

                    $row.addClass('pc-row-to100');
                    $row.find('.pc-field-ww').attr('readonly', true);
                    if (!$row.find('.pc-to100-badge').length) {
                        $row.find('.pc-field-ww').after('<span class="pc-to100-badge">to 100%</span>');
                    }
                } else {
                    $row.removeClass('pc-row-to100');
                    $row.find('.pc-field-ww').removeAttr('readonly');
                    $row.find('.pc-to100-badge').remove();
                }

                self.recalcTo100();
            });

            // Recalc on %w/w change.
            this.$wrap.on('input change', '.pc-field-ww', function () {
                self.recalcTo100();
                self.recalcCostSummary();
                self.recalcWarnings();
                self.updateInciContribution($(this).closest('.pc-row'));
            });

            // Validate %w/w when leaving the field: allow a plain number or
            // exactly "q.s." — anything else is cleared.
            this.$wrap.on('blur change focusout', '.pc-field-ww', function () {
                var $f = $(this);
                if ($f.prop('readonly')) { return; }
                var v = $.trim($f.val());
                if (v === '') { return; }

                if (/^q\.?\s*s\.?$/i.test(v)) {
                    // Quantum satis — normalise to "q.s.".
                    if (v !== 'q.s.') { $f.val('q.s.'); }
                } else if (isFinite(Number(v))) {
                    // A valid number (whole string must be numeric); keep it.
                    return;
                } else {
                    // Not a number and not q.s. — reject it.
                    $f.val('');
                }

                self.recalcTo100();
                self.recalcCostSummary();
                self.recalcWarnings();
            });

            // Re-check warnings when a Function is picked (preservative check).
            this.$wrap.on('change', '.pc-field-function', function () {
                self.recalcWarnings();
            });

            // Re-check warnings when the anhydrous / self-preserving box is toggled.
            $(document).on('change', '#pc-preservative-ack', function () {
                self.recalcWarnings();
            });

            // Re-check the pH window when the product's target pH changes.
            $(document).on('input change',
                '#acf-field_final_ph, input[name="final_ph"], [data-name="final_ph"] input',
                function () {
                    self.recalcWarnings();
                }
            );

            // Refresh all ingredient data from Trade Names.
            $('#pc-refresh-meta').on('click', function () {
                self.refreshAllRows();
            });

            // Formula version compare / restore.
            $(document).on('click', '.pc-version-compare', function () {
                var index   = $(this).data('index');
                var $detail = $('#pc-version-detail-' + index);
                var $cell   = $detail.find('.pc-version-detail-cell');

                if ($detail.is(':visible')) {
                    $detail.hide();
                    return;
                }

                $cell.html('<em>Loading…</em>');
                $detail.show();

                $.ajax({
                    url: pcData.ajaxUrl,
                    data: {
                        action: 'pc_version_compare',
                        nonce: pcData.nonce,
                        post_id: $('#post_ID').val(),
                        index: index
                    },
                    success: function (res) {
                        $cell.html(res.success ? res.data : '<em>Could not load comparison.</em>');
                    },
                    error: function () {
                        $cell.html('<em>Could not load comparison.</em>');
                    }
                });
            });

            $(document).on('click', '.pc-version-delete', function () {
                if (!window.confirm('Delete this formula version? This cannot be undone.')) {
                    return;
                }
                var $btn = $(this);
                $.ajax({
                    url: pcData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'pc_version_delete',
                        nonce: pcData.nonce,
                        post_id: $('#post_ID').val(),
                        index: $btn.data('index')
                    },
                    success: function (res) {
                        if (res.success) {
                            // Reload so the remaining versions renumber correctly.
                            window.location.reload();
                        } else {
                            window.alert('Delete failed: ' + (res.data || 'unknown error'));
                        }
                    },
                    error: function () {
                        window.alert('Delete failed: request error.');
                    }
                });
            });

            // Formula version rename (editable name, saves as you type / on blur).
            var versionNameTimers = {};
            $(document).on('input', '.pc-version-name', function () {
                var $input = $(this);
                var index  = $input.data('index');
                clearTimeout(versionNameTimers[index]);
                versionNameTimers[index] = setTimeout(function () {
                    self.saveVersionName($input);
                }, 600);
            });
            $(document).on('blur', '.pc-version-name', function () {
                var $input = $(this);
                clearTimeout(versionNameTimers[$input.data('index')]);
                self.saveVersionName($input);
            });
            $(document).on('keydown', '.pc-version-name', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });

            $(document).on('click', '.pc-version-restore', function () {
                if (!window.confirm('Restore this formula version? The current saved formula will be snapshotted first, then replaced. The page will reload.')) {
                    return;
                }
                $.ajax({
                    url: pcData.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'pc_version_restore',
                        nonce: pcData.nonce,
                        post_id: $('#post_ID').val(),
                        index: $(this).data('index')
                    },
                    success: function (res) {
                        if (res.success) {
                            window.location.reload();
                        } else {
                            window.alert('Restore failed: ' + (res.data || 'unknown error'));
                        }
                    },
                    error: function () {
                        window.alert('Restore failed: request error.');
                    }
                });
            });

            // Recalc cost summary when existing CPT meta fields change.
            // These are the field names from the existing Products CPT meta fields.
            $(document).on('input change',
                '#acf-field_batch_size, #acf-field_labour, #acf-field_facility_running_costs, ' +
                '#acf-field_misc_costs, #acf-field_packaging_unit_cost, #acf-field_packaging_units_per_batch, ' +
                '#acf-field_unit_size, ' +
                'input[name="batch_size"], input[name="labour"], input[name="facility_running_costs"], ' +
                'input[name="misc_costs"], input[name="packaging_unit_cost"], input[name="packaging_units_per_batch"], ' +
                'input[name="unit_size"], ' +
                // Also handle ACF field wrappers by data attribute.
                '[data-name="batch_size"] input, [data-name="labour"] input, [data-name="facility_running_costs"] input, ' +
                '[data-name="misc_costs"] input, [data-name="packaging_unit_cost"] input, [data-name="packaging_units_per_batch"] input, ' +
                '[data-name="unit_size"] input',
                function () {
                    self.recalcCostSummary();
                }
            );

            // Recalc when the Waste % field changes.
            $(document).on('input change', '#pc-waste-percent', function () {
                self.recalcCostSummary();
            });
        },

        /* ──────────────────────────────
         * Add new row
         * ────────────────────────────── */
        addRow: function () {
            var template = wp.template('pc-row');
            var html = template({ i: this.nextIndex });
            this.$body.append(html);

            var $newRow = this.$body.find('.pc-row').last();
            this.initSingleTradeSelect($newRow.find('.pc-field-trade-name'));

            this.nextIndex++;
            this.reindexRows();
        },

        /* ──────────────────────────────
         * Reindex rows after sort / remove
         * ────────────────────────────── */
        reindexRows: function () {
            this.$body.find('.pc-row').each(function (idx) {
                $(this).attr('data-index', idx);
                $(this).find('[name]').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/pc_rows\[\d+\]/, 'pc_rows[' + idx + ']'));
                    }
                });
            });
            this.nextIndex = this.$body.find('.pc-row').length;
        },

        /* ──────────────────────────────
         * Recalc "to 100%" dynamically
         * ────────────────────────────── */
        recalcTo100: function () {
            var $to100Row = this.$body.find('.pc-row-to100');
            if (!$to100Row.length) {
                this.updateTotal();
                return;
            }

            var sum = 0;
            this.$body.find('.pc-row').not('.pc-row-to100').each(function () {
                var val = parseFloat($(this).find('.pc-field-ww').val()) || 0;
                sum += val;
            });

            var to100val = Math.max(0, 100 - sum);
            to100val = Math.round(to100val * 10000) / 10000;
            $to100Row.find('.pc-field-ww').val(to100val);

            this.updateTotal();
            this.recalcCostSummary();
        },

        updateTotal: function () {
            var sum = 0;
            this.$body.find('.pc-row .pc-field-ww').each(function () {
                sum += parseFloat($(this).val()) || 0;
            });
            this.$total.html('<strong>' + sum.toFixed(2) + '</strong>');
        },

        /* ──────────────────────────────
         * Helper: get a value from an existing CPT meta field.
         * Tries multiple selector patterns (ACF, Metabox, plain).
         * ────────────────────────────── */
        getFieldValue: function (fieldName) {
            var val;

            // Try ACF field wrapper: [data-name="fieldName"] input
            val = $('[data-name="' + fieldName + '"] input').val();
            if (val) return parseFloat(val) || 0;

            // Try input[name="fieldName"]
            val = $('input[name="' + fieldName + '"]').val();
            if (val) return parseFloat(val) || 0;

            // Try ACF-style id: #acf-field_fieldName
            val = $('#acf-field_' + fieldName).val();
            if (val) return parseFloat(val) || 0;

            return 0;
        },

        /* ──────────────────────────────
         * Cost Summary Calculation
         *
         * Uses existing Products CPT meta fields:
         *   batch_size, labour, facility_running_costs, misc_costs,
         *   packaging_unit_cost, packaging_units_per_batch, unit_size
         * ────────────────────────────── */
        recalcCostSummary: function () {
            var currency = (window.pcData && pcData.currency) ? pcData.currency : '$';

            // Raw material cost per KG = sum of (percent_w_w / 100 * price_per_kg).
            var rawCostPerKg = 0;
            this.$body.find('.pc-row').each(function () {
                var ww    = parseFloat($(this).find('.pc-field-ww').val()) || 0;
                var price = parseFloat($(this).find('.pc-field-price').val()) || 0;
                rawCostPerKg += (ww / 100) * price;
            });

            // Read existing CPT meta fields.
            var batchSize         = this.getFieldValue('batch_size');
            var labour            = this.getFieldValue('labour');
            var facilityCosts     = this.getFieldValue('facility_running_costs');
            var miscCosts         = this.getFieldValue('misc_costs');
            var packagingUnitCost = this.getFieldValue('packaging_unit_cost');
            var unitsPerBatch     = this.getFieldValue('packaging_units_per_batch');
            var unitSize          = this.getFieldValue('unit_size'); // packaging size in grams

            // Waste allowance (matches the Batch Costings widget calculation).
            var wastePct = parseFloat($('#pc-waste-percent').val());
            if (isNaN(wastePct) || wastePct < 0) {
                wastePct = 2;
            }
            var batchSizeWithWaste = batchSize * (1 + wastePct / 100);

            // If units_per_batch not set, calculate from batch_size and unit_size.
            if (!unitsPerBatch && unitSize > 0 && batchSize > 0) {
                unitsPerBatch = Math.floor((batchSize * 1000) / unitSize);
            }

            // Ingredient purchase cost per batch: round each ingredient's
            // required kg up to the next MOQ multiple, matching the
            // Batch Costings widget's batch_cost calculation.
            var batchIngredientCost = 0;
            var self = this;
            this.$body.find('.pc-row').each(function () {
                var $r    = $(this);
                var ww    = parseFloat($r.find('.pc-field-ww').val()) || 0;
                var price = parseFloat($r.find('.pc-field-price').val()) || 0;

                var kgNeeded = batchSizeWithWaste > 0 ? (ww / 100) * batchSizeWithWaste : 0;
                if (kgNeeded <= 0) {
                    return;
                }

                batchIngredientCost += self.lineCost(self.readTiers($r), kgNeeded, price);
            });

            var totalBatchCost = batchIngredientCost + facilityCosts + labour + miscCosts + (packagingUnitCost * unitsPerBatch);
            var costPerUnit    = unitsPerBatch > 0 ? totalBatchCost / unitsPerBatch : 0;

            $('#pc-raw-cost-kg').text(rawCostPerKg > 0 ? currency + rawCostPerKg.toFixed(4) : '—');
            $('#pc-raw-cost-batch').text(batchIngredientCost > 0 ? currency + batchIngredientCost.toFixed(2) : '—');
            $('#pc-units-batch').text(unitsPerBatch > 0 ? unitsPerBatch : '—');
            $('#pc-batch-cost').text(totalBatchCost > 0 ? currency + totalBatchCost.toFixed(2) : '—');
            $('#pc-cost-unit').text(costPerUnit > 0 ? currency + costPerUnit.toFixed(4) : '—');

            this.updateRowKgBatch(batchSizeWithWaste);
            this.renderRequirements(batchSizeWithWaste, currency);
            this.renderCostDrivers(currency);
            this.renderSweetSpot(currency, wastePct);
        },

        /* ──────────────────────────────
         * Per-row "Kg / batch" column in the formula table
         * ────────────────────────────── */
        updateRowKgBatch: function (batchSizeWithWaste) {
            var total = 0;
            this.$body.find('.pc-row').each(function () {
                var $r   = $(this);
                var ww   = parseFloat($r.find('.pc-field-ww').val()) || 0;
                var need = batchSizeWithWaste > 0 ? (ww / 100) * batchSizeWithWaste : 0;
                total += need;
                $r.find('.pc-cell-kgbatch').text(need > 0 ? need.toFixed(3) + ' kg' : '—');
            });
            $('#pc-total-kgbatch').html(total > 0 ? '<strong>' + total.toFixed(3) + ' kg</strong>' : '');
        },

        /* ──────────────────────────────
         * Row data snapshot for insight panels
         * ────────────────────────────── */
        getRowData: function () {
            var rows = [];
            this.$body.find('.pc-row').each(function () {
                var $r = $(this);
                var name = $r.find('.pc-trade-search').val() ||
                           $r.find('.pc-field-trade-name option:selected').text() || '';
                if (name === '— Select —') { name = ''; }
                rows.push({
                    $row: $r,
                    name: name || '(unnamed)',
                    phase: ($r.find('.pc-field-phase').val() || '').toUpperCase(),
                    ww: parseFloat($r.find('.pc-field-ww').val()) || 0,
                    price: parseFloat($r.find('.pc-field-price').val()) || 0,
                    moq: parseFloat($r.find('.pc-field-moq').val()) || 0,
                    ph: $r.find('.pc-field-ph').val() || '',
                    fn: $r.find('.pc-field-function').val() || '',
                    usageMin: parseFloat($r.attr('data-usage-min')),
                    usageMax: parseFloat($r.attr('data-usage-max')),
                    tiers: PC.readTiers($r)
                });
            });
            return rows;
        },

        /* ──────────────────────────────
         * Bulk pricing tiers
         * ────────────────────────────── */
        readTiers: function ($row) {
            var raw = $row.attr('data-price-tiers');
            if (!raw) return { perkg: [], packs: [] };
            try {
                var t = JSON.parse(raw);
                if (t && (t.perkg || t.packs)) {
                    return { perkg: t.perkg || [], packs: t.packs || [] };
                }
                return { perkg: [], packs: [] };
            } catch (e) {
                return { perkg: [], packs: [] };
            }
        },

        pickCheaper: function (a, b) {
            if (!a) return b;
            if (!b) return a;
            if (b.cost < a.cost - 1e-9) return b;
            if (Math.abs(b.cost - a.cost) <= 1e-9 && b.qty > a.qty) return b;
            return a;
        },

        // Per-kg rate a purchase of `q` kg qualifies for: the rate of the
        // largest break whose threshold is at or below q. `perkg` must be
        // sorted ascending by threshold. Mirrors PC_Trade_Data::perkg_rate().
        perkgRate: function (perkg, q) {
            var rate = 0;
            perkg.forEach(function (r) {
                if ((parseFloat(r.threshold) || 0) <= q + 1e-9) {
                    rate = parseFloat(r.rate) || 0;
                }
            });
            return rate;
        },

        // Prefer the greater quantity; on a quantity tie prefer lower cost.
        // Mirrors PC_Trade_Data::pick_more_stock().
        pickMoreStock: function (a, b) {
            if (!a) return b;
            if (!b) return a;
            if (b.qty > a.qty + 1e-9) return b;
            if (Math.abs(b.qty - a.qty) <= 1e-9 && b.cost < a.cost - 1e-9) return b;
            return a;
        },

        // Free-stock allowance as a fraction (e.g. 0.05 for 5%). Mirrors
        // PC_Trade_Data::get_stock_allowance(); sourced from pcData.
        stockAllowance: function () {
            var pct = parseFloat(window.pcData && pcData.stockAllowancePct);
            if (!isFinite(pct) || pct < 0) { pct = 5; }
            return pct / 100;
        },

        // Candidate per-kg-break purchases covering `needed`, each rounded up
        // to a whole MOQ increment. Mirrors PC_Trade_Data::perkg_candidates().
        perkgCandidates: function (perkg, needed) {
            var self = this, out = [];
            if (!perkg.length) { return out; }
            var sorted = perkg.slice().sort(function (a, b) {
                return (parseFloat(a.threshold) || 0) - (parseFloat(b.threshold) || 0);
            });
            var increment = parseFloat(sorted[0].threshold) || 0;
            sorted.forEach(function (r) {
                var target = Math.max(needed, parseFloat(r.threshold) || 0);
                var q = (increment > 0) ? Math.ceil(target / increment - 1e-9) * increment : target;
                out.push({ qty: q, cost: q * self.perkgRate(sorted, q) });
            });
            return out;
        },

        // Cheapest purchase to obtain at least `needed` kg → { qty, cost }.
        // Finds the strict cheapest across per-kg breaks and pack combinations,
        // then applies the free-stock allowance: the greatest quantity whose
        // cost is within (cheapest × (1 + allowance)) wins. Mirrors
        // PC_Trade_Data::cheapest_purchase().
        purchaseDetail: function (tiers, needed, fallback) {
            needed = Math.max(0, needed);
            var fb = parseFloat(fallback) || 0;
            var perkg = (tiers && tiers.perkg) ? tiers.perkg : [];
            var packs = (tiers && tiers.packs) ? tiers.packs : [];

            if (!perkg.length && !packs.length) {
                return { qty: needed, cost: needed * fb };
            }
            if (needed <= 0) { return { qty: 0, cost: 0 }; }

            var self = this;

            // Coverage target: allow a tiny shortfall so unit-conversion / whole-
            // gram rounding doesn't reject a cheaper near-exact purchase. Mirrors
            // PC_Trade_Data::COVERAGE_TOLERANCE.
            var needCov = needed * (1 - 0.005);

            var perkgCands = this.perkgCandidates(perkg, needCov);
            var packMin = packs.length ? this.cheapestPackCombo(packs, needCov) : null;

            var minCost = null;
            perkgCands.forEach(function (c) {
                if (minCost === null || c.cost < minCost) { minCost = c.cost; }
            });
            if (packMin && (minCost === null || packMin.cost < minCost)) { minCost = packMin.cost; }
            if (minCost === null) { return { qty: needed, cost: needed * fb }; }

            var budget = minCost * (1 + this.stockAllowance());

            var best = null;
            perkgCands.forEach(function (c) {
                if (c.cost <= budget + 1e-9) { best = self.pickMoreStock(best, c); }
            });
            if (packs.length) {
                var packPref = this.cheapestPackCombo(packs, needCov, budget);
                if (packPref) { best = this.pickMoreStock(best, packPref); }
            }

            return best || { qty: needed, cost: needed * fb };
        },

        cheapestPackCombo: function (packsIn, needed, budget) {
            var self = this;
            if (typeof budget === 'undefined') { budget = null; }
            var packs = [];
            var maxG = 0;
            packsIn.forEach(function (t) {
                var g = Math.round((parseFloat(t.qty) || 0) * 1000);
                if (g > 0) { packs.push({ g: g, cost: parseFloat(t.cost) || 0 }); if (g > maxG) { maxG = g; } }
            });
            if (!packs.length) { return null; }

            var needG = Math.ceil(needed * 1000);

            var single = null;
            packs.forEach(function (p) {
                var cnt = Math.ceil(needG / p.g);
                var c = cnt * p.cost;
                if (single === null || c < single.cost) { single = { cost: c, qty: cnt * p.g }; }
            });

            function gcd(a, b) { a = Math.abs(a); b = Math.abs(b); while (b) { var t = b; b = a % b; a = t; } return a; }
            var unit = needG;
            packs.forEach(function (p) { unit = gcd(unit, p.g); });
            if (unit < 1) { unit = 1; }
            var target = Math.ceil(needG / unit);
            var ext = target + Math.ceil(maxG / unit);
            if (ext > 300000) { return { qty: single.qty / 1000, cost: single.cost }; }

            var dpCost = new Array(ext + 1).fill(Infinity);
            var dpQty  = new Array(ext + 1).fill(-1);
            dpCost[0] = 0; dpQty[0] = 0;
            for (var w = 1; w <= ext; w++) {
                for (var i = 0; i < packs.length; i++) {
                    var u = packs[i].g / unit;
                    var prev = (w - u > 0) ? w - u : 0;
                    if (dpCost[prev] === Infinity) { continue; }
                    var c = packs[i].cost + dpCost[prev];
                    var q = packs[i].g + dpQty[prev];
                    if (c < dpCost[w] - 1e-9 || (Math.abs(c - dpCost[w]) <= 1e-9 && q > dpQty[w])) {
                        dpCost[w] = c; dpQty[w] = q;
                    }
                }
            }

            var min = { qty: dpQty[target] / 1000, cost: dpCost[target] };
            if (budget === null) { return min; }

            var bestCombo = min;
            for (var x = target; x <= ext; x++) {
                if (dpCost[x] === Infinity || dpQty[x] < 0) { continue; }
                if (dpCost[x] <= budget + 1e-9) {
                    bestCombo = self.pickMoreStock(bestCombo, { qty: dpQty[x] / 1000, cost: dpCost[x] });
                }
            }
            return bestCombo;
        },

        lineCost: function (tiers, needed, fallback) {
            return this.purchaseDetail(tiers, needed, fallback).cost;
        },

        /* ──────────────────────────────
         * Batch Requirements (per-ingredient purchasing list)
         * ────────────────────────────── */
        renderRequirements: function (batchSizeWithWaste, currency) {
            var $box = $('#pc-batch-requirements');
            if (!$box.length) { return; }

            var self = this;
            var rows = this.getRowData().filter(function (r) { return r.ww > 0; });

            if (!rows.length || batchSizeWithWaste <= 0) {
                $box.html('<em>Set Batch Size and add ingredients to see quantities.</em>');
                return;
            }

            var html = '<table class="pc-req-table"><thead><tr>' +
                '<th>Ingredient</th><th>% w/w</th><th>Kg needed</th><th>Kg to buy</th><th>Line cost</th>' +
                '</tr></thead><tbody>';

            var totalNeed = 0, totalBuy = 0, totalCost = 0;
            rows.forEach(function (r) {
                var need = (r.ww / 100) * batchSizeWithWaste;
                var d    = self.purchaseDetail(r.tiers, need, r.price);
                totalNeed += need; totalBuy += d.qty; totalCost += d.cost;

                var extra = d.qty - need;
                var buyCell = d.qty.toFixed(3) + ' kg';
                if (extra > 0.0005) {
                    buyCell += ' <span class="pc-req-extra">(+' + extra.toFixed(3) + ' spare)</span>';
                }

                html += '<tr>' +
                    '<td>' + $('<span>').text(r.name).html() + '</td>' +
                    '<td>' + r.ww + '%</td>' +
                    '<td>' + need.toFixed(3) + ' kg</td>' +
                    '<td>' + buyCell + '</td>' +
                    '<td>' + (d.cost > 0 ? currency + d.cost.toFixed(2) : '—') + '</td>' +
                    '</tr>';
            });

            html += '</tbody><tfoot><tr>' +
                '<th>Total</th><th></th>' +
                '<th>' + totalNeed.toFixed(3) + ' kg</th>' +
                '<th>' + totalBuy.toFixed(3) + ' kg</th>' +
                '<th>' + currency + totalCost.toFixed(2) + '</th>' +
                '</tr></tfoot></table>';

            $box.html(html);
        },

        /* ──────────────────────────────
         * Cost Drivers: % of weight vs % of raw material cost
         * ────────────────────────────── */
        renderCostDrivers: function (currency) {
            var $box = $('#pc-cost-drivers');
            if (!$box.length) return;

            var rows = this.getRowData().filter(function (r) { return r.ww > 0; });
            var totalWw = 0, totalCost = 0;

            rows.forEach(function (r) {
                r.costPerKg = (r.ww / 100) * r.price;
                totalWw   += r.ww;
                totalCost += r.costPerKg;
            });

            if (!rows.length || totalCost <= 0) {
                $box.html('<em>Add ingredients with prices to see the breakdown.</em>');
                return;
            }

            rows.sort(function (a, b) { return b.costPerKg - a.costPerKg; });

            var html = '<table class="pc-drivers-table"><thead><tr>' +
                '<th>Ingredient</th><th>% of weight</th><th>% of cost</th><th class="pc-driver-bars">Weight vs Cost</th>' +
                '</tr></thead><tbody>';

            rows.forEach(function (r) {
                var wSharePct = totalWw > 0 ? (r.ww / totalWw) * 100 : 0;
                var cSharePct = (r.costPerKg / totalCost) * 100;
                html += '<tr>' +
                    '<td>' + $('<span>').text(r.name).html() + '</td>' +
                    '<td>' + wSharePct.toFixed(1) + '%</td>' +
                    '<td><strong>' + cSharePct.toFixed(1) + '%</strong></td>' +
                    '<td class="pc-driver-bars">' +
                        '<div class="pc-bar pc-bar-weight" style="width:' + Math.min(100, wSharePct).toFixed(1) + '%;"></div>' +
                        '<div class="pc-bar pc-bar-cost" style="width:' + Math.min(100, cSharePct).toFixed(1) + '%;"></div>' +
                    '</td></tr>';
            });

            html += '</tbody></table>' +
                '<p class="description"><span class="pc-bar-key pc-bar-weight"></span> % of formula weight ' +
                '&nbsp; <span class="pc-bar-key pc-bar-cost"></span> % of raw material cost</p>';

            $box.html(html);
        },

        /* ──────────────────────────────
         * Batch Size Sweet Spot: unit cost at candidate batch sizes
         * ────────────────────────────── */
        renderSweetSpot: function (currency, wastePct) {
            var self = this;
            var $box = $('#pc-sweet-spot');
            if (!$box.length) return;

            var batchSize = this.getFieldValue('batch_size');
            var unitSize  = this.getFieldValue('unit_size');
            if (batchSize <= 0 || unitSize <= 0) {
                $box.html('<em>Requires Batch Size and Unit Size to be set.</em>');
                return;
            }

            var labour            = this.getFieldValue('labour');
            var facilityCosts     = this.getFieldValue('facility_running_costs');
            var miscCosts         = this.getFieldValue('misc_costs');
            var packagingUnitCost = this.getFieldValue('packaging_unit_cost');
            var rows              = this.getRowData();

            var multipliers = [0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4, 5];
            var results = [];
            var best = null;

            multipliers.forEach(function (mult) {
                var size      = batchSize * mult;
                var sizeWaste = size * (1 + wastePct / 100);
                var units     = Math.floor((size * 1000) / unitSize);
                if (units <= 0) return;

                var ingCost = 0;
                rows.forEach(function (r) {
                    var kgNeeded = (r.ww / 100) * sizeWaste;
                    if (kgNeeded <= 0) return;
                    ingCost += self.lineCost(r.tiers, kgNeeded, r.price);
                });

                var total    = ingCost + labour + facilityCosts + miscCosts + (packagingUnitCost * units);
                var unitCost = total / units;

                var entry = { mult: mult, size: size, units: units, unitCost: unitCost };
                results.push(entry);
                if (!best || unitCost < best.unitCost) best = entry;
            });

            if (!results.length) {
                $box.html('<em>Requires Batch Size and Unit Size to be set.</em>');
                return;
            }

            var maxCost = Math.max.apply(null, results.map(function (r) { return r.unitCost; }));

            var html = '<table class="pc-sweetspot-table"><thead><tr>' +
                '<th>Batch size</th><th>Units</th><th>Cost / unit</th><th class="pc-driver-bars"></th>' +
                '</tr></thead><tbody>';

            results.forEach(function (r) {
                var isBest    = best && r === best;
                var isCurrent = r.mult === 1;
                html += '<tr class="' + (isBest ? 'pc-sweet-best' : '') + '">' +
                    '<td>' + r.size.toFixed(2) + ' kg' + (isCurrent ? ' <em>(current)</em>' : '') + '</td>' +
                    '<td>' + r.units + '</td>' +
                    '<td><strong>' + currency + r.unitCost.toFixed(3) + '</strong>' + (isBest ? ' ★' : '') + '</td>' +
                    '<td class="pc-driver-bars"><div class="pc-bar pc-bar-cost" style="width:' +
                        (maxCost > 0 ? Math.min(100, (r.unitCost / maxCost) * 100).toFixed(1) : 0) + '%;"></div></td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            $box.html(html);
        },

        /* ──────────────────────────────
         * Refresh all ingredient data from Trade Names
         * ────────────────────────────── */
        refreshAllRows: function () {
            var self    = this;
            var $status = $('#pc-refresh-status');
            var $rows   = this.$body.find('.pc-row').filter(function () {
                return $(this).find('.pc-field-trade-name').val();
            });

            if (!$rows.length) {
                $status.text('No ingredients to refresh.');
                return;
            }

            var pending = $rows.length;
            var changed = 0;
            $status.text('Refreshing…');

            $rows.each(function () {
                var $row     = $(this);
                var id       = $row.find('.pc-field-trade-name').val();
                var oldPrice = $row.find('.pc-field-price').val();

                self.fetchTradeMeta(id, $row, function (res) {
                    if (res && res.success && String($row.find('.pc-field-price').val()) !== String(oldPrice)) {
                        changed++;
                        $row.find('.pc-field-price').addClass('pc-flash');
                        setTimeout(function () { $row.find('.pc-field-price').removeClass('pc-flash'); }, 2500);
                    }
                    pending--;
                    if (pending === 0) {
                        $status.text('Done — ' + changed + ' price' + (changed === 1 ? '' : 's') + ' changed. Save the product to store the new values.');
                        self.recalcCostSummary();
                        self.recalcWarnings();
                    }
                });
            });
        },

        /* ──────────────────────────────
         * Formulation guardrails
         * ────────────────────────────── */
        parsePh: function (str) {
            if (!str) return null;
            var m = String(str).match(/\d+(\.\d+)?/g);
            if (!m || !m.length) return null;
            var nums = m.map(parseFloat);
            return [Math.min.apply(null, nums), Math.max.apply(null, nums)];
        },

        recalcWarnings: function () {
            var $box = $('#pc-formula-warnings');
            if (!$box.length) return;

            var rows = this.getRowData();
            var warnings = [];
            var infos = [];

            if (!rows.length) {
                $box.empty();
                return;
            }

            // Total must be 100%.
            var total = 0;
            rows.forEach(function (r) { total += r.ww; });
            if (Math.abs(total - 100) > 0.01) {
                warnings.push('Formula total is ' + total.toFixed(2) + '% — it should be 100%.');
                this.$total.addClass('pc-total-bad').removeClass('pc-total-ok');
            } else {
                this.$total.addClass('pc-total-ok').removeClass('pc-total-bad');
            }

            // Usage-rate limits.
            rows.forEach(function (r) {
                r.$row.removeClass('pc-row-warning');
                if (r.ww <= 0) return;
                if (!isNaN(r.usageMax) && r.usageMax > 0 && r.ww > r.usageMax) {
                    warnings.push(r.name + ' is at ' + r.ww + '% — above its maximum usage rate of ' + r.usageMax + '%.');
                    r.$row.addClass('pc-row-warning');
                }
                if (!isNaN(r.usageMin) && r.usageMin > 0 && r.ww < r.usageMin) {
                    warnings.push(r.name + ' is at ' + r.ww + '% — below its minimum effective usage rate of ' + r.usageMin + '%.');
                    r.$row.addClass('pc-row-warning');
                }
            });

            // Preservative present? (skip the reminder if acknowledged as anhydrous / self-preserving)
            var acknowledged = $('#pc-preservative-ack').is(':checked');
            var hasPreservative = rows.some(function (r) {
                return /preserv/i.test(r.fn);
            });
            if (!hasPreservative && !acknowledged) {
                warnings.push('No ingredient has the Function "Preservative" — confirm this formula is self-preserving or anhydrous.');
            }

            // pH compatibility window.
            var self = this;
            var win = null;
            var winValid = true;
            rows.forEach(function (r) {
                var range = self.parsePh(r.ph);
                if (!range) return;
                if (!win) {
                    win = range.slice();
                } else {
                    win[0] = Math.max(win[0], range[0]);
                    win[1] = Math.min(win[1], range[1]);
                }
            });
            if (win) {
                if (win[0] > win[1]) {
                    winValid = false;
                    warnings.push('Ingredient pH ranges do not overlap — there is no pH at which every ingredient is within its stated range.');
                } else {
                    infos.push('Formula pH compatibility window: ' + win[0].toFixed(1) + ' – ' + win[1].toFixed(1) + '.');
                    var targetPh = this.getFieldValue('final_ph');
                    if (targetPh > 0 && (targetPh < win[0] || targetPh > win[1])) {
                        warnings.push('Target final pH ' + targetPh + ' is outside the compatibility window ' + win[0].toFixed(1) + ' – ' + win[1].toFixed(1) + '.');
                    }
                }
            }

            var html = '';
            if (warnings.length) {
                html += '<ul class="pc-warning-list">';
                warnings.forEach(function (w) {
                    html += '<li>' + $('<span>').text(w).html() + '</li>';
                });
                html += '</ul>';
            }
            if (infos.length && winValid) {
                html += '<ul class="pc-info-list">';
                infos.forEach(function (i) {
                    html += '<li>' + $('<span>').text(i).html() + '</li>';
                });
                html += '</ul>';
            }
            $box.html(html);
        },

        /* ──────────────────────────────
         * Per-ingredient INCI breakdown
         * ────────────────────────────── */
        collapseAllInci: function () {
            this.$body.find('.pc-inci-subrow').remove();
            this.$body.find('.pc-row').removeClass('pc-inci-open');
            this.$body.find('.pc-inci-toggle').removeClass('active');
        },

        toggleInci: function ($row) {
            var self = this;

            // Already open → close.
            if ($row.next('.pc-inci-subrow').length) {
                $row.next('.pc-inci-subrow').remove();
                $row.removeClass('pc-inci-open');
                $row.find('.pc-inci-toggle').removeClass('active');
                return;
            }

            var tradeId = $row.find('.pc-field-trade-name').val();
            if (!tradeId) {
                window.alert('Select a Trade Name for this row first.');
                return;
            }

            var cols = $row.children('td').length || 11;
            var $sub = $('<tr class="pc-inci-subrow"><td colspan="' + cols + '"><div class="pc-inci-panel"><em>Loading INCI breakdown…</em></div></td></tr>');
            $row.after($sub);
            $row.addClass('pc-inci-open');
            $row.find('.pc-inci-toggle').addClass('active');

            $.ajax({
                url: pcData.ajaxUrl,
                data: { action: 'pc_get_inci_composition', nonce: pcData.nonce, post_id: tradeId },
                success: function (res) {
                    if (res.success) {
                        self.renderInciPanel($sub.find('.pc-inci-panel'), res.data, $row);
                    } else {
                        $sub.find('.pc-inci-panel').html('<em>Could not load: ' + $('<span>').text(res.data || 'error').html() + '</em>');
                    }
                },
                error: function () {
                    $sub.find('.pc-inci-panel').html('<em>Could not load INCI breakdown.</em>');
                }
            });
        },

        renderInciPanel: function ($panel, data, $row) {
            var self    = this;
            var comp    = (data.composition && data.composition.length) ? data.composition : [{ inci: '', percent_min: '', percent_max: '' }];
            var tradeId = $row.find('.pc-field-trade-name').val();
            var title   = $('<span>').text(data.title || 'this raw material').html();

            var html = '<div class="pc-inci-panel-head">INCI breakdown for <strong>' + title +
                '</strong> — enter each INCI as a <strong>Min–Max % of the raw material</strong> (from its SDS). ' +
                'The midpoint is used for label ordering, and the constituents are normalised to total 100% of the material.</div>';
            html += '<table class="pc-inci-sub-table"><thead><tr>' +
                '<th>INCI Name</th><th>Min %</th><th>Max %</th><th>Midpoint</th><th>&asymp; % in formula</th><th></th>' +
                '</tr></thead><tbody class="pc-inci-sub-body"></tbody></table>';
            html += '<div class="pc-inci-sub-foot">' +
                '<button type="button" class="button pc-inci-sub-add">+ Add INCI</button> ' +
                '<span class="pc-inci-sub-total"></span> ' +
                '<button type="button" class="button button-primary pc-inci-sub-save" data-trade="' + parseInt(tradeId, 10) + '">Save to raw material</button> ' +
                '<span class="pc-inci-sub-status"></span>' +
                '<p class="description">For an exact value, put the same number in Min and Max. Saving updates this raw material\'s INCI composition everywhere it is used, and the label declaration re-orders accordingly. Reload the product to refresh the INCI Label Declaration preview below.</p>' +
                '</div>';

            $panel.html(html);

            var $body = $panel.find('.pc-inci-sub-body');
            comp.forEach(function (r) {
                var min = (r.percent_min != null && r.percent_min !== '') ? r.percent_min : r.percent;
                var max = (r.percent_max != null && r.percent_max !== '') ? r.percent_max : r.percent;
                self.addInciSubRow($body, r.inci, min, max);
            });
            self.recalcInciTotals($panel, $row);
        },

        addInciSubRow: function ($body, inci, min, max) {
            var $tr = $(
                '<tr class="pc-inci-sub-row">' +
                '<td><input type="text" class="pc-inci-sub-name widefat" placeholder="e.g. Glycerin"></td>' +
                '<td><input type="number" step="any" min="0" max="100" class="pc-inci-sub-min" style="width:80px;"></td>' +
                '<td><input type="number" step="any" min="0" max="100" class="pc-inci-sub-max" style="width:80px;"></td>' +
                '<td class="pc-inci-sub-mid">&mdash;</td>' +
                '<td class="pc-inci-sub-contrib">&mdash;</td>' +
                '<td><button type="button" class="button pc-inci-sub-remove" title="Remove">&times;</button></td>' +
                '</tr>'
            );
            $tr.find('.pc-inci-sub-name').val(inci || '');
            if (min !== '' && min != null && !isNaN(parseFloat(min))) {
                $tr.find('.pc-inci-sub-min').val(parseFloat(min));
            }
            if (max !== '' && max != null && !isNaN(parseFloat(max))) {
                $tr.find('.pc-inci-sub-max').val(parseFloat(max));
            }
            $body.append($tr);
        },

        // Midpoint of a sub-row (falls back to whichever single box is filled).
        inciRowMid: function ($subRow) {
            var minRaw = $subRow.find('.pc-inci-sub-min').val();
            var maxRaw = $subRow.find('.pc-inci-sub-max').val();
            var hasMin = minRaw !== '' && !isNaN(parseFloat(minRaw));
            var hasMax = maxRaw !== '' && !isNaN(parseFloat(maxRaw));
            if (!hasMin && !hasMax) return null;
            var min = hasMin ? parseFloat(minRaw) : parseFloat(maxRaw);
            var max = hasMax ? parseFloat(maxRaw) : parseFloat(minRaw);
            if (max < min) { var t = min; min = max; max = t; }
            return (min + max) / 2;
        },

        recalcInciTotals: function ($panel, $row) {
            var self  = this;
            var rowWW = ($row && $row.length) ? (parseFloat($row.find('.pc-field-ww').val()) || 0) : 0;

            // First pass: midpoints and their sum (for normalisation).
            var midSum = 0;
            $panel.find('.pc-inci-sub-row').each(function () {
                var mid = self.inciRowMid($(this));
                $(this).data('mid', mid);
                $(this).find('.pc-inci-sub-mid').text(mid != null ? mid.toFixed(2) : '—');
                if (mid != null) { midSum += mid; }
            });

            // Second pass: normalise midpoints to 100% of material → contribution in formula.
            $panel.find('.pc-inci-sub-row').each(function () {
                var mid = $(this).data('mid');
                var normalised = (mid != null && midSum > 0) ? (mid / midSum) * 100 : 0;
                var contrib = rowWW * normalised / 100;
                $(this).find('.pc-inci-sub-contrib').text(contrib > 0 ? contrib.toFixed(3) + '%' : '—');
            });

            var $t = $panel.find('.pc-inci-sub-total');
            if (midSum > 0) {
                $t.text('Midpoints total ' + midSum.toFixed(2) + '% → normalised to 100%').addClass('pc-total-ok').removeClass('pc-total-bad');
            } else {
                $t.text('Enter a % for each INCI').removeClass('pc-total-ok pc-total-bad');
            }
        },

        updateInciContribution: function ($row) {
            var $sub = $row.next('.pc-inci-subrow');
            if ($sub.length) {
                this.recalcInciTotals($sub.find('.pc-inci-panel'), $row);
            }
        },

        saveInci: function ($btn) {
            var $panel  = $btn.closest('.pc-inci-panel');
            var tradeId = $btn.data('trade');
            var $status = $panel.find('.pc-inci-sub-status');
            var rows    = [];

            $panel.find('.pc-inci-sub-row').each(function () {
                var name = $.trim($(this).find('.pc-inci-sub-name').val());
                var min  = $(this).find('.pc-inci-sub-min').val();
                var max  = $(this).find('.pc-inci-sub-max').val();
                if (name) {
                    rows.push({ inci: name, percent_min: min, percent_max: max });
                }
            });

            $status.text('Saving…').removeClass('pc-saved pc-error');

            $.ajax({
                url: pcData.ajaxUrl,
                method: 'POST',
                data: { action: 'pc_save_inci_composition', nonce: pcData.nonce, post_id: tradeId, rows: rows },
                success: function (res) {
                    if (res.success) {
                        $status.text('Saved ✓ — reload to refresh the declaration.').addClass('pc-saved');
                    } else {
                        $status.text('Save failed: ' + (res.data || 'error')).addClass('pc-error');
                    }
                },
                error: function () {
                    $status.text('Save failed: request error.').addClass('pc-error');
                }
            });
        },

        /* ──────────────────────────────
         * Formula version rename
         * ────────────────────────────── */
        saveVersionName: function ($input) {
            var index    = $input.data('index');
            var $status  = $('.pc-version-name-status[data-index="' + index + '"]');
            var lastSaved = $input.data('saved');
            var value    = $input.val();

            if (lastSaved !== undefined && lastSaved === value) {
                return; // No change since last save.
            }

            $status.text('…').removeClass('pc-saved pc-error');

            $.ajax({
                url: pcData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'pc_version_rename',
                    nonce: pcData.nonce,
                    post_id: $('#post_ID').val(),
                    index: index,
                    name: value
                },
                success: function (res) {
                    if (res.success) {
                        $input.data('saved', value);
                        $status.text('✓').addClass('pc-saved');
                        setTimeout(function () { $status.text(''); }, 1500);
                    } else {
                        $status.text('!').addClass('pc-error');
                    }
                },
                error: function () {
                    $status.text('!').addClass('pc-error');
                }
            });
        }
    };

    $(document).ready(function () {
        if ($('#pc-formula-wrap').length) {
            PC.init();
        }
    });

})(jQuery);

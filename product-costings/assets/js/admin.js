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
                placeholder: 'pc-sortable-placeholder',
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
        fetchTradeMeta: function (postId, $row) {
            $.ajax({
                url: pcData.ajaxUrl,
                data: { action: 'pc_get_trade_name_meta', nonce: pcData.nonce, post_id: postId },
                success: function (res) {
                    if (res.success && res.data) {
                        $row.find('.pc-field-ph').val(res.data.ph || '');
                        $row.find('.pc-field-price').val(res.data.price_per_kg || '');
                        $row.find('.pc-field-moq').val(res.data.moq || '');
                        $row.find('.pc-field-natural-origin').val(res.data.natural_origin || '');

                        // Pre-select function if trade name has one.
                        if (res.data.function1) {
                            var $fnSelect = $row.find('.pc-field-function');
                            if ($fnSelect.find('option[value="' + res.data.function1 + '"]').length) {
                                $fnSelect.val(res.data.function1);
                            }
                        }

                        PC.recalcCostSummary();
                    }
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
                $(this).closest('.pc-row').remove();
                self.reindexRows();
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // Duplicate row.
            this.$wrap.on('click', '.pc-duplicate-row', function () {
                var $row   = $(this).closest('.pc-row');
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
            var currency = '£';

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
            this.$body.find('.pc-row').each(function () {
                var ww    = parseFloat($(this).find('.pc-field-ww').val()) || 0;
                var price = parseFloat($(this).find('.pc-field-price').val()) || 0;
                var moq   = parseFloat($(this).find('.pc-field-moq').val()) || 0;

                var kgNeeded = batchSizeWithWaste > 0 ? (ww / 100) * batchSizeWithWaste : 0;
                if (kgNeeded <= 0 || price <= 0) {
                    return;
                }

                var kgToPurchase = moq > 0 ? Math.ceil(kgNeeded / moq) * moq : kgNeeded;
                batchIngredientCost += kgToPurchase * price;
            });

            var totalBatchCost = batchIngredientCost + facilityCosts + labour + miscCosts + (packagingUnitCost * unitsPerBatch);
            var costPerUnit    = unitsPerBatch > 0 ? totalBatchCost / unitsPerBatch : 0;

            $('#pc-raw-cost-kg').text(rawCostPerKg > 0 ? currency + rawCostPerKg.toFixed(4) : '—');
            $('#pc-raw-cost-batch').text(batchIngredientCost > 0 ? currency + batchIngredientCost.toFixed(2) : '—');
            $('#pc-units-batch').text(unitsPerBatch > 0 ? unitsPerBatch : '—');
            $('#pc-batch-cost').text(totalBatchCost > 0 ? currency + totalBatchCost.toFixed(2) : '—');
            $('#pc-cost-unit').text(costPerUnit > 0 ? currency + costPerUnit.toFixed(4) : '—');
        }
    };

    $(document).ready(function () {
        if ($('#pc-formula-wrap').length) {
            PC.init();
        }
    });

})(jQuery);

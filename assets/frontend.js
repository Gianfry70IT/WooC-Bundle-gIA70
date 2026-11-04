/*
 * asset/frontend.js - Versione 2.8.2
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

jQuery(document).ready(function ($) {
    const $bundleForm = $('.wcb-bundle-form');
    const ALL_UNAVAILABLE_MSG = 'Al momento nessun prodotto di questo gruppo è disponibile.';

    // =========================================================================
    // 0. INIT CRITICO
    // =========================================================================
    $('.wcb-gallery-wrapper').each(function () {
        if ($(this).find('.wcb-thumbnail-image.active').length === 0) {
            $(this).find('.wcb-thumbnail-image').first().addClass('active');
        }
    });

    // =========================================================================
    // 1. SMART SELECT
    // =========================================================================
    function initSmartSelect() {
        $('.wcb-bundle-group').each(function () {
            const $group = $(this);
            const isRequired = $group.data('is-required');
            const mode = $group.data('selection-mode');

            // Gruppo con tutti i prodotti esauriti: avviso visibile subito, non solo dopo il clic sul pulsante
            const $items = $group.find('.wcb-product-item');
            if ($items.length > 0 && $items.not('.wcb-out-of-stock').length === 0 && $group.find('.wcb-group-stock-notice').length === 0) {
                const $notice = $('<div class="wcb-group-stock-notice" role="status"></div>').text(ALL_UNAVAILABLE_MSG);
                const $title = $group.find('.wcb-group-title').first();
                if ($title.length) $notice.insertAfter($title); else $group.prepend($notice);
            }

            if (isRequired && (mode === 'single' || mode === 'multiple')) {
                // I prodotti esauriti hanno l'input disabilitato: non contano per la selezione automatica
                const $inputs = $group.find('input[type="radio"], input[type="checkbox"]').not(':disabled');
                if ($inputs.length === 1) {
                    const $input = $inputs.first();
                    if (!$input.is(':checked')) {
                        $input.prop('checked', true).trigger('change');
                    }
                }
            }
        });
    }

    // =========================================================================
    // 2. GESTIONE CARRELLO
    // =========================================================================
    function groupCartItems() {
        if ($('table.cart, .shop_table.cart').length === 0) return;
        $('tr.cart_item').removeClass('wcb-group-start wcb-group-middle wcb-group-end wcb-group-single');

        let prevBundleId = null;
        let $rows = $('tr.cart_item');

        $rows.each(function (index) {
            const $row = $(this);
            const $info = $row.find('.wcb-cart-bundle-info');
            const currentBundleId = $info.length ? $info.data('wcb-bundle-id') : null;

            if (currentBundleId) {
                if (currentBundleId === prevBundleId) {
                    $info.find('.wcb-bundle-label, .wcb-edit-bundle-link').hide();
                }
                const nextRow = $rows.eq(index + 1);
                const nextInfo = nextRow.find('.wcb-cart-bundle-info');
                const nextBundleId = nextInfo.length ? nextInfo.data('wcb-bundle-id') : null;
                const isStart = currentBundleId !== prevBundleId;
                const isEnd = currentBundleId !== nextBundleId;

                if (isStart && isEnd) $row.addClass('wcb-group-single');
                else if (isStart) $row.addClass('wcb-group-start');
                else if (isEnd) $row.addClass('wcb-group-end');
                else $row.addClass('wcb-group-middle');
            }
            prevBundleId = currentBundleId;
        });
    }
    groupCartItems();
    $(document.body).on('updated_cart_totals', groupCartItems);

    // "Elimina bundle" nel carrello classico (nel carrello a blocchi lo gestisce frontend-blocks-cart.js)
    $(document.body).on('click', 'a.wcb-remove-bundle-link', function (e) {
        if (!confirm($(this).data('confirm') || 'Vuoi eliminare l\'intero bundle dal carrello?')) {
            e.preventDefault(); e.stopImmediatePropagation(); return false;
        }
    });

    $(document.body).on('click', '.remove', function (e) {
        const $row = $(this).closest('tr.cart_item');
        const $info = $row.find('.wcb-cart-bundle-info').length ? $row.find('.wcb-cart-bundle-info') : $(this).closest('.cart_item').find('.wcb-cart-bundle-info');
        if ($info.length > 0) {
            if (!confirm(wcb_params.i18n.confirm_remove_bundle)) {
                e.preventDefault(); e.stopImmediatePropagation(); return false;
            }
        }
    });

    if ($bundleForm.length === 0) return;

    const $addToCartButton = $bundleForm.find('.single_add_to_cart_button');
    const $messagesContainer = $('#wcb-ajax-messages');
    // Nel template il pulsante parte "disabled" (senza JS non deve inviare nulla): da qui in poi
    // prop('disabled') indica solo l'invio in corso, lo stato "bundle incompleto" è una classe
    $addToCartButton.prop('disabled', false);
    $('.wcb-mobile-submit-btn').prop('disabled', false);
    let validationAttempted = false;

    // =========================================================================
    // 3. LOGICA AGGIUNTA AL CARRELLO
    // =========================================================================
    $(document).on('click', '.wcb-mobile-submit-btn', function (e) {
        e.preventDefault();
        if (!$(this).is(':disabled')) {
            $bundleForm.trigger('submit');
        }
    });

    $bundleForm.on('submit', function (e) {
        e.preventDefault();
        if ($addToCartButton.is(':disabled')) return;
        if (getIncompleteGroups().length > 0) {
            showMissingFeedback();
            return;
        }
        $addToCartButton.prop('disabled', true).text('Elaborazione...');
        $messagesContainer.slideUp().empty();

        $.ajax({
            type: 'POST',
            url: wcb_params.ajax_url,
            data: { action: 'wcb_add_bundle_to_cart', security: wcb_params.nonce, form_data: $(this).serialize() },
            success: function (response) {
                if (response.success) {
                    $messagesContainer.html('<div class="woocommerce-message">' + response.data.message + '</div>').slideDown();
                    setTimeout(function () { window.location.href = response.data.cart_url; }, 1000);
                } else {
                    let errorsHtml = '';
                    response.data.messages.forEach(function (msg) { errorsHtml += '<li>' + msg + '</li>'; });
                    $messagesContainer.html('<ul class="woocommerce-error" role="alert">' + errorsHtml + '</ul>').slideDown();
                    $addToCartButton.prop('disabled', false).text($addToCartButton.data('original-text'));
                }
            },
            error: function () {
                $messagesContainer.html('<ul class="woocommerce-error" role="alert"><li>' + 'Si è verificato un errore critico. Riprova.' + '</li></ul>').slideDown();
                $addToCartButton.prop('disabled', false).text($addToCartButton.data('original-text'));
            }
        });
    });

    // =========================================================================
    // 4. FUNZIONI DI VALIDAZIONE
    // =========================================================================
    function isPersonalizationComplete($productItem) {
        if (!$productItem.data('personalization-required')) return true;
        let isComplete = true;
        $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input[data-required="true"], .wcb-variation-set .wcb-personalization-input[data-required="true"]').each(function () {
            if ($(this).val().trim() === '') { isComplete = false; $(this).addClass('wcb-input-error'); }
            else { $(this).removeClass('wcb-input-error'); }
        });
        return isComplete;
    }

    function areVariationsComplete($productItem) {
        if ($productItem.find('.wcb-variation-container').length === 0 && $productItem.find('.wcb-variation-fields-template').length === 0) return true;
        let isComplete = true;
        $productItem.find('.wcb-variation-container:visible select.wcb-variation-select, .wcb-variation-sets-container .wcb-variation-set select.wcb-variation-select').each(function () {
            if ($(this).val() === '') isComplete = false;
        });
        return isComplete;
    }

    function plural(n, singular, pluralForm) {
        return n === 1 ? singular : pluralForm;
    }

    // Restituisce '' se il gruppo è completo, altrimenti cosa manca (testo mostrato al cliente)
    function getGroupIssue($group) {
        const mode = $group.data('selection-mode');
        const isRequired = $group.data('is-required');
        const $groupItems = $group.find('.wcb-product-item');
        if (isRequired && $groupItems.length > 0 && $groupItems.not('.wcb-out-of-stock').length === 0) {
            return ALL_UNAVAILABLE_MSG;
        }
        const itemIssue = function ($productItem) {
            if (!areVariationsComplete($productItem)) return 'Scegli le opzioni del prodotto selezionato.';
            if (!isPersonalizationComplete($productItem)) return 'Compila i campi di personalizzazione obbligatori.';
            return '';
        };
        switch (mode) {
            case 'single': {
                const $radio = $group.find('input[type="radio"]:checked');
                if ($radio.length > 0) return itemIssue($radio.closest('.wcb-product-item'));
                return isRequired ? 'Scegli un prodotto.' : '';
            }
            case 'multiple': {
                const min = $group.data('min-qty');
                const max = $group.data('max-qty');
                const $checkboxes = $group.find('input[type="checkbox"]:checked');
                const count = $checkboxes.length;
                if (count === 0 && !isRequired) return '';
                if (count < min) return min === 1 ? 'Scegli almeno un prodotto.' : `Scegli almeno ${min} prodotti (${plural(count, 'ne hai scelto', 'ne hai scelti')} ${count}).`;
                if (max !== 0 && count > max) return `Puoi scegliere al massimo ${max} ${plural(max, 'prodotto', 'prodotti')} (ne hai scelti ${count}).`;
                let issue = '';
                $checkboxes.each(function () {
                    issue = itemIssue($(this).closest('.wcb-product-item'));
                    return issue === '';
                });
                return issue;
            }
            case 'quantity':
            case 'multiple_quantity': {
                const min = (mode === 'quantity') ? $group.data('total-qty') : $group.data('min-qty');
                const max = (mode === 'quantity') ? $group.data('total-qty') : $group.data('max-qty');
                let currentTotal = 0;
                let issue = '';
                $group.find('.wcb-quantity-input').each(function () {
                    const $input = $(this);
                    const $productItem = $input.closest('.wcb-product-item');
                    const qty = parseInt($input.val()) || 0;
                    const itemMin = parseInt($productItem.attr('data-item-min')) || 1;
                    const itemStep = parseInt($productItem.attr('data-item-step')) || 1;
                    currentTotal += qty;
                    if (qty > 0) {
                        const name = $productItem.find('.wcb-product-name').first().text().trim();
                        let qtyIssue = '';
                        if (qty < itemMin) qtyIssue = `La quantità minima per "${name}" è ${itemMin}.`;
                        else if (qty % itemStep !== 0) qtyIssue = `La quantità di "${name}" deve essere un multiplo di ${itemStep}.`;
                        // itemIssue va valutato comunque: segna in rosso i campi di personalizzazione vuoti
                        const otherIssue = itemIssue($productItem);
                        issue = issue || qtyIssue || otherIssue;
                    }
                });
                if (currentTotal === 0 && !isRequired) return '';
                if (issue) return issue;
                if (mode === 'quantity') {
                    if (currentTotal < max) return `Seleziona ${max} pezzi in totale: ${plural(max - currentTotal, 'ne manca 1', 'ne mancano ' + (max - currentTotal))}.`;
                    if (currentTotal > max) return `Seleziona ${max} pezzi in totale: ${plural(currentTotal - max, 'ne hai 1 in più', 'ne hai ' + (currentTotal - max) + ' in più')}.`;
                    return '';
                }
                if (currentTotal < min) return `Seleziona almeno ${min} ${plural(min, 'pezzo', 'pezzi')} (ora ${currentTotal}).`;
                if (max !== 0 && currentTotal > max) return `Puoi selezionare al massimo ${max} ${plural(max, 'pezzo', 'pezzi')} (ora ${currentTotal}).`;
                return '';
            }
        }
        return 'Completa questo gruppo.';
    }

    function validateGroup($group) {
        return getGroupIssue($group) === '';
    }

    // =========================================================================
    // 4b. COSA MANCA: messaggio nel gruppo, elenco sotto il pulsante, scroll
    // =========================================================================
    function getIncompleteGroups() {
        const incomplete = [];
        $bundleForm.find('.wcb-bundle-group').each(function (index) {
            const $group = $(this);
            const issue = getGroupIssue($group);
            if (issue) {
                const title = $group.find('.wcb-group-title').first().clone().children().remove().end().text().trim();
                incomplete.push({ $group: $group, issue: issue, title: title || ('Gruppo ' + (index + 1)) });
            }
        });
        return incomplete;
    }

    function renderMissingFeedback(incomplete) {
        $bundleForm.find('.wcb-bundle-group').each(function () {
            const $group = $(this);
            const found = incomplete.find(function (item) { return item.$group.is($group); });
            let $issue = $group.find('.wcb-group-issue').first();
            if (!found) {
                $issue.remove();
                return;
            }
            if ($issue.length === 0) {
                $issue = $('<div class="wcb-group-issue" role="status" tabindex="-1"></div>');
                const $title = $group.find('.wcb-group-title').first();
                if ($title.length) $issue.insertAfter($title); else $group.prepend($issue);
            }
            $issue.text(found.issue);
        });

        let $summary = $bundleForm.find('.wcb-missing-summary');
        if (incomplete.length === 0) {
            $summary.remove();
            return;
        }
        if ($summary.length === 0) {
            $summary = $('<div class="wcb-missing-summary" aria-live="polite"></div>').insertAfter($addToCartButton);
        }
        const $list = $('<ul></ul>');
        incomplete.forEach(function (item) {
            const $link = $('<a href="#"></a>').text(item.title).on('click', function (e) {
                e.preventDefault();
                focusGroup(item.$group);
            });
            $list.append($('<li></li>').append($link));
        });
        $summary.empty().append($('<p></p>').text('Per continuare completa:'), $list);
    }

    function focusGroup($group) {
        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        $('html, body').stop().animate({ scrollTop: Math.max(0, $group.offset().top - 100) }, reduceMotion ? 0 : 400).promise().done(function () {
            $group.removeClass('wcb-group-attention');
            void $group[0].offsetWidth; // riavvia l'animazione se si clicca di nuovo
            $group.addClass('wcb-group-attention');
            setTimeout(function () { $group.removeClass('wcb-group-attention'); }, 1600);
            // Il focus sul messaggio lo fa leggere anche agli screen reader
            const $issue = $group.find('.wcb-group-issue').first();
            if ($issue.length) $issue[0].focus({ preventScroll: true });
        });
    }

    function showMissingFeedback() {
        const incomplete = getIncompleteGroups();
        if (incomplete.length === 0) return;
        validationAttempted = true;
        renderMissingFeedback(incomplete);
        focusGroup(incomplete[0].$group);
    }

    function getCurrentQty($group) {
        let qty = 0; const mode = $group.data('selection-mode');
        if (mode === 'single' || mode === 'multiple') qty = $group.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length;
        else $group.find('.wcb-quantity-input').each(function () { qty += parseInt($(this).val()) || 0; });
        return qty;
    }

    window.wcb_validateGroup = validateGroup;
    window.wcb_updateBundleState = updateBundleState;
    window.wcb_updateBundlePrice = updateBundlePrice;

    // =========================================================================
    // 5. CALCOLO PREZZO (FIXED)
    // =========================================================================
    function getPricingConfig() {
        const formPricing = $bundleForm.first().data('pricing');
        if (formPricing && typeof formPricing === 'object') {
            return formPricing;
        }
        if (typeof wcb_params !== 'undefined' && wcb_params.pricing) {
            return wcb_params.pricing;
        }
        return null;
    }

    function getVariationPrice(productId, attributes, callback) {
        $.ajax({
            url: wcb_params.ajax_url, type: 'POST',
            data: { action: 'wcb_get_variation_price', product_id: productId, attributes: attributes, security: wcb_params.nonce },
            success: function (response) { if (response.success) callback(response.data.price); else callback(0); },
            error: function () { callback(0); }
        });
    }

    // Helper per pulire stringhe prezzo provenienti da attributi data (che potrebbero avere virgole)
    function parseDataPrice(value) {
        if (value === undefined || value === null || value === '') return 0;
        if (typeof value === 'number') return isNaN(value) ? 0 : value;
        const clean = value.toString().replace(',', '.').replace(/[^0-9.-]/g, '');
        return parseFloat(clean) || 0;
    }

    function parsePriceFromElement($element, $item) {
        if ($element && $element.length > 0) {
            let $priceNode = $element.find('ins .woocommerce-Price-amount').first();
            if ($priceNode.length === 0) {
                $priceNode = $element.find('.woocommerce-Price-amount').not('.woocommerce-price-suffix .woocommerce-Price-amount').last(); // esclude il suffisso tipo "IVA esclusa"
            }
            if ($priceNode.length === 0) {
                $priceNode = $element.find('ins').length > 0 ? $element.find('ins').first() : $element;
            }
            const pricing = getPricingConfig() || {};
            const thousandSep = pricing.thousand_separator || '';
            const decimalSep = pricing.decimal_separator || ',';
            let text = $priceNode.clone().find('.woocommerce-price-suffix, small').remove().end().text() || '';
            if (thousandSep) {
                text = text.split(thousandSep).join('');
            }
            if (decimalSep && decimalSep !== '.') {
                text = text.split(decimalSep).join('.');
            }
            const clean = text.replace(/[^0-9.-]/g, '').trim();
            const parsed = parseFloat(clean);
            if (!isNaN(parsed) && parsed > 0) {
                return parsed;
            }
        }
        if ($item && $item.length > 0) {
            const fallback = parseDataPrice($item.attr('data-base-price'));
            if (fallback > 0) return fallback;
        }
        return 0;
    }

    function formatPriceValue(amount) {
        const pricing = getPricingConfig() || {};
        const decimalSep = pricing.decimal_separator || ',';
        const currencySymbol = pricing.currency_symbol || '€';
        const currencyPos = pricing.currency_position || 'right';
        let formatted = (parseFloat(amount) || 0).toFixed(2).replace('.', decimalSep);
        if (currencyPos === 'left_space') formatted = currencySymbol + ' ' + formatted;
        else if (currencyPos.includes('left')) formatted = currencySymbol + formatted;
        else if (currencyPos === 'right_space') formatted = formatted + ' ' + currencySymbol;
        else formatted = formatted + currencySymbol;
        return formatted;
    }

    function finalizePriceCalculation(totalPrice, extraSurchargeTotal = 0) {
        const pricing = getPricingConfig();
        if (!pricing) return;
        let finalPrice = totalPrice;
        const fixedPrice = parseDataPrice(pricing.fixed_price);
        if (pricing.type === 'fixed' && fixedPrice > 0) {
            finalPrice = fixedPrice;
        } else {
            const discPct = parseDataPrice(pricing.discount_percentage);
            const discAmt = parseDataPrice(pricing.discount_amount);
            if (discPct > 0) finalPrice *= (1 - (discPct / 100));
            if (discAmt > 0) finalPrice = Math.max(0, finalPrice - discAmt);
        }
        finalPrice += (parseFloat(extraSurchargeTotal) || 0);
        let formattedPrice = formatPriceValue(finalPrice);
        $('.wcb-price-value').html(formattedPrice);
        $(document).trigger('wcb_bundle_price_updated', [finalPrice, formattedPrice]);
    }

    // Risolve il prezzo della variazione selezionata nei select di $container,
    // usando il campo '_price' incluso in data-variation-data dal template.
    // Ritorna null se la selezione è incompleta o non matcha nessuna variazione.
    function getSelectedVariationPrice($item, $container) {
        const variationData = $item.data('variation-data');
        if (!variationData || $container.length === 0) return null;
        let selection = {};
        let complete = true;
        $container.find('select.wcb-variation-select').each(function () {
            const val = $(this).val();
            if (!val) { complete = false; return false; }
            selection[$(this).data('attribute-name')] = val;
        });
        if (!complete || Object.keys(selection).length === 0) return null;
        for (const variation of variationData) {
            let isMatch = true;
            for (const attr in selection) {
                // Attributo vuoto nella variazione = "qualsiasi valore"
                if (variation[attr] && variation[attr] !== selection[attr]) { isMatch = false; break; }
            }
            if (isMatch && variation._price !== undefined && variation._price !== null && variation._price !== '') {
                const vPrice = parseDataPrice(variation._price);
                if (vPrice > 0) return vPrice;
            }
        }
        return null;
    }

    // --- FUNZIONE CRITICA AGGIORNATA ---
    function updateBundlePrice() {
        if (!getPricingConfig()) return;
        let bundleTotal = 0;
        let bundleExtraSurcharge = 0;
        let pendingRequests = 0;

        $('.wcb-bundle-group').each(function () {
            const $group = $(this);
            const mode = $group.data('selection-mode');
            // FIX: Override di gruppo applicabile solo alle modalità single/multiple
            const groupOverride = (mode === 'single' || mode === 'multiple')
                ? parseDataPrice($group.attr('data-group-price-override'))
                : 0;

            let groupItemTotal = 0;
            let groupSelectedQty = 0; // FIX: Contatore quantità reale

            $group.find('.wcb-product-item').each(function () {
                const $item = $(this);
                const $radio = $item.find('input[type="radio"]');
                const $checkbox = $item.find('input[type="checkbox"]');
                const $quantityInput = $item.find('.wcb-quantity-input');
                let quantity = 0;

                if ($radio.length > 0 && $radio.is(':checked')) quantity = 1;
                else if ($checkbox.length > 0 && $checkbox.is(':checked')) quantity = 1;
                else if ($quantityInput.length > 0) quantity = parseInt($quantityInput.val(), 10) || 0;

                const extraUnitPrice = parseDataPrice($item.attr('data-extra-unit-price'));
                const $extraInput = $item.find('.wcb-extra-units-input');
                const $extraSubtotal = $item.find('.wcb-extra-units-subtotal');

                if (quantity > 0) {
                    groupSelectedQty += quantity; // Incrementa il contatore "prodotti scelti"

                    const itemOverride = $item.attr('data-price-override');
                    if (itemOverride !== undefined && itemOverride !== null && itemOverride !== '') {
                        groupItemTotal += parseDataPrice(itemOverride) * quantity;
                    } else {
                        const basePrice = parsePriceFromElement($item.find('.wcb-product-price'), $item);
                        const $variationSets = $item.find('.wcb-variation-sets-container .wcb-variation-set');
                        if ($variationSets.length > 0) {
                            // Modalità a quantità: ogni "Pezzo" può avere una variazione (e prezzo) diversa
                            $variationSets.each(function () {
                                const variationPrice = getSelectedVariationPrice($item, $(this));
                                groupItemTotal += (variationPrice !== null ? variationPrice : basePrice);
                            });
                        } else {
                            const variationPrice = getSelectedVariationPrice($item, $item.find('.wcb-variation-container'));
                            groupItemTotal += (variationPrice !== null ? variationPrice : basePrice) * quantity;
                        }
                    }

                    if ($extraInput.length > 0 && extraUnitPrice > 0) {
                        const extraUnits = Math.max(0, parseInt($extraInput.val(), 10) || 0);
                        const extraCost = extraUnits * extraUnitPrice;
                        bundleExtraSurcharge += extraCost;
                        if ($extraSubtotal.length > 0) {
                            if (extraUnits > 0) {
                                $extraSubtotal.text('+' + formatPriceValue(extraCost)).addClass('has-extra');
                            } else {
                                $extraSubtotal.text('').removeClass('has-extra');
                            }
                        }
                    }
                } else if ($extraSubtotal.length > 0) {
                    $extraSubtotal.text('').removeClass('has-extra');
                }
            });

            // LOGICA OVERRIDE GRUPPO CORRETTA:
            // Se c'è un override valido E abbiamo selezionato almeno un prodotto (quantità > 0)
            // Allora il totale del gruppo è l'override.
            if (groupOverride > 0 && groupSelectedQty > 0) {
                bundleTotal += groupOverride;
            } else {
                bundleTotal += groupItemTotal;
            }
        });

        if (pendingRequests === 0) finalizePriceCalculation(bundleTotal, bundleExtraSurcharge);
    }

    function updateBundleState() {
        let isBundleComplete = true;
        $('.wcb-bundle-group').each(function () {
            const $group = $(this);
            const isGroupValid = validateGroup($group);
            const currentQty = getCurrentQty($group);
            const isRequired = $group.data('is-required');
            if (isGroupValid) {
                $group.removeClass('wcb-group-incomplete');
                if (currentQty === 0 && !isRequired) $group.removeClass('wcb-group-complete');
                else $group.addClass('wcb-group-complete');
            } else {
                $group.removeClass('wcb-group-complete').addClass('wcb-group-incomplete');
                isBundleComplete = false;
            }
        });
        // Mai "disabled": un pulsante disabilitato non riceve il clic, e il clic serve a dire cosa manca
        $addToCartButton.add('.wcb-mobile-submit-btn')
            .toggleClass('disabled wcb-is-incomplete', !isBundleComplete)
            .attr('aria-disabled', isBundleComplete ? 'false' : 'true');
        // Dopo il primo tentativo i messaggi seguono le modifiche del cliente e spariscono a gruppo completato
        if (validationAttempted) renderMissingFeedback(getIncompleteGroups());
        updateBundlePrice();
        $(document).trigger('wcb_bundle_state_updated', [isBundleComplete]);
    }

    // =========================================================================
    // 6. EVENTI FORM
    // =========================================================================
    $bundleForm.on('change', '.wcb-variation-select', function () {
        const $changedSelect = $(this);
        const $productItem = $changedSelect.closest('.wcb-product-item');
        const variationData = $productItem.data('variation-data');
        if (!variationData) { updateBundleState(); return; }
        const $selectContainer = $changedSelect.closest('.wcb-variation-container, .wcb-variation-set');
        const $allSelects = $selectContainer.find('.wcb-variation-select');
        let currentSelection = {};
        $allSelects.each(function () { const val = $(this).val(); if (val) currentSelection[$(this).data('attribute-name')] = val; });
        $allSelects.not($changedSelect).each(function () {
            const $currentSelect = $(this);
            const attributeName = $currentSelect.data('attribute-name');
            $currentSelect.find('option:gt(0)').prop('disabled', true).hide();
            const possibleOptions = new Set();
            variationData.forEach(function (variation) {
                let isMatch = true;
                for (const attr in currentSelection) { if (attr !== attributeName && variation[attr] && variation[attr] !== currentSelection[attr]) { isMatch = false; break; } }
                if (isMatch && variation[attributeName]) possibleOptions.add(variation[attributeName]);
            });
            $currentSelect.find('option').each(function () { if (possibleOptions.has($(this).val())) { $(this).prop('disabled', false).show(); } });
            if ($currentSelect.find('option:selected').is(':disabled')) $currentSelect.val('');
        });
        updateBundleState();
    });

    $bundleForm.on('change', 'input[type="radio"], input[type="checkbox"]', function () {
        const $input = $(this);
        const $productItem = $input.closest('.wcb-product-item');
        const $variationContainer = $productItem.find('.wcb-variation-container');
        const $personalizationContainer = $productItem.find('.wcb-personalization-field-container');
        const $extraUnitsContainer = $productItem.find('.wcb-extra-units-container');
        if ($input.is('[type="radio"]')) {
            const $otherProductItems = $input.closest('.wcb-group-products').find('.wcb-product-item').not($productItem);
            $otherProductItems.removeClass('selected');
            $otherProductItems.find('.wcb-variation-container').slideUp().find('select').prop('disabled', true);
            $otherProductItems.find('.wcb-personalization-field-container').slideUp();
            $otherProductItems.find('.wcb-extra-units-container').slideUp();
        }
        if ($input.is(':checked')) {
            $productItem.addClass('selected');
            $variationContainer.slideDown().find('select').prop('disabled', false).first().trigger('change');
            $personalizationContainer.slideDown();
            $extraUnitsContainer.slideDown();
        } else {
            $productItem.removeClass('selected');
            $variationContainer.slideUp().find('select').prop('disabled', true);
            $personalizationContainer.slideUp();
            $extraUnitsContainer.slideUp();
        }
        updateBundleState();
    });

    $bundleForm.on('change input', '.wcb-quantity-input', function (e) {
        const $input = $(this);
        const $group = $input.closest('.wcb-bundle-group');
        // TETTO DI SICUREZZA sulla quantità. Senza un limite, il ciclo qui sotto
        // crea un blocco DOM per ogni unità: un valore abnorme (es. un numero di
        // telefono/CAP iniettato dall'autofill del browser in questo campo
        // numerico) genererebbe milioni di iterazioni e bloccherebbe la pagina
        // ("La pagina non risponde"). Un umano digita pochi pezzi; l'autofill no.
        const mode = $group.data('selection-mode');
        let cap = 1000; // massimo pezzi gestibili nella UI per singola riga
        if (mode === 'quantity') { const t = parseInt($group.data('total-qty')) || 0; if (t > 0) cap = Math.min(cap, t); }
        else if (mode === 'multiple_quantity') { const m = parseInt($group.data('max-qty')) || 0; if (m > 0) cap = Math.min(cap, m); }
        const raw = parseInt($input.val());
        let qty = isNaN(raw) ? 0 : raw;
        if (qty < 0) qty = 0;
        if (qty > cap) qty = cap;
        // Riallinea il campo solo se il valore era fuori range (non disturba la
        // digitazione né il campo vuoto).
        if (!isNaN(raw) && raw !== qty) $input.val(qty);
        const $productItem = $input.closest('.wcb-product-item');
        const $setsContainer = $productItem.find('.wcb-variation-sets-container');
        const $variationTemplate = $productItem.find('.wcb-variation-fields-template');
        const $personalizationTemplate = $productItem.find('.wcb-personalization-field-template');
        const existingCount = $setsContainer.children('.wcb-variation-set').length;
        if (qty > 0) {
            $productItem.addClass('selected');
            if (existingCount < qty) {
                for (let i = existingCount; i < qty; i++) {
                    const $newSet = $('<div class="wcb-variation-set"><h5>' + 'Pezzo ' + (i + 1) + '</h5></div>');
                    if ($variationTemplate.length > 0) {
                        const html = $variationTemplate.html().replace(/__INDEX__/g, i);
                        $newSet.append($(html).find('select').prop('disabled', false).end());
                    }
                    if ($personalizationTemplate.length > 0) $newSet.append($personalizationTemplate.html().replace(/__INDEX__/g, i));
                    $setsContainer.append($newSet);
                }
            } else if (existingCount > qty) {
                $setsContainer.children('.wcb-variation-set').slice(qty).remove();
            }
            if (!$setsContainer.is(':visible')) {
                $setsContainer.slideDown();
            }
            $productItem.find('.wcb-extra-units-container').slideDown();
        } else {
            $setsContainer.empty();
            $productItem.removeClass('selected');
            $setsContainer.slideUp();
            $productItem.find('.wcb-extra-units-container').slideUp();
        }
        updateBundleState();
    });

    $bundleForm.on('change input', '.wcb-extra-units-input', function () {
        const $input = $(this);
        const raw = parseInt($input.val(), 10);
        let extraQty = isNaN(raw) ? 0 : raw;
        if (extraQty < 0) extraQty = 0;
        if (extraQty > 1000) extraQty = 1000;
        if (!isNaN(raw) && raw !== extraQty) $input.val(extraQty);
        updateBundlePrice();
    });

    // Rivalida i campi di personalizzazione a ogni modifica.
    // IMPORTANTE per l'autofill di Chrome: la compilazione automatica spesso
    // emette 'change' (non 'input' come la digitazione) o nessun evento, e non
    // sempre sui campi già marcati in errore. Ascoltiamo quindi sia 'input' sia
    // 'change' su TUTTI i campi, così i valori autocompilati sbloccano il pulsante.
    $bundleForm.on('input change', '.wcb-personalization-input', function () {
        updateBundleState();
    });

    // Autofill di Chrome al caricamento pagina: non emette input/change, ma fa
    // partire un'animazione CSS sullo pseudo-stato :-webkit-autofill. La
    // intercettiamo per rivalidare (vedi keyframes 'wcbAutofillStart' in frontend.css).
    $bundleForm.on('animationstart', '.wcb-personalization-input', function (e) {
        const name = (e.originalEvent || e).animationName;
        if (name === 'wcbAutofillStart') updateBundleState();
    });

    $addToCartButton.data('original-text', $addToCartButton.text());

    // 7. EDIT MODE
    function initEditMode() {
        if (typeof wcb_params.edit_mode === 'undefined' || !wcb_params.edit_mode.active) { updateBundleState(); return; }
        const config = wcb_params.edit_mode.config;
        const bundleId = wcb_params.edit_mode.bundle_id;
        if (!$('input[name="wcb_update_bundle_id"]').length) $bundleForm.append('<input type="hidden" name="wcb_update_bundle_id" value="' + bundleId + '">');
        $addToCartButton.text(wcb_params.i18n.update_bundle_btn);
        $addToCartButton.data('original-text', wcb_params.i18n.update_bundle_btn);

        const fillPersonalization = ($container, data) => {
            if (!data || !Array.isArray(data)) return;
            data.forEach((field, fIndex) => { $container.find('.wcb-personalization-input').eq(fIndex).val(field.value); });
        };
        function waitForAndPopulate($container, attributes, attempt = 1) {
            if (attempt > 15) return;
            const $selects = $container.find('select');
            if ($selects.length === 0 && Object.keys(attributes).length > 0) { setTimeout(() => waitForAndPopulate($container, attributes, attempt + 1), 200); return; }
            $.each(attributes, function (key, value) {
                let attrName = key.startsWith('attribute_') ? key : 'attribute_' + key;
                let $select = $container.find('select[data-attribute-name="' + attrName + '"]');
                if (!$select.length) $select = $container.find('select[name*="[' + attrName + ']"]');
                if ($select.length) {
                    $select.prop('disabled', false).val(value);
                    // Variante nel frattempo esaurita: niente preselezione, il cliente ne sceglie un'altra
                    if ($select.find('option:selected').is(':disabled')) $select.val('');
                    $select.trigger('change');
                }
            });
        }
        $.each(config, function (groupIndex, selections) {
            const $group = $('.wcb-bundle-group[data-group-index="' + groupIndex + '"]');
            if (!$group.length) return;
            const itemCounts = {};
            selections.forEach(item => {
                const key = (item.is_variation && item.parent_id) ? item.parent_id : item.item_id;
                if (!itemCounts[key]) itemCounts[key] = [];
                itemCounts[key].push(item);
            });
            $.each(itemCounts, function (prodId, items) {
                let $productItem = $group.find('.wcb-product-item[data-product-id="' + prodId + '"]');
                if (!$productItem.length) { const $input = $group.find('input[value="' + prodId + '"]'); if ($input.length) $productItem = $input.closest('.wcb-product-item'); }
                // Prodotto esaurito dopo l'aggiunta al carrello: non lo ripristiniamo, il gruppo segnala cosa manca
                if ($productItem.length && !$productItem.hasClass('wcb-out-of-stock')) {
                    const qty = items.length;
                    const $checkbox = $productItem.find('input[type="checkbox"], input[type="radio"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true).trigger('change');
                        if (items[0].is_variation) waitForAndPopulate($productItem.find('.wcb-variation-container'), items[0].attributes);
                        if (items[0].personalization_data) setTimeout(() => { fillPersonalization($productItem.find('.wcb-personalization-field-container'), items[0].personalization_data); }, 300);
                    }
                    const totalExtraUnits = items.reduce((sum, it) => sum + (parseInt(it.extra_units, 10) || 0), 0);
                    const $extraInput = $productItem.find('.wcb-extra-units-input');
                    if ($extraInput.length && totalExtraUnits > 0) {
                        setTimeout(function () { $extraInput.val(totalExtraUnits).trigger('change'); }, 150);
                    }
                    const $qtyInput = $productItem.find('.wcb-quantity-input');
                    if ($qtyInput.length) {
                        setTimeout(function () { $qtyInput.val(qty).trigger('change').trigger('input'); }, 100);
                        const populateSets = (retry = 0) => {
                            const $sets = $productItem.find('.wcb-variation-set');
                            if ($sets.length < qty && retry < 15) { setTimeout(() => populateSets(retry + 1), 200); return; }
                            $sets.each(function (index) {
                                if (items[index]) {
                                    if (items[index].is_variation) waitForAndPopulate($(this), items[index].attributes);
                                    if (items[index].personalization_data) fillPersonalization($(this), items[index].personalization_data);
                                }
                            });
                        };
                        setTimeout(populateSets, 200);
                    }
                }
            });
        });
        setTimeout(updateBundleState, 1500);
    }
    initEditMode();
    initSmartSelect();
    initLightbox();
    setTimeout(() => updateBundleState(), 500);

    // 8. GALLERIA MICRO-CAROUSEL
    $('body').on('click', '.wcb-next', function (e) {
        e.preventDefault(); e.stopPropagation();
        const $wrapper = $(this).closest('.wcb-gallery-wrapper');
        const $current = $wrapper.find('.wcb-thumbnail-image.active');
        let $next = $current.next('img');
        if ($next.length === 0) $next = $wrapper.find('img').first();
        switchImage($wrapper, $current, $next);
    });
    $('body').on('click', '.wcb-prev', function (e) {
        e.preventDefault(); e.stopPropagation();
        const $wrapper = $(this).closest('.wcb-gallery-wrapper');
        const $current = $wrapper.find('.wcb-thumbnail-image.active');
        let $prev = $current.prev('img');
        if ($prev.length === 0) $prev = $wrapper.find('img').last();
        switchImage($wrapper, $current, $prev);
    });
    function switchImage($wrapper, $current, $target) {
        $current.removeClass('active'); $target.addClass('active');
        const total = $wrapper.find('img').length;
        const realIdx = $wrapper.find('img').index($target) + 1;
        $wrapper.find('.wcb-gallery-counter').text(realIdx + '/' + total);
    }

    // 9. LIGHTBOX
    window.openLightbox = function (imageUrl, caption) {
        const $lightbox = $('#wcb-lightbox');
        $lightbox.find('.wcb-lightbox-image').attr('src', imageUrl);
        $lightbox.find('.wcb-lightbox-caption').text(caption);
        $lightbox.css('display', 'flex').hide().fadeIn(300).css('opacity', '1').addClass("show");
        $('body').css('overflow', 'hidden');
    };
    window.closeLightbox = function () {
        const $lightbox = $('#wcb-lightbox');
        $lightbox.fadeOut(300, function () { $(this).css('display', 'none').removeClass("show"); });
        $('body').css('overflow', '');
        setTimeout(() => { $lightbox.find('.wcb-lightbox-image').attr('src', ''); }, 300);
    };
    function initLightbox() {
        $bundleForm.off('click', '.wcb-thumbnail-image');
        $bundleForm.on('click', '.wcb-thumbnail-image', function (e) {
            e.preventDefault(); e.stopPropagation();
            if ($(this).hasClass('active')) {
                const $image = $(this);
                const fullUrl = $image.data('full-image') || $image.attr('src');
                window.openLightbox(fullUrl, $image.closest('.wcb-product-item').find('.wcb-product-name').text());
            }
        });
        $(document).off('click.wcb-lb').on('click.wcb-lb', '.wcb-lightbox-close, .wcb-lightbox', function (e) {
            if (e.target === this || $(e.target).hasClass('wcb-lightbox-close')) window.closeLightbox();
        });
        $(document).off('keyup.wcb-lb').on('keyup.wcb-lb', function (e) { if (e.key === 'Escape') window.closeLightbox(); });
        $(document).off('click.wcb-lb-img').on('click.wcb-lb-img', '.wcb-lightbox-image', function (e) { e.stopPropagation(); });

        // 10. MODALE DESCRIZIONE COMPLETA PRODOTTO
        $bundleForm.off('click.wcb-desc', '.wcb-open-desc-modal').on('click.wcb-desc', '.wcb-open-desc-modal', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const $item = $btn.closest('.wcb-product-item');
            const $tpl = $item.find('.wcb-full-description-template');
            if ($tpl.length === 0) return false;
            const title = $tpl.attr('data-product-title') || $item.find('.wcb-product-name').text().trim();
            const html = $tpl.html();
            const modalEl = document.getElementById('wcb-desc-modal');
            if (!modalEl) return false;
            $(modalEl).find('.wcb-desc-modal-title').text(title);
            $(modalEl).find('.wcb-desc-modal-body').html(html);
            if (typeof modalEl.showModal === 'function') {
                if (!modalEl.open) modalEl.showModal();
            } else {
                $(modalEl).attr('open', 'open').addClass('show');
            }
            return false;
        });

        $(document).off('click.wcb-desc-close').on('click.wcb-desc-close', '.wcb-desc-modal-close, #wcb-desc-modal', function (e) {
            const modalEl = document.getElementById('wcb-desc-modal');
            if (!modalEl) return;
            if (e.target === modalEl || $(e.target).closest('.wcb-desc-modal-close').length > 0) {
                if (typeof modalEl.close === 'function' && modalEl.open) {
                    modalEl.close();
                } else {
                    $(modalEl).removeAttr('open').removeClass('show');
                }
            }
        });
    }
}); // END READY
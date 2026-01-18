/*
 * asset/frontend.js - Versione 2.3.6
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

jQuery(document).ready(function($) {
    const $bundleForm = $('.wcb-bundle-form');
    
    // =========================================================================
    // 1. SMART SELECT (Auto-selezione)
    // =========================================================================
    function initSmartSelect() {
        $('.wcb-bundle-group').each(function() {
            const $group = $(this);
            const isRequired = $group.data('is-required');
            const mode = $group.data('selection-mode');
            
            if (isRequired && (mode === 'single' || mode === 'multiple')) {
                const $inputs = $group.find('input[type="radio"], input[type="checkbox"]');
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
    // 2. GESTIONE CARRELLO (Visual Grouping & Alerts)
    // =========================================================================
    function groupCartItems() {
        if ($('table.cart, .shop_table.cart').length === 0) return;

        $('tr.cart_item').removeClass('wcb-group-start wcb-group-middle wcb-group-end wcb-group-single');
        
        let prevBundleId = null;
        let $rows = $('tr.cart_item');
        
        $rows.each(function(index) {
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

                if (isStart && isEnd) {
                    $row.addClass('wcb-group-single');
                } else if (isStart) {
                    $row.addClass('wcb-group-start');
                } else if (isEnd) {
                    $row.addClass('wcb-group-end');
                } else {
                    $row.addClass('wcb-group-middle');
                }
            }
            prevBundleId = currentBundleId;
        });
    }

    groupCartItems();
    $(document.body).on('updated_cart_totals', groupCartItems);

    $(document.body).on('click', '.remove', function(e) {
        const $row = $(this).closest('tr.cart_item');
        const $info = $row.find('.wcb-cart-bundle-info').length ? $row.find('.wcb-cart-bundle-info') : $(this).closest('.cart_item').find('.wcb-cart-bundle-info');
        
        if ($info.length > 0) {
            if (!confirm(wcb_params.i18n.confirm_remove_bundle)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        }
    });

    if ($bundleForm.length === 0) return;

    const $addToCartButton = $bundleForm.find('.single_add_to_cart_button');
    const $messagesContainer = $('#wcb-ajax-messages');

    // =========================================================================
    // 3. LOGICA AGGIUNTA AL CARRELLO (AJAX)
    // =========================================================================
    $bundleForm.on('submit', function(e) {
        e.preventDefault();
        if ($addToCartButton.is(':disabled')) return;

        $addToCartButton.prop('disabled', true).text('Elaborazione...');
        $messagesContainer.slideUp().empty();

        $.ajax({
            type: 'POST',
            url: wcb_params.ajax_url,
            data: {
                action: 'wcb_add_bundle_to_cart',
                security: wcb_params.nonce,
                form_data: $bundleForm.serialize()
            },
            success: function(response) {
                if (response.success) {
                    $messagesContainer.html('<div class="woocommerce-message">' + response.data.message + '</div>').slideDown();
                    setTimeout(function() {
                        window.location.href = response.data.cart_url;
                    }, 1000);
                } else {
                    let errorsHtml = '';
                    response.data.messages.forEach(function(msg) {
                        errorsHtml += '<li>' + msg + '</li>';
                    });
                    $messagesContainer.html('<ul class="woocommerce-error" role="alert">' + errorsHtml + '</ul>').slideDown();
                    $addToCartButton.prop('disabled', false).text($addToCartButton.data('original-text'));
                }
            },
            error: function() {
                $messagesContainer.html('<ul class="woocommerce-error" role="alert"><li>' + 'Si è verificato un errore critico. Riprova.' + '</li></ul>').slideDown();
                $addToCartButton.prop('disabled', false).text($addToCartButton.data('original-text'));
            }
        });
    });

    // =========================================================================
    // 4. FUNZIONI DI VALIDAZIONE (CORE 2.3.0 MERGED)
    // =========================================================================
    function isPersonalizationComplete($productItem) {
        if (!$productItem.data('personalization-required')) {
            return true; 
        }
        let isComplete = true;
        const $requiredInputs = $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input[data-required="true"], .wcb-variation-set .wcb-personalization-input[data-required="true"]');
        
        $requiredInputs.each(function() {
            if ($(this).val().trim() === '') {
                isComplete = false;
                $(this).addClass('wcb-input-error');
            } else {
                $(this).removeClass('wcb-input-error');
            }
        });
        return isComplete;
    }

    function areVariationsComplete($productItem) {
        if ($productItem.find('.wcb-variation-container').length === 0 && $productItem.find('.wcb-variation-fields-template').length === 0) {
            return true;
        }
        let isComplete = true;
        $productItem.find('.wcb-variation-container:visible select.wcb-variation-select, .wcb-variation-sets-container .wcb-variation-set select.wcb-variation-select').each(function() {
            if ($(this).val() === '') isComplete = false;
        });
        return isComplete;
    }

    function validateGroup($group) {
        const mode = $group.data('selection-mode');
        let isValid = false;

        switch (mode) {
            case 'single': {
                const $radio = $group.find('input[type="radio"]:checked');
                if ($radio.length > 0) {
                    const $productItem = $radio.closest('.wcb-product-item');
                    isValid = areVariationsComplete($productItem) && isPersonalizationComplete($productItem);
                } else {
                    if (!$group.data('is-required')) isValid = true;
                }
                break;
            }
            case 'multiple': {
                const min = $group.data('min-qty');
                const max = $group.data('max-qty');
                const $checkboxes = $group.find('input[type="checkbox"]:checked');
                
                // Validazione Quantità Gruppo
                let groupQtyValid = ($checkboxes.length >= min && (max === 0 || $checkboxes.length <= max));
                
                if (groupQtyValid) {
                    let allItemsValid = true;
                    $checkboxes.each(function() {
                        const $productItem = $(this).closest('.wcb-product-item');
                        if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) {
                            allItemsValid = false;
                            return false;
                        }
                    });
                    isValid = allItemsValid;
                }
                if ($checkboxes.length === 0 && !$group.data('is-required')) isValid = true;
                break;
            }
            case 'quantity':
            case 'multiple_quantity': {
                const min = (mode === 'quantity') ? $group.data('total-qty') : $group.data('min-qty');
                const max = (mode === 'quantity') ? $group.data('total-qty') : $group.data('max-qty');
                
                let currentTotal = 0;
                let allItemsValid = true;
                
                $group.find('.wcb-quantity-input').each(function() {
                    const $input = $(this);
                    const qty = parseInt($input.val()) || 0;
                    
                    // --- NUOVA LOGICA v2.3.0: Validazione Min/Step per Item ---
                    const itemMin = parseInt($input.closest('.wcb-product-item').attr('data-item-min')) || 1;
                    const itemStep = parseInt($input.closest('.wcb-product-item').attr('data-item-step')) || 1;

                    currentTotal += qty;
                    
                    if (qty > 0) {
                        // 1. Validazione Regole Item
                        if (qty < itemMin) allItemsValid = false; // Non rispetta il minimo del singolo prodotto
                        if (qty % itemStep !== 0) allItemsValid = false; // Non rispetta lo step (multipli)

                        // 2. Validazione Varianti/Personalizzazioni
                        const $productItem = $(this).closest('.wcb-product-item');
                        if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) {
                            allItemsValid = false;
                        }
                    }
                });

                // Validazione Finale Gruppo
                if (currentTotal === 0 && !$group.data('is-required')) {
                    isValid = true;
                } else if (allItemsValid && currentTotal >= min && (max === 0 || currentTotal <= max)) {
                    isValid = true;
                    // Se modalità Quantity (Fixed), deve essere esatto
                    if (mode === 'quantity' && currentTotal !== max) isValid = false;
                }
                break;
            }
        }
        return isValid;
    }

    function getCurrentQty($group) {
        let qty = 0;
        const mode = $group.data('selection-mode');
        
        if (mode === 'single' || mode === 'multiple') {
            qty = $group.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length;
        } else {
            $group.find('.wcb-quantity-input').each(function() {
                qty += parseInt($(this).val()) || 0;
            });
        }
        return qty;
    }

    // =========================================================================
    // 5. LOGICA DI CALCOLO PREZZO DINAMICO
    // =========================================================================
    function getVariationPrice(productId, attributes, callback) {
        $.ajax({
            url: wcb_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wcb_get_variation_price',
                product_id: productId,
                attributes: attributes,
                security: wcb_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.price);
                } else {
                    callback(0);
                }
            },
            error: function() { callback(0); }
        });
    }

    function parsePriceFromElement($element) {
        if (!$element || $element.length === 0) return 0;
        const text = $element.find('ins').length > 0 ? $element.find('ins').text() : $element.text();
        const clean = text.replace(wcb_params.pricing.currency_symbol, '')
                          .replace(wcb_params.pricing.thousand_separator, '')
                          .replace(wcb_params.pricing.decimal_separator, '.').trim();
        return parseFloat(clean) || 0;
    }

    function finalizePriceCalculation(totalPrice) {
        const pricing = wcb_params.pricing;
        let finalPrice = totalPrice;

        if (pricing.type === 'calculated') {
            if (pricing.discount_percentage > 0) finalPrice *= (1 - (pricing.discount_percentage / 100));
            if (pricing.discount_amount > 0) finalPrice = Math.max(0, finalPrice - pricing.discount_amount);
        } else if (pricing.type === 'fixed') {
            finalPrice = parseFloat(pricing.fixed_price);
        }
        
        let formattedPrice = finalPrice.toFixed(2).replace('.', wcb_params.pricing.decimal_separator);
        if (wcb_params.pricing.currency_position.includes('left')) formattedPrice = wcb_params.pricing.currency_symbol + formattedPrice;
        else formattedPrice = formattedPrice + wcb_params.pricing.currency_symbol;

        $('.wcb-price-value').html(formattedPrice);
    }

    function updateBundlePrice() {
        if (typeof wcb_params.pricing === 'undefined') return;

        let totalPrice = 0;
        let pendingRequests = 0;
        const itemsToPrice = [];

        $('.wcb-product-item').each(function() {
            const $item = $(this);
            const $radio = $item.find('input[type="radio"]');
            const $checkbox = $item.find('input[type="checkbox"]');
            const $quantityInput = $item.find('.wcb-quantity-input');
            let quantity = 0;

            if ($radio.length > 0 && $radio.is(':checked')) quantity = 1;
            else if ($checkbox.length > 0 && $checkbox.is(':checked')) quantity = 1;
            else if ($quantityInput.length > 0) quantity = parseInt($quantityInput.val(), 10) || 0;

            if (quantity > 0) itemsToPrice.push({ item: $item, quantity: quantity });
        });
        
        if (itemsToPrice.length === 0) { finalizePriceCalculation(0); return; }
        
        itemsToPrice.forEach(function(bundleItem) {
            const { item, quantity } = bundleItem;
            const $item = item;
            const productId = $item.data('product-id');
            
            // --- NUOVA LOGICA v2.3.0: Price Override ---
            const overridePrice = $item.data('price-override'); 

            if (overridePrice !== undefined && overridePrice !== null && overridePrice !== '') {
                totalPrice += parseFloat(overridePrice) * quantity;
                return; // Se c'è override, usa quello e salta il resto
            }

            const basePrice = parsePriceFromElement($item.find('.wcb-product-price'));
            const isVariable = $item.find('.wcb-variation-container').length > 0 || $item.find('.wcb-variation-fields-template').length > 0;

            if (!isVariable) {
                totalPrice += basePrice * quantity;
            } else {
                const $variationContainers = $item.find('.wcb-variation-container:visible, .wcb-variation-set');
                
                if ($variationContainers.length === 0) { 
                } else if ($variationContainers.length === 1 && !$item.find('.wcb-variation-set').length) { 
                    const selectedAttributes = {};
                    let allAttributesSelected = true;
                    $variationContainers.find('.wcb-variation-select').each(function() {
                        const attrName = $(this).data('attribute-name');
                        const attrValue = $(this).val();
                        if (attrValue) selectedAttributes[attrName] = attrValue;
                        else allAttributesSelected = false;
                    });

                    if (allAttributesSelected) {
                        pendingRequests++;
                        getVariationPrice(productId, selectedAttributes, function(variationPrice) {
                            totalPrice += variationPrice * quantity;
                            pendingRequests--;
                            if (pendingRequests === 0) finalizePriceCalculation(totalPrice);
                        });
                    }
                } else { 
                    $variationContainers.each(function() {
                        const $set = $(this);
                        const selectedAttributes = {};
                        let allAttributesSelectedInSet = true;
                        $set.find('.wcb-variation-select').each(function() {
                            const attrName = $(this).data('attribute-name');
                            const attrValue = $(this).val();
                            if (attrValue) selectedAttributes[attrName] = attrValue;
                            else allAttributesSelectedInSet = false;
                        });

                        if (allAttributesSelectedInSet) {
                            pendingRequests++;
                            getVariationPrice(productId, selectedAttributes, function(variationPrice) {
                                totalPrice += variationPrice; 
                                pendingRequests--;
                                if (pendingRequests === 0) finalizePriceCalculation(totalPrice);
                            });
                        }
                    });
                }
            }
        });

        if (pendingRequests === 0) finalizePriceCalculation(totalPrice);
    }

    function updateBundleState() {
        let isBundleComplete = true;
        $('.wcb-bundle-group').each(function() {
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
        
        $addToCartButton.prop('disabled', !isBundleComplete);
        $('.wcb-mobile-submit-btn').prop('disabled', !isBundleComplete);
        updateBundlePrice();
    }

    // =========================================================================
    // 6. EVENT LISTENERS
    // =========================================================================
    $bundleForm.on('change', '.wcb-variation-select', function() {
        const $changedSelect = $(this);
        const $productItem = $changedSelect.closest('.wcb-product-item');
        const variationData = $productItem.data('variation-data');
        if (!variationData) { updateBundleState(); return; }

        const $selectContainer = $changedSelect.closest('.wcb-variation-container, .wcb-variation-set');
        const $allSelects = $selectContainer.find('.wcb-variation-select');
        let currentSelection = {};
        $allSelects.each(function() {
            const val = $(this).val();
            if (val) currentSelection[$(this).data('attribute-name')] = val;
        });

        $allSelects.not($changedSelect).each(function() {
            const $currentSelect = $(this);
            const attributeName = $currentSelect.data('attribute-name');
            $currentSelect.find('option:gt(0)').prop('disabled', true);
            const possibleOptions = new Set();
            variationData.forEach(function(variation) {
                let isMatch = true;
                for (const attr in currentSelection) {
                    if (attr !== attributeName && variation[attr] && variation[attr] !== currentSelection[attr]) {
                        isMatch = false; break;
                    }
                }
                if (isMatch && variation[attributeName]) possibleOptions.add(variation[attributeName]);
            });
            $currentSelect.find('option').each(function(){
                if(possibleOptions.has($(this).val())) $(this).prop('disabled', false);
            });
            if ($currentSelect.find('option:selected').is(':disabled')) $currentSelect.val('');
        });
        updateBundleState();
    });

    $bundleForm.on('change', 'input[type="radio"], input[type="checkbox"]', function() {
        const $input = $(this);
        const $productItem = $input.closest('.wcb-product-item');
        const $variationContainer = $productItem.find('.wcb-variation-container');
        const $personalizationContainer = $productItem.find('.wcb-personalization-field-container');
        
        if ($input.is('[type="radio"]')) {
            const $otherProductItems = $input.closest('.wcb-group-products').find('.wcb-product-item').not($productItem);
            $otherProductItems.find('.wcb-variation-container').slideUp().find('select').prop('disabled', true);
            $otherProductItems.find('.wcb-personalization-field-container').slideUp();
        }

        if ($input.is(':checked')) {
            $variationContainer.slideDown().find('select').prop('disabled', false).first().trigger('change');
            $personalizationContainer.slideDown();
        } else {
            $variationContainer.slideUp().find('select').prop('disabled', true);
            $personalizationContainer.slideUp();
        }
        updateBundleState();
    });

    // --- AGGIORNATO EVENTO QUANTITÀ ---
    $bundleForm.on('change input', '.wcb-quantity-input', function(e) {
        const $input = $(this);
        const qty = parseInt($input.val()) || 0;
        
        // Logica Auto-correzione (opzionale, solo su 'change' per non disturbare la digitazione)
        // Se si preferisce non auto-correggere ma solo invalidare, rimuovere questo blocco.
        // Qui manteniamo una logica soft: se l'utente lascia il campo (change) e il valore è invalido,
        // NON lo correggiamo forzatamente ma lasciamo che la validazione faccia il suo corso (bordo rosso/pulsante disabilitato).
        // Tuttavia, per coerenza con la UX standard, se uno digita < 0, mettiamo 0.
        if (e.type === 'change' && qty < 0) {
            $input.val(0);
        }

        const $productItem = $input.closest('.wcb-product-item');
        const $setsContainer = $productItem.find('.wcb-variation-sets-container');
        const $variationTemplate = $productItem.find('.wcb-variation-fields-template');
        const $personalizationTemplate = $productItem.find('.wcb-personalization-field-template');

        // Generazione campi dinamici
        // NOTA: Generiamo i campi anche se la quantità non rispetta il min/step, 
        // così l'utente vede cosa sta succedendo. La validazione finale bloccherà l'acquisto.
        $setsContainer.empty();
        if (qty > 0) {
            for (let i = 0; i < qty; i++) {
                const $newSet = $('<div class="wcb-variation-set"><h5>' + 'Pezzo ' + (i + 1) + '</h5></div>');
                if ($variationTemplate.length > 0) {
                    const html = $variationTemplate.html().replace(/__INDEX__/g, i);
                    $newSet.append($(html).find('select').prop('disabled', false).end());
                }
                if ($personalizationTemplate.length > 0) {
                    $newSet.append($personalizationTemplate.html().replace(/__INDEX__/g, i));
                }
                $setsContainer.append($newSet);
            }
            $setsContainer.slideDown();
        } else {
            $setsContainer.slideUp();
        }
        updateBundleState();
    });
    
    $bundleForm.on('input', '.wcb-personalization-input.wcb-input-error', function() {
        if ($(this).val().trim() !== '') {
            $(this).removeClass('wcb-input-error');
            updateBundleState();
        }
    });

    $addToCartButton.data('original-text', $addToCartButton.text());

    // =========================================================================
    // 7. LOGICA MODIFICA BUNDLE
    // =========================================================================
    function initEditMode() {
        if (typeof wcb_params.edit_mode === 'undefined' || !wcb_params.edit_mode.active) {
            updateBundleState(); 
            return;
        }
        
        const config = wcb_params.edit_mode.config;
        const bundleId = wcb_params.edit_mode.bundle_id;

        if (!$('input[name="wcb_update_bundle_id"]').length) {
            $bundleForm.append('<input type="hidden" name="wcb_update_bundle_id" value="' + bundleId + '">');
        }
        
        $addToCartButton.text(wcb_params.i18n.update_bundle_btn);
        $addToCartButton.data('original-text', wcb_params.i18n.update_bundle_btn);

        const fillPersonalization = ($container, data) => {
            if (!data || !Array.isArray(data)) return;
            data.forEach((field, fIndex) => {
                $container.find('.wcb-personalization-input').eq(fIndex).val(field.value);
            });
        };

        function waitForAndPopulate($container, attributes, attempt = 1) {
            if (attempt > 15) return;
            const $selects = $container.find('select');
            if ($selects.length === 0 && Object.keys(attributes).length > 0) {
                setTimeout(() => waitForAndPopulate($container, attributes, attempt + 1), 200);
                return;
            }
            $.each(attributes, function(key, value) {
                let attrName = key.startsWith('attribute_') ? key : 'attribute_' + key;
                let $select = $container.find('select[data-attribute-name="' + attrName + '"]');
                if (!$select.length) $select = $container.find('select[name*="[' + attrName + ']"]');
                if ($select.length) {
                    $select.prop('disabled', false); 
                    $select.val(value).trigger('change');
                }
            });
        }

        $.each(config, function(groupIndex, selections) {
            const $group = $('.wcb-bundle-group[data-group-index="' + groupIndex + '"]');
            if (!$group.length) return;
            
            const itemCounts = {};
            selections.forEach(item => {
                const key = (item.is_variation && item.parent_id) ? item.parent_id : item.item_id;
                if (!itemCounts[key]) itemCounts[key] = [];
                itemCounts[key].push(item);
            });

            $.each(itemCounts, function(prodId, items) {
                let $productItem = $group.find('.wcb-product-item[data-product-id="' + prodId + '"]');
                if (!$productItem.length) {
                    const $input = $group.find('input[value="' + prodId + '"]');
                    if($input.length) $productItem = $input.closest('.wcb-product-item');
                }

                if ($productItem.length) {
                    const qty = items.length;

                    const $checkbox = $productItem.find('input[type="checkbox"], input[type="radio"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true).trigger('change');
                        
                        if (items[0].is_variation) {
                            waitForAndPopulate($productItem.find('.wcb-variation-container'), items[0].attributes);
                        }
                        
                        if (items[0].personalization_data) {
                            setTimeout(() => {
                                fillPersonalization($productItem.find('.wcb-personalization-field-container'), items[0].personalization_data);
                            }, 300);
                        }
                    }
                    
                    const $qtyInput = $productItem.find('.wcb-quantity-input');
                    if ($qtyInput.length) {
                        setTimeout(function() {
                            $qtyInput.val(qty).trigger('change').trigger('input'); 
                        }, 100);

                        const populateSets = (retry = 0) => {
                            const $sets = $productItem.find('.wcb-variation-set');
                            if ($sets.length < qty && retry < 15) {
                                setTimeout(() => populateSets(retry + 1), 200);
                                return;
                            }
                            $sets.each(function(index) {
                                if (items[index]) {
                                    if (items[index].is_variation) {
                                        waitForAndPopulate($(this), items[index].attributes);
                                    }
                                    if (items[index].personalization_data) {
                                        fillPersonalization($(this), items[index].personalization_data);
                                    }
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
});

function openLightbox(imageUrl, caption) {
    const $lightbox = jQuery('#wcb-lightbox');
    $lightbox.find('.wcb-lightbox-image').attr('src', imageUrl);
    $lightbox.find('.wcb-lightbox-caption').text(caption);
    $lightbox.css('display', 'flex').hide().fadeIn(300).css('opacity', '1').addClass("show");
    jQuery('body').css('overflow', 'hidden');
}

function closeLightbox() {
    const $lightbox = jQuery('#wcb-lightbox');
    $lightbox.fadeOut(300, function() {
        jQuery(this).css('display', 'none').removeClass("show");
    });
    jQuery('body').css('overflow', '');
    setTimeout(() => {
        $lightbox.find('.wcb-lightbox-image').attr('src', '');
        $lightbox.find('.wcb-lightbox-caption').text('');
    }, 300);
}

function initLightbox() {
    const $bundleForm = jQuery('.wcb-bundle-form');
    if ($bundleForm.length === 0) return;
    
    $bundleForm.on('click', '.wcb-thumbnail-image', function(e) {
        e.preventDefault(); e.stopPropagation();
        const $image = jQuery(this);
        openLightbox($image.data('full-image'), $image.closest('.wcb-product-item').find('.wcb-product-name').text());
    });
    
    jQuery(document).on('click', '.wcb-lightbox-close, .wcb-lightbox', function(e) {
        if (e.target === this || jQuery(e.target).hasClass('wcb-lightbox-close')) closeLightbox();
    });
    
    jQuery(document).on('click', '.wcb-lightbox-image', function(e) { e.stopPropagation(); });
    jQuery(document).on('keyup', function(e) { if (e.key === 'Escape') closeLightbox(); });
}
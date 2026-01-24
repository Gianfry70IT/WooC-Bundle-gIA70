/*
 * asset/frontend.js - Versione 2.4.6 (Fix Price Logic)
 * Author: Gianfranco Greco con Codice Sorgente
*/

jQuery(document).ready(function($) {
    const $bundleForm = $('.wcb-bundle-form');

    // =========================================================================
    // 0. INIT CRITICO
    // =========================================================================
    $('.wcb-gallery-wrapper').each(function() {
        if ($(this).find('.wcb-thumbnail-image.active').length === 0) {
            $(this).find('.wcb-thumbnail-image').first().addClass('active');
        }
    });

    // =========================================================================
    // 1. SMART SELECT
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
    // 2. GESTIONE CARRELLO
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

    $(document.body).on('click', '.remove', function(e) {
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

    // =========================================================================
    // 3. LOGICA AGGIUNTA AL CARRELLO
    // =========================================================================
    $(document).on('click', '.wcb-mobile-submit-btn', function(e) {
        e.preventDefault();
        if (!$(this).is(':disabled') && !$(this).hasClass('disabled')) {
            $bundleForm.trigger('submit');
        }
    });

    $bundleForm.on('submit', function(e) {
        e.preventDefault();
        if ($addToCartButton.is(':disabled')) return;
        $addToCartButton.prop('disabled', true).text('Elaborazione...');
        $messagesContainer.slideUp().empty();

        $.ajax({
            type: 'POST',
            url: wcb_params.ajax_url,
            data: { action: 'wcb_add_bundle_to_cart', security: wcb_params.nonce, form_data: $bundleForm.serialize() },
            success: function(response) {
                if (response.success) {
                    $messagesContainer.html('<div class="woocommerce-message">' + response.data.message + '</div>').slideDown();
                    setTimeout(function() { window.location.href = response.data.cart_url; }, 1000);
                } else {
                    let errorsHtml = '';
                    response.data.messages.forEach(function(msg) { errorsHtml += '<li>' + msg + '</li>'; });
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
    // 4. FUNZIONI DI VALIDAZIONE
    // =========================================================================
    function isPersonalizationComplete($productItem) {
        if (!$productItem.data('personalization-required')) return true; 
        let isComplete = true;
        $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input[data-required="true"], .wcb-variation-set .wcb-personalization-input[data-required="true"]').each(function() {
            if ($(this).val().trim() === '') { isComplete = false; $(this).addClass('wcb-input-error'); }
            else { $(this).removeClass('wcb-input-error'); }
        });
        return isComplete;
    }

    function areVariationsComplete($productItem) {
        if ($productItem.find('.wcb-variation-container').length === 0 && $productItem.find('.wcb-variation-fields-template').length === 0) return true;
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
                } else if (!$group.data('is-required')) isValid = true;
                break;
            }
            case 'multiple': {
                const min = $group.data('min-qty');
                const max = $group.data('max-qty');
                const $checkboxes = $group.find('input[type="checkbox"]:checked');
                let groupQtyValid = ($checkboxes.length >= min && (max === 0 || $checkboxes.length <= max));
                if (groupQtyValid) {
                    let allItemsValid = true;
                    $checkboxes.each(function() {
                        const $productItem = $(this).closest('.wcb-product-item');
                        if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) { allItemsValid = false; return false; }
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
                    const itemMin = parseInt($input.closest('.wcb-product-item').attr('data-item-min')) || 1;
                    const itemStep = parseInt($input.closest('.wcb-product-item').attr('data-item-step')) || 1;
                    currentTotal += qty;
                    if (qty > 0) {
                        if (qty < itemMin || qty % itemStep !== 0) allItemsValid = false;
                        const $productItem = $(this).closest('.wcb-product-item');
                        if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) allItemsValid = false;
                    }
                });
                if (currentTotal === 0 && !$group.data('is-required')) isValid = true;
                else if (allItemsValid && currentTotal >= min && (max === 0 || currentTotal <= max)) {
                    isValid = true;
                    if (mode === 'quantity' && currentTotal !== max) isValid = false;
                }
                break;
            }
        }
        return isValid;
    }

    function getCurrentQty($group) {
        let qty = 0; const mode = $group.data('selection-mode');
        if (mode === 'single' || mode === 'multiple') qty = $group.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length;
        else $group.find('.wcb-quantity-input').each(function() { qty += parseInt($(this).val()) || 0; });
        return qty;
    }

    window.wcb_validateGroup = validateGroup; 
    window.wcb_updateBundleState = updateBundleState;

    // =========================================================================
    // 5. CALCOLO PREZZO (FIXED)
    // =========================================================================
    function getVariationPrice(productId, attributes, callback) {
        $.ajax({
            url: wcb_params.ajax_url, type: 'POST',
            data: { action: 'wcb_get_variation_price', product_id: productId, attributes: attributes, security: wcb_params.nonce },
            success: function(response) { if (response.success) callback(response.data.price); else callback(0); },
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
    
    // Helper per pulire stringhe prezzo provenienti da attributi data (che potrebbero avere virgole)
    function parseDataPrice(value) {
        if (value === undefined || value === null || value === '') return 0;
        // Se è già un numero, ritornalo
        if (typeof value === 'number') return value;
        // Sostituisci virgola con punto e rimuovi simboli non numerici (eccetto punto e meno)
        const clean = value.toString().replace(',', '.').replace(/[^0-9.-]/g, '');
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

    // --- FUNZIONE CRITICA AGGIORNATA ---
    function updateBundlePrice() {
        if (typeof wcb_params.pricing === 'undefined') return;
        let bundleTotal = 0;
        let pendingRequests = 0;

        $('.wcb-bundle-group').each(function() {
            const $group = $(this);
            // FIX: Usa parseDataPrice per gestire virgole e formati valuta
            const groupOverride = parseDataPrice($group.data('group-price-override')); 
            
            let groupItemTotal = 0;
            let groupSelectedQty = 0; // FIX: Contatore quantità reale

            $group.find('.wcb-product-item').each(function() {
                const $item = $(this);
                const $radio = $item.find('input[type="radio"]');
                const $checkbox = $item.find('input[type="checkbox"]');
                const $quantityInput = $item.find('.wcb-quantity-input');
                let quantity = 0;

                if ($radio.length > 0 && $radio.is(':checked')) quantity = 1;
                else if ($checkbox.length > 0 && $checkbox.is(':checked')) quantity = 1;
                else if ($quantityInput.length > 0) quantity = parseInt($quantityInput.val(), 10) || 0;

                if (quantity > 0) {
                    groupSelectedQty += quantity; // Incrementa il contatore "prodotti scelti"
                    
                    const itemOverride = $item.data('price-override'); 
                    let price = 0;
                    if (itemOverride !== undefined && itemOverride !== null && itemOverride !== '') {
                         price = parseFloat(itemOverride);
                    } else {
                         price = parsePriceFromElement($item.find('.wcb-product-price'));
                    }
                    
                    groupItemTotal += price * quantity;
                }
            });

            // LOGICA OVERRIDE GRUPPO CORRETTA:
            // Se c'è un override valido E abbiamo selezionato almeno un prodotto (quantità > 0)
            // Allora il totale del gruppo è l'override.
            // Ignoriamo groupItemTotal perché potrebbe essere 0 se i prezzi sono nascosti.
            if (groupOverride > 0 && groupSelectedQty > 0) {
                bundleTotal += groupOverride; 
            } else {
                bundleTotal += groupItemTotal;
            }
        });
        
        if (pendingRequests === 0) finalizePriceCalculation(bundleTotal);
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
    // 6. EVENTI FORM
    // =========================================================================
    $bundleForm.on('change', '.wcb-variation-select', function() {
        const $changedSelect = $(this);
        const $productItem = $changedSelect.closest('.wcb-product-item');
        const variationData = $productItem.data('variation-data');
        if (!variationData) { updateBundleState(); return; }
        const $selectContainer = $changedSelect.closest('.wcb-variation-container, .wcb-variation-set');
        const $allSelects = $selectContainer.find('.wcb-variation-select');
        let currentSelection = {};
        $allSelects.each(function() { const val = $(this).val(); if (val) currentSelection[$(this).data('attribute-name')] = val; });
        $allSelects.not($changedSelect).each(function() {
            const $currentSelect = $(this);
            const attributeName = $currentSelect.data('attribute-name');
            $currentSelect.find('option:gt(0)').prop('disabled', true).hide();
            const possibleOptions = new Set();
            variationData.forEach(function(variation) {
                let isMatch = true;
                for (const attr in currentSelection) { if (attr !== attributeName && variation[attr] && variation[attr] !== currentSelection[attr]) { isMatch = false; break; } }
                if (isMatch && variation[attributeName]) possibleOptions.add(variation[attributeName]);
            });
            $currentSelect.find('option').each(function(){ if(possibleOptions.has($(this).val())) { $(this).prop('disabled', false).show(); } });
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
        if ($input.is(':checked')) { $variationContainer.slideDown().find('select').prop('disabled', false).first().trigger('change'); $personalizationContainer.slideDown(); }
        else { $variationContainer.slideUp().find('select').prop('disabled', true); $personalizationContainer.slideUp(); }
        updateBundleState();
    });

    $bundleForm.on('change input', '.wcb-quantity-input', function(e) {
        const $input = $(this);
        const qty = parseInt($input.val()) || 0;
        if (e.type === 'change' && qty < 0) $input.val(0);
        const $productItem = $input.closest('.wcb-product-item');
        const $setsContainer = $productItem.find('.wcb-variation-sets-container');
        const $variationTemplate = $productItem.find('.wcb-variation-fields-template');
        const $personalizationTemplate = $productItem.find('.wcb-personalization-field-template');
        $setsContainer.empty();
        if (qty > 0) {
            for (let i = 0; i < qty; i++) {
                const $newSet = $('<div class="wcb-variation-set"><h5>' + 'Pezzo ' + (i + 1) + '</h5></div>');
                if ($variationTemplate.length > 0) {
                    const html = $variationTemplate.html().replace(/__INDEX__/g, i);
                    $newSet.append($(html).find('select').prop('disabled', false).end());
                }
                if ($personalizationTemplate.length > 0) $newSet.append($personalizationTemplate.html().replace(/__INDEX__/g, i));
                $setsContainer.append($newSet);
            }
            $setsContainer.slideDown();
        } else { $setsContainer.slideUp(); }
        updateBundleState();
    });
    
    $bundleForm.on('input', '.wcb-personalization-input.wcb-input-error', function() {
        if ($(this).val().trim() !== '') { $(this).removeClass('wcb-input-error'); updateBundleState(); }
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
            $.each(attributes, function(key, value) {
                let attrName = key.startsWith('attribute_') ? key : 'attribute_' + key;
                let $select = $container.find('select[data-attribute-name="' + attrName + '"]');
                if (!$select.length) $select = $container.find('select[name*="[' + attrName + ']"]');
                if ($select.length) { $select.prop('disabled', false); $select.val(value).trigger('change'); }
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
                if (!$productItem.length) { const $input = $group.find('input[value="' + prodId + '"]'); if($input.length) $productItem = $input.closest('.wcb-product-item'); }
                if ($productItem.length) {
                    const qty = items.length;
                    const $checkbox = $productItem.find('input[type="checkbox"], input[type="radio"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true).trigger('change');
                        if (items[0].is_variation) waitForAndPopulate($productItem.find('.wcb-variation-container'), items[0].attributes);
                        if (items[0].personalization_data) setTimeout(() => { fillPersonalization($productItem.find('.wcb-personalization-field-container'), items[0].personalization_data); }, 300);
                    }
                    const $qtyInput = $productItem.find('.wcb-quantity-input');
                    if ($qtyInput.length) {
                        setTimeout(function() { $qtyInput.val(qty).trigger('change').trigger('input'); }, 100);
                        const populateSets = (retry = 0) => {
                            const $sets = $productItem.find('.wcb-variation-set');
                            if ($sets.length < qty && retry < 15) { setTimeout(() => populateSets(retry + 1), 200); return; }
                            $sets.each(function(index) {
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
    $('body').on('click', '.wcb-next', function(e) {
        e.preventDefault(); e.stopPropagation();
        const $wrapper = $(this).closest('.wcb-gallery-wrapper');
        const $current = $wrapper.find('.wcb-thumbnail-image.active');
        let $next = $current.next('img');
        if ($next.length === 0) $next = $wrapper.find('img').first();
        switchImage($wrapper, $current, $next);
    });
    $('body').on('click', '.wcb-prev', function(e) {
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
    window.openLightbox = function(imageUrl, caption) {
        const $lightbox = $('#wcb-lightbox');
        $lightbox.find('.wcb-lightbox-image').attr('src', imageUrl);
        $lightbox.find('.wcb-lightbox-caption').text(caption);
        $lightbox.css('display', 'flex').hide().fadeIn(300).css('opacity', '1').addClass("show");
        $('body').css('overflow', 'hidden');
    };
    window.closeLightbox = function() {
        const $lightbox = $('#wcb-lightbox');
        $lightbox.fadeOut(300, function() { $(this).css('display', 'none').removeClass("show"); });
        $('body').css('overflow', '');
        setTimeout(() => { $lightbox.find('.wcb-lightbox-image').attr('src', ''); }, 300);
    };
    function initLightbox() {
        $bundleForm.off('click', '.wcb-thumbnail-image');
        $bundleForm.on('click', '.wcb-thumbnail-image', function(e) {
            e.preventDefault(); e.stopPropagation();
            if ($(this).hasClass('active')) {
                const $image = $(this);
                const fullUrl = $image.data('full-image') || $image.attr('src');
                window.openLightbox(fullUrl, $image.closest('.wcb-product-item').find('.wcb-product-name').text());
            }
        });
        $(document).off('click.wcb-lb').on('click.wcb-lb', '.wcb-lightbox-close, .wcb-lightbox', function(e) {
            if (e.target === this || $(e.target).hasClass('wcb-lightbox-close')) window.closeLightbox();
        });
        $(document).off('keyup.wcb-lb').on('keyup.wcb-lb', function(e) { if (e.key === 'Escape') window.closeLightbox(); });
        $(document).off('click.wcb-lb-img').on('click.wcb-lb-img', '.wcb-lightbox-image', function(e) { e.stopPropagation(); });
    }
}); // END READY
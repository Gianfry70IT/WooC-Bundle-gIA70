// Plugin Name:       WooC Bundle gIA70
// Description:       Un framework per creare prodotti bundle personalizzabili, unendo un'amministrazione stabile con un frontend funzionale.
// Version:           0.8.2
// Author:            Gianfranco Greco con Codice Sorgente
// Copyright (c) 2025 Gianfranco Greco
// Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
// Text Domain:       wcb-framework

jQuery(document).ready(function($) {
    const $bundleForm = $('.wcb-bundle-form');
    if ($bundleForm.length === 0) return;

    const $addToCartButton = $bundleForm.find('.single_add_to_cart_button');
    const $messagesContainer = $('#wcb-ajax-messages');

    $bundleForm.on('submit', function(e) {
        e.preventDefault();
        if ($addToCartButton.is(':disabled')) return;

        $addToCartButton.prop('disabled', true).text('Aggiunta...');
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
                    console.error("Errore di validazione dal backend:", response.data.messages);
                    
                    let errorsHtml = '';
                    response.data.messages.forEach(function(msg) {
                        if (msg.startsWith('DEBUG_DATA:')) {
                            try {
                                const debugData = JSON.parse(msg.substring(11));
                                errorsHtml += '<li><strong style="color: #c00;">[DEBUG]</strong> Problema con: <strong>' + debugData.product_name + '</strong>';
                                errorsHtml += '<ul>';
                                errorsHtml += '<li>Quantità richiesta: <strong>' + debugData.expected_qty + '</strong></li>';
                                errorsHtml += '<li>Set di varianti trovati: <strong>' + debugData.found_sets_count + '</strong></li>';
                                errorsHtml += '</ul></li>';
                                console.log('Dati ricevuti dal server per "' + debugData.product_name + '":', debugData.found_sets_data);
                            } catch (e) {
                                errorsHtml += '<li>' + msg + '</li>';
                            }
                        } else {
                            errorsHtml += '<li>' + msg + '</li>';
                        }
                    });
                    
                    $messagesContainer.html('<ul class="woocommerce-error" role="alert">' + errorsHtml + '</ul>').slideDown();
                    $addToCartButton.prop('disabled', false).text('Aggiungi al carrello');
                }
            },
            error: function() {
                $messagesContainer.html('<ul class="woocommerce-error" role="alert"><li>' + 'Si è verificato un errore critico. Riprova.' + '</li></ul>').slideDown();
                $addToCartButton.prop('disabled', false).text('Aggiungi al carrello');
            }
        });
    });

    function isPersonalizationComplete($productItem) {
        if (!$productItem.data('personalization-required')) {
            return true; // Non è obbligatorio, quindi è "completo"
        }

        let isComplete = true;
        
        // Validazione per single/multiple
        $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input').each(function() {
            if ($(this).val().trim() === '') {
                isComplete = false;
                $(this).addClass('wcb-input-error');
            } else {
                $(this).removeClass('wcb-input-error');
            }
        });

        // Validazione per quantity
        $productItem.find('.wcb-variation-set').each(function() {
            $(this).find('.wcb-personalization-input').each(function() {
                 if ($(this).val().trim() === '') {
                    isComplete = false;
                    $(this).addClass('wcb-input-error');
                } else {
                    $(this).removeClass('wcb-input-error');
                }
            });
        });

        return isComplete;
    }

    function areVariationsComplete($productItem) {
        if ($productItem.find('.wcb-variation-container').length === 0 && $productItem.find('.wcb-variation-fields-template').length === 0) {
            return true;
        }

        let isComplete = true;
        $productItem.find('.wcb-variation-container:visible select.wcb-variation-select').each(function() {
            if ($(this).val() === '') isComplete = false;
        });
        $productItem.find('.wcb-variation-sets-container .wcb-variation-set').each(function() {
            $(this).find('select.wcb-variation-select').each(function() {
                if ($(this).val() === '') isComplete = false;
            });
        });
        return isComplete;
    }

    function validateGroup($group) {
        const mode = $group.data('selection-mode');
        
        switch (mode) {
            case 'single': {
                const $radio = $group.find('input[type="radio"]:checked');
                if ($radio.length === 0) return false;
                const $productItem = $radio.closest('.wcb-product-item');
                return areVariationsComplete($productItem) && isPersonalizationComplete($productItem);
            }
            case 'multiple': {
                const min = $group.data('min-qty');
                const max = $group.data('max-qty');
                const $checkboxes = $group.find('input[type="checkbox"]:checked');
                if ($checkboxes.length < min || (max > 0 && $checkboxes.length > max)) return false;
                if ($checkboxes.length === 0 && min > 0) return false;
                
                let allOk = true;
                $checkboxes.each(function() {
                    const $productItem = $(this).closest('.wcb-product-item');
                    if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) {
                        allOk = false;
                        return false;
                    }
                });
                return allOk;
            }
            case 'quantity': {
                const totalQty = $group.data('total-qty');
                let currentTotal = 0;
                let allOk = true;
                
                $group.find('.wcb-quantity-input').each(function() {
                    const qty = parseInt($(this).val()) || 0;
                    currentTotal += qty;
                    if (qty > 0) {
                        const $productItem = $(this).closest('.wcb-product-item');
                         if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) {
                            allOk = false;
                        }
                    }
                });

                if (currentTotal !== totalQty) return false;
                return allOk;
            }
        }
        return false;
    }

    function updateBundleState() {
        let isBundleComplete = true;
        $('.wcb-bundle-group').each(function() {
            const $group = $(this);
            const isRequired = $group.data('is-required');
            const isGroupComplete = validateGroup($group);

            if (isGroupComplete) {
                $group.removeClass('wcb-group-incomplete').addClass('wcb-group-complete');
            } else {
                $group.removeClass('wcb-group-complete');
                if (isRequired) {
                    $group.addClass('wcb-group-incomplete');
                    isBundleComplete = false;
                }
            }
        });
        $addToCartButton.prop('disabled', !isBundleComplete);
    }
    
    $bundleForm.on('change', '.wcb-variation-select', function() {
        const $changedSelect = $(this);
        const $productItem = $changedSelect.closest('.wcb-product-item');
        const variationData = $productItem.data('variation-data');
        if (!variationData) { updateBundleState(); return; }

        const $selectContainer = $changedSelect.closest('.wcb-variation-container, .wcb-variation-set');
        const $allSelects = $selectContainer.find('.wcb-variation-select');
        
        let currentSelection = {};
        $allSelects.each(function() {
            const attributeName = $(this).data('attribute-name');
            const selectedValue = $(this).val();
            if (selectedValue) {
                currentSelection[attributeName] = selectedValue;
            }
        });

        $allSelects.each(function() {
            const $currentSelect = $(this);
            const attributeName = $currentSelect.data('attribute-name');
            if ($currentSelect.is($changedSelect)) return;
            $currentSelect.find('option:gt(0)').prop('disabled', true);
            const possibleOptions = new Set();
            variationData.forEach(function(variation) {
                let isMatch = true;
                for (const attr in currentSelection) {
                    if (attr !== attributeName && variation[attr] && variation[attr] !== currentSelection[attr]) {
                        isMatch = false;
                        break;
                    }
                }
                if (isMatch && variation[attributeName]) {
                    possibleOptions.add(variation[attributeName]);
                }
            });
            $currentSelect.find('option').each(function(){
                if(possibleOptions.has($(this).val())){
                    $(this).prop('disabled', false);
                }
            });
            if ($currentSelect.find('option:selected').is(':disabled')) {
                $currentSelect.val('');
            }
        });
        updateBundleState();
    });

    $bundleForm.on('change', 'input[type="radio"], input[type="checkbox"]', function() {
        const $input = $(this);
        const $productItem = $input.closest('.wcb-product-item');
        const $variationContainer = $productItem.find('.wcb-variation-container');
        const $personalizationContainer = $productItem.find('.wcb-personalization-field-container');
        const isChecked = $input.is(':checked');

        if ($input.is('[type="radio"]')) {
            const $otherProductItems = $input.closest('.wcb-group-products').find('.wcb-product-item').not($productItem);
            $otherProductItems.find('.wcb-variation-container').slideUp().find('select').prop('disabled', true);
            $otherProductItems.find('.wcb-personalization-field-container').slideUp();
        }

        if (isChecked) {
            $variationContainer.slideDown().find('select').prop('disabled', false);
            $variationContainer.find('.wcb-variation-select').first().trigger('change');
            $personalizationContainer.slideDown();
        } else {
            $variationContainer.slideUp().find('select').prop('disabled', true);
            $personalizationContainer.slideUp();
        }
        updateBundleState();
    });

    $bundleForm.on('change input', '.wcb-quantity-input', function() {
        const $input = $(this);
        const qty = parseInt($input.val()) || 0;
        const $productItem = $input.closest('.wcb-product-item');
        const $setsContainer = $productItem.find('.wcb-variation-sets-container');
        const $variationTemplate = $productItem.find('.wcb-variation-fields-template');
        const $personalizationTemplate = $productItem.find('.wcb-personalization-field-template');

        $setsContainer.empty();

        if (qty > 0) {
            $setsContainer.show();
            for (let i = 0; i < qty; i++) {
                const $newSet = $('<div class="wcb-variation-set"><h5>' + 'Pezzo ' + (i + 1) + '</h5></div>');
                
                if ($variationTemplate.length > 0) {
                    const variationTemplateHtml = $variationTemplate.html().replace(/__INDEX__/g, i);
                    const $newVariationContent = $(variationTemplateHtml);
                    $newVariationContent.find('select').prop('disabled', false);
                    $newSet.append($newVariationContent);
                }

                if ($personalizationTemplate.length > 0) {
                    const personalizationTemplateHtml = $personalizationTemplate.html().replace(/__INDEX__/g, i);
                    $newSet.append(personalizationTemplateHtml);
                }
                
                $setsContainer.append($newSet);
            }
        } else {
            $setsContainer.hide();
        }
        updateBundleState();
    });
    
    $bundleForm.on('input', '.wcb-personalization-input.wcb-input-error', function() {
        if ($(this).val().trim() !== '') {
            $(this).removeClass('wcb-input-error');
            updateBundleState();
        }
    });

    updateBundleState();
});

// frontend.js
// Copyright (c) 2025 Gianfranco Greco
// Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html

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

  function isPersonalizationComplete($productItem) {
    if (!$productItem.data('personalization-required')) {
      return true; 
    }
    let isComplete = true;
    $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input, .wcb-variation-set .wcb-personalization-input').each(function() {
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
            // Se il gruppo non è obbligatorio, è valido anche se vuoto
            if (!$group.data('is-required')) isValid = true;
        }
        break;
      }
      case 'multiple': {
        const min = $group.data('min-qty');
        const max = $group.data('max-qty');
        const $checkboxes = $group.find('input[type="checkbox"]:checked');
        if ($checkboxes.length >= min && (max === 0 || $checkboxes.length <= max)) {
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
          const qty = parseInt($(this).val()) || 0;
          currentTotal += qty;
          if (qty > 0) {
            const $productItem = $(this).closest('.wcb-product-item');
            if (!areVariationsComplete($productItem) || !isPersonalizationComplete($productItem)) {
              allItemsValid = false;
            }
          }
        });

        if (currentTotal === 0 && !$group.data('is-required')) {
            isValid = true;
        } else if (allItemsValid && currentTotal >= min && (max === 0 || currentTotal <= max)) {
            isValid = true;
            if (mode === 'quantity') isValid = (currentTotal === max);
        }
        break;
      }
    }
    return isValid;
  }
  
    // =========================================================================
    // LOGICA DI CALCOLO PREZZO DINAMICO (RIPRISTINATA)
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
                    console.error('Errore nel recupero prezzo variante:', response.data);
                    callback(0);
                }
            },
            error: function() {
                console.error('Errore AJAX nel recupero prezzo variante');
                callback(0);
            }
        });
    }

    function parsePriceFromElement($element) {
        if (!$element || $element.length === 0) return 0;
        const priceHTML = $element.html();
        let priceText = '';

        // Gestisce sia prezzi semplici che in saldo
        const ins = $element.find('ins');
        if (ins.length > 0) {
            priceText = ins.text();
        } else {
            priceText = $element.text();
        }
        
        const cleanPrice = priceText.replace(wcb_params.pricing.currency_symbol, '').replace(wcb_params.pricing.thousand_separator, '').replace(wcb_params.pricing.decimal_separator, '.').trim();
        return parseFloat(cleanPrice) || 0;
    }


    function finalizePriceCalculation(totalPrice) {
        const pricing = wcb_params.pricing;
        let finalPrice = totalPrice;

        if (pricing.type === 'calculated') {
            if (pricing.discount_percentage > 0) {
                finalPrice *= (1 - (pricing.discount_percentage / 100));
            }
            if (pricing.discount_amount > 0) {
                finalPrice = Math.max(0, finalPrice - pricing.discount_amount);
            }
        } else if (pricing.type === 'fixed') {
            finalPrice = parseFloat(pricing.fixed_price);
        }
        
        let formattedPrice = finalPrice.toFixed(2)
            .replace('.', wcb_params.pricing.decimal_separator);

        // Aggiungi il simbolo della valuta in base alla posizione
        if (wcb_params.pricing.currency_position.includes('left')) {
            formattedPrice = wcb_params.pricing.currency_symbol + formattedPrice;
        } else {
            formattedPrice = formattedPrice + wcb_params.pricing.currency_symbol;
        }

        const $priceContainer = $('.wcb-bundle-price');
        if ($priceContainer.length > 0) {
            $priceContainer.find('.wcb-price-value').html(formattedPrice);
        } else {
            $('.wcb-bundle-form').before(
                `<div class="wcb-bundle-price">
                    <h3>Prezzo Totale: <span class="wcb-price-value">${formattedPrice}</span></h3>
                 </div>`
            );
        }
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
            let isSelected = false;
            let quantity = 0;

            if ($radio.length > 0 && $radio.is(':checked')) {
                isSelected = true;
                quantity = 1;
            } else if ($checkbox.length > 0 && $checkbox.is(':checked')) {
                isSelected = true;
                quantity = 1;
            } else if ($quantityInput.length > 0) {
                quantity = parseInt($quantityInput.val(), 10) || 0;
                if (quantity > 0) isSelected = true;
            }

            if (isSelected) {
                itemsToPrice.push({ item: $item, quantity: quantity });
            }
        });
        
        if (itemsToPrice.length === 0) {
             finalizePriceCalculation(0);
             return;
        }
        
        itemsToPrice.forEach(function(bundleItem) {
            const { item, quantity } = bundleItem;
            const $item = item;
            const productId = $item.data('product-id');
            const $priceElement = $item.find('.wcb-product-price');
            const basePrice = parsePriceFromElement($priceElement);

            const isVariable = $item.find('.wcb-variation-container').length > 0 || $item.find('.wcb-variation-fields-template').length > 0;

            if (!isVariable) {
                totalPrice += basePrice * quantity;
            } else {
                // Gestione prodotto variabile
                const $variationContainers = $item.find('.wcb-variation-container:visible, .wcb-variation-set');
                
                if ($variationContainers.length === 0) { // Modalità a quantità, ma ancora a 0
                    // Non fare nulla, il prezzo non viene aggiunto
                } else if ($variationContainers.length === 1 && !$item.find('.wcb-variation-set').length) { // Scelta singola/multipla
                    const selectedAttributes = {};
                    let allAttributesSelected = true;
                    $variationContainers.find('.wcb-variation-select').each(function() {
                        const attrName = $(this).data('attribute-name');
                        const attrValue = $(this).val();
                        if (attrValue) {
                            selectedAttributes[attrName] = attrValue;
                        } else {
                            allAttributesSelected = false;
                        }
                    });

                    if (allAttributesSelected) {
                        pendingRequests++;
                        getVariationPrice(productId, selectedAttributes, function(variationPrice) {
                            totalPrice += variationPrice * quantity;
                            pendingRequests--;
                            if (pendingRequests === 0) finalizePriceCalculation(totalPrice);
                        });
                    } else {
                        // Un attributo non è selezionato, non aggiungiamo prezzo
                    }
                } else { // Modalità a quantità con più set di varianti
                    $variationContainers.each(function() {
                        const $set = $(this);
                        const selectedAttributes = {};
                        let allAttributesSelectedInSet = true;
                        $set.find('.wcb-variation-select').each(function() {
                            const attrName = $(this).data('attribute-name');
                            const attrValue = $(this).val();
                            if (attrValue) {
                                selectedAttributes[attrName] = attrValue;
                            } else {
                                allAttributesSelectedInSet = false;
                            }
                        });

                        if (allAttributesSelectedInSet) {
                            pendingRequests++;
                            getVariationPrice(productId, selectedAttributes, function(variationPrice) {
                                totalPrice += variationPrice; // La quantità qui è 1 per set
                                pendingRequests--;
                                if (pendingRequests === 0) finalizePriceCalculation(totalPrice);
                            });
                        }
                    });
                }
            }
        });

        if (pendingRequests === 0) {
            finalizePriceCalculation(totalPrice);
        }
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
    // Richiama il calcolo del prezzo ogni volta che lo stato cambia
    updateBundlePrice();
  }

  $bundleForm.on('change', '.wcb-variation-select', function() {
    const $changedSelect = $(this);
    const $productItem = $changedSelect.closest('.wcb-product-item');
    const variationData = $productItem.data('variation-data');
    if (!variationData || !Array.isArray(variationData)) { 
      updateBundleState(); 
      return; 
    }

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

    $allSelects.not($changedSelect).each(function() {
      const $currentSelect = $(this);
      const attributeName = $currentSelect.data('attribute-name');
      const originalValue = $currentSelect.val();
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
      $variationContainer.slideDown().find('select').prop('disabled', false).first().trigger('change');
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

  // Salva il testo originale del pulsante
  $addToCartButton.data('original-text', $addToCartButton.text());

  // Trigger iniziale per impostare lo stato
  updateBundleState();
  initLightbox();
});

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
    }).on('click', '.wcb-lightbox-image', e => e.stopPropagation())
      .on('keyup', e => { if (e.key === 'Escape') closeLightbox(); });
}

function openLightbox(imageUrl, caption) {
    const $lightbox = jQuery('#wcb-lightbox');
    $lightbox.find('.wcb-lightbox-image').attr('src', imageUrl);
    $lightbox.find('.wcb-lightbox-caption').text(caption);
    $lightbox.css('display', 'flex').hide().fadeIn(300);
    jQuery('body').addClass('wcb-lightbox-open');
}

function closeLightbox() {
    jQuery('#wcb-lightbox').fadeOut(300);
    jQuery('body').removeClass('wcb-lightbox-open');
}


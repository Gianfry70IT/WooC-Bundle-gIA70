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
      return true; 
    }

    let isComplete = true;

    $productItem.find('.wcb-personalization-field-container:visible .wcb-personalization-input').each(function() {
      if ($(this).val().trim() === '') {
        isComplete = false;
        $(this).addClass('wcb-input-error');
      } else {
        $(this).removeClass('wcb-input-error');
      }
    });

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
      case 'multiple_quantity': {
        const min = $group.data('min-qty');
        const max = $group.data('max-qty');
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

        if (currentTotal < min || (max > 0 && currentTotal > max)) {
          return false;
        }

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



  function updateBundlePrice() {
    if (typeof wcb_params.pricing === 'undefined') return;

    const pricing = wcb_params.pricing;
    let totalPrice = 0;
    let pendingRequests = 0;

    // Raccogli tutti i prodotti selezionati
    $('.wcb-product-item').each(function() {
      const $item = $(this);
      const $input = $item.find('input[type="radio"], input[type="checkbox"]');
      const $quantity = $item.find('.wcb-quantity-input');
      const $priceElement = $item.find('.wcb-product-price');
      const productId = $item.data('product-id');

      let isSelected = false;
      let quantity = 0;

      if ($input.length > 0) {
        isSelected = $input.is(':checked');
        quantity = isSelected ? 1 : 0;
      }

      if ($quantity.length > 0) {
        quantity = parseInt($quantity.val()) || 0;
        isSelected = quantity > 0;
      }

      if (isSelected) {
        // Per prodotti variabili, ottieni il prezzo della variante selezionata
        const $variationContainer = $item.find('.wcb-variation-container:visible');
        if ($variationContainer.length > 0) {
          // Ottieni gli attributi selezionati
          const selectedAttributes = {};
          let allAttributesSelected = true;

          $variationContainer.find('.wcb-variation-select').each(function() {
            const attributeName = $(this).data('attribute-name');
            const selectedValue = $(this).val();
            if (selectedValue) {
              selectedAttributes[attributeName] = selectedValue;
            } else {
              allAttributesSelected = false;
            }
          });

          if (allAttributesSelected) {
            pendingRequests++;
            // Usa AJAX per ottenere il prezzo della variante
            getVariationPrice(productId, selectedAttributes, function(variationPrice) {
              totalPrice += variationPrice * quantity;
              pendingRequests--;

              if (pendingRequests === 0) {
                finalizePriceCalculation(totalPrice, pricing);
              }
            });
          } else {
            // Se non tutti gli attributi sono selezionati, usa il prezzo base
            const basePrice = parsePriceFromElement($priceElement);
            totalPrice += basePrice * quantity;
          }
        } else {
          // Prodotto semplice - usa il prezzo base
          const basePrice = parsePriceFromElement($priceElement);
          totalPrice += basePrice * quantity;
        }
      }
    });

    // Se non ci sono richieste AJAX pendenti, finalizza il calcolo
    if (pendingRequests === 0) {
      finalizePriceCalculation(totalPrice, pricing);
    }
  }

  function finalizePriceCalculation(totalPrice, pricing) {
    // Applica sconti se in modalità calcolata
    if (pricing.type === 'calculated') {
      if (pricing.discount_percentage > 0) {
        totalPrice = totalPrice * (1 - (pricing.discount_percentage / 100));
      }
      if (pricing.discount_amount > 0) {
        totalPrice = Math.max(0, totalPrice - pricing.discount_amount);
      }
    } else if (pricing.type === 'fixed') {
      totalPrice = pricing.fixed_price;
    }

    // Aggiorna l'UI con il prezzo calcolato
    $('.wcb-bundle-price').remove();
    if (totalPrice > 0) {
      $('.wcb-bundle-form').before(`
<div class="wcb-bundle-price" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; text-align: center;">
<h3 style="margin: 0; color: #2c3e50;">Prezzo Totale: <span style="color: #27ae60;">${pricing.currency_symbol}${totalPrice.toFixed(2)}</span></h3>
</div>
`);
    }
  }  



  // Funzione helper per estrarre il prezzo da un elemento
  function parsePriceFromElement($element) {
    if ($element.length === 0) return 0;

    const priceText = $element.text().replace(/[^\d,.]/g, '').replace(',', '.');
    return parseFloat(priceText) || 0;
  }

  // Aggiungi questa funzione per ottenere il prezzo della variante via AJAX
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




  function updateBundleState() {
    let isBundleComplete = true;
    $('.wcb-bundle-group').each(function() {
      const $group = $(this);
      const isRequired = $group.data('is-required');
      const wasComplete = $group.hasClass('wcb-group-complete');
      const isGroupComplete = validateGroup($group);

      if (isGroupComplete) {
        $group.removeClass('wcb-group-incomplete').addClass('wcb-group-complete');
        if (!wasComplete) {
          animateGroupStatus($group, true);
        }
      } else {
        $group.removeClass('wcb-group-complete');
        if (isRequired) {
          $group.addClass('wcb-group-incomplete');
          isBundleComplete = false;
        }
      }
    });
    $addToCartButton.prop('disabled', !isBundleComplete);

    // Aggiungi una classe al pulsante quando è abilitato
    if (!isBundleComplete) {
      $addToCartButton.removeClass('wcb-button-ready');
    } else {
      $addToCartButton.addClass('wcb-button-ready');
    }
    updateBundlePrice();
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

  $bundleForm.on('change input', 'input[type="radio"], input[type="checkbox"], .wcb-quantity-input', function() {
    setTimeout(updateBundlePrice, 100);
  });

    function animateGroupStatus($group, isComplete) {
        $group.css('transition', 'all 0.5s ease');
        if (isComplete) {
            $group.addClass('wcb-group-complete-animate');
            setTimeout(() => {
                $group.removeClass('wcb-group-complete-animate');
            }, 500);
        }
    }
    
    updateBundleState();
    
    // Inizializza il lightbox
    initLightbox();
});


// ========== FUNZIONI LIGHTBOX ========== 

function openLightbox(imageUrl, caption) {
    const $lightbox = jQuery('#wcb-lightbox');
    const $lightboxImage = $lightbox.find('.wcb-lightbox-image');
    const $lightboxCaption = $lightbox.find('.wcb-lightbox-caption');
    
    $lightboxImage.attr('src', imageUrl);
    $lightboxCaption.text(caption);

    // MODIFICA: Cambia direttamente lo stile display invece di usare classi
    $lightbox.css('display', 'flex').hide().fadeIn(300);
    $lightbox.css('opacity', '1');
    $lightbox.addClass("show");
    jQuery('body').css('overflow', 'hidden');
}

function closeLightbox() {
    const $lightbox = jQuery('#wcb-lightbox');
    
    // MODIFICA: Nascondi con animazione e poi reimposta display: none
    $lightbox.fadeOut(300, function() {
        jQuery(this).css('display', 'none');
        $lightbox.removeClass("show");
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
    
    // Apri lightbox al click sull'immagine
    $bundleForm.on('click', '.wcb-thumbnail-image', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $image = jQuery(this);
        const imageUrl = $image.data('full-image');
        const productName = $image.closest('.wcb-product-item').find('.wcb-product-name').text();
        
        openLightbox(imageUrl, productName);
    });
    
    // Chiudi lightbox
    jQuery(document).on('click', '.wcb-lightbox-close, .wcb-lightbox', function(e) {
        if (e.target === this || jQuery(e.target).hasClass('wcb-lightbox-close')) {
            closeLightbox();
        }
    });
    
    // Previeni la chiusura quando si clicca sull'immagine
    jQuery(document).on('click', '.wcb-lightbox-image', function(e) {
        e.stopPropagation();
    });
    
    // Chiudi con ESC
    jQuery(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });
}

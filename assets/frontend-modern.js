/*
 * asset/frontend-modern.js - Versione 2.5
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

jQuery(document).ready(function ($) {
    console.log('WooC Bundle Frontend Modern Theme v2.5 loaded');

    const $bundleForm = $('.wcb-bundle-form');

    // Verifica se siamo in un bundle product
    if ($('.wcb-bundle-form').length === 0) return;

    // =========================================================================
    // 1. MICRO-INTERAZIONI MODERNE
    // =========================================================================

    // Effetto hover migliorato
    $('.wcb-product-item').on('mouseenter', function () {
        if (!$(this).hasClass('selected')) {
            $(this).addClass('hover-modern');
        }
    }).on('mouseleave', function () {
        $(this).removeClass('hover-modern');
    });

    // Effetto selezione con animazione
    $('input[type="radio"], input[type="checkbox"]').on('change', function () {
        const $item = $(this).closest('.wcb-product-item');

        // Rimuovi selezione dagli altri elementi dello stesso gruppo
        if ($(this).is('[type="radio"]')) {
            $item.siblings('.wcb-product-item').removeClass('selected');
        }

        // Aggiungi/rimuovi classe selected
        if ($(this).is(':checked')) {
            $item.addClass('selected');

            // Effetto ripple
            const $ripple = $('<div class="wcb-ripple"></div>');
            $item.append($ripple);
            setTimeout(() => $ripple.remove(), 600);
        } else {
            $item.removeClass('selected');
        }
    });

    // Effetto focus per input quantità
    $('.wcb-quantity-input').on('focus', function () {
        $(this).addClass('focus-modern');
    }).on('blur', function () {
        $(this).removeClass('focus-modern');
    });

    // =========================================================================
    // 2. ANIMAZIONI PREZZO DINAMICO
    // =========================================================================

    // Memorizza l'ultimo prezzo per animazione
    let lastPrice = 0;

    function animatePriceChange(currentPrice) {
        const $priceElement = $('.wcb-price-value');
        const numericPrice = typeof currentPrice === 'number'
            ? currentPrice
            : parseFloat($priceElement.first().text().replace(/[^\d.,]/g, '').replace(',', '.'));

        if (lastPrice > 0 && numericPrice !== lastPrice) {
            $priceElement.addClass('price-changing');
            setTimeout(() => {
                $priceElement.removeClass('price-changing');
            }, 300);
        }

        lastPrice = numericPrice;
    }

    $(document).on('wcb_bundle_price_updated', function (e, finalPrice) {
        animatePriceChange(finalPrice);
    });

    // Intercetta l'aggiornamento del prezzo esposto globalmente
    const originalUpdateBundlePrice = window.wcb_updateBundlePrice || window.updateBundlePrice;
    if (typeof originalUpdateBundlePrice === 'function') {
        window.wcb_updateBundlePrice = window.updateBundlePrice = function () {
            originalUpdateBundlePrice();
            animatePriceChange();
        };
    }

    // =========================================================================
    // 3. PROGRESS INDICATOR
    // =========================================================================
    function createProgressIndicator() {
        if ($('.wcb-progress-indicator').length > 0) return;

        const $groups = $('.wcb-bundle-group:visible');
        if ($groups.length > 1) {
            const $progress = $(`
                <div class="wcb-progress-indicator">
                    <div class="wcb-progress-track"></div>
                </div>
            `);

            // Crea steps solo per i gruppi visibili
            $groups.each(function (stepIdx) {
                const $group = $(this);
                const groupIndex = $group.data('group-index');
                const rawTitle = $group.find('.wcb-group-title').clone().children().remove().end().text().trim();
                const title = rawTitle.length > 20 ? rawTitle.substring(0, 20).trim() + '…' : rawTitle;
                const isRequired = $group.data('is-required') === true || $group.data('is-required') === 'true';

                const $step = $(`
                    <div class="wcb-progress-step" style="cursor: pointer;">
                        <div class="wcb-step-circle">
                            <span class="wcb-step-number">${stepIdx + 1}</span>
                            ${isRequired ? '<span class="wcb-step-required">*</span>' : ''}
                        </div>
                        <div class="wcb-step-title"></div>
                    </div>
                `);
                // Titolo inserito come testo: rawTitle arriva già decodificato da .text()
                $step.attr({ 'data-group-index': groupIndex, title: rawTitle });
                $step.find('.wcb-step-title').text(title);

                $step.on('click', function () {
                    $('html, body').animate({
                        scrollTop: $group.offset().top - 100
                    }, 350);
                });

                $progress.append($step);
            });

            $('.wcb-bundle-container').prepend($progress);
        }
    }

    // Aggiorna progress indicator sincronizzandolo con lo stato reale dei gruppi (complete / incomplete / opzionale neutro)
    function updateProgressIndicator() {
        $('.wcb-bundle-group:visible').each(function () {
            const $group = $(this);
            const groupIndex = $group.data('group-index');
            const $step = $(`.wcb-progress-step[data-group-index="${groupIndex}"]`);

            if ($group.hasClass('wcb-group-complete')) {
                $step.addClass('completed').removeClass('incomplete');
            } else if ($group.hasClass('wcb-group-incomplete')) {
                $step.addClass('incomplete').removeClass('completed');
            } else {
                $step.removeClass('completed incomplete');
            }
        });
    }

    $(document).on('wcb_bundle_state_updated', function () {
        if ($('.wcb-progress-indicator').length === 0) {
            createProgressIndicator();
        }
        updateProgressIndicator();
    });

    // Hook nell'updateBundleState esistente
    const originalUpdateBundleState = window.wcb_updateBundleState || window.updateBundleState;
    if (typeof originalUpdateBundleState === 'function') {
        window.wcb_updateBundleState = window.updateBundleState = function () {
            originalUpdateBundleState();
            if ($('.wcb-progress-indicator').length === 0) {
                createProgressIndicator();
            }
            updateProgressIndicator();
        };
    }

    // =========================================================================
    // 4. TOOLTIP PER INFORMAZIONI
    // =========================================================================
    function addTooltips() {
        // Tooltip per descrizioni gruppo
        $('.wcb-group-description').each(function () {
            if ($(this).text().length > 50) {
                $(this).attr('title', $(this).text());
                $(this).css('cursor', 'help');
            }
        });

        // Tooltip per prezzo override
        $('.wcb-product-item[data-price-override]').each(function () {
            const overridePrice = $(this).data('price-override');
            const originalPrice = $(this).find('.wcb-product-price').text();

            if (overridePrice) {
                $(this).attr('title', `Prezzo speciale: ${overridePrice} € (invece di ${originalPrice})`);
            }
        });
    }

    // =========================================================================
    // 5. LOADING STATES
    // =========================================================================
    $bundleForm.on('submit', function () {
        const $button = $(this).find('.single_add_to_cart_button, .wcb-mobile-submit-btn');
        $button.addClass('loading-modern');
        $button.html('<span class="wcb-spinner"></span> Aggiungendo...');
    });

    // =========================================================================
    // 6. INIZIALIZZAZIONE
    // =========================================================================
    setTimeout(() => {
        if ($('.wcb-progress-indicator').length === 0) {
            createProgressIndicator();
        }
        addTooltips();
        updateProgressIndicator();
    }, 500);

    // Aggiungi stili dinamici
    $('head').append(`
        <style>
            /* Stili per progress indicator */
            .wcb-progress-indicator {
                display: flex;
                justify-content: space-between;
                margin: 30px 0 40px;
                position: relative;
            }
            
            .wcb-progress-track {
                position: absolute;
                top: 15px;
                left: 0;
                right: 0;
                height: 4px;
                background: #e2e8f0;
                z-index: 1;
            }
            
            .wcb-progress-step {
                position: relative;
                z-index: 2;
                text-align: center;
                flex: 1;
            }
            
            .wcb-step-circle {
                width: 34px;
                height: 34px;
                background: #e2e8f0;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 10px;
                transition: all 0.3s;
                font-weight: 700;
                position: relative;
            }
            
            .wcb-progress-step.completed .wcb-step-circle {
                background: #4361ee;
                color: white;
                transform: scale(1.1);
            }
            
            .wcb-progress-step.incomplete .wcb-step-circle {
                background: #f43f5e;
                color: white;
            }
            
            .wcb-step-title {
                font-size: 12px;
                color: #64748b;
                font-weight: 500;
            }
            
            /* Animazione ripple */
            .wcb-ripple {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(67, 97, 238, 0.2);
                animation: ripple 0.6s linear;
            }
            
            @keyframes ripple {
                to {
                    width: 200px;
                    height: 200px;
                    opacity: 0;
                }
            }
            
            /* Animazione cambio prezzo */
            .price-changing {
                animation: pricePulse 0.3s ease-in-out;
            }
            
            @keyframes pricePulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            
            /* Loading spinner */
            .wcb-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255,255,255,0.3);
                border-radius: 50%;
                border-top-color: white;
                animation: spin 1s ease-in-out infinite;
                margin-right: 8px;
                vertical-align: middle;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            /* Hover moderno */
            .wcb-product-item.hover-modern {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            }
            
            /* Focus moderno */
            .wcb-quantity-input.focus-modern {
                border-color: #4361ee;
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }
        </style>
    `);
});
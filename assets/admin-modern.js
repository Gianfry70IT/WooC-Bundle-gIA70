/*
 * asset/admin-modern.js - Versione 2.4.7
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

jQuery(document).ready(function($) {
    console.log('WooC Bundle Modern Theme v2.4.1 loaded');
    
    // Funzione Core per inizializzare un gruppo
    function initModernGroup($container) {
        $container.addClass('modern-card');
        
        // Badge
        $container.each(function() {
            const $group = $(this);
            if ($group.find('.wcb-badge').length) return; // Evita duplicati
            
            const isRequired = $group.find('input[name*="[is_required]"]').is(':checked');
            if (isRequired) {
                $group.find('.group-title').append('<span class="wcb-badge badge-required">Obbligatorio</span>');
            } else {
                $group.find('.group-title').append('<span class="wcb-badge badge-optional">Opzionale</span>');
            }
        });
        
        // Toggle Buttons
        $container.find('.selection-mode-select').each(function() {
            const $select = $(this);
            if ($select.data('modern-init')) return; // Già fatto
            
            const $wrapper = $select.closest('.form-field');
            
            const modes = [
                { value: 'single', label: 'Singola' },
                { value: 'multiple', label: 'Multipla' },
                { value: 'quantity', label: 'Quantità' },
                { value: 'multiple_quantity', label: 'Qty Multipla' }
            ];
            
            const $toggleContainer = $('<div class="mode-toggle"></div>');
            modes.forEach(mode => {
                const isActive = $select.val() === mode.value;
                const $button = $(`<button type="button" class="mode-toggle-btn ${isActive ? 'active' : ''}" data-value="${mode.value}">${mode.label}</button>`);
                $toggleContainer.append($button);
            });
            
            $select.hide();
            $wrapper.append($toggleContainer);
            $select.data('modern-init', true);
            
            $toggleContainer.on('click', '.mode-toggle-btn', function() {
                const value = $(this).data('value');
                $select.val(value).trigger('change');
                $toggleContainer.find('.mode-toggle-btn').removeClass('active');
                $(this).addClass('active');
            });
        });
    }

    // Init iniziale
    initModernGroup($('.bundle-group'));
    
    // Init su nuovi gruppi aggiunti dinamicamente
    $(document).on('wcb_group_added', function(e, $newGroup) {
        initModernGroup($newGroup);
    });
});
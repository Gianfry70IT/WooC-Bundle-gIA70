jQuery(document).ready(function($) {
    console.log('WooC Bundle Modern Theme v2.4.0 loaded');
    
    // Attiva animazioni e effetti moderni
    $('.bundle-group').addClass('modern-card');
    
    // Aggiungi badge per stato gruppi
    $('.bundle-group').each(function() {
        const $group = $(this);
        const isRequired = $group.find('input[name*="[is_required]"]').is(':checked');
        
        if (isRequired) {
            $group.find('.group-title').append('<span class="wcb-badge badge-required">Obbligatorio</span>');
        } else {
            $group.find('.group-title').append('<span class="wcb-badge badge-optional">Opzionale</span>');
        }
    });
    
    // Toggle moderno per modalità
    $('.selection-mode-select').each(function() {
        const $select = $(this);
        const $container = $select.closest('.form-field');
        
        // Crea toggle buttons
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
        
        // Sostituisci select con toggle
        $select.hide();
        $container.append($toggleContainer);
        
        // Gestione click toggle
        $toggleContainer.on('click', '.mode-toggle-btn', function() {
            const value = $(this).data('value');
            $select.val(value).trigger('change');
            $toggleContainer.find('.mode-toggle-btn').removeClass('active');
            $(this).addClass('active');
        });
    });
});
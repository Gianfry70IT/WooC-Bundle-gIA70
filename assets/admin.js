/*
 * asset/admin.js - Versione 2.3.6
 * Author: Gianfranco Greco con Codice Sorgente
 * Copyright (c) 2025 Gianfranco Greco
 * Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html
*/

jQuery(document).ready(function($) {
    const $groupsContainer = $('#bundle_groups_container');

    // --- UTILS ---
    function formatProduct(product) {
        if (product.loading) return product.text;
        var imageHtml = product.image_url ? `<div class="select2-result-product__image"><img src="${product.image_url}" /></div>` : '';
        return $(`<div class="select2-result-product clearfix">${imageHtml}<div class="select2-result-product__meta"><div class="select2-result-product__title">${product.text}</div></div></div>`);
    }
    function formatProductSelection(product) { return product.text || product.id; }

    // --- LOGICA VISIBILITÀ SETTINGS (FIX UI ROBUSTO) ---
    function toggleProductSettingsVisibility($group) {
        const mode = $group.find('select.selection-mode-select').val();
        // Min e Step hanno senso solo se c'è un input numerico nel frontend (quantity o multiple_quantity)
        const showQtySettings = ['quantity', 'multiple_quantity'].includes(mode);
        
        // Cerca specificamente dentro questo gruppo per evitare conflitti
        $group.find('.wcb-setting-min, .wcb-setting-step').toggle(showQtySettings);
    }

    // --- RENDER TABELLA ---
    function renderProductSettings($group) {
        const $settingsContainer = $group.find('.wcb-product-settings-list');
        const $select = $group.find('select.wc-product-search');
        const selectedData = $select.select2('data') || [];
        const groupIndex = $group.attr('data-group-index');

        const currentIds = selectedData.map(p => p.id.toString());

        // Rimuovi non selezionati
        $settingsContainer.find('.wcb-product-setting-row').each(function() {
            if (!currentIds.includes($(this).attr('data-product-id'))) $(this).remove();
        });

        // Aggiungi nuovi
        selectedData.forEach(function(product) {
            const pid = product.id.toString();
            if ($settingsContainer.find(`.wcb-product-setting-row[data-product-id="${pid}"]`).length === 0) {
                let imageUrl = product.image_url;
                if (!imageUrl) {
                    const $opt = $select.find(`option[value="${pid}"]`);
                    imageUrl = $opt.data('image-url');
                }
                if (!imageUrl && typeof wc_enhanced_select_params !== 'undefined') {
                    imageUrl = wc_enhanced_select_params.ajax_url.replace('/admin-ajax.php', '/images/placeholder.png');
                }

                // Recupera dati esistenti (se ricaricamento pagina)
                const $preload = $group.find(`.wcb-preload-data[data-id="${pid}"]`);
                const savedMin = $preload.data('min') || 1;
                const savedStep = $preload.data('step') || 1;
                const savedPrice = $preload.data('price') || '';

                // NOTA: Aggiunte classi wcb-setting-min e wcb-setting-step per gestione visibilità
                const rowHtml = `
                    <div class="wcb-product-setting-row" data-product-id="${pid}">
                        <div class="wcb-row-thumb"><img src="${imageUrl}" width="40"></div>
                        <div class="wcb-row-name"><strong>${product.text}</strong><br><small>ID: ${pid}</small></div>
                        <div class="wcb-row-inputs">
                            <label class="wcb-setting-min">Min: <input type="number" name="_bundle_groups[${groupIndex}][products_settings][${pid}][min_qty]" value="${savedMin}" min="0" class="tiny-input"></label>
                            <label class="wcb-setting-step">Step: <input type="number" name="_bundle_groups[${groupIndex}][products_settings][${pid}][step]" value="${savedStep}" min="1" class="tiny-input"></label>
                            <label>Prezzo: <input type="text" name="_bundle_groups[${groupIndex}][products_settings][${pid}][price]" value="${savedPrice}" placeholder="€" class="wc_input_price"></label>
                        </div>
                        <div class="wcb-row-actions"><button type="button" class="wcb-remove-product-row">&times;</button></div>
                    </div>`;
                $settingsContainer.append(rowHtml);
            }
        });
        
        if (currentIds.length === 0) $settingsContainer.html('<p style="padding:10px; color:#666;">Cerca e seleziona i prodotti qui sopra.</p>');
        
        // FORZA AGGIORNAMENTO VISIBILITÀ DOPO IL RENDER
        toggleProductSettingsVisibility($group);
    }

    // --- FIX CRITICO RICERCA E BACKSPACE ---
    function initEnhancedSelect($target) {
        $target.each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
            
            var $element = $(this);
            var ajaxUrl = (typeof wc_enhanced_select_params !== 'undefined') ? wc_enhanced_select_params.ajax_url : ajaxurl;

            $element.select2({
                allowClear: false,
                placeholder: $(this).data('placeholder'),
                minimumInputLength: 3,
                closeOnSelect: false, // Mantiene aperto dopo la selezione per aggiungerne altri
                escapeMarkup: markup => markup,
                templateResult: formatProduct,
                templateSelection: formatProductSelection,
                ajax: {
                    url: ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        term: params.term,
                        action: 'wcb_custom_product_search', // Hardcoded action name for safety
                        security: (typeof wc_enhanced_select_params !== 'undefined') ? wc_enhanced_select_params.search_products_nonce : '',
                        exclude: $element.attr('data-exclude') || ''
                    }),
                    processResults: data => ({ results: data }),
                    cache: true
                },
                language: {
                    errorLoading: function() { return "Errore nel caricamento dei risultati."; },
                    searching: function() { return "Ricerca in corso..."; },
                    noResults: function() { return "Nessun prodotto trovato."; }
                }
            });

            // --- FIX BACKSPACE CHE CANCELLA PRODOTTI ---
            // Intercetta il tasto keydown sul campo di ricerca DOPO che select2 è stato inizializzato
            $element.on('select2:open', function() {
                $('.select2-container--open .select2-search__field').off('keydown.wcb').on('keydown.wcb', function(e) {
                    // Se premi Backspace (8) e il campo è vuoto -> STOP
                    if (e.which === 8 && $(this).val().length === 0) {
                        e.stopImmediatePropagation();
                        e.stopPropagation();
                        return false; 
                    }
                });
                $('.select2-container--open').addClass('wcb-select2-open');
            });

            $element.addClass('enhanced');
        });
    }

    // --- COMMON UTILS ---
    function updateProductExclusions() {
        const allSelectedIds = Array.from($groupsContainer.find('select.wc-product-search option:selected')).map(el => el.value);
        $groupsContainer.find('select.wc-product-search').attr('data-exclude', allSelectedIds.join(','));
    }

    function reindexGroups() {
        $groupsContainer.find('.bundle-group').each(function(newIndex) {
            $(this).attr('data-group-index', newIndex);
            $(this).find('[name]').each(function() {
                this.name = this.name.replace(/_bundle_groups\[\d+\]/, `_bundle_groups[${newIndex}]`);
            });
        });
    }

    // --- HTML GENERATOR ---
    function getGroupHtml(groupIndex, data = {}) {
        const title = data.title || '';
        const min_qty = data.min_qty || 1; const max_qty = data.max_qty || 0; const total_qty = data.total_qty || 1;
        const selection_mode = data.selection_mode || 'multiple';
        const is_required = data.is_required === 'no' ? '' : 'checked';
        const products = data.products || [];
        const price_override = data.price_override || '';
        const personalization_enabled = data.personalization_enabled === 'yes' ? 'checked' : '';
        const products_settings = data.products_settings || {};

        let fieldsHtml = '';
        const generateFieldRow = (idx, label, required) => `
            <div class="wcb-field-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center; background:#f9f9f9; padding:10px; border:1px solid #eee;">
                <div style="flex:1;"><label>Etichetta:</label><input type="text" name="_bundle_groups[${groupIndex}][personalization_fields][${idx}][label]" class="widefat" value="${label}"></div>
                <div style="width:100px;"><label>Obbligatorio?</label><select name="_bundle_groups[${groupIndex}][personalization_fields][${idx}][required]" class="widefat"><option value="no" ${required!=='yes'?'selected':''}>No</option><option value="yes" ${required==='yes'?'selected':''}>Sì</option></select></div>
                <div><button type="button" class="button wcb-remove-field-btn">&times;</button></div>
            </div>`;

        if (data.personalization_fields && Array.isArray(data.personalization_fields)) {
            data.personalization_fields.forEach((f, i) => fieldsHtml += generateFieldRow('f_'+new Date().getTime()+'_'+i, f.label, f.required));
        } else if (data.personalization_label) fieldsHtml += generateFieldRow('f_'+new Date().getTime(), data.personalization_label, data.personalization_required);

        let optionsHtml = ''; let preloadHtml = '';
        if (products.length > 0) {
            products.forEach(p => {
                optionsHtml += `<option value="${p.id}" data-image-url="${p.image_url}" selected="selected">${p.text}</option>`;
                const pSet = products_settings[p.id] || {};
                preloadHtml += `<div class="wcb-preload-data" data-id="${p.id}" data-min="${pSet.min_qty||1}" data-step="${pSet.step||1}" data-price="${pSet.price||''}" style="display:none;"></div>`;
            });
        }

        return `
        <div class="bundle-group options_group" data-group-index="${groupIndex}">
            <div class="group-header"><span class="sort-handle">☰</span><h3 class="group-title">${title || 'Nuovo Gruppo'}</h3><button type="button" class="button remove-group">X</button></div>
            <div class="group-content">
                <p class="form-field"><label>Titolo Gruppo</label><input type="text" class="group-title-input" name="_bundle_groups[${groupIndex}][title]" value="${title}"></p>
                
                <div class="wcb-products-section">
                    <label class="label-aggiungi-prodotti">Cerca e Aggiungi Prodotti</label>
                    <select class="wc-product-search" multiple="multiple" style="width: 100%;" name="_bundle_groups[${groupIndex}][products][]" data-placeholder="Scrivi qui per cercare...">${optionsHtml}</select>
                    ${preloadHtml}
                    <div class="wcb-product-settings-list"></div>
                </div>

                <hr/><h4>Regole Generali</h4>
                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                    <p class="form-field"><label><input type="checkbox" name="_bundle_groups[${groupIndex}][is_required]" value="yes" ${is_required}> Obbligatorio</label></p>
                    <p class="form-field"><label>Modalità:</label>
                        <select class="selection-mode-select" name="_bundle_groups[${groupIndex}][selection_mode]">
                            <option value="single" ${selection_mode === 'single' ? 'selected' : ''}>Singola</option>
                            <option value="multiple" ${selection_mode === 'multiple' ? 'selected' : ''}>Multipla</option>
                            <option value="quantity" ${selection_mode === 'quantity' ? 'selected' : ''}>Qty Fissa</option>
                            <option value="multiple_quantity" ${selection_mode === 'multiple_quantity' ? 'selected' : ''}>Qty Multipla</option>
                        </select>
                    </p>
                    <p class="form-field half-width rule-field rule-multiple" style="display:${['multiple','multiple_quantity'].includes(selection_mode)?'block':'none'}"><label>Min Qty</label><input type="number" name="_bundle_groups[${groupIndex}][min_qty]" value="${min_qty}" min="0"></p>
                    <p class="form-field half-width rule-field rule-multiple" style="display:${['multiple','multiple_quantity'].includes(selection_mode)?'block':'none'}"><label>Max Qty</label><input type="number" name="_bundle_groups[${groupIndex}][max_qty]" value="${max_qty}" min="0"></p>
                    <p class="form-field rule-field rule-quantity" style="display:${selection_mode==='quantity'?'block':'none'}"><label>Totale Esatto</label><input type="number" name="_bundle_groups[${groupIndex}][total_qty]" value="${total_qty}" min="1"></p>
                    <p class="form-field"><label>Prezzo Override Gruppo (€)</label><input type="text" name="_bundle_groups[${groupIndex}][price_override]" value="${price_override}" class="wc_input_price"></p>
                </div>

                <hr/><h4>Personalizzazione</h4>
                <p class="form-field"><label><input type="checkbox" class="personalization-enable" name="_bundle_groups[${groupIndex}][personalization_enabled]" value="yes" ${personalization_enabled}> Abilita</label></p>
                <div class="personalization-options wcb-fields-wrapper" style="display: ${personalization_enabled ? 'block' : 'none'};">
                    <div class="wcb-fields-container">${fieldsHtml}</div>
                    <div style="margin-top:10px;"><button type="button" class="button button-secondary wcb-add-field-btn">+ Campo Testo</button></div>
                </div>
            </div>
        </div>`;
    }

    // --- EVENTS ---
    function gestisciVisibilitaGenerale() {
        const productType = $('#product-type').val();
        if (productType !== 'custom_bundle') { $('.bundle_price_field_wrapper, .wcb-signature').hide(); return; }
        $('.pricing.show_if_simple').addClass('show_if_custom_bundle').show();
        const tipoPrezzo = $('#_bundle_pricing_type').val();
        $('.bundle_price_calculated_field').toggle(tipoPrezzo === 'calculated');
        $('.bundle_price_fixed_field').toggle(tipoPrezzo !== 'calculated');
        $('.wcb-signature').show();
    }
    
    $('body').on('change', '#product-type, #_bundle_pricing_type', gestisciVisibilitaGenerale);
    gestisciVisibilitaGenerale();

    if (typeof wcb_bundle_data !== 'undefined' && wcb_bundle_data.groups.length > 0) {
        wcb_bundle_data.groups.forEach((group, index) => $groupsContainer.append(getGroupHtml(index, group)));
    }

    initEnhancedSelect($('.wc-product-search'));
    
    // --- FIX: ESEGUI RENDER SU TUTTI I GRUPPI ESISTENTI AL CARICAMENTO ---
    $('.bundle-group').each(function() { 
        const $group = $(this);
        renderProductSettings($group); 
        toggleProductSettingsVisibility($group); // Forza check iniziale
    });
    
    updateProductExclusions();
    
    $('.bundle-group:not(:first)').addClass('closed').find('.group-content').hide();

    $groupsContainer.on('click', '.group-header', function(e) {
        if ($(e.target).closest('.sort-handle, .remove-group, .button, input, select').length) return;
        e.preventDefault();
        const $group = $(this).closest('.bundle-group');
        const $content = $group.find('.group-content');
        if ($group.hasClass('closed')) { $group.removeClass('closed'); $content.stop(true,true).slideDown(250); }
        else { $group.addClass('closed'); $content.stop(true,true).slideUp(250); }
    });

    $('#add_bundle_group').on('click', function() {
        const newIndex = $groupsContainer.find('.bundle-group').length;
        const $newGroup = $(getGroupHtml(newIndex));
        $groupsContainer.append($newGroup);
        initEnhancedSelect($newGroup.find('select.wc-product-search'));
        
        // Render e Toggle immediato per il nuovo gruppo
        renderProductSettings($newGroup);
        toggleProductSettingsVisibility($newGroup);
        
        updateProductExclusions();
    });

    $('#expand_all_groups').on('click', function() { $('.bundle-group.closed').removeClass('closed').find('.group-content').slideDown(300); });
    $('#collapse_all_groups').on('click', function() { $('.bundle-group:not(.closed)').addClass('closed').find('.group-content').slideUp(300); });

    $groupsContainer.on('click', '.remove-group', function() { if(confirm('Rimuovere?')) { $(this).closest('.bundle-group').remove(); reindexGroups(); updateProductExclusions(); }});

    $groupsContainer.on('change', 'select.wc-product-search', function() {
        const $group = $(this).closest('.bundle-group');
        renderProductSettings($group);
        updateProductExclusions();
    });

    $groupsContainer.on('click', '.wcb-remove-product-row', function() {
        const $row = $(this).closest('.wcb-product-setting-row');
        const pid = $row.attr('data-product-id');
        const $select = $row.closest('.bundle-group').find('select.wc-product-search');
        let selected = $select.val() || [];
        selected = selected.filter(id => id !== pid);
        $select.val(selected).trigger('change');
    });

    $groupsContainer.on('change', '.selection-mode-select', function() {
        const $group = $(this).closest('.bundle-group');
        const mode = $(this).val();
        
        // Toggle Regole Generali
        $group.find('.rule-field').hide();
        $group.find('.rule-multiple').toggle(mode === 'multiple' || mode === 'multiple_quantity');
        $group.find('.rule-quantity').toggle(mode === 'quantity');
        
        // Toggle Settings Prodotti (Min/Step)
        toggleProductSettingsVisibility($group);
    });

    $groupsContainer.on('keyup', '.group-title-input', function() { $(this).closest('.bundle-group').find('.group-title').text($(this).val() || 'Nuovo Gruppo'); });
    $groupsContainer.on('change', '.personalization-enable', function() { $(this).closest('.group-content').find('.personalization-options').slideToggle($(this).is(':checked')); });

    $groupsContainer.on('click', '.wcb-add-field-btn', function(){
        var $wrapper = $(this).closest('.wcb-fields-wrapper');
        var groupIndex = $(this).closest('.bundle-group').attr('data-group-index'); 
        var fieldIndex = 'f_' + new Date().getTime(); 
        var fieldHtml = `<div class="wcb-field-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center; background:#f9f9f9; padding:10px; border:1px solid #eee;"><div style="flex:1;"><label>Etichetta:</label><input type="text" name="_bundle_groups[${groupIndex}][personalization_fields][${fieldIndex}][label]" class="widefat"></div><div style="width:100px;"><label>Obbligatorio?</label><select name="_bundle_groups[${groupIndex}][personalization_fields][${fieldIndex}][required]" class="widefat"><option value="no">No</option><option value="yes">Sì</option></select></div><div><button type="button" class="button wcb-remove-field-btn">&times;</button></div></div>`;
        $wrapper.find('.wcb-fields-container').append(fieldHtml);
    });
    $groupsContainer.on('click', '.wcb-remove-field-btn', function(){ $(this).closest('.wcb-field-row').remove(); });

    $groupsContainer.sortable({ handle: '.sort-handle', update: () => { reindexGroups(); updateProductExclusions(); } });

    $('head').append(`<style>.wcb-select2-loading { padding: 10px; text-align: center; color: #666; } .wcb-select2-open .select2-dropdown { border-color: #0073aa; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }</style>`);
});
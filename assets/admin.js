// admin.js
// Copyright (c) 2025 Gianfranco Greco
// Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html

jQuery(document).ready(function($) {
    const $groupsContainer = $('#bundle_groups_container');

    function getGroupHtml(groupIndex, data = {}) {
        const title = data.title || '';
        const min_qty = data.min_qty || 1;
        const max_qty = data.max_qty || 1;
        const total_qty = data.total_qty || 1;
        const selection_mode = data.selection_mode || 'multiple';
        const is_required = data.is_required === 'no' ? '' : 'checked';
        const products = data.products || [];
        const personalization_enabled = data.personalization_enabled === 'yes' ? 'checked' : '';
        const personalization_label = data.personalization_label || 'Il tuo Nome';
        const personalization_required = data.personalization_required === 'yes' ? 'checked' : '';
    
        let optionsHtml = '';
        if (products.length > 0) {
            products.forEach(product => {
                optionsHtml += `<option value="${product.id}" selected="selected">${product.text}</option>`;
            });
        }
    
        return `
            <div class="bundle-group options_group" data-group-index="${groupIndex}">
                <div class="group-header"><span class="sort-handle">☰</span><h3 class="group-title">${title || 'Nuovo Gruppo'}</h3><button type="button" class="button remove-group" title="Rimuovi Gruppo">X</button></div>
                <div class="group-content">
                    <p class="form-field"><label>Titolo Gruppo</label><input type="text" class="group-title-input" name="_bundle_groups[${groupIndex}][title]" value="${title}"></p>
                    <p class="form-field"><label>Prodotti Selezionabili</label><select class="wc-product-search" multiple="multiple" style="width: 100%;" name="_bundle_groups[${groupIndex}][products][]" data-placeholder="Cerca prodotti..." data-action="wcb_custom_product_search">${optionsHtml}</select></p>
                    <hr/><h4>Regole di Selezione</h4>
                    <p class="form-field"><label><input type="checkbox" name="_bundle_groups[${groupIndex}][is_required]" value="yes" ${is_required}> Gruppo Obbligatorio</label></p>
                    <p class="form-field"><label>Modalità di Selezione</label><select class="selection-mode-select" name="_bundle_groups[${groupIndex}][selection_mode]"><option value="single" ${selection_mode === 'single' ? 'selected' : ''}>Scelta Singola</option><option value="multiple" ${selection_mode === 'multiple' ? 'selected' : ''}>Scelta Multipla</option><option value="quantity" ${selection_mode === 'quantity' ? 'selected' : ''}>Scelta a Quantità</option></select></p>
                    <p class="form-field half-width rule-field rule-multiple" style="display: ${selection_mode === 'multiple' ? 'block' : 'none'};"><label>Quantità Minima</label><input type="number" name="_bundle_groups[${groupIndex}][min_qty]" value="${min_qty}" min="0" step="1"></p>
                    <p class="form-field half-width rule-field rule-multiple" style="display: ${selection_mode === 'multiple' ? 'block' : 'none'};"><label>Quantità Massima</label><input type="number" name="_bundle_groups[${groupIndex}][max_qty]" value="${max_qty}" min="0" step="1"></p>
                    <p class="form-field rule-field rule-quantity" style="display: ${selection_mode === 'quantity' ? 'block' : 'none'};"><label>Quantità Totale</label><input type="number" name="_bundle_groups[${groupIndex}][total_qty]" value="${total_qty}" min="1" step="1"></p>
                    <hr/><h4>Opzioni di Personalizzazione</h4>
                    <p class="form-field"><label><input type="checkbox" class="personalization-enable" name="_bundle_groups[${groupIndex}][personalization_enabled]" value="yes" ${personalization_enabled}> Abilita campo di testo personalizzato</label></p>
                    <div class="personalization-options" style="display: ${personalization_enabled ? 'block' : 'none'};">
                        <p class="form-field"><label>Etichetta del campo di testo</label><input type="text" name="_bundle_groups[${groupIndex}][personalization_label]" value="${personalization_label}"></p>
                        <p class="form-field"><label><input type="checkbox" name="_bundle_groups[${groupIndex}][personalization_required]" value="yes" ${personalization_required}> Rendi la personalizzazione obbligatoria</label></p>
                    </div>
                </div>
            </div>
        `;
    }
    
    function reindexGroups() {
        $groupsContainer.find('.bundle-group').each(function(newIndex, group) {
            $(group).attr('data-group-index', newIndex);
            $(group).find('[name]').each(function() {
                this.name = this.name.replace(/_bundle_groups\[\d+\]/, '_bundle_groups[' + newIndex + ']');
            });
        });
    }

    function initEnhancedSelect($target) {
        $target.each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
            const select2_args = {
                allowClear: $(this).data('allow_clear') ? true : false, placeholder: $(this).data('placeholder'), minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3', escapeMarkup: function(markup) { return markup; },
                ajax: {
                    url: wc_enhanced_select_params.ajax_url, dataType: 'json', delay: 250,
                    data: function(params) { return { term: params.term, action: 'wcb_custom_product_search', security: wc_enhanced_select_params.search_products_nonce, exclude: $(this).attr('data-exclude') || '' }; },
                    processResults: function(data) {
                        const terms = [];
                        if (data) $.each(data, function(id, text) { terms.push({ id: id, text: text }); });
                        return { results: terms };
                    },
                    cache: false
                }
            };
            $(this).select2(select2_args).addClass('enhanced');
        });
    }

    function updateProductExclusions() {
        const allSelectedIds = new Set();
        $groupsContainer.find('select.wc-product-search').each(function() {
            const selected = $(this).val();
            if (selected) (Array.isArray(selected) ? selected : [selected]).forEach(id => allSelectedIds.add(id.toString()));
        });
        const idsToExclude = Array.from(allSelectedIds).join(',');
        $groupsContainer.find('select.wc-product-search').attr('data-exclude', idsToExclude);
    }

    function validateMinMax($group) {
        const $minInput = $group.find('input[name$="[min_qty]"]');
        const $maxInput = $group.find('input[name$="[max_qty]"]');
        if ($minInput.length === 0 || $maxInput.length === 0) return;
        const minVal = parseInt($minInput.val(), 10);
        const maxVal = parseInt($maxInput.val(), 10);
        if (!isNaN(minVal)) {
            $maxInput.attr('min', minVal);
            if (maxVal > 0 && !isNaN(maxVal) && maxVal < minVal) $maxInput.val(minVal).addClass('error');
            else $maxInput.removeClass('error');
        }
    }

    if (typeof wcb_bundle_data !== 'undefined' && wcb_bundle_data.groups.length > 0) {
        wcb_bundle_data.groups.forEach((group, index) => {
            $groupsContainer.append(getGroupHtml(index, group));
        });
    }

    initEnhancedSelect($('.wc-product-search'));
    updateProductExclusions();
    $groupsContainer.find('.bundle-group').each(function() { validateMinMax($(this)); });

    $('#add_bundle_group').on('click', function() {
        const newIndex = $groupsContainer.find('.bundle-group').length;
        const $newGroup = $(getGroupHtml(newIndex));
        $groupsContainer.append($newGroup);
        initEnhancedSelect($newGroup.find('select.wc-product-search'));
        updateProductExclusions();
    });

    $groupsContainer.on('click', '.remove-group', function() {
        $(this).closest('.bundle-group').remove();
        reindexGroups();
        updateProductExclusions();
    });

    $groupsContainer.on('change', '.selection-mode-select', function() {
        const $group = $(this).closest('.bundle-group');
        const mode = $(this).val();
        $group.find('.rule-field').hide();
        $group.find('.rule-' + mode).show();
    });
    
    $groupsContainer.on('keyup', '.group-title-input', function() {
        const newTitle = $(this).val() || 'Nuovo Gruppo';
        $(this).closest('.bundle-group').find('.group-title').text(newTitle);
    });

    $groupsContainer.on('change', '.personalization-enable', function() {
        const $options = $(this).closest('.group-content').find('.personalization-options');
        if ($(this).is(':checked')) $options.slideDown();
        else $options.slideUp();
    });

    $groupsContainer.sortable({ handle: '.sort-handle', update: function() { reindexGroups(); updateProductExclusions(); } });
    $groupsContainer.on('change', 'select.wc-product-search', function() { updateProductExclusions(); });
    $groupsContainer.on('change input', 'input[name$="[min_qty]"], input[name$="[max_qty]"]', function() { validateMinMax($(this).closest('.bundle-group')); });
    
    function gestisciVisibilitaFirma() {
        const productType = $('#product-type').val();
        const $signature = $('.wcb-signature');

        if (productType === 'custom_bundle') {
            $signature.show();
        } else {
            $signature.hide();
        }
    }

    gestisciVisibilitaFirma();

    $('#product-type').on('change', function() {
        gestisciVisibilitaFirma();
    });

});

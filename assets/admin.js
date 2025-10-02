// admin.js
// Copyright (c) 2025 Gianfranco Greco
// Licensed under the GNU GPL v2 or later: https://www.gnu.org/licenses/gpl-2.0.html

jQuery(document).ready(function($) {
    const $groupsContainer = $('#bundle_groups_container');

    function formatProduct(product) {
        if (product.loading) return product.text;
        return $(
            `<div class="select2-result-product clearfix">
                <div class="select2-result-product__image"><img src="${product.image_url || ''}" /></div>
                <div class="select2-result-product__meta">
                    <div class="select2-result-product__title">${product.text}</div>
                </div>
            </div>`
        );
    }
    
    function formatProductSelection(product) {
        return product.text || product.id;
    }

    function renderProductGallery($group) {
        const $galleryContainer = $group.find('.wcb-product-gallery');
        const $select = $group.find('select.wc-product-search');
        const selectedData = $select.select2('data') || [];

        $galleryContainer.empty();

        selectedData.forEach(function(product) {
            let imageUrl = product.image_url;

            if (!imageUrl) {
                const originalOption = $select.find(`option[value="${product.id}"]`);
                if (originalOption.length) {
                    imageUrl = originalOption.data('image-url');
                }
            }
            
            imageUrl = imageUrl || wc_enhanced_select_params.ajax_url.replace('/admin-ajax.php', '/images/placeholder.png');

            const galleryItemHtml = `
                <div class="wcb-gallery-item" data-product-id="${product.id}" title="${product.text}">
                    <img src="${imageUrl}" alt="${product.text}" />
                    <button type="button" class="wcb-remove-product" aria-label="Rimuovi prodotto">&times;</button>
                </div>`;
            $galleryContainer.append(galleryItemHtml);
        });
    }

    function gestisciVisibilitaGenerale() {
        const productType = $('#product-type').val();
        if (productType !== 'custom_bundle') {
            $('.bundle_price_field_wrapper, .wcb-signature').hide();
            return;
        }
        $('.pricing.show_if_simple').addClass('show_if_custom_bundle').show();
        const tipoPrezzo = $('#_bundle_pricing_type').val();
        $('.bundle_price_calculated_field').toggle(tipoPrezzo === 'calculated');
        $('.bundle_price_fixed_field').toggle(tipoPrezzo !== 'calculated');
        $('.wcb-signature').show();
    }

    $('body').on('change', '#product-type, #_bundle_pricing_type', gestisciVisibilitaGenerale);
    gestisciVisibilitaGenerale();

    function getGroupHtml(groupIndex, data = {}) {
        const title = data.title || '';
        const min_qty = data.min_qty || 1;
        const max_qty = data.max_qty || 0;
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
                optionsHtml += `<option value="${product.id}" data-image-url="${product.image_url}" selected="selected">${product.text}</option>`;
            });
        }

        return `
        <div class="bundle-group options_group" data-group-index="${groupIndex}">
            <div class="group-header"><span class="sort-handle">☰</span><h3 class="group-title">${title || 'Nuovo Gruppo'}</h3><button type="button" class="button remove-group" title="Rimuovi Gruppo">X</button></div>
            <div class="group-content">
                <p class="form-field"><label>Titolo Gruppo</label><input type="text" class="group-title-input" name="_bundle_groups[${groupIndex}][title]" value="${title}"></p>
                <p class="form-field"><label>Prodotti Selezionabili</label><select class="wc-product-search" multiple="multiple" style="width: 100%;" name="_bundle_groups[${groupIndex}][products][]" data-placeholder="Cerca e aggiungi prodotti..." data-action="wcb_custom_product_search">${optionsHtml}</select></p>
                <div class="wcb-product-gallery-container"><div class="wcb-product-gallery"></div></div>
                <hr/><h4>Regole di Selezione</h4>
                <p class="form-field"><label><input type="checkbox" name="_bundle_groups[${groupIndex}][is_required]" value="yes" ${is_required}> Gruppo Obbligatorio</label></p>
                <p class="form-field"><label>Modalità di Selezione</label>
                    <select class="selection-mode-select" name="_bundle_groups[${groupIndex}][selection_mode]">
                        <option value="single" ${selection_mode === 'single' ? 'selected' : ''}>Scelta Singola</option>
                        <option value="multiple" ${selection_mode === 'multiple' ? 'selected' : ''}>Scelta Multipla</option>
                        <option value="quantity" ${selection_mode === 'quantity' ? 'selected' : ''}>Scelta a Quantità Fissa</option>
                        <option value="multiple_quantity" ${selection_mode === 'multiple_quantity' ? 'selected' : ''}>Quantità Multipla a Range</option>
                    </select>
                </p>
                <p class="form-field half-width rule-field rule-multiple" style="display: ${selection_mode === 'multiple' || selection_mode === 'multiple_quantity' ? 'block' : 'none'};"><label>Quantità Minima</label><input type="number" name="_bundle_groups[${groupIndex}][min_qty]" value="${min_qty}" min="0" step="1"></p>
                <p class="form-field half-width rule-field rule-multiple" style="display: ${selection_mode === 'multiple' || selection_mode === 'multiple_quantity' ? 'block' : 'none'};"><label>Quantità Massima (0 per illimitata)</label><input type="number" name="_bundle_groups[${groupIndex}][max_qty]" value="${max_qty}" min="0" step="1"></p>
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
        $groupsContainer.find('.bundle-group').each(function(newIndex) {
            $(this).attr('data-group-index', newIndex);
            $(this).find('[name]').each(function() {
                this.name = this.name.replace(/_bundle_groups\[\d+\]/, `_bundle_groups[${newIndex}]`);
            });
        });
    }

    function updateProductExclusions() {
        const allSelectedIds = Array.from($groupsContainer.find('select.wc-product-search option:selected')).map(el => el.value);
        $groupsContainer.find('select.wc-product-search').attr('data-exclude', allSelectedIds.join(','));
    }

    if (wcb_bundle_data?.groups.length > 0) {
        wcb_bundle_data.groups.forEach((group, index) => $groupsContainer.append(getGroupHtml(index, group)));
    }

    initEnhancedSelect($('.wc-product-search'));
    $('.bundle-group').each(function() { renderProductGallery($(this)); });
    updateProductExclusions();
    
    $('.bundle-group:not(:first)').addClass('closed').find('.group-content').hide();

    $('#add_bundle_group').on('click', function() {
        const newIndex = $groupsContainer.find('.bundle-group').length;
        const $newGroup = $(getGroupHtml(newIndex));
        $groupsContainer.append($newGroup);
        initEnhancedSelect($newGroup.find('select.wc-product-search'));
        renderProductGallery($newGroup);
        updateProductExclusions();
    });

    $('#expand_all_groups').on('click', function() {
        $('.bundle-group.closed').removeClass('closed').find('.group-content').slideDown(300);
    });

    $('#collapse_all_groups').on('click', function() {
        $('.bundle-group:not(.closed)').addClass('closed').find('.group-content').slideUp(300);
    });

    $groupsContainer.on('click', '.remove-group', function() {
        $(this).closest('.bundle-group').remove();
        reindexGroups();
        updateProductExclusions();
    });
    
    $groupsContainer.on('click', '.wcb-remove-product', function() {
        const $item = $(this).closest('.wcb-gallery-item');
        const productId = $item.data('product-id').toString();
        const $select = $item.closest('.bundle-group').find('select.wc-product-search');
        $select.find(`option[value="${productId}"]`).prop('selected', false);
        $select.trigger('change');
    });
    
    $groupsContainer.on('change', 'select.wc-product-search', function() {
        renderProductGallery($(this).closest('.bundle-group'));
        updateProductExclusions();
    });

    $groupsContainer.on('click', '.group-header', function(e) {
        if ($(e.target).is('.sort-handle, .remove-group, .button')) {
            return; 
        }
        const $group = $(this).closest('.bundle-group');
        if ($group.hasClass('closed')) {
            $group.removeClass('closed').find('.group-content').slideDown(300);
        } else {
            $group.addClass('closed').find('.group-content').slideUp(300);
        }
    });

    $groupsContainer.on('change', '.selection-mode-select', function() {
        const $group = $(this).closest('.bundle-group');
        const mode = $(this).val();
        $group.find('.rule-field').hide();
        $group.find('.rule-multiple').toggle(mode === 'multiple' || mode === 'multiple_quantity');
        $group.find('.rule-quantity').toggle(mode === 'quantity');
    });

    $groupsContainer.on('keyup', '.group-title-input', function() {
        $(this).closest('.bundle-group').find('.group-title').text($(this).val() || 'Nuovo Gruppo');
    });

    $groupsContainer.on('change', '.personalization-enable', function() {
        $(this).closest('.group-content').find('.personalization-options').slideToggle($(this).is(':checked'));
    });

    $groupsContainer.sortable({ handle: '.sort-handle', update: () => { reindexGroups(); updateProductExclusions(); } });
    
function showSelect2Loader($select) {
  $select.data('select2').$dropdown.find('.select2-results')
    .html('<div class="wcb-select2-loading">Caricamento...</div>');
}

function initEnhancedSelect($target) {
  $target.each(function() {
    if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
    
    $(this).select2({
        allowClear: $(this).data('allow_clear') || false, placeholder: $(this).data('placeholder'), minimumInputLength: 3,
        escapeMarkup: markup => markup, templateResult: formatProduct, templateSelection: formatProductSelection,
        ajax: {
            url: wc_enhanced_select_params.ajax_url, dataType: 'json', delay: 250,
            data: params => ({ term: params.term, action: $(this).data('action'), security: wc_enhanced_select_params.search_products_nonce, exclude: $(this).attr('data-exclude') || '' }),
            processResults: data => ({ results: data }), cache: false
        },
        language: {
            searching: function() {
                return "Ricerca in corso...";
            }
        }
    }).on('select2:open', function() {
      $('.select2-container--open').addClass('wcb-select2-open');
    }).addClass('enhanced');
  });
}

$('head').append(`
  <style>
    .wcb-select2-loading {
      padding: 10px;
      text-align: center;
      color: var(--wcb-secondary);
    }
    
    .wcb-select2-open .select2-dropdown {
      border-color: var(--wcb-primary);
      box-shadow: 0 0 0 3px rgba(56, 88, 233, 0.1);
    }
  </style>
`);

});


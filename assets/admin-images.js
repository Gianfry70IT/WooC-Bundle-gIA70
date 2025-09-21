jQuery(document).ready(function($) {
    // Funzione per aggiungere immagini ai prodotti esistenti
    function addImagesToExistingProducts() {
        $('.wc-product-search').each(function() {
            const $select = $(this);
            
            // Per ogni opzione selezionata
            $select.find('option:selected').each(function() {
                const productId = $(this).val();
                const productText = $(this).text();
                
                if (productId) {
                    // Crea un elemento con immagine
                    const $choice = $select.next('.select2-container')
                        .find('.select2-selection__choice:contains("' + productText + '")');
                    
                    if ($choice.length && !$choice.find('.wcb-product-thumb').length) {
                        $.ajax({
                            url: wcb_bundle_data.ajax_url,
                            method: 'POST',
                            data: {
                                action: 'wcb_get_product_image',
                                product_id: productId,
                                security: wcb_bundle_data.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $choice.prepend(
                                        '<img class="wcb-product-thumb" src="' + response.data.image + '" /> '
                                    );
                                }
                            }
                        });
                    }
                }
            });
        });
    }
    
    // Esegui al caricamento della pagina
    setTimeout(addImagesToExistingProducts, 1000);
    
    // Esegui quando vengono aggiunti nuovi prodotti
    $(document).on('change', '.wc-product-search', function() {
        setTimeout(addImagesToExistingProducts, 500);
    });
});
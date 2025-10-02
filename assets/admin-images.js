jQuery(document).ready(function($) {
    function addImagesToExistingProducts() {
        $('.wc-product-search').each(function() {
            const $select = $(this);
            
            $select.find('option:selected').each(function() {
                const productId = $(this).val();
                const productText = $(this).text();
                
                if (productId) {
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
    
    setTimeout(addImagesToExistingProducts, 1000);
    
    $(document).on('change', '.wc-product-search', function() {
        setTimeout(addImagesToExistingProducts, 500);
    });
});
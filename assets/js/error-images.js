jQuery(document).ready(function ($) {
    'use strict';
    let is_current_page_focus = false;
    /*Set paged to 1 before submitting*/
    $('.tablenav-pages').find('.current-page').on('focus', function (e) {
        is_current_page_focus = true;
    }).on('blur', function (e) {
        is_current_page_focus = false;
    });
    $('select[name="tbds_search_product_id"]').on('change', function () {
        let $form = $(this).closest('form');
        if (!is_current_page_focus) {
            $form.find('.current-page').val(1);
        }
        $form.submit();
    });
    $('.tbds-search-product-id').select2({
        placeholder: 'Filter by product',
        allowClear: true,
    });
    $('.tbds-search-product-id-ajax').select2({
        closeOnSelect: true,
        allowClear: true,
        placeholder: "Please enter product title to search",
        ajax: {
            url: "admin-ajax.php?action=wad_search_product_failed_images",
            dataType: 'json',
            type: "GET",
            quietMillis: 50,
            delay: 250,
            data: function (params) {
                return {
                    keyword: params.term,
                    p_id: $(this).closest('td').data('id')
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            }
        },
        escapeMarkup: function (markup) {
            return markup;
        }, // let our custom formatter work
        minimumInputLength: 1
    });
    let $button_download = $('.tbds-action-download');
    let $button_download_all = $('.tbds-action-download-all');
    let $button_delete = $('.tbds-action-delete');
    let $button_delete_all = $('.tbds-action-delete-all');
    let queue = [];
    let queue_delete = [];
    let is_bulk_delete = false;

    $button_download_all.on('click', function () {
        if ($('.tbds-button-all-container').find('.loading').length === 0) {
            $('.tbds-action-download').not('.loading').map(function () {
                if ($(this).closest('.tbds-actions-container').find('.loading').length === 0) {
                    queue.push($(this));
                }
            });
            if (queue.length > 0) {
                queue.shift().click();
                $button_download_all.addClass('loading');
            }
        }
    });

    $button_delete_all.on('click', function () {
        if ($('.tbds-button-all-container').find('.loading').length === 0) {
            if (confirm(tbdsParams.i18n_confirm_delete_all)) {
                $('.tbds-action-delete').not('.loading').map(function () {
                    if ($(this).closest('.tbds-actions-container').find('.loading').length === 0) {
                        queue_delete.push($(this));
                    }
                });
                console.log(queue_delete)
                if (queue_delete.length > 0) {
                    is_bulk_delete = true;
                    queue_delete.shift().click();
                    $button_delete_all.addClass('loading');
                }
            }

        }
    });

    $button_delete.on('click', function () {
        let $button = $(this);
        let $row = $button.closest('tr');
        let item_id = $button.data('item_id');
        if ($button.hasClass('loading')) {
            return;
        }
        if (is_bulk_delete || confirm(tbdsParams.i18n_confirm_delete)) {
            $button.addClass('loading');
            $button.find('.tbds-delete-image-error').remove();
            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_delete_error_product_images',
                    _ajax_nonce: tbdsParams.security,
                    item_id: item_id
                },
                success: function (response) {
                    $button.removeClass('loading');
                    if (response.status === 'success') {
                        $row.remove();
                        if ($('.tbds-action-download').length === 0) {
                            $('.tbds-button-all-container').remove();
                        }
                    } else {
                        let $result_icon = $('<span class="tbds-delete-image-error dashicons dashicons-no" title="' + response.message + '"></span>');
                        $button.append($result_icon);
                    }
                },
                error: function (err) {
                    console.log(err);
                    $button.removeClass('loading');
                },
                complete: function () {
                    if (queue_delete.length > 0) {
                        queue_delete.shift().click();
                    } else {
                        if ($('.tbds-action-delete-all').hasClass('loading')) {
                            $('.tbds-action-delete-all').removeClass('loading')
                        }
                        is_bulk_delete = false;
                    }
                }
            })
        }
    });

    $button_download.on('click', function () {
        let $button = $(this);
        let $row = $button.closest('tr');
        let item_id = $button.data('item_id');

        if ($button.hasClass('loading')) return;

        $button.addClass('loading');
        $button.find('.tbds-download-image-error').remove();

        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'tbds_download_error_product_images',
                _ajax_nonce: tbdsParams.security,
                item_id: item_id
            },
            success: function (response) {
                $button.removeClass('loading');
                if (response.status === 'success') {
                    $row.remove();
                    if ($('.tbds-action-download').length === 0) {
                        $('.tbds-button-all-container').remove();
                    }
                } else {
                    let $result_icon = $('<span class="tbds-download-image-error dashicons dashicons-no" title="' + response.message + '"></span>');
                    $button.append($result_icon);
                }
            },
            error: function (err) {
                console.log(err);
                $button.removeClass('loading');
            },
            complete: function () {
                if (queue.length > 0) {
                    queue.shift().click();
                } else if ($('.tbds-action-download-all').hasClass('loading')) {
                    $('.tbds-action-download-all').removeClass('loading')
                }
            }
        })
    })
});

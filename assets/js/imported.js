jQuery(document).ready($ => {
    'use strict';
    let queue = [];
    let is_deleting = false;

    /*Set paged to 1 before submitting*/
    let is_current_page_focus = false;
    $('.tablenav-pages').find('.current-page')
        .on('focus', () => is_current_page_focus = true)
        .on('blur', () => is_current_page_focus = false);

    $('.search-box').find('input[type="submit"]').on('click', function () {
        let $form = $(this).closest('form');
        if (!is_current_page_focus) {
            $form.find('.current-page').val(1);
        }
    });

    $('.vi-ui.tabular.menu .item').viTab();
    $('.vi-ui.accordion').vi_accordion('refresh');
    $('select.vi-ui.dropdown').viDropdown();
    let $imported_list_count = $('#toplevel_page_woo-alidropship').find('.current').find('.tbds-imported-list-count');
    $('.tbds-button-view-and-edit').on('click', e => e.stopPropagation());

    add_keyboard_event();

    function add_keyboard_event() {
        $(document).on('keydown', function (e) {
            if (!$('.tbds-delete-product-options-container').hasClass('tbds-hidden')) {
                if (e.keyCode == 13) {
                    if (!$('.tbds-delete-product-options-button-override').hasClass('tbds-hidden')) {
                        $('.tbds-delete-product-options-button-override').trigger('click');
                        $('.tbds-delete-product-options-override-product').focus();
                    } else if (!$('.tbds-delete-product-options-button-delete').hasClass('tbds-hidden')) {
                        $('.tbds-delete-product-options-button-delete').trigger('click');
                    }
                } else if (e.keyCode === 27) {
                    $('.tbds-overlay').trigger('click');
                }
            }
        });
    }

    $('.tbds-button-trash').on('click', function () {
        let $button = $(this);
        let $trash_count = $('.tbds-imported-products-count-trash');
        let trash_count = parseInt($trash_count.html());
        let $publish_count = $('.tbds-imported-products-count-publish');
        let publish_count = parseInt($publish_count.html());
        let data = {
            action: 'tbds_trash_product',
            _ajax_nonce: tbdsParams.security,
            product_id: $(this).data('product_id'),
        };
        let $product_container = $('#tbds-product-item-id-' + data.product_id);

        $button.addClass('loading');

        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'post',
            dataType: 'JSON',
            data: data,
            success: function (res) {
                if (res.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = imported_list_count_value - 1;
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }
                    trash_count++;
                    publish_count--;
                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $trash_count.html(trash_count);
                        $publish_count.html(publish_count);
                        $product_container.remove();
                    }, 300)
                }
            },
            error: function (res) {
                console.log(res);
            },
            complete: function () {
                $button.removeClass('loading');
            }

        });
    });

    $('.tbds-button-restore').on('click', function () {
        let $button = $(this);
        let $trash_count = $('.tbds-imported-products-count-trash');
        let trash_count = parseInt($trash_count.html());
        let $publish_count = $('.tbds-imported-products-count-publish');
        let publish_count = parseInt($publish_count.html());
        let data = {
            action: 'tbds_restore_product',
            _ajax_nonce: tbdsParams.security,
            product_id: $(this).data('product_id')
        };
        let $product_container = $('#tbds-product-item-id-' + data.product_id);
        $button.addClass('loading');

        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'post',
            dataType: 'JSON',
            data: data,
            success: function (res) {
                console.log(res);
                if (res.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = imported_list_count_value + 1;
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }
                    trash_count--;
                    publish_count++;
                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $trash_count.html(trash_count);
                        $publish_count.html(publish_count);
                        $product_container.remove();
                    }, 300)
                }
            },
            error: function (res) {
                console.log(res);
            },
            complete: function () {
                $button.removeClass('loading');
            }
        });
    });

    $('.tbds-button-delete').on('click', function () {
        let $button_delete = $(this);
        if (!$button_delete.hasClass('loading')) {
            let product_title = $button_delete.data()['product_title'];
            let product_id = $button_delete.data()['product_id'];
            let woo_product_id = $button_delete.data()['woo_product_id'];
            $('.tbds-delete-product-options-product-title').html(product_title);
            $('.tbds-delete-product-options-button-delete').data('product_id', product_id).data('woo_product_id', woo_product_id);
            tbds_delete_product_options_show_delete();
        }
    });


    $('.tbds-delete-product-options-button-delete').on('click', function () {
        let $button = $(this);
        let product_id = $button.data()['product_id'];
        let woo_product_id = $button.data()['woo_product_id'];
        let $button_delete = $(`.tbds-button-delete[data-product_id="${product_id}"]`);
        $button_delete.addClass('loading');
        let $product_container = $(`#tbds-product-item-id-${product_id}`);
        $product_container.addClass('tbds-accordion-deleting').vi_accordion('close', 0);
        let delete_woo_product = $('.tbds-delete-product-options-delete-woo-product').prop('checked') ? 1 : 0;
        tbds_delete_product_options_hide();
        if (is_deleting) {
            queue.push({
                product_id: product_id,
                woo_product_id: woo_product_id,
                delete_woo_product: delete_woo_product,
            });
        } else {
            is_deleting = true;
            tbds_delete_product(product_id, woo_product_id, delete_woo_product);
        }
    });

    function tbds_delete_product(product_id, woo_product_id, delete_woo_product) {
        let $button_delete = $(`.tbds-button-delete[data-product_id="${product_id}"]`);
        let $product_container = $(`#tbds-product-item-id-${product_id}`);
        hide_message($product_container);
        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'tbds_delete_product',
                _ajax_nonce: tbdsParams.security,
                product_id: product_id,
                woo_product_id: woo_product_id,
                delete_woo_product: delete_woo_product,
            },
            success: function (response) {
                if (response.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = parseInt(imported_list_count_value - 1);
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }

                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $product_container.remove();
                        maybe_reload_page();
                    }, 300)
                } else {
                    show_message($product_container, 'negative', response.message);
                    $product_container.removeClass('tbds-accordion-deleting').vi_accordion('open', 0);
                }
            },
            error: function (err) {
                show_message($product_container, 'negative', err.statusText);
                $product_container.removeClass('tbds-accordion-deleting').vi_accordion('open', 0);
            },
            complete: function () {
                is_deleting = false;
                $button_delete.removeClass('loading');
                if (queue.length > 0) {
                    let current = queue.shift();
                    tbds_delete_product(current.product_id, current.woo_product_id, current.delete_woo_product);
                }
            }
        })

    }

    $('.tbds-button-override').on('click', function () {
        let button_override = $(this);
        let product_id = button_override.data()['product_id'];
        let product_title = button_override.data()['product_title'];
        let woo_product_id = button_override.data()['woo_product_id'];
        $('.tbds-delete-product-options-product-title').html(product_title);
        $('.tbds-delete-product-options-button-override').data('product_id', product_id).data('woo_product_id', woo_product_id);
        $('.tbds-delete-product-options-override-product-message').addClass('tbds-hidden');
        tbds_delete_product_options_show_override();
    });

    let override_product_data, override_product_id;
    $('.tbds-delete-product-options-override-product-new-close').on('click', function () {
        $('.tbds-delete-product-options-override-product-message').addClass('tbds-hidden').html('');
        $('.tbds-delete-product-options-override-product-new-wrap').addClass('tbds-hidden');
        $('.tbds-delete-product-options-button-override').html('Check').removeClass('tbds-checked');
        $('.tbds-delete-product-options-override-product').val('').focus();
        override_product_data = '';
        override_product_id = '';
    });

    $('.tbds-delete-product-options-button-override').on('click', function () {
        let $button_override = $(this);
        let override_product_url = $('#tbds-delete-product-options-override-product').val();
        let product_id = $button_override.data('product_id');
        let woo_product_id = $button_override.data('woo_product_id');
        let $current_button_override = $('.tbds-button-override[data-product_id="' + product_id + '"]');
        let step = 'check';

        if ($button_override.hasClass('tbds-checked')) {
            step = 'override';
        }

        if (step === 'check') {
            if (!override_product_url) {
                alert('Please enter url or ID of product you want to use to override current product with');
                return;
            }
        } else {
            if (!override_product_data && !override_product_id) {
                alert('Please enter product url to check.');
                return;
            }
        }

        $('.tbds-delete-product-options-override-product-message').addClass('tbds-hidden');
        $current_button_override.addClass('loading');
        $button_override.addClass('loading');

        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'tbds_override_product',
                _ajax_nonce: tbdsParams.security,
                product_id: product_id,
                woo_product_id: woo_product_id,
                override_product_url: override_product_url,
                override_product_data: override_product_data,
                override_product_id: override_product_id,
                step: step,
                replace_description: $('.tbds-delete-product-options-override-product-replace-description').prop('checked') ? 1 : 0,
            },
            success: function (response) {
                if (step === 'check') {
                    if (response.status === 'error') {
                        $('.tbds-delete-product-options-override-product-message').removeClass('tbds-hidden').html(response.message);
                    } else {
                        console.log(response.redirect_url)
                        window.open(response.redirect_url, '_blank');
                        // override_product_data = response.data;
                        // override_product_id = response.exist_product_id;
                        // $('.tbds-delete-product-options-override-product-new-wrap').removeClass('tbds-hidden');
                        // $('.tbds-delete-product-options-override-product-new-image').find('img').attr('src', response.image);
                        // $('.tbds-delete-product-options-override-product-new-title').html(response.title);
                        //
                        // if (response.message) {
                        //     $('.tbds-delete-product-options-override-product-message').removeClass('tbds-hidden').html(response.message);
                        // }
                        //
                        // if (response.status === 'success') {
                        //     $button_override.html(tbdsParams.override).addClass('tbds-checked');
                        // }
                    }
                } else {
                    if (response.status === 'success') {
                        let $product_container = $('#tbds-product-item-id-' + product_id);
                        $product_container.find('div.content').eq(0).prepend(response.data);
                        // $product_container.vi_accordion('close', 0);
                        $current_button_override.remove();
                        $product_container.find('.tbds-button-override-container').append(response.button_override_html);
                        tbds_delete_product_options_hide();
                    } else {
                        $button_override.html(tbdsParams.check).removeClass('tbds-checked');
                        if (response.message) {
                            $('.tbds-delete-product-options-override-product-message').removeClass('tbds-hidden').html(response.message);
                        }
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $current_button_override.removeClass('loading');
                $button_override.removeClass('loading');
            }
        })
    });

    $('.tbds-overlay').on('click', () => tbds_delete_product_options_hide());
    $('.tbds-delete-product-options-close').on('click', () => tbds_delete_product_options_hide());
    $('.tbds-delete-product-options-button-cancel').on('click', () => tbds_delete_product_options_hide());
    $('.tbds-accordion-store-url').on('click', e => e.stopPropagation());

    function tbds_delete_product_options_hide() {
        $('.tbds-delete-product-options-content-header-delete').addClass('tbds-hidden');
        $('.tbds-delete-product-options-button-delete').addClass('tbds-hidden').data('product_id', '').data('woo_product_id', '');
        $('.tbds-delete-product-options-delete-woo-product-wrap').addClass('tbds-hidden');
        $('.tbds-delete-product-options').addClass('tbds-delete-product-options-editing');
        $('.tbds-delete-product-options-container').addClass('tbds-hidden');
        tbds_enable_scroll();
        $('.tbds-delete-product-options-content-header-override').addClass('tbds-hidden');
        $('.tbds-delete-product-options-button-override').addClass('tbds-hidden').data('product_id', '').data('woo_product_id', '');
        $('.tbds-delete-product-options-override-product-wrap').addClass('tbds-hidden');
    }

    function tbds_delete_product_options_show_override() {
        $('.tbds-delete-product-options-content-header-override').removeClass('tbds-hidden');
        $('.tbds-delete-product-options-button-override').removeClass('tbds-hidden tbds-checked').html(tbdsParams.check);
        $('.tbds-delete-product-options-override-product-wrap').removeClass('tbds-hidden');
        $('.tbds-delete-product-options-override-product-new-image').find('img').attr('src', '');
        $('.tbds-delete-product-options-override-product-new-title').html('');
        $('.tbds-delete-product-options-override-product-new-wrap').addClass('tbds-hidden');
        tbds_delete_product_options_show();
        $('.tbds-delete-product-options-override-product').val('').focus();
    }

    function tbds_delete_product_options_show_delete() {
        $('.tbds-delete-product-options-content-header-delete').removeClass('tbds-hidden');
        $('.tbds-delete-product-options-button-delete').removeClass('tbds-hidden');
        $('.tbds-delete-product-options-delete-woo-product-wrap').removeClass('tbds-hidden');
        tbds_delete_product_options_show();
    }

    function tbds_delete_product_options_show() {
        $('.tbds-delete-product-options-container').removeClass('tbds-hidden');
        tbds_disable_scroll();
    }

    function tbds_enable_scroll() {
        let scrollTop = parseInt($('html').css('top'));
        $('html').removeClass('tbds-noscroll');
        $('html,body').scrollTop(-scrollTop);
    }

    function tbds_disable_scroll() {
        if ($(document).height() > $(window).height()) {
            let scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : $('body').scrollTop(); // Works for Chrome, Firefox, IE...
            $('html').addClass('tbds-noscroll').css('top', -scrollTop);
        }
    }

    function hide_message($parent) {
        $parent.find('.tbds-message').html('')
    }

    function show_message($parent, type, message) {
        $parent.find('.tbds-message').html(`<div class="vi-ui message ${type}"><div>${message}</div></div>`)
    }

    function maybe_reload_page() {
        if ($('.tbds-accordion').length === 0) {
            let url = new URL(document.location.href);
            url.searchParams.delete('tbds_search_woo_id');
            url.searchParams.delete('tbds_search_id');
            url.searchParams.delete('tbds_search');
            url.searchParams.delete('paged');
            document.location.href = url.href;
        }
    }
});

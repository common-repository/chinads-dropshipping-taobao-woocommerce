jQuery(document).ready($ => {
    'use strict';

    function parse_str(str, array) {
        const _fixStr = (str) => decodeURIComponent(str.replace(/\+/g, '%20'));
        const strArr = String(str).replace(/^&/, '').replace(/&$/, '').split('&');
        const sal = strArr.length;
        let i, j, ct, p, lastObj, obj, chr, tmp, key, value, postLeftBracketPos, keys, keysLen;
        const $global = (typeof window !== 'undefined' ? window : global);
        $global.$locutus = $global.$locutus || {};
        const $locutus = $global.$locutus;
        $locutus.php = $locutus.php || {};

        if (!array) array = $global;

        for (i = 0; i < sal; i++) {
            tmp = strArr[i].split('=');
            key = _fixStr(tmp[0]);
            value = (tmp.length < 2) ? '' : _fixStr(tmp[1]);

            if (key.includes('__proto__') || key.includes('constructor') || key.includes('prototype')) break;

            while (key.charAt(0) === ' ') key = key.slice(1);

            if (key.indexOf('\x00') > -1) {
                key = key.slice(0, key.indexOf('\x00'))
            }

            if (key && key.charAt(0) !== '[') {
                keys = [];
                postLeftBracketPos = 0;

                for (j = 0; j < key.length; j++) {
                    if (key.charAt(j) === '[' && !postLeftBracketPos) {
                        postLeftBracketPos = j + 1
                    } else if (key.charAt(j) === ']') {
                        if (postLeftBracketPos) {
                            if (!keys.length) keys.push(key.slice(0, postLeftBracketPos - 1));

                            keys.push(key.substr(postLeftBracketPos, j - postLeftBracketPos));
                            postLeftBracketPos = 0;

                            if (key.charAt(j + 1) !== '[') break;
                        }
                    }
                }

                if (!keys.length) keys = [key];

                for (j = 0; j < keys[0].length; j++) {
                    chr = keys[0].charAt(j);

                    if (chr === ' ' || chr === '.' || chr === '[') {
                        keys[0] = keys[0].substr(0, j) + '_' + keys[0].substr(j + 1)
                    }

                    if (chr === '[') break;
                }

                obj = array;

                for (j = 0, keysLen = keys.length; j < keysLen; j++) {
                    key = keys[j].replace(/^['"]/, '').replace(/['"]$/, '');
                    lastObj = obj;
                    if ((key === '' || key === ' ') && j !== 0) {
                        // Insert new dimension
                        ct = -1
                        for (p in obj) {
                            if (obj.hasOwnProperty(p)) {
                                if (+p > ct && p.match(/^\d+$/g)) {
                                    ct = +p
                                }
                            }
                        }
                        key = ct + 1
                    }

                    // if primitive value, replace with object
                    if (Object(obj[key]) !== obj[key]) obj[key] = {};

                    obj = obj[key];
                }
                lastObj[key] = value;
            }
        }
    }

    let queue = [];
    let is_importing = false;
    let is_current_page_focus = false;
    /*Set paged to 1 before submitting*/
    $('.tablenav-pages').find('.current-page').on('focus', function (e) {
        is_current_page_focus = true;
    }).on('blur', function (e) {
        is_current_page_focus = false;
    });
    $('.search-box').find('input[type="submit"]').on('click', function () {
        let $form = $(this).closest('form');
        if (!is_current_page_focus) {
            $form.find('.current-page').val(1);
        }
    });
    $('.vi-ui.tabular.menu .item').viTab();
    $('.vi-ui.accordion').vi_accordion('refresh');
    // $('.vi-ui.checkbox').checkbox();
    $('.ui-sortable').sortable();
    $('select.vi-ui.dropdown').not('.tbds-accordion-bulk-actions,.tbds-modal-popup-set-shipping-class-select,.tbds-import-data-shipping-class,.tbds-import-data-tags,.tbds-modal-popup-set-tags-select').viDropdown();
    $('.tbds-accordion-bulk-actions').viDropdown({placeholder: 'auto'});
    $('.tbds-modal-popup-set-shipping-class-select,.tbds-import-data-shipping-class').viDropdown({placeholder: ''});
    $('.tbds-import-data-tags,.tbds-modal-popup-set-tags-select').viDropdown({allowAdditions: true});
    $('.tbds-button-view-and-edit').on('click', function (e) {
        e.stopPropagation();
    });

    /*Set default categories*/
    $('.tbds-import-data-categories,.tbds-modal-popup-set-categories-select').viDropdown({
        onAdd: function (value, text, $choice) {
            $(this).find('a.ui.label').map(function () {
                let $option = $(this);
                $option.html($option.html().replace(/&nbsp;/g, ''));
            })
        }
    });
    if (tbdsParams.product_categories) {
        $('.tbds-import-data-categories').viDropdown('set exactly', tbdsParams.product_categories).trigger('change');
    }

    /**
     * Filter product attributes
     */
    $('body').on('click', '.tbds-attribute-filter-item', function (e) {
        let $button = $(this);
        let selected = [];
        let $container = $button.closest('table');
        let $attribute_filters = $container.find('.tbds-attribute-filter-list');
        let $attribute_filter = $attribute_filters.eq(0);
        let current_filter_slug = $attribute_filter.data('attribute_slug');
        if ($button.hasClass('tbds-attribute-filter-item-active')) {
            $button.removeClass('tbds-attribute-filter-item-active');
        } else {
            $button.addClass('tbds-attribute-filter-item-active');
        }
        let $variations_rows = $container.find('.tbds-product-variation-row');
        let $active_filters = $attribute_filter.find('.tbds-attribute-filter-item-active');
        let active_variations = [];
        if ($active_filters.length > 0) {
            $active_filters.map(function () {
                selected.push($(this).data('attribute_value'));
            });
            for (let $i = 0; $i < $variations_rows.length; $i++) {
                let $current_attribute = $variations_rows.eq($i).find('.tbds-import-data-variation-attribute[data-attribute_slug="' + current_filter_slug + '"]');
                if (selected.indexOf($current_attribute.data('attribute_value')) > -1) {
                    active_variations[$i] = 1;
                } else {
                    active_variations[$i] = 0;
                }
            }
        } else {
            for (let $i = 0; $i < $variations_rows.length; $i++) {
                active_variations[$i] = 1;
            }
        }

        if ($attribute_filters.length > 1) {
            for (let $j = 1; $j < $attribute_filters.length; $j++) {
                $attribute_filter = $attribute_filters.eq($j);
                current_filter_slug = $attribute_filter.data('attribute_slug');
                $active_filters = $attribute_filter.find('.tbds-attribute-filter-item-active');
                if ($active_filters.length > 0) {
                    $active_filters.map(function () {
                        selected.push($(this).data('attribute_value'));
                    });
                    for (let $i = 0; $i < $variations_rows.length; $i++) {
                        let $current_attribute = $variations_rows.eq($i).find('.tbds-import-data-variation-attribute[data-attribute_slug="' + current_filter_slug + '"]');
                        if (selected.indexOf($current_attribute.data('attribute_value')) < 0) {
                            active_variations[$i] = 0;
                        }
                    }
                }
            }
        }
        let variations_count = 0;
        for (let $i = 0; $i < $variations_rows.length; $i++) {
            let $current_variation = $variations_rows.eq($i);
            if (active_variations[$i] == 1) {
                $current_variation.removeClass('tbds-variation-filter-inactive');
                if ($current_variation.find('.tbds-variation-enable').prop('checked')) {
                    variations_count++;
                }
            } else {
                $current_variation.addClass('tbds-variation-filter-inactive');
            }
        }
        let $current_container = $button.closest('form');
        $current_container.find('.tbds-selected-variation-count').html(variations_count);
    });

    /**
     * Set product featured image
     */
    $('body').on('click', '.tbds-set-product-image', function (e) {
        e.stopPropagation();
        let $button = $(this);
        let container = $button.closest('form');
        let $product_image_container = container.find('.tbds-product-image');
        let $gallery_item = $button.closest('.tbds-product-gallery-item');
        let $product_gallery = $button.closest('.tbds-product-gallery');
        if ($gallery_item.hasClass('tbds-is-product-image')) {
            $gallery_item.removeClass('tbds-is-product-image');
            $product_image_container.removeClass('tbds-selected-item');
            $product_image_container.find('input[type="hidden"]').val('');
        } else {
            if (!$gallery_item.hasClass('tbds-selected-item')) {
                $gallery_item.click();
            }

            if (!$product_image_container.hasClass('tbds-selected-item')) {
                $product_image_container.addClass('tbds-selected-item');
            }
            $product_gallery.find('.tbds-product-gallery-item').removeClass('tbds-is-product-image');
            $gallery_item.addClass('tbds-is-product-image');
            let product_image_url = $gallery_item.find('img').data('image_src');

            $(this).closest('.tbds-accordion').find('.tbds-accordion-product-image').attr('src', product_image_url);
            $product_image_container.find('img').attr('src', product_image_url);
            $product_image_container.find('input[type="hidden"]').val(product_image_url);
        }

    });

    add_keyboard_event();

    /**
     * Support ESC(cancel) and Enter(OK) key
     */
    function add_keyboard_event() {
        $(document).on('keydown', function (e) {
            if (!$('.tbds-set-price-container').hasClass('tbds-hidden')) {
                if (e.keyCode == 13) {
                    $('.tbds-set-price-button-set').click();
                } else if (e.keyCode == 27) {
                    $('.tbds-overlay').click();
                }
            } else if (!$('.tbds-override-product-options-container').hasClass('tbds-hidden')) {
                if (e.keyCode == 13) {
                    $('.tbds-override-product-options-button-override').click();
                } else if (e.keyCode == 27) {
                    $('.tbds-override-product-overlay').click();
                }
            }
        });
    }

    count_selected_variations();
    let current_focus_checkbox;

    /**
     * Count currently selected variations
     */
    function count_selected_variations() {
        $('body').on('click', '.tbds-variations-bulk-enable', function () {
            let $current_container = $(this).closest('form');
            let selected = 0;
            if ($(this).prop('checked')) {
                selected = $current_container.find('.tbds-product-variation-row').length - $current_container.find('.tbds-variation-filter-inactive').length;
                $current_container.find('.tbds-variations-bulk-select-image').prop('checked', true).trigger('change');
            } else {
                $current_container.find('.tbds-import-data-variation-default').prop('checked', false);
                $current_container.find('.tbds-variations-bulk-select-image').prop('checked', false).trigger('change');
            }
            $current_container.find('.tbds-selected-variation-count').html(selected);
        }).on('click', '.tbds-variation-enable', function (e) {
            let $current_select = $(this);
            let $current_container = $current_select.closest('form');
            let prev_select = $current_container.find('.tbds-variation-enable').index(current_focus_checkbox);
            let selected = 0;
            if (e.shiftKey) {
                let current_index = $current_container.find('.tbds-variation-enable').index($current_select);
                if ($current_select.prop('checked')) {
                    if (prev_select < current_index) {
                        for (let i = prev_select; i <= current_index; i++) {
                            $current_container.find('.tbds-variation-enable').eq(i).prop('checked', true)
                        }
                    } else {
                        for (let i = current_index; i <= prev_select; i++) {
                            $current_container.find('.tbds-variation-enable').eq(i).prop('checked', true)
                        }
                    }
                } else {
                    if (prev_select < current_index) {
                        for (let i = prev_select; i <= current_index; i++) {
                            $current_container.find('.tbds-variation-enable').eq(i).prop('checked', false)
                        }
                    } else {
                        for (let i = current_index; i <= prev_select; i++) {
                            $current_container.find('.tbds-variation-enable').eq(i).prop('checked', false)
                        }
                    }
                }
            }
            $current_container.find('.tbds-variation-enable').map(function () {
                let $current_row = $(this).closest('tr');
                if ($(this).prop('checked') && !$current_row.hasClass('tbds-variation-filter-inactive')) {
                    selected++;
                    $current_row.find('.tbds-variation-image').removeClass('tbds-selected-item').click();
                } else {
                    $current_row.find('.tbds-variation-image').addClass('tbds-selected-item').click();
                    $current_row.find('.tbds-import-data-variation-default').prop('checked', false);
                }
            });

            $current_container.find('.tbds-selected-variation-count').html(selected);
            current_focus_checkbox = $(this);
        })
    }

    /**
     * Bulk select variations
     */
    $('body').on('change', '.tbds-variations-bulk-enable', function () {
        let product = $(this).closest('form');
        product.find('.tbds-product-variation-row:not(.tbds-variation-filter-inactive) .tbds-variation-enable').prop('checked', $(this).prop('checked'));
    });

    /**
     * Bulk select images
     */
    $('body').on('change', '.tbds-variations-bulk-select-image', function () {
        let button_bulk = $(this);
        let product = button_bulk.closest('form');
        let image_wrap = product.find('.tbds-variation-image');
        if (button_bulk.prop('checked')) {
            image_wrap.addClass('tbds-selected-item');
        } else {
            image_wrap.removeClass('tbds-selected-item');
        }
        image_wrap.map(function () {
            let current = $(this);
            if (button_bulk.prop('checked')) {
                current.find('input[type="hidden"]').val(current.find('.tbds-import-data-variation-image').attr('src'));
            } else {
                current.find('input[type="hidden"]').val('');
            }
        })

    });

    function hide_message($parent) {
        $parent.find('.tbds-message').html('')
    }

    function show_message($parent, type, message) {
        $parent.find('.tbds-message').html(`<div class="vi-ui message ${type}"><div>${message}</div></div>`)
    }

    let $import_list_count = $('#toplevel_page_woo-alidropship').find('.current').find('.tbds-import-list-count');
    let $imported_list_count = $('.tbds-imported-list-count');
    /**
     * Empty import list
     */
    $('.tbds-button-empty-import-list').on('click', function (e) {
        if (!confirm('Do you want to delete all products(except overriding products) from your Import list?')) {
            e.preventDefault();
            return false;
        }
    });
    let is_bulk_remove = false;
    /**
     * Remove product
     */
    $('.tbds-button-remove').on('click', function (e) {
        e.stopPropagation();
        let $button_remove = $(this);
        let product_id = $button_remove.data('product_id');
        let $product_container = $('#tbds-product-item-id-' + product_id);
        if ($button_remove.closest('.tbds-button-view-and-edit').find('.loading').length === 0 && (is_bulk_remove || confirm(tbdsParams.i18n_remove_product_confirm))) {
            $product_container.vi_accordion('close', 0).addClass('tbds-accordion-removing');
            $button_remove.addClass('loading');
            hide_message($product_container);
            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_remove',
                    _ajax_nonce: tbdsParams.security,
                    product_id: product_id,
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let import_list_count_value = parseInt($import_list_count.html());
                        if (import_list_count_value > 0) {
                            let current_count = parseInt(import_list_count_value - 1);
                            $import_list_count.html(current_count);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                        }
                        $product_container.fadeOut(300);
                        setTimeout(function () {
                            $product_container.remove();
                            maybe_reload_page();
                            maybe_hide_bulk_actions();
                        }, 300)
                    } else {
                        $product_container.vi_accordion('open', 0).removeClass('tbds-accordion-removing');
                        show_message($product_container, 'negative', response.message ? response.message : 'Error');
                    }
                },
                error: function (err) {
                    console.log(err);
                    $product_container.vi_accordion('open', 0).removeClass('tbds-accordion-removing');
                    show_message($product_container, 'negative', err.statusText);
                },
                complete: function () {
                    $button_remove.removeClass('loading');
                }
            })
        }
    });

    /**
     * Import product
     */
    $('.tbds-button-import').on('click', function (e) {
        e.stopPropagation();
        let newFormData = {};
        let $button_import = $(this);
        let $button_container = $button_import.closest('.tbds-button-view-and-edit');
        let product_id = $button_import.data('product_id');
        let $product_container = $('#tbds-product-item-id-' + product_id);
        if ($product_container.hasClass('tbds-accordion-importing') || $product_container.hasClass('tbds-accordion-removing') || $product_container.hasClass('tbds-accordion-splitting')) {
            return;
        }
        let $form = $product_container.find('.tbds-product-container');
        // let data = $form.serializeArray();
        let form_data = $form.find('.vi-ui.tab').not('.tbds-variations-tab').find('input,select,textarea').serializeArray();
        let description = $('#wp-tbds-product-description-' + product_id + '-wrap').hasClass('tmce-active') ? tinyMCE.get('tbds-product-description-' + product_id).getContent() : $('#tbds-product-description-' + product_id).val();
        form_data.push({name: 'tbds_product[' + product_id + '][description]', value: description});
        let selected = {};
        if ($form.find('.tbds-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            $form.find('.tbds-variation-enable').map(function () {
                let $row = $(this).closest('.tbds-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('tbds-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                    let variation_data = $row.find('input,select,textarea').serializeArray();
                    if (variation_data.length > 0) {
                        /*only send data of selected variations*/
                        for (let v_i = 0; v_i < variation_data.length; v_i++) {
                            form_data.push(variation_data[v_i]);
                        }
                    }
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }
        form_data.push({name: 'z_check_max_input_vars', value: 1});
        form_data = $.param(form_data);

        parse_str(form_data, newFormData);

        if (selected[product_id].length === 0) {
            alert(tbdsParams.i18n_empty_variation_error);
            return;
        }
        let empty_price_error = false, sale_price_error = false;
        $form.find('.tbds-import-data-variation-sale-price').removeClass('tbds-price-error');
        $form.find('.tbds-import-data-variation-regular-price').removeClass('tbds-price-error');
        for (let i = 0; i < $form.find('.tbds-import-data-variation-sale-price').length; i++) {
            let sale_price = $form.find('.tbds-import-data-variation-sale-price').eq(i);
            let regular_price = $form.find('.tbds-import-data-variation-regular-price').eq(i);
            if (!parseFloat(regular_price.val())) {
                empty_price_error = true;
                regular_price.addClass('tbds-price-error')
            } else if (parseFloat(sale_price.val()) > parseFloat(regular_price.val())) {
                sale_price_error = true;
                sale_price.addClass('tbds-price-error')
            }
        }

        if (empty_price_error) {
            alert(tbdsParams.i18n_empty_price_error);
            return;
        } else if (sale_price_error) {
            alert(tbdsParams.i18n_sale_price_error);
            return;
        }

        $button_import.addClass('loading');

        if (!is_importing) {
            $product_container.vi_accordion('close', 0).addClass('tbds-accordion-importing');
            is_importing = true;
            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_import',
                    _ajax_nonce: tbdsParams.security,
                    form_data: newFormData,
                    selected: selected,
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let import_list_count_value = parseInt($import_list_count.html());
                        if (import_list_count_value > 0) {
                            import_list_count_value--;
                            $import_list_count.html(import_list_count_value);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + import_list_count_value);
                        } else {
                            $import_list_count.html(0);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + 0);
                        }
                        let imported_list_count_value = parseInt($imported_list_count.html());
                        imported_list_count_value++;
                        $imported_list_count.html(imported_list_count_value);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + imported_list_count_value);
                        if ($('.tbds-button-import').length === 0) {
                            $('.tbds-button-import-all').remove();
                        }
                        $button_container.append(response.button_html);
                        $button_container.find('.tbds-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.tbds-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        $button_import.removeClass('loading');
                        show_message($product_container, 'negative', response.message ? response.message : 'Error');
                    }
                },
                error: function (err) {
                    console.log(err)
                    $button_import.removeClass('loading');
                    show_message($product_container, 'negative', err.statusText);
                },
                complete: function () {
                    is_importing = false;
                    $product_container.vi_accordion('open', 0).removeClass('tbds-accordion-importing');
                    if (queue.length > 0) {
                        queue.shift().click();
                    } else if ($('.tbds-button-import-all').hasClass('loading')) {
                        $('.tbds-button-import-all').removeClass('loading')
                    }
                }
            })
        } else {
            queue.push($button_import);
        }
    });

    /**
     * Bulk import
     */
    $('.tbds-button-import-all').on('click', function () {
        let $button_import = $(this);

        if ($button_import.hasClass('loading')) {
            return;
        }

        if (!confirm(tbdsParams.i18n_import_all_confirm)) {
            return;
        }

        $('.tbds-button-import').not('.loading').map(function () {
            if ($(this).closest('.tbds-button-view-and-edit').find('.loading').length === 0) {
                queue.push($(this));
                $(this).addClass('loading');
            }
        });

        if (queue.length > 0) {
            if (!is_importing) {
                queue.shift().click();
            }
            $button_import.addClass('loading');
        } else {
            alert(tbdsParams.i18n_not_found_error);
        }
    });

    let found_items, check_orders;

    /**
     * Override product
     */
    $('.tbds-button-override').on('click', function (e) {
        e.stopPropagation();
        let $button_import = $(this);
        let product_id = $button_import.data('product_id');
        let form = $button_import.closest('.tbds-accordion').find('.tbds-product-container');
        let selected = {};

        if (form.find('.tbds-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            form.find('.tbds-variation-enable').map(function () {
                let $row = $(this).closest('.tbds-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('tbds-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }

        if (selected[product_id].length === 0) {
            alert(tbdsParams.i18n_empty_variation_error);
            return;
        }

        let empty_price_error = false,
            sale_price_error = false,
            $container = $button_import.closest('.tbds-accordion').find('.tbds-product-container');

        $container.find('.tbds-import-data-variation-sale-price').removeClass('tbds-price-error');
        $container.find('.tbds-import-data-variation-regular-price').removeClass('tbds-price-error');

        for (let i = 0; i < $container.find('.tbds-import-data-variation-sale-price').length; i++) {
            let sale_price = $container.find('.tbds-import-data-variation-sale-price').eq(i);
            let regular_price = $container.find('.tbds-import-data-variation-regular-price').eq(i);
            if (!parseFloat(regular_price.val())) {
                empty_price_error = true;
                regular_price.addClass('tbds-price-error')
            } else if (parseFloat(sale_price.val()) > parseFloat(regular_price.val())) {
                sale_price_error = true;
                sale_price.addClass('tbds-price-error')
            }
        }

        if (empty_price_error) {
            alert(tbdsParams.i18n_empty_price_error);
            return;
        } else if (sale_price_error) {
            alert(tbdsParams.i18n_sale_price_error);
            return;
        }

        let $override_woo_id = $container.find('.tbds-override-woo-id');
        if ($override_woo_id.val()) {
            $('.tbds-override-product-title').html($override_woo_id.find(':selected').html());
        } else {
            $('.tbds-override-product-title').html($button_import.closest('.tbds-accordion').find('.tbds-override-product-product-title').html());
        }

        $('.tbds-override-product-options-button-override').data('product_id', product_id).data('override_product_id', $button_import.data('override_product_id'));

        tbds_override_product_show($button_import);
    });

    $('.tbds-override-woo-id').on('change', function () {
        let $override_woo_id = $(this), $container = $override_woo_id.closest('.tbds-accordion'),
            $button_import = $container.find('.tbds-button-import'),
            $button_override = $container.find('.tbds-button-override');
        if ($(this).val()) {
            $button_import.addClass('tbds-hidden');
            $button_override.removeClass('tbds-hidden');
        } else {
            $button_import.removeClass('tbds-hidden');
            $button_override.addClass('tbds-hidden');
        }
    });

    $('.tbds-override-product-options-override-keep-product').on('change', function () {
        let $button = $(this),
            $message = $button.closest('.tbds-override-product-options-container').find('.tbds-override-product-remove-warning'),
            $override_find_in_orders = $('.tbds-override-product-options-content-body-row-override-find-in-orders');
        if ($button.prop('checked')) {
            $message.fadeOut(100);
            $override_find_in_orders.hide();
        } else {
            $message.fadeIn(100);
            $override_find_in_orders.show();
        }
    }).trigger('change');

    /**
     * Confirm Override product
     */
    $('.tbds-override-product-options-button-override').on('click', function () {
        let $button = $(this);
        let product_id = $button.data('product_id');
        let override_product_id = $button.data('override_product_id');
        let $button_import = $('.tbds-button-override[data-product_id="' + product_id + '"]');
        let $button_container = $button_import.closest('.tbds-button-view-and-edit');
        let $product_container = $('#tbds-product-item-id-' + product_id);
        let $form = $product_container.find('.tbds-product-container');
        // let data = $form.serializeArray();
        let form_data = $form.find('.vi-ui.tab').not('.tbds-variations-tab').find('input,select,textarea').serializeArray();
        let description = $('#wp-tbds-product-description-' + product_id + '-wrap').hasClass('tmce-active') ? tinyMCE.get('tbds-product-description-' + product_id).getContent() : $('#tbds-product-description-' + product_id).val();
        form_data.push({name: 'tbds_product[' + product_id + '][description]', value: description});
        let selected = {};

        if ($form.find('.tbds-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            $form.find('.tbds-variation-enable').map(function () {
                let $row = $(this).closest('.tbds-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('tbds-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                    let variation_data = $row.find('input,select,textarea').serializeArray();
                    if (variation_data.length > 0) {
                        /*only send data of selected variations*/
                        for (let v_i = 0; v_i < variation_data.length; v_i++) {
                            form_data.push(variation_data[v_i]);
                        }
                    }
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }

        form_data.push({name: 'z_check_max_input_vars', value: 1});
        form_data = $.param(form_data);

        let newFormData = {};
        let replace_items = {};

        parse_str(form_data, newFormData);

        if (check_orders) {
            $('.tbds-override-order-container').map(function () {
                replace_items[$(this).data('replace_item_id')] = $(this).find('.tbds-override-with').val();
            })
        }

        $button_import.addClass('loading');
        $button.addClass('loading');
        let override_hide = $('.tbds-override-product-options-override-hide').prop('checked') ? 1 : 0;

        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'tbds_override',
                _ajax_nonce: tbdsParams.security,
                form_data: newFormData,
                selected: selected,
                override_product_id: override_product_id,
                check_orders: check_orders,
                replace_items: replace_items,
                found_items: found_items,
                override_woo_id: $product_container.find('.tbds-override-woo-id').val(),
                override_keep_product: $('.tbds-override-product-options-override-keep-product').prop('checked') ? 1 : 0,
                override_title: $('.tbds-override-product-options-override-title').prop('checked') ? 1 : 0,
                override_images: $('.tbds-override-product-options-override-images').prop('checked') ? 1 : 0,
                override_description: $('.tbds-override-product-options-override-description').prop('checked') ? 1 : 0,
                override_find_in_orders: $('.tbds-override-product-options-override-find-in-orders').prop('checked') ? 1 : 0,
                override_hide: override_hide,
            },
            success: function (response) {
                if (check_orders) {
                    if (response.status === 'success') {
                        tbds_override_product_hide();
                        $button_container.append(response.button_html);
                        $button_container.find('.tbds-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.tbds-accordion-bulk-item-check').remove();
                        $product_container.find('.tbds-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        alert(response.message);
                    }

                } else {
                    if (response.status === 'checked') {
                        let $replace_order = $('.tbds-override-product-options-content-body-override-old');
                        $replace_order.removeClass('tbds-hidden').html(response.replace_order_html);
                        check_orders = 1;
                        found_items = response.found_items;
                        if (override_hide === 1) {
                            $('.tbds-override-product-options-content-body-option').remove();
                        } else {
                            $('.tbds-override-product-options-content-body-option').addClass('tbds-hidden');
                        }
                    } else if (response.status === 'success') {
                        tbds_override_product_hide();
                        $button_container.append(response.button_html);
                        $button_container.find('.tbds-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.tbds-accordion-bulk-item-check').remove();
                        $product_container.find('.tbds-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        alert(response.message);
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $button_import.removeClass('loading');
                $button.removeClass('loading');
            }
        })
    });

    $(document).on('change', '.tbds-override-with-attributes>select', function (e) {
        if ($('.tbds-override-product-options-override-keep-product').prop('checked') || $('.tbds-override-product-options-container').hasClass('tbds-override-product-options-container-reimport') || $('.tbds-override-product-options-container').hasClass('tbds-override-product-options-container-map-existing')) {
            let $current = $(this);
            let selected = $current.val();
            let prev_value = $current.data('prev_value');
            $('.tbds-override-with-attributes>select').not($(this)).map(function () {
                let $current = $(this);
                if (selected) {
                    $current.find(`option[value="${selected}"]`).prop('disabled', true);
                }
                if (prev_value) {
                    $current.find(`option[value="${prev_value}"]`).prop('disabled', false);
                }
            });
            $current.data('prev_value', selected);
        }
    });

    /**
     * Bulk set sale price
     */
    $('.tbds-import-data-variation-sale-price').on('change', function () {
        let button = $(this);
        let container_row = button.closest('tr');
        let current_value = parseFloat(button.val());
        let profit = container_row.find('.tbds-import-data-variation-profit');
        let cost = container_row.find('.tbds-import-data-variation-cost');
        let profit_value = 0;
        if (current_value) {
            profit_value = current_value - parseFloat(cost.html());
        } else {
            profit_value = parseFloat(container_row.find('.tbds-import-data-variation-regular-price').val()) - parseFloat(cost.html());
        }
        profit.html(roundResult(profit_value));
    });

    /**
     * Bulk set regular price
     */
    $('.tbds-import-data-variation-regular-price').on('change', function () {
        let button = $(this);
        let container_row = button.closest('tr');
        let sale_price = parseFloat(container_row.find('.tbds-import-data-variation-sale-price').val());
        let profit = container_row.find('.tbds-import-data-variation-profit');
        let cost = container_row.find('.tbds-import-data-variation-cost');
        let profit_value = 0;
        if (!sale_price) {
            profit_value = parseFloat(button.val()) - parseFloat(cost.html());
            profit.html(roundResult(profit_value));
        }
    });

    /**
     * Bulk set price confirm
     */
    $('body').on('click', '.tbds-set-price', function () {
        let $button = $(this);
        $button.addClass('tbds-set-price-editing');
        let $container = $('.tbds-modal-popup-container');
        $container.attr('class', 'tbds-modal-popup-container tbds-modal-popup-container-set-price');
        let $content = $('.tbds-modal-popup-content-set-price');
        $content.find('.tbds-modal-popup-header').find('h2').html('Set ' + $button.data('set_price').replace(/_/g, ' '));
        tbds_set_price_show();
    });

    /**
     * Select gallery images
     */
    $('body').on('click', '.tbds-product-gallery-item', function () {
        let current = $(this);
        let image = current.find('.tbds-product-gallery-image');
        let container = current.closest('form');
        let gallery_container = container.find('.tbds-product-gallery');
        let $product_image_container = container.find('.tbds-product-image');
        if (current.hasClass('tbds-selected-item')) {
            if (current.hasClass('tbds-is-product-image')) {
                current.removeClass('tbds-is-product-image');
                current.find('tbds-set-product-image').click();
                $product_image_container.removeClass('tbds-selected-item').find('input[type="hidden"]').val('');
            }
            current.removeClass('tbds-selected-item').find('input[type="hidden"]').val('');
        } else {
            current.addClass('tbds-selected-item').find('input[type="hidden"]').val(image.data('image_src'));
        }
        container.find('.tbds-selected-gallery-count').html(gallery_container.find('.tbds-selected-item').length);
    });

    /**
     * Select product image
     */
    $('body').on('click', '.tbds-product-image', function () {
        let image_src = $(this).find('.tbds-import-data-image').attr('src');
        let $container = $(this).closest('form');
        if (image_src) {
            let $gallery_item = $container.find('.tbds-product-gallery-image[data-image_src="' + image_src + '"]').closest('.tbds-product-gallery-item');
            $gallery_item.find('.tbds-set-product-image').click();
        }
    });

    /**
     * Select default variation
     */
    $('body').on('click', '.tbds-import-data-variation-default', function () {
        let $current = $(this);
        if ($current.prop('checked')) {
            let $enable = $current.closest('tr').find('.tbds-variation-enable');
            if (!$enable.prop('checked')) {
                $enable.click();
            }
        }
    });

    /**
     * Select variation image
     */
    $('body').on('click', '.tbds-variation-image', function () {
        let $current = $(this);
        if ($current.hasClass('tbds-selected-item')) {
            $current.removeClass('tbds-selected-item').find('input[type="hidden"]').val('');
        } else {
            $current.addClass('tbds-selected-item').find('input[type="hidden"]').val($current.find('img').attr('src'));
            $current.closest('tr').find('.tbds-variation-enable').prop('checked', true);
        }
    });

    $('.tbds-overlay').on('click', function () {
        tbds_set_price_hide()
    });

    $('.tbds-modal-popup-close').on('click', function () {
        tbds_set_price_hide()
    });

    $('.tbds-set-price-button-cancel').on('click', function () {
        tbds_set_price_hide()
    });

    $('.tbds-set-price-amount').on('change', function () {
        let price = parseFloat($(this).val());
        if (isNaN(price)) {
            price = 0;
        }
        $(this).val(price);
    });

    $('.tbds-set-price-button-set').on('click', function () {
        let button = $(this);
        let action = $('.tbds-set-price-action').val(),
            amount = parseFloat($('.tbds-set-price-amount').val());
        let editing = $('.tbds-set-price-editing');
        let container = editing.closest('table');
        let target_field;
        if (editing.data('set_price') === 'sale_price') {
            target_field = container.find('.tbds-import-data-variation-sale-price');
        } else {
            target_field = container.find('.tbds-import-data-variation-regular-price');
        }
        if (target_field.length > 0) {
            switch (action) {
                case 'set_new_value':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.tbds-product-variation-row');
                        if (!$row.hasClass('tbds-variation-filter-inactive') && $row.find('.tbds-variation-enable').prop('checked')) {
                            $price.val(amount);
                        }
                    });
                    break;
                case 'increase_by_fixed_value':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.tbds-product-variation-row'),
                            current_amount = parseFloat($price.val());
                        if (!$row.hasClass('tbds-variation-filter-inactive') && $row.find('.tbds-variation-enable').prop('checked')) {
                            $price.val(current_amount + amount);
                        }
                    });
                    break;
                case 'increase_by_percentage':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.tbds-product-variation-row'),
                            current_amount = parseFloat($price.val());
                        if (!$row.hasClass('tbds-variation-filter-inactive') && $row.find('.tbds-variation-enable').prop('checked')) {
                            $price.val((1 + amount / 100) * current_amount);
                        }
                    });
                    break;
            }
        }
        container.find('.tbds-import-data-variation-profit').map(function () {
            let $profit = $(this), $row = $profit.closest('tr');
            if (!$row.hasClass('tbds-variation-filter-inactive') && $row.find('.tbds-variation-enable').prop('checked')) {
                let sale_price = $row.find('.tbds-import-data-variation-sale-price');
                let regular_price = $row.find('.tbds-import-data-variation-regular-price');
                let cost = $row.find('.tbds-import-data-variation-cost');
                let sale_price_v = parseFloat(sale_price.val()), regular_price_v = parseFloat(regular_price.val()),
                    cost_v = parseFloat(cost.html()), profit_v;
                if (sale_price_v) {
                    profit_v = roundResult(sale_price_v - cost_v);
                } else {
                    profit_v = roundResult(regular_price_v - cost_v);
                }
                $profit.html(profit_v);
            }
        });
        tbds_set_price_hide()
    });

    $('.tbds-accordion-store-url').on('click', function (e) {
        e.stopPropagation();
    });

    $('.tbds-lazy-load').on('click', function () {
        let $tab = $(this);
        let tab_data = $tab.data('tab');
        if (!$tab.hasClass('tbds-lazy-load-loaded')) {
            $tab.addClass('tbds-lazy-load-loaded');
            let $tab_data = $('.tbds-lazy-load-tab-data[data-tab="' + tab_data + '"]');
            $tab_data.find('img').map(function () {
                let image_src = $(this).data('image_src');
                if (image_src) {
                    $(this).attr('src', image_src);
                }
            })
        }
    });

    /**
     * Load variations dynamically
     */
    $('.tbds-variations-tab-menu').on('click', function () {
        let $tab = $(this);
        let $overlay = $tab.closest('.tbds-accordion').find('.tbds-product-overlay');
        let tab_data = $tab.data('tab');
        let $tab_data = $('.tbds-variations-tab[data-tab="' + tab_data + '"]');
        let $variations_table = $tab_data.find('.tbds-variations-table');
        if (!$tab_data.hasClass('tbds-variations-tab-loaded')) {
            $overlay.removeClass('tbds-hidden');
            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'GET',
                dataType: 'JSON',
                data: {
                    action: 'tbds_load_variations_table',
                    _ajax_nonce: tbdsParams.security,
                    product_id: $tab_data.data('product_id'),
                    product_index: tab_data.substring(11),
                },
                success: function (response) {
                    let variations_table;
                    if (response.status === 'success') {
                        $tab_data.addClass('tbds-variations-tab-loaded');
                        variations_table = response.data;
                        if (response.hasOwnProperty('split_option') && response.split_option) {
                            $variations_table.closest('.tbds-variations-tab').find('.tbds-button-split-container').html(response.split_option);
                        }
                    } else {
                        variations_table = `<div class="vi-ui negative message">${response.data}</div>`;
                    }
                    $variations_table.html(variations_table).find('.vi-ui.dropdown').viDropdown({
                        fullTextSearch: true,
                        forceSelection: false,
                        selectOnKeydown: false
                    });
                },
                error: function (err) {
                    console.log(err);
                    $variations_table.html(`<div class="vi-ui negative message">ERROR</div>`);
                },
                complete: function () {
                    $overlay.addClass('tbds-hidden');
                }
            })
        }
    });

    function tbds_set_price_hide() {
        $('.tbds-set-price').removeClass('tbds-set-price-editing');
        $('.tbds-attributes-attribute-removing').removeClass('tbds-attributes-attribute-removing');
        $('.tbds-modal-popup-container').addClass('tbds-hidden');
        tbds_enable_scroll()
    }

    function tbds_set_price_show() {
        $('.tbds-modal-popup-container').removeClass('tbds-hidden');
        tbds_disable_scroll();
    }

    $('.tbds-override-product-overlay').on('click', function () {
        tbds_override_product_hide()
    });
    $('.tbds-override-product-options-close').on('click', function () {
        tbds_override_product_hide()
    });
    $('.tbds-override-product-options-button-cancel').on('click', function () {
        tbds_override_product_hide()
    });

    function tbds_override_product_hide() {
        $('.tbds-override-product-options-container').addClass('tbds-hidden');
        found_items = [];
        check_orders = 0;
        tbds_enable_scroll()
    }

    function tbds_override_product_show($button_import) {
        let $container = $('.tbds-override-product-options-container');

        if ($button_import.hasClass('tbds-button-map-existing')) {
            $container.addClass('tbds-override-product-options-container-map-existing');
        } else {
            $container.removeClass('tbds-override-product-options-container-map-existing');
        }

        if ($button_import.hasClass('tbds-button-reimport')) {
            $container.addClass('tbds-override-product-options-container-reimport');
        } else {
            $container.removeClass('tbds-override-product-options-container-reimport');
        }

        $container.removeClass('tbds-hidden');

        $('.tbds-override-product-options-content-body-override-old').addClass('tbds-hidden');

        let $override_options = $('.tbds-override-product-options-content-body-option');
        if ($override_options.length > 0) {
            $override_options.removeClass('tbds-hidden');
        } else {
            $('.tbds-override-product-options-button-override').click();
        }

        found_items = [];
        check_orders = 0;
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

    function roundResult(number) {
        let decNum = parseInt(tbdsParams.decimals),
            temp = Math.pow(10, decNum);
        return Math.round(number * temp) / temp;
    }


    function maybe_hide_bulk_actions() {
        let $check = $('.tbds-accordion-bulk-item-check'),
            $bulk_actions = $('.tbds-accordion-bulk-actions-container');

        if ($bulk_actions.css('display') !== 'none') {
            if ($check.length > 0) {
                let check = 0;
                $check.map(function () {
                    if ($(this).prop('checked')) {
                        check++;
                    }
                });
                if (check === 0) {
                    $bulk_actions.fadeOut(200);
                }
            } else {
                $bulk_actions.fadeOut(200);
            }
        }
    }

    function maybe_reload_page() {
        if ($('.tbds-accordion').length === 0) {
            let url = new URL(document.location.href);
            url.searchParams.delete('tbds_search_id');
            url.searchParams.delete('tbds_search');
            url.searchParams.delete('paged');
            document.location.href = url.href;
        }
    }

    /*Edit attributes*/
    $(document).on('click', '.tbds-attributes-button-save', function () {
        let $button = $(this),
            $container = $button.closest('.tbds-accordion'),
            $row = $button.closest('tr'),
            change = 0,
            $attribute_values = $row.find('.tbds-attributes-attribute-value'),
            $slug = $row.find('.tbds-attributes-attribute-slug'),
            $overlay = $container.find('.tbds-product-overlay'),
            $name = $row.find('.tbds-attributes-attribute-name');

        if (!$name.val()) {
            alert(tbdsParams.i18n_empty_attribute_name);
            return;
        }

        if ($name.val() !== $name.data('attribute_name')) {
            change++;
        }

        let attribute_values = [];
        $attribute_values.map(function () {
            let attribute_value = $(this).val();
            if (attribute_value !== $(this).data('attribute_value')) {
                change++;
            }
            attribute_value = attribute_value.toLowerCase().trim();
            if (attribute_value && -1 === attribute_values.indexOf(attribute_value)) {
                attribute_values.push(attribute_value);
            }
        });

        if (attribute_values.length !== $attribute_values.length && !$button.hasClass('vichinads-attributes-button-save-loading')) {
            alert(tbdsParams.i18n_invalid_attribute_values);
            return;
        }


        if (change > 0) {
            let formData = {};
            parse_str($row.find('input').serialize(), formData);
            $overlay.removeClass('tbds-hidden');
            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_save_attributes',
                    _ajax_nonce: tbdsParams.security,
                    form_data: formData,
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let need_update_variations = false;
                        if (response.new_slug) {
                            need_update_variations = true;
                            $slug.html(response.new_slug);
                            $name.data('attribute_name', $name.val());
                        }
                        if (response.change_value === true) {
                            need_update_variations = true;
                            $row.find('.tbds-attributes-attribute-value').map(function () {
                                let $attribute_value = $(this);
                                $attribute_value.data('attribute_value', $attribute_value.val());
                            });
                        }
                        if (need_update_variations) {
                            $container.find('.tbds-variations-tab').removeClass('tbds-variations-tab-loaded');
                        }
                    }
                },
                error: function (err) {
                    console.log(err)
                },
                complete: function () {
                    $button.removeClass('loading vichinads-attributes-button-save-loading');
                    $row.removeClass('tbds-attributes-attribute-editing');
                    if ($row.closest('.tbds-product-row').find('.vichinads-attributes-button-save-loading').length){
                        $row.closest('.tbds-product-row').find('.vichinads-attributes-button-save-loading').eq(0).trigger('click');
                    }else {
                        $overlay.addClass('tbds-hidden');
                        if ($container.find('.tbds-variations-tab-menu.active').length){
                            $container.find('.tbds-variations-tab-loaded').removeClass('tbds-variations-tab-loaded');
                            $container.find('.tbds-variations-tab-menu').trigger('click');
                        }
                    }
                }
            })
        } else {
            $button.removeClass('loading vichinads-attributes-button-save-loading');
            $row.closest('.tbds-product-row').find('.vichinads-attributes-button-save-loading').eq(0).trigger('click');
        }
    });


    $(document).on('click', '.tbds-attributes-button-trans', function () {
        let $button = $(this), $row = $button.closest('tr');
        let icon = $(this).find('i');
        let transArray = [];
        icon.addClass('spinner');

        $row.find('input').each(function (i) {
            let $input = $(this), text = $input.val();
            let url = getTransUrl(text);

            transArray.push(new Promise((resolve, reject) => {
                fetch(url).then(res => res.json()).then(res => {
                    let transed = res.sentences[0].trans;
                    if (transed) $input.val(transed);
                    resolve(transed)
                }).catch(res => reject(res))
            }));
        });

        Promise.all(transArray).then(res => {
            icon.removeClass('spinner');
        }).catch(res => {
            console.log(res)
        });
    });

    $('.tbds-translate-title-n-attributes').on('click', function () {
        let $thisRow = $(this).closest('.tbds-product-row');
        $(this).find('i').addClass('spinner');
        let transArray = [];
        $thisRow.find('.tbds-import-data-title, .tbds-attributes-tab input').each(function (i) {
            let $input = $(this), text = $input.val();
            let url = getTransUrl(text);

            transArray.push(new Promise((resolve, reject) => {
                fetch(url).then(res => res.json()).then(res => {
                    let transed = res.sentences[0].trans;
                    if (transed) $input.val(transed);
                    resolve(transed)
                }).catch(res => reject(res))
            }));
        });

        Promise.all(transArray).then(res => {
            $thisRow.find('.tbds-attributes-button-save:not(.vichinads-attributes-button-save-loading)').map(function () {
                $(this).addClass('vichinads-attributes-button-save-loading');
            });

            if ($thisRow.find('.vichinads-attributes-button-save-loading').length ) {
                $thisRow.find('.vichinads-attributes-button-save-loading').eq(0).trigger('click');
            }
            // $thisRow.find('.vichinads-spinner').removeClass('vichinads-spinner');
            $thisRow.find('.spinner').removeClass('spinner');
        }).catch(res => {
            console.log(res)
        });
        // $thisRow.find('.tbds-title-translate-btn').trigger('click');
        // $thisRow.find('.tbds-attributes-button-trans').trigger('click');
        //
        //
        // $thisRow.find('.tbds-attributes-tab input').each(function (i) {
        //     let $input = $(this), text = $input.val();
        //     let url = getTransUrl(text);
        //
        //     transArray.push(new Promise((resolve, reject) => {
        //         fetch(url).then(res => res.json()).then(res => {
        //             let transed = res.sentences[0].trans;
        //             if (transed) $input.val(transed);
        //             resolve(transed)
        //         }).catch(res => reject(res))
        //     }));
        // });
        //
        // Promise.all(transArray).then(res => {
        //     $thisRow.find('.tbds-attributes-button-save').trigger('click');
        // }).catch(res => {
        //     console.log(res)
        // });
    });

    /*Switch tmce when opening Description tab*/
    $('.tbds-description-tab-menu').on('click', function () {
        $(`.tbds-description-tab[data-tab="${$(this).data('tab')}"]`).find('.switch-tmce').click();
    });

    /*Show/hide button set variation image*/
    $('.tbds-gallery-tab-menu').on('click', function () {
        let $button = $(this),
            $container = $button.closest('.tbds-accordion'),
            $variations_tab = $container.find('.tbds-variations-tab'),
            $variation_count = $container.find('.tbds-selected-variation-count'),
            $product_gallery = $container.find('.tbds-product-gallery');
        if ($variation_count.length > 0 && $variations_tab.hasClass('tbds-variations-tab-loaded')) {
            if (parseInt($variation_count.html()) > 0) {
                $product_gallery.addClass('tbds-allow-set-variation-image');
            } else {
                $product_gallery.removeClass('tbds-allow-set-variation-image');
            }
        }
    });

    /*Set variation image*/
    $('.tbds-set-variation-image').on('click', function (e) {
        e.stopPropagation();
        let $button = $(this),
            $container = $button.closest('.tbds-accordion'),
            $rows = $container.find('.tbds-product-variation-row').not('.tbds-variation-filter-inactive'),
            image_src = $button.closest('.tbds-product-gallery-item').find('.tbds-product-gallery-image').data('image_src');
        if (image_src && $rows.length > 0) {
            $rows.map(function () {
                let $row = $(this);
                if ($row.find('.tbds-variation-enable').prop('checked')) {
                    let $image_container = $row.find('.tbds-variation-image');
                    let $image_input = $image_container.find('input[type="hidden"]');
                    $image_container.find('.tbds-import-data-variation-image').attr('src', image_src).attr('image_src', image_src);
                    if ($image_input.val()) {
                        $image_input.val(image_src)
                    }
                }
            });
            villatheme_admin_show_message('Image is set for selected variations', 'success', '', false, 2000);
        }
    });

    /**
     * Remove an attribute
     */
    $('body')
        .on('click', '.tbds-attributes-attribute-remove', function () {
            let $button = $(this);
            let $row = $button.closest('.tbds-attributes-attribute-row');
            $row.addClass('tbds-attributes-attribute-removing');
            let $container = $('.tbds-modal-popup-container');
            let $content = $('.tbds-modal-popup-select-attribute');
            $content.html($button.closest('.tbds-attributes-attribute-row').find('.tbds-attributes-attribute-values').html());
            $content.find('.tbds-attributes-attribute-value').addClass('vi-ui').addClass('button').addClass('mini');
            $container.attr('class', 'tbds-modal-popup-container tbds-modal-popup-container-remove-attribute');
            tbds_set_price_show();
            if ($content.find('.tbds-attributes-attribute-value').length === 1) {
                $content.find('.tbds-attributes-attribute-value').eq(0).click();
            }
        })
        .on('click', '.tbds-modal-popup-select-attribute .tbds-attributes-attribute-value', function () {
            let $button = $(this),
                $overlay = $('.tbds-saving-overlay'),
                $row = $('.tbds-attributes-attribute-removing'),
                $container = $row.closest('.tbds-accordion'),
                $tab = $container.find('.tbds-product-tab'),
                tab_data = $tab.data('tab');
            $overlay.removeClass('tbds-hidden');

            let formData = {};
            parse_str($row.find('input').serialize(), formData);

            $.ajax({
                url: tbdsParams.ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_remove_attribute',
                    _ajax_nonce: tbdsParams.security,
                    attribute_slug: $row.find('.tbds-attributes-attribute-slug').data('attribute_slug'),
                    attribute_value: $button.data('attribute_value'),
                    form_data: formData,
                    product_index: tab_data.substring(11),
                },
                success: function (response) {
                    if (response.status === 'success') {
                        if ($container.find('.tbds-attributes-attribute-row').length > 1) {
                            $row.remove();
                            $container.find('.tbds-variations-tab').removeClass('tbds-variations-tab-loaded');
                        } else {
                            $container.find('.tbds-attributes-tab-menu').remove();
                            $container.find('.tbds-attributes-tab').remove();
                            $container.find('.tbds-variations-tab-menu').remove();
                            $container.find('.tbds-variations-tab').remove();
                            $container.find('.tabular.menu .item').eq(0).addClass('active');
                            $container.find('.tbds-product-tab').addClass('active');
                        }
                        if (response.html) {
                            $(response.html).insertAfter($container.find('.tbds-import-data-sku-status-visibility')).find('.vi-ui.dropdown').viDropdown({
                                fullTextSearch: true,
                                forceSelection: false,
                                selectOnKeydown: false
                            });
                        }
                        villatheme_admin_show_message(response.message, response.status, '', false, 2000);
                    } else {
                        villatheme_admin_show_message(response.message, response.status, '', false, 5000);
                    }
                },
                error: function (err) {
                    console.log(err);
                    villatheme_admin_show_message('An error occurs', 'error', '', false, 5000);
                },
                complete: function () {
                    $overlay.addClass('tbds-hidden');
                    $('.tbds-attributes-attribute-editing').removeClass('tbds-attributes-attribute-editing');
                    $('.tbds-overlay').click();
                }
            })
        });

    /*Bulk product*/
    $('.tbds-accordion-bulk-item-check').on('click', function (e) {
        let $button = $(this), show_actions = false;
        e.stopPropagation();
        if ($button.prop('checked')) {
            show_actions = true;
        } else {
            let $checkbox = $('.tbds-accordion-bulk-item-check');
            if ($checkbox.length > 0) {
                for (let i = 0; i < $checkbox.length; i++) {
                    if ($checkbox.eq(i).prop('checked')) {
                        show_actions = true;
                        break;
                    }
                }
            }
        }
        if (show_actions) {
            $('.tbds-accordion-bulk-actions-container').fadeIn(200);
        } else {
            $('.tbds-accordion-bulk-actions-container').fadeOut(200);
            // $('select[name="tbds_bulk_actions"]').val('none').trigger('change');
            $('.tbds-accordion-bulk-actions').viDropdown('clear');
        }
    });

    $('.tbds-accordion-bulk-item-check-all').on('click', function (e) {
        let $button = $(this), $checkbox = $('.tbds-accordion-bulk-item-check');
        if ($button.prop('checked')) {
            if ($checkbox.length > 0) {
                $('.tbds-accordion-bulk-actions-container').fadeIn(200);
                $checkbox.prop('checked', true).trigger('change');
            }
        } else {
            $('.tbds-accordion-bulk-actions-container').fadeOut(200);
            // $('select[name="tbds_bulk_actions"]').val('none').trigger('change');
            $('.tbds-accordion-bulk-actions').viDropdown('clear');
            $checkbox.prop('checked', false).trigger('change');
        }
    });

    $('select[name="tbds_bulk_actions"]').on('change', function () {
        let $action = $(this),
            action = $action.val(),
            $checkbox = $('.tbds-accordion-bulk-item-check');

        if ($checkbox.length > 0 && action !== '') {
            switch (action) {
                case 'set_status_publish':
                case 'set_status_pending':
                case 'set_status_draft':
                    let status = action.replace('set_status_', '');
                    $checkbox.map(function () {
                        let $button = $(this);
                        if ($button.prop('checked')) {
                            let $container = $button.closest('.tbds-accordion'),
                                $status = $container.find('.tbds-import-data-status');
                            if ($status.length > 0) {
                                $status.find('select').val(status).trigger('change');
                            }
                        }
                    });
                    break;
                case 'set_visibility_visible':
                case 'set_visibility_catalog':
                case 'set_visibility_search':
                case 'set_visibility_hidden':
                    let visibility = action.replace('set_visibility_', '');
                    $checkbox.map(function () {
                        let $button = $(this);
                        if ($button.prop('checked')) {
                            let $container = $button.closest('.tbds-accordion'),
                                $visibility = $container.find('.tbds-import-data-catalog-visibility');
                            if ($visibility.length > 0) {
                                $visibility.find('select').val(visibility).trigger('change');
                            }
                        }
                    });
                    break;
                case 'set_tags':
                case 'set_categories':
                    let taxonomy = action.replace('set_', '');
                    let $container = $('.tbds-modal-popup-container');
                    $container.attr('class', `tbds-modal-popup-container tbds-modal-popup-container-set-${taxonomy}`);
                    tbds_set_price_show();
                    break;
                case 'import':
                    if (confirm(tbdsParams.i18n_bulk_import_product_confirm)) {
                        $checkbox.map(function () {
                            let $button = $(this);
                            if ($button.prop('checked')) {
                                let $container = $button.closest('.tbds-accordion');
                                $container.find('.tbds-button-import').not('.tbds-hidden').click();
                                // $container.find('.tbds-button-override').not('.tbds-hidden').click();
                            }
                        });
                    }
                    break;
                case 'remove':
                    if (confirm(tbdsParams.i18n_bulk_remove_product_confirm)) {
                        is_bulk_remove = true;
                        $checkbox.map(function () {
                            let $button = $(this);
                            if ($button.prop('checked')) {
                                let $container = $button.closest('.tbds-accordion');
                                $container.find('.tbds-button-remove').click();
                            }
                        });
                        is_bulk_remove = false;
                    }
                    break;
            }
            $('.tbds-accordion-bulk-actions').viDropdown('clear');
            // setTimeout(function () {
            //     $action.val('none').trigger('change');
            // }, 100)
        }
    });

    $('body')
        .on('click', '.tbds-set-categories-button-set', function () {
            let $checkbox = $('.tbds-accordion-bulk-item-check'),
                $new_categories = $('select[name="tbds_bulk_set_categories"]'),
                new_categories = $new_categories.val();

            $checkbox.map(function () {
                let $button = $(this);
                if ($button.prop('checked')) {
                    let $container = $button.closest('.tbds-accordion'),
                        $categories = $container.find('.tbds-import-data-categories');
                    if ($categories.length > 0) {
                        $categories.viDropdown('set exactly', new_categories);
                    }
                }
            });

            tbds_set_price_hide();
        })
        .on('click', '.tbds-set-categories-button-add', function () {
            let $checkbox = $('.tbds-accordion-bulk-item-check'),
                $new_categories = $('select[name="tbds_bulk_set_categories"]'),
                new_categories = $new_categories.val();

            if (new_categories.length > 0) {
                $checkbox.map(function () {
                    let $button = $(this);
                    if ($button.prop('checked')) {
                        let $container = $button.closest('.tbds-accordion'),
                            $categories = $container.find('.tbds-import-data-categories');
                        if ($categories.length > 0) {
                            $categories.viDropdown('set exactly', [...new Set(new_categories.concat($categories.viDropdown('get values')))]);
                        }
                    }
                });
            }

            tbds_set_price_hide();
        })
        .on('click', '.tbds-set-categories-button-cancel', function () {
            tbds_set_price_hide();
        })
        .on('click', '.tbds-set-tags-button-set', function () {
            let $checkbox = $('.tbds-accordion-bulk-item-check'),
                $new_tags = $('select[name="tbds_bulk_set_tags"]'), new_tags = $new_tags.val();
            $checkbox.map(function () {
                let $button = $(this);
                if ($button.prop('checked')) {
                    let $container = $button.closest('.tbds-accordion'),
                        $tags = $container.find('.tbds-import-data-tags');
                    if ($tags.length > 0) {
                        $tags.viDropdown('set exactly', new_tags);
                    }
                }
            });
            tbds_set_price_hide();
        })
        .on('click', '.tbds-set-tags-button-add', function () {
            let $checkbox = $('.tbds-accordion-bulk-item-check'),
                $new_tags = $('select[name="tbds_bulk_set_tags"]'), new_tags = $new_tags.val();
            if (new_tags.length > 0) {
                $checkbox.map(function () {
                    let $button = $(this);
                    if ($button.prop('checked')) {
                        let $container = $button.closest('.tbds-accordion'),
                            $tags = $container.find('.tbds-import-data-tags');
                        if ($tags.length > 0) {
                            $tags.viDropdown('set exactly', [...new Set(new_tags.concat($tags.viDropdown('get values')))]);
                        }
                    }
                });
            }
            tbds_set_price_hide();
        })
        .on('click', '.tbds-set-tags-button-cancel', function () {
            tbds_set_price_hide();
        })
        .on('click', '.tbds-modal-popup-set-categories-clear', function () {
            $(this).parent().find('.tbds-modal-popup-set-categories-select').viDropdown('clear')
        })
        .on('click', '.tbds-modal-popup-set-tags-clear', function () {
            $(this).parent().find('.tbds-modal-popup-set-tags-select').viDropdown('clear')
        });

    $(".search-product").select2({
        width: '100%',
        closeOnSelect: true,
        allowClear: true,
        placeholder: "Please enter product title to search",
        ajax: {
            url: `admin-ajax.php?action=tbds_search_product&exclude_taobao_products=1&_ajax_nonce=${tbdsParams.security}`,
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


    const getTransUrl = (text) => `https://translate.googleapis.com/translate_a/single?client=gtx&sl=zh-CN&tl=${tbdsParams.transCode}&hl=en-US&dt=t&dt=bd&dj=1&source=input&q=${text}`;

    $('.tbds-title-translate-btn').on('click', function () {
        let icon = $(this).find('i');
        let titleInput = $(this).parent().parent().find('.tbds-import-data-title');
        let title = titleInput.val();
        let url = getTransUrl(title);

        icon.addClass('spinner');

        fetch(url).then(res => res.json()).then(res => {
            let transed = res.sentences[0].trans;
            if (transed) titleInput.val(transed);
            icon.removeClass('spinner');
        })
    });
});

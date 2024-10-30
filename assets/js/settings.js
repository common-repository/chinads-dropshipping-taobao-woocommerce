jQuery(document).ready(function ($) {
    'use strict';

    function contain_only_digit(val) {
        return /^\d+$/.test(val);
    }

    function contain_only_digit_and_x(val) {
        return /^[\d|x]+$/i.test(val);
    }

    function validate_price_rules($row) {
        $row.find('.tbds-error').removeClass('tbds-error');
        let rule_error = 0;
        let $sale_price = $row.find('.tbds-plus-sale-value');
        let $price = $row.find('.tbds-plus-value');
        let $price_from = $row.find('.tbds-price-from');
        let $price_to = $row.find('.tbds-price-to');
        if (parseFloat($sale_price.val()) > -1 && parseFloat($sale_price.val()) > parseFloat($price.val())) {
            rule_error++;
            $sale_price.closest('.vi-ui.labeled').addClass('tbds-error');
            $price.closest('.vi-ui.labeled').addClass('tbds-error');
        }
        if ($price_to.val() !== '' && parseFloat($price_from.val()) > parseFloat($price_to.val())) {
            rule_error++;
            $price_from.closest('.vi-ui.labeled').addClass('tbds-error');
            $price_to.closest('.vi-ui.labeled').addClass('tbds-error');
        }
        return rule_error;
    }

    function validate_price_format($row) {
        $row.find('.tbds-error').removeClass('tbds-error');
        $row.find('.tbds-error-message').html('');
        let format_error = 0;
        let $price_range_from = $row.find('.tbds-format-price-rules-from'),
            price_range_from = $price_range_from.val(),
            $price_range_to = $row.find('.tbds-format-price-rules-to'),
            price_range_to = $price_range_to.val(),
            $part_range_from = $row.find('.tbds-format-price-rules-value-from'),
            part_range_from = $part_range_from.val(),
            $part_range_to = $row.find('.tbds-format-price-rules-value-to'),
            part_range_to = $part_range_to.val(),
            $new_value = $row.find('.tbds-format-price-rules-value'),
            new_value = $new_value.val(),
            $part = $row.find('select[name="tbds_format_price_rules[part][]"]');

        if (parseFloat(price_range_from) > parseFloat(price_range_to)) {
            format_error++;
            $price_range_from.closest('.vi-ui.labeled').addClass('tbds-error');
            $price_range_to.closest('.vi-ui.labeled').addClass('tbds-error');
            $price_range_from.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_min_max);
            $price_range_to.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_max_min);
        }
        if ($part.val() === 'integer') {
            let part_range_max = Math.min(parseInt(price_range_from).toString().length, parseInt(price_range_to).toString().length) - 1;
            if (parseFloat(part_range_from) > parseFloat(part_range_to)) {
                format_error++;
                $part_range_from.closest('.vi-ui.labeled').addClass('tbds-error');
                $part_range_to.closest('.vi-ui.labeled').addClass('tbds-error');
                $part_range_from.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_min_max);
                $part_range_to.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_max_min);
            }
            if (parseInt(price_range_from).toString().length < 2) {
                format_error++;
                $price_range_from.closest('.vi-ui.labeled').addClass('tbds-error');
                $price_range_from.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_min_digits);
            }
            if (parseInt(price_range_to).toString().length < 2) {
                format_error++;
                $price_range_to.closest('.vi-ui.labeled').addClass('tbds-error');
                $price_range_to.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_min_digits);
            }

            if ((parseInt(part_range_from) !== 0 || parseInt(part_range_to) !== 0) && part_range_max > 0) {
                if (part_range_from.length > part_range_max) {
                    format_error++;
                    $part_range_from.closest('.vi-ui.labeled').addClass('tbds-error');
                    $part_range_from.closest('.tbds-error-message-parent').find('.tbds-error-message').html(part_range_max === 1 ? tbdsParams.i18n_error_max_digit.replace('{value}', part_range_max) : tbdsParams.i18n_error_max_digits.replace('{value}', part_range_max));
                }
                if (part_range_to.length > part_range_max) {
                    format_error++;
                    $part_range_to.closest('.vi-ui.labeled').addClass('tbds-error');
                    $part_range_to.closest('.tbds-error-message-parent').find('.tbds-error-message').html(part_range_max === 1 ? tbdsParams.i18n_error_max_digit.replace('{value}', part_range_max) : tbdsParams.i18n_error_max_digits.replace('{value}', part_range_max));
                }
            }
            if ((part_range_from === '' && part_range_to === '' && new_value.length > part_range_max && part_range_max > 0)) {
                format_error++;
                $new_value.closest('.vi-ui.labeled').addClass('tbds-error');
                $new_value.closest('.tbds-error-message-parent').find('.tbds-error-message').html(part_range_max === 1 ? tbdsParams.i18n_error_max_digit.replace('{value}', part_range_max) : tbdsParams.i18n_error_max_digits.replace('{value}', part_range_max));
            }
            let new_min = Math.min(part_range_max, Math.max(part_range_from.length, part_range_to.length));
            if (((part_range_from !== '' || part_range_to !== '') && new_value.length > new_min && part_range_max > 0)) {
                format_error++;
                $new_value.closest('.vi-ui.labeled').addClass('tbds-error');
                $new_value.closest('.tbds-error-message-parent').find('.tbds-error-message').html(new_min === 1 ? tbdsParams.i18n_error_max_digit.replace('{value}', new_min) : tbdsParams.i18n_error_max_digits.replace('{value}', new_min));
            }
            if (!contain_only_digit(new_value)) {
                format_error++;
                $new_value.closest('.vi-ui.labeled').addClass('tbds-error');
                $new_value.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_digit_only);
            }
        } else if (max_decimals > 0) {
            if (parseFloat(`.${part_range_from}`) > parseFloat(`.${part_range_to}`)) {
                format_error++;
                $part_range_from.closest('.vi-ui.labeled').addClass('tbds-error');
                $part_range_to.closest('.vi-ui.labeled').addClass('tbds-error');
                $part_range_from.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_min_max);
                $part_range_to.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_max_min);
            }
            if (new_value.length > max_decimals) {
                format_error++;
                $new_value.closest('.vi-ui.labeled').addClass('tbds-error');
                $new_value.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_max_decimals);
            }
            if (!contain_only_digit_and_x(new_value)) {
                format_error++;
                $new_value.closest('.vi-ui.labeled').addClass('tbds-error');
                $new_value.closest('.tbds-error-message-parent').find('.tbds-error-message').html(tbdsParams.i18n_error_digit_and_x_only);
            }
        }
        return format_error;
    }

    //Data storage for Taobao products
    $(document).on('click', '.vichinads-migrate-to-new-table', function () {
        if (!confirm('Do you want to migrate to new table?')) {
            return;
        }
        let $thisBtn = $(this);
        $thisBtn.addClass('loading');
        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'post',
            data: {
                action: 'vichinads_migrate_to_new_table',
                _ajax_nonce: tbdsParams.security,
            },
            success(res) {
                $thisBtn.text(res.data);
                $thisBtn.removeClass('vichinads-migrate-to-new-table');
            },
            complete() {
                $thisBtn.removeClass('loading');
            }
        });
    });

    $(document).on('click', '.vichinads-migrate-remove-old-data', function () {
        if (!confirm('Do you want to remove old data from posts table?')) {
            return;
        }
        let $thisBtn = $(this);
        $thisBtn.addClass('loading');
        $.ajax({
            url: tbdsParams.ajaxUrl,
            type: 'post',
            data: {
                action: 'vichinads_migrate_remove_old_data',
                _ajax_nonce: tbdsParams.security,
            },
            success(res) {
                $thisBtn.text(res.data);
                $thisBtn.removeClass('vichinads-migrate-remove-old-data');
            },
            complete() {
                $thisBtn.removeClass('loading');
            }
        });
    });
    $('.vi-ui.menu .item').viTab({history: true});
    $('.tbds-dropdown').viDropdown({placeholder: ''});
    $('.tbds-save-settings').on('click', function (e) {
        let rule_error = 0, format_error = 0;
        $('.tbds-price-rule-row').map(function () {
            rule_error += validate_price_rules($(this));
        });
        if (rule_error > 0) {
            alert('Regular price can not be smaller than sale price');
            return false;
        }
        $('.tbds-format-price-rules-container>tr').map(function () {
            format_error += validate_price_format($(this));
        });
        if (format_error > 0) {
            alert('You have error(s) in your rules');
            return false;
        }
    });

    /*Search categories*/
    $('.search-category').select2({
        width: '100%',
        closeOnSelect: false,
        placeholder: "Please fill in your category title",
        ajax: {
            url: "admin-ajax.php?action=tbds_search_cate",
            dataType: 'json',
            type: "GET",
            quietMillis: 50,
            delay: 250,
            data: params => ({keyword: params.term}),
            processResults: data => ({results: data}),
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        }, // let our custom formatter work
        minimumInputLength: 2
    });

    /*Search tags*/
    $('.search-tags').select2({
        width: '100%',
        closeOnSelect: false,
        placeholder: "Please enter tag to search",
        ajax: {
            url: "admin-ajax.php?action=tbds_search_tags",
            dataType: 'json',
            type: "GET",
            quietMillis: 50,
            delay: 250,
            data: params => ({keyword: params.term}),
            processResults: data => ({results: data}),
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        }, // let our custom formatter work
        minimumInputLength: 2
    });

    /*Add row*/
    $('.tbds-price-rule-add').on('click', function () {
        let $rows = $('.tbds-price-rule-row'),
            $lastRow = $rows.last(),
            $newRow = $lastRow.clone();
        $newRow.find('.tbds-price-from').val('');
        $newRow.find('.tbds-price-to').val('');
        $newRow.find('.tbds-plus-value-type').viDropdown();
        $('.tbds-price-rule-container').append($newRow);
    });

    /*remove row*/
    $(document).on('click', '.tbds-price-rule-remove', function () {
        let $button = $(this), $rows = $('.tbds-price-rule-row'),
            $row = $button.closest('.tbds-price-rule-row');
        if ($rows.length > 1) {
            if (confirm('Do you want to remove this row?')) {
                $row.fadeOut(300);
                setTimeout(function () {
                    $row.remove();
                }, 300)
            }
        }
    });

    /*calculate when "price from" changes*/
    $(document).on('change', 'select[name="tbds_plus_value_type[]"]', function () {
        change_price_label($(this));
    });
    $(document).on('change', 'select[name="tbds_price_default[plus_value_type]"]', function () {
        change_price_label($(this));
    });

    function change_price_label($select) {
        let $current = $select.closest('tr');
        switch ($select.val()) {
            case 'fixed':
                $current.find('.tbds-value-label-left').html('+');
                $current.find('.tbds-value-label-right').html('$');
                break;
            case 'percent':
                $current.find('.tbds-value-label-left').html('+');
                $current.find('.tbds-value-label-right').html('%');
                break;
            case 'multiply':
                $current.find('.tbds-value-label-left').html('x');
                $current.find('.tbds-value-label-right').html('');
                break;
            default:
                $current.find('.tbds-value-label-left').html('=');
                $current.find('.tbds-value-label-right').html('$');
        }
    }

    $('.vi-ui.button.primary').on('click', function () {
        if (!$('#tbds-import-currency-rate').val()) {
            alert('Please enter Import products currency exchange rate');
            return false;
        }
    });

    $('.tbds-generate-secretkey').on('click', function () {
        var a = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890".split(""), b = [];
        for (let i = 0; i < 32; i++) {
            var j = (Math.random() * (a.length - 1)).toFixed(0);
            b[i] = a[j];
        }

        $('.tbds-secret-key').val(b.join(""));
    });

    $('.tbds-copy-secretkey').on('click', function () {
        let $container = $(this).closest('td');
        $container.find('.tbds-secret-key').select();
        $container.find('.tbds-copy-secretkey-success').remove();
        document.execCommand('copy');
        let $result_icon = $('<span class="tbds-copy-secretkey-success dashicons dashicons-yes" title="Copied to Clipboard"></span>');
        $container.append($result_icon);
        $result_icon.fadeOut(10000);
        setTimeout(function () {
            $result_icon.remove();
        }, 10000);
    });

//String replace

    $('.add-string-replace-rule').on('click', function () {
        let clone = `<tr class="clone-source">
                        <td>
                            <input type="text" name="tbds_string_replace[from_string][]">
                        </td>
                         <td>
                            <div class="tbds-string-replace-sensitive-container">
                            <input type="checkbox" value="1" class="tbds-string-replace-sensitive">                            
                            <input type="hidden" class="tbds-string-replace-sensitive-value" value="" name="tbds_string_replace[sensitive][]">
                            </div>
                        </td>
                        <td>
                            <input type="text" name="tbds_string_replace[to_string][]"  placeholder="Blank is delete">
                        </td>
                        <td>
                            <button type="button" class="vi-ui button negative tiny delete-string-replace-rule">
                                <i class="dashicons dashicons-trash "></i>
                            </button>
                        </td>
                    </tr>`;

        $('.string-replace tbody').append(clone);
    });

    $('body').on('change', '.tbds-string-replace-sensitive', function () {
        let $container = $(this).closest('.tbds-string-replace-sensitive-container');
        let $sensitive_value = $container.find('.tbds-string-replace-sensitive-value');
        let sensitive_value = $(this).prop('checked') ? 1 : '';
        $sensitive_value.val(sensitive_value);
    });
    $('body').on('click', '.delete-string-replace-rule', function () {
        if (confirm('Remove this item?')) {
            $(this).closest('.clone-source').remove();
        }
    });
    /*String replace*/
    $('.add-string-replace-rule-url').on('click', function () {
        let clone = `<tr class="clone-source">
                        <td>
                            <input type="text" value="" name="tbds-carrier_url_replaces[from_string][]">
                        </td>
                        <td>
                            <input type="text" placeholder="URL of a replacement carrier" value="" name="tbds-carrier_url_replaces[to_string][]">
                        </td>
                        <td>
                            <button type="button" class="vi-ui button negative tiny delete-string-replace-rule">
                                <i class="dashicons dashicons-trash"></i>
                            </button>
                        </td>
                    </tr>`;

        $('.string-replace-url tbody').append(clone);
    });
    $('.add-string-replace-rule-name').on('click', function () {
        let clone = `<tr class="clone-source">
                        <td>
                            <input type="text" value="" name="tbds-carrier_name_replaces[from_string][]">
                        </td>
                         <td>
                            <div class="tbds-string-replace-sensitive-container">
                                <input type="checkbox" value="1" class="tbds-string-replace-sensitive">
                                <input type="hidden" class="tbds-string-replace-sensitive-value" value="" name="tbds-carrier_name_replaces[sensitive][]">
                            </div>
                        </td>
                        <td>
                            <input type="text" placeholder="Blank is delete" value="" name="tbds-carrier_name_replaces[to_string][]">
                        </td>                                       
                        <td>
                            <button type="button" class="vi-ui button negative tiny delete-string-replace-rule">
                                <i class="dashicons dashicons-trash"></i>
                            </button>
                        </td>
                    </tr>`;

        $('.string-replace-name tbody').append(clone);
    });
    /*Format price rules*/
    $(document).on('click', '.tbds-format-price-rules-duplicate', function () {
        let $row = $(this).closest('tr'), $new_row = $row.clone();
        $new_row.find('.vi-ui.dropdown').viDropdown('set selected', $row.find('select[name="tbds_format_price_rules[part][]"]').val());
        $new_row.insertAfter($row);
        recalculate_index();
    });
    $(document).on('click', '.tbds-format-price-rules-remove', function () {
        let $row = $(this).closest('tr');
        if (confirm('Do you really want to remove this row?')) {
            if ($('.tbds-format-price-rules-container>tr').length === 1) {
                $row.find('input[name="tbds_format_price_rules[from][]"]').val(0);
                $row.find('input[name="tbds_format_price_rules[to][]"]').val(0);
                $row.find('input[name="tbds_format_price_rules[value_from][]"]').val(0);
                $row.find('input[name="tbds_format_price_rules[value_to][]"]').val(0);
                $row.find('input[name="tbds_format_price_rules[value][]"]').val(0);
                $row.find('select[name="tbds_format_price_rules[part][]"]').val('fraction').trigger('change');
            } else {
                $row.fadeOut(300);
                setTimeout(function () {
                    $row.remove();
                    recalculate_index();
                }, 300)
            }
        }
    });
    $('.tbds-format-price-rules-table').on('change', 'select[name="tbds_format_price_rules[part][]"]', function () {
        let $row = $(this).closest('tr'), $label = $row.find('.tbds-format-price-rules-label'),
            label_class = $label.attr('class');
        if ($(this).val() === 'integer') {
            $label.attr('class', label_class.replace(' left ', ' right '))
        } else {
            $label.attr('class', label_class.replace(' right ', ' left '))
        }
    }).on('change', 'select[name="tbds_format_price_rules[part][]"],input[name="tbds_format_price_rules[from][]"],input[name="tbds_format_price_rules[to][]"],input[name="tbds_format_price_rules[value_from][]"],input[name="tbds_format_price_rules[value_to][]"],input[name="tbds_format_price_rules[value][]"]', function () {
        validate_price_format($(this).closest('tr'));
    });
    $('.tbds-format-price-rules-test-button').on('click', function () {
        let $button = $(this);
        if (!$button.hasClass('loading')) {
            let format_error = 0, format_price_rules = [], $result = $('.tbds-format-price-rules-test-result');
            $('.tbds-format-price-rules-container>tr').map(function () {
                let error_count = validate_price_format($(this));
                if (error_count > 0) {
                    format_error += error_count;
                } else {
                    format_price_rules.push({
                        from: $(this).find('input[name="tbds_format_price_rules[from][]"]').val(),
                        to: $(this).find('input[name="tbds_format_price_rules[to][]"]').val(),
                        part: $(this).find('select[name="tbds_format_price_rules[part][]"]').val(),
                        value_from: $(this).find('input[name="tbds_format_price_rules[value_from][]"]').val(),
                        value_to: $(this).find('input[name="tbds_format_price_rules[value_to][]"]').val(),
                        value: $(this).find('input[name="tbds_format_price_rules[value][]"]').val(),
                    });
                }
            });
            if (format_error === 0) {
                $button.addClass('loading');
                $result.html('');
                $.ajax({
                    url: tbdsParams.url,
                    type: 'GET',
                    dataType: 'JSON',
                    data: {
                        action: 'tbds_format_price_rules_test',
                        format_price_rules_test: $('.tbds-format-price-rules-test').val(),
                        format_price_rules: format_price_rules,
                    },
                    success: function (response) {
                        $result.html(response.result);
                    },
                    error: function (err) {
                        $result.html(err.statusText);
                    },
                    complete: function () {
                        $button.removeClass('loading');
                    }
                })
            } else {
                alert('Please review your rules before continuing.')
            }
        }
    });
    $('.tbds-format-price-rules-container.ui-sortable').sortable({
        stop: function (event, ui) {
            recalculate_index();
        }
    });
    /*Add shipping cost after price rules*/
    $('#tbds-show-shipping-option').on('change', function () {
        let $dependency = $('#tbds-shipping-cost-after-price-rules').closest('tr');
        if ($(this).prop('checked')) {
            $dependency.fadeIn(200);
        } else {
            $dependency.fadeOut(200);
        }
    }).trigger('change');
    $('#tbds-use-external-image').on('change', function () {
        let $dependency = $('#tbds-download-description-images').closest('tr');
        if (!$(this).prop('checked')) {
            $dependency.fadeIn(200);
        } else {
            $dependency.fadeOut(200);
        }
    }).trigger('change');

    function recalculate_index() {
        let count = 1;
        $('.tbds-format-price-rules-number').map(function () {
            $(this).html(count);
            count++;
        })
    }

    $('.woo-alidropship form').on('submit', function () {
        $('.tbds-save-settings').addClass('loading');
    });


});
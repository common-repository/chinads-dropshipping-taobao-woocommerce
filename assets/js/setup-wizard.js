jQuery(document).ready(function ($) {
    'use strict';
    let ajaxNonce = tbdsParams.security, ajaxUrl = tbdsParams.ajaxUrl;

    $('select.vi-ui.dropdown').viDropdown();

    /*Search categories*/
    $(".search-category").select2({
        width: '100%',
        closeOnSelect: false,
        placeholder: "Please fill in your category title",
        ajax: {
            url: "admin-ajax.php?action=tbds_search_cate&_vi_tbds_ajax_nonce=" + ajaxNonce,
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

    /*remove last row*/
    $(document).on('click', '.tbds-price-rule-remove', function () {
        let $button = $(this), $rows = $('.tbds-price-rule-row'),
            $row = $button.closest('.tbds-price-rule-row');
        if ($rows.length > 1) {
            if (confirm('Do you want to remove this row?')) {
                $row.fadeOut(300);
                setTimeout(() => $row.remove(), 300);
            }
        }
    });

    $('.vi-ui.button.primary').on('click', function () {
        let rateInput = $('#tbds-import-currency-rate');
        if (rateInput.length && !rateInput.val()) {
            alert('Please enter Import products currency exchange rate');
            return false;
        }
    });

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

    $('.tbds-select-plugin').on('change', function () {
        let checkedCount = $('.tbds-select-plugin:checked').length;
        $('.tbds-finish').text(checkedCount > 0 ? 'Install & Return to Dashboard' : 'Return to Dashboard');
    });

    $('.tbds-toggle-select-plugin').on('change', function () {
        let checked = $(this).prop('checked');
        $('.tbds-select-plugin').prop('checked', checked);
        $('.tbds-finish').text(checked ? 'Install & Return to Dashboard' : 'Return to Dashboard');
    });

    $('.tbds-finish').on('click', function () {
        let $button = $(this);
        let install_plugins = $('.tbds-select-plugin:checked').map((i, el) => $(el).data('plugin_slug')).toArray();

        if (install_plugins.length > 0) {
            $button.addClass('loading');
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'tbds_setup_install_plugins',
                    nonce: ajaxNonce,
                    install_plugins: install_plugins,
                },
                success() {
                },
                complete() {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        dataType: 'JSON',
                        data: {
                            action: 'tbds_setup_activate_plugins',
                            nonce: ajaxNonce,
                            install_plugins: install_plugins,
                        },
                        success() {
                        },
                        complete() {
                            $button.removeClass('loading');
                            window.location.href = tbdsParams.settingsPage;
                        }
                    })
                }
            })
        } else {
            window.location.href = tbdsParams.settingsPage;
        }

        return false;
    });
});

<?php

namespace TaobaoDropship\Inc;

defined( 'ABSPATH' ) || exit;

class Enqueue {
	protected static $instance = null;
	protected $slug;

	public function __construct() {
		$this->slug = TBDS_CONST['assets_slug'];
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function register_scripts() {
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		$lib_styles  = [
			'button',
			'tab',
			'input',
			'icon',
			'segment',
			'image',
			'modal',
			'dimmer',
			'transition',
			'menu',
			'grid',
			'search',
			'message',
			'loader',
			'label',
			'select2',
			'header',
			'accordion',
			'dropdown',
			'checkbox',
			'form',
			'table',
		];
		$styles      = [ 'import-list', 'imported', 'settings', 'error-images', 'setup-wizard' ];
		$lib_scripts = [ 'select2', 'transition', 'dimmer', 'accordion', 'tab', 'modal', 'dropdown', 'jqColorPicker', 'jquery.address' ];
		$scripts     = [
			'settings'     => [ 'jquery' ],
			'error-images' => [ 'jquery' ],
			'imported'     => [ 'jquery' ],
			'show-message' => [ 'jquery' ],
			'setup-wizard' => [ 'jquery' ],
			'import-list'  => [ 'jquery', 'jquery-ui-sortable' ]
		];

		foreach ( $lib_styles as $style ) {
			wp_register_style( $this->slug . $style, TBDS_CONST['libs_url'] . $style . '.min.css', '', TBDS_CONST['version'] );
		}

		foreach ( $styles as $style ) {
			wp_register_style( $this->slug . $style, TBDS_CONST['css_url'] . $style . $suffix . '.css', '', TBDS_CONST['version'] );
		}

		foreach ( $lib_scripts as $script ) {
			wp_register_script( $this->slug . $script, TBDS_CONST['libs_url'] . $script . '.min.js', [ 'jquery' ], TBDS_CONST['version'] , false);
		}

		foreach ( $scripts as $script => $depend ) {
			wp_register_script( $this->slug . $script, TBDS_CONST['js_url'] . $script . $suffix . '.js', $depend, TBDS_CONST['version'], false );
		}
	}

	public function admin_enqueue_scripts() {
		global $tbds_pages;
		$screen_id = get_current_screen()->id;
		$scripts   = $styles = [];
		$localize  = '';
		$params    = [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'security' => wp_create_nonce( 'tbds_security' )
		];

		$settings = Data::instance();

		if ( in_array( $screen_id, (array) $tbds_pages ) ) {
			$this->register_scripts();

			switch ( $screen_id ) {
				case $tbds_pages['settings']:
					wp_enqueue_media();
					$styles   = [ 'select2', 'label', 'input', 'icon', 'tab', 'table', 'transition', 'dropdown', 'form', 'button', 'checkbox', 'message', 'segment', 'menu', 'accordion', 'settings' ];
					$scripts  = [ 'select2', 'transition', 'dropdown', 'tab', 'accordion', 'jquery.address', 'settings' ];
					$localize = 'settings';
					$decimals = wc_get_price_decimals();

					$params = array_merge( $params, [
						'decimals'                    => $decimals,
						'i18n_error_max_digit'        => esc_html__( 'Maximum {value} digit', 'chinads' ),
						'i18n_error_max_digits'       => esc_html__( 'Maximum {value} digits', 'chinads' ),
						'i18n_error_digit_only'       => esc_html__( 'Numerical digit only', 'chinads' ),
						'i18n_error_digit_and_x_only' => esc_html__( 'Numerical digit & X only', 'chinads' ),
						'i18n_error_min_digits'       => esc_html__( 'Minimum 2 digits', 'chinads' ),
						'i18n_error_min_max'          => esc_html__( 'Min can not > max', 'chinads' ),
						'i18n_error_max_min'          => esc_html__( 'Max can not < min', 'chinads' ),
						'i18n_error_max_decimals'     => sprintf( "%s: <a target='_blank' href='admin.php?page=wc-settings#woocommerce_price_num_decimals'>%s</a>",
							esc_html( _n( 'Max decimal', 'Max decimals', $decimals, 'chinads' ) ), esc_html( $decimals ) ),
					] );
					break;

				case $tbds_pages['import_list']:
					$styles   = [ 'select2', 'label', 'input', 'icon', 'tab', 'table', 'transition', 'dropdown', 'form', 'button', 'checkbox', 'segment', 'message', 'menu', 'accordion', 'import-list' ];
					$scripts  = [ 'select2', 'transition', 'dropdown', 'tab', 'accordion', 'jquery.address', 'show-message', 'import-list' ];
					$localize = 'import-list';
					$params   = array_merge( $params, [
						'transCode'                              => $settings->get_param( 'trans_code' ),
						'decimals'                               => wc_get_price_decimals(),
						'i18n_empty_variation_error'             => esc_attr__( 'Please select at least 1 variation to import.', 'chinads' ),
						'i18n_empty_price_error'                 => esc_attr__( 'Regular price can not be empty.', 'chinads' ),
						'i18n_sale_price_error'                  => esc_attr__( 'Sale price must be smaller than regular price.', 'chinads' ),
						'i18n_not_found_error'                   => esc_attr__( 'No product found.', 'chinads' ),
						'i18n_import_all_confirm'                => esc_attr__( 'Import all products on this page to your WooCommerce store?', 'chinads' ),
						'i18n_remove_product_confirm'            => esc_attr__( 'Remove this product from import list?', 'chinads' ),
						'i18n_bulk_remove_product_confirm'       => esc_html__( 'Remove selected product(s) from import list?', 'chinads' ),
						'i18n_bulk_import_product_confirm'       => esc_html__( 'Import all selected product(s)?', 'chinads' ),
//					'product_categories'               => self::$settings->get_params( 'product_categories' ),
						'i18n_split_product_confirm'             => esc_html__( 'Split to 2 products by selected variation(s)?', 'chinads' ),
						'i18n_split_product_no_variations'       => esc_html__( 'Please select variations to split', 'chinads' ),
						'i18n_split_product_too_many_variations' => esc_html__( 'Please select less variations to split', 'chinads' ),
						'i18n_split_product_message'             => esc_html__( 'If product is split successfully, page will be reloaded automatically to load new products.', 'chinads' ),
						'i18n_empty_attribute_name'              => esc_html__( 'Attribute name can not be empty', 'chinads' ),
						'i18n_invalid_attribute_values'          => esc_html__( 'Attribute value can not be empty or duplicated', 'chinads' ),
					] );
					break;

				case $tbds_pages['imported']:
					$styles   = [ 'select2', 'label', 'input', 'icon', 'tab', 'table', 'transition', 'dropdown', 'form', 'button', 'checkbox', 'segment', 'menu', 'accordion', 'imported' ];
					$scripts  = [ 'select2', 'transition', 'dropdown', 'tab', 'accordion', 'jquery.address', 'imported' ];
					$localize = 'imported';
					$params   = array_merge( $params, [
						'check'    => esc_attr__( 'Check', 'chinads' ),
						'override' => esc_attr__( 'Override', 'chinads' ),
					] );
					break;

				case $tbds_pages['error_images']:
					$styles   = [ 'select2', 'label', 'input', 'icon', 'tab', 'table', 'transition', 'dropdown', 'form', 'button', 'checkbox', 'segment', 'menu', 'accordion', 'error-images' ];
					$scripts  = [ 'select2', 'transition', 'dropdown', 'tab', 'accordion', 'jquery.address', 'error-images' ];
					$localize = 'error-images';
					$params   = array_merge( $params, [
						'i18n_confirm_delete'     => esc_html__( 'Are you sure you want to delete this item?', 'chinads' ),
						'i18n_confirm_delete_all' => esc_html__( 'Are you sure you want to delete all item(s) on this page?', 'chinads' ),
					] );
					break;

				case $tbds_pages['recommended_plugins']:
					$styles  = [ 'table' ];
					$scripts = [];
					if (!wp_style_is('chinads-recommended_plugins')){
						wp_register_style('chinads-recommended_plugins',false,[],TBDS_CONST['version']);
						wp_enqueue_style('chinads-recommended_plugins');
						wp_add_inline_style('chinads-recommended_plugins','.fist-col { min-width: 300px;}.vi-wad-plugin-name {    font-weight: 600;}.vi-wad-plugin-name a { text-decoration: none;}');
					}
					break;
			}
		}

		if ( isset( $_GET['_wpnonce'] ) && ! empty( $_GET['tbds_setup_wizard'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'tbds_setup' ) ) {
			$this->register_scripts();
			$styles   = [ 'select2', 'label', 'input', 'icon', 'tab', 'table', 'transition', 'dropdown', 'form', 'button', 'checkbox', 'segment', 'message', 'menu', 'accordion', 'setup-wizard' ];
			$scripts  = [ 'select2', 'transition', 'dropdown', 'tab', 'accordion', 'jquery.address', 'show-message', 'setup-wizard' ];
			$localize = 'setup-wizard';
			$params   = array_merge( $params, [
				'settingsPage' => esc_url( admin_url( 'admin.php?page=tbds-settings' ) ),
			] );
		}

		foreach ( $scripts as $script ) {
			wp_enqueue_script( $this->slug . $script );
		}

		foreach ( $styles as $style ) {
			wp_enqueue_style( $this->slug . $style );
		}

		if ( $localize ) {
			wp_localize_script( $this->slug . $localize, 'tbdsParams', $params );
		}
	}

}

<?php

namespace TaobaoDropship\Inc;

use TaobaoDropship\Admin\Taobao_Products_Table;

defined( 'ABSPATH' ) || exit;

class Data {
	protected static $instance = null;
	protected $params, $current_params;

	public function __construct() {
		$this->init_params();
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function default_options() {
		return [
			'enable'                                => 1,
			'use_tbds_table'                      => '',
			'product_status'                        => 'publish',
			'catalog_visibility'                    => 'visible',
			'product_gallery'                       => '1',
			'product_categories'                    => [get_option( 'default_product_cat', 0 )],
			'product_tags'                          => [],
			'product_shipping_class'                => '',
			'product_description'                   => 'none',
			'variation_visible'                     => '',
			'manage_stock'                          => 1,
			'ignore_ship_from'                      => '',
			'price_from'                            => [ 0 ],
			'price_to'                              => [ '' ],
			'plus_value'                            => [ 200 ],
			'plus_sale_value'                       => [ - 1 ],
			'plus_value_type'                       => [ 'percent' ],
			'price_default'                         => [
				'plus_value'      => 2,
				'plus_sale_value' => 1,
				'plus_value_type' => 'multiply',
			],
			'import_product_currency'               => 'CNY',
			'import_currency_rate'                  => 1,
			'disable_background_process'            => '',
			'simple_if_one_variation'               => '',
			'download_description_images'           => '',
			'shipping_cost_after_price_rules'       => '',
			'use_global_attributes'                 => '',
			'format_price_rules_enable'             => '',
			'format_price_rules_test'               => 0,
			'format_price_rules'                    => [],
			'override_hide'                         => 0,
			'override_keep_product'                 => 1,
			'override_title'                        => 0,
			'override_images'                       => 0,
			'override_description'                  => 0,
			'override_find_in_orders'               => 1,
			'delete_woo_product'                    => 1,
			'use_external_image'                    => '',
			'trans_code'                            => 'en',
		];
	}

	public function init_params() {
		$this->params = $this->get_params();
	}

	public function get_params() {
		$this->current_params = get_option( 'tbds_params' );

		return wp_parse_args( $this->current_params, $this->default_options() );
	}

	public function get_param( $key ) {
		if ( ! $this->params ) {
			$this->init_params();
		}
		switch ($key){
			case 'import_currency_rate':
				if (!isset($this->current_params['import_currency_rate'])){
					$this->params['import_currency_rate']= Utils::get_exchange_rate();
				}
				break;
			case 'use_tbds_table':
				if (!isset($this->current_params['use_tbds_table'])){
					if (get_option('tbds_migrated_to_new_table','notfound') === 'notfound'){
						$count_posts = array_sum( (array)wp_count_posts('tbds_draft_product'));
						if ( !$count_posts ) {
							Taobao_Products_Table::maybe_create_table();
							update_option( 'tbds_deleted_old_posts_data', true );
							update_option( 'tbds_migrated_to_new_table', true );
						}else{
							update_option( 'tbds_migrated_to_new_table', '' );
						}
					}
					if (get_option('tbds_migrated_to_new_table')){
						$this->params['use_tbds_table'] = 1;
					}
				}
				break;
		}
		return $this->params[ $key ] ?? '';
	}

}

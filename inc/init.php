<?php

namespace TaobaoDropship\Inc;

use TaobaoDropship\Admin\Admin;
use TaobaoDropship\Admin\Api;
use TaobaoDropship\Admin\Auth;
use TaobaoDropship\Admin\Draft_Product;
use TaobaoDropship\Admin\Error_Images;
use TaobaoDropship\Admin\Import_List;
use TaobaoDropship\Admin\Imported;
use TaobaoDropship\Admin\Product;
use TaobaoDropship\Admin\Recommend;
use TaobaoDropship\Admin\Settings;

defined( 'ABSPATH' ) || exit;

class Init {
	protected static $instance = null;

	public function __construct() {
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function load_class() {
		Enqueue::instance();

		Api::instance();
		Auth::instance();
		Draft_Product::instance();
		Import_List::instance();
		Imported::instance();
		Error_Images::instance();
		Setup_Wizard::instance();
		Recommend::instance();

		if ( is_admin() ) {
			Settings::instance();
			Admin::instance();
			Product::instance();
			$this->support();
		}
	}


	public function support() {
		if ( ! class_exists( 'VillaTheme_Support' ) ) {
			require_once TBDS_CONST['plugin_dir'] . 'support/support.php';
		}
		new \VillaTheme_Support(
			[
				'support'   => 'https://wordpress.org/support/plugin/',
				'docs'      => 'http://docs.villatheme.com/chinads-taobao-dropshipping-for-woocommerce',
				'review'    => 'https://wordpress.org/support/plugin/chinads-taobao-dropshipping-for-woocommerce/reviews/?rate=5#rate-response',
				'pro_url'   => 'https://1.envato.market/PyAWE6',
				'slug'      => 'chinads-taobao-dropshipping-for-woocommerce',
				'menu_slug' => 'tbds-import-list',
				'css'       => TBDS_CONST['css_url'],
				'image'     => TBDS_CONST['img_url'],
				'version'   => TBDS_CONST['version'],
				'survey_url' => 'https://script.google.com/macros/s/AKfycbxmADIhCxfiZgx8qzWBeqY4BnQvZ00rDKe2q2WAHIp6SdgZEmJVqd-ahn1cYx-jF1q1/exec'
			]
		);
	}
}
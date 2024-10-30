<?php
/**
 * Plugin Name: ChinaDS - Taobao Dropshipping for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/chinads-taobao-dropshipping-for-woocommerce/
 * Description:  Transfer data from Taobao products to WooCommerce effortlessly.
 * Version: 2.0.5
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chinads
 * Domain Path: /languages
 * Copyright 2022 - 2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.0
 * WC requires at least: 7.0
 * WC tested up to: 8.8
 **/

namespace TaobaoDropship;

use TaobaoDropship\Admin\Error_Images_Query;
use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Init;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

global $wpdb;

define( 'TBDS_CONST', [
	'version'     => '2.0.5',
	'plugin_name' => 'ChinaDS - Taobao Dropshipping for WooCommerce',
	'slug'        => 'tbds',
	'assets_slug' => 'tbds-',
	'file'        => __FILE__,
	'img_table'   => $wpdb->prefix . 'tbds_error_product_images',
	'basename'    => plugin_basename( __FILE__ ),
	'plugin_dir'  => plugin_dir_path( __FILE__ ),
	'admin_views' => plugin_dir_path( __FILE__ ) . 'admin/views/',
	'js_url'      => plugins_url( 'assets/js/', __FILE__ ),
	'css_url'     => plugins_url( 'assets/css/', __FILE__ ),
	'libs_url'    => plugins_url( 'assets/libs/', __FILE__ ),
	'img_url'     => plugins_url( 'assets/img/', __FILE__ ),
] );

require_once plugin_dir_path( __FILE__ ) . 'autoload.php';
class TaobaoDropship {

	protected $checker;

	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'active' ] );
		add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
		if ( $this->premium_active() ) {
			return;
		}
		add_action( 'plugins_loaded', [ $this, 'check_environment' ] );
	}

	public function check_environment($recent_activate = false) {
		$include_dir = plugin_dir_path( __FILE__ ) . 'support/';

		if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
			include_once $include_dir . 'support.php';
		}
		$environment = new \VillaTheme_Require_Environment( [
				'plugin_name'     => TBDS_CONST['plugin_name'],
				'php_version'     => '7.0',
				'wp_version'      => '5.0',
				'require_plugins' => [
					[
						'slug' => 'woocommerce',
						'name' => 'WooCommerce',
						'required_version'      => '7.0',
					]
				]
			]
		);

		if ( $environment->has_error() ) {
			return;
		}
		if ( get_option( 'tbds_setup_wizard' ) &&
		     ($recent_activate || (!empty($_GET['page']) && strpos(wc_clean(wp_unslash($_GET['page'])),"tbds") === 0))) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = add_query_arg( [
				'tbds_setup_wizard' => true,
				'_wpnonce'               => wp_create_nonce( 'tbds_setup' )
			], admin_url() );
			wp_safe_redirect( $url );
			exit();
		}

		global $wpdb;
		$tables = array(
			'tbds_posts'    => 'tbds_posts',
			'tbds_postmeta' => 'tbds_postmeta'
		);
		foreach ( $tables as $name => $table ) {
			$wpdb->$name    = $wpdb->prefix . $table;
			$wpdb->tables[] = $table;
		}
		add_filter( 'plugin_action_links_' . TBDS_CONST['basename'], [ $this, 'setting_link' ] );

		$this->load_text_domain();
		Init::instance()->load_class();
	}
	public function before_woocommerce_init() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	public function setting_link( $links ) {
		$settings_link = [
			sprintf( "<a href='%1s' >%2s</a>",
				esc_url( admin_url( 'admin.php?page=tbds-settings' ) ),
				esc_html__( 'Settings', 'chinads' ) )
		];

		return array_merge( $settings_link, $links );
	}

	public function load_text_domain() {
		$locale   = determine_locale();
		$locale   = apply_filters( 'plugin_locale', $locale, 'chinads' );
		$basename = plugin_basename( dirname( __FILE__ ) );

		unload_textdomain( 'chinads' );

		load_textdomain( 'chinads', WP_LANG_DIR . "/{$basename}/{$basename}-{$locale}.mo" );
		load_plugin_textdomain( 'chinads', false, $basename . '/languages' );
	}
	public function premium_active(){
		return is_plugin_active( 'chinads-woocommerce-taobao-dropshipping/chinads-woocommerce-taobao-dropshipping.php' );
	}

	public function active( $network_wide ) {
		if (  $this->premium_active()) {
			return;
		}
		global $wpdb;
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			$current_blog = $wpdb->blogid;
			$blogs        = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching

			//Multi site activate action
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog );
				Error_Images_Query::create_table();
			}
			switch_to_blog( $current_blog );
		} else {
			//Single site activate action
			Error_Images_Query::create_table();
		}

		if ( ! get_option( 'tbds_params' ) ) {
			add_action( 'activated_plugin', [ $this, 'after_activated' ] );
		}
	}

	public function after_activated( $plugin ) {
		if ( $plugin === TBDS_CONST['basename'] ) {
			update_option('tbds_setup_wizard', 1, 'no');
			$this->check_environment(true);
		}
	}

}

new TaobaoDropship();

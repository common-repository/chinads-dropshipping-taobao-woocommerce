<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Setup_Wizard;

defined( 'ABSPATH' ) || exit;

class Recommend {
	protected static $instance = null;
	protected $settings;
	protected $dismiss;

	public function __construct() {
		$this->dismiss  = 'wad_install_recommended_plugins_dismiss';
		$this->settings = Data::instance();
		add_action( 'admin_head', [ $this, 'admin_head' ] );
	}

	public function admin_head() {
		$screen_id = get_current_screen()->id;
		global $tbds_pages;
		if ( $screen_id == ($tbds_pages['recommended_plugins']??'') ) {
			return;
		}

		$wad_dismiss_nonce = isset( $_REQUEST['wad_dismiss_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wad_dismiss_nonce'] ) ) : '';
		$dismiss_plugin    = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';

		if ( wp_verify_nonce( $wad_dismiss_nonce, 'wad_dismiss_nonce' ) ) {
			$option = $dismiss_plugin ? "{$this->dismiss}__{$dismiss_plugin}" : $this->dismiss;
			update_option( $option, time() );
		}

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public static function admin_notices_html( $message, $button, $plugin_slug ) {
		?>
        <div class="villatheme-dashboard updated" style="border-left: 4px solid #ffba00">
            <div class="villatheme-content">
                <form action="" method="get">
                    <p><?php echo wp_kses_post( $message ) ?></p>
                    <p><?php echo wp_kses_post( $button ) ?></p>
                    <a href="<?php echo esc_url( add_query_arg( array(
						'wad_dismiss_nonce' => wp_create_nonce( 'wad_dismiss_nonce' ),
						'plugin'            => $plugin_slug,
					) ) ) ?>" target="_self"
                       class="button notice-dismiss vi-button-dismiss"><?php esc_html_e( 'Dismiss', 'chinads' ) ?></a>
                </form>
            </div>
        </div>
		<?php
	}

	public function admin_notices() {
		if ( class_exists( 'VI_WOO_ALIDROPSHIP_Admin_Recommend' ) || class_exists( 'VI_WOOCOMMERCE_ALIDROPSHIP_Admin_Recommend' ) ) {
			return;
		}

		global $pagenow;
		$action              = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_plugin             = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$recommended_plugins = array(
			array(
				'slug'                => 'exmage-wp-image-links',
				'pro'                 => '',
				'name'                => 'EXMAGE – WordPress Image Links',
				'message_not_install' => sprintf( "%s <strong>EXMAGE – WordPress Image Links</strong> %s </br> %s",
					esc_html__( 'Need to save your server storage?', 'chinads' ),
					esc_html__( 'will help you solve the problem by using external image URLs.', 'chinads' ),
					esc_html__( 'When this plugin is active, "Use external links for images" option will be available in the ALD plugin settings/Product which allows to use original AliExpress product image URLs for featured image, gallery images and variation image of imported Taobao products.', 'chinads' )
				),
				'message_not_active'  => sprintf( "<strong>EXMAGE – WordPress Image Links</strong>%s",
					esc_html__( 'is currently inactive, external images added by this plugin(Post/product featured image, product gallery images...) will no longer work properly.', 'chinads' ) ),
			),
			array(
				'slug'                => 'bulky-bulk-edit-products-for-woo',
				'pro'                 => '',
				'name'                => 'Bulky – Bulk Edit Products for WooCommerce',
				'message_not_install' => sprintf( "%s <strong>Bulky – Bulk Edit Products for WooCommerce</strong>", esc_html__( 'Quickly and easily edit your products in bulk with', 'chinads' ) ),
//				'message_not_active'  => __( '<strong>Bulky – Bulk Edit Products for WooCommerce</strong> is currently inactive. Activate it to quickly edit your products in bulk', 'chinads' ),
			),
			array(
				'slug'                => 'email-template-customizer-for-woo',
				'pro'                 => 'woocommerce-email-template-customizer',
				'name'                => 'Email Template Customizer for WooCommerce',
				'message_not_install' => sprintf( "%s <strong>Email Template Customizer for WooCommerce</strong> %s",
					esc_html__( 'Try our brand new', 'chinads' ),
					esc_html__( 'plugin to easily customize your WooCommerce emails and make them more beautiful and professional.', 'chinads' ) )
//				'message_not_active'  => __( '<strong>Email Template Customizer for WooCommerce</strong> is currently inactive. Activate it to customize WooCommerce emails with ease and make your customers more satisfied when receiving your emails.', 'chinads' ),
			),
			array(
				'slug'                => 'product-variations-swatches-for-woocommerce',
				'pro'                 => 'woocommerce-product-variations-swatches',
				'name'                => 'Product Variations Swatches for WooCommerce',
				'message_not_install' => sprintf( "%s <strong>Product Variations Swatches for WooCommerce</strong> %s",
					esc_html__( 'Need a variations swatches plugin that works perfectly with ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce?', 'chinads' ),
					esc_html__( 'is what you need.', 'chinads' ) ),
				'message_not_active'  => sprintf( "<strong>Product Variations Swatches for WooCommerce</strong> %s",
					esc_html__( 'is currently inactive, this prevents variable products from displaying beautifully.', 'chinads' ) ),
			),
		);

		$plugins = get_plugins();
		foreach ( $recommended_plugins as $recommended_plugin ) {
			$plugin_slug = $recommended_plugin['slug'];
			if ( ! get_option( "{$this->dismiss}__{$plugin_slug}" ) ) {
				if ( ! empty( $recommended_plugin['pro'] ) && is_plugin_active( "{$recommended_plugin['pro']}/{$recommended_plugin['pro']}.php" ) ) {
					continue;
				}
				$plugin = "{$plugin_slug}/{$plugin_slug}.php";
				if ( ! isset( $plugins[ $plugin ] ) ) {
					if ( ! ( $pagenow === 'update.php' && $action === 'install-plugin' && $_plugin === $plugin_slug ) ) {
						$button = '<a href="' . esc_url( wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$plugin_slug}" ), "install-plugin_{$plugin_slug}" ) ) . '" target="_self" class="button button-primary">' . esc_html__( 'Install now', 'chinads' ) . '</a>';
						self::admin_notices_html( $recommended_plugin['message_not_install'], $button, $plugin_slug );
					}
				} elseif ( ! is_plugin_active( $plugin ) && ! empty( $recommended_plugin['message_not_active'] ) ) {
					$button = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array(
							'action' => 'activate',
							'plugin' => $plugin
						), admin_url( 'plugins.php' ) ), "activate-plugin_{$plugin}" ) ) . '" target="_self" class="button button-primary">' . esc_html__( 'Activate now', 'chinads' ) . '</a>';
					self::admin_notices_html( $recommended_plugin['message_not_active'], $button, $plugin_slug );
				}
			}
		}
	}

	public function page_callback() {
		$plugins = Setup_Wizard::recommended_plugins();
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Recommended plugins', 'chinads' ) ?></h2>
            <table cellspacing="0" id="status" class="vi-ui celled table">
                <thead>
                <tr>
                    <th></th>
                    <th><?php esc_html_e( 'Plugins', 'chinads' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'chinads' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$installed_plugins = get_plugins();
				foreach ( $plugins as $plugin ) {
					$plugin_id = "{$plugin['slug']}/{$plugin['slug']}.php";
					?>
                    <tr>
                        <td>
                            <a target="_blank" href="<?php echo esc_url( "https://wordpress.org/plugins/{$plugin['slug']}" ) ?>">
                                <img src="<?php echo esc_url( $plugin['img'] ) ?>" width="60" height="60">
                            </a>
                        </td>
                        <td class="fist-col">
                            <div class="vi-wad-plugin-name">
                                <a target="_blank" href="<?php echo esc_url( "https://wordpress.org/plugins/{$plugin['slug']}" ) ?>">
                                    <strong><?php echo esc_html( $plugin['name'] ) ?></strong>
                                </a>
                            </div>
                            <div>
								<?php
								if ( ! isset( $installed_plugins[ $plugin_id ] ) ) {
									?>
                                    <a href="<?php echo esc_url( wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$plugin['slug']}" ), "install-plugin_{$plugin['slug']}" ) ) ?>" target="_blank">
										<?php esc_html_e( 'Install', 'chinads' ); ?>
                                    </a>
									<?php
								} elseif ( ! is_plugin_active( $plugin_id ) ) {
									?>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'activate', 'plugin' => $plugin_id ], admin_url( 'plugins.php' ) ), "activate-plugin_{$plugin_id}" ) ) ?>" target="_blank">
										<?php esc_html_e( 'Activate', 'chinads' ); ?>
                                    </a>
									<?php
								} else {
									esc_html_e( 'Currently active', 'chinads' );
								}
								?>
                            </div>
                        </td>
                        <td><?php echo esc_html( $plugin['desc'] ) ?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
        </div>
		<?php
	}
}

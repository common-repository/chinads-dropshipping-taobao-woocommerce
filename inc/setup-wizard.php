<?php

namespace TaobaoDropship\Inc;

use Automattic\WooCommerce\Admin\PluginsHelper;

defined( 'ABSPATH' ) || exit;

class Setup_Wizard {
	protected static $instance = null;
	protected $settings;
	protected $plugins = [];
	protected $current_url;

	public function __construct() {
		$this->settings = Data::instance();
		$this->plugins_init();
		add_action( 'admin_head', [ $this, 'setup_wizard' ] );
		add_action( 'wp_ajax_tbds_setup_install_plugins', [ $this, 'install_plugins' ] );
		add_action( 'wp_ajax_tbds_setup_activate_plugins', [ $this, 'activate_plugins' ] );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	protected function plugins_init() {
		return $this->plugins = self::recommended_plugins();
	}

	public static function recommended_plugins() {
		return [
			[
				'slug' => 'exmage-wp-image-links',
				'name' => 'EXMAGE – WordPress Image Links',
				'desc' => __( 'Save storage by using external image URLs. This plugin is required if you want to use external URLs(Taobao cdn image URLs) for product featured image, gallery images and variation image.', 'chinads' ),
				'img'  => 'https://ps.w.org/exmage-wp-image-links/assets/icon-128x128.jpg'
			],
			[
				'slug' => 'bulky-bulk-edit-products-for-woo',
				'name' => 'Bulky – Bulk Edit Products for WooCommerce',
				'desc' => __( 'The plugin offers sufficient simple and advanced tools to help filter various available attributes of simple and variable products such as ID, Title, Content, Excerpt, Slugs, SKU, Post date, range of regular price and sale price, Sale date, range of stock quantity, Product type, Categories.... Users can quickly search for wanted products fields and work with the product fields in bulk.', 'chinads' ),
				'img'  => 'https://ps.w.org/bulky-bulk-edit-products-for-woo/assets/icon-128x128.png'
			],
			[
				'slug' => 'woo-cart-all-in-one',
				'name' => 'Cart All In One For WooCommerce',
				'desc' => __( 'All cart features you need in one simple plugin', 'chinads' ),
				'img'  => 'https://ps.w.org/woo-cart-all-in-one/assets/icon-128x128.png'
			],
			[
				'slug' => 'email-template-customizer-for-woo',
				'name' => 'Email Template Customizer for WooCommerce',
				'desc' => __( 'Customize WooCommerce emails to make them more beautiful and professional after only several mouse clicks.', 'chinads' ),
				'img'  => 'https://ps.w.org/email-template-customizer-for-woo/assets/icon-128x128.jpg'
			],
			[
				'slug' => 'product-variations-swatches-for-woocommerce',
				'name' => 'Product Variations Swatches for WooCommerce',
				'desc' => __( 'Product Variations Swatches for WooCommerce is a professional plugin that allows you to show and select attributes for variation products. The plugin displays variation select options of the products under colors, buttons, images, variation images, radio so it helps the customers observe the products they need more visually, save time to find the wanted products than dropdown type for variations of a variable product.', 'chinads' ),
				'img'  => 'https://ps.w.org/product-variations-swatches-for-woocommerce/assets/icon-128x128.jpg'
			],
			[
				'slug' => 'woo-abandoned-cart-recovery',
				'name' => 'Abandoned Cart Recovery for WooCommerce',
				'desc' => __( 'Helps you to recovery unfinished order in your store. When a customer adds a product to cart but does not complete check out. After a scheduled time, the cart will be marked as “abandoned”. The plugin will start to send cart recovery email or facebook message to the customer, remind him/her to complete the order.', 'chinads' ),
				'img'  => 'https://ps.w.org/woo-abandoned-cart-recovery/assets/icon-128x128.png'
			],
			[
				'slug' => 'woo-photo-reviews',
				'name' => 'Photo Reviews for WooCommerce',
				'desc' => __( 'An ultimate review plugin for WooCommerce which helps you send review reminder emails, allows customers to post reviews include product pictures and send thank you emails with WooCommerce coupons to customers.', 'chinads' ),
				'img'  => 'https://ps.w.org/woo-photo-reviews/assets/icon-128x128.jpg'
			],
			[
				'slug' => 'woo-orders-tracking',
				'name' => 'Order Tracking for WooCommerce',
				'desc' => __( 'Allows you to bulk add tracking code to WooCommerce orders. Then the plugin will send tracking email with tracking URLs to customers. The plugin also helps you to add tracking code and carriers name to your PayPal transactions. This option will save you tons of time and avoid mistake when adding tracking code to PayPal.', 'chinads' ),
				'img'  => 'https://ps.w.org/woo-orders-tracking/assets/icon-128x128.jpg'
			],
		];
	}

	public function setup_wizard() {
		if ( isset( $_POST['submit'] ) && $_POST['submit'] === 'tbds_install_recommend_plugins' ) {
			try {
				$wc_install = new \WC_Install();
				if ( is_array( $this->plugins ) && ! empty( $this->plugins ) ) {
					foreach ( $this->plugins as $plugin ) {
						$slug_name = $this->set_name( $plugin['slug'] );
						if ( ! empty( $_POST[ $slug_name ] ) ) {
							$wc_install::background_installer( $plugin['slug'], [ 'name' => $plugin['name'], 'repo-slug' => $plugin['slug'] ] );
						}
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=tbds-settings' ) );
				exit;
			} catch ( \Exception $e ) {

			}
		}

		if ( ! empty( $_GET['tbds_setup_wizard'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'tbds_setup' ) ) {
			$step = isset( $_GET['step'] ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 1;
			$func = 'set_up_step_' . $step;

			if ( method_exists( $this, $func ) ) {
				delete_option('tbds_setup_wizard');
				if ( isset( $_SERVER['REQUEST_URI'] ) ) {
					$this->current_url = remove_query_arg( 'step', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
				}
				?>
                <div id="tbds-setup-wizard">

                    <div class="tbds-logo">
                        <img src="<?php echo esc_url( TBDS_CONST['img_url'] . 'icon-256x256.png' ) ?>" alt="" width="80"/>
                    </div>

                    <h1><?php printf( "%s %s", esc_html( TBDS_CONST['plugin_name'] ), esc_html__( 'Setup Wizard', 'chinads' ) ); ?></h1>

                    <div class="tbds-wrapper vi-ui segment">
						<?php
						$this->$func();
						?>
                    </div>
                    <div class="tbds-skip-btn">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tbds-settings' ) ) ?>">
							<?php esc_html_e( 'Skip', 'chinads' ); ?>
                        </a>
                    </div>
                </div>
				<?php
				do_action( 'tbds_print_scripts' );
			}
			exit;
		}
	}

	public function set_name( $slug ) {
		return esc_attr( 'vi_install_' . str_replace( '-', '_', $slug ) );
	}

	public function set_up_step_1() {
		?>
        <h2><?php esc_html_e( 'Extension configuration', 'chinads' ); ?></h2>
        <div class="tbds-step-1">
            <table class="vi-ui table">
                <tr>
                    <td><?php esc_html_e( 'Install Chrome Extension', 'chinads' ); ?></td>
                    <td>
                        <a href="https://downloads.villatheme.com/?download=chinads-extension" target="_blank">
							<?php printf( "%s %s", esc_html( TBDS_CONST['plugin_name'] ), esc_html__( 'Extension', 'chinads' ) ); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Video guide', 'chinads' ); ?></td>
                    <td>
                        <h5><?php esc_html_e( 'Register account', 'chinads' ); ?></h5>
                        <iframe width="560" height="315" allowfullscreen src="https://www.youtube-nocookie.com/embed/kO9KxF4DNXE" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>

                        <h5><?php esc_html_e( 'Install and use', 'chinads' ); ?></h5>
                        <iframe width="560" height="315" allowfullscreen src="https://www.youtube-nocookie.com/embed/weZk4kWoPhw" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </td>
                </tr>
            </table>
        </div>
        <div class="tbds-btn-group">
            <a href="<?php echo esc_url( $this->current_url . '&step=2' ) ?>" class="vi-ui button primary">
				<?php esc_html_e( 'Next', 'chinads' ); ?>
            </a>
        </div>
		<?php
	}

	public function set_up_step_2() {
		$currency_rate = round( Utils::get_exchange_rate(),2);
		$current_currency = get_woocommerce_currency();
		?>
        <h2><?php esc_html_e( 'Plugin configuration', 'chinads' ); ?></h2>
        <form method="post" action="" class="vi-ui form setup-wizard">
            <div class="tbds-step-2">
				<?php wp_nonce_field( 'tbds-settings' ) ?>
                <input type="hidden" name="tbds_setup_redirect" value="<?php echo esc_url( $this->current_url . '&step=3' ) ?>">
                <table class="vi-ui table">
                    <tr>
                        <td>
                            <label for="">
								<?php esc_html_e( 'Default categories', 'chinads' ); ?>
                            </label>
                        </td>
                        <td>
                            <select name="tbds_product_categories[]" class="tbds-product-categories search-category" id="tbds-product-categories" multiple>
								<?php
								if ( ! empty( $this->settings->get_param( 'product_categories' ) ) && is_array( $this->settings->get_param( 'product_categories' ) ) ) {
									$categories = $this->settings->get_param( 'product_categories' );
									foreach ( $categories as $category_id ) {
										$category = get_term( $category_id );
										if ( $category ) {
											printf( '<option value="%s" selected>%s</option>', esc_attr( $category_id ), esc_html( $category->name ) );
										}
									}
								}
								?>
                            </select>
                            <p><?php esc_html_e( 'Imported products will be added to these categories.', 'chinads' ) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Import products currency exchange rate', 'chinads' ) ?></td>
                        <td>
                            <input type="text" id="tbds-import-currency-rate" name="tbds_import_currency_rate"
                                   value="<?php echo esc_attr( $this->settings->get_param( 'import_currency_rate' ) ) ?>"/>
                            <p><?php esc_html_e( 'This is exchange rate to convert from CNY to your store currency.', 'chinads' ) ?></p>
                            <p><?php echo wp_kses_post(sprintf(esc_html__( 'E.g: Your Woocommerce store currency is %1$s, exchange rate is: 1 CNY = %2$s %1$s',//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			                        'chinads' ), $current_currency, $currency_rate)) ?></p>
                            <p><?php echo wp_kses_post(sprintf(esc_html__( '=> set "Import products currency exchange rate" %s','chinads' ),$currency_rate))//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <table class="vi-ui celled table price-rule">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Price range', 'chinads' ) ?></th>
                                    <th><?php esc_html_e( 'Actions', 'chinads' ) ?></th>
                                    <th><?php esc_html_e( 'Sale price', 'chinads' ) ?>
                                        <div class="tbds-description">
											<?php esc_html_e( '(Set -1 to not use sale price)', 'chinads' ) ?>
                                        </div>
                                    </th>
                                    <th style="min-width: 135px"><?php esc_html_e( 'Regular price', 'chinads' ) ?></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody class="tbds-price-rule-container ui-sortable">
								<?php
								$price_from       = $this->settings->get_param( 'price_from' );
								$price_default    = $this->settings->get_param( 'price_default' );
								$price_to         = $this->settings->get_param( 'price_to' );
								$plus_value       = $this->settings->get_param( 'plus_value' );
								$plus_sale_value  = $this->settings->get_param( 'plus_sale_value' );
								$plus_value_type  = $this->settings->get_param( 'plus_value_type' );
								$price_from_count = count( (array) $price_from );

								if ( $price_from_count > 0 ) {
									/*adjust price rules since version 1.0.1.1*/
									if ( ! is_array( $price_to ) || count( $price_to ) !== $price_from_count ) {
										if ( $price_from_count > 1 ) {
											$price_to   = array_values( array_slice( $price_from, 1 ) );
											$price_to[] = '';
										} else {
											$price_to = array( '' );
										}
									}
									for ( $i = 0; $i < count( $price_from ); $i ++ ) {
										switch ( $plus_value_type[ $i ] ) {
											case 'fixed':
												$value_label_left  = '+';
												$value_label_right = '¥';
												break;
											case 'percent':
												$value_label_left  = '+';
												$value_label_right = '%';
												break;
											case 'multiply':
												$value_label_left  = 'x';
												$value_label_right = '';
												break;
											default:
												$value_label_left  = '=';
												$value_label_right = '¥';
										}
										?>
                                        <tr class="tbds-price-rule-row">
                                            <td>
                                                <div class="equal width fields">
                                                    <div class="field">
                                                        <div class="vi-ui left labeled input fluid">
                                                            <label for="amount" class="vi-ui label">¥</label>
                                                            <input step="any" type="number" min="0" value="<?php echo esc_attr( $price_from[ $i ] ); ?>" name="tbds_price_from[]" class="tbds-price-from">
                                                        </div>
                                                    </div>
                                                    <span class="tbds-price-from-to-separator">-</span>
                                                    <div class="field">
                                                        <div class="vi-ui left labeled input fluid">
                                                            <label for="amount" class="vi-ui label">¥</label>
                                                            <input step="any" type="number" min="0" value="<?php echo esc_attr( $price_to[ $i ] ); ?>" name="tbds_price_to[]" class="tbds-price-to">
                                                        </div>
                                                    </div>

                                                </div>
                                            </td>
                                            <td>
                                                <select name="tbds_plus_value_type[]"
                                                        class="vi-ui fluid dropdown tbds-plus-value-type">
                                                    <option value="fixed" <?php selected( $plus_value_type[ $i ], 'fixed' ) ?>><?php esc_html_e( 'Increase by Fixed amount(¥)', 'chinads' ) ?></option>
                                                    <option value="percent" <?php selected( $plus_value_type[ $i ], 'percent' ) ?>><?php esc_html_e( 'Increase by Percentage(%)', 'chinads' ) ?></option>
                                                    <option value="multiply" <?php selected( $plus_value_type[ $i ], 'multiply' ) ?>><?php esc_html_e( 'Multiply with', 'chinads' ) ?></option>
                                                    <option value="set_to" <?php selected( $plus_value_type[ $i ], 'set_to' ) ?>><?php esc_html_e( 'Set to', 'chinads' ) ?></option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="vi-ui right labeled input fluid">
                                                    <label for="amount" class="vi-ui label tbds-value-label-left">
														<?php echo esc_html( $value_label_left ) ?>
                                                    </label>
                                                    <input type="number" min="-1" step="any" value="<?php echo esc_attr( $plus_sale_value[ $i ] ); ?>" name="tbds_plus_sale_value[]" class="tbds-plus-sale-value">
                                                    <div class="vi-ui basic label tbds-value-label-right">
														<?php echo esc_html( $value_label_right ) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="vi-ui right labeled input fluid">
                                                    <label for="amount" class="vi-ui label tbds-value-label-left">
														<?php echo esc_html( $value_label_left ) ?>
                                                    </label>
                                                    <input type="number" min="0" step="any" value="<?php echo esc_attr( $plus_value[ $i ] ); ?>" name="tbds_plus_value[]" class="tbds-plus-value">
                                                    <div class="vi-ui basic label tbds-value-label-right">
														<?php echo esc_html( $value_label_right ) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="">
                                                    <span class="vi-ui button icon negative mini tbds-price-rule-remove"><i class="icon trash"> </i></span>
                                                </div>
                                            </td>
                                        </tr>
										<?php
									}
								}
								?>
                                </tbody>
                                <tfoot>
								<?php
								$plus_value_type_d = isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply';
								$plus_sale_value_d = isset( $price_default['plus_sale_value'] ) ? $price_default['plus_sale_value'] : 1;
								$plus_value_d      = isset( $price_default['plus_value'] ) ? $price_default['plus_value'] : 2;
								switch ( $plus_value_type_d ) {
									case 'fixed':
										$value_label_left  = '+';
										$value_label_right = '¥';
										break;
									case 'percent':
										$value_label_left  = '+';
										$value_label_right = '%';
										break;
									case 'multiply':
										$value_label_left  = 'x';
										$value_label_right = '';
										break;
									default:
										$value_label_left  = '=';
										$value_label_right = '¥';
								}
								?>
                                <tr class="tbds-price-rule-row-default">
                                    <th><?php esc_html_e( 'Default', 'chinads' ) ?></th>
                                    <th>
                                        <select name="tbds_price_default[plus_value_type]"
                                                class="vi-ui fluid dropdown tbds-plus-value-type">
                                            <option value="fixed" <?php selected( $plus_value_type_d, 'fixed' ) ?>><?php esc_html_e( 'Increase by Fixed amount(¥)', 'chinads' ) ?></option>
                                            <option value="percent" <?php selected( $plus_value_type_d, 'percent' ) ?>><?php esc_html_e( 'Increase by Percentage(%)', 'chinads' ) ?></option>
                                            <option value="multiply" <?php selected( $plus_value_type_d, 'multiply' ) ?>><?php esc_html_e( 'Multiply with', 'chinads' ) ?></option>
                                            <option value="set_to" <?php selected( $plus_value_type_d, 'set_to' ) ?>><?php esc_html_e( 'Set to', 'chinads' ) ?></option>
                                        </select>
                                    </th>
                                    <th>
                                        <div class="vi-ui right labeled input fluid">
                                            <label for="amount" class="vi-ui label tbds-value-label-left">
												<?php echo esc_html( $value_label_left ) ?>
                                            </label>
                                            <input type="number" min="-1" step="any" value="<?php echo esc_attr( $plus_sale_value_d ); ?>" name="tbds_price_default[plus_sale_value]" class="tbds-plus-sale-value">
                                            <div class="vi-ui basic label tbds-value-label-right">
												<?php echo esc_html( $value_label_right ) ?>
                                            </div>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="vi-ui right labeled input fluid">
                                            <label for="amount" class="vi-ui label tbds-value-label-left">
												<?php echo esc_html( $value_label_left ) ?>
                                            </label>
                                            <input type="number" min="0" step="any" value="<?php echo esc_attr( $plus_value_d ); ?>" name="tbds_price_default[plus_value]" class="tbds-plus-value">
                                            <div class="vi-ui basic label tbds-value-label-right">
												<?php echo esc_html( $value_label_right ) ?>
                                            </div>
                                        </div>
                                    </th>
                                    <th>
                                    </th>
                                </tr>
                                </tfoot>
                            </table>
                            <span class="tbds-price-rule-add vi-ui button icon positive mini">
                                <i class="icon add"> </i>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="tbds-btn-group">
                <a href="<?php echo esc_url( $this->current_url . '&step=1' ) ?>" class="vi-ui button">
					<?php esc_html_e( 'Back', 'chinads' ); ?>
                </a>
                <button type="submit" name="tbds_save_settings" class="vi-ui button primary" value="tbds_wizard_submit">
					<?php esc_html_e( 'Next', 'chinads' ); ?>
                </button>
            </div>
        </form>
		<?php
	}

	public function set_up_step_3() {
		$plugins = $this->plugins;
		?>
        <form method="post" style="margin-bottom: 0">
            <div class="tbds-step-3">
                <div class="">
                    <table id="status" class="vi-ui table">
                        <thead>
                        <tr>
                            <th><input type="checkbox" checked class="tbds-toggle-select-plugin"></th>
                            <th></th>
                            <th><?php esc_html_e( 'Recommended plugins', 'chinads' ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						foreach ( $plugins as $plugin ) {
							$plugin_url = "https://wordpress.org/plugins/{$plugin['slug']}";
							?>
                            <tr>
                                <td>
                                    <input type="checkbox" value="1" checked class="tbds-select-plugin"
                                           data-plugin_slug="<?php echo esc_attr( $plugin['slug'] ) ?>"
                                           name="<?php echo esc_attr($this->set_name( $plugin['slug'] )) ?>">
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $plugin_url ) ?>" target="_blank">
                                        <img src="<?php echo esc_url( $plugin['img'] ) ?>" width="60" height="60">
                                    </a>
                                </td>
                                <td>
                                    <div class="tbds-plugin-name">
                                        <a href="<?php echo esc_url( $plugin_url ) ?>" target="_blank">
                                            <span style="font-weight: 700"> <?php echo wp_kses_post( $plugin['name'] ) ?></span>
                                        </a>
                                    </div>
                                    <div style="text-align: justify"><?php echo wp_kses_post( $plugin['desc'] ) ?></div>
                                </td>
                            </tr>
							<?php
						}
						?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tbds-btn-group">
                <a href="<?php echo esc_url( $this->current_url . '&step=2' ) ?>" class="vi-ui button">
					<?php esc_html_e( 'Back', 'chinads' ); ?>
                </a>
                <button type="submit" class="vi-ui button primary tbds-finish" name="submit" value="tbds_install_recommend_plugins">
					<?php esc_html_e( 'Install & Return to Dashboard', 'chinads' ); ?>
                </button>
            </div>
        </form>
		<?php
	}

	public function install_plugins() {
		check_ajax_referer( 'tbds_security', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$plugins = isset( $_POST['install_plugins'] ) ? wc_clean( wp_unslash( $_POST['install_plugins'] ) ) : array();

		if ( ! is_array( $plugins ) && ! count( $plugins ) ) {
			wp_send_json_error();
		}

		include_once ABSPATH . '/wp-admin/includes/admin.php';
		include_once ABSPATH . '/wp-admin/includes/plugin-install.php';
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . '/wp-admin/includes/class-plugin-upgrader.php';

		$existing_plugins  = PluginsHelper::get_installed_plugins_paths();
		$installed_plugins = array();

		foreach ( $plugins as $plugin ) {
			$slug = sanitize_key( $plugin );

			if ( isset( $existing_plugins[ $slug ] ) ) {
				$installed_plugins[] = $plugin;
				continue;
			}

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'sections' => false,
					),
				)
			);

			if ( ! is_wp_error( $api ) ) {
				$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
				$result   = $upgrader->install( $api->download_link );
				if ( ! is_wp_error( $result ) && ! is_null( $result ) ) {
					$installed_plugins[] = $plugin;
				}
			}
		}
		if ( count( $installed_plugins ) ) {
			wp_send_json_success( array( 'installed_plugins' => $installed_plugins ) );
		} else {
			wp_send_json_error();
		}
	}

	public function activate_plugins() {
		check_ajax_referer( 'tbds_security', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$plugin_paths = PluginsHelper::get_installed_plugins_paths();
		$plugins      = isset( $_POST['install_plugins'] ) ? wc_clean( wp_unslash( $_POST['install_plugins'] ) ) : array();

		if ( ! is_array( $plugins ) && ! count( $plugins ) ) {
			wp_send_json_error();
		}

		$activated_plugins = array();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// the mollie-payments-for-woocommerce plugin calls `WP_Filesystem()` during it's activation hook, which crashes without this include.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		foreach ( $plugins as $plugin ) {
			$slug = $plugin;
			$path = isset( $plugin_paths[ $slug ] ) ? $plugin_paths[ $slug ] : false;
			if ( $path ) {
				$result = activate_plugin( $path );
				if ( is_null( $result ) ) {
					$activated_plugins[] = $plugin;
				}
			}
		}

		if ( count( $activated_plugins ) ) {
			wp_send_json_success( array( 'activated_plugins' => $activated_plugins ) );
		} else {
			wp_send_json_error();
		}
	}
}

<?php

namespace TaobaoDropship\Admin;

defined( 'ABSPATH' ) || exit;

class Auth {
	protected static $instance = null;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_filter( 'woocommerce_locate_template', array( $this, 'woocommerce_locate_template' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function admin_menu() {
		add_submenu_page( '',
			esc_html__( 'Auth', 'chinads' ),
			esc_html__( 'Auth', 'chinads' ),
			'manage_options',
			'tbds-auth',
			[ $this, 'auth_page' ]
		);
	}

	public function auth_page() {
		$api_credentials = get_option( 'tbds_temp_api_credentials', array() );
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Authorize ChinaDS - Taobao Dropshipping for WooCommerce Extension', 'chinads' ) ?></h2>
			<?php
			if ( ! empty( $api_credentials['consumer_key'] ) && ! empty( $api_credentials['consumer_secret'] ) ) {
				?>
                <form method="post" class="tbds-auth-form">
                    <input type="hidden" value="<?php echo esc_attr( $api_credentials['consumer_key'] ) ?>"
                           name="tbds_consumer_key">
                    <input type="hidden" value="<?php echo esc_attr( $api_credentials['consumer_secret'] ) ?>"
                           name="tbds_consumer_secret">
                </form>
				<?php
			}
			?>
        </div>
		<?php
		delete_option( 'tbds_temp_api_credentials' );
	}


	public function woocommerce_locate_template( $template, $template_name, $template_path ) {
		global $woocommerce;

		$_template = $template;

		if ( ! $template_path ) {
			$template_path = $woocommerce->template_url;
		}

		$plugin_path = TBDS_CONST['plugin_dir'] . '/templates/woocommerce/';

		// Look within passed path within the theme - this is priority
		$template = locate_template( [ $template_path . $template_name, $template_name ] );

		// Modification: Get the template from this plugin, if it exists
		if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
			$template = $plugin_path . $template_name;
		}

		// Use default template
		if ( ! $template ) {
			$template = $_template;
		}

		// Return what we found
		return $template;
	}

	public function admin_enqueue_scripts() {
		global $pagenow;
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pagenow === 'admin.php' && $page === 'tbds-auth' ) {
			wp_enqueue_script( 'tbds-auth', TBDS_CONST['js_url'] . 'auth.js', array( 'jquery' ), TBDS_CONST['version'], false );
		}
	}

}

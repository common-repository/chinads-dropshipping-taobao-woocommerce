<?php

namespace TaobaoDropship\Admin;

defined( 'ABSPATH' ) || exit;

class Admin {
	protected static $instance = null;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function admin_menu() {
		global $tbds_pages;

		add_menu_page(
			'ChinaDS',
			'ChinaDS',
			'manage_options',
			'tbds-import-list',
			[ $this, 'import_list_page' ],
			TBDS_CONST['img_url'] . 'icon.png',
			5
		);


		$tbds_pages['import_list'] = add_submenu_page(
			'tbds-import-list',
			esc_html__( 'Import List', 'chinads' ),
			esc_html__( 'Import List', 'chinads' ),
			'manage_options',
			'tbds-import-list',
			[ $this, 'import_list_page' ]
		);

		add_action( "load-{$tbds_pages['import_list']}", [ $this, 'import_list_screen_options_page' ] );

		$tbds_pages['imported'] = add_submenu_page(
			'tbds-import-list',
			esc_html__( 'Imported', 'chinads' ),
			esc_html__( 'Imported', 'chinads' ),
			'manage_options',
			'tbds-imported',
			[ $this, 'imported_list_page' ]
		);

		add_action( "load-{$tbds_pages['imported']}", [ $this, 'imported_screen_options_page' ] );

		$tbds_pages['error_images'] = add_submenu_page(
			'tbds-import-list',
			esc_html__( 'Failed Images', 'chinads' ),
			esc_html__( 'Failed Images', 'chinads' ),
			'manage_options',
			'tbds-error-images',
			[ $this, 'error_images_page' ]
		);

		add_action( "load-{$tbds_pages['error_images']}", [ $this, 'error_images_screen_options_page' ] );

		$tbds_pages['settings'] = add_submenu_page(
			'tbds-import-list',
			esc_html__( 'Settings', 'chinads' ),
			esc_html__( 'Settings', 'chinads' ),
			'manage_options',
			'tbds-settings',
			[ $this, 'settings_page' ]
		);
		$tbds_pages['recommended_plugins'] = add_submenu_page(
			'tbds-import-list',
			esc_html__( 'Recommended plugins', 'chinads' ),
			esc_html__( 'Recommended plugins', 'chinads' ),
			'manage_options',
			'tbds-plugins-recommend',
			[ $this, 'recommended_plugins_page' ]
		);
	}

	public function import_list_screen_options_page() {
		add_screen_option( 'per_page', [
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 5,
			'option'  => 'tbds_import_list_per_page'
		] );
	}

	public function imported_screen_options_page() {
		add_screen_option( 'per_page', [
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 5,
			'option'  => 'tbds_imported_per_page'
		] );
	}

	public function error_images_screen_options_page() {
		add_screen_option( 'per_page', [
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 10,
			'option'  => 'tbds_error_images_per_page'
		] );
	}

	public function save_screen_options( $status, $option, $value ) {
		if ( in_array( $option, [ 'tbds_import_list_per_page', 'tbds_imported_per_page', 'tbds_error_images_per_page' ] ) ) {
			return $value;
		}

		return $status;
	}

	public function settings_page() {
		Settings::instance()->settings_page();
	}

	public function import_list_page() {
		Import_List::instance()->page_callback();
	}

	public function imported_list_page() {
		Imported::instance()->imported_list_callback();
	}

	public function error_images_page() {
		Error_Images::instance()->page_callback();
	}

	public function recommended_plugins_page() {
		Recommend::instance()->page_callback();
	}

	public function admin_notices() {
		$errors              = [];
		$permalink_structure = get_option( 'permalink_structure' );

		if ( ! $permalink_structure ) {
			$errors[] = sprintf( "%s <a href='%s' target='_blank'>%s</a> %s",
				esc_html__( 'You are using Permalink structure as Plain. Please go to', 'chinads' ),
				esc_html( admin_url( 'options-permalink.php' ) ),
				esc_html__( 'Permalink Settings', 'chinads' ),
				esc_html__( 'to change it', 'chinads' )
			);
		}

		if ( ! is_ssl() ) {
			$errors[] = sprintf( "%s <a href='https://wordpress.org/documentation/article/https-for-wordpress/' target='_blank'>%s</a>",
				esc_html__( 'Your site is not using HTTPS. For more details, please read', 'chinads' ),
				esc_html__( 'HTTPS for WordPress', 'chinads' )
			);
		}

		if ( ! empty( $errors ) ) {
			?>
            <div class="error">
                <h3>
					<?php
					echo esc_html( TBDS_CONST['plugin_name'] ) . ': ' . esc_html( _n( 'you can not import products unless below issue is resolved',
							'you can not import products unless below issues are resolved',
							count( $errors ), 'chinads' ) );
					?>
                </h3>
				<?php
				foreach ( $errors as $error ) {
					echo wp_kses_post( "<p>{$error}</p>" );
				}
				?>
            </div>
			<?php
		}
	}
}


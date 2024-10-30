<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Admin\Background_Process\Taobao_Product_Migrate_New_Table;
use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Taobao_Post;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

class Settings {
	protected static $instance = null;
	protected $dropdown_class = 'fluid tbds-dropdown';
	protected $settings;
	public static $migrate_process;

	public function __construct() {
		$this->settings = Data::instance();
		add_action( 'init', [ $this, 'save_settings' ] );
		add_action( 'init', [ $this, 'background_process' ] );
		add_action( 'tbds_price_rule', [ $this, 'price_rule' ] );
		add_action( 'tbds_admin_field_video_guide', [ $this, 'video_guide' ] );

		add_filter( 'tbds_merge_external_options_before_save', [ $this, 'merge_external_options_before_save' ] );
		add_action( 'wp_ajax_tbds_search_cate', [ $this, 'search_cate' ] );
		add_action( 'wp_ajax_tbds_search_tags', [ $this, 'search_tags' ] );
		add_action( 'wp_ajax_vichinads_migrate_to_new_table', array( $this, 'migrate_to_new_table' ) );
		add_action( 'wp_ajax_vichinads_migrate_remove_old_data', array( $this, 'remove_old_data' ) );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}
	public static function migrate_process() {
        if (self::$migrate_process === null){
	        self::$migrate_process = Taobao_Product_Migrate_New_Table::instance();
        }
		return self::$migrate_process;
	}
	public function background_process() {
        self::$migrate_process = Taobao_Product_Migrate_New_Table::instance();
	}
	public function migrate_to_new_table() {
		check_ajax_referer( 'tbds_security' );
		Taobao_Products_Table::maybe_create_table();
		$migrate_process = Taobao_Product_Migrate_New_Table::instance( true );

		if ( $migrate_process->is_queue_empty() && ! $migrate_process->is_process_running() ) {
			$migrate_process->push_to_queue( [ 'step' => 'move' ] );
			$migrate_process->save()->dispatch();
		}

		wp_send_json_success( esc_html__( 'Migration progress has started running in the background.', 'chinads' ) );
	}

	public function remove_old_data() {
		check_ajax_referer( 'tbds_security' );
		$migrate_process = Taobao_Product_Migrate_New_Table::instance( true );

		if ( $migrate_process->is_queue_empty() && ! $migrate_process->is_process_running() ) {
			$migrate_process->push_to_queue( [ 'step' => 'delete' ] );
			$migrate_process->save()->dispatch();
		}

		wp_send_json_success( esc_html__( 'Deletion progress has started running in the background.', 'chinads' ) );
	}
	public function settings_page() {
		$tabs      = $this->define_tabs();
		$first_tab = array_key_first( $tabs );

		if (!$this->settings->get_params('use_tbds_table') || !get_option('tbds_deleted_old_posts_data')){
			$count_tbds_posts = array_sum( (array)wp_count_posts('tbds_draft_product'));
			if (!$count_tbds_posts){
				Taobao_Products_Table::maybe_create_table();
				update_option( 'tbds_deleted_old_posts_data', true );
				update_option( 'tbds_migrated_to_new_table', true );
			}else{
				?>
                <div class="vi-ui message">
                    <div class="header"><?php esc_html_e( 'Data storage for Taobao products', 'chinads' ) ?>
                        :
                    </div>
                    <div class="tbds-table-wrap">
						<?php
						$migrate_process = self::migrate_process();
						global $wpdb;
						if (!Taobao_Post::use_tbds_table()){
							if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
								if ( $count_tbds_posts ) {
									$migrated = (int)$wpdb->get_var( "select count(*) from {$wpdb->prefix}tbds_posts" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
									$remain   = $count_tbds_posts - $migrated;
									if ( $remain ) {
										printf( "<div class='vi-ui message red '><b>%s %s</b></div>",
											esc_html( $remain ),
											esc_html__( 'items remaining in the migration process. New Taobao products cannot be added while the process is ongoing.', 'chinads' ) );
									}
								}
							} else {
								?>
                                <button type="button" class="vi-ui button vichinads-migrate-to-new-table blue">
									<?php esc_html_e( 'Migrate & use new table', 'chinads' ); ?>
                                </button>
								<?php
							}
						}else{
							if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
								printf( "<div class='vi-ui message red '><b>%s</b></div>",
									esc_html__( 'Deleting old data in background','chinads' ) );

							} else {
								if ( $count_tbds_posts) {
									?>
                                    <button type="button" class="vi-ui button vichinads-migrate-remove-old-data red">
										<?php esc_html_e( 'Remove old data in posts table', 'chinads' ); ?>
                                    </button>
									<?php
								}
							}
						}
						?>
                    </div>
                </div>
				<?php
			}
		}
		?>
        <form method="post" class="vi-ui form">
			<?php wp_nonce_field( 'tbds-settings' ); ?>

            <div class="vi-ui top attached tabular menu">
				<?php
				foreach ( $tabs as $slug => $text ) {
					$active = $first_tab == $slug ? 'active' : '';
					printf( ' <a class="item %s" data-tab="%s">%s</a>', esc_attr( $active ), esc_attr( $slug ), esc_html( $text ) );
				}
				?>
            </div>
			<?php
			foreach ( $tabs as $slug => $text ) {
				$active = $first_tab == $slug ? 'active' : '';
				$method = $slug . '_options';

				printf( '<div class="vi-ui bottom attached %s tab segment" data-tab="%s">', esc_attr( $active ), esc_attr( $slug ) );

				if ( method_exists( $this, $method ) ) {
					$options = $this->$method();
					Settings_Helper::output_fields( $options );
				} else {
					do_action( 'tbds_settings_tab', $slug );
				}

				echo '</div>';
			}
			?>
            <p class="tbds-save-settings-container">
                <button type="submit" class="vi-ui button labeled icon primary tbds-save-settings" name="tbds_save_settings" value="1">
                    <i class="save icon"> </i>
					<?php esc_html_e( 'Save Settings', 'chinads' ); ?>
                </button>
            </p>
        </form>
		<?php
		do_action( 'villatheme_support_chinads-taobao-dropshipping-for-woocommerce' );

	}

	public function define_tabs() {
		return [
			'general'  => esc_html__( 'General', 'chinads' ),
			'product'  => esc_html__( 'Product', 'chinads' ),
			'price'    => esc_html__( 'Product price', 'chinads' ),
			'video' => esc_html__( 'Product Video', 'chinads' ),
			'product_update' => esc_html__( 'Product Sync', 'chinads' ),
			'product_split' => esc_html__( 'Product splitting', 'chinads' ),
			'override' => esc_html__( 'Product overriding', 'chinads' ),
			'fulfill'  => esc_html__( 'Fulfill', 'chinads' ),
		];
	}

	public function general_options() {
		return [
			[ 'type' => 'section_start' ],

			[
				'id'    => 'enable',
				'title' => esc_html__( 'Enable', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'You need to enable this to let ChinaDS - Taobao Dropshipping for WooCommerce Extension connect to your store', 'chinads' ),
			],
			[
				'id' => 'video_guide',
			],

			[ 'type' => 'section_end' ],

		];
	}

	public function product_options() {
		$categories  = Data::instance()->get_param( 'product_categories' );
		$cat_options = [];
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			foreach ( $categories as $category_id ) {
				$category = get_term( $category_id );
				if ( $category ) {
					$cat_options[ $category_id ] = $category->name;
				}
			}
		}

		$product_tags = (array) Data::instance()->get_param( 'product_tags' );
		$product_tags = array_combine( $product_tags, $product_tags );

		$options = [
			[ 'type' => 'section_start' ],
			[
				'id'      => 'trans_code',
				'title'   => esc_html__( 'Languague for translate', 'chinads' ),
				'type'    => 'select',
				'options' => [
					'af'    => 'Afrikaans',
					'sq'    => 'Albanian',
					'am'    => 'Amharic',
					'ar'    => 'Arabic',
					'hy'    => 'Armenian',
					'az'    => 'Azerbaijani',
					'eu'    => 'Basque',
					'be'    => 'Belarusian',
					'bn'    => 'Bengali',
					'bs'    => 'Bosnian',
					'bg'    => 'Bulgarian',
					'ca'    => 'Catalan',
					'ceb'   => 'Cebuano',
					'zh-CN' => 'Chinese (Simplified)',
					'zh-TW' => 'Chinese (Traditional)',
					'co'    => 'Corsican',
					'hr'    => 'Croatian',
					'cs'    => 'Czech',
					'da'    => 'Danish',
					'nl'    => 'Dutch',
					'en'    => 'English',
					'eo'    => 'Esperanto',
					'et'    => 'Estonian',
					'fi'    => 'Finnish',
					'fr'    => 'French',
					'fy'    => 'Frisian',
					'gl'    => 'Galician',
					'ka'    => 'Georgian',
					'de'    => 'German',
					'el'    => 'Greek',
					'gu'    => 'Gujarati',
					'ht'    => 'Haitian Creole',
					'ha'    => 'Hausa',
					'haw'   => 'Hawaiian',
					'he'    => 'Hebrew',
					'hi'    => 'Hindi',
					'hmn'   => 'Hmong',
					'hu'    => 'Hungarian',
					'is'    => 'Icelandic',
					'ig'    => 'Igbo',
					'id'    => 'Indonesian',
					'ga'    => 'Irish',
					'it'    => 'Italian',
					'ja'    => 'Japanese',
					'jv'    => 'Javanese',
					'kn'    => 'Kannada',
					'kk'    => 'Kazakh',
					'km'    => 'Khmer',
					'rw'    => 'Kinyarwanda',
					'ko'    => 'Korean',
					'ku'    => 'Kurdish',
					'ky'    => 'Kyrgyz',
					'lo'    => 'Lao',
					'lv'    => 'Latvian',
					'lt'    => 'Lithuanian',
					'lb'    => 'Luxembourgish',
					'mk'    => 'Macedonian',
					'mg'    => 'Malagasy',
					'ms'    => 'Malay',
					'ml'    => 'Malayalam',
					'mt'    => 'Maltese',
					'mi'    => 'Maori',
					'mr'    => 'Marathi',
					'mn'    => 'Mongolian',
					'my'    => 'Myanmar (Burmese)',
					'ne'    => 'Nepali',
					'no'    => 'Norwegian',
					'ny'    => 'Nyanja (Chichewa)',
					'or'    => 'Odia (Oriya)',
					'ps'    => 'Pashto',
					'fa'    => 'Persian',
					'pl'    => 'Polish',
					'pt'    => 'Portuguese (Portugal, Brazil)',
					'pa'    => 'Punjabi',
					'ro'    => 'Romanian',
					'ru'    => 'Russian',
					'sm'    => 'Samoan',
					'gd'    => 'Scots Gaelic',
					'sr'    => 'Serbian',
					'st'    => 'Sesotho',
					'sn'    => 'Shona',
					'sd'    => 'Sindhi',
					'si'    => 'Sinhala (Sinhalese)',
					'sk'    => 'Slovak',
					'sl'    => 'Slovenian',
					'so'    => 'Somali',
					'es'    => 'Spanish',
					'su'    => 'Sundanese',
					'sw'    => 'Swahili',
					'sv'    => 'Swedish',
					'tl'    => 'Tagalog (Filipino)',
					'tg'    => 'Tajik',
					'ta'    => 'Tamil',
					'tt'    => 'Tatar',
					'te'    => 'Telugu',
					'th'    => 'Thai',
					'tr'    => 'Turkish',
					'tk'    => 'Turkmen',
					'uk'    => 'Ukrainian',
					'ur'    => 'Urdu',
					'ug'    => 'Uyghur',
					'uz'    => 'Uzbek',
					'vi'    => 'Vietnamese',
					'cy'    => 'Welsh',
					'xh'    => 'Xhosa',
					'yi'    => 'Yiddish',
					'yo'    => 'Yoruba',
					'zu'    => 'Zulu'
				],
				'class'   => $this->dropdown_class,
			],
			[
				'id'      => 'product_status',
				'title'   => esc_html__( 'Product status', 'chinads' ),
				'type'    => 'select',
				'options' => Utils::get_product_status_options(),
				'class'   => $this->dropdown_class,
				'desc'    => esc_html__( 'Imported products status will be set to this value.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Product SKU', 'chinads' ),
				'type'    => 'pro_version'
			],
			[
				'title'   => esc_html__( 'Auto generate unique sku if exists', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'When importing product in Import list, automatically generate unique sku by adding increment if sku exists.', 'chinads' )
			],
			[
				'id'    => 'use_global_attributes',
				'title' => esc_html__( 'Use global attributes', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => sprintf( "%s <a href='https://docs.woocommerce.com/document/managing-product-taxonomies/#section-6'>%s</a>", esc_html__( 'Global attributes will be used instead of custom attributes. More detail about', 'chinads' ), esc_html__( 'Product attributes', 'chinads' ) )
			],
			[
				'id'    => 'simple_if_one_variation',
				'title' => esc_html__( 'Import as simple product', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'If a product has only 1 variation or you select only 1 variation to import, that product will be imported as simple product. Variation sku and attributes will not be used.', 'chinads' )
			],
			[
				'id'      => 'catalog_visibility',
				'title'   => esc_html__( 'Catalog visibility', 'chinads' ),
				'type'    => 'select',
				'options' => Utils::get_catalog_visibility_options(),
				'class'   => $this->dropdown_class,
				'desc'    => esc_html__( 'This setting determines which shop pages products will be listed on.', 'chinads' )
			],
			[
				'id'      => 'product_description',
				'title'   => esc_html__( 'Product description', 'chinads' ),
				'type'    => 'select',
				'options' => [
					'none'                           => esc_html__( 'None', 'chinads' ),
					'item_specifics'                 => esc_html__( 'Item specifics', 'chinads' ),
					'description'                    => esc_html__( 'Product Description', 'chinads' ),
					'item_specifics_and_description' => esc_html__( 'Item specifics &amp; Product Description', 'chinads' ),
				],
				'class'   => $this->dropdown_class,
				'desc'    => esc_html__( 'Default product description when adding product to import list', 'chinads' )
			],
			[
				'id'    => 'download_description_images',
				'title' => esc_html__( 'Import description images', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Upload images in product description if any. If disabled, images in description will use the original Taobao cdn links', 'chinads' )
			],
			[
				'id'    => 'product_gallery',
				'title' => esc_html__( 'Default select product images', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'First image will be selected as product image and other images(except images from product description) are selected in gallery when adding product to import list', 'chinads' )
			],
			[
				'id'    => 'disable_background_process',
				'title' => esc_html__( 'Disable background process', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'When importing products, instead of letting their images import in the background, main product image will be uploaded immediately while gallery and variation images(if any) will be added to Failed images page so that you can go there to import them manually.', 'chinads' )
			],
			[
				'id'      => 'product_categories',
				'title'   => esc_html__( 'Default categories', 'chinads' ),
				'type'    => 'multiselect',
				'options' => $cat_options,
				'class'   => 'search-category',
				'desc'    => esc_html__( 'Imported products will be added to these categories.', 'chinads' )
			],
			[
				'id'      => 'product_tags',
				'title'   => esc_html__( 'Default product tags', 'chinads' ),
				'type'    => 'multiselect',
				'options' => $product_tags,
				'class'   => 'search-tags',
				'desc'    => esc_html__( 'Imported products will be added these tags.', 'chinads' )
			],
			[
				'id'      => 'product_shipping_class',
				'title'   => esc_html__( 'Default shipping class', 'chinads' ),
				'type'    => 'select',
				'options' => Utils::get_shipping_class_options(),
				'class'   => $this->dropdown_class,
				'desc'    => esc_html__( 'Shipping class selected here will also be selected by default in the Import list', 'chinads' )
			],
			[
				'id'    => 'variation_visible',
				'title' => esc_html__( 'Product variations is visible on product page', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Enable to make variations of imported products visible on product page', 'chinads' )
			],
			[
				'id'    => 'manage_stock',
				'title' => esc_html__( 'Manage stock', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( "Enable manage stock and import product inventory. If this option is disabled, products stock status will be set \"Instock\" and product inventory will not be imported", 'chinads' )
			],
			[ 'type' => 'section_end' ],

		];

		if ( class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
			array_splice( $options, 6, 0, [
				[
					'id'    => 'use_external_image',
					'title' => esc_html__( 'Use external links for images', 'chinads' ),
					'type'  => 'checkbox',
					'desc'  => esc_html__( 'This helps save storage by using original Taobao image URLs but you will not be able to edit them', 'chinads' )
				]
			] );
		}

		return $options;

	}

	public function price_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'title'   => esc_html__( 'Exchange rate API', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Get exchange rate from this selected API', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Update rate automatically', 'chinads' ),
				'type'    => 'pro_version',
			],
			[
				'id'    => 'import_currency_rate',
				'title' => esc_html__( 'Exchange rate', 'chinads' ),
				'type'  => 'number',
				'step'  => 'any',
				'desc'  => esc_html__( 'This is exchange rate to convert product price from CNY to your store currency when adding products to import list.', 'chinads' )
			],
			[ 'type' => 'section_end' ],
			[
				'type' => 'do_action',
				'id'   => 'price_rule'
			],
		];
	}
	public function video_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'title'   => esc_html__( 'Import product video', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Product video will be imported as an external link', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Show product video tab', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Display product video on a separate tab in the frontend', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Video tab priority', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'You can adjust this value to change order of video tab', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Make video full tab width', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'By default, product videos are displayed in their original width. Enable this option to make product videos have the same width as the tab.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Add video to description', 'chinads' ),
				'type'    => 'pro_version',
			],
			[ 'type' => 'section_end' ]
		];
	}
	public function product_split_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'title'   => esc_html__( 'Automatically remove attribute', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'When splitting a product by a specific attribute, remove that attribute of split products', 'chinads' )
			],
			[ 'type' => 'section_end' ]
		];
	}
	public function fulfill_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'title'   => esc_html__( 'Billing district meta field', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'If you customize checkout fields to add the billing district field, please enter the order meta field which is used to store the billing district here.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Shipping district meta field', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'If you customize checkout fields to add the shipping district field, please enter the order meta field which is used to store the shipping district here.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Billing area meta field', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'If you customize checkout fields to add the billing area field, please enter the order meta field which is used to store the billing area here.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Shipping area meta field', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'If you customize checkout fields to add the shipping area field, please enter the order meta field which is used to store the shipping area here.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Taobao order note', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Add this note to Taobao order when fulfilling', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Show action', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Only show action buttons for orders with status among these', 'chinads' )
			],
			[ 'type' => 'section_end' ]
		];
	}
	public function product_update_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'title'   => esc_html__( 'Product status', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Only sync products with selected statuses. Leave empty to select all statuses.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Sync attribute', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Sync attributes of products by applying attribute mapping rules', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Sync quantity', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Sync quantity of WooCommerce products with Taobao if products managed stock', 'chinads' )
			],
			[
				'title'   => esc_html__( 'Sync price', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Sync price of WooCommerce products with Taobao. All rules in Product Price tab will be applied to new price.', 'chinads' )
			],
			[
				'title'   => esc_html__( 'If a product is out of stock', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Select an action when an Taobao product is out-of-stock', 'chinads' )
			],
			[
				'title'   => esc_html__( 'If a product is no longer available', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Select an action when an Taobao product is no longer available', 'chinads' )
			],
			[
				'title'   => esc_html__( 'If a variation is no longer available', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Select an action when a variation of an Taobao product is no longer available', 'chinads' )
			],
			[ 'type' => 'section_end' ]
		];
	}

	public function price_rule() {
		include_once TBDS_CONST['admin_views'] . 'html-price-rule-setting.php';
	}

	public function video_guide() {
		?>
        <a href="https://downloads.villatheme.com/?download=chinads-extension" target="_blank">
			<?php printf( "%s %s %s",
				esc_html__( 'Add', 'chinads' ),
				esc_html( TBDS_CONST['plugin_name'] ),
				esc_html__( 'Extension', 'chinads' ) ); ?>
        </a>
        <h4><?php esc_html_e( 'Video guide', 'chinads' ); ?></h4>

        <h5><?php esc_html_e( 'Register account', 'chinads' ); ?></h5>
        <iframe width="560" height="315" allowfullscreen src="https://www.youtube-nocookie.com/embed/kO9KxF4DNXE" frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>

        <h5><?php esc_html_e( 'Install and use', 'chinads' ); ?></h5>
        <iframe width="560" height="315" allowfullscreen src="https://www.youtube-nocookie.com/embed/weZk4kWoPhw" frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
		<?php
	}

	public function override_options() {
		return [
			[ 'type' => 'section_start' ],
			[
				'id'    => 'override_keep_product',
				'title' => esc_html__( 'Keep Woo product', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Instead of deleting old product to create a new one, it will update the overridden old product\'s prices/stock/attributes/variations based on the new data. This way, data such as reviews, metadata... will not be lost.', 'chinads' ),
			],
			[
				'title'   => esc_html__( 'Override SKU', 'chinads' ),
				'type'    => 'pro_version',
				'desc'    => esc_html__( 'Replace SKU of overridden product with new product\'s SKU', 'chinads' )
			],
			[
				'id'    => 'override_title',
				'title' => esc_html__( 'Override title', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Replace title of overridden product with new product\'s title', 'chinads' ),
			],
			[
				'id'    => 'override_images',
				'title' => esc_html__( 'Override images', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Replace images and gallery of overridden product with new product\'s images and gallery', 'chinads' ),
			],
			[
				'id'    => 'override_description',
				'title' => esc_html__( 'Override description', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Replace description and short description of overridden product with new product\'s description and short description', 'chinads' ),
			],
			[
				'id'    => 'override_hide',
				'title' => esc_html__( 'Hide options', 'chinads' ),
				'type'  => 'checkbox',
				'desc'  => esc_html__( 'Do not show these options when overriding product', 'chinads' ),
			],
			[ 'type' => 'section_end' ],
		];
	}

	public function save_settings() {
		if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['tbds_save_settings'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'tbds-settings' ) && current_user_can( 'manage_options' ) ) {

			$tabs    = $this->define_tabs();
			$options = [];
			foreach ( $tabs as $slug => $text ) {
				$method = $slug . '_options';
				if ( method_exists( $this, $method ) ) {
					$options = array_merge( $options, $this->$method() );
				} else {
					$options = array_merge( $options, apply_filters( 'tbds_save_setting_option', [], $slug ) );
				}
			}

			$options = apply_filters( 'tbds_merge_external_options_before_save', $options );

			Settings_Helper::save_fields( $options );

			if ( 'tbds_wizard_submit' === $_POST['tbds_save_settings'] ) {

				$default = Data::instance()->default_options();
				$params  = get_option( 'tbds_params' );
				$parse   = [
					'import_currency_rate' => $params['import_currency_rate'],
					'price_from'           => $params['price_from'],
					'price_to'             => $params['price_to'],
					'plus_value_type'      => $params['plus_value_type'],
					'plus_sale_value'      => $params['plus_sale_value'],
					'plus_value'           => $params['plus_value'],
					'price_default'        => $params['price_default'],
				];

				$params = wp_parse_args( $parse, $default );
				update_option( 'tbds_params', $params );
			}

			if ( isset( $_POST['tbds_setup_redirect'] ) ) {
				$url_redirect = esc_url_raw( wp_unslash( $_POST['tbds_setup_redirect'] ) );
				wp_safe_redirect( $url_redirect );
				exit;
			}
		}
	}

	public function merge_external_options_before_save( $options ) {
		$options[] = [ 'id' => 'price_from' ];
		$options[] = [ 'id' => 'price_to' ];
		$options[] = [ 'id' => 'plus_value_type' ];
		$options[] = [ 'id' => 'plus_sale_value' ];
		$options[] = [ 'id' => 'plus_value' ];
		$options[] = [ 'id' => 'price_default' ];

		return $options;
	}

	public function search_cate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$keyword    = filter_input( INPUT_GET, 'keyword', FILTER_SANITIZE_STRING );
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $keyword,
				'hide_empty' => false
			)
		);

		$items = array();

		if ( count( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->term_id,
					'text' => $category->name
				);
				$items[] = $item;
			}
		}

		wp_send_json( $items );
	}

	public function search_tags() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$keyword    = filter_input( INPUT_GET, 'keyword', FILTER_SANITIZE_STRING );
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $keyword,
				'hide_empty' => false
			)
		);

		$items[] = array( 'id' => $keyword, 'text' => $keyword );

		if ( count( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->name,
					'text' => $category->name
				);
				$items[] = $item;
			}
		}

		wp_send_json( $items );
	}
}

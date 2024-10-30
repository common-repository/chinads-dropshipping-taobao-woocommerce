<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Taobao_Post;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

class Api {
	protected static $instance = null;
	protected $namespace = 'chinads';
	protected $settings;

	public function __construct() {
		$this->settings = Data::instance();
		add_action( 'rest_api_init', array( $this, 'register_api' ) );
		add_filter( 'woocommerce_rest_is_request_to_rest_api', [ $this, 'woocommerce_rest_is_request_to_rest_api' ] );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function validate( \WP_REST_Request $request ) {
		$result = array(
			'status'       => 'error',
			'message'      => '',
			'message_type' => 1,
		);

		/*check ssl*/
		if ( ! is_ssl() ) {
			$result['message']      = esc_html__( 'SSL is required', 'chinads' );
			$result['message_type'] = 2;

			wp_send_json( $result );
		}

		/*check enable*/
		if ( ! $this->settings->get_param( 'enable' ) ) {
			$result['message']      = TBDS_CONST['plugin_name'] . ' ' . esc_html__( 'plugin is currently disabled. Please enable it to use this function.', 'chinads' );
			$result['message_type'] = 2;

			wp_send_json( $result );
		}
		$migrate_process = Settings::migrate_process();
		if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
			$result['message']      = esc_html__( 'Migrate processing', 'chinads' );
			$result['message_type'] = 2;
			wp_send_json( $result );
		}
	}

	public function woocommerce_rest_is_request_to_rest_api( $is_request_to_rest_api ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		if ( false !== strpos( $request_uri, $rest_prefix . 'chinads/' ) ) {
			$is_request_to_rest_api = true;
		}

		return $is_request_to_rest_api;
	}

	public function register_api() {
		register_rest_route( $this->namespace, '/auth', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'auth' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace, '/auth/revoke_api_key', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'revoke_api_key' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( $this->namespace, '/auth/sync', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_auth' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}


	public function auth( \WP_REST_Request $request ) {
		$consumer_key    = sanitize_text_field( $request->get_param( 'consumer_key' ) );
		$consumer_secret = sanitize_text_field( $request->get_param( 'consumer_secret' ) );
		if ( $consumer_key && $consumer_secret ) {
			$user = $this->get_user_data_by_consumer_key( $consumer_key );
			if ( $user && hash_equals( $user->consumer_secret, $consumer_secret ) ) {
				update_option( 'tbds_temp_api_credentials', $request->get_params() );
			}
		}
	}

	private function get_user_data_by_consumer_key( $consumer_key ) {
		global $wpdb;
		$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
		$query        = "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s";

		return $wpdb->get_row( $wpdb->prepare( $query, $consumer_key ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
	}


	public function revoke_api_key( \WP_REST_Request $request ) {
		$this->validate( $request );
		$consumer_key    = sanitize_text_field( $request->get_param( 'consumer_key' ) );
		$consumer_secret = sanitize_text_field( $request->get_param( 'consumer_secret' ) );

		if ( ! $consumer_key ) {
			$authorization = $request->get_header( 'authorization' );
			if ( $authorization ) {
				$authorization = base64_decode( substr( $authorization, 6 ) );
				$consumer      = explode( ':', $authorization );
				if ( count( $consumer ) === 2 ) {
					$consumer_key    = $consumer[0];
					$consumer_secret = $consumer[1];
				}
			}
		}

		if ( ! $consumer_key && ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			$consumer_key = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
		}

		if ( ! $consumer_secret && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			$consumer_secret = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		wp_send_json(
			array(
				'status' => 'success',
				'result' => $this->revoke_woocommerce_api_key( $consumer_key, $consumer_secret ),
			)
		);
	}

	public function revoke_woocommerce_api_key( $consumer_key, $consumer_secret ) {
		global $wpdb;
		$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
		$query        = "DELETE FROM {$wpdb->prefix}woocommerce_api_keys	WHERE consumer_key = %s AND consumer_secret=%s";

		return $wpdb->query( $wpdb->prepare( $query, $consumer_key, $consumer_secret ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , 	WordPress.DB.PreparedSQL.NotPrepared
	}

	public function sync_auth( \WP_REST_Request $request ) {
		$this->validate( $request );
		$this->import_to_list( $request );
	}


	public function parse_product_data( $data, $from = '' ) {
		if (!empty($data['tbds_NEW_DETAIL_ENV'])){
			$variations = $new_variation_images = [];
			$product_data = $data['data'] ?? $data ;
			$store_info =[
				'shop_id' => $product_data['seller']['shopId'] ?? '',
				'shop_url' => $product_data['seller']['pcShopUrl'] ?? '',
				'name'    => $product_data['seller']['shopName'] ?? '',
			];
			$item_data = $product_data['item'] ?? [];
			$sku_map = $product_data['skuBase']['skus'] ?? [];
			$item_id =$item_data['itemId'] ?? '';
			$item_name = $item_data['title'] ??'';
			$item_desc_url = $item_data['pcADescUrl'] ??'';
			$gallery = $this->prepare_gallery( $item_data['images'] ??[] );
			$pc_buy_params = $product_data['pcTrade']['pcBuyParams'] ?? [];
			$src_attributes       = $product_data['skuBase']['props'] ?? [];
			$sku2info = $product_data['skuCore']['sku2info'] ?? [];
			if ( ! empty( $sku_map ) && !empty($sku2info)&& !empty($src_attributes)) {
				$variation_images     = $variation_props = [];
				foreach ($src_attributes as $i => $prop){
					if (empty($prop['pid']) || empty($prop['values']) || !is_array($prop['values'])){
						continue;
					}
					$slug      = wc_sanitize_taxonomy_name( $prop['name'] ?? '' );
					$src_attributes[ $i ]['slug'] = $slug;
					$src_attributes[ $i ]['default_values'] = $prop['values'];
					$variation_props[$prop['pid']] = ['slug' => $slug];
					$has_img = boolval($prop['hasImage']?? 'false');
					if ($has_img){
						$variation_images[$prop['pid']] = [];
					}
					$prop_values=[];
					foreach ($prop['values'] as $item){
						$prop_values[$prop['pid'].':'.$item['vid']] = $item['name'];
						$variation_props[$prop['pid']][$item['vid']] = $item;
						if (isset($variation_images[$prop['pid']])) {
							$variation_images[ $prop['pid'] ][ $item['vid'] ] = $item['image'];
						}
					}
					$src_attributes[$i]['values'] = $prop_values;
				}
				foreach ( $sku_map as $item ) {
					if (!isset($item['skuId'], $item['propPath'])){
						continue;
					}
					if (!isset($sku2info[$item['skuId']]) || !is_array($sku2info[$item['skuId']])){
						continue;
					}
					$item += $sku2info[$item['skuId']];
					$sku_id                     = $item['skuId'] ?? '';
					$variation['sku']           = $sku_id;
					$variation['skuId']         = $sku_id;
					$variation['regular_price'] = $item['price']['priceText'] ?? '';
					$variation['stock']         = $item['quantity'] ?? '';
					$variation['sale_price']    = '';
					if ( ! empty( $item['subPrice'] ) ) {
						$variation['sale_price'] = $item['subPrice']['priceText'] ?? '';
					}
					$props = array_filter( explode( ';', $item['propPath'] ) );
					if ( ! empty( $props ) ) {
						foreach ( $props as $prop ) {
							$keys = explode( ':', $prop );
							$prop_id = $keys[0] ?? '';
							$vid = $keys[1] ?? '';
							if (!$prop_id || !$vid ){
								continue;
							}
							if (isset($variation_images[ $prop_id ][ $vid ])) {
								$image                  = $variation_images[ $prop_id ][ $vid ];
								$image                  = 'https' !== substr( $image, 0, 5 ) ? set_url_scheme( $image, 'https' ) : $image;
								$variation['image']     = $image;
								$new_variation_images[] = $image;
							}
							if (isset($variation_props[$prop_id]['slug']) && isset($variation_props[$prop_id][$vid]['name'])){
								$variation['attributes'][ $variation_props[$prop_id]['slug'] ] = $variation_props[$prop_id][$vid]['name'];
							}
						}
					}
					$variation['shipping_cost'] = 0;

					$variations[] = $variation;
				}
			} else {
				$variation['skuId']         = $item_id;
				$variation['regular_price'] = $pc_buy_params['buy_now'] ?? $sku2info[0]['priceText'] ?? '';
				$variation['sale_price']    = $variation['regular_price'] ;
				$variation['stock']         = $sku2info[0]['quantity'];
				$variation['shipping_cost'] = 0;

				$variations[] = $variation;
			}
			$result = [
				'sku'               => $item_id,
				'name'              => $item_name,
				'description_url'   => $item_desc_url,
				'description'       => $data['tbdsDescHtml'] ?? '',
				'short_description' => $data['short_description'] ?? '',
				'item_specifics'    => $data['tbdsItemSpecifics'] ?? '',
				'store_info'        => $store_info,
				'gallery'           => $gallery,
				'list_attributes'   => [],
				'attributes'        => $src_attributes,
				'variations'        => $variations,
				'variation_images'  => $new_variation_images,
				'sku_map'           => $sku_map,
			];
		}else{
			$call = "parse_product_data_from_{$from}";
			if ( method_exists( $this, $call ) ) {
				$result = $this->$call($data);
			}
		}
		return $result ?? [];
	}
	public function parse_product_data_from_taobao( $data ) {
		$variations           = [];
		$gallery              = [];
		$new_variation_images = [];
		$sku_map              = $data['sku']['valItemInfo']['skuMap'] ?? [];
		$promotion            = $data['promotion']['promoData'] ?? [];
		$variation_images     = $data['tbds_variations_image'] ?? [];
		$src_attributes       = $data['tbds_attributes'] ?? [];

		if ( ! empty( $sku_map ) ) {
			$stocks     = $data['dynStock']['sku'] ?? [];
			$deliveries = $data['deliveryFee']['data']['serviceInfo']['sku'] ?? [];

			foreach ( $sku_map as $key => $item ) {
				$sku_id                     = $item['skuId'] ?? '';
				$variation['sku']           = $sku_id;
				$variation['skuId']         = $sku_id;
				$variation['regular_price'] = $item['price'] ?? '';
				$variation['sale_price']    = '';

				$keys = array_filter( explode( ';', $key ) );

				if ( ! empty( $variation_images ) ) {
					foreach ( $variation_images as $img_data ) {
						$id = $img_data['id'] ?? '';
						if ( in_array( $id, $keys ) && ! empty( $img_data['img'] ) ) {
							$image                  = $img_data['img'];
							$image                  = 'https' !== substr( $image, 0, 5 ) ? set_url_scheme( $image, 'https' ) : $image;
							$variation['image']     = $image;
							$new_variation_images[] = $image;
						}
					}
				}

				if ( ! empty( $src_attributes ) ) {
					foreach ( $src_attributes as $i => $attr ) {
						if ( ! empty( $attr['values'] ) ) {
							$values    = $attr['values'];
							$slug      = wc_sanitize_taxonomy_name( $attr['name'] ?? '' );
							$intersect = array_intersect( $keys, array_keys( $values ) );

							$src_attributes[ $i ]['slug'] = $slug;

							if ( empty( $intersect ) ) {
								continue;
							}

							$id   = current( $intersect );
							$term = $values[ $id ];

							$variation['attributes'][ $slug ] = $term;
						}
					}
				}

				if ( ! empty( $stocks ) ) {
					$variation['stock'] = $stocks[ $key ]['stock'] ?? '';
				}

				if ( ! empty( $promotion ) ) {
					$variation['sale_price'] = $promotion[ $key ][0]['price'] ?? '';
				}

				$shipping_cost = 0;
				if ( ! empty( $deliveries[ $sku_id ][0]['info'] ) ) {
					$info = $deliveries[ $sku_id ][0]['info'] ?? '';
					preg_match( '/\d+\.?\d+/im', $info, $match );
					$shipping_cost = $match[0] ?? 0;
				}
				$variation['shipping_cost'] = $shipping_cost;

				$variations[] = $variation;
			}
		} else {
			$stocks     = $data['dynStock'] ?? [];
			$deliveries = $data['deliveryFee']['data']['serviceInfo']['list'] ?? [];

			$variation['skuId']         = $data['itemId'] ?? '';
			$variation['regular_price'] = $data['price'] ?? '';
			$variation['sale_price']    = $promotion['def'][0]['price'] ?? '';
			$variation['stock']         = $stocks['stock'] ?? '';

			$shipping_cost = 0;
			if ( ! empty( $deliveries[0]['info'] ) ) {
				$info = $deliveries[0]['info'] ?? '';
				preg_match( '/\d+\.?\d+/im', $info, $match );
				$shipping_cost = $match[0] ?? 0;
			}
			$variation['shipping_cost'] = $shipping_cost;

			$variations[] = $variation;
		}

		if ( ! empty( $data['idata']['item']['auctionImages'] ) ) {
			$gallery = array_map( 'sanitize_url', $data['idata']['item']['auctionImages'] );
			$gallery = $this->prepare_gallery( $gallery );
		}

		$new_data = [
			'sku'               => $data['itemId'] ?? '',
			'name'              => $data['idata']['item']['title'] ?? '',
			'description_url'   => $data['descUrl'] ?? '',
			'description'       => $data['tbdsDescHtml'] ?? '',
			'short_description' => $data['short_description'] ?? '',
			'item_specifics'    => $data['tbdsItemSpecifics'] ?? '',
			'store_info'        => [
				'shop_id' => $data['shopId'] ?? '',
				'name'    => $data['shopName'] ?? '',
			],
			'gallery'           => $gallery,
			'list_attributes'   => [],
			'attributes'        => $src_attributes,
			'variations'        => $variations,
			'variation_images'  => $new_variation_images,
			'sku_map'           => $sku_map,
		];

		return $new_data;
	}

	public function parse_product_data_from_tmall( $data ) {
		$variations           = [];
		$new_variation_images = [];
		$inventory            = $data['inventoryDO'] ?? [];
		$sku_map              = $data['valItemInfo']['skuMap'] ?? [];
		$promotion            = $data['itemPriceResultDO']['priceInfo'] ?? [];
		$variation_images     = $data['propertyPics'] ?? [];
		$src_attributes       = $data['tbds_attributes'] ?? [];

		if ( ! empty( $sku_map ) ) {
			foreach ( $sku_map as $key => $item ) {
				$sku_id                     = $item['skuId'] ?? '';
				$variation['sku']           = $sku_id;
				$variation['skuId']         = $sku_id;
				$variation['regular_price'] = $item['price'] ?? '';
				$variation['stock']         = $inventory['skuQuantity'][ $sku_id ]['quantity'] ?? $item['stock'] ?? '';
				$variation['sale_price']    = '';

				if ( ! empty( $promotion ) ) {
					$variation['sale_price'] = $promotion[ $sku_id ]['promotionList'][0]['price'] ?? '';
				}

				$keys = array_filter( explode( ';', $key ) );

				if ( ! empty( $keys ) ) {
					foreach ( $keys as $sub_key ) {
						$sub_key = ";{$sub_key};";
						if ( ! empty( $variation_images[ $sub_key ][0] ) ) {
							$image                  = $variation_images[ $sub_key ][0];
							$image                  = 'https' !== substr( $image, 0, 5 ) ? set_url_scheme( $image, 'https' ) : $image;
							$variation['image']     = $image;
							$new_variation_images[] = $image;
						}
					}
				}

				if ( ! empty( $src_attributes ) ) {
					foreach ( $src_attributes as $i => $attr ) {
						if ( ! empty( $attr['values'] ) ) {
							$values    = $attr['values'];
							$slug      = wc_sanitize_taxonomy_name( $attr['name'] ?? '' );
							$intersect = array_intersect( $keys, array_keys( $values ) );

							$src_attributes[ $i ]['slug'] = $slug;

							if ( empty( $intersect ) ) {
								continue;
							}

							$id   = current( $intersect );
							$term = $values[ $id ];

							$variation['attributes'][ $slug ] = $term;
						}
					}
				}

				$shipping_cost = 0;
				if ( ! empty( $deliveries[ $sku_id ][0]['info'] ) ) {
					$info = $deliveries[ $sku_id ][0]['info'] ?? '';
					preg_match( '/\d+\.?\d+/im', $info, $match );
					$shipping_cost = $match[0] ?? 0;
				}
				$variation['shipping_cost'] = $shipping_cost;

				$variations[] = $variation;
			}
		} else {
			$stocks     = $data['dynStock'] ?? [];
			$deliveries = $data['deliveryFee']['data']['serviceInfo']['list'] ?? [];

			$variation['skuId']         = $data['itemId'] ?? '';
			$variation['regular_price'] = $data['price'] ?? $data['detail']['defaultItemPrice'] ?? '';
			$variation['sale_price']    = $promotion['def']['price'] ?? $promotion['def'][0]['price'] ?? $promotion['def']['promotionList'][0]['price'] ?? '';
			$variation['stock']         = $inventory['icTotalQuantity'] ?? $stocks['stock'] ?? '';

			$shipping_cost = 0;
			if ( ! empty( $deliveries[0]['info'] ) ) {
				$info = $deliveries[0]['info'] ?? '';
				preg_match( '/\d+\.?\d+/im', $info, $match );
				$shipping_cost = $match[0] ?? 0;
			}

			$variation['shipping_cost'] = $shipping_cost;

			$variations[] = $variation;
		}

		if ( ! empty( $data['propertyPics']['default'] ) ) {
			$gallery = array_map( 'sanitize_url', $data['propertyPics']['default'] );
		} else {
			$gallery = array_map( 'sanitize_url', $data['tbds_gallery'] ?? [] );
		}

		$gallery = $this->prepare_gallery( $gallery );

		$new_data = [
			'sku'               => $data['itemDO']['itemId'] ?? '',
			'name'              => $data['itemDO']['title'] ?? '',
			'description_url'   => $data['api']['descUrl'] ?? '',
			'description'       => $data['tbdsDescHtml'] ?? '',
			'short_description' => $data['short_description'] ?? '',
			'store_info'        => [
				'shop_id'  => $data['shopId'] ?? '',
				'shop_url' => $data['shopUrl'] ?? '',
				'name'     => $data['shopName'] ?? '',
			],
			'gallery'           => $gallery,
			'list_attributes'   => [],
			'attributes'        => $src_attributes,
			'variations'        => $variations,
			'variation_images'  => $new_variation_images,
			'sku_map'           => $sku_map,
		];

		return $new_data;
	}

	public function prepare_gallery( $gallery ) {

		if ( ! empty( $gallery ) && is_array( $gallery ) ) {
			foreach ( $gallery as $key => $img ) {
				if ( strpos( $img, '//img.alicdn.com/imgextra/https' ) !== false ) {
					$img = substr( $img, strlen( '//img.alicdn.com/imgextra/' ) );
				} elseif ( 'https' !== substr( $img, 0, 5 ) ) {
					$img = set_url_scheme( $img, 'https' );
				}

				$gallery[ $key ] = $img;
			}
		}

		return $gallery;
	}

	public function import_to_list( \WP_REST_Request $request ) {
		Utils::set_time_limit();

		$result = array(
			'status'       => 'error',
			'message'      => '',
			'message_type' => 1,
		);

		$data             = $request->get_param( 'product_data' );
		$from             = $request->get_param( 'from' );
		$action           = $request->get_param( 'action' );
		$draft_product_id = $request->get_param( 'draft_product_id' );
		$taobao_host      = $request->get_param( 'host' );

		$product_data = [];
		if ( in_array($from ,[ 'taobao', 'tmall' ])) {
			$product_data = $this->parse_product_data( $data, $from );
		} else {
			$result['message'] = esc_html__( 'No product data was sent', 'chinads' );
			wp_send_json( $result );
		}
		if (empty($product_data) || empty($product_data['sku'])){
			$result['message'] = esc_html__( 'No product data was sent', 'chinads' );
			wp_send_json( $result );
		}

		$product_data['host'] = $taobao_host;
		$sku                  = isset( $product_data['sku'] ) ? sanitize_text_field( $product_data['sku'] ) : '';
		$post_id = Taobao_Post::get_post_id_by_taobao_id($sku);
		if ( ! $post_id ) {
			$parent = '';
			$status = 'draft';

			if ( $action == 'override' ) {
				$parent = $draft_product_id;
				$status = 'override';
			}

			$post_id = $this->create_import_product( $product_data, [ 'post_parent' => $parent, 'post_status' => $status ] );

			if ( is_wp_error( $post_id ) ) {
				$result['message'] = $post_id->get_error_message();
				wp_send_json( $result );
			} elseif ( ! $post_id ) {
				$result['message'] = esc_html__( 'Can not create post', 'chinads' );
				wp_send_json( $result );
			}

			$result['status']  = 'success';
			$result['message'] = esc_html__( 'Product is added to import list', 'chinads' );
		} else {
			$result['message'] = esc_html__( 'Product exists', 'chinads' );
		}

		wp_send_json( $result );
	}

	public function create_import_product( $data, $post_data = [] ) {
		$host              = isset( $data['host'] ) ? esc_url_raw( $data['host'] ) : '';
		$sku               = isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '';
		$title             = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$description_url   = isset( $data['description_url'] ) ? stripslashes( $data['description_url'] ) : '';
		$description_url   = str_replace( 'var=desc', '', urldecode( $description_url ) );
		$description_url   = 'https' !== substr( $description_url, 0, 5 ) ? set_url_scheme( $description_url, 'https' ) : $description_url;
		$description       = isset( $data['description'] ) ? sanitize_text_field( stripslashes( $data['description'] ) ) : '';
		$short_description = isset( $data['short_description'] ) ? sanitize_text_field( stripslashes( $data['short_description'] ) ) : '';
		$gallery           = isset( $data['gallery'] ) ? stripslashes_deep( $data['gallery'] ) : array();
		$variations        = isset( $data['variations'] ) ? stripslashes_deep( $data['variations'] ) : array();
		$attributes        = isset( $data['attributes'] ) ? stripslashes_deep( $data['attributes'] ) : array();
		$store_info        = isset( $data['store_info'] ) ? stripslashes_deep( $data['store_info'] ) : array();
		$variation_images  = isset( $data['variation_images'] ) ? stripslashes_deep( $data['variation_images'] ) : array();
		$sku_map           = isset( $data['sku_map'] ) ? wc_clean( $data['sku_map'] ) : [];

		$str_replace         = (array) $this->settings->get_param( 'string_replace' );
		$description_setting = $this->settings->get_param( 'product_description' );

		$desc_images = [];

		$short_description = base64_decode( $short_description );
		if ( $description === 'tbds_reset' ) {
			$description = '';
			$request = wp_safe_remote_get( $description_url);
			if ( ! is_wp_error( $request ) && !empty($request['body']) ) {
				$description     =  preg_replace( '/<script\>[\s\S]*?<\/script>/im', '', $request['body'] ) ;
				$description     =  preg_replace( '/<div class="hlg_rand.*?<\/div>/i', '', $description ) ;
			}
		} else {
			$description = base64_decode( $description );// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}
		switch ( $description_setting ) {
			case 'none':
				$description = '';
				break;

			case 'item_specifics':
				$description = $short_description;
				break;

			case 'description':
				break;

			case 'item_specifics_and_description':
			default:
				$description = $short_description . $description;
		}

		$description = apply_filters( 'tbds_import_product_description', $description, $data );

		if ( $description ) {
			preg_match_all( '/src="([\s\S]*?)"/im', $description, $matches );
			if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
				$desc_images = array_values( array_unique( $matches[1] ) );
			}
		}

		if ( isset( $str_replace['to_string'] ) && is_array( $str_replace['to_string'] ) && $str_replace_count = count( $str_replace['to_string'] ) ) {
			for ( $i = 0; $i < $str_replace_count; $i ++ ) {
				if ( $str_replace['sensitive'][ $i ] ) {
					$description = str_replace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $description );
					$title       = str_replace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $title );
				} else {
					$description = str_ireplace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $description );
					$title       = str_ireplace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $title );
				}
			}
		}

		$description = wp_kses_post( $description );

		$post_data = array_merge( [
			'post_title'   => $title,
			'post_type'    => 'tbds_draft_product',
			'post_status'  => 'draft',
			'post_excerpt' => '',
			'post_content' => $description,
		], $post_data );
		$delay_desc = false;
		$post_id    = Taobao_Post::insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) &&  in_array($post_id->get_error_code(),['tbds_db_insert_error','db_insert_error']) ) {
			$post_data['post_content'] = 'delay_desc';
			$post_id                   = Taobao_Post::insert_post( $post_data, true );
			$delay_desc                = true;
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			if ( ! empty( $desc_images ) ) {
				Taobao_Post::update_post_meta( $post_id, '_tbds_description_images', $desc_images );
			}

			Taobao_Post::update_post_meta( $post_id, '_tbds_sku', $sku );
			Taobao_Post::update_post_meta( $post_id, '_tbds_attributes', $attributes );
			Taobao_Post::update_post_meta( $post_id, '_tbds_variation_images', $variation_images );
			Taobao_Post::update_post_meta( $post_id, '_tbds_taobao_host', $host );
			Taobao_Post::update_post_meta( $post_id, '_tbds_sku_map', $sku_map );

			$gallery = array_unique( array_filter( $gallery ) );
			if ( ! empty( $gallery ) ) {
				Taobao_Post::update_post_meta( $post_id, '_tbds_gallery', $gallery );
			}

			if ( is_array( $store_info ) && count( $store_info ) ) {
				Taobao_Post::update_post_meta( $post_id, '_tbds_store_info', $store_info );
			}

			if ( ! empty( $variations ) && is_array( $variations ) ) {
				Taobao_Post::update_post_meta( $post_id, '_tbds_variations', $variations );
			}

			if ( $delay_desc ) {
				Taobao_Post::update_post_meta( $post_id, '_tbds_delay_desc_url', $description_url );
			}
		}

		return $post_id;
	}

	public function permissions_check() {
		if ( ! wc_rest_check_post_permissions( 'product', 'create' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_create', esc_html__( 'Unauthorized', 'chinads' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

}

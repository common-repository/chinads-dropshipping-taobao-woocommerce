<?php

namespace TaobaoDropship\Inc;

defined( 'ABSPATH' ) || exit;

class Utils {
	public static function set_time_limit() {
		ini_set( 'max_execution_time', '3000' );
		ini_set( 'max_input_time', '3000' );
		ini_set( 'default_socket_timeout', '3000' );
		@set_time_limit( 0 );
	}


	public static function set_class_name( $input, $set_name = false, $multiple = false ) {
		$prefix = 'tbds-';
		if ( is_array( $input ) ) {
			return implode( ' ', array_map( array( __CLASS__, 'set_class_name' ), $input ) );
		} else {
			$multiple = $multiple ? '[]' : '';

			return $set_name ? str_replace( '-', '_', $prefix . $input . $multiple ) : $prefix . $input;
		}
	}

	public static function get_product_tags() {
		$tags = get_terms( [ 'taxonomy' => 'product_tag', 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false ] );
		$tags = wp_list_pluck( $tags, 'name', 'name' );

		return $tags;
	}

	public static function get_product_categories() {
		$categories = get_categories( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

		return self::build_dropdown_categories_tree( $categories );
	}

	private static function build_dropdown_categories_tree( $all_cats, $parent_cat = 0, $level = 1 ) {
		$res = [];
		foreach ( $all_cats as $cat ) {
			if ( $cat->parent == $parent_cat ) {
				$prefix               = str_repeat( '&nbsp;-&nbsp;', $level - 1 );
				$res[ $cat->term_id ] = $prefix . $cat->name . " ({$cat->count})";
				$child_cats           = self::build_dropdown_categories_tree( $all_cats, $cat->term_id, $level + 1 );
				if ( $child_cats ) {
					$res += $child_cats;
				}
			}
		}

		return $res;
	}

	public static function json_decode( $json, $assoc = true, $depth = 512, $options = 2 ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$json = mb_convert_encoding( $json, 'UTF-8', 'UTF-8' );
		}

		return json_decode( $json, $assoc, $depth, $options );
	}

	public static function sku_exists( $sku = '' ) {
		$sku_exists = false;

		if ( $sku ) {
			$id_from_sku = wc_get_product_id_by_sku( $sku );
			$product     = $id_from_sku ? wc_get_product( $id_from_sku ) : false;
			$sku_exists  = $product && 'importing' !== $product->get_status();
		}

		return $sku_exists;
	}

	public static function get_attribute_name_by_slug( $slug ) {
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	public static function download_image( &$image_id, $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		global $wpdb;
		$settings = Data::instance();

		if ( $settings->get_param( 'use_external_image' ) && class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
//		    error_log(print_r($url,true));
			$external_image = \EXMAGE_WP_IMAGE_LINKS::add_image( $url, $image_id, $post_parent );
			$thumb_id       = $external_image['id'] ? $external_image['id'] : new \WP_Error( 'exmage_image_error', $external_image['message'] );
		} else {
			$new_url   = $url;
			$parse_url = wp_parse_url( $new_url );
			$scheme    = empty( $parse_url['scheme'] ) ? 'http' : $parse_url['scheme'];
			$image_id  = "{$parse_url['host']}{$parse_url['path']}";
			$new_url   = "{$scheme}://{$image_id}";

			preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG)/', $new_url, $matches );
			if ( ! is_array( $matches ) || ! count( $matches ) ) {
				preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG)/', $url, $matches );
				if ( is_array( $matches ) && count( $matches ) ) {
					$new_url  .= "?{$matches[0]}";
					$image_id .= "?{$matches[0]}";
				}
			}

			$thumb_id = self::get_id_by_image_id( $image_id );
			if ( ! $thumb_id ) {
				$thumb_id = self::upload_image( $new_url, $post_parent, $exclude, $post_title, $desc );
				if ( ! is_wp_error( $thumb_id ) ) {
					update_post_meta( $thumb_id, '_tbds_image_id', $image_id );
				}
			} elseif ( $post_parent ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} set post_parent=%s WHERE ID=%s AND post_parent = 0 LIMIT 1", [ $post_parent, $thumb_id ] ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		return $thumb_id;
	}

	/**
	 * @param $image_id
	 * @param bool $count
	 * @param bool $multiple
	 *
	 * @return array|bool|object|string|null
	 */
	public static function get_id_by_image_id( $image_id, $count = false, $multiple = false ) {
		global $wpdb;
		if ( $image_id ) {
			$post_type = 'attachment';
			$meta_key  = "_tbds_image_id";

			if ( $count ) {
				$query   = "SELECT count(*) from {$wpdb->postmeta} join {$wpdb->posts} on {$wpdb->postmeta}.post_id={$wpdb->posts}.ID where {$wpdb->posts}.post_type = '{$post_type}' and {$wpdb->posts}.post_status != 'trash' and {$wpdb->postmeta}.meta_key = '{$meta_key}' and {$wpdb->postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $image_id ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$query = "SELECT {$wpdb->postmeta}.* from {$wpdb->postmeta} join {$wpdb->posts} on {$wpdb->postmeta}.post_id={$wpdb->posts}.ID where {$wpdb->posts}.post_type = '{$post_type}' and {$wpdb->posts}.post_status != 'trash' and {$wpdb->postmeta}.meta_key = '{$meta_key}' and {$wpdb->postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $image_id ), ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $image_id ), 1 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function set_catalog_visibility( $product_id, $catalog_visibility ) {
		$terms = array();
		switch ( $catalog_visibility ) {
			case 'hidden':
				$terms[] = 'exclude-from-search';
				$terms[] = 'exclude-from-catalog';
				break;
			case 'catalog':
				$terms[] = 'exclude-from-search';
				break;
			case 'search':
				$terms[] = 'exclude-from-catalog';
				break;
		}
		if ( count( $terms ) && ! is_wp_error( wp_set_post_terms( $product_id, $terms, 'product_visibility', false ) ) ) {
			delete_transient( 'wc_featured_products' );
			do_action( 'woocommerce_product_set_visibility', $product_id, $catalog_visibility );
		}
	}

	public static function get_catalog_visibility_options() {
		return [
			'visible' => esc_html__( 'Shop and search results', 'chinads' ),
			'catalog' => esc_html__( 'Shop only', 'chinads' ),
			'search'  => esc_html__( 'Search results only', 'chinads' ),
			'hidden'  => esc_html__( 'Hidden', 'chinads' ),
		];
	}

	public static function get_product_status_options() {
		return [
			'publish' => esc_html__( 'Publish', 'chinads' ),
			'pending' => esc_html__( 'Pending', 'chinads' ),
			'draft'   => esc_html__( 'Draft', 'chinads' ),
		];
	}

	public static function get_shipping_class_options() {
		$shipping_classes = (array) get_terms( [ 'taxonomy' => 'product_shipping_class', 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false ] );
		$shipping_classes = wp_list_pluck( $shipping_classes, 'name', 'term_id' );

		return [ '' => esc_html__( 'No shipping class', 'chinads' ) ] + $shipping_classes;
	}

	public static function get_button_view_edit_html( $woo_product_id ) {
		ob_start();
		?>
        <a href="<?php echo esc_attr( get_post_permalink( $woo_product_id ) ) ?>" target="_blank" class="button" rel="nofollow">
			<?php esc_html_e( 'View product', 'chinads' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( "post.php?post={$woo_product_id}&action=edit" ) ) ?>" target="_blank" class="button button-primary" rel="nofollow">
			<?php esc_html_e( 'Edit product', 'chinads' ) ?>
        </a>
		<?php
		return ob_get_clean();
	}

	public static function upload_image( $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG)/', $url, $matches );
		if ( is_array( $matches ) && count( $matches ) ) {
			if ( ! in_array( strtolower( $matches[1] ), $exclude ) ) {

				add_filter( 'big_image_size_threshold', '__return_false' );
				//add product image:
				if ( ! function_exists( 'media_handle_upload' ) ) {
					require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
					require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
					require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
				}

				// Download file to temp location
				$tmp                    = download_url( $url );
				$file_array['name']     = apply_filters( 'tbds_image_file_name', basename( $matches[0] ), $post_parent, $post_title );
				$file_array['tmp_name'] = $tmp;

				// If error storing temporarily, unlink
				if ( is_wp_error( $tmp ) ) {
					wp_delete_file( $file_array['tmp_name'] );

					return $tmp;
				}
				$args = array();
				if ( $post_parent ) {
					$args['post_parent'] = $post_parent;
				}
				if ( $post_title ) {
					$args['post_title'] = $post_title;
				}
				//use media_handle_sideload to upload img:
				$thumbid = media_handle_sideload( $file_array, '', $desc, $args );
				// If error storing permanently, unlink
				if ( is_wp_error( $thumbid ) ) {
					wp_delete_file( $file_array['tmp_name'] );
				}

				return $thumbid;
			} else {
				return new \WP_Error( 'tbds_file_type_not_permitted', esc_html__( 'File type is not permitted', 'chinads' ) );
			}
		} else {
			return new \WP_Error( 'tbds_file_type_not_permitted', esc_html__( 'Can not detect file type', 'chinads' ) );
		}
	}


	public static function sanitize_taxonomy_name( $name ) {
		return urldecode( function_exists( 'mb_strtolower' ) ? mb_strtolower( urlencode( wc_sanitize_taxonomy_name( $name ) ) ) : strtolower( urlencode( wc_sanitize_taxonomy_name( $name ) ) ) );
	}

	public static function get_taobao_url( $woo_product_id ) {
		$host = get_post_meta( $woo_product_id, '_tbds_taobao_host', true ) ?: get_post_meta( $woo_product_id, '_tbds_taobao_product_host', true );
		$sku  = get_post_meta( $woo_product_id, '_tbds_taobao_product_id', true );

		return $host && $sku ? "{$host}/item.htm?id={$sku}" : '';
	}

	public static function get_exchange_rate( $api = 'google', $target_currency = '', $decimals = false ) {
		if ( $decimals === false ) {
			$decimals = 10;
		}

		$rate = false;

		if ( ! $target_currency ) {
			$target_currency = get_woocommerce_currency();
		}

		if ( self::strtolower( $target_currency ) === 'cny' ) {
			$rate = 1;
		} else {
			$get_rate = self::get_google_exchange_rate( $target_currency );

			if ( $get_rate['status'] === 'success' && $get_rate['data'] ) {
				$rate = $get_rate['data'];
			}
			$rate = apply_filters( 'tbds_get_exchange_rate', round( $rate, $decimals ), $api );
		}

		return $rate;
	}

	private static function get_google_exchange_rate( $target_currency, $source_currency = 'CNY' ) {
		$response = array(
			'status' => 'error',
			'data'   => false,
		);

		$url = 'https://www.google.com/async/currency_v2_update?vet=12ahUKEwjfsduxqYXfAhWYOnAKHdr6BnIQ_sIDMAB6BAgFEAE..i&ei=kgAGXN-gDJj1wAPa9ZuQBw&yv=3&async=source_amount:1,source_currency:';
		$url .= self::get_country_freebase( $source_currency );
		$url .= ',target_currency:';
		$url .= self::get_country_freebase( $target_currency );
		$url .= ',lang:en,country:us,disclaimer_url:https%3A%2F%2Fwww.google.com%2Fintl%2Fen%2Fgooglefinance%2Fdisclaimer%2F,period:5d,interval:1800,_id:knowledge-currency__currency-v2-updatable,_pms:s,_fmt:pc';

		$request = wp_remote_get( $url, [
			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
			'timeout'    => 10
		] );

		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			preg_match( '/data-exchange-rate=\"(.+?)\"/', $request['body'], $match );
			if ( is_array( $match ) && count( $match ) > 1 ) {
				$response['status'] = 'success';
				$response['data']   = $match[1];
			} else {
				$response['data'] = esc_html__( 'Preg_match fails', 'chinads' );
			}
		} else {
			$response['data'] = $request->get_error_message();
		}

		return $response;
	}

	private static function get_country_freebase( $country_code = '' ) {
		$countries = array(
			"AED" => "/m/02zl8q",
			"AFN" => "/m/019vxc",
			"ALL" => "/m/01n64b",
			"AMD" => "/m/033xr3",
			"ANG" => "/m/08njbf",
			"AOA" => "/m/03c7mb",
			"ARS" => "/m/024nzm",
			"AUD" => "/m/0kz1h",
			"AWG" => "/m/08s1k3",
			"AZN" => "/m/04bq4y",
			"BAM" => "/m/02lnq3",
			"BBD" => "/m/05hy7p",
			"BDT" => "/m/02gsv3",
			"BGN" => "/m/01nmfw",
			"BHD" => "/m/04wd20",
			"BIF" => "/m/05jc3y",
			"BMD" => "/m/04xb8t",
			"BND" => "/m/021x2r",
			"BOB" => "/m/04tkg7",
			"BRL" => "/m/03385m",
			"BSD" => "/m/01l6dm",
			"BTC" => "/m/05p0rrx",
			"BWP" => "/m/02nksv",
			"BYN" => "/m/05c9_x",
			"BZD" => "/m/02bwg4",
			"CAD" => "/m/0ptk_",
			"CDF" => "/m/04h1d6",
			"CHF" => "/m/01_h4b",
			"CLP" => "/m/0172zs",
			"CNY" => "/m/0hn4_",
			"COP" => "/m/034sw6",
			"CRC" => "/m/04wccn",
			"CUC" => "/m/049p2z",
			"CUP" => "/m/049p2z",
			"CVE" => "/m/06plyy",
			"CZK" => "/m/04rpc3",
			"DJF" => "/m/05yxn7",
			"DKK" => "/m/01j9nc",
			"DOP" => "/m/04lt7_",
			"DZD" => "/m/04wcz0",
			"EGP" => "/m/04phzg",
			"ETB" => "/m/02_mbk",
			"EUR" => "/m/02l6h",
			"FJD" => "/m/04xbp1",
			"GBP" => "/m/01nv4h",
			"GEL" => "/m/03nh77",
			"GHS" => "/m/01s733",
			"GMD" => "/m/04wctd",
			"GNF" => "/m/05yxld",
			"GTQ" => "/m/01crby",
			"GYD" => "/m/059mfk",
			"HKD" => "/m/02nb4kq",
			"HNL" => "/m/04krzv",
			"HRK" => "/m/02z8jt",
			"HTG" => "/m/04xrp0",
			"HUF" => "/m/01hfll",
			"IDR" => "/m/0203sy",
			"ILS" => "/m/01jcw8",
			"INR" => "/m/02gsvk",
			"IQD" => "/m/01kpb3",
			"IRR" => "/m/034n11",
			"ISK" => "/m/012nk9",
			"JMD" => "/m/04xc2m",
			"JOD" => "/m/028qvh",
			"JPY" => "/m/088n7",
			"KES" => "/m/05yxpb",
			"KGS" => "/m/04k5c6",
			"KHR" => "/m/03_m0v",
			"KMF" => "/m/05yxq3",
			"KRW" => "/m/01rn1k",
			"KWD" => "/m/01j2v3",
			"KYD" => "/m/04xbgl",
			"KZT" => "/m/01km4c",
			"LAK" => "/m/04k4j1",
			"LBP" => "/m/025tsrc",
			"LKR" => "/m/02gsxw",
			"LRD" => "/m/05g359",
			"LSL" => "/m/04xm1m",
			"LYD" => "/m/024xpm",
			"MAD" => "/m/06qsj1",
			"MDL" => "/m/02z6sq",
			"MGA" => "/m/04hx_7",
			"MKD" => "/m/022dkb",
			"MMK" => "/m/04r7gc",
			"MOP" => "/m/02fbly",
			"MRO" => "/m/023c2n",
			"MUR" => "/m/02scxb",
			"MVR" => "/m/02gsxf",
			"MWK" => "/m/0fr4w",
			"MXN" => "/m/012ts8",
			"MYR" => "/m/01_c9q",
			"MZN" => "/m/05yxqw",
			"NAD" => "/m/01y8jz",
			"NGN" => "/m/018cg3",
			"NIO" => "/m/02fvtk",
			"NOK" => "/m/0h5dw",
			"NPR" => "/m/02f4f4",
			"NZD" => "/m/015f1d",
			"OMR" => "/m/04_66x",
			"PAB" => "/m/0200cp",
			"PEN" => "/m/0b423v",
			"PGK" => "/m/04xblj",
			"PHP" => "/m/01h5bw",
			"PKR" => "/m/02svsf",
			"PLN" => "/m/0glfp",
			"PYG" => "/m/04w7dd",
			"QAR" => "/m/05lf7w",
			"RON" => "/m/02zsyq",
			"RSD" => "/m/02kz6b",
			"RUB" => "/m/01hy_q",
			"RWF" => "/m/05yxkm",
			"SAR" => "/m/02d1cm",
			"SBD" => "/m/05jpx1",
			"SCR" => "/m/01lvjz",
			"SDG" => "/m/08d4zw",
			"SEK" => "/m/0485n",
			"SGD" => "/m/02f32g",
			"SLL" => "/m/02vqvn",
			"SOS" => "/m/05yxgz",
			"SRD" => "/m/02dl9v",
			"SSP" => "/m/08d4zw",
			"STD" => "/m/06xywz",
			"SZL" => "/m/02pmxj",
			"THB" => "/m/0mcb5",
			"TJS" => "/m/0370bp",
			"TMT" => "/m/0425kx",
			"TND" => "/m/04z4ml",
			"TOP" => "/m/040qbv",
			"TRY" => "/m/04dq0w",
			"TTD" => "/m/04xcgz",
			"TWD" => "/m/01t0lt",
			"TZS" => "/m/04s1qh",
			"UAH" => "/m/035qkb",
			"UGX" => "/m/04b6vh",
			"USD" => "/m/09nqf",
			"UYU" => "/m/04wblx",
			"UZS" => "/m/04l7bl",
			"VEF" => "/m/021y_m",
			"VND" => "/m/03ksl6",
			"XAF" => "/m/025sw2b",
			"XCD" => "/m/02r4k",
			"XOF" => "/m/025sw2q",
			"XPF" => "/m/01qyjx",
			"YER" => "/m/05yxwz",
			"ZAR" => "/m/01rmbs",
			"ZMW" => "/m/0fr4f"
		);
		if ( $country_code ) {
			return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : '';
		} else {
			return $countries;
		}
	}

	public static function strtolower( $string ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $string ) : strtolower( $string );
	}

}
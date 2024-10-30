<?php

namespace TaobaoDropship\Admin;

defined( 'ABSPATH' ) || exit;

class Draft_Product {
	protected static $instance = null;

	public function __construct() {
		add_action( 'init', array( $this, 'register_draft_product' ) );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function register_draft_product() {
		if ( post_type_exists( 'tbds_draft_product' ) ) {
			return;
		}

		$labels = [
			'name'               => _x( 'Draft product', 'chinads' ),
			'singular_name'      => _x( 'Draft product', 'chinads' ),
			'edit_item'          => __( 'Edit', 'chinads' ),
			'view_item'          => __( 'View', 'chinads' ),
			'all_items'          => __( 'All products', 'chinads' ),
			'search_items'       => __( 'Search product', 'chinads' ),
			'not_found'          => __( 'No draft product found.', 'chinads' ),
			'not_found_in_trash' => __( 'No draft product found in Trash.', 'chinads' )
		];

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'query_var'           => true,
			'capability_type'     => 'post',
			'capabilities'        => [ 'create_posts' => false ],
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 2,
			'supports'            => [ 'title' ],
			'exclude_from_search' => true,
		);

		register_post_type( 'tbds_draft_product', $args );

		register_post_status( 'override', array(
			'label'                     => _x( 'Override', 'Order status', 'chinads' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
			/* translators: %s: number of orders */
			'label_count'               => '',
		) );
	}

}

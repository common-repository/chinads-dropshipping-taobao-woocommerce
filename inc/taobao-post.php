<?php
namespace TaobaoDropship\Inc;
use TaobaoDropship\Admin\Taobao_Products_Table;
use WP_Post;
defined( 'ABSPATH' ) || exit;

final class Taobao_Post {

	public $ID;

	public $post_author = 0;

	public $post_date = '0000-00-00 00:00:00';

	public $post_date_gmt = '0000-00-00 00:00:00';

	public $post_content = '';

	public $post_title = '';

	public $post_excerpt = '';

	public $post_status = 'publish';

	public $post_name = '';

	public $post_modified = '0000-00-00 00:00:00';

	public $post_modified_gmt = '0000-00-00 00:00:00';

	public $post_parent = 0;

	public $post_type = 'tbds_draft_product';

	public $filter;
	protected static $use_tbds_table = null;

	public function __construct( $post ) {
		foreach ( get_object_vars( $post ) as $key => $value ) {
			$this->$key = $value;
		}
	}

	public static function get_the_title( $post = 0 ) {
		if ( self::use_tbds_table() ) {
			$post       = self::get_post( $post );
			$post_title = isset( $post->post_title ) ? $post->post_title : '';
			$post_id    = isset( $post->ID ) ? $post->ID : 0;
			$title      = apply_filters( 'the_title', $post_title, $post_id );
		} else {
			$title = get_the_title( $post );
		}

		return $title;
	}

	public static function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		return self::use_tbds_table() ? Taobao_Products_Table::update_post_meta( $post_id, $meta_key, $meta_value, $prev_value ) : update_post_meta( $post_id, $meta_key, $meta_value, $prev_value );
	}

	public static function get_post_meta( $post_id, $key = '', $single = false ) {
		return self::use_tbds_table() ? Taobao_Products_Table::get_post_meta( $post_id, $key, $single ) : get_post_meta( $post_id, $key, $single );
	}

	public static function delete_post( $postid = 0, $force_delete = false ) {
		return self::use_tbds_table() ? Taobao_Products_Table::delete_post( $postid, $force_delete ) : wp_delete_post( $postid, $force_delete );
	}

	public static function trash_post( $post_id = 0 ) {
		return self::use_tbds_table() ? Taobao_Products_Table::trash_post( $post_id ) : wp_trash_post( $post_id );
	}

	public static function update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
		return self::use_tbds_table() ? Taobao_Products_Table::update_post( $postarr, $wp_error, $fire_after_hooks ) : wp_update_post( $postarr, $wp_error, $fire_after_hooks );
	}

	public static function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
		return self::use_tbds_table() ? Taobao_Products_Table::get_post( $post, $output, $filter ) : get_post( $post, $output, $filter );
	}

	public static function insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		return self::use_tbds_table() ? Taobao_Products_Table::insert_post( $postarr, $wp_error, $fire_after_hooks ) : wp_insert_post( $postarr, $wp_error, $fire_after_hooks );
	}

	public static function count_posts( $type = 'tbds_draft_product', $perm = '' ) {
		return self::use_tbds_table() ? Taobao_Products_Table::count_posts( $type, $perm ) : wp_count_posts( $type, $perm );
	}

	public static function publish_post( $post ) {
		self::use_tbds_table() ? Taobao_Products_Table::publish_post( $post ) : wp_publish_post( $post );
	}

	public static function get_post_id_by_taobao_id( $id, $status = [ 'publish', 'draft', 'override' ], $multiple = false ) {
		global $wpdb;
		$table_posts    = self::get_table_name();
		$table_postmeta = self::get_table_name( true );
		$post_id_column = self::use_tbds_table() ? 'tbds_post_id' : 'post_id';
		$post_type      = 'tbds_draft_product';
		$meta_key       = '_tbds_sku';
		$args           = array();
		$where          = array();
		if ( $status ) {
			if ( is_array( $status ) ) {
				if ( count( $status ) === 1 ) {
					$where[] = "{$table_posts}.post_status=%s";
					$args[]  = $status[0];
				} else {
					$where[] = "{$table_posts}.post_status IN (" . implode( ', ', array_fill( 0, count( $status ), '%s' ) ) . ")";
					foreach ( $status as $v ) {
						$args[] = $v;
					}
				}
			} else {
				$where[] = "{$table_posts}.post_status=%s";
				$args[]  = $status;
			}
		}
		if ( $id ) {
			$where[] = "{$table_postmeta}.meta_key = '{$meta_key}'";
			$where[] = "{$table_postmeta}.meta_value = %s";
			$args[]  = $id;
			$query   = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
			$query   .= ' AND ' . implode( ' AND ', $where );

			if ( $multiple ) {
				$results = $wpdb->get_col( $wpdb->prepare( $query, $args ), 1 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$query   .= ' LIMIT 1';
				$results = $wpdb->get_var( $wpdb->prepare( $query, $args ), 1 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
			}
		} else {
			$where[] = "{$table_postmeta}.meta_key = '{$meta_key}'";
			$query   = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
			$query   .= ' AND ' . implode( ' AND ', $where );
			$results = $wpdb->get_col( count( $args ) ? $wpdb->prepare( $query, $args ) : $query, 1 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
		}

		return $results ?? null;
	}

	public static function get_post_id_by_woo_id( $product_id, $count = false, $multiple = false, $status = [ 'publish', 'draft', 'override' ] ) {
		global $wpdb;
		if ( $product_id ) {
			$table_posts    = self::get_table_name();
			$table_postmeta = self::get_table_name( true );
			$post_id_column = self::use_tbds_table() ? 'tbds_post_id' : 'post_id';
			$post_type      = 'tbds_draft_product';
			$meta_key       = '_tbds_woo_id';
			$post_status    = '';
			if ( $status ) {
				if ( is_array( $status ) ) {
					$status_count = count( $status );
					if ( $status_count === 1 ) {
						$post_status = " AND {$table_posts}.post_status='{$status[0]}' ";
					} elseif ( $status_count > 1 ) {
						$post_status = " AND {$table_posts}.post_status IN ('" . implode( "','", $status ) . "') ";
					}
				} else {
					$post_status = " AND {$table_posts}.post_status='{$status}' ";
				}
			}

			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'{$post_status}and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $product_id ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'{$post_status}and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $product_id ), ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $product_id ), 1 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function get_overriding_product( $id ) {
		global $wpdb;
		if ( $id ) {
			$table_posts = self::get_table_name();
			$query       = "SELECT ID from {$table_posts} where {$table_posts}.post_type = 'tbds_draft_product' and {$table_posts}.post_status = 'override' and {$table_posts}.post_parent = %s LIMIT 1";

			return $wpdb->get_var( $wpdb->prepare( $query, $id ), 0 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
		} else {
			return false;
		}
	}

	public static function empty_import_list( $status = 'draft' ) {
		global $wpdb;
		$table_posts    = self::get_table_name();
		$table_postmeta = self::get_table_name( true );
		$post_id_column = self::use_tbds_table() ? 'tbds_post_id' : 'post_id';
		$daft_id        = $wpdb->get_col( "SELECT ID from {$table_posts} where {$table_posts}.post_type = 'tbds_draft_product' and {$table_posts}.post_status='{$status}'" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $daft_id ) ) {
			return;
		}
		$daft_id = implode( ',', $daft_id );
		$wpdb->query( "DELETE from {$table_posts} WHERE {$table_posts}.ID IN ({$daft_id})" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE from {$table_postmeta} WHERE {$table_postmeta}.{$post_id_column} IN ({$daft_id})" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_table_name( $is_meta = false ) {
		global $wpdb;
		if ( self::use_tbds_table() ) {
			return $is_meta ? $wpdb->tbds_postmeta : $wpdb->tbds_posts;
		} else {
			return $is_meta ? $wpdb->postmeta : $wpdb->posts;
		}
	}

	public static function use_tbds_table() {
		if ( self::$use_tbds_table !== null ) {
			return self::$use_tbds_table;
		}
		$migrated = get_option( 'tbds_migrated_to_new_table' );
		if ( $migrated ) {
			self::$use_tbds_table = true;
		} else {
			self::$use_tbds_table = Data::instance()->get_param( 'use_tbds_table' );
		}
		return self::$use_tbds_table;
	}

	public static function query( $args ) {
		return self::use_tbds_table() ? new Taobao_Post_Query( $args ) : new \WP_Query( $args );
	}

	public static function get_instance( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}
		$_post = wp_cache_get( $post_id, 'tbds_posts' );
		if ( ! $_post ) {
			$_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tbds_posts WHERE ID = %d LIMIT 1", $post_id ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( ! $_post ) {
				return false;
			}
			$_post = sanitize_post( $_post, 'raw' );
			wp_cache_add( $_post->ID, $_post, 'tbds_posts' );
		} elseif ( empty( $_post->filter ) || 'raw' !== $_post->filter ) {
			$_post = sanitize_post( $_post, 'raw' );
		}

		return new WP_Post( $_post );
	}

	public function __isset( $key ) {
		if ( in_array( $key, [ 'tags_input', 'post_category', 'page_template', 'ancestors' ] ) ) {
			return true;
		}

		return metadata_exists( 'post', $this->ID, $key );
	}

	public function __get( $key ) {
		if ( 'page_template' === $key && $this->__isset( $key ) ) {
			return get_post_meta( $this->ID, '_wp_page_template', true );
		}

		if ( 'post_category' === $key ) {
			if ( is_object_in_taxonomy( $this->post_type, 'category' ) ) {
				$terms = get_the_terms( $this, 'category' );
			}

			if ( empty( $terms ) ) {
				return array();
			}

			return wp_list_pluck( $terms, 'term_id' );
		}

		if ( 'tags_input' === $key ) {
			if ( is_object_in_taxonomy( $this->post_type, 'post_tag' ) ) {
				$terms = get_the_terms( $this, 'post_tag' );
			}

			if ( empty( $terms ) ) {
				return array();
			}

			return wp_list_pluck( $terms, 'name' );
		}

		// Rest of the values need filtering.
		if ( 'ancestors' === $key ) {
			$value = get_post_ancestors( $this );
		} else {
			$value = get_post_meta( $this->ID, $key, true );
		}

		if ( $this->filter ) {
			$value = sanitize_post_field( $key, $value, $this->ID, $this->filter );
		}

		return $value;
	}

	public function filter( $filter ) {
		if ( $this->filter === $filter ) {
			return $this;
		}

		if ( 'raw' === $filter ) {
			return self::get_instance( $this->ID );
		}

		return sanitize_post( $this, $filter );
	}

	public function to_array() {
		$post = get_object_vars( $this );

		foreach ( array( 'ancestors', 'page_template', 'post_category', 'tags_input' ) as $key ) {
			if ( $this->__isset( $key ) ) {
				$post[ $key ] = $this->__get( $key );
			}
		}

		return $post;
	}

	/**
	 * Adds any posts from the given IDs to the cache that do not already exist in cache.
	 *
	 * @param int[] $ids ID list.
	 * @param bool $update_meta_cache Optional. Whether to update the meta cache. Default true.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 */
	public static function _prime_post_caches( $ids, $update_meta_cache = true ) {
		global $wpdb;

		$non_cached_ids = _get_non_cached_ids( $ids, 'tbds_posts' );
		if ( ! empty( $non_cached_ids ) ) {
			$fresh_posts = $wpdb->get_results( sprintf( "SELECT $wpdb->tbds_posts.* FROM $wpdb->tbds_posts WHERE ID IN (%s)", implode( ',', $non_cached_ids ) ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared

			if ( $fresh_posts ) {
				// Despite the name, update_post_cache() expects an array rather than a single post.
				self::update_post_cache( $fresh_posts );
			}
		}

		if ( $update_meta_cache ) {
			update_meta_cache( 'tbds_posts', $ids );
		}
	}

	/**
	 * Updates posts in cache.
	 *
	 * @param WP_Post[] $posts Array of post objects (passed by reference).
	 */
	public static function update_post_cache( &$posts ) {
		if ( ! $posts ) {
			return;
		}

		$data = array();
		foreach ( $posts as $post ) {
			if ( empty( $post->filter ) || 'raw' !== $post->filter ) {
				$post = sanitize_post( $post, 'raw' );
			}
			$data[ $post->ID ] = $post;
		}
		wp_cache_add_multiple( $data, 'tbds_posts' );
	}

	/**
	 * Updates post, term, and metadata caches for a list of post objects.
	 *
	 * @param WP_Post[] $posts Array of post objects (passed by reference).
	 * @param string $post_type Optional. Post type. Default 'tbds_draft_product'.
	 * @param bool $update_meta_cache Optional. Whether to update the meta cache. Default true.
	 */
	public static function update_post_caches( &$posts, $update_meta_cache = true ) {
		// No point in doing all this work if we didn't match any posts.
		if ( ! $posts ) {
			return;
		}
		self::update_post_cache( $posts );
		$post_ids = array();
		foreach ( $posts as $post ) {
			$post_ids[] = $post->ID;
		}
		if ( $update_meta_cache ) {
			update_meta_cache( 'tbds_posts', $post_ids );
		}
	}

	/**
	 * Will clean the post in the cache.
	 *
	 * Cleaning means delete from the cache of the post. Will call to clean the term
	 * object cache associated with the post ID.
	 *
	 * This function not run if $_wp_suspend_cache_invalidation is not empty. See
	 * wp_suspend_cache_invalidation().
	 *
	 *
	 * @param int|WP_Post $post Post ID or post object to remove from the cache.
	 *
	 * @global bool $_wp_suspend_cache_invalidation
	 *
	 */
	public static function clean_post_cache( $post ) {
		global $_wp_suspend_cache_invalidation;

		if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
			return;
		}

		$post = self::get_post( $post );

		if ( ! $post ) {
			return;
		}

		wp_cache_delete( $post->ID, 'tbds_posts' );
		wp_cache_delete( $post->ID, 'tbds_post_meta' );

		/**
		 * Fires immediately after the given post's cache is cleaned.
		 *
		 * @param int $post_id Post ID.
		 * @param WP_Post $post Post object.
		 */
		do_action( 'clean_post_cache', $post->ID, $post );
		wp_cache_set( 'last_changed', microtime(), 'tbds_posts' );
	}
}
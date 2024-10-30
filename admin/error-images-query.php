<?php

namespace TaobaoDropship\Admin;

defined( 'ABSPATH' ) || exit;

class Error_Images_Query {
	protected static $table = TBDS_CONST['img_table'];

	public static function create_table() {
		global $wpdb;
		$table = self::$table;

		$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `product_id` bigint(20) NOT NULL,
                             `product_ids` longtext NOT NULL,
                             `image_src` longtext NOT NULL,
                             `set_gallery` tinyint(1) NOT NULL,
                             PRIMARY KEY  (`id`)
                             )";

		$wpdb->query( $query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function insert( $product_id, $product_ids, $image_src, $set_gallery ) {
		global $wpdb;
		$table = self::$table;

		$wpdb->insert( $table,//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			[
				'product_id'  => $product_id,
				'product_ids' => $product_ids,
				'image_src'   => $image_src,
				'set_gallery' => $set_gallery,
			],
			[ '%d', '%s', '%s', '%d' ]
		);

		return $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = self::$table;

		$delete = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching

		return $delete;
	}

	public static function get_row( $id ) {
		global $wpdb;
		$table = self::$table;

		$query = "SELECT * FROM {$table} WHERE id=%s LIMIT 1";

		return $wpdb->get_row( $wpdb->prepare( $query, $id ), ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_rows( $limit = 0, $offset = 0, $count = false, $product_id = '' ) {
		global $wpdb;
		$table = self::$table;

		$select = '*';
		if ( $count ) {
			$select = 'count(*)';
			$query  = "SELECT {$select} FROM {$table}";
			if ( $product_id ) {
				$query .= " WHERE {$table}.product_id=%s";
				$query = $wpdb->prepare( $query, $product_id );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			return $wpdb->get_var( $query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$query = "SELECT {$select} FROM {$table}";
			if ( $product_id ) {
				$query .= " WHERE {$table}.product_id=%s";
				if ( $limit ) {
					$query .= " LIMIT {$offset},{$limit}";
				}
				$query = $wpdb->prepare( $query, $product_id ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} elseif ( $limit ) {
				$query .= " LIMIT {$offset},{$limit}";
			}

			return $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	public static function get_products_ids( $search = '', $limit = 50 ) {
		global $wpdb;
		$table = self::$table;

		if ( $search ) {
			$query = "SELECT distinct product_id FROM {$table} left join {$wpdb->posts} on {$table}.product_id={$wpdb->posts}.ID where {$wpdb->posts}.post_title like %s LIMIT 0, {$limit}";
			$query = $wpdb->prepare( $query, '%' . $wpdb->esc_like( $search ) . '%' );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$query = "SELECT distinct product_id FROM {$table}";
		}

		return $wpdb->get_col( $query, 0 );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}
}

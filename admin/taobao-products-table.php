<?php
namespace TaobaoDropship\Admin;
use TaobaoDropship\Inc\Taobao_Post;
use WP_Post;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Taobao_Products_Table' ) ) {
	class Taobao_Products_Table {
		public static function maybe_create_table() {
			global $wpdb;
			$table_list   = array(
				'tbds_posts',
				'tbds_postmeta',
			);
			$found_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}tbds_posts%'" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! empty( array_diff( $table_list, $found_tables ) ) ) {
				self::create_table();
			}
		}

		protected static function create_table() {
			global $wpdb;
			$max_index_length = 191;
			$queries          = array();
			$collate          = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

			$queries[] = "create table if not exists {$wpdb->prefix}tbds_posts
				(
				    ID                    bigint unsigned auto_increment		        primary key,
				    post_author           bigint unsigned default 0                     not null,
				    post_date             datetime        default '0000-00-00 00:00:00' not null,
				    post_date_gmt         datetime        default '0000-00-00 00:00:00' not null,
				    post_content          longtext        default ''                    not null,
				    post_title            text                                          not null,
				    post_excerpt          text                                          not null,
				    post_status           varchar(20)     default 'publish'             not null,
				    post_name             varchar(200)    default ''                    not null,
				    post_modified         datetime        default '0000-00-00 00:00:00' not null,
				    post_modified_gmt     datetime        default '0000-00-00 00:00:00' not null,
				    post_parent           bigint unsigned default 0                     not null,
				    post_type             varchar(20)     default 'tbds_draft_product'  not null,
					KEY post_name (post_name({$max_index_length})),
					KEY type_status_date (post_type,post_status,post_date,ID),
					KEY post_parent (post_parent),
					KEY post_author (post_author)
				) {$collate}";
			$queries[] = "create table if not exists {$wpdb->prefix}tbds_postmeta
				(
				    meta_id bigint(20) unsigned NOT NULL auto_increment,
					tbds_post_id bigint(20) unsigned NOT NULL default '0',
					meta_key varchar(255) default NULL,
					meta_value longtext,
					PRIMARY KEY  (meta_id),
					KEY tbds_post_id (tbds_post_id),
					KEY meta_key (meta_key({$max_index_length}))
				) {$collate}";
			foreach ( $queries as $query ) {
				$wpdb->query( $query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		public static function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
			if ( ! $post ) {
				return null;
			}
			if ( $post instanceof Taobao_Post ) {
				$_post = $post;
			} elseif ( is_object( $post ) ) {
				if ( empty( $post->filter ) ) {
					$_post = sanitize_post( $post, 'raw' );
					$_post = new WP_Post( $_post );
				} elseif ( 'raw' === $post->filter ) {
					$_post = new Taobao_Post( $post );
				} else {
					$_post = Taobao_Post::get_instance( $post->ID );
				}
			} else {
				$_post = Taobao_Post::get_instance( $post );
			}

			if ( ! $_post ) {
				return null;
			}

			$_post = $_post->filter( $filter );

			if ( ARRAY_A === $output ) {
				return $_post->to_array();
			} elseif ( ARRAY_N === $output ) {
				return array_values( $_post->to_array() );
			}

			return $_post;
		}

		public static function get_post_field( $field, $post = null, $context = 'display' ) {
			$post = self::get_post( $post );

			if ( ! $post ) {
				return '';
			}

			if ( ! isset( $post->$field ) ) {
				return '';
			}

			return sanitize_post_field( $field, $post->$field, $post->ID, $context );
		}
		public static function count_posts( $type = 'tbds_draft_product', $perm = '' ) {
			global $wpdb;
			$cache_key = _count_posts_cache_key( $type, $perm );

			$counts = wp_cache_get( $cache_key, 'tbds_counts' );
			if ( false !== $counts ) {
				// We may have cached this before every status was registered.
				foreach ( get_post_stati() as $status ) {
					if ( ! isset( $counts->{$status} ) ) {
						$counts->{$status} = 0;
					}
				}

				/** This filter is documented in wp-includes/post.php */
				return apply_filters( 'wp_count_posts', $counts, $type, $perm );
			}
			$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->tbds_posts} WHERE post_type = %s";

			if ( 'readable' === $perm && is_user_logged_in() ) {
				if ( ! current_user_can( 'read_private_posts' ) ) {
					$query .= $wpdb->prepare(
						" AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
						get_current_user_id()
					);
				}
			}

			$query .= ' GROUP BY post_status';

			$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.PreparedSQL.NotPrepared
			$counts  = array_fill_keys( get_post_stati(), 0 );

			foreach ( $results as $row ) {
				$counts[ $row['post_status'] ] = $row['num_posts'];
			}

			$counts = (object) $counts;
			wp_cache_set( $cache_key, $counts, 'tbds_counts' );
			/**
			 * Filters the post counts by status for the current post type.
			 *
			 * @param stdClass $counts An object containing the current post_type's post
			 *                         counts by status.
			 * @param string $type Post type.
			 * @param string $perm The permission to determine if the posts are 'readable'
			 *                         by the current user.
			 *
			 */
			return apply_filters( 'wp_count_posts', $counts, $type, $perm );
		}
		protected static function check_ascii( $input_string ) {
			if ( function_exists( 'mb_check_encoding' ) ) {
				if ( mb_check_encoding( $input_string, 'ASCII' ) ) {
					return true;
				}
			} elseif ( ! preg_match( '/[^\x00-\x7F]/', $input_string ) ) {
				return true;
			}

			return false;
		}
		/**
		 * Strips any invalid characters based on value/charset pairs.
		 *
		 * @param array $data Array of value arrays. Each value array has the keys 'value' and 'charset'.
		 *                    An optional 'ascii' key can be set to false to avoid redundant ASCII checks.
		 * @return array|WP_Error The $data parameter, with invalid characters removed from each value.
		 *                        This works as a passthrough: any additional keys such as 'field' are
		 *                        retained in each value array. If we cannot remove invalid characters,
		 *                        a WP_Error object is returned.
		 */
		public static function strip_invalid_text( $table, $field, $text ) {
			if (!$text || ! is_string($text)){
				return $text;
			}
			global $wpdb;
			$charset = $wpdb->get_col_charset($table, $field);
			if ( is_wp_error( $charset) || false === $charset ) {
				return '';
			}
			$length = $wpdb->get_col_length( $table, $field );
			if ( is_wp_error( $length ) || false === $charset) {
				return '';
			}
			if ( is_array( $length ) ) {
				$truncate_by_byte_length = 'byte' === ($length['type'] ??'');
				$length                  = $length['length']??255;
			} else {
				$length = false;
				/*
				 * Since we have no length, we'll never truncate. Initialize the variable to false.
				 * True would take us through an unnecessary (for this case) codepath below.
				 */
				$truncate_by_byte_length = false;
			}
			$needs_validation = true;
			if (
				// latin1 can store any byte sequence.
				'latin1' === $charset
				||
				// ASCII is always OK.
				self::check_ascii($text)
			) {
				$truncate_by_byte_length = true;
				$needs_validation        = false;
			}
			if ( $truncate_by_byte_length ) {
				mbstring_binary_safe_encoding();
				if ( false !== $length && strlen( $text ) > $length ) {
					$text = substr( $text, 0, $length );
				}
				reset_mbstring_encoding();

				if ( ! $needs_validation ) {
					return $text;
				}
			}
			if ( ( 'utf8' === $charset || 'utf8mb3' === $charset || 'utf8mb4' === $charset ) && function_exists( 'mb_strlen' ) ) {
				$regex = '/
					(
						(?: [\x00-\x7F]                  # single-byte sequences   0xxxxxxx
						|   [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
						|   \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
						|   [\xE1-\xEC][\x80-\xBF]{2}
						|   \xED[\x80-\x9F][\x80-\xBF]
						|   [\xEE-\xEF][\x80-\xBF]{2}';

				if ( 'utf8mb4' === $charset ) {
					$regex .= '
						|    \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
						|    [\xF1-\xF3][\x80-\xBF]{3}
						|    \xF4[\x80-\x8F][\x80-\xBF]{2}
					';
				}

				$regex         .= '){1,40}                          # ...one or more times
					)
					| .                                  # anything else
					/x';
				$text = preg_replace( $regex, '$1', $text );

				if ( false !== $length && mb_strlen( $text, 'UTF-8' ) > $length ) {
					$text = mb_substr( $text, 0, $length, 'UTF-8' );
				}
				return $text;
			}
			return $text;
		}
		public static function insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
			global $wpdb;
			// Capture original pre-sanitized array for passing into filters.
			$unsanitized_postarr = $postarr;
			$user_id             = get_current_user_id();
			$defaults            = array(
				'post_author'   => $user_id,
				'post_content'  => '',
				'post_title'    => '',
				'post_excerpt'  => '',
				'post_status'   => 'draft',
				'post_type'     => 'tbds_draft_product',
				'post_parent'   => 0,
				'import_id'     => 0,
				'context'       => '',
				'post_date'     => '',
				'post_date_gmt' => '',
			);
			$postarr             = wp_parse_args( $postarr, $defaults );
			unset( $postarr['filter'] );
			$postarr = sanitize_post( $postarr, 'db' );
			// Are we updating or creating?
			$post_id = 0;
			$update  = false;
			if ( ! empty( $postarr['ID'] ) ) {
				$update = true;
				// Get the post ID and GUID.
				$post_id     = $postarr['ID'];
				$post_before = self::get_post( $post_id );

				if ( is_null( $post_before ) ) {
					if ( $wp_error ) {
						return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
					}

					return 0;
				}
				$previous_status = self::get_post_field( 'post_status', $post_id );
			} else {
				$previous_status = 'new';
				$post_before     = null;
			}
			$post_type    = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];
			$post_title   = $postarr['post_title'];
			$post_content = self::strip_invalid_text($wpdb->tbds_posts,'post_content',$postarr['post_content']);
			$post_excerpt = $postarr['post_excerpt'];

			if ( isset( $postarr['post_name'] ) ) {
				$post_name = $postarr['post_name'];
			} elseif ( $update ) {
				// For an update, don't modify the post_name if it wasn't supplied as an argument.
				$post_name = $post_before->post_name;
			}
			$maybe_empty = 'attachment' !== $post_type && ! $post_content && ! $post_title && ! $post_excerpt;

			if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
				return $wp_error ? new WP_Error( 'empty_content', esc_html__( 'Content, title, and excerpt are empty.', 'chinads' ) ) : 0;
			}

			$post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];
			if ( 'pending' === $post_status ) { //wait
				$post_type_object = get_post_type_object( $post_type );

				if ( ! $update && $post_type_object && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
					$post_name = '';
				} elseif ( $update && ! current_user_can( 'publish_post', $post_id ) ) {
					$post_name = '';
				}
			}
			/*
			 * Create a valid post name. Drafts and pending posts are allowed to have
			 * an empty post name.
			 */
			if ( empty( $post_name ) ) {
				if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
					$post_name = sanitize_title( $post_title );
				} else {
					$post_name = '';
				}
			} else {
				// On updates, we need to check to see if it's using the old, fixed sanitization context.
				$check_name = sanitize_title( $post_name, '', 'old-save' );

				if ( $update && strtolower( urlencode( $post_name ) ) == $check_name && self::get_post_field( 'post_name', $post_id ) == $check_name ) {
					$post_name = $check_name;
				} else { // New post, or slug has changed.
					$post_name = sanitize_title( $post_name );
				}
			}
			/*
			 * Resolve the post date from any provided post date or post date GMT strings;
			 * if none are provided, the date will be set to now.
			 */

			$post_date = wp_resolve_post_date( $postarr['post_date'], $postarr['post_date_gmt'] );

			if ( ! $post_date ) {
				if ( $wp_error ) {
					return new WP_Error( 'invalid_date', esc_html__( 'Invalid date.', 'chinads' ) );
				} else {
					return 0;
				}
			}

			if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' === $postarr['post_date_gmt'] ) {
				if ( ! in_array( $post_status, get_post_stati( array( 'date_floating' => true ) ), true ) ) {
					$post_date_gmt = get_gmt_from_date( $post_date );
				} else {
					$post_date_gmt = '0000-00-00 00:00:00';
				}
			} else {
				$post_date_gmt = $postarr['post_date_gmt'];
			}
			if ( $update || '0000-00-00 00:00:00' === $post_date ) {
				$post_modified     = current_time( 'mysql' );
				$post_modified_gmt = current_time( 'mysql', 1 );
			} else {
				$post_modified     = $post_date;
				$post_modified_gmt = $post_date_gmt;
			}

			if ( 'attachment' !== $post_type ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				if ( 'publish' === $post_status ) {
					if ( strtotime( $post_date_gmt ) - strtotime( $now ) >= MINUTE_IN_SECONDS ) {
						$post_status = 'future';
					}
				} elseif ( 'future' === $post_status ) {
					if ( strtotime( $post_date_gmt ) - strtotime( $now ) < MINUTE_IN_SECONDS ) {
						$post_status = 'publish';
					}
				}
			}
			$post_author = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
			$import_id   = isset( $postarr['import_id'] ) ? $postarr['import_id'] : 0;
			$post_parent = isset( $postarr['post_parent'] ) ? (int) $postarr['post_parent'] : 0;
			$new_postarr = array_merge(
				array( 'ID' => $post_id ),
				compact( array_diff( array_keys( $defaults ), array( 'context', 'filter' ) ) )
			);
			$post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_id, $new_postarr, $postarr );
			if ( 'trash' === $previous_status && 'trash' !== $post_status ) {
				$desired_post_slug = self::get_post_meta( $post_id, '_wp_desired_post_slug', true );

				if ( $desired_post_slug ) {
					self::delete_post_meta( $post_id, '_wp_desired_post_slug' );
					$post_name = $desired_post_slug;
				}
			}
			// Expected_slashed (everything!).
			$data  = compact(
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_title',
				'post_excerpt',
				'post_status',
				'post_type',
				'post_name',
				'post_modified',
				'post_modified_gmt',
				'post_parent',
			);
			$data  = wp_unslash( $data );
			$where = array( 'ID' => $post_id );
			if ( $update ) {
				/**
				 * Fires immediately before an existing post is updated in the database.
				 *
				 * @param int $post_id Post ID.
				 * @param array $data Array of unslashed post data.
				 *
				 *
				 */
//				do_action( 'pre_post_update', $post_id, $data );
				if ( false === $wpdb->update( $wpdb->tbds_posts, $data, $where ) ) {//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					return $wp_error ? new WP_Error( 'tbds_db_update_error', esc_html__( 'Could not update post in the database.', 'chinads' ), $wpdb->last_error ) : 0;
				}
			} else {
				// If there is a suggested ID, use it if not already present.
				if ( ! empty( $import_id ) ) {
					$import_id = (int) $import_id;

					if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}tbds_posts WHERE ID = %d", $import_id ) ) ) {//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
						$data['ID'] = $import_id;
					}
				}

				if ( false === $wpdb->insert( $wpdb->prefix . 'tbds_posts', $data ) ) {//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					return $wp_error ? new WP_Error( 'tbds_db_insert_error', esc_html__( 'Could not insert post into the database.' , 'chinads'), $wpdb->last_error ) : 0;
				}

				$post_id = (int) $wpdb->insert_id;

				// Use the newly generated $post_id.
				$where = array( 'ID' => $post_id );
			}
			if ( empty( $data['post_name'] ) && ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) {
				$data['post_name'] = sanitize_title( $data['post_title'], $post_id );

				$wpdb->update( $wpdb->tbds_posts, array( 'post_name' => $data['post_name'] ), $where );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			    Taobao_Post::clean_post_cache( $post_id );
			}
			if ( ! empty( $postarr['meta_input'] ) ) {
				foreach ( $postarr['meta_input'] as $field => $value ) {
					self::update_post_meta( $post_id, $field, $value );
				}
			}
			$post = self::get_post( $post_id );
			/**
			 * Fires once a post has been saved.
			 * @param int     $post_id Post ID.
			 * @param WP_Post $post    Post object.
			 * @param bool    $update  Whether this is an existing post being updated.
			 */
			do_action( 'wp_insert_post', $post_id, $post, $update );

			if ( $fire_after_hooks ) {
				wp_after_insert_post( $post, $update, $post_before );
			}
			return $post_id;
		}
		public static function update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
			if ( is_object( $postarr ) ) {
				// Non-escaped post was passed.
				$postarr = get_object_vars( $postarr );
				$postarr = wp_slash( $postarr );
			}
			// First, get all of the original fields.
			$post = self::get_post( $postarr['ID'], ARRAY_A );

			if ( is_null( $post ) ) {
				if ( $wp_error ) {
					return new WP_Error( 'invalid_post', esc_html__( 'Invalid post ID.', 'chinads' ) );
				}
				return 0;
			}
			// Escape data pulled from DB.
			$post = wp_slash( $post );
			// Drafts shouldn't be assigned a date unless explicitly done so by the user.
			if ( isset( $post['post_status'] )
			     && in_array( $post['post_status'], array( 'draft', 'pending', 'auto-draft' ), true )
			     && empty( $postarr['edit_date'] ) && ( '0000-00-00 00:00:00' === $post['post_date_gmt'] )
			) {
				$clear_date = true;
			} else {
				$clear_date = false;
			}
			// Merge old and new fields with new fields overwriting old ones.
			$postarr = array_merge( $post, $postarr );
			if ( $clear_date ) {
				$postarr['post_date']     = current_time( 'mysql' );
				$postarr['post_date_gmt'] = '';
			}
			return self::insert_post( $postarr, $wp_error, $fire_after_hooks );
		}
		public static function delete_post( $postid = 0, $force_delete = false ) {
			global $wpdb;

			$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->tbds_posts} WHERE ID = %d", $postid ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $post ) {
				return $post;
			}

			$post = self::get_post( $post );
			$check = apply_filters( 'pre_delete_post', null, $post, $force_delete );
			if ( null !== $check ) {
				return $check;
			}

			self::delete_post_meta( $postid, '_wp_trash_meta_status' );
			self::delete_post_meta( $postid, '_wp_trash_meta_time' );

			$parent_data  = array( 'post_parent' => $post->post_parent );
			$parent_where = array( 'post_parent' => $postid );

			if ( is_post_type_hierarchical( $post->post_type ) ) {
				// Point children of this page to its parent, also clean the cache of affected children.
				$children_query = $wpdb->prepare( "SELECT * FROM {$wpdb->tbds_posts} WHERE post_parent = %d AND post_type = %s", $postid, $post->post_type );
				$children       = $wpdb->get_results( $children_query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
				if ( $children ) {
					$wpdb->update( $wpdb->tbds_posts, $parent_data, $parent_where + array( 'post_type' => $post->post_type ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
			}

			// Point all attachments to this post up one level.
			$wpdb->update( $wpdb->tbds_posts, $parent_data, $parent_where );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->tbds_postmeta} WHERE tbds_post_id = %d ", $postid ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( $post_meta_ids as $mid ) {
				delete_metadata_by_mid( 'tbds_post', $mid );
			}

			$result = $wpdb->delete( $wpdb->tbds_posts, array( 'ID' => $postid ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( ! $result ) {
				return false;
			}

		    Taobao_Post::clean_post_cache( $post );

			if ( is_post_type_hierarchical( $post->post_type ) && $children ) {
				foreach ( $children as $child ) {
				Taobao_Post::clean_post_cache( $child );
				}
			}

			wp_clear_scheduled_hook( 'publish_future_post', array( $postid ) );
			return $post;
		}
		public static function trash_post( $post_id = 0 ) {
			if ( ! EMPTY_TRASH_DAYS ) {
				return self::delete_post( $post_id, true );
			}

			$post = self::get_post( $post_id );

			if ( ! $post ) {
				return $post;
			}

			if ( 'trash' === $post->post_status ) {
				return false;
			}

			$check = apply_filters( 'pre_trash_post', null, $post );

			if ( null !== $check ) {
				return $check;
			}

			self::add_post_meta( $post_id, '_wp_trash_meta_status', $post->post_status );
			self::add_post_meta( $post_id, '_wp_trash_meta_time', time() );

			$post_updated = self::update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'trash',
				)
			);

			if ( ! $post_updated ) {
				return false;
			}

			return $post;
		}
		public static function publish_post( $post ) {
			global $wpdb;
			$post = self::get_post( $post );
			if ( ! $post ) {
				return;
			}
			if ( 'publish' === $post->post_status ) {
				return;
			}
			$wpdb->update( $wpdb->tbds_posts, array( 'post_status' => 'publish' ), array( 'ID' => $post->ID ) );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Taobao_Post::clean_post_cache($post);
		}
		public static function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
			return add_metadata( 'tbds_post', $post_id, $meta_key, $meta_value, $unique );
		}

		public static function get_post_meta( $post_id, $key = '', $single = false ) {
			return get_metadata( 'tbds_post', $post_id, $key, $single );
		}
		public static function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			return update_metadata( 'tbds_post', $post_id, $meta_key, $meta_value, $prev_value );
		}
		public static function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
			return delete_metadata( 'tbds_post', $post_id, $meta_key, $meta_value );
		}
	}

}
<?php
namespace TaobaoDropship\Inc;
use WP_Query;
use WP_Meta_Query;
defined( 'ABSPATH' ) || exit;
class Taobao_Post_Query extends WP_Query {
	private $query_vars_hash = false;
	private $query_vars_changed = true;
	protected function parse_orderby( $orderby ) {
		global $wpdb;
		// Used to filter values.
		$allowed_keys = array(
			'post_name',
			'post_author',
			'post_date',
			'post_title',
			'post_modified',
			'post_parent',
			'post_type',
			'name',
			'author',
			'date',
			'title',
			'modified',
			'parent',
			'type',
			'ID',
			'rand',
			'post__in',
			'post_parent__in',
			'post_name__in',
		);
		$primary_meta_key   = '';
		$primary_meta_query = false;
		$meta_clauses       = $this->meta_query->get_clauses();
		if ( ! empty( $meta_clauses ) ) {
			$primary_meta_query = reset( $meta_clauses );

			if ( ! empty( $primary_meta_query['key'] ) ) {
				$primary_meta_key = $primary_meta_query['key'];
				$allowed_keys[]   = $primary_meta_key;
			}

			$allowed_keys[] = 'meta_value';
			$allowed_keys[] = 'meta_value_num';
			$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );
		}

		// If RAND() contains a seed value, sanitize and add to allowed keys.
		$rand_with_seed = false;
		if ( preg_match( '/RAND\(([0-9]+)\)/i', $orderby, $matches ) ) {
			$orderby        = sprintf( 'RAND(%s)', (int) $matches[1] );
			$allowed_keys[] = $orderby;
			$rand_with_seed = true;
		}

		if ( ! in_array( $orderby, $allowed_keys, true ) ) {
			return false;
		}
		$orderby_clause = '';
		switch ($orderby){
			case 'post_name':
			case 'post_author':
			case 'post_date':
			case 'post_title':
			case 'post_modified':
			case 'post_parent':
			case 'post_type':
			case 'ID':
			case 'comment_count':
				$orderby_clause = "{$wpdb->tbds_posts}.{$orderby}";
				break;
			case 'rand':
				$orderby_clause = 'RAND()';
				break;
			case $primary_meta_key:
			case 'meta_value':
				if ( ! empty( $primary_meta_query['type'] ) ) {
					$orderby_clause = "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
				} else {
					$orderby_clause = "{$primary_meta_query['alias']}.meta_value";
				}
				break;
			case 'meta_value_num':
				$orderby_clause = "{$primary_meta_query['alias']}.meta_value+0";
				break;
			case 'post__in':
				if ( ! empty( $this->query_vars['post__in'] ) ) {
					$orderby_clause = "FIELD({$wpdb->tbds_posts}.ID," . implode( ',', array_map( 'absint', $this->query_vars['post__in'] ) ) . ')';
				}
				break;
			case 'post_parent__in':
				if ( ! empty( $this->query_vars['post_parent__in'] ) ) {
					$orderby_clause = "FIELD( {$wpdb->tbds_posts}.post_parent," . implode( ', ', array_map( 'absint', $this->query_vars['post_parent__in'] ) ) . ' )';
				}
				break;
			case 'post_name__in':
				if ( ! empty( $this->query_vars['post_name__in'] ) ) {
					$post_name__in        = array_map( 'sanitize_title_for_query', $this->query_vars['post_name__in'] );
					$post_name__in_string = "'" . implode( "','", $post_name__in ) . "'";
					$orderby_clause       = "FIELD( {$wpdb->tbds_posts}.post_name," . $post_name__in_string . ' )';
				}
				break;
			default:
				if ( array_key_exists( $orderby, $meta_clauses ) ) {
					$meta_clause    = $meta_clauses[ $orderby ];
					$orderby_clause = "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
				} elseif ( $rand_with_seed ) {
					$orderby_clause = $orderby;
				} else {
					// Default: order by post field.
					$orderby_clause = "{$wpdb->tbds_posts}.post_" . sanitize_key( $orderby );
				}
				break;
		}
		return $orderby_clause;
	}
	protected function parse_search( &$q ) {
		global $wpdb;

		$search = '';

		// Added slashes screw with quote grouping when done early, so done later.
		$q['s'] = stripslashes( $q['s'] );
		if ( empty( $_GET['s'] ) && $this->is_main_query() ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q['s'] = urldecode( $q['s'] );
		}
		// There are no line breaks in <input /> fields.
		$q['s']                  = str_replace( array( "\r", "\n" ), '', $q['s'] );
		$q['search_terms_count'] = 1;
		if ( ! empty( $q['sentence'] ) ) {
			$q['search_terms'] = array( $q['s'] );
		} else {
			if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $q['s'], $matches ) ) {
				$q['search_terms_count'] = count( $matches[0] );
				$q['search_terms']       = $this->parse_search_terms( $matches[0] );
				// If the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence.
				if ( empty( $q['search_terms'] ) || count( $q['search_terms'] ) > 9 ) {
					$q['search_terms'] = array( $q['s'] );
				}
			} else {
				$q['search_terms'] = array( $q['s'] );
			}
		}

		$n                         = ! empty( $q['exact'] ) ? '' : '%';
		$searchand                 = '';
		$q['search_orderby_title'] = array();

		$default_search_columns = array( 'post_title', 'post_excerpt', 'post_content' );
		$search_columns         = ! empty( $q['search_columns'] ) ? $q['search_columns'] : $default_search_columns;
		if ( ! is_array( $search_columns ) ) {
			$search_columns = array( $search_columns );
		}

		/**
		 * Filters the columns to search in a WP_Query search.
		 *
		 * The supported columns are `post_title`, `post_excerpt` and `post_content`.
		 * They are all included by default.
		 *
		 * @since 6.2.0
		 *
		 * @param string[] $search_columns Array of column names to be searched.
		 * @param string   $search         Text being searched.
		 * @param WP_Query $query          The current WP_Query instance.
		 */
		$search_columns = (array) apply_filters( 'post_search_columns', $search_columns, $q['s'], $this );

		// Use only supported search columns.
		$search_columns = array_intersect( $search_columns, $default_search_columns );
		if ( empty( $search_columns ) ) {
			$search_columns = $default_search_columns;
		}

		/**
		 * Filters the prefix that indicates that a search term should be excluded from results.
		 *
		 * @since 4.7.0
		 *
		 * @param string $exclusion_prefix The prefix. Default '-'. Returning
		 *                                 an empty value disables exclusions.
		 */
		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );

		foreach ( $q['search_terms'] as $term ) {
			// If there is an $exclusion_prefix, terms prefixed with it should be excluded.
			$exclude = $exclusion_prefix && ( substr( $term, 0, 1 ) === $exclusion_prefix );
			if ( $exclude ) {
				$like_op  = 'NOT LIKE';
				$andor_op = 'AND';
				$term     = substr( $term, 1 );
			} else {
				$like_op  = 'LIKE';
				$andor_op = 'OR';
			}

			if ( $n && ! $exclude ) {
				$like                        = '%' . $wpdb->esc_like( $term ) . '%';
				$q['search_orderby_title'][] = $wpdb->prepare( "{$wpdb->tbds_posts}.post_title LIKE %s", $like );
			}

			$like = $n . $wpdb->esc_like( $term ) . $n;

			$search_columns_parts = array();
			foreach ( $search_columns as $search_column ) {
				$search_columns_parts[ $search_column ] = $wpdb->prepare( "({$wpdb->tbds_posts}.$search_column $like_op %s)", $like );//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			if ( ! empty( $this->allow_query_attachment_by_filename ) ) {
				$search_columns_parts['attachment'] = $wpdb->prepare( "(sq1.meta_value $like_op %s)", $like );//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$search .= "$searchand(" . implode( " $andor_op ", $search_columns_parts ) . ')';

			$searchand = ' AND ';
		}

		if ( ! empty( $search ) ) {
			$search = " AND ({$search}) ";
		}

		return $search;
	}
	private function set_found_posts( $q, $limits ) {
		global $wpdb;

		/*
		 * Bail if posts is an empty array. Continue if posts is an empty string,
		 * null, or false to accommodate caching plugins that fill posts later.
		 */
		if ( $q['no_found_rows'] || ( is_array( $this->posts ) && ! $this->posts ) ) {
			return;
		}
		if ( ! empty( $limits ) ) {
			/**
			 * Filters the query to run for retrieving the found posts.
			 *
			 * @since 2.1.0
			 *
			 * @param string   $found_posts_query The query to run to find the found posts.
			 * @param WP_Query $query             The WP_Query instance (passed by reference).
			 */
			$found_posts_query = apply_filters_ref_array( 'found_posts_query', array( 'SELECT FOUND_ROWS()', &$this ) );
			$this->found_posts = (int) $wpdb->get_var( $found_posts_query );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared
		} else {
			if ( is_array( $this->posts ) ) {
				$this->found_posts = count( $this->posts );
			} else {
				if ( null === $this->posts ) {
					$this->found_posts = 0;
				} else {
					$this->found_posts = 1;
				}
			}
		}

		/**
		 * Filters the number of found posts for the query.
		 *
		 * @since 2.1.0
		 *
		 * @param int      $found_posts The number of posts found.
		 * @param WP_Query $query       The WP_Query instance (passed by reference).
		 */
		$this->found_posts = (int) apply_filters_ref_array( 'found_posts', array( $this->found_posts, &$this ) );

		if ( ! empty( $limits ) ) {
			$this->max_num_pages = ceil( $this->found_posts / $q['posts_per_page'] );
		}
	}
	public function get_posts() {
		global $wpdb;

		$this->parse_query();

		do_action_ref_array( 'pre_get_posts', array( &$this ) );

		// Shorthand.
		$q = &$this->query_vars;

		// Fill again in case 'pre_get_posts' unset some vars.
		$q = $this->fill_query_vars( $q );
		// Parse meta query.
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $q );

		// Set a flag if a 'pre_get_posts' hook changed the query vars.
		$hash = md5( serialize( $this->query_vars ) );
		if ( $hash != $this->query_vars_hash ) {
			$this->query_vars_changed = true;
			$this->query_vars_hash    = $hash;
		}
		unset( $hash );
		// First let's clear some variables.
		$distinct         = '';
		$whichauthor      = '';
		$where            = '';
		$limits           = '';
		$join             = '';
		$search           = '';
		$groupby          = '';
		$post_status_join = false;
		$page             = 1;

		if ( ! isset( $q['ignore_sticky_posts'] ) ) {
			$q['ignore_sticky_posts'] = false;
		}

		if ( ! isset( $q['suppress_filters'] ) ) {
			$q['suppress_filters'] = false;
		}

		if ( ! isset( $q['cache_results'] ) ) {
			$q['cache_results'] = true;
		}
		if ( ! isset( $q['update_post_meta_cache'] ) ) {
			$q['update_post_meta_cache'] = true;
		}
		if ( ! isset( $q['post_type'] ) ) {
			if ( $this->is_search ) {
				$q['post_type'] = 'any';
			} else {
				$q['post_type'] = '';
			}
		}
		$post_type = $q['post_type'];
		if ( empty( $q['posts_per_page'] ) ) {
			$q['posts_per_page'] = get_option( 'posts_per_page' );
		}
		if ( isset( $q['showposts'] ) && $q['showposts'] ) {
			$q['showposts']      = (int) $q['showposts'];
			$q['posts_per_page'] = $q['showposts'];
		}
		if ( ! isset( $q['nopaging'] ) ) {
			if ( - 1 == $q['posts_per_page'] ) {
				$q['nopaging'] = true;
			} else {
				$q['nopaging'] = false;
			}
		}
		if ( $this->is_feed ) {
			// This overrides 'posts_per_page'.
			if ( ! empty( $q['posts_per_rss'] ) ) {
				$q['posts_per_page'] = $q['posts_per_rss'];
			} else {
				$q['posts_per_page'] = get_option( 'posts_per_rss' );
			}
			$q['nopaging'] = false;
		}
		$q['posts_per_page'] = (int) $q['posts_per_page'];
		if ( $q['posts_per_page'] < - 1 ) {
			$q['posts_per_page'] = abs( $q['posts_per_page'] );
		} elseif ( 0 == $q['posts_per_page'] ) {
			$q['posts_per_page'] = 1;
		}
		if ( isset( $q['page'] ) ) {
			$q['page'] = trim( $q['page'], '/' );
			$q['page'] = absint( $q['page'] );
		}

		// If true, forcibly turns off SQL_CALC_FOUND_ROWS even when limits are present.
		if ( isset( $q['no_found_rows'] ) ) {
			$q['no_found_rows'] = (bool) $q['no_found_rows'];
		} else {
			$q['no_found_rows'] = false;
		}
		switch ( $q['fields'] ) {
			case 'ids':
				$fields = "{$wpdb->tbds_posts}.ID";
				break;
			case 'id=>parent':
				$fields = "{$wpdb->tbds_posts}.ID, {$wpdb->tbds_posts}.post_parent";
				break;
			default:
				$fields = "{$wpdb->tbds_posts}.*";
		}

		// The "m" parameter is meant for months but accepts datetimes of varying specificity.
		if ( $q['m'] ) {
			$where .= " AND YEAR({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 0, 4 );
			if ( strlen( $q['m'] ) > 5 ) {
				$where .= " AND MONTH({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 4, 2 );
			}
			if ( strlen( $q['m'] ) > 7 ) {
				$where .= " AND DAYOFMONTH({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 6, 2 );
			}
			if ( strlen( $q['m'] ) > 9 ) {
				$where .= " AND HOUR({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 8, 2 );
			}
			if ( strlen( $q['m'] ) > 11 ) {
				$where .= " AND MINUTE({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 10, 2 );
			}
			if ( strlen( $q['m'] ) > 13 ) {
				$where .= " AND SECOND({$wpdb->tbds_posts}.post_date)=" . substr( $q['m'], 12, 2 );
			}
		}

		// Handle the other individual date parameters.
		$date_parameters = array();
		if ( '' !== $q['hour'] ) {
			$date_parameters['hour'] = $q['hour'];
		}
		if ( '' !== $q['minute'] ) {
			$date_parameters['minute'] = $q['minute'];
		}
		if ( '' !== $q['second'] ) {
			$date_parameters['second'] = $q['second'];
		}
		if ( $q['year'] ) {
			$date_parameters['year'] = $q['year'];
		}
		if ( $q['monthnum'] ) {
			$date_parameters['monthnum'] = $q['monthnum'];
		}
		if ( $q['w'] ) {
			$date_parameters['week'] = $q['w'];
		}
		if ( $q['day'] ) {
			$date_parameters['day'] = $q['day'];
		}
		if ( $date_parameters ) {
			$date_query = new WP_Date_Query( array( $date_parameters ) );
			$where      .= $date_query->get_sql();
		}
		unset( $date_parameters, $date_query );
		// Handle complex date queries.
		if ( ! empty( $q['date_query'] ) ) {
			$this->date_query = new WP_Date_Query( $q['date_query'] );
			$where            .= $this->date_query->get_sql();
		}
		// If we've got a post_type AND it's not "any" post_type.
		if ( ! empty( $q['post_type'] ) && 'any' !== $q['post_type'] ) {
			foreach ( (array) $q['post_type'] as $_post_type ) {
				$ptype_obj = get_post_type_object( $_post_type );
				if ( ! $ptype_obj || ! $ptype_obj->query_var || empty( $q[ $ptype_obj->query_var ] ) ) {
					continue;
				}

				if ( ! $ptype_obj->hierarchical ) {
					// Non-hierarchical post types can directly use 'name'.
					$q['name'] = $q[ $ptype_obj->query_var ];
				} else {
					// Hierarchical post types will operate through 'pagename'.
					$q['pagename'] = $q[ $ptype_obj->query_var ];
					$q['name']     = '';
				}

				// Only one request for a slug is possible, this is why name & pagename are overwritten above.
				break;
			} // End foreach.
			unset( $ptype_obj );
		}
		if ( '' !== $q['title'] ) {
			$where .= $wpdb->prepare( " AND {$wpdb->tbds_posts}.post_title = %s", stripslashes( $q['title'] ) );
		}
		// Parameters related to 'post_name'.
		if ( '' !== $q['name'] ) {
			$q['name'] = sanitize_title_for_query( $q['name'] );
			$where     .= " AND {$wpdb->tbds_posts}.post_name = '" . $q['name'] . "'";
		}elseif ( '' !== $q['attachment'] ) {
			$q['attachment'] = sanitize_title_for_query( wp_basename( $q['attachment'] ) );
			$q['name']       = $q['attachment'];
			$where           .= " AND {$wpdb->tbds_posts}.post_name = '" . $q['attachment'] . "'";
		} elseif ( is_array( $q['post_name__in'] ) && ! empty( $q['post_name__in'] ) ) {
			$q['post_name__in'] = array_map( 'sanitize_title_for_query', $q['post_name__in'] );
			$post_name__in      = "'" . implode( "','", $q['post_name__in'] ) . "'";
			$where              .= " AND {$wpdb->tbds_posts}.post_name IN ($post_name__in)";
		}
		// If an attachment is requested by number, let it supersede any post number.
		if ( $q['attachment_id'] ) {
			$q['p'] = absint( $q['attachment_id'] );
		}
		// If a post number is specified, load that post.
		if ( $q['p'] ) {
			$where .= " AND {$wpdb->tbds_posts}.ID = " . $q['p'];
		} elseif ( $q['post__in'] ) {
			$post__in = implode( ',', array_map( 'absint', $q['post__in'] ) );
			$where    .= " AND {$wpdb->tbds_posts}.ID IN ($post__in)";
		} elseif ( $q['post__not_in'] ) {
			$post__not_in = implode( ',', array_map( 'absint', $q['post__not_in'] ) );
			$where        .= " AND {$wpdb->tbds_posts}.ID NOT IN ($post__not_in)";
		}
		if ( is_numeric( $q['post_parent'] ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->tbds_posts}.post_parent = %d ", $q['post_parent'] );
		} elseif ( $q['post_parent__in'] ) {
			$post_parent__in = implode( ',', array_map( 'absint', $q['post_parent__in'] ) );
			$where           .= " AND {$wpdb->tbds_posts}.post_parent IN ($post_parent__in)";
		} elseif ( $q['post_parent__not_in'] ) {
			$post_parent__not_in = implode( ',', array_map( 'absint', $q['post_parent__not_in'] ) );
			$where               .= " AND {$wpdb->tbds_posts}.post_parent NOT IN ($post_parent__not_in)";
		}
		// If a search pattern is specified, load the posts that match.
		if ( strlen( $q['s'] ) ) {
			$search = $this->parse_search( $q );
		}
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the search SQL that is used in the WHERE clause of WP_Query.
			 *
			 * @param string $search Search SQL for WHERE clause.
			 * @param WP_Query $query The current WP_Query object.
			 *
			 *
			 */
			$search = apply_filters_ref_array( 'posts_search', array( $search, &$this ) );
		}
		if ( ! empty( $this->tax_query->queries ) || ! empty( $this->meta_query->queries ) || ! empty( $this->allow_query_attachment_by_filename ) ) {
			$groupby = "{$wpdb->tbds_posts}.ID";
		}
		// Author/user stuff.
		if ( ! empty( $q['author'] ) && '0' != $q['author'] ) {
			$q['author'] = addslashes_gpc( '' . urldecode( $q['author'] ) );
			$authors     = array_unique( array_map( 'intval', preg_split( '/[,\s]+/', $q['author'] ) ) );
			foreach ( $authors as $author ) {
				$key         = $author > 0 ? 'author__in' : 'author__not_in';
				$q[ $key ][] = abs( $author );
			}
			$q['author'] = implode( ',', $authors );
		}
		if ( ! empty( $q['author__not_in'] ) ) {
			$author__not_in = implode( ',', array_map( 'absint', array_unique( (array) $q['author__not_in'] ) ) );
			$where          .= " AND {$wpdb->tbds_posts}.post_author NOT IN ($author__not_in) ";
		} elseif ( ! empty( $q['author__in'] ) ) {
			$author__in = implode( ',', array_map( 'absint', array_unique( (array) $q['author__in'] ) ) );
			$where      .= " AND {$wpdb->tbds_posts}.post_author IN ($author__in) ";
		}
		// Author stuff for nice URLs.
		if ( '' !== $q['author_name'] ) {
			if ( strpos( $q['author_name'], '/' ) !== false ) {
				$q['author_name'] = explode( '/', $q['author_name'] );
				if ( $q['author_name'][ count( $q['author_name'] ) - 1 ] ) {
					$q['author_name'] = $q['author_name'][ count( $q['author_name'] ) - 1 ]; // No trailing slash.
				} else {
					$q['author_name'] = $q['author_name'][ count( $q['author_name'] ) - 2 ]; // There was a trailing slash.
				}
			}
			$q['author_name'] = sanitize_title_for_query( $q['author_name'] );
			$q['author']      = get_user_by( 'slug', $q['author_name'] );
			if ( $q['author'] ) {
				$q['author'] = $q['author']->ID;
			}
			$whichauthor .= " AND ({$wpdb->tbds_posts}.post_author = " . absint( $q['author'] ) . ')';
		}
		$where .= $search . $whichauthor ;
		if ( ! empty( $this->meta_query->queries ) ) {
			$clauses = $this->meta_query->get_sql( 'tbds_post', $wpdb->tbds_posts, 'ID', $this );
			$join    .= $clauses['join'];
			$where   .= $clauses['where'];
		}
		$rand = ( isset( $q['orderby'] ) && 'rand' === $q['orderby'] );
		if ( ! isset( $q['order'] ) ) {
			$q['order'] = $rand ? '' : 'DESC';
		} else {
			$q['order'] = $rand ? '' : $this->parse_order( $q['order'] );
		}
		// These values of orderby should ignore the 'order' parameter.
		$force_asc = array( 'post__in', 'post_name__in', 'post_parent__in' );
		if ( isset( $q['orderby'] ) && in_array( $q['orderby'], $force_asc, true ) ) {
			$q['order'] = '';
		}
		// Order by.
		if ( empty( $q['orderby'] ) ) {
			/*
			 * Boolean false or empty array blanks out ORDER BY,
			 * while leaving the value unset or otherwise empty sets the default.
			 */
			if ( isset( $q['orderby'] ) && ( is_array( $q['orderby'] ) || false === $q['orderby'] ) ) {
				$orderby = '';
			} else {
				$orderby = "{$wpdb->tbds_posts}.post_date " . $q['order'];
			}
		} elseif ( 'none' === $q['orderby'] ) {
			$orderby = '';
		}else{
			$orderby_array = array();
			if ( is_array( $q['orderby'] ) ) {
				foreach ( $q['orderby'] as $_orderby => $order ) {
					$orderby = addslashes_gpc( urldecode( $_orderby ) );
					$parsed  = $this->parse_orderby( $orderby );
					if ( ! $parsed ) {
						continue;
					}
					$orderby_array[] = $parsed . ' ' . $this->parse_order( $order );
				}
				$orderby = implode( ', ', $orderby_array );
			}else {
				$q['orderby'] = urldecode( $q['orderby'] );
				$q['orderby'] = addslashes_gpc( $q['orderby'] );
				foreach ( explode( ' ', $q['orderby'] ) as $i => $orderby ) {
					$parsed = $this->parse_orderby( $orderby );
					// Only allow certain values for safety.
					if ( ! $parsed ) {
						continue;
					}

					$orderby_array[] = $parsed;
				}
				$orderby = implode( ' ' . $q['order'] . ', ', $orderby_array );
				if ( empty( $orderby ) ) {
					$orderby = "{$wpdb->tbds_posts}.post_date " . $q['order'];
				} elseif ( ! empty( $q['order'] ) ) {
					$orderby .= " {$q['order']}";
				}
			}
		}
		// Order search results by relevance only when another "orderby" is not specified in the query.
		if ( ! empty( $q['s'] ) ) {
			$search_orderby = '';
			if ( ! empty( $q['search_orderby_title'] ) && ( empty( $q['orderby'] ) && ! $this->is_feed ) || ( isset( $q['orderby'] ) && 'relevance' === $q['orderby'] ) ) {
				$search_orderby = $this->parse_search_order( $q );
			}

			if ( ! $q['suppress_filters'] ) {
				/**
				 * Filters the ORDER BY used when ordering search results.
				 *
				 * @param string $search_orderby The ORDER BY clause.
				 * @param WP_Query $query The current WP_Query instance.
				 *
				 *
				 */
				$search_orderby = apply_filters( 'posts_search_orderby', $search_orderby, $this );
			}

			if ( $search_orderby ) {
				$orderby = $orderby ? $search_orderby . ', ' . $orderby : $search_orderby;
			}
		}
		if ( is_array( $post_type ) && count( $post_type ) > 1 ) {
			$post_type_cap = 'multiple_post_type';
		} else {
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			$post_type_object = get_post_type_object( $post_type );
			if ( empty( $post_type_object ) ) {
				$post_type_cap = $post_type;
			}
		}
		$skip_post_status = false;
		if ( 'any' === $post_type ) {
			$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
			if ( empty( $in_search_post_types ) ) {
				$post_type_where  = ' AND 1=0 ';
				$skip_post_status = true;
			} else {
				$post_type_where = " AND {$wpdb->tbds_posts}.post_type IN ('" . implode( "', '", array_map( 'esc_sql', $in_search_post_types ) ) . "')";
			}
		}elseif ( ! empty( $post_type ) && is_array( $post_type ) ) {
			$post_type_where = " AND {$wpdb->tbds_posts}.post_type IN ('" . implode( "', '", esc_sql( $post_type ) ) . "')";
		} elseif ( ! empty( $post_type ) ) {
			$post_type_where  = $wpdb->prepare( " AND {$wpdb->tbds_posts}.post_type = %s", $post_type );
			$post_type_object = get_post_type_object( $post_type );
		} else {
			$post_type_where  = " AND {$wpdb->tbds_posts}.post_type = 'post'";
			$post_type_object = get_post_type_object( 'post' );
		}
		$edit_cap = 'edit_post';
		$read_cap = 'read_post';

		if ( ! empty( $post_type_object ) ) {
			$edit_others_cap  = $post_type_object->cap->edit_others_posts;
			$read_private_cap = $post_type_object->cap->read_private_posts;
		} else {
			$edit_others_cap  = 'edit_others_' . $post_type_cap . 's';
			$read_private_cap = 'read_private_' . $post_type_cap . 's';
		}
		$user_id = get_current_user_id();
		if ( $skip_post_status ) {
			$where .= $post_type_where;
		}elseif ( ! empty( $q['post_status'] ) ) {
			$where .= $post_type_where;
			$statuswheres = array();
			$q_status     = $q['post_status'];
			if ( ! is_array( $q_status ) ) {
				$q_status = explode( ',', $q_status );
			}
			$r_status = array();
			$p_status = array();
			$e_status = array();
			if ( in_array( 'any', $q_status, true ) ) {
				foreach ( get_post_stati( array( 'exclude_from_search' => true ) ) as $status ) {
					if ( ! in_array( $status, $q_status, true ) ) {
						$e_status[] = "{$wpdb->tbds_posts}.post_status <> '$status'";
					}
				}
			} else {
				foreach ( get_post_stati() as $status ) {
					if ( in_array( $status, $q_status, true ) ) {
						if ( 'private' === $status ) {
							$p_status[] = "{$wpdb->tbds_posts}.post_status = '$status'";
						} else {
							$r_status[] = "{$wpdb->tbds_posts}.post_status = '$status'";
						}
					}
				}
			}
			if ( empty( $q['perm'] ) || 'readable' !== $q['perm'] ) {
				$r_status = array_merge( $r_status, $p_status );
				unset( $p_status );
			}
			if ( ! empty( $e_status ) ) {
				$statuswheres[] = '(' . implode( ' AND ', $e_status ) . ')';
			}
			if ( ! empty( $r_status ) ) {
				if ( ! empty( $q['perm'] ) && 'editable' === $q['perm'] && ! current_user_can( $edit_others_cap ) ) {
					$statuswheres[] = "({$wpdb->tbds_posts}.post_author = $user_id " . 'AND (' . implode( ' OR ', $r_status ) . '))';
				} else {
					$statuswheres[] = '(' . implode( ' OR ', $r_status ) . ')';
				}
			}
			if ( ! empty( $p_status ) ) {
				if ( ! empty( $q['perm'] ) && 'readable' === $q['perm'] && ! current_user_can( $read_private_cap ) ) {
					$statuswheres[] = "({$wpdb->tbds_posts}.post_author = $user_id " . 'AND (' . implode( ' OR ', $p_status ) . '))';
				} else {
					$statuswheres[] = '(' . implode( ' OR ', $p_status ) . ')';
				}
			}
			if ( $post_status_join ) {
				$join .= " LEFT JOIN {$wpdb->tbds_posts} AS p2 ON ({$wpdb->tbds_posts}.post_parent = p2.ID) ";
				foreach ( $statuswheres as $index => $statuswhere ) {
					$statuswheres[ $index ] = "($statuswhere OR ({$wpdb->tbds_posts}.post_status = 'inherit' AND " . str_replace( $wpdb->tbds_posts, 'p2', $statuswhere ) . '))';
				}
			}
			$where_status = implode( ' OR ', $statuswheres );
			if ( ! empty( $where_status ) ) {
				$where .= " AND ($where_status)";
			}
		}elseif ( ! $this->is_singular ){
			if ( 'any' === $post_type ) {
				$queried_post_types = get_post_types( array( 'exclude_from_search' => false ) );
			} elseif ( is_array( $post_type ) ) {
				$queried_post_types = $post_type;
			} elseif ( ! empty( $post_type ) ) {
				$queried_post_types = array( $post_type );
			} else {
				$queried_post_types = array( 'post' );
			}
			if ( ! empty( $queried_post_types ) ) {
				$status_type_clauses = array();
				foreach ( $queried_post_types as $queried_post_type ) {
					$queried_post_type_object = get_post_type_object( $queried_post_type );
					$type_where = '(' . $wpdb->prepare( "{$wpdb->tbds_posts}.post_type = %s AND (", $queried_post_type );
					// Public statuses.
					$public_statuses = get_post_stati( array( 'public' => true ) );
					$status_clauses  = array();
					foreach ( $public_statuses as $public_status ) {
						$status_clauses[] = "{$wpdb->tbds_posts}.post_status = '$public_status'";
					}
					$type_where .= implode( ' OR ', $status_clauses );
					// Add protected states that should show in the admin all list.
					if ( $this->is_admin ) {
						$admin_all_statuses = get_post_stati(
							array(
								'protected'              => true,
								'show_in_admin_all_list' => true,
							)
						);
						foreach ( $admin_all_statuses as $admin_all_status ) {
							$type_where .= " OR {$wpdb->tbds_posts}.post_status = '$admin_all_status'";
						}
					}

					// Add private states that are visible to current user.
					if ( is_user_logged_in() && $queried_post_type_object instanceof WP_Post_Type ) {
						$read_private_cap = $queried_post_type_object->cap->read_private_posts;
						$private_statuses = get_post_stati( array( 'private' => true ) );
						foreach ( $private_statuses as $private_status ) {
							$type_where .= current_user_can( $read_private_cap ) ? " \nOR {$wpdb->tbds_posts}.post_status = '$private_status'" : " \nOR ({$wpdb->tbds_posts}.post_author = $user_id AND {$wpdb->tbds_posts}.post_status = '$private_status')";
						}
					}

					$type_where .= '))';

					$status_type_clauses[] = $type_where;
				}
				if ( ! empty( $status_type_clauses ) ) {
					$where .= ' AND (' . implode( ' OR ', $status_type_clauses ) . ')';
				}
			}else {
				$where .= ' AND 1=0 ';
			}
		}else {
			$where .= $post_type_where;
		}
		/*
		 * Apply filters on where and join prior to paging so that any
		 * manipulations to them are reflected in the paging by day queries.
		 */
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the WHERE clause of the query.
			 *
			 * @param string $where The WHERE clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$where = apply_filters_ref_array( 'posts_where', array( $where, &$this ) );

			/**
			 * Filters the JOIN clause of the query.
			 *
			 * @param string $join The JOIN clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$join = apply_filters_ref_array( 'posts_join', array( $join, &$this ) );
		}
		// Paging.
		if ( empty( $q['nopaging'] ) && ! $this->is_singular ) {
			$page = absint( $q['paged'] );
			if ( ! $page ) {
				$page = 1;
			}

			// If 'offset' is provided, it takes precedence over 'paged'.
			if ( isset( $q['offset'] ) && is_numeric( $q['offset'] ) ) {
				$q['offset'] = absint( $q['offset'] );
				$pgstrt      = $q['offset'] . ', ';
			} else {
				$pgstrt = absint( ( $page - 1 ) * $q['posts_per_page'] ) . ', ';
			}
			$limits = 'LIMIT ' . $pgstrt . $q['posts_per_page'];
		}
		$pieces = array( 'where', 'groupby', 'join', 'orderby', 'distinct', 'fields', 'limits' );
		/*
		 * Apply post-paging filters on where and join. Only plugins that
		 * manipulate paging queries should use these hooks.
		 */
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the WHERE clause of the query.
			 *
			 * Specifically for manipulating paging queries.
			 *
			 * @param string $where The WHERE clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$where = apply_filters_ref_array( 'posts_where_paged', array( $where, &$this ) );

			/**
			 * Filters the GROUP BY clause of the query.
			 *
			 * @param string $groupby The GROUP BY clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$groupby = apply_filters_ref_array( 'posts_groupby', array( $groupby, &$this ) );
			/**
			 * Filters the JOIN clause of the query.
			 *
			 * Specifically for manipulating paging queries.
			 *
			 * @param string $join The JOIN clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$join = apply_filters_ref_array( 'posts_join_paged', array( $join, &$this ) );
			/**
			 * Filters the ORDER BY clause of the query.
			 *
			 * @param string $orderby The ORDER BY clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$orderby = apply_filters_ref_array( 'posts_orderby', array( $orderby, &$this ) );
			/**
			 * Filters the DISTINCT clause of the query.
			 *
			 * @param string $distinct The DISTINCT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$distinct = apply_filters_ref_array( 'posts_distinct', array( $distinct, &$this ) );

			/**
			 * Filters the LIMIT clause of the query.
			 *
			 * @param string $limits The LIMIT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$limits = apply_filters_ref_array( 'post_limits', array( $limits, &$this ) );
			/**
			 * Filters the SELECT clause of the query.
			 *
			 * @param string $fields The SELECT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$fields = apply_filters_ref_array( 'posts_fields', array( $fields, &$this ) );
			/**
			 * Filters all query clauses at once, for convenience.
			 *
			 * Covers the WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT,
			 * fields (SELECT), and LIMIT clauses.
			 *
			 * @param string[] $clauses {
			 *     Associative array of the clauses for the query.
			 *
			 * @type string $where The WHERE clause of the query.
			 * @type string $groupby The GROUP BY clause of the query.
			 * @type string $join The JOIN clause of the query.
			 * @type string $orderby The ORDER BY clause of the query.
			 * @type string $distinct The DISTINCT clause of the query.
			 * @type string $fields The SELECT clause of the query.
			 * @type string $limits The LIMIT clause of the query.
			 * }
			 *
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$clauses = (array) apply_filters_ref_array( 'posts_clauses', array( compact( $pieces ), &$this ) );
			$where    = isset( $clauses['where'] ) ? $clauses['where'] : '';
			$groupby  = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';
			$join     = isset( $clauses['join'] ) ? $clauses['join'] : '';
			$orderby  = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
			$distinct = isset( $clauses['distinct'] ) ? $clauses['distinct'] : '';
			$fields   = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
			$limits   = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
		}
		/**
		 * Fires to announce the query's current selection parameters.
		 *
		 * For use by caching plugins.
		 *
		 * @param string $selection The assembled selection query.
		 *
		 *
		 */
		do_action( 'posts_selection', $where . $groupby . $orderby . $limits . $join );
		/*
		 * Filters again for the benefit of caching plugins.
		 * Regular plugins should use the hooks above.
		 */
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the WHERE clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $where The WHERE clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$where = apply_filters_ref_array( 'posts_where_request', array( $where, &$this ) );
			/**
			 * Filters the GROUP BY clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $groupby The GROUP BY clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$groupby = apply_filters_ref_array( 'posts_groupby_request', array( $groupby, &$this ) );
			/**
			 * Filters the JOIN clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $join The JOIN clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$join = apply_filters_ref_array( 'posts_join_request', array( $join, &$this ) );
			/**
			 * Filters the ORDER BY clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $orderby The ORDER BY clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$orderby = apply_filters_ref_array( 'posts_orderby_request', array( $orderby, &$this ) );
			/**
			 * Filters the DISTINCT clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $distinct The DISTINCT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$distinct = apply_filters_ref_array( 'posts_distinct_request', array( $distinct, &$this ) );
			/**
			 * Filters the SELECT clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $fields The SELECT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$fields = apply_filters_ref_array( 'posts_fields_request', array( $fields, &$this ) );

			/**
			 * Filters the LIMIT clause of the query.
			 *
			 * For use by caching plugins.
			 *
			 * @param string $limits The LIMIT clause of the query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$limits = apply_filters_ref_array( 'post_limits_request', array( $limits, &$this ) );
			/**
			 * Filters all query clauses at once, for convenience.
			 *
			 * For use by caching plugins.
			 *
			 * Covers the WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT,
			 * fields (SELECT), and LIMIT clauses.
			 *
			 * @param string[] $clauses {
			 *     Associative array of the clauses for the query.
			 *
			 * @type string $where The WHERE clause of the query.
			 * @type string $groupby The GROUP BY clause of the query.
			 * @type string $join The JOIN clause of the query.
			 * @type string $orderby The ORDER BY clause of the query.
			 * @type string $distinct The DISTINCT clause of the query.
			 * @type string $fields The SELECT clause of the query.
			 * @type string $limits The LIMIT clause of the query.
			 * }
			 *
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$clauses = (array) apply_filters_ref_array( 'posts_clauses_request', array( compact( $pieces ), &$this ) );

			$where    = isset( $clauses['where'] ) ? $clauses['where'] : '';
			$groupby  = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';
			$join     = isset( $clauses['join'] ) ? $clauses['join'] : '';
			$orderby  = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
			$distinct = isset( $clauses['distinct'] ) ? $clauses['distinct'] : '';
			$fields   = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
			$limits   = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
		}

		if ( ! empty( $groupby ) ) {
			$groupby = 'GROUP BY ' . $groupby;
		}
		if ( ! empty( $orderby ) ) {
			$orderby = 'ORDER BY ' . $orderby;
		}

		$found_rows = '';
		if ( ! $q['no_found_rows'] && ! empty( $limits ) ) {
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}
		$old_request = "
			SELECT $found_rows $distinct $fields
			FROM {$wpdb->tbds_posts} $join
			WHERE 1=1 $where
			$groupby
			$orderby
			$limits
		";

		$this->request = $old_request;
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the completed SQL query before sending.
			 *
			 * @param string $request The complete SQL query.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$this->request = apply_filters_ref_array( 'posts_request', array( $this->request, &$this ) );
		}
		$this->posts = apply_filters_ref_array( 'posts_pre_query', array( null, &$this ) );

		$id_query_is_cacheable = ! str_contains( strtoupper( $orderby ), ' RAND(' );

		$cacheable_field_values = array(
			"{$wpdb->tbds_posts}.*",
			"{$wpdb->tbds_posts}.ID, {$wpdb->tbds_posts}.post_parent",
			"{$wpdb->tbds_posts}.ID",
		);
		if ( ! in_array( $fields, $cacheable_field_values, true ) ) {
			$id_query_is_cacheable = false;
		}
		if ( $q['cache_results'] && $id_query_is_cacheable ) {
			$new_request = str_replace( $fields, "{$wpdb->tbds_posts}.*", $this->request );
			$cache_key   = $this->generate_cache_key( $q, $new_request );
			$cache_found = false;
			if ( null === $this->posts ) {
				$cached_results = wp_cache_get( $cache_key, 'tbds_posts_queries', false, $cache_found );
				if ( $cached_results ) {
					if ( 'ids' === $q['fields'] ) {
						/** @var int[] */
						$this->posts = array_map( 'intval', $cached_results['posts'] );
					} else {
						Taobao_Post::_prime_post_caches( $cached_results['posts'], $q['update_post_meta_cache'] );
						/** @var WP_Post[] */
						$this->posts = array_map( 'TaobaoDropship\Inc\Taobao_Post::get_post', $cached_results['posts'] );
					}
					$this->post_count    = count( $this->posts );
					$this->found_posts   = $cached_results['found_posts'];
					$this->max_num_pages = $cached_results['max_num_pages'];
					if ( 'ids' === $q['fields'] ) {
						return $this->posts;
					} elseif ( 'id=>parent' === $q['fields'] ) {
						/** @var int[] */
						$post_parents = array();

						foreach ( $this->posts as $key => $post ) {
							$obj              = new stdClass();
							$obj->ID          = (int) $post->ID;
							$obj->post_parent = (int) $post->post_parent;

							$this->posts[ $key ] = $obj;

							$post_parents[ $obj->ID ] = $obj->post_parent;
						}

						return $post_parents;
					}
				}
			}
		}
		if ( 'ids' === $q['fields'] ) {
			if ( null === $this->posts ) {
				$this->posts = $wpdb->get_col( $this->request );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.PreparedSQL.NotPrepared
			}
			/** @var int[] */
			$this->posts      = array_map( 'intval', $this->posts );
			$this->post_count = count( $this->posts );
			$this->set_found_posts( $q, $limits );
			if ( $q['cache_results'] && $id_query_is_cacheable ) {
				$cache_value = array(
					'posts'         => $this->posts,
					'found_posts'   => $this->found_posts,
					'max_num_pages' => $this->max_num_pages,
				);

				wp_cache_set( $cache_key, $cache_value, 'tbds_posts_queries' );
			}

			return $this->posts;
		}
		if ( 'id=>parent' === $q['fields'] ) {
			if ( null === $this->posts ) {
				$this->posts = $wpdb->get_results( $this->request );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery ,  WordPress.DB.PreparedSQL.NotPrepared
			}
			$this->post_count = count( $this->posts );
			$this->set_found_posts( $q, $limits );
			/** @var int[] */
			$post_parents = array();
			$post_ids     = array();
			foreach ( $this->posts as $key => $post ) {
				$this->posts[ $key ]->ID          = (int) $post->ID;
				$this->posts[ $key ]->post_parent = (int) $post->post_parent;

				$post_parents[ (int) $post->ID ] = (int) $post->post_parent;
				$post_ids[]                      = (int) $post->ID;
			}
			if ( $q['cache_results'] && $id_query_is_cacheable ) {
				$cache_value = array(
					'posts'         => $post_ids,
					'found_posts'   => $this->found_posts,
					'max_num_pages' => $this->max_num_pages,
				);

				wp_cache_set( $cache_key, $cache_value, 'tbds_posts_queries' );
			}

			return $post_parents;
		}
		if ( null === $this->posts ) {
			$split_the_query = ( $old_request == $this->request && "{$wpdb->tbds_posts}.*" === $fields && ! empty( $limits ) && $q['posts_per_page'] < 500 );
			/**
			 * Filters whether to split the query.
			 *
			 * Splitting the query will cause it to fetch just the IDs of the found posts
			 * (and then individually fetch each post by ID), rather than fetching every
			 * complete row at once. One massive result vs. many small results.
			 *
			 * @param bool $split_the_query Whether or not to split the query.
			 * @param WP_Query $query The WP_Query instance.
			 *
			 *
			 */
			$split_the_query = apply_filters( 'split_the_query', $split_the_query, $this );
			if ( $split_the_query ) {
				// First get the IDs and then fill in the objects.

				$this->request = "
					SELECT $found_rows $distinct {$wpdb->tbds_posts}.ID
					FROM {$wpdb->tbds_posts} $join
					WHERE 1=1 $where
					$groupby
					$orderby
					$limits
				";

				/**
				 * Filters the Post IDs SQL request before sending.
				 *
				 * @param string $request The post ID request.
				 * @param WP_Query $query The WP_Query instance.
				 *
				 *
				 */
				$this->request = apply_filters( 'posts_request_ids', $this->request, $this );
				$post_ids = $wpdb->get_col( $this->request );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.PreparedSQL.NotPrepared
				if ( $post_ids ) {
					$this->posts = $post_ids;
					$this->set_found_posts( $q, $limits );
					Taobao_Post::_prime_post_caches( $post_ids,  $q['update_post_meta_cache'] );
				} else {
					$this->posts = array();
				}
			} else {
				$this->posts = $wpdb->get_results( $this->request );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.PreparedSQL.NotPrepared
				$this->set_found_posts( $q, $limits );
			}
		}
		// Convert to WP_Post objects.
		if ( $this->posts ) {
			/** @var WP_Post[] */
			$this->posts = array_map( 'TaobaoDropship\Inc\Taobao_Post::get_post', $this->posts );
		}
		if ( $q['cache_results'] && $id_query_is_cacheable && ! $cache_found ) {
			$post_ids = wp_list_pluck( $this->posts, 'ID' );

			$cache_value = array(
				'posts'         => $post_ids,
				'found_posts'   => $this->found_posts,
				'max_num_pages' => $this->max_num_pages,
			);

			wp_cache_set( $cache_key, $cache_value, 'tbds_posts_queries' );
		}
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the raw post results array, prior to status checks.
			 *
			 * @param WP_Post[] $posts Array of post objects.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$this->posts = apply_filters_ref_array( 'posts_results', array( $this->posts, &$this ) );
		}
		if ( ! $q['suppress_filters'] ) {
			/**
			 * Filters the array of retrieved posts after they've been fetched and
			 * internally processed.
			 *
			 * @param WP_Post[] $posts Array of post objects.
			 * @param WP_Query $query The WP_Query instance (passed by reference).
			 *
			 *
			 */
			$this->posts = apply_filters_ref_array( 'the_posts', array( $this->posts, &$this ) );
		}
		// Ensure that any posts added/modified via one of the filters above are
		// of the type WP_Post and are filtered.
		if ( $this->posts ) {
			$this->post_count = count( $this->posts );

			/** @var WP_Post[] */
			$this->posts = array_map( 'TaobaoDropship\Inc\Taobao_Post::get_post', $this->posts );

			if ( $q['cache_results'] ) {
				Taobao_Post::update_post_caches( $this->posts, $q['update_post_meta_cache'] );
			}

			/** @var WP_Post */
			$this->post = reset( $this->posts );
		} else {
			$this->post_count = 0;
			$this->posts      = array();
		}

		return $this->posts;
	}
}
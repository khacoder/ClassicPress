<?php
/**
 * Post revision functions.
 *
 * @package ClassicPress
 * @subpackage Post_Revisions
 */

/**
 * Determines which fields of posts are to be saved in revisions.
 *
 * @since WP-2.6.0
 * @since WP-4.5.0 A `WP_Post` object can now be passed to the `$post` parameter.
 * @since WP-4.5.0 The optional `$autosave` parameter was deprecated and renamed to `$deprecated`.
 * @access private
 *
 * @staticvar array $fields
 *
 * @param array|WP_Post $post       Optional. A post array or a WP_Post object being processed
 *                                  for insertion as a post revision. Default empty array.
 * @param bool          $deprecated Not used.
 * @return array Array of fields that can be versioned.
 */
function _wp_post_revision_fields( $post = array(), $deprecated = false ) {
	static $fields = null;

	if ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	if ( is_null( $fields ) ) {
		// Allow these to be versioned
		$fields = array(
			'post_title'   => __( 'Title' ),
			'post_content' => __( 'Content' ),
			'post_excerpt' => __( 'Excerpt' ),
		);
	}

	/**
	 * Filters the list of fields saved in post revisions.
	 *
	 * Included by default: 'post_title', 'post_content' and 'post_excerpt'.
	 *
	 * Disallowed fields: 'ID', 'post_name', 'post_parent', 'post_date',
	 * 'post_date_gmt', 'post_status', 'post_type', 'comment_count',
	 * and 'post_author'.
	 *
	 * @since WP-2.6.0
	 * @since WP-4.5.0 The `$post` parameter was added.
	 *
	 * @param array $fields List of fields to revision. Contains 'post_title',
	 *                      'post_content', and 'post_excerpt' by default.
	 * @param array $post   A post array being processed for insertion as a post revision.
	 */
	$fields = apply_filters( '_wp_post_revision_fields', $fields, $post );

	// WP uses these internally either in versioning or elsewhere - they cannot be versioned
	foreach ( array( 'ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status', 'post_type', 'comment_count', 'post_author' ) as $protect ) {
		unset( $fields[ $protect ] );
	}

	return $fields;
}

/**
 * Returns a post array ready to be inserted into the posts table as a post revision.
 *
 * @since WP-4.5.0
 * @access private
 *
 * @param array|WP_Post $post     Optional. A post array or a WP_Post object to be processed
 *                                for insertion as a post revision. Default empty array.
 * @param bool          $autosave Optional. Is the revision an autosave? Default false.
 * @return array Post array ready to be inserted as a post revision.
 */
function _wp_post_revision_data( $post = array(), $autosave = false ) {
	if ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	$fields = _wp_post_revision_fields( $post );

	$revision_data = array();

	foreach ( array_intersect( array_keys( $post ), array_keys( $fields ) ) as $field ) {
		$revision_data[ $field ] = $post[ $field ];
	}

	$revision_data['post_parent']   = $post['ID'];
	$revision_data['post_status']   = 'inherit';
	$revision_data['post_type']     = 'revision';
	$revision_data['post_name']     = $autosave ? "$post[ID]-autosave-v1" : "$post[ID]-revision-v1"; // "1" is the revisioning system version
	$revision_data['post_date']     = isset( $post['post_modified'] ) ? $post['post_modified'] : '';
	$revision_data['post_date_gmt'] = isset( $post['post_modified_gmt'] ) ? $post['post_modified_gmt'] : '';

	return $revision_data;
}

/**
 * Creates a revision for the current version of a post.
 *
 * Typically used immediately after a post update, as every update is a revision,
 * and the most recent revision always matches the current post.
 *
 * @since WP-2.6.0
 *
 * @param int $post_id The ID of the post to save as a revision.
 * @return int|WP_Error|void Void or 0 if error, new revision ID, if success.
 */
function wp_save_post_revision( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! $post = get_post( $post_id ) ) {
		return;
	}

	if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
		return;
	}

	if ( 'auto-draft' == $post->post_status ) {
		return;
	}

	if ( ! wp_revisions_enabled( $post ) ) {
		return;
	}

	// Compare the proposed update with the last stored revision verifying that
	// they are different, unless a plugin tells us to always save regardless.
	// If no previous revisions, save one
	if ( $revisions = wp_get_post_revisions( $post_id ) ) {
		// grab the last revision, but not an autosave
		foreach ( $revisions as $revision ) {
			if ( false !== strpos( $revision->post_name, "{$revision->post_parent}-revision" ) ) {
				$last_revision = $revision;
				break;
			}
		}

		/**
		 * Filters whether the post has changed since the last revision.
		 *
		 * By default a revision is saved only if one of the revisioned fields has changed.
		 * This filter can override that so a revision is saved even if nothing has changed.
		 *
		 * @since WP-3.6.0
		 *
		 * @param bool    $check_for_changes Whether to check for changes before saving a new revision.
		 *                                   Default true.
		 * @param WP_Post $last_revision     The last revision post object.
		 * @param WP_Post $post              The post object.
		 *
		 */
		if ( isset( $last_revision ) && apply_filters( 'wp_save_post_revision_check_for_changes', $check_for_changes = true, $last_revision, $post ) ) {
			$post_has_changed = false;

			foreach ( array_keys( _wp_post_revision_fields( $post ) ) as $field ) {
				if ( normalize_whitespace( $post->$field ) != normalize_whitespace( $last_revision->$field ) ) {
					$post_has_changed = true;
					break;
				}
			}

			/**
			 * Filters whether a post has changed.
			 *
			 * By default a revision is saved only if one of the revisioned fields has changed.
			 * This filter allows for additional checks to determine if there were changes.
			 *
			 * @since WP-4.1.0
			 *
			 * @param bool    $post_has_changed Whether the post has changed.
			 * @param WP_Post $last_revision    The last revision post object.
			 * @param WP_Post $post             The post object.
			 *
			 */
			$post_has_changed = (bool) apply_filters( 'wp_save_post_revision_post_has_changed', $post_has_changed, $last_revision, $post );

			//don't save revision if post unchanged
			if ( ! $post_has_changed ) {
				return;
			}
		}
	}

	$return = _wp_put_post_revision( $post );

	// If a limit for the number of revisions to keep has been set,
	// delete the oldest ones.
	$revisions_to_keep = wp_revisions_to_keep( $post );

	if ( $revisions_to_keep < 0 ) {
		return $return;
	}

	$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );

	$delete = count( $revisions ) - $revisions_to_keep;

	if ( $delete < 1 ) {
		return $return;
	}

	$revisions = array_slice( $revisions, 0, $delete );

	for ( $i = 0; isset( $revisions[ $i ] ); $i++ ) {
		if ( false !== strpos( $revisions[ $i ]->post_name, 'autosave' ) ) {
			continue;
		}

		wp_delete_post_revision( $revisions[ $i ]->ID );
	}

	return $return;
}

/**
 * Retrieve the autosaved data of the specified post.
 *
 * Returns a post object containing the information that was autosaved for the
 * specified post. If the optional $user_id is passed, returns the autosave for that user
 * otherwise returns the latest autosave.
 *
 * @since WP-2.6.0
 *
 * @param int $post_id The post ID.
 * @param int $user_id Optional The post author ID.
 * @return WP_Post|false The autosaved data or false on failure or when no autosave exists.
 */
function wp_get_post_autosave( $post_id, $user_id = 0 ) {
	$revisions = wp_get_post_revisions( $post_id, array( 'check_enabled' => false ) );

	foreach ( $revisions as $revision ) {
		if ( false !== strpos( $revision->post_name, "{$post_id}-autosave" ) ) {
			if ( $user_id && $user_id != $revision->post_author ) {
				continue;
			}

			return $revision;
		}
	}

	return false;
}

/**
 * Determines if the specified post is a revision.
 *
 * @since WP-2.6.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return false|int False if not a revision, ID of revision's parent otherwise.
 */
function wp_is_post_revision( $post ) {
	if ( ! $post = wp_get_post_revision( $post ) ) {
		return false;
	}

	return (int) $post->post_parent;
}

/**
 * Determines if the specified post is an autosave.
 *
 * @since WP-2.6.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return false|int False if not a revision, ID of autosave's parent otherwise
 */
function wp_is_post_autosave( $post ) {
	if ( ! $post = wp_get_post_revision( $post ) ) {
		return false;
	}

	if ( false !== strpos( $post->post_name, "{$post->post_parent}-autosave" ) ) {
		return (int) $post->post_parent;
	}

	return false;
}

/**
 * Inserts post data into the posts table as a post revision.
 *
 * @since WP-2.6.0
 * @access private
 *
 * @param int|WP_Post|array|null $post     Post ID, post object OR post array.
 * @param bool                   $autosave Optional. Is the revision an autosave?
 * @return int|WP_Error WP_Error or 0 if error, new revision ID if success.
 */
function _wp_put_post_revision( $post = null, $autosave = false ) {
	if ( is_object( $post ) ) {
		$post = get_object_vars( $post );
	} elseif ( ! is_array( $post ) ) {
		$post = get_post( $post, ARRAY_A );
	}

	if ( ! $post || empty( $post['ID'] ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
	}

	if ( isset( $post['post_type'] ) && 'revision' == $post['post_type'] ) {
		return new WP_Error( 'post_type', __( 'Cannot create a revision of a revision' ) );
	}

	$post = _wp_post_revision_data( $post, $autosave );
	$post = wp_slash( $post ); //since data is from db

	$revision_id = wp_insert_post( $post );
	if ( is_wp_error( $revision_id ) ) {
		return $revision_id;
	}

	if ( $revision_id ) {
		/**
		 * Fires once a revision has been saved.
		 *
		 * @since WP-2.6.0
		 *
		 * @param int $revision_id Post revision ID.
		 */
		do_action( '_wp_put_post_revision', $revision_id );
	}

	return $revision_id;
}

/**
 * Gets a post revision.
 *
 * @since WP-2.6.0
 *
 * @param int|WP_Post $post   The post ID or object.
 * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                            a WP_Post object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string      $filter Optional sanitation filter. See sanitize_post().
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function wp_get_post_revision( &$post, $output = OBJECT, $filter = 'raw' ) {
	if ( ! $revision = get_post( $post, OBJECT, $filter ) ) {
		return $revision;
	}
	if ( 'revision' !== $revision->post_type ) {
		return null;
	}

	if ( $output == OBJECT ) {
		return $revision;
	} elseif ( $output == ARRAY_A ) {
		$_revision = get_object_vars( $revision );
		return $_revision;
	} elseif ( $output == ARRAY_N ) {
		$_revision = array_values( get_object_vars( $revision ) );
		return $_revision;
	}

	return $revision;
}

/**
 * Restores a post to the specified revision.
 *
 * Can restore a past revision using all fields of the post revision, or only selected fields.
 *
 * @since WP-2.6.0
 *
 * @param int|WP_Post $revision_id Revision ID or revision object.
 * @param array       $fields      Optional. What fields to restore from. Defaults to all.
 * @return int|false|null Null if error, false if no fields to restore, (int) post ID if success.
 */
function wp_restore_post_revision( $revision_id, $fields = null ) {
	if ( ! $revision = wp_get_post_revision( $revision_id, ARRAY_A ) ) {
		return $revision;
	}

	if ( ! is_array( $fields ) ) {
		$fields = array_keys( _wp_post_revision_fields( $revision ) );
	}

	$update = array();
	foreach ( array_intersect( array_keys( $revision ), $fields ) as $field ) {
		$update[ $field ] = $revision[ $field ];
	}

	if ( ! $update ) {
		return false;
	}

	$update['ID'] = $revision['post_parent'];

	$update = wp_slash( $update ); //since data is from db

	$post_id = wp_update_post( $update );
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Update last edit user
	update_post_meta( $post_id, '_edit_last', get_current_user_id() );

	/**
	 * Fires after a post revision has been restored.
	 *
	 * @since WP-2.6.0
	 *
	 * @param int $post_id     Post ID.
	 * @param int $revision_id Post revision ID.
	 */
	do_action( 'wp_restore_post_revision', $post_id, $revision['ID'] );

	return $post_id;
}

/**
 * Deletes a revision.
 *
 * Deletes the row from the posts table corresponding to the specified revision.
 *
 * @since WP-2.6.0
 *
 * @param int|WP_Post $revision_id Revision ID or revision object.
 * @return array|false|WP_Post|WP_Error|null Null or WP_Error if error, deleted post if success.
 */
function wp_delete_post_revision( $revision_id ) {
	if ( ! $revision = wp_get_post_revision( $revision_id ) ) {
		return $revision;
	}

	$delete = wp_delete_post( $revision->ID );
	if ( $delete ) {
		/**
		 * Fires once a post revision has been deleted.
		 *
		 * @since WP-2.6.0
		 *
		 * @param int          $revision_id Post revision ID.
		 * @param object|array $revision    Post revision object or array.
		 */
		do_action( 'wp_delete_post_revision', $revision->ID, $revision );
	}

	return $delete;
}

/**
 * Returns all revisions of specified post.
 *
 * @since WP-2.6.0
 *
 * @see get_children()
 *
 * @param int|WP_Post $post_id Optional. Post ID or WP_Post object. Default is global `$post`.
 * @param array|null  $args    Optional. Arguments for retrieving post revisions. Default null.
 * @return array An array of revisions, or an empty array if none.
 */
function wp_get_post_revisions( $post_id = 0, $args = null ) {
	$post = get_post( $post_id );
	if ( ! $post || empty( $post->ID ) ) {
		return array();
	}

	$defaults = array(
		'order'         => 'DESC',
		'orderby'       => 'date ID',
		'check_enabled' => true,
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( $args['check_enabled'] && ! wp_revisions_enabled( $post ) ) {
		return array();
	}

	$args = array_merge(
		$args,
		array(
			'post_parent' => $post->ID,
			'post_type'   => 'revision',
			'post_status' => 'inherit',
		)
	);

	if ( ! $revisions = get_children( $args ) ) {
		return array();
	}

	return $revisions;
}

/**
 * Determine if revisions are enabled for a given post.
 *
 * @since WP-3.6.0
 *
 * @param WP_Post $post The post object.
 * @return bool True if number of revisions to keep isn't zero, false otherwise.
 */
function wp_revisions_enabled( $post ) {
	return wp_revisions_to_keep( $post ) !== 0;
}

/**
 * Determine how many revisions to retain for a given post.
 *
 * By default, an infinite number of revisions are kept.
 *
 * The constant WP_POST_REVISIONS can be set in wp-config to specify the limit
 * of revisions to keep.
 *
 * @since WP-3.6.0
 *
 * @param WP_Post $post The post object.
 * @return int The number of revisions to keep.
 */
function wp_revisions_to_keep( $post ) {
	$num = WP_POST_REVISIONS;

	if ( true === $num ) {
		$num = -1;
	} else {
		$num = intval( $num );
	}

	if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
		$num = 0;
	}

	/**
	 * Filters the number of revisions to save for the given post.
	 *
	 * Overrides the value of WP_POST_REVISIONS.
	 *
	 * @since WP-3.6.0
	 *
	 * @param int     $num  Number of revisions to store.
	 * @param WP_Post $post Post object.
	 */
	return (int) apply_filters( 'wp_revisions_to_keep', $num, $post );
}

/**
 * Sets up the post object for preview based on the post autosave.
 *
 * @since WP-2.7.0
 * @access private
 *
 * @param WP_Post $post
 * @return WP_Post|false
 */
function _set_preview( $post ) {
	if ( ! is_object( $post ) ) {
		return $post;
	}

	$preview = wp_get_post_autosave( $post->ID );
	if ( ! is_object( $preview ) ) {
		return $post;
	}

	$preview = sanitize_post( $preview );

	$post->post_content = $preview->post_content;
	$post->post_title   = $preview->post_title;
	$post->post_excerpt = $preview->post_excerpt;

	add_filter( 'get_the_terms', '_wp_preview_terms_filter', 10, 3 );
	add_filter( 'get_post_metadata', '_wp_preview_post_thumbnail_filter', 10, 3 );

	return $post;
}

/**
 * Filters the latest content for preview from the post autosave.
 *
 * @since WP-2.7.0
 * @access private
 */
function _show_post_preview() {
	if ( isset( $_GET['preview_id'] ) && isset( $_GET['preview_nonce'] ) ) {
		$id = (int) $_GET['preview_id'];

		if ( false === wp_verify_nonce( $_GET['preview_nonce'], 'post_preview_' . $id ) ) {
			wp_die( __( 'Sorry, you are not allowed to preview drafts.' ) );
		}

		add_filter( 'the_preview', '_set_preview' );
	}
}

/**
 * Filters terms lookup to set the post format.
 *
 * @since WP-3.6.0
 * @access private
 *
 * @param array  $terms
 * @param int    $post_id
 * @param string $taxonomy
 * @return array
 */
function _wp_preview_terms_filter( $terms, $post_id, $taxonomy ) {
	if ( ! $post = get_post() ) {
		return $terms;
	}

	if ( empty( $_REQUEST['post_format'] ) || $post->ID != $post_id || 'post_format' != $taxonomy || 'revision' == $post->post_type ) {
		return $terms;
	}

	if ( 'standard' == $_REQUEST['post_format'] ) {
		$terms = array();
	} elseif ( $term = get_term_by( 'slug', 'post-format-' . sanitize_key( $_REQUEST['post_format'] ), 'post_format' ) ) {
		$terms = array( $term ); // Can only have one post format
	}

	return $terms;
}

/**
 * Filters post thumbnail lookup to set the post thumbnail.
 *
 * @since WP-4.6.0
 * @access private
 *
 * @param null|array|string $value    The value to return - a single metadata value, or an array of values.
 * @param int               $post_id  Post ID.
 * @param string            $meta_key Meta key.
 * @return null|array The default return value or the post thumbnail meta array.
 */
function _wp_preview_post_thumbnail_filter( $value, $post_id, $meta_key ) {
	if ( ! $post = get_post() ) {
		return $value;
	}

	if ( empty( $_REQUEST['_thumbnail_id'] ) ||
		 empty( $_REQUEST['preview_id'] ) ||
		 $post->ID != $post_id ||
		 '_thumbnail_id' != $meta_key ||
		 'revision' == $post->post_type ||
		 $post_id != $_REQUEST['preview_id']
	) {
		return $value;
	}

	$thumbnail_id = intval( $_REQUEST['_thumbnail_id'] );
	if ( $thumbnail_id <= 0 ) {
		return '';
	}

	return strval( $thumbnail_id );
}

/**
 * Gets the post revision version.
 *
 * @since WP-3.6.0
 * @access private
 *
 * @param WP_Post $revision
 * @return int|false
 */
function _wp_get_post_revision_version( $revision ) {
	if ( is_object( $revision ) ) {
		$revision = get_object_vars( $revision );
	} elseif ( ! is_array( $revision ) ) {
		return false;
	}

	if ( preg_match( '/^\d+-(?:autosave|revision)-v(\d+)$/', $revision['post_name'], $matches ) ) {
		return (int) $matches[1];
	}

	return 0;
}

/**
 * Upgrade the revisions author, add the current post as a revision and set the revisions version to 1
 *
 * @since WP-3.6.0
 * @access private
 *
 * @global wpdb $wpdb ClassicPress database abstraction object.
 *
 * @param WP_Post $post      Post object
 * @param array   $revisions Current revisions of the post
 * @return bool true if the revisions were upgraded, false if problems
 */
function _wp_upgrade_revisions_of_post( $post, $revisions ) {
	global $wpdb;

	// Add post option exclusively
	$lock   = "revision-upgrade-{$post->ID}";
	$now    = time();
	$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no') /* LOCK */", $lock, $now ) );
	if ( ! $result ) {
		// If we couldn't get a lock, see how old the previous lock is
		$locked = get_option( $lock );
		if ( ! $locked ) {
			// Can't write to the lock, and can't read the lock.
			// Something broken has happened
			return false;
		}

		if ( $locked > $now - 3600 ) {
			// Lock is not too old: some other process may be upgrading this post.  Bail.
			return false;
		}

		// Lock is too old - update it (below) and continue
	}

	// If we could get a lock, re-"add" the option to fire all the correct filters.
	update_option( $lock, $now );

	reset( $revisions );
	$add_last = true;

	do {
		$this_revision = current( $revisions );
		$prev_revision = next( $revisions );

		$this_revision_version = _wp_get_post_revision_version( $this_revision );

		// Something terrible happened
		if ( false === $this_revision_version ) {
			continue;
		}

		// 1 is the latest revision version, so we're already up to date.
		// No need to add a copy of the post as latest revision.
		if ( 0 < $this_revision_version ) {
			$add_last = false;
			continue;
		}

		// Always update the revision version
		$update = array(
			'post_name' => preg_replace( '/^(\d+-(?:autosave|revision))[\d-]*$/', '$1-v1', $this_revision->post_name ),
		);

		// If this revision is the oldest revision of the post, i.e. no $prev_revision,
		// the correct post_author is probably $post->post_author, but that's only a good guess.
		// Update the revision version only and Leave the author as-is.
		if ( $prev_revision ) {
			$prev_revision_version = _wp_get_post_revision_version( $prev_revision );

			// If the previous revision is already up to date, it no longer has the information we need :(
			if ( $prev_revision_version < 1 ) {
				$update['post_author'] = $prev_revision->post_author;
			}
		}

		// Upgrade this revision
		$result = $wpdb->update( $wpdb->posts, $update, array( 'ID' => $this_revision->ID ) );

		if ( $result ) {
			wp_cache_delete( $this_revision->ID, 'posts' );
		}
	} while ( $prev_revision );

	delete_option( $lock );

	// Add a copy of the post as latest revision.
	if ( $add_last ) {
		wp_save_post_revision( $post->ID );
	}

	return true;
}

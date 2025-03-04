<?php
/**
 * ClassicPress Theme Administration API
 *
 * @package ClassicPress
 * @subpackage Administration
 */

/**
 * Remove a theme
 *
 * @since WP-2.8.0
 *
 * @global WP_Filesystem_Base $wp_filesystem Subclass
 *
 * @param string $stylesheet Stylesheet of the theme to delete
 * @param string $redirect Redirect to page when complete.
 * @return void|bool|WP_Error When void, echoes content.
 */
function delete_theme( $stylesheet, $redirect = '' ) {
	global $wp_filesystem;

	if ( empty( $stylesheet ) ) {
		return false;
	}

	if ( empty( $redirect ) ) {
		$redirect = wp_nonce_url( 'themes.php?action=delete&stylesheet=' . urlencode( $stylesheet ), 'delete-theme_' . $stylesheet );
	}

	ob_start();
	$credentials = request_filesystem_credentials( $redirect );
	$data        = ob_get_clean();

	if ( false === $credentials ) {
		if ( ! empty( $data ) ) {
			include_once ABSPATH . 'wp-admin/admin-header.php';
			echo $data;
			include ABSPATH . 'wp-admin/admin-footer.php';
			exit;
		}
		return;
	}

	if ( ! WP_Filesystem( $credentials ) ) {
		ob_start();
		request_filesystem_credentials( $redirect, '', true ); // Failed to connect, Error and request again.
		$data = ob_get_clean();

		if ( ! empty( $data ) ) {
			include_once ABSPATH . 'wp-admin/admin-header.php';
			echo $data;
			include ABSPATH . 'wp-admin/admin-footer.php';
			exit;
		}
		return;
	}

	if ( ! is_object( $wp_filesystem ) ) {
		return new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.' ) );
	}

	if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
		return new WP_Error( 'fs_error', __( 'Filesystem error.' ), $wp_filesystem->errors );
	}

	// Get the base plugin folder.
	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) ) {
		return new WP_Error( 'fs_no_themes_dir', __( 'Unable to locate ClassicPress theme directory.' ) );
	}

	$themes_dir = trailingslashit( $themes_dir );
	$theme_dir  = trailingslashit( $themes_dir . $stylesheet );
	$deleted    = $wp_filesystem->delete( $theme_dir, true );

	if ( ! $deleted ) {
		return new WP_Error( 'could_not_remove_theme', sprintf( __( 'Could not fully remove the theme %s.' ), $stylesheet ) );
	}

	$theme_translations = wp_get_installed_translations( 'themes' );

	// Remove language files, silently.
	if ( ! empty( $theme_translations[ $stylesheet ] ) ) {
		$translations = $theme_translations[ $stylesheet ];

		foreach ( $translations as $translation => $data ) {
			$wp_filesystem->delete( WP_LANG_DIR . '/themes/' . $stylesheet . '-' . $translation . '.po' );
			$wp_filesystem->delete( WP_LANG_DIR . '/themes/' . $stylesheet . '-' . $translation . '.mo' );
		}
	}

	// Remove the theme from allowed themes on the network.
	if ( is_multisite() ) {
		WP_Theme::network_disable_theme( $stylesheet );
	}

	// Force refresh of theme update information.
	delete_site_transient( 'update_themes' );

	return true;
}

/**
 * Get the Page Templates available in this theme
 *
 * @since WP-1.5.0
 * @since WP-4.7.0 Added the `$post_type` parameter.
 *
 * @param WP_Post|null $post      Optional. The post being edited, provided for context.
 * @param string       $post_type Optional. Post type to get the templates for. Default 'page'.
 * @return array Key is the template name, value is the filename of the template
 */
function get_page_templates( $post = null, $post_type = 'page' ) {
	return array_flip( wp_get_theme()->get_page_templates( $post, $post_type ) );
}

/**
 * Tidies a filename for url display by the theme editor.
 *
 * @since WP-2.9.0
 * @access private
 *
 * @param string $fullpath Full path to the theme file
 * @param string $containingfolder Path of the theme parent folder
 * @return string
 */
function _get_template_edit_filename( $fullpath, $containingfolder ) {
	return str_replace( dirname( dirname( $containingfolder ) ), '', $fullpath );
}

/**
 * Check if there is an update for a theme available.
 *
 * Will display link, if there is an update available.
 *
 * @since WP-2.7.0
 * @see get_theme_update_available()
 *
 * @param WP_Theme $theme Theme data object.
 */
function theme_update_available( $theme ) {
	echo get_theme_update_available( $theme );
}

/**
 * Retrieve the update link if there is a theme update available.
 *
 * Will return a link if there is an update available.
 *
 * @since WP-3.8.0
 *
 * @staticvar object $themes_update
 *
 * @param WP_Theme $theme WP_Theme object.
 * @return false|string HTML for the update link, or false if invalid info was passed.
 */
function get_theme_update_available( $theme ) {
	static $themes_update = null;

	if ( ! current_user_can( 'update_themes' ) ) {
		return false;
	}

	if ( ! isset( $themes_update ) ) {
		$themes_update = get_site_transient( 'update_themes' );
	}

	if ( ! ( $theme instanceof WP_Theme ) ) {
		return false;
	}

	$stylesheet = $theme->get_stylesheet();

	$html = '';

	if ( isset( $themes_update->response[ $stylesheet ] ) ) {
		$update      = $themes_update->response[ $stylesheet ];
		$theme_name  = $theme->display( 'Name' );
		$details_url = add_query_arg(
			array(
				'TB_iframe' => 'true',
				'width'     => 1024,
				'height'    => 800,
			),
			$update['url']
		); //Theme browser inside WP? replace this, Also, theme preview JS will override this on the available list.
		$update_url  = wp_nonce_url( admin_url( 'update.php?action=upgrade-theme&amp;theme=' . urlencode( $stylesheet ) ), 'upgrade-theme_' . $stylesheet );

		if ( ! is_multisite() ) {
			if ( ! current_user_can( 'update_themes' ) ) {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number */
				$html = sprintf(
					'<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version']
				);
			} elseif ( empty( $update['package'] ) ) {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number */
				$html = sprintf(
					'<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this theme.</em>' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version']
				);
			} else {
				/* translators: 1: theme name, 2: theme details URL, 3: additional link attributes, 4: version number, 5: update URL, 6: additional link attributes */
				$html = sprintf(
					'<p><strong>' . __( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s" %6$s>update now</a>.' ) . '</strong></p>',
					$theme_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: theme name, 2: version number */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $theme_name, $update['new_version'] ) )
					),
					$update['new_version'],
					$update_url,
					sprintf(
						'aria-label="%s" id="update-theme" data-slug="%s"',
						/* translators: %s: theme name */
						esc_attr( sprintf( __( 'Update %s now' ), $theme_name ) ),
						$stylesheet
					)
				);
			}
		}
	}

	return $html;
}

/**
 * Retrieve list of ClassicPress theme features (aka theme tags)
 *
 * @since WP-3.1.0
 *
 * @param bool $api Optional. Whether try to fetch tags from the ClassicPress.net API. Defaults to true.
 * @return array Array of features keyed by category with translations keyed by slug.
 */
function get_theme_feature_list( $api = true ) {
	// Hard-coded list is used if api not accessible.
	$features = array(

		__( 'Subject' )  => array(
			'blog'           => __( 'Blog' ),
			'e-commerce'     => __( 'E-Commerce' ),
			'education'      => __( 'Education' ),
			'entertainment'  => __( 'Entertainment' ),
			'food-and-drink' => __( 'Food & Drink' ),
			'holiday'        => __( 'Holiday' ),
			'news'           => __( 'News' ),
			'photography'    => __( 'Photography' ),
			'portfolio'      => __( 'Portfolio' ),
		),

		__( 'Features' ) => array(
			'accessibility-ready'   => __( 'Accessibility Ready' ),
			'custom-background'     => __( 'Custom Background' ),
			'custom-colors'         => __( 'Custom Colors' ),
			'custom-header'         => __( 'Custom Header' ),
			'custom-logo'           => __( 'Custom Logo' ),
			'editor-style'          => __( 'Editor Style' ),
			'featured-image-header' => __( 'Featured Image Header' ),
			'featured-images'       => __( 'Featured Images' ),
			'footer-widgets'        => __( 'Footer Widgets' ),
			'full-width-template'   => __( 'Full Width Template' ),
			'post-formats'          => __( 'Post Formats' ),
			'sticky-post'           => __( 'Sticky Post' ),
			'theme-options'         => __( 'Theme Options' ),
		),

		__( 'Layout' )   => array(
			'grid-layout'   => __( 'Grid Layout' ),
			'one-column'    => __( 'One Column' ),
			'two-columns'   => __( 'Two Columns' ),
			'three-columns' => __( 'Three Columns' ),
			'four-columns'  => __( 'Four Columns' ),
			'left-sidebar'  => __( 'Left Sidebar' ),
			'right-sidebar' => __( 'Right Sidebar' ),
		),

	);

	if ( ! $api || ! current_user_can( 'install_themes' ) ) {
		return $features;
	}

	if ( ! $feature_list = get_site_transient( 'wporg_theme_feature_list' ) ) {
		set_site_transient( 'wporg_theme_feature_list', array(), 3 * HOUR_IN_SECONDS );
	}

	if ( ! $feature_list ) {
		$feature_list = themes_api( 'feature_list', array() );
		if ( is_wp_error( $feature_list ) ) {
			return $features;
		}
	}

	if ( ! $feature_list ) {
		return $features;
	}

	set_site_transient( 'wporg_theme_feature_list', $feature_list, 3 * HOUR_IN_SECONDS );

	$category_translations = array(
		'Layout'   => __( 'Layout' ),
		'Features' => __( 'Features' ),
		'Subject'  => __( 'Subject' ),
	);

	// Loop over the wporg canonical list and apply translations
	$wporg_features = array();
	foreach ( (array) $feature_list as $feature_category => $feature_items ) {
		if ( isset( $category_translations[ $feature_category ] ) ) {
			$feature_category = $category_translations[ $feature_category ];
		}
		$wporg_features[ $feature_category ] = array();

		foreach ( $feature_items as $feature ) {
			if ( isset( $features[ $feature_category ][ $feature ] ) ) {
				$wporg_features[ $feature_category ][ $feature ] = $features[ $feature_category ][ $feature ];
			} else {
				$wporg_features[ $feature_category ][ $feature ] = $feature;
			}
		}
	}

	return $wporg_features;
}

/**
 * Retrieves theme installer pages from the ClassicPress.net Themes API.
 *
 * It is possible for a theme to override the Themes API result with three
 * filters. Assume this is for themes, which can extend on the Theme Info to
 * offer more choices. This is very powerful and must be used with care, when
 * overriding the filters.
 *
 * The first filter, {@see 'themes_api_args'}, is for the args and gives the action
 * as the second parameter. The hook for {@see 'themes_api_args'} must ensure that
 * an object is returned.
 *
 * The second filter, {@see 'themes_api'}, allows a plugin to override the ClassicPress.net
 * Theme API entirely. If `$action` is 'query_themes', 'theme_information', or 'feature_list',
 * an object MUST be passed. If `$action` is 'hot_tags', an array should be passed.
 *
 * Finally, the third filter, {@see 'themes_api_result'}, makes it possible to filter the
 * response object or array, depending on the `$action` type.
 *
 * Supported arguments per action:
 *
 * | Argument Name      | 'query_themes' | 'theme_information' | 'hot_tags' | 'feature_list'   |
 * | -------------------| :------------: | :-----------------: | :--------: | :--------------: |
 * | `$slug`            | No             |  Yes                | No         | No               |
 * | `$per_page`        | Yes            |  No                 | No         | No               |
 * | `$page`            | Yes            |  No                 | No         | No               |
 * | `$number`          | No             |  No                 | Yes        | No               |
 * | `$search`          | Yes            |  No                 | No         | No               |
 * | `$tag`             | Yes            |  No                 | No         | No               |
 * | `$author`          | Yes            |  No                 | No         | No               |
 * | `$user`            | Yes            |  No                 | No         | No               |
 * | `$browse`          | Yes            |  No                 | No         | No               |
 * | `$locale`          | Yes            |  Yes                | No         | No               |
 * | `$fields`          | Yes            |  Yes                | No         | No               |
 *
 * @since WP-2.8.0
 *
 * @param string       $action API action to perform: 'query_themes', 'theme_information',
 *                             'hot_tags' or 'feature_list'.
 * @param array|object $args   {
 *     Optional. Array or object of arguments to serialize for the Themes API.
 *
 *     @type string  $slug     The theme slug. Default empty.
 *     @type int     $per_page Number of themes per page. Default 24.
 *     @type int     $page     Number of current page. Default 1.
 *     @type int     $number   Number of tags to be queried.
 *     @type string  $search   A search term. Default empty.
 *     @type string  $tag      Tag to filter themes. Default empty.
 *     @type string  $author   Username of an author to filter themes. Default empty.
 *     @type string  $user     Username to query for their favorites. Default empty.
 *     @type string  $browse   Browse view: 'featured', 'popular', 'updated', 'favorites'.
 *     @type string  $locale   Locale to provide context-sensitive results. Default is the value of get_locale().
 *     @type array   $fields   {
 *         Array of fields which should or should not be returned.
 *
 *         @type bool $description        Whether to return the theme full description. Default false.
 *         @type bool $sections           Whether to return the theme readme sections: description, installation,
 *                                        FAQ, screenshots, other notes, and changelog. Default false.
 *         @type bool $rating             Whether to return the rating in percent and total number of ratings.
 *                                        Default false.
 *         @type bool $ratings            Whether to return the number of rating for each star (1-5). Default false.
 *         @type bool $downloaded         Whether to return the download count. Default false.
 *         @type bool $downloadlink       Whether to return the download link for the package. Default false.
 *         @type bool $last_updated       Whether to return the date of the last update. Default false.
 *         @type bool $tags               Whether to return the assigned tags. Default false.
 *         @type bool $homepage           Whether to return the theme homepage link. Default false.
 *         @type bool $screenshots        Whether to return the screenshots. Default false.
 *         @type int  $screenshot_count   Number of screenshots to return. Default 1.
 *         @type bool $screenshot_url     Whether to return the URL of the first screenshot. Default false.
 *         @type bool $photon_screenshots Whether to return the screenshots via Photon. Default false.
 *         @type bool $template           Whether to return the slug of the parent theme. Default false.
 *         @type bool $parent             Whether to return the slug, name and homepage of the parent theme. Default false.
 *         @type bool $versions           Whether to return the list of all available versions. Default false.
 *         @type bool $theme_url          Whether to return theme's URL. Default false.
 *         @type bool $extended_author    Whether to return nicename or nicename and display name. Default false.
 *     }
 * }
 * @return object|array|WP_Error Response object or array on success, WP_Error on failure. See the
 *         {@link https://developer.wordpress.org/reference/functions/themes_api/ function reference article}
 *         for more information on the make-up of possible return objects depending on the value of `$action`.
 */
function themes_api( $action, $args = array() ) {

	if ( is_array( $args ) ) {
		$args = (object) $args;
	}

	if ( ! isset( $args->per_page ) ) {
		$args->per_page = 24;
	}

	if ( ! isset( $args->locale ) ) {
		$args->locale = get_user_locale();
	}

	/**
	 * Filters arguments used to query for installer pages from the ClassicPress.net Themes API.
	 *
	 * Important: An object MUST be returned to this filter.
	 *
	 * @since WP-2.8.0
	 *
	 * @param object $args   Arguments used to query for installer pages from the ClassicPress.net Themes API.
	 * @param string $action Requested action. Likely values are 'theme_information',
	 *                       'feature_list', or 'query_themes'.
	 */
	$args = apply_filters( 'themes_api_args', $args, $action );

	/**
	 * Filters whether to override the ClassicPress.net Themes API.
	 *
	 * Passing a non-false value will effectively short-circuit the ClassicPress.net API request.
	 *
	 * If `$action` is 'query_themes', 'theme_information', or 'feature_list', an object MUST
	 * be passed. If `$action` is 'hot_tags', an array should be passed.
	 *
	 * @since WP-2.8.0
	 *
	 * @param false|object|array $override Whether to override the ClassicPress.net Themes API. Default false.
	 * @param string             $action   Requested action. Likely values are 'theme_information',
	 *                                    'feature_list', or 'query_themes'.
	 * @param object             $args     Arguments used to query for installer pages from the Themes API.
	 */
	$res = apply_filters( 'themes_api', false, $action, $args );

	if ( ! $res ) {
		// include an unmodified $wp_version
		include ABSPATH . WPINC . '/version.php';

		$url = 'https://api.wordpress.org/themes/info/1.0/';

		$http_args = array(
			'user-agent' => classicpress_user_agent(),
			'body'       => array(
				'action'  => $action,
				'request' => serialize( $args ),
			),
		);
		$request   = wp_remote_post( $url, $http_args );

		if ( is_wp_error( $request ) ) {
			if ( ! wp_doing_ajax() ) {
				trigger_error(
					sprintf(
						/* translators: %s: support forums URL */
						__( 'An unexpected error occurred. Something may be wrong with ClassicPress.net or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.' ),
						__( 'https://forums.classicpress.net/c/support' )
					) . ' ' . __( '(ClassicPress could not establish a secure connection to ClassicPress.net. Please contact your server administrator.)' ),
					headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE
				);
			}

			// Retry request
			$request = wp_remote_post( $url, $http_args );
		}

		if ( is_wp_error( $request ) ) {
			$res = new WP_Error(
				'themes_api_failed',
				sprintf(
					/* translators: %s: support forums URL */
					__( 'An unexpected error occurred. Something may be wrong with ClassicPress.net or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.' ),
					__( 'https://forums.classicpress.net/c/support' )
				),
				$request->get_error_message()
			);
		} else {
			$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );
			if ( ! is_object( $res ) && ! is_array( $res ) ) {
				$res = new WP_Error(
					'themes_api_failed',
					sprintf(
						/* translators: %s: support forums URL */
						__( 'An unexpected error occurred. Something may be wrong with ClassicPress.net or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.' ),
						__( 'https://forums.classicpress.net/c/support' )
					),
					wp_remote_retrieve_body( $request )
				);
			}
		}
	}

	/**
	 * Filters the returned ClassicPress.net Themes API response.
	 *
	 * @since WP-2.8.0
	 *
	 * @param array|object|WP_Error $res    ClassicPress.net Themes API response.
	 * @param string                $action Requested action. Likely values are 'theme_information',
	 *                                      'feature_list', or 'query_themes'.
	 * @param object                $args   Arguments used to query for installer pages from the ClassicPress.net Themes API.
	 */
	return apply_filters( 'themes_api_result', $res, $action, $args );
}

/**
 * Prepare themes for JavaScript.
 *
 * @since WP-3.8.0
 *
 * @param array $themes Optional. Array of WP_Theme objects to prepare.
 *                      Defaults to all allowed themes.
 *
 * @return array An associative array of theme data, sorted by name.
 */
function wp_prepare_themes_for_js( $themes = null ) {
	$current_theme = get_stylesheet();

	/**
	 * Filters theme data before it is prepared for JavaScript.
	 *
	 * Passing a non-empty array will result in wp_prepare_themes_for_js() returning
	 * early with that value instead.
	 *
	 * @since WP-4.2.0
	 *
	 * @param array      $prepared_themes An associative array of theme data. Default empty array.
	 * @param null|array $themes          An array of WP_Theme objects to prepare, if any.
	 * @param string     $current_theme   The current theme slug.
	 */
	$prepared_themes = (array) apply_filters( 'pre_prepare_themes_for_js', array(), $themes, $current_theme );

	if ( ! empty( $prepared_themes ) ) {
		return $prepared_themes;
	}

	// Make sure the current theme is listed first.
	$prepared_themes[ $current_theme ] = array();

	if ( null === $themes ) {
		$themes = wp_get_themes( array( 'allowed' => true ) );
		if ( ! isset( $themes[ $current_theme ] ) ) {
			$themes[ $current_theme ] = wp_get_theme();
		}
	}

	$updates = array();
	if ( current_user_can( 'update_themes' ) ) {
		$updates_transient = get_site_transient( 'update_themes' );
		if ( isset( $updates_transient->response ) ) {
			$updates = $updates_transient->response;
		}
	}

	WP_Theme::sort_by_name( $themes );

	$parents = array();

	foreach ( $themes as $theme ) {
		$slug         = $theme->get_stylesheet();
		$encoded_slug = urlencode( $slug );

		$parent = false;
		if ( $theme->parent() ) {
			$parent           = $theme->parent();
			$parents[ $slug ] = $parent->get_stylesheet();
			$parent           = $parent->display( 'Name' );
		}

		$customize_action = null;
		if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
			$customize_action = esc_url(
				add_query_arg(
					array(
						'return' => urlencode( esc_url_raw( remove_query_arg( wp_removable_query_args(), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ),
					),
					wp_customize_url( $slug )
				)
			);
		}

		$update_requires_wp  = isset( $updates[ $slug ]['requires'] ) ? $updates[ $slug ]['requires'] : null;
		$update_requires_php = isset( $updates[ $slug ]['requires_php'] ) ? $updates[ $slug ]['requires_php'] : null;

		$prepared_themes[ $slug ] = array(
			'id'             => $slug,
			'name'           => $theme->display( 'Name' ),
			'screenshot'     => array( $theme->get_screenshot() ), // @todo Multiple screenshots.
			'description'    => $theme->display( 'Description' ),
			'author'         => $theme->display( 'Author', false, true ),
			'authorAndUri'   => $theme->display( 'Author' ),
			'tags'           => $theme->display( 'Tags' ),
			'version'        => $theme->get( 'Version' ),
			'compatibleWP'   => is_wp_version_compatible( $theme->get( 'RequiresWP' ) ),
			'compatiblePHP'  => is_php_version_compatible( $theme->get( 'RequiresPHP' ) ),
			'updateResponse' => array(
				'compatibleWP'  => is_wp_version_compatible( $update_requires_wp ),
				'compatiblePHP' => is_php_version_compatible( $update_requires_php ),
			),
			'parent'         => $parent,
			'active'         => $slug === $current_theme,
			'hasUpdate'      => isset( $updates[ $slug ] ),
			'hasPackage'     => isset( $updates[ $slug ] ) && ! empty( $updates[ $slug ]['package'] ),
			'update'         => get_theme_update_available( $theme ),
			'actions'        => array(
				'activate'  => current_user_can( 'switch_themes' ) ? wp_nonce_url( admin_url( 'themes.php?action=activate&amp;stylesheet=' . $encoded_slug ), 'switch-theme_' . $slug ) : null,
				'customize' => $customize_action,
				'delete'    => current_user_can( 'delete_themes' ) ? wp_nonce_url( admin_url( 'themes.php?action=delete&amp;stylesheet=' . $encoded_slug ), 'delete-theme_' . $slug ) : null,
			),
		);
	}

	// Remove 'delete' action if theme has an active child
	if ( ! empty( $parents ) && array_key_exists( $current_theme, $parents ) ) {
		unset( $prepared_themes[ $parents[ $current_theme ] ]['actions']['delete'] );
	}

	// Indicate a preferred ClassicPress child theme for the WP parent themes
	$wp_theme_slugs = array( 'twentyfifteen', 'twentysixteen', 'twentyseventeen' );
	foreach ( $wp_theme_slugs as $slug ) {
		if (
			isset( $prepared_themes[ $slug ] ) &&
			isset( $prepared_themes[ "classicpress-$slug" ] )
		) {
			$prepared_themes[ $slug ]['preferredChildName'] =
				$prepared_themes[ "classicpress-$slug" ]['name'];
		}
	}

	/**
	 * Filters the themes prepared for JavaScript, for themes.php.
	 *
	 * Could be useful for changing the order, which is by name by default.
	 *
	 * @since WP-3.8.0
	 *
	 * @param array $prepared_themes Array of themes.
	 */
	$prepared_themes = apply_filters( 'wp_prepare_themes_for_js', $prepared_themes );
	$prepared_themes = array_values( $prepared_themes );
	return array_filter( $prepared_themes );
}

/**
 * Print JS templates for the theme-browsing UI in the Customizer.
 *
 * @since WP-4.2.0
 */
function customize_themes_print_templates() {
	?>
	<script type="text/html" id="tmpl-customize-themes-details-view">
		<div class="theme-backdrop"></div>
		<div class="theme-wrap wp-clearfix" role="document">
			<div class="theme-header">
				<button type="button" class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
				<button type="button" class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
				<button type="button" class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close details dialog' ); ?></span></button>
			</div>
			<div class="theme-about wp-clearfix">
				<div class="theme-screenshots">
				<# if ( data.screenshot && data.screenshot[0] ) { #>
					<div class="screenshot"><img src="{{ data.screenshot[0] }}" alt="" /></div>
				<# } else { #>
					<div class="screenshot blank"></div>
				<# } #>
				</div>

				<div class="theme-info">
					<# if ( data.active ) { #>
						<span class="current-label"><?php _e( 'Current Theme' ); ?></span>
					<# } #>
					<h2 class="theme-name">{{{ data.name }}}<span class="theme-version"><?php printf( __( 'Version: %s' ), '{{ data.version }}' ); ?></span></h2>
					<h3 class="theme-author"><?php printf( __( 'By %s' ), '{{{ data.authorAndUri }}}' ); ?></h3>

					<# if ( data.stars && 0 != data.num_ratings ) { #>
						<div class="theme-rating">
							{{{ data.stars }}}
							<span class="num-ratings">
								<?php
								/* translators: %s: number of ratings */
								echo sprintf( __( '(%s ratings)' ), '{{ data.num_ratings }}' );
								?>
							</span>
						</div>
					<# } #>

					<# if ( data.hasUpdate ) { #>
						<# if ( data.updateResponse.compatibleWP && data.updateResponse.compatiblePHP ) { #>
							<div class="notice notice-warning notice-alt notice-large" data-slug="{{ data.id }}">
								<h3 class="notice-title"><?php _e( 'Update Available' ); ?></h3>
								{{{ data.update }}}
							</div>
						<# } else { #>
							<div class="notice notice-error notice-alt notice-large" data-slug="{{ data.id }}">
								<h3 class="notice-title"><?php _e( 'Update Incompatible' ); ?></h3>
								<p>
									<# if ( ! data.updateResponse.compatibleWP && ! data.updateResponse.compatiblePHP ) { #>
										<?php
										_e( 'There is a new version available, but it doesn&#8217;t work with your version of PHP or support ClassicPress.' );
										if ( current_user_can( 'update_php' ) ) {
											printf(
												/* translators: %s: URL to Update PHP page. */
												' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
												esc_url( wp_get_update_php_url() )
											);
											wp_update_php_annotation( '</p><p><em>', '</em>' );
										}
										?>
									<# } else if ( ! data.updateResponse.compatibleWP ) { #>
										<?php
										_e( 'There is a new version available, but it doesn&#8217;t support ClassicPress.' );
										?>
									<# } else if ( ! data.updateResponse.compatiblePHP ) { #>
										<?php
										_e( 'There is a new version available, but it doesn&#8217;t work with your version of PHP.' );
										if ( current_user_can( 'update_php' ) ) {
											printf(
												/* translators: %s: URL to Update PHP page. */
												' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
												esc_url( wp_get_update_php_url() )
											);
											wp_update_php_annotation( '</p><p><em>', '</em>' );
										}
										?>
									<# } #>
								</p>
							</div>
						<# } #>
					<# } #>

					<# if ( data.parent ) { #>
						<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), '<strong>{{{ data.parent }}}</strong>' ); ?></p>
					<# } #>

					<p class="theme-description">{{{ data.description }}}</p>

					<# if ( data.tags ) { #>
						<p class="theme-tags"><span><?php _e( 'Tags:' ); ?></span> {{{ data.tags }}}</p>
					<# } #>
				</div>
			</div>

			<div class="theme-actions">
				<# if ( data.active ) { #>
					<button type="button" class="button button-primary customize-theme"><?php _e( 'Customize' ); ?></button>
				<# } else if ( 'installed' === data.type ) { #>
					<?php if ( current_user_can( 'delete_themes' ) ) { ?>
						<# if ( data.actions && data.actions['delete'] ) { #>
							<a href="{{{ data.actions['delete'] }}}" data-slug="{{ data.id }}" class="button button-secondary delete-theme"><?php _e( 'Delete' ); ?></a>
						<# } #>
					<?php } ?>

					<# if ( data.compatibleWP && data.compatiblePHP ) { #>
						<button type="button" class="button button-primary preview-theme" data-slug="{{ data.id }}"><?php _e( 'Live Preview' ); ?></button>
					<# } else { #>
						<button class="button button-primary disabled"><?php _e( 'Live Preview' ); ?></button>
					<# } #>
				<# } else { #>
					<# if ( data.compatibleWP && data.compatiblePHP ) { #>
						<button type="button" class="button theme-install" data-slug="{{ data.id }}"><?php _e( 'Install' ); ?></button>
						<button type="button" class="button button-primary theme-install preview" data-slug="{{ data.id }}"><?php _e( 'Install &amp; Preview' ); ?></button>
					<# } else { #>
						<button type="button" class="button disabled"><?php _ex( 'Cannot Install', 'theme' ); ?></button>
						<button type="button" class="button button-primary disabled"><?php _e( 'Install &amp; Preview' ); ?></button>
					<# } #>
				<# } #>
			</div>
		</div>
	</script>
	<?php
}

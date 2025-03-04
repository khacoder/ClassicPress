<?php
/**
 * Toolbar API: Top-level Toolbar functionality
 *
 * @package ClassicPress
 * @subpackage Toolbar
 * @since WP-3.1.0
 */

/**
 * Instantiate the admin bar object and set it up as a global for access elsewhere.
 *
 * UNHOOKING THIS FUNCTION WILL NOT PROPERLY REMOVE THE ADMIN BAR.
 * For that, use show_admin_bar(false) or the {@see 'show_admin_bar'} filter.
 *
 * @since WP-3.1.0
 * @access private
 *
 * @global WP_Admin_Bar $wp_admin_bar
 *
 * @return bool Whether the admin bar was successfully initialized.
 */
function _wp_admin_bar_init() {
	global $wp_admin_bar;

	if ( ! is_admin_bar_showing() ) {
		return false;
	}

	/* Load the admin bar class code ready for instantiation */
	require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

	/* Instantiate the admin bar */

	/**
	 * Filters the admin bar class to instantiate.
	 *
	 * @since WP-3.1.0
	 *
	 * @param string $wp_admin_bar_class Admin bar class to use. Default 'WP_Admin_Bar'.
	 */
	$admin_bar_class = apply_filters( 'wp_admin_bar_class', 'WP_Admin_Bar' );
	if ( class_exists( $admin_bar_class ) ) {
		$wp_admin_bar = new $admin_bar_class;
	} else {
		return false;
	}

	$wp_admin_bar->initialize();
	$wp_admin_bar->add_menus();

	return true;
}

/**
 * Renders the admin bar to the page based on the $wp_admin_bar->menu member var.
 *
 * This is called very late on the footer actions so that it will render after
 * anything else being added to the footer.
 *
 * It includes the {@see 'admin_bar_menu'} action which should be used to hook in and
 * add new menus to the admin bar. That way you can be sure that you are adding at most
 * optimal point, right before the admin bar is rendered. This also gives you access to
 * the `$post` global, among others.
 *
 * @since WP-3.1.0
 *
 * @global WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_render() {
	global $wp_admin_bar;

	if ( ! is_admin_bar_showing() || ! is_object( $wp_admin_bar ) ) {
		return;
	}

	/**
	 * Load all necessary admin bar items.
	 *
	 * This is the hook used to add, remove, or manipulate admin bar items.
	 *
	 * @since WP-3.1.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference
	 */
	do_action_ref_array( 'admin_bar_menu', array( &$wp_admin_bar ) );

	/**
	 * Fires before the admin bar is rendered.
	 *
	 * @since WP-3.1.0
	 */
	do_action( 'wp_before_admin_bar_render' );

	$wp_admin_bar->render();

	/**
	 * Fires after the admin bar is rendered.
	 *
	 * @since WP-3.1.0
	 */
	do_action( 'wp_after_admin_bar_render' );
}

/**
 * Add the ClassicPress logo menu.
 *
 * @since WP-3.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_wp_menu( $wp_admin_bar ) {
	if ( current_user_can( 'read' ) ) {
		$about_url = self_admin_url( 'about.php' );
	} elseif ( is_multisite() ) {
		$about_url = get_dashboard_url( get_current_user_id(), 'about.php' );
	} else {
		$about_url = false;
	}

	$cp_logo_src       = includes_url( 'images/classicpress-logo-dashicon-grey-on-transparent.svg' );
	$wp_logo_menu_args = array(
		'id'    => 'wp-logo',
		'title' => (
			'<img class="cp-logo" src="' . $cp_logo_src . '" alt="ClassicPress logo" />'
			. '<span class="screen-reader-text">' . __( 'About ClassicPress' ) . '</span>'
		),
		'href'  => $about_url,
	);

	// Set tabindex="0" to make sub menus accessible when no URL is available.
	if ( ! $about_url ) {
		$wp_logo_menu_args['meta'] = array(
			'tabindex' => 0,
		);
	}

	$wp_admin_bar->add_menu( $wp_logo_menu_args );

	if ( $about_url ) {
		// Add "About ClassicPress" link
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'wp-logo',
				'id'     => 'about',
				'title'  => __( 'About ClassicPress' ),
				'href'   => $about_url,
			)
		);
	}

	// Add ClassicPress.net link
	$wp_admin_bar->add_menu(
		array(
			'parent' => 'wp-logo-external',
			'id'     => 'wporg',
			'title'  => __( 'ClassicPress.net' ),
			'href'   => 'https://www.classicpress.net',
		)
	);

	// Add documentation link
	$wp_admin_bar->add_menu(
		array(
			'parent' => 'wp-logo-external',
			'id'     => 'documentation',
			'title'  => __( 'Documentation' ),
			'href'   => __( 'https://docs.classicpress.net/' ),
		)
	);

	// Add support link
	$wp_admin_bar->add_menu(
		array(
			'parent' => 'wp-logo-external',
			'id'     => 'support',
			'title'  => __( 'Support' ),
			'href'   => __( 'https://docs.classicpress.net/faq-support/' ),
		)
	);
}

/**
 * Add the sidebar toggle button.
 *
 * @since WP-3.8.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_sidebar_toggle( $wp_admin_bar ) {
	if ( is_admin() ) {
		$wp_admin_bar->add_menu(
			array(
				'id'    => 'menu-toggle',
				'title' => '<span class="ab-icon"></span><span class="screen-reader-text">' . __( 'Menu' ) . '</span>',
				'href'  => '#',
			)
		);
	}
}

/**
 * Add the "My Account" item.
 *
 * @since WP-3.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_my_account_item( $wp_admin_bar ) {
	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	if ( ! $user_id ) {
		return;
	}

	if ( current_user_can( 'read' ) ) {
		$profile_url = get_edit_profile_url( $user_id );
	} elseif ( is_multisite() ) {
		$profile_url = get_dashboard_url( $user_id, 'profile.php' );
	} else {
		$profile_url = false;
	}

	$avatar       = get_avatar( $user_id, 26 );
	$display_name = '<span class="display-name">' . $current_user->display_name . '</span>';
	$class        = empty( $avatar ) ? '' : 'with-avatar';

	$wp_admin_bar->add_menu(
		array(
			'id'     => 'my-account',
			'parent' => 'top-secondary',
			'title'  => $display_name . $avatar,
			'href'   => $profile_url,
			'meta'   => array(
				'class' => $class,
			),
		)
	);
}

/**
 * Add the "My Account" submenu items.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_my_account_menu( $wp_admin_bar ) {
	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	if ( ! $user_id ) {
		return;
	}

	if ( current_user_can( 'read' ) ) {
		$profile_url = get_edit_profile_url( $user_id );
	} elseif ( is_multisite() ) {
		$profile_url = get_dashboard_url( $user_id, 'profile.php' );
	} else {
		$profile_url = false;
	}

	$wp_admin_bar->add_group(
		array(
			'parent' => 'my-account',
			'id'     => 'user-actions',
		)
	);

	$user_info  = get_avatar( $user_id, 64 );
	$user_info .= "<span class='display-name'>{$current_user->display_name}</span>";

	if ( $current_user->display_name !== $current_user->user_login ) {
		$user_info .= "<span class='username'>{$current_user->user_login}</span>";
	}

	$wp_admin_bar->add_menu(
		array(
			'parent' => 'user-actions',
			'id'     => 'user-info',
			'title'  => $user_info,
			'href'   => $profile_url,
			'meta'   => array(
				'tabindex' => -1,
			),
		)
	);

	if ( false !== $profile_url ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'user-actions',
				'id'     => 'edit-profile',
				'title'  => __( 'Edit My Profile' ),
				'href'   => $profile_url,
			)
		);
	}

	$wp_admin_bar->add_menu(
		array(
			'parent' => 'user-actions',
			'id'     => 'logout',
			'title'  => __( 'Log Out' ),
			'href'   => wp_logout_url(),
		)
	);
}

/**
 * Add the "Site Name" menu.
 *
 * @since WP-3.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_site_menu( $wp_admin_bar ) {
	// Don't show for logged out users.
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Show only when the user is a member of this site, or they're a super admin.
	if ( ! is_user_member_of_blog() && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	$blogname = get_bloginfo( 'name' );

	if ( ! $blogname ) {
		$blogname = preg_replace( '#^(https?://)?(www.)?#', '', get_home_url() );
	}

	if ( is_network_admin() ) {
		/* translators: %s: site name */
		$blogname = sprintf( __( 'Network Admin: %s' ), esc_html( get_network()->site_name ) );
	} elseif ( is_user_admin() ) {
		/* translators: %s: site name */
		$blogname = sprintf( __( 'User Dashboard: %s' ), esc_html( get_network()->site_name ) );
	}

	$title = wp_html_excerpt( $blogname, 40, '&hellip;' );

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'site-name',
			'title' => $title,
			'href'  => ( is_admin() || ! current_user_can( 'read' ) ) ? home_url( '/' ) : admin_url(),
		)
	);

	// Create submenu items.

	if ( is_admin() ) {
		// Add an option to visit the site.
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'view-site',
				'title'  => __( 'Visit Site' ),
				'href'   => home_url( '/' ),
			)
		);

		if ( is_blog_admin() && is_multisite() && current_user_can( 'manage_sites' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'site-name',
					'id'     => 'edit-site',
					'title'  => __( 'Edit Site' ),
					'href'   => network_admin_url( 'site-info.php?id=' . get_current_blog_id() ),
				)
			);
		}
	} elseif ( current_user_can( 'read' ) ) {
		// We're on the front end, link to the Dashboard.
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'dashboard',
				'title'  => __( 'Dashboard' ),
				'href'   => admin_url(),
			)
		);

		// Add the appearance submenu items.
		wp_admin_bar_appearance_menu( $wp_admin_bar );
	}
}

/**
 * Adds the "Customize" link to the Toolbar.
 *
 * @since WP-4.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
 * @global WP_Customize_Manager $wp_customize
 */
function wp_admin_bar_customize_menu( $wp_admin_bar ) {
	global $wp_customize;

	// Don't show for users who can't access the customizer or when in the admin.
	if ( ! current_user_can( 'customize' ) || is_admin() ) {
		return;
	}

	// Don't show if the user cannot edit a given customize_changeset post currently being previewed.
	if ( is_customize_preview() && $wp_customize->changeset_post_id() && ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->edit_post, $wp_customize->changeset_post_id() ) ) {
		return;
	}

	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	if ( is_customize_preview() && $wp_customize->changeset_uuid() ) {
		$current_url = remove_query_arg( 'customize_changeset_uuid', $current_url );
	}

	$customize_url = add_query_arg( 'url', urlencode( $current_url ), wp_customize_url() );
	if ( is_customize_preview() ) {
		$customize_url = add_query_arg( array( 'changeset_uuid' => $wp_customize->changeset_uuid() ), $customize_url );
	}

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'customize',
			'title' => __( 'Customize' ),
			'href'  => $customize_url,
			'meta'  => array(
				'class' => 'hide-if-no-customize',
			),
		)
	);
	add_action( 'wp_before_admin_bar_render', 'wp_customize_support_script' );
}

/**
 * Add the "My Sites/[Site Name]" menu and all submenus.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_my_sites_menu( $wp_admin_bar ) {
	// Don't show for logged out users or single site mode.
	if ( ! is_user_logged_in() || ! is_multisite() ) {
		return;
	}

	// Show only when the user has at least one site, or they're a super admin.
	if ( count( $wp_admin_bar->user->blogs ) < 1 && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	if ( $wp_admin_bar->user->active_blog ) {
		$my_sites_url = get_admin_url( $wp_admin_bar->user->active_blog->blog_id, 'my-sites.php' );
	} else {
		$my_sites_url = admin_url( 'my-sites.php' );
	}

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'my-sites',
			'title' => __( 'My Sites' ),
			'href'  => $my_sites_url,
		)
	);

	if ( current_user_can( 'manage_network' ) ) {
		$wp_admin_bar->add_group(
			array(
				'parent' => 'my-sites',
				'id'     => 'my-sites-super-admin',
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'my-sites-super-admin',
				'id'     => 'network-admin',
				'title'  => __( 'Network Admin' ),
				'href'   => network_admin_url(),
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'network-admin',
				'id'     => 'network-admin-d',
				'title'  => __( 'Dashboard' ),
				'href'   => network_admin_url(),
			)
		);

		if ( current_user_can( 'manage_sites' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'network-admin',
					'id'     => 'network-admin-s',
					'title'  => __( 'Sites' ),
					'href'   => network_admin_url( 'sites.php' ),
				)
			);
		}

		if ( current_user_can( 'manage_network_users' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'network-admin',
					'id'     => 'network-admin-u',
					'title'  => __( 'Users' ),
					'href'   => network_admin_url( 'users.php' ),
				)
			);
		}

		if ( current_user_can( 'manage_network_themes' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'network-admin',
					'id'     => 'network-admin-t',
					'title'  => __( 'Themes' ),
					'href'   => network_admin_url( 'themes.php' ),
				)
			);
		}

		if ( current_user_can( 'manage_network_plugins' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'network-admin',
					'id'     => 'network-admin-p',
					'title'  => __( 'Plugins' ),
					'href'   => network_admin_url( 'plugins.php' ),
				)
			);
		}

		if ( current_user_can( 'manage_network_options' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'network-admin',
					'id'     => 'network-admin-o',
					'title'  => __( 'Settings' ),
					'href'   => network_admin_url( 'settings.php' ),
				)
			);
		}
	}

	// Add site links
	$wp_admin_bar->add_group(
		array(
			'parent' => 'my-sites',
			'id'     => 'my-sites-list',
			'meta'   => array(
				'class' => current_user_can( 'manage_network' ) ? 'ab-sub-secondary' : '',
			),
		)
	);

	foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
		switch_to_blog( $blog->userblog_id );

		$cp_logo_src = includes_url( 'images/classicpress-logo-dashicon-grey-on-transparent.svg' );

		$cplogo = '<img class="cp-logo" src="' . $cp_logo_src . '" alt="" />';

		$blogname = $blog->blogname;

		if ( ! $blogname ) {
			$blogname = preg_replace( '#^(https?://)?(www.)?#', '', get_home_url() );
		}

		$menu_id = 'blog-' . $blog->userblog_id;

		if ( current_user_can( 'read' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'my-sites-list',
					'id'     => $menu_id,
					'title'  => $cplogo . $blogname,
					'href'   => admin_url(),
				)
			);

			$wp_admin_bar->add_menu(
				array(
					'parent' => $menu_id,
					'id'     => $menu_id . '-d',
					'title'  => __( 'Dashboard' ),
					'href'   => admin_url(),
				)
			);
		} else {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'my-sites-list',
					'id'     => $menu_id,
					'title'  => $blavatar . $blogname,
					'href'   => home_url(),
				)
			);
		}

		if ( current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => $menu_id,
					'id'     => $menu_id . '-n',
					'title'  => __( 'New Post' ),
					'href'   => admin_url( 'post-new.php' ),
				)
			);
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => $menu_id,
					'id'     => $menu_id . '-c',
					'title'  => __( 'Manage Comments' ),
					'href'   => admin_url( 'edit-comments.php' ),
				)
			);
		}

		$wp_admin_bar->add_menu(
			array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-v',
				'title'  => __( 'Visit Site' ),
				'href'   => home_url( '/' ),
			)
		);

		restore_current_blog();
	}
}

/**
 * Provide a shortlink.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_shortlink_menu( $wp_admin_bar ) {
	$short = wp_get_shortlink( 0, 'query' );
	$id    = 'get-shortlink';

	if ( empty( $short ) ) {
		return;
	}

	$html = '<input class="shortlink-input" type="text" readonly="readonly" value="' . esc_attr( $short ) . '" />';

	$wp_admin_bar->add_menu(
		array(
			'id'    => $id,
			'title' => __( 'Shortlink' ),
			'href'  => $short,
			'meta'  => array( 'html' => $html ),
		)
	);
}

/**
 * Provide an edit link for posts and terms.
 *
 * @since WP-3.1.0
 *
 * @global WP_Term  $tag
 * @global WP_Query $wp_the_query
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_edit_menu( $wp_admin_bar ) {
	global $tag, $wp_the_query, $user_id;

	if ( is_admin() ) {
		$current_screen = get_current_screen();
		$post           = get_post();

		if ( 'post' == $current_screen->base
			&& 'add' != $current_screen->action
			&& ( $post_type_object = get_post_type_object( $post->post_type ) )
			&& current_user_can( 'read_post', $post->ID )
			&& ( $post_type_object->public )
			&& ( $post_type_object->show_in_admin_bar ) ) {
			if ( 'draft' == $post->post_status ) {
				$preview_link = get_preview_post_link( $post );
				$wp_admin_bar->add_menu(
					array(
						'id'    => 'preview',
						'title' => $post_type_object->labels->view_item,
						'href'  => esc_url( $preview_link ),
						'meta'  => array( 'target' => 'wp-preview-' . $post->ID ),
					)
				);
			} else {
				$wp_admin_bar->add_menu(
					array(
						'id'    => 'view',
						'title' => $post_type_object->labels->view_item,
						'href'  => get_permalink( $post->ID ),
					)
				);
			}
		} elseif ( 'edit' == $current_screen->base
			&& ( $post_type_object = get_post_type_object( $current_screen->post_type ) )
			&& ( $post_type_object->public )
			&& ( $post_type_object->show_in_admin_bar )
			&& ( get_post_type_archive_link( $post_type_object->name ) )
			&& ! ( 'post' === $post_type_object->name && 'posts' === get_option( 'show_on_front' ) ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'archive',
					'title' => $post_type_object->labels->view_items,
					'href'  => get_post_type_archive_link( $current_screen->post_type ),
				)
			);
		} elseif ( 'term' == $current_screen->base
			&& isset( $tag ) && is_object( $tag ) && ! is_wp_error( $tag )
			&& ( $tax = get_taxonomy( $tag->taxonomy ) )
			&& $tax->public ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'view',
					'title' => $tax->labels->view_item,
					'href'  => get_term_link( $tag ),
				)
			);
		} elseif ( 'user-edit' == $current_screen->base
			&& isset( $user_id )
			&& ( $user_object = get_userdata( $user_id ) )
			&& $user_object->exists()
			&& $view_link = get_author_posts_url( $user_object->ID ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'view',
					'title' => __( 'View User' ),
					'href'  => $view_link,
				)
			);
		}
	} else {
		$current_object = $wp_the_query->get_queried_object();

		if ( empty( $current_object ) ) {
			return;
		}

		if ( ! empty( $current_object->post_type )
			&& ( $post_type_object = get_post_type_object( $current_object->post_type ) )
			&& current_user_can( 'edit_post', $current_object->ID )
			&& $post_type_object->show_in_admin_bar
			&& $edit_post_link = get_edit_post_link( $current_object->ID ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'edit',
					'title' => $post_type_object->labels->edit_item,
					'href'  => $edit_post_link,
				)
			);
		} elseif ( ! empty( $current_object->taxonomy )
			&& ( $tax = get_taxonomy( $current_object->taxonomy ) )
			&& current_user_can( 'edit_term', $current_object->term_id )
			&& $edit_term_link = get_edit_term_link( $current_object->term_id, $current_object->taxonomy ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'edit',
					'title' => $tax->labels->edit_item,
					'href'  => $edit_term_link,
				)
			);
		} elseif ( is_a( $current_object, 'WP_User' )
			&& current_user_can( 'edit_user', $current_object->ID )
			&& $edit_user_link = get_edit_user_link( $current_object->ID ) ) {
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'edit',
					'title' => __( 'Edit User' ),
					'href'  => $edit_user_link,
				)
			);
		}
	}
}

/**
 * Add "Add New" menu.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_new_content_menu( $wp_admin_bar ) {
	$actions = array();

	$cpts = (array) get_post_types( array( 'show_in_admin_bar' => true ), 'objects' );

	if ( isset( $cpts['post'] ) && current_user_can( $cpts['post']->cap->create_posts ) ) {
		$actions['post-new.php'] = array( $cpts['post']->labels->name_admin_bar, 'new-post' );
	}

	if ( isset( $cpts['attachment'] ) && current_user_can( 'upload_files' ) ) {
		$actions['media-new.php'] = array( $cpts['attachment']->labels->name_admin_bar, 'new-media' );
	}

	if ( current_user_can( 'manage_links' ) ) {
		$actions['link-add.php'] = array( _x( 'Link', 'add new from admin bar' ), 'new-link' );
	}

	if ( isset( $cpts['page'] ) && current_user_can( $cpts['page']->cap->create_posts ) ) {
		$actions['post-new.php?post_type=page'] = array( $cpts['page']->labels->name_admin_bar, 'new-page' );
	}

	unset( $cpts['post'], $cpts['page'], $cpts['attachment'] );

	// Add any additional custom post types.
	foreach ( $cpts as $cpt ) {
		if ( ! current_user_can( $cpt->cap->create_posts ) ) {
			continue;
		}

		$key             = 'post-new.php?post_type=' . $cpt->name;
		$actions[ $key ] = array( $cpt->labels->name_admin_bar, 'new-' . $cpt->name );
	}
	// Avoid clash with parent node and a 'content' post type.
	if ( isset( $actions['post-new.php?post_type=content'] ) ) {
		$actions['post-new.php?post_type=content'][1] = 'add-new-content';
	}

	if ( current_user_can( 'create_users' ) || ( is_multisite() && current_user_can( 'promote_users' ) ) ) {
		$actions['user-new.php'] = array( _x( 'User', 'add new from admin bar' ), 'new-user' );
	}

	if ( ! $actions ) {
		return;
	}

	$title = '<span class="ab-icon"></span><span class="ab-label">' . _x( 'New', 'admin bar menu group label' ) . '</span>';

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'new-content',
			'title' => $title,
			'href'  => admin_url( current( array_keys( $actions ) ) ),
		)
	);

	foreach ( $actions as $link => $action ) {
		list( $title, $id ) = $action;

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'new-content',
				'id'     => $id,
				'title'  => $title,
				'href'   => admin_url( $link ),
			)
		);
	}
}

/**
 * Add edit comments link with awaiting moderation count bubble.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_comments_menu( $wp_admin_bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$awaiting_mod  = wp_count_comments();
	$awaiting_mod  = $awaiting_mod->moderated;
	$awaiting_text = sprintf( _n( '%s comment awaiting moderation', '%s comments awaiting moderation', $awaiting_mod ), number_format_i18n( $awaiting_mod ) );

	$icon   = '<span class="ab-icon"></span>';
	$title  = '<span class="ab-label awaiting-mod pending-count count-' . $awaiting_mod . '" aria-hidden="true">' . number_format_i18n( $awaiting_mod ) . '</span>';
	$title .= '<span class="screen-reader-text">' . $awaiting_text . '</span>';

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'comments',
			'title' => $icon . $title,
			'href'  => admin_url( 'edit-comments.php' ),
		)
	);
}

/**
 * Add appearance submenu items to the "Site Name" menu.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_appearance_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_group(
		array(
			'parent' => 'site-name',
			'id'     => 'appearance',
		)
	);

	if ( current_user_can( 'switch_themes' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'appearance',
				'id'     => 'themes',
				'title'  => __( 'Themes' ),
				'href'   => admin_url( 'themes.php' ),
			)
		);
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	if ( current_theme_supports( 'widgets' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'appearance',
				'id'     => 'widgets',
				'title'  => __( 'Widgets' ),
				'href'   => admin_url( 'widgets.php' ),
			)
		);
	}

	if ( current_theme_supports( 'menus' ) || current_theme_supports( 'widgets' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'appearance',
				'id'     => 'menus',
				'title'  => __( 'Menus' ),
				'href'   => admin_url( 'nav-menus.php' ),
			)
		);
	}

	if ( current_theme_supports( 'custom-background' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'appearance',
				'id'     => 'background',
				'title'  => __( 'Background' ),
				'href'   => admin_url( 'themes.php?page=custom-background' ),
				'meta'   => array(
					'class' => 'hide-if-customize',
				),
			)
		);
	}

	if ( current_theme_supports( 'custom-header' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'appearance',
				'id'     => 'header',
				'title'  => __( 'Header' ),
				'href'   => admin_url( 'themes.php?page=custom-header' ),
				'meta'   => array(
					'class' => 'hide-if-customize',
				),
			)
		);
	}

}

/**
 * Provide an update link if theme/plugin/core updates are available.
 *
 * @since WP-3.1.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_updates_menu( $wp_admin_bar ) {

	$update_data = wp_get_update_data();

	if ( ! $update_data['counts']['total'] ) {
		return;
	}

	$title  = '<span class="ab-icon"></span><span class="ab-label">' . number_format_i18n( $update_data['counts']['total'] ) . '</span>';
	$title .= '<span class="screen-reader-text">' . $update_data['title'] . '</span>';

	$wp_admin_bar->add_menu(
		array(
			'id'    => 'updates',
			'title' => $title,
			'href'  => network_admin_url( 'update-core.php' ),
			'meta'  => array(
				'title' => $update_data['title'],
			),
		)
	);
}

/**
 * Add search form.
 *
 * @since WP-3.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_search_menu( $wp_admin_bar ) {
	if ( is_admin() ) {
		return;
	}

	$form  = '<form action="' . esc_url( home_url( '/' ) ) . '" method="get" id="adminbarsearch">';
	$form .= '<input class="adminbar-input" name="s" id="adminbar-search" type="text" value="" maxlength="150" />';
	$form .= '<label for="adminbar-search" class="screen-reader-text">' . __( 'Search' ) . '</label>';
	$form .= '<input type="submit" class="adminbar-button" value="' . __( 'Search' ) . '"/>';
	$form .= '</form>';

	$wp_admin_bar->add_menu(
		array(
			'parent' => 'top-secondary',
			'id'     => 'search',
			'title'  => $form,
			'meta'   => array(
				'class'    => 'admin-bar-search',
				'tabindex' => -1,
			),
		)
	);
}

/**
 * Add secondary menus.
 *
 * @since WP-3.3.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_admin_bar_add_secondary_groups( $wp_admin_bar ) {
	$wp_admin_bar->add_group(
		array(
			'id'   => 'top-secondary',
			'meta' => array(
				'class' => 'ab-top-secondary',
			),
		)
	);

	$wp_admin_bar->add_group(
		array(
			'parent' => 'wp-logo',
			'id'     => 'wp-logo-external',
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		)
	);
}

/**
 * Style and scripts for the admin bar.
 *
 * @since WP-3.1.0
 */
function wp_admin_bar_header() { ?>
<style type="text/css" media="print">#wpadminbar { display:none; }</style>
	<?php
}

/**
 * Default admin bar callback.
 *
 * @since WP-3.1.0
 */
function _admin_bar_bump_cb() {
	?>
<style type="text/css" media="screen">
	html { margin-top: 32px !important; }
	* html body { margin-top: 32px !important; }
	@media screen and ( max-width: 782px ) {
		html { margin-top: 46px !important; }
		* html body { margin-top: 46px !important; }
	}
</style>
	<?php
}

/**
 * Sets the display status of the admin bar.
 *
 * This can be called immediately upon plugin load. It does not need to be called
 * from a function hooked to the {@see 'init'} action.
 *
 * @since WP-3.1.0
 *
 * @global bool $show_admin_bar
 *
 * @param bool $show Whether to allow the admin bar to show.
 */
function show_admin_bar( $show ) {
	global $show_admin_bar;
	$show_admin_bar = (bool) $show;
}

/**
 * Determine whether the admin bar should be showing.
 *
 * @since WP-3.1.0
 *
 * @global bool   $show_admin_bar
 * @global string $pagenow
 *
 * @return bool Whether the admin bar should be showing.
 */
function is_admin_bar_showing() {
	global $show_admin_bar, $pagenow;

	// For all these types of requests, we never want an admin bar.
	if ( defined( 'XMLRPC_REQUEST' ) || defined( 'DOING_AJAX' ) || defined( 'IFRAME_REQUEST' ) ) {
		return false;
	}

	if ( is_embed() ) {
		return false;
	}

	// Integrated into the admin.
	if ( is_admin() ) {
		return true;
	}

	if ( ! isset( $show_admin_bar ) ) {
		if ( ! is_user_logged_in() || 'wp-login.php' == $pagenow ) {
			$show_admin_bar = false;
		} else {
			$show_admin_bar = _get_admin_bar_pref();
		}
	}

	/**
	 * Filters whether to show the admin bar.
	 *
	 * Returning false to this hook is the recommended way to hide the admin bar.
	 * The user's display preference is used for logged in users.
	 *
	 * @since WP-3.1.0
	 *
	 * @param bool $show_admin_bar Whether the admin bar should be shown. Default false.
	 */
	$show_admin_bar = apply_filters( 'show_admin_bar', $show_admin_bar );

	return $show_admin_bar;
}

/**
 * Retrieve the admin bar display preference of a user.
 *
 * @since WP-3.1.0
 * @access private
 *
 * @param string $context Context of this preference check. Defaults to 'front'. The 'admin'
 *  preference is no longer used.
 * @param int $user Optional. ID of the user to check, defaults to 0 for current user.
 * @return bool Whether the admin bar should be showing for this user.
 */
function _get_admin_bar_pref( $context = 'front', $user = 0 ) {
	$pref = get_user_option( "show_admin_bar_{$context}", $user );
	if ( false === $pref ) {
		return true;
	}

	return 'true' === $pref;
}

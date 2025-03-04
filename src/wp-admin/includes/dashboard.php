<?php
/**
 * ClassicPress Dashboard Widget Administration Screen API
 *
 * @package ClassicPress
 * @subpackage Administration
 */

/**
 * Registers dashboard widgets.
 *
 * Handles POST data, sets up filters.
 *
 * @since WP-2.5.0
 *
 * @global array $wp_registered_widgets
 * @global array $wp_registered_widget_controls
 * @global array $wp_dashboard_control_callbacks
 */
function wp_dashboard_setup() {
	global $wp_registered_widgets, $wp_registered_widget_controls, $wp_dashboard_control_callbacks;
	$wp_dashboard_control_callbacks = array();
	$screen                         = get_current_screen();

	/* Register Widgets and Controls */

	$response = wp_check_browser_version();

	if ( $response && $response['upgrade'] ) {
		add_filter( 'postbox_classes_dashboard_dashboard_browser_nag', 'dashboard_browser_nag_class' );
		if ( $response['insecure'] ) {
			wp_add_dashboard_widget( 'dashboard_browser_nag', __( 'You are using an insecure browser!' ), 'wp_dashboard_browser_nag' );
		} else {
			wp_add_dashboard_widget( 'dashboard_browser_nag', __( 'Your browser is out of date!' ), 'wp_dashboard_browser_nag' );
		}
	}

	// Right Now
	if ( is_blog_admin() && current_user_can( 'edit_posts' ) ) {
		wp_add_dashboard_widget( 'dashboard_right_now', __( 'At a Glance' ), 'wp_dashboard_right_now' );
	}

	if ( is_network_admin() ) {
		wp_add_dashboard_widget( 'network_dashboard_right_now', __( 'Right Now' ), 'wp_network_dashboard_right_now' );
	}

	// Activity Widget
	if ( is_blog_admin() ) {
		wp_add_dashboard_widget( 'dashboard_activity', __( 'Activity' ), 'wp_dashboard_site_activity' );
	}

	// QuickPress Widget
	if ( is_blog_admin() && current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
		$quick_draft_title = sprintf( '<span class="hide-if-no-js">%1$s</span> <span class="hide-if-js">%2$s</span>', __( 'Quick Draft' ), __( 'Your Recent Drafts' ) );
		wp_add_dashboard_widget( 'dashboard_quick_press', $quick_draft_title, 'wp_dashboard_quick_press' );
	}

	// ClassicPress News
	wp_add_dashboard_widget( 'dashboard_primary', __( 'ClassicPress News' ), 'wp_dashboard_events_news' );

	// ClassicPress Petitions
	wp_add_dashboard_widget( 'dashboard_petitions', __( 'ClassicPress Petitions' ), 'cp_dashboard_petitions' );

	if ( is_network_admin() ) {

		/**
		 * Fires after core widgets for the Network Admin dashboard have been registered.
		 *
		 * @since WP-3.1.0
		 */
		do_action( 'wp_network_dashboard_setup' );

		/**
		 * Filters the list of widgets to load for the Network Admin dashboard.
		 *
		 * @since WP-3.1.0
		 *
		 * @param array $dashboard_widgets An array of dashboard widgets.
		 */
		$dashboard_widgets = apply_filters( 'wp_network_dashboard_widgets', array() );
	} elseif ( is_user_admin() ) {

		/**
		 * Fires after core widgets for the User Admin dashboard have been registered.
		 *
		 * @since WP-3.1.0
		 */
		do_action( 'wp_user_dashboard_setup' );

		/**
		 * Filters the list of widgets to load for the User Admin dashboard.
		 *
		 * @since WP-3.1.0
		 *
		 * @param array $dashboard_widgets An array of dashboard widgets.
		 */
		$dashboard_widgets = apply_filters( 'wp_user_dashboard_widgets', array() );
	} else {

		/**
		 * Fires after core widgets for the admin dashboard have been registered.
		 *
		 * @since WP-2.5.0
		 */
		do_action( 'wp_dashboard_setup' );

		/**
		 * Filters the list of widgets to load for the admin dashboard.
		 *
		 * @since WP-2.5.0
		 *
		 * @param array $dashboard_widgets An array of dashboard widgets.
		 */
		$dashboard_widgets = apply_filters( 'wp_dashboard_widgets', array() );
	}

	foreach ( $dashboard_widgets as $widget_id ) {
		$name = empty( $wp_registered_widgets[ $widget_id ]['all_link'] ) ? $wp_registered_widgets[ $widget_id ]['name'] : $wp_registered_widgets[ $widget_id ]['name'] . " <a href='{$wp_registered_widgets[$widget_id]['all_link']}' class='edit-box open-box'>" . __( 'View all' ) . '</a>';
		wp_add_dashboard_widget( $widget_id, $name, $wp_registered_widgets[ $widget_id ]['callback'], $wp_registered_widget_controls[ $widget_id ]['callback'] );
	}

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget_id'] ) ) {
		check_admin_referer( 'edit-dashboard-widget_' . $_POST['widget_id'], 'dashboard-widget-nonce' );
		ob_start(); // hack - but the same hack wp-admin/widgets.php uses
		wp_dashboard_trigger_widget_control( $_POST['widget_id'] );
		ob_end_clean();
		wp_redirect( remove_query_arg( 'edit' ) );
		exit;
	}

	/** This action is documented in wp-admin/edit-form-advanced.php */
	do_action( 'do_meta_boxes', $screen->id, 'normal', '' );

	/** This action is documented in wp-admin/edit-form-advanced.php */
	do_action( 'do_meta_boxes', $screen->id, 'side', '' );
}

/**
 * Adds a new dashboard widget.
 *
 * @since WP-2.7.0
 *
 * @global array $wp_dashboard_control_callbacks
 *
 * @param string   $widget_id        Widget ID  (used in the 'id' attribute for the widget).
 * @param string   $widget_name      Title of the widget.
 * @param callable $callback         Function that fills the widget with the desired content.
 *                                   The function should echo its output.
 * @param callable $control_callback Optional. Function that outputs controls for the widget. Default null.
 * @param array    $callback_args    Optional. Data that should be set as the $args property of the widget array
 *                                   (which is the second parameter passed to your callback). Default null.
 */
function wp_add_dashboard_widget( $widget_id, $widget_name, $callback, $control_callback = null, $callback_args = null ) {
	$screen = get_current_screen();
	global $wp_dashboard_control_callbacks;

	$private_callback_args = array( '__widget_basename' => $widget_name );

	if ( is_null( $callback_args ) ) {
		$callback_args = $private_callback_args;
	} elseif ( is_array( $callback_args ) ) {
		$callback_args = array_merge( $callback_args, $private_callback_args );
	}

	if ( $control_callback && current_user_can( 'edit_dashboard' ) && is_callable( $control_callback ) ) {
		$wp_dashboard_control_callbacks[ $widget_id ] = $control_callback;
		if ( isset( $_GET['edit'] ) && $widget_id == $_GET['edit'] ) {
			list($url)    = explode( '#', add_query_arg( 'edit', false ), 2 );
			$widget_name .= ' <span class="postbox-title-action"><a href="' . esc_url( $url ) . '">' . __( 'Cancel' ) . '</a></span>';
			$callback     = '_wp_dashboard_control_callback';
		} else {
			list($url)    = explode( '#', add_query_arg( 'edit', $widget_id ), 2 );
			$widget_name .= ' <span class="postbox-title-action"><a href="' . esc_url( "$url#$widget_id" ) . '" class="edit-box open-box">' . __( 'Configure' ) . '</a></span>';
		}
	}

	$side_widgets = array( 'dashboard_quick_press', 'dashboard_primary' );

	$location = 'normal';
	if ( in_array( $widget_id, $side_widgets ) ) {
		$location = 'side';
	}

	$priority = 'core';
	if ( 'dashboard_browser_nag' === $widget_id ) {
		$priority = 'high';
	}

	add_meta_box( $widget_id, $widget_name, $callback, $screen, $location, $priority, $callback_args );
}

/**
 * Outputs controls for the current dashboard widget.
 *
 * @access private
 * @since WP-2.7.0
 *
 * @param mixed $dashboard
 * @param array $meta_box
 */
function _wp_dashboard_control_callback( $dashboard, $meta_box ) {
	echo '<form method="post" class="dashboard-widget-control-form wp-clearfix">';
	wp_dashboard_trigger_widget_control( $meta_box['id'] );
	wp_nonce_field( 'edit-dashboard-widget_' . $meta_box['id'], 'dashboard-widget-nonce' );
	echo '<input type="hidden" name="widget_id" value="' . esc_attr( $meta_box['id'] ) . '" />';
	submit_button( __( 'Submit' ) );
	echo '</form>';
}

/**
 * Displays the dashboard.
 *
 * @since WP-2.5.0
 */
function wp_dashboard() {
	$screen      = get_current_screen();
	$columns     = absint( $screen->get_columns() );
	$columns_css = '';
	if ( $columns ) {
		$columns_css = " columns-$columns";
	}

	?>
<div id="dashboard-widgets" class="metabox-holder<?php echo $columns_css; ?>">
	<div id="postbox-container-1" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
	</div>
	<div id="postbox-container-2" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'side', '' ); ?>
	</div>
	<div id="postbox-container-3" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'column3', '' ); ?>
	</div>
	<div id="postbox-container-4" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'column4', '' ); ?>
	</div>
</div>

	<?php
	wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

}

//
// Dashboard Widgets
//

/**
 * Dashboard widget that displays some basic stats about the site.
 *
 * Formerly 'Right Now'. A streamlined 'At a Glance' as of 3.8.
 *
 * @since WP-2.7.0
 */
function wp_dashboard_right_now() {
	?>
	<div class="main">
	<ul>
	<?php
	// Posts and Pages
	foreach ( array( 'post', 'page' ) as $post_type ) {
		$num_posts = wp_count_posts( $post_type );
		if ( $num_posts && $num_posts->publish ) {
			if ( 'post' == $post_type ) {
				$text = _n( '%s Post', '%s Posts', $num_posts->publish );
			} else {
				$text = _n( '%s Page', '%s Pages', $num_posts->publish );
			}
			$text             = sprintf( $text, number_format_i18n( $num_posts->publish ) );
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && current_user_can( $post_type_object->cap->edit_posts ) ) {
				printf( '<li class="%1$s-count"><a href="edit.php?post_type=%1$s">%2$s</a></li>', $post_type, $text );
			} else {
				printf( '<li class="%1$s-count"><span>%2$s</span></li>', $post_type, $text );
			}
		}
	}
	// Comments
	$num_comm = wp_count_comments();
	if ( $num_comm && ( $num_comm->approved || $num_comm->moderated ) ) {
		$text = sprintf( _n( '%s Comment', '%s Comments', $num_comm->approved ), number_format_i18n( $num_comm->approved ) );
		?>
		<li class="comment-count"><a href="edit-comments.php"><?php echo $text; ?></a></li>
		<?php
		$moderated_comments_count_i18n = number_format_i18n( $num_comm->moderated );
		/* translators: %s: number of comments in moderation */
		$text = sprintf( _nx( '%s in moderation', '%s in moderation', $num_comm->moderated, 'comments' ), $moderated_comments_count_i18n );
		/* translators: %s: number of comments in moderation */
		$aria_label = sprintf( _nx( '%s comment in moderation', '%s comments in moderation', $num_comm->moderated, 'comments' ), $moderated_comments_count_i18n );
		?>
		<li class="comment-mod-count
		<?php
		if ( ! $num_comm->moderated ) {
			echo ' hidden';
		}
		?>
		"><a href="edit-comments.php?comment_status=moderated" aria-label="<?php esc_attr_e( $aria_label ); ?>"><?php echo $text; ?></a></li>
		<?php
	}

	/**
	 * Filters the array of extra elements to list in the 'At a Glance'
	 * dashboard widget.
	 *
	 * Prior to WP-3.8.0, the widget was named 'Right Now'. Each element
	 * is wrapped in list-item tags on output.
	 *
	 * @since WP-3.8.0
	 *
	 * @param array $items Array of extra 'At a Glance' widget items.
	 */
	$elements = apply_filters( 'dashboard_glance_items', array() );

	if ( $elements ) {
		echo '<li>' . implode( "</li>\n<li>", $elements ) . "</li>\n";
	}

	?>
	</ul>
	<?php
	update_right_now_message();

	// Check if search engines are asked not to index this site.
	if ( ! is_network_admin() && ! is_user_admin() && current_user_can( 'manage_options' ) && '0' == get_option( 'blog_public' ) ) {

		/**
		 * Filters the link title attribute for the 'Search Engines Discouraged'
		 * message displayed in the 'At a Glance' dashboard widget.
		 *
		 * Prior to WP-3.8.0, the widget was named 'Right Now'.
		 *
		 * @since WP-3.0.0
		 * @since WP-4.5.0 The default for `$title` was updated to an empty string.
		 *
		 * @param string $title Default attribute text.
		 */
		$title = apply_filters( 'privacy_on_link_title', '' );

		/**
		 * Filters the link label for the 'Search Engines Discouraged' message
		 * displayed in the 'At a Glance' dashboard widget.
		 *
		 * Prior to WP-3.8.0, the widget was named 'Right Now'.
		 *
		 * @since WP-3.0.0
		 *
		 * @param string $content Default text.
		 */
		$content    = apply_filters( 'privacy_on_link_text', __( 'Search Engines Discouraged' ) );
		$title_attr = '' === $title ? '' : " title='$title'";

		echo "<p><a href='options-reading.php'$title_attr>$content</a></p>";
	}
	?>
	</div>
	<?php
	/*
	 * activity_box_end has a core action, but only prints content when multisite.
	 * Using an output buffer is the only way to really check if anything's displayed here.
	 */
	ob_start();

	/**
	 * Fires at the end of the 'At a Glance' dashboard widget.
	 *
	 * Prior to WP-3.8.0, the widget was named 'Right Now'.
	 *
	 * @since WP-2.5.0
	 */
	do_action( 'rightnow_end' );

	/**
	 * Fires at the end of the 'At a Glance' dashboard widget.
	 *
	 * Prior to WP-3.8.0, the widget was named 'Right Now'.
	 *
	 * @since WP-2.0.0
	 */
	do_action( 'activity_box_end' );

	$actions = ob_get_clean();

	if ( ! empty( $actions ) ) :
		?>
	<div class="sub">
		<?php echo $actions; ?>
	</div>
		<?php
	endif;
}

/**
 * @since WP-3.1.0
 */
function wp_network_dashboard_right_now() {
	$actions = array();
	if ( current_user_can( 'create_sites' ) ) {
		$actions['create-site'] = '<a href="' . network_admin_url( 'site-new.php' ) . '">' . __( 'Create a New Site' ) . '</a>';
	}
	if ( current_user_can( 'create_users' ) ) {
		$actions['create-user'] = '<a href="' . network_admin_url( 'user-new.php' ) . '">' . __( 'Create a New User' ) . '</a>';
	}

	$c_users = get_user_count();
	$c_blogs = get_blog_count();

	/* translators: %s: number of users on the network */
	$user_text = sprintf( _n( '%s user', '%s users', $c_users ), number_format_i18n( $c_users ) );
	/* translators: %s: number of sites on the network */
	$blog_text = sprintf( _n( '%s site', '%s sites', $c_blogs ), number_format_i18n( $c_blogs ) );

	/* translators: 1: text indicating the number of sites on the network, 2: text indicating the number of users on the network */
	$sentence = sprintf( __( 'You have %1$s and %2$s.' ), $blog_text, $user_text );

	if ( $actions ) {
		echo '<ul class="subsubsub">';
		foreach ( $actions as $class => $action ) {
			 $actions[ $class ] = "\t<li class='$class'>$action";
		}
		echo implode( " |</li>\n", $actions ) . "</li>\n";
		echo '</ul>';
	}
	?>
	<br class="clear" />

	<p class="youhave"><?php echo $sentence; ?></p>


	<?php
		/**
		 * Fires in the Network Admin 'Right Now' dashboard widget
		 * just before the user and site search form fields.
		 *
		 * @since WP-MU (3.0.0)
		 *
		 * @param null $unused
		 */
		do_action( 'wpmuadminresult', '' );
	?>

	<form action="<?php echo network_admin_url( 'users.php' ); ?>" method="get">
		<p>
			<label class="screen-reader-text" for="search-users"><?php _e( 'Search Users' ); ?></label>
			<input type="search" name="s" value="" size="30" autocomplete="off" id="search-users"/>
			<?php submit_button( __( 'Search Users' ), '', false, false, array( 'id' => 'submit_users' ) ); ?>
		</p>
	</form>

	<form action="<?php echo network_admin_url( 'sites.php' ); ?>" method="get">
		<p>
			<label class="screen-reader-text" for="search-sites"><?php _e( 'Search Sites' ); ?></label>
			<input type="search" name="s" value="" size="30" autocomplete="off" id="search-sites"/>
			<?php submit_button( __( 'Search Sites' ), '', false, false, array( 'id' => 'submit_sites' ) ); ?>
		</p>
	</form>
	<?php
	/**
	 * Fires at the end of the 'Right Now' widget in the Network Admin dashboard.
	 *
	 * @since WP-MU (3.0.0)
	 */
	do_action( 'mu_rightnow_end' );

	/**
	 * Fires at the end of the 'Right Now' widget in the Network Admin dashboard.
	 *
	 * @since WP-MU (3.0.0)
	 */
	do_action( 'mu_activity_box_end' );
}

/**
 * The Quick Draft widget display and creation of drafts.
 *
 * @since WP-3.8.0
 *
 * @global int $post_ID
 *
 * @param string $error_msg Optional. Error message. Default false.
 */
function wp_dashboard_quick_press( $error_msg = false ) {
	global $post_ID;

	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	/* Check if a new auto-draft (= no new post_ID) is needed or if the old can be used */
	$last_post_id = (int) get_user_option( 'dashboard_quick_press_last_post_id' ); // Get the last post_ID
	if ( $last_post_id ) {
		$post = get_post( $last_post_id );
		if ( empty( $post ) || $post->post_status != 'auto-draft' ) { // auto-draft doesn't exists anymore
			$post = get_default_post_to_edit( 'post', true );
			update_user_option( get_current_user_id(), 'dashboard_quick_press_last_post_id', (int) $post->ID ); // Save post_ID
		} else {
			$post->post_title = ''; // Remove the auto draft title
		}
	} else {
		$post    = get_default_post_to_edit( 'post', true );
		$user_id = get_current_user_id();
		// Don't create an option if this is a super admin who does not belong to this site.
		if ( in_array( get_current_blog_id(), array_keys( get_blogs_of_user( $user_id ) ) ) ) {
			update_user_option( $user_id, 'dashboard_quick_press_last_post_id', (int) $post->ID ); // Save post_ID
		}
	}

	$post_ID = (int) $post->ID;
	?>

	<form name="post" action="<?php echo esc_url( admin_url( 'post.php' ) ); ?>" method="post" id="quick-press" class="initial-form hide-if-no-js">

		<?php if ( $error_msg ) : ?>
		<div class="error"><?php echo $error_msg; ?></div>
		<?php endif; ?>

		<div class="input-text-wrap" id="title-wrap">
			<label class="screen-reader-text prompt" for="title" id="title-prompt-text">

				<?php
				/** This filter is documented in wp-admin/edit-form-advanced.php */
				echo apply_filters( 'enter_title_here', __( 'Title' ), $post );
				?>
			</label>
			<input type="text" name="post_title" id="title" autocomplete="off" />
		</div>

		<div class="textarea-wrap" id="description-wrap">
			<label class="screen-reader-text prompt" for="content" id="content-prompt-text"><?php _e( 'What&#8217;s on your mind?' ); ?></label>
			<textarea name="content" id="content" class="mceEditor" rows="3" cols="15" autocomplete="off"></textarea>
		</div>

		<p class="submit">
			<input type="hidden" name="action" id="quickpost-action" value="post-quickdraft-save" />
			<input type="hidden" name="post_ID" value="<?php echo $post_ID; ?>" />
			<input type="hidden" name="post_type" value="post" />
			<?php wp_nonce_field( 'add-post' ); ?>
			<?php submit_button( __( 'Save Draft' ), 'primary', 'save', false, array( 'id' => 'save-post' ) ); ?>
			<br class="clear" />
		</p>

	</form>
	<?php
	wp_dashboard_recent_drafts();
}

/**
 * Show recent drafts of the user on the dashboard.
 *
 * @since WP-2.7.0
 *
 * @param array $drafts
 */
function wp_dashboard_recent_drafts( $drafts = false ) {
	if ( ! $drafts ) {
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'author'         => get_current_user_id(),
			'posts_per_page' => 4,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		/**
		 * Filters the post query arguments for the 'Recent Drafts' dashboard widget.
		 *
		 * @since WP-4.4.0
		 *
		 * @param array $query_args The query arguments for the 'Recent Drafts' dashboard widget.
		 */
		$query_args = apply_filters( 'dashboard_recent_drafts_query_args', $query_args );

		$drafts = get_posts( $query_args );
		if ( ! $drafts ) {
			return;
		}
	}

	echo '<div class="drafts">';
	if ( count( $drafts ) > 3 ) {
		echo '<p class="view-all"><a href="' . esc_url( admin_url( 'edit.php?post_status=draft' ) ) . '">' . __( 'View all drafts' ) . "</a></p>\n";
	}
	echo '<h2 class="hide-if-no-js">' . __( 'Your Recent Drafts' ) . "</h2>\n<ul>";

	$drafts = array_slice( $drafts, 0, 3 );
	foreach ( $drafts as $draft ) {
		$url   = get_edit_post_link( $draft->ID );
		$title = _draft_or_post_title( $draft->ID );
		echo "<li>\n";
		/* translators: %s: post title */
		echo '<div class="draft-title"><a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ) . '">' . esc_html( $title ) . '</a>';
		echo '<time datetime="' . get_the_time( 'c', $draft ) . '">' . get_the_time( __( 'F j, Y' ), $draft ) . '</time></div>';
		if ( $the_content = wp_trim_words( $draft->post_content, 10 ) ) {
			echo '<p>' . $the_content . '</p>';
		}
		echo "</li>\n";
	}
	echo "</ul>\n</div>";
}

/**
 * Outputs a row for the Recent Comments widget.
 *
 * @access private
 * @since WP-2.7.0
 *
 * @global WP_Comment $comment
 *
 * @param WP_Comment $comment   The current comment.
 * @param bool       $show_date Optional. Whether to display the date.
 */
function _wp_dashboard_recent_comments_row( &$comment, $show_date = true ) {
	$GLOBALS['comment'] = clone $comment;

	if ( $comment->comment_post_ID > 0 ) {

		$comment_post_title = _draft_or_post_title( $comment->comment_post_ID );
		$comment_post_url   = get_the_permalink( $comment->comment_post_ID );
		$comment_post_link  = "<a href='$comment_post_url'>$comment_post_title</a>";
	} else {
		$comment_post_link = '';
	}

	$actions_string = '';
	if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) {
		// Pre-order it: Approve | Reply | Edit | Spam | Trash.
		$actions = array(
			'approve'   => '',
			'unapprove' => '',
			'reply'     => '',
			'edit'      => '',
			'spam'      => '',
			'trash'     => '',
			'delete'    => '',
			'view'      => '',
		);

		$del_nonce     = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

		$approve_url   = esc_url( "comment.php?action=approvecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$approve_nonce" );
		$unapprove_url = esc_url( "comment.php?action=unapprovecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$approve_nonce" );
		$spam_url      = esc_url( "comment.php?action=spamcomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );
		$trash_url     = esc_url( "comment.php?action=trashcomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );
		$delete_url    = esc_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );

		$actions['approve']   = "<a href='$approve_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved' class='vim-a' aria-label='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
		$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved' class='vim-u' aria-label='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
		$actions['edit']      = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' aria-label='" . esc_attr__( 'Edit this comment' ) . "'>" . __( 'Edit' ) . '</a>';
		$actions['reply']     = '<a onclick="window.commentReply && commentReply.open(\'' . $comment->comment_ID . '\',\'' . $comment->comment_post_ID . '\');return false;" class="vim-r hide-if-no-js" aria-label="' . esc_attr__( 'Reply to this comment' ) . '" href="#">' . __( 'Reply' ) . '</a>';
		$actions['spam']      = "<a href='$spam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::spam=1' class='vim-s vim-destructive' aria-label='" . esc_attr__( 'Mark this comment as spam' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb' ) . '</a>';

		if ( ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = "<a href='$delete_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Delete this comment permanently' ) . "'>" . __( 'Delete Permanently' ) . '</a>';
		} else {
			$actions['trash'] = "<a href='$trash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Move this comment to the Trash' ) . "'>" . _x( 'Trash', 'verb' ) . '</a>';
		}

		$actions['view'] = '<a class="comment-link" href="' . esc_url( get_comment_link( $comment ) ) . '" aria-label="' . esc_attr__( 'View this comment' ) . '">' . __( 'View' ) . '</a>';

		/**
		 * Filters the action links displayed for each comment in the 'Recent Comments'
		 * dashboard widget.
		 *
		 * @since WP-2.6.0
		 *
		 * @param array      $actions An array of comment actions. Default actions include:
		 *                            'Approve', 'Unapprove', 'Edit', 'Reply', 'Spam',
		 *                            'Delete', and 'Trash'.
		 * @param WP_Comment $comment The comment object.
		 */
		$actions = apply_filters( 'comment_row_actions', array_filter( $actions ), $comment );

		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( ( ( 'approve' == $action || 'unapprove' == $action ) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' | ';

			// Reply and quickedit need a hide-if-no-js span
			if ( 'reply' == $action || 'quickedit' == $action ) {
				$action .= ' hide-if-no-js';
			}

			if ( 'view' === $action && '1' !== $comment->comment_approved ) {
				$action .= ' hidden';
			}
			$actions_string .= "<span class='$action'>$sep$link</span>";
		}
	}
	?>

		<li id="comment-<?php echo $comment->comment_ID; ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status( $comment ) ), $comment ); ?>>

			<?php echo get_avatar( $comment, 50, 'mystery' ); ?>

			<?php if ( ! $comment->comment_type || 'comment' == $comment->comment_type ) : ?>

			<div class="dashboard-comment-wrap has-row-actions">
			<p class="comment-meta">
				<?php
				// Comments might not have a post they relate to, e.g. programmatically created ones.
				if ( $comment_post_link ) {
					printf(
						/* translators: 1: comment author, 2: post link, 3: notification if the comment is pending */
						__( 'From %1$s on %2$s %3$s' ),
						'<cite class="comment-author">' . get_comment_author_link( $comment ) . '</cite>',
						$comment_post_link,
						'<span class="approve">' . __( '[Pending]' ) . '</span>'
					);
				} else {
					printf(
						/* translators: 1: comment author, 2: notification if the comment is pending */
						__( 'From %1$s %2$s' ),
						'<cite class="comment-author">' . get_comment_author_link( $comment ) . '</cite>',
						'<span class="approve">' . __( '[Pending]' ) . '</span>'
					);
				}
				?>
			</p>

				<?php
			else :
				switch ( $comment->comment_type ) {
					case 'pingback':
						$type = __( 'Pingback' );
						break;
					case 'trackback':
						$type = __( 'Trackback' );
						break;
					default:
						$type = ucwords( $comment->comment_type );
				}
				$type = esc_html( $type );
				?>
			<div class="dashboard-comment-wrap has-row-actions">
			<p class="comment-meta">
				<?php
				// Pingbacks, Trackbacks or custom comment types might not have a post they relate to, e.g. programmatically created ones.
				if ( $comment_post_link ) {
					printf(
						/* translators: 1: type of comment, 2: post link, 3: notification if the comment is pending */
						_x( '%1$s on %2$s %3$s', 'dashboard' ),
						"<strong>$type</strong>",
						$comment_post_link,
						'<span class="approve">' . __( '[Pending]' ) . '</span>'
					);
				} else {
					printf(
						/* translators: 1: type of comment, 2: notification if the comment is pending */
						_x( '%1$s %2$s', 'dashboard' ),
						"<strong>$type</strong>",
						'<span class="approve">' . __( '[Pending]' ) . '</span>'
					);
				}
				?>
			</p>
			<p class="comment-author"><?php comment_author_link( $comment ); ?></p>

			<?php endif; // comment_type ?>
			<blockquote><p><?php comment_excerpt( $comment ); ?></p></blockquote>
			<?php if ( $actions_string ) : ?>
			<p class="row-actions"><?php echo $actions_string; ?></p>
			<?php endif; ?>
			</div>
		</li>
	<?php
	$GLOBALS['comment'] = null;
}

/**
 * Callback function for Activity widget.
 *
 * @since WP-3.8.0
 */
function wp_dashboard_site_activity() {

	echo '<div id="activity-widget">';

	$future_posts = wp_dashboard_recent_posts(
		array(
			'max'    => 5,
			'status' => 'future',
			'order'  => 'ASC',
			'title'  => __( 'Publishing Soon' ),
			'id'     => 'future-posts',
		)
	);
	$recent_posts = wp_dashboard_recent_posts(
		array(
			'max'    => 5,
			'status' => 'publish',
			'order'  => 'DESC',
			'title'  => __( 'Recently Published' ),
			'id'     => 'published-posts',
		)
	);

	$recent_comments = wp_dashboard_recent_comments();

	if ( ! $future_posts && ! $recent_posts && ! $recent_comments ) {
		echo '<div class="no-activity">';
		echo '<p class="smiley" aria-hidden="true"></p>';
		echo '<p>' . __( 'No activity yet!' ) . '</p>';
		echo '</div>';
	}

	echo '</div>';
}

/**
 * Generates Publishing Soon and Recently Published sections.
 *
 * @since WP-3.8.0
 *
 * @param array $args {
 *     An array of query and display arguments.
 *
 *     @type int    $max     Number of posts to display.
 *     @type string $status  Post status.
 *     @type string $order   Designates ascending ('ASC') or descending ('DESC') order.
 *     @type string $title   Section title.
 *     @type string $id      The container id.
 * }
 * @return bool False if no posts were found. True otherwise.
 */
function wp_dashboard_recent_posts( $args ) {
	$query_args = array(
		'post_type'      => 'post',
		'post_status'    => $args['status'],
		'orderby'        => 'date',
		'order'          => $args['order'],
		'posts_per_page' => intval( $args['max'] ),
		'no_found_rows'  => true,
		'cache_results'  => false,
		'perm'           => ( 'future' === $args['status'] ) ? 'editable' : 'readable',
	);

	/**
	 * Filters the query arguments used for the Recent Posts widget.
	 *
	 * @since WP-4.2.0
	 *
	 * @param array $query_args The arguments passed to WP_Query to produce the list of posts.
	 */
	$query_args = apply_filters( 'dashboard_recent_posts_query_args', $query_args );
	$posts      = new WP_Query( $query_args );

	if ( $posts->have_posts() ) {

		echo '<div id="' . $args['id'] . '" class="activity-block">';

		echo '<h3>' . $args['title'] . '</h3>';

		echo '<ul>';

		$today    = date( 'Y-m-d', current_time( 'timestamp' ) );
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );

		while ( $posts->have_posts() ) {
			$posts->the_post();

			$time = get_the_time( 'U' );
			if ( date( 'Y-m-d', $time ) == $today ) {
				$relative = __( 'Today' );
			} elseif ( date( 'Y-m-d', $time ) == $tomorrow ) {
				$relative = __( 'Tomorrow' );
			} elseif ( date( 'Y', $time ) !== date( 'Y', current_time( 'timestamp' ) ) ) {
				/* translators: date and time format for recent posts on the dashboard, from a different calendar year, see https://secure.php.net/date */
				$relative = date_i18n( __( 'M jS Y' ), $time );
			} else {
				/* translators: date and time format for recent posts on the dashboard, see https://secure.php.net/date */
				$relative = date_i18n( __( 'M jS' ), $time );
			}

			// Use the post edit link for those who can edit, the permalink otherwise.
			$recent_post_link = current_user_can( 'edit_post', get_the_ID() ) ? get_edit_post_link() : get_permalink();

			$draft_or_post_title = _draft_or_post_title();
			printf(
				'<li><span>%1$s</span> <a href="%2$s" aria-label="%3$s">%4$s</a></li>',
				/* translators: 1: relative date, 2: time */
				sprintf( _x( '%1$s, %2$s', 'dashboard' ), $relative, get_the_time() ),
				$recent_post_link,
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $draft_or_post_title ) ),
				$draft_or_post_title
			);
		}

		echo '</ul>';
		echo '</div>';

	} else {
		return false;
	}

	wp_reset_postdata();

	return true;
}

/**
 * Show Comments section.
 *
 * @since WP-3.8.0
 *
 * @param int $total_items Optional. Number of comments to query. Default 5.
 * @return bool False if no comments were found. True otherwise.
 */
function wp_dashboard_recent_comments( $total_items = 5 ) {
	// Select all comment types and filter out spam later for better query performance.
	$comments = array();

	$comments_query = array(
		'number' => $total_items * 5,
		'offset' => 0,
	);
	if ( ! current_user_can( 'edit_posts' ) ) {
		$comments_query['status'] = 'approve';
	}

	while ( count( $comments ) < $total_items && $possible = get_comments( $comments_query ) ) {
		if ( ! is_array( $possible ) ) {
			break;
		}
		foreach ( $possible as $comment ) {
			if ( ! current_user_can( 'read_post', $comment->comment_post_ID ) ) {
				continue;
			}
			$comments[] = $comment;
			if ( count( $comments ) == $total_items ) {
				break 2;
			}
		}
		$comments_query['offset'] += $comments_query['number'];
		$comments_query['number']  = $total_items * 10;
	}

	if ( $comments ) {
		echo '<div id="latest-comments" class="activity-block">';
		echo '<h3>' . __( 'Recent Comments' ) . '</h3>';

		echo '<ul id="the-comment-list" data-wp-lists="list:comment">';
		foreach ( $comments as $comment ) {
			_wp_dashboard_recent_comments_row( $comment );
		}
		echo '</ul>';

		if ( current_user_can( 'edit_posts' ) ) {
			echo '<h3 class="screen-reader-text">' . __( 'View more comments' ) . '</h3>';
			_get_list_table( 'WP_Comments_List_Table' )->views();
		}

		wp_comment_reply( -1, false, 'dashboard', false );
		wp_comment_trashnotice();

		echo '</div>';
	} else {
		return false;
	}
	return true;
}

/**
 * Display generic dashboard RSS widget feed.
 *
 * @since WP-2.5.0
 *
 * @param string $widget_id
 */
function wp_dashboard_rss_output( $widget_id ) {
	$widgets = get_option( 'dashboard_widget_options' );
	echo '<div class="rss-widget">';
	wp_widget_rss_output( $widgets[ $widget_id ] );
	echo '</div>';
}

/**
 * Checks to see if all of the feed url in $check_urls are cached.
 *
 * If $check_urls is empty, look for the rss feed url found in the dashboard
 * widget options of $widget_id. If cached, call $callback, a function that
 * echoes out output for this widget. If not cache, echo a "Loading..." stub
 * which is later replaced by Ajax call (see top of /wp-admin/index.php)
 *
 * @since WP-2.5.0
 *
 * @param string $widget_id
 * @param callable $callback
 * @param array $check_urls RSS feeds
 * @return bool False on failure. True on success.
 */
function wp_dashboard_cached_rss_widget( $widget_id, $callback, $check_urls = array() ) {
	$loading    = '<p class="widget-loading hide-if-no-js">' . __( 'Loading&#8230;' ) . '</p><div class="hide-if-js notice notice-error inline"><p>' . __( 'This widget requires JavaScript.' ) . '</p></div>';
	$doing_ajax = wp_doing_ajax();

	if ( empty( $check_urls ) ) {
		$widgets = get_option( 'dashboard_widget_options' );
		if ( empty( $widgets[ $widget_id ]['url'] ) && ! $doing_ajax ) {
			echo $loading;
			return false;
		}
		$check_urls = array( $widgets[ $widget_id ]['url'] );
	}

	$locale    = get_user_locale();
	$cache_key = 'dash_v2_' . md5( $widget_id . '_' . $locale );
	if ( false !== ( $output = get_transient( $cache_key ) ) ) {
		echo $output;
		return true;
	}

	if ( ! $doing_ajax ) {
		echo $loading;
		return false;
	}

	if ( $callback && is_callable( $callback ) ) {
		$args = array_slice( func_get_args(), 3 );
		array_unshift( $args, $widget_id, $check_urls );
		ob_start();
		call_user_func_array( $callback, $args );
		set_transient( $cache_key, ob_get_flush(), 12 * HOUR_IN_SECONDS ); // Default lifetime in cache of 12 hours (same as the feeds)
	}

	return true;
}

//
// Dashboard Widgets Controls
//

/**
 * Calls widget control callback.
 *
 * @since WP-2.5.0
 *
 * @global array $wp_dashboard_control_callbacks
 *
 * @param int $widget_control_id Registered Widget ID.
 */
function wp_dashboard_trigger_widget_control( $widget_control_id = false ) {
	global $wp_dashboard_control_callbacks;

	if ( is_scalar( $widget_control_id ) && $widget_control_id && isset( $wp_dashboard_control_callbacks[ $widget_control_id ] ) && is_callable( $wp_dashboard_control_callbacks[ $widget_control_id ] ) ) {
		call_user_func(
			$wp_dashboard_control_callbacks[ $widget_control_id ],
			'',
			array(
				'id'       => $widget_control_id,
				'callback' => $wp_dashboard_control_callbacks[ $widget_control_id ],
			)
		);
	}
}

/**
 * The RSS dashboard widget control.
 *
 * Sets up $args to be used as input to wp_widget_rss_form(). Handles POST data
 * from RSS-type widgets.
 *
 * @since WP-2.5.0
 *
 * @param string $widget_id
 * @param array $form_inputs
 */
function wp_dashboard_rss_control( $widget_id, $form_inputs = array() ) {
	if ( ! $widget_options = get_option( 'dashboard_widget_options' ) ) {
		$widget_options = array();
	}

	if ( ! isset( $widget_options[ $widget_id ] ) ) {
		$widget_options[ $widget_id ] = array();
	}

	$number                                 = 1; // Hack to use wp_widget_rss_form()
	$widget_options[ $widget_id ]['number'] = $number;

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget-rss'][ $number ] ) ) {
		$_POST['widget-rss'][ $number ]         = wp_unslash( $_POST['widget-rss'][ $number ] );
		$widget_options[ $widget_id ]           = wp_widget_rss_process( $_POST['widget-rss'][ $number ] );
		$widget_options[ $widget_id ]['number'] = $number;

		// Title is optional. If black, fill it if possible.
		if ( ! $widget_options[ $widget_id ]['title'] && isset( $_POST['widget-rss'][ $number ]['title'] ) ) {
			$rss = fetch_feed( $widget_options[ $widget_id ]['url'] );
			if ( is_wp_error( $rss ) ) {
				$widget_options[ $widget_id ]['title'] = htmlentities( __( 'Unknown Feed' ) );
			} else {
				$widget_options[ $widget_id ]['title'] = htmlentities( strip_tags( $rss->get_title() ) );
				$rss->__destruct();
				unset( $rss );
			}
		}
		update_option( 'dashboard_widget_options', $widget_options );
		$locale    = get_user_locale();
		$cache_key = 'dash_v2_' . md5( $widget_id . '_' . $locale );
		delete_transient( $cache_key );
	}

	wp_widget_rss_form( $widget_options[ $widget_id ], $form_inputs );
}


/**
 * Renders the ClassicPress News dashboard widget.
 *
 * @since WP-4.8.0
 * @since 1.0.0 Events section removed.
 */
function wp_dashboard_events_news() {
	?>
	<div class="classicpress-news-inline-notice">
		<?php
		printf(
			/* translators: link to ClassicPress blog */
			__( 'You can always find the latest ClassicPress news on our <a href="%s">official&nbsp;blog</a>.' ),
			'https://www.classicpress.net/blog/'
		);
		?>
	</div>
	<div class="wordpress-news hide-if-no-js">
		<?php wp_dashboard_primary(); ?>
	</div>
	<?php
}

/**
 * ClassicPress News dashboard widget.
 *
 * @since WP-2.7.0
 * @since WP-4.8.0 Removed popular plugins feed.
 */
function wp_dashboard_primary() {
	$feeds = array(
		'news' => array(

			/**
			 * Filters the primary link URL for the 'ClassicPress News' dashboard widget.
			 *
			 * @since WP-2.5.0
			 *
			 * @param string $link The widget's primary link URL.
			 */
			'link'         => apply_filters( 'dashboard_primary_link', 'https://www.classicpress.net/blog/' ),

			/**
			 * Filters the primary feed URL for the 'ClassicPress News' dashboard widget.
			 *
			 * @since WP-2.3.0
			 *
			 * @param string $url The widget's primary feed URL.
			 */
			'url'          => apply_filters( 'dashboard_primary_feed', 'https://www.classicpress.net/feed/' ),

			/**
			 * Filters the primary link title for the 'ClassicPress News' dashboard widget.
			 *
			 * @since WP-2.3.0
			 *
			 * @param string $title Title attribute for the widget's primary link.
			 */
			'title'        => apply_filters( 'dashboard_primary_title', __( 'ClassicPress Blog' ) ),
			'items'        => 5,
			'show_summary' => 0,
			'show_author'  => 0,
			'show_date'    => 0,
		),
	);

	wp_dashboard_cached_rss_widget( 'dashboard_primary', 'wp_dashboard_primary_output', $feeds );
}

/**
 * Display the ClassicPress news feeds.
 *
 * @since WP-3.8.0
 * @since WP-4.8.0 Removed popular plugins feed.
 *
 * @param string $widget_id Widget ID.
 * @param array  $feeds     Array of RSS feeds.
 */
function wp_dashboard_primary_output( $widget_id, $feeds ) {
	foreach ( $feeds as $type => $args ) {
		$args['type'] = $type;
		echo '<div class="rss-widget">';
			wp_widget_rss_output( $args['url'], $args );
		echo '</div>';
	}
}

/**
 * Display file upload quota on dashboard.
 *
 * Runs on the {@see 'activity_box_end'} hook in wp_dashboard_right_now().
 *
 * @since WP-3.0.0
 *
 * @return bool|null True if not multisite, user can't upload files, or the space check option is disabled.
 */
function wp_dashboard_quota() {
	if ( ! is_multisite() || ! current_user_can( 'upload_files' ) || get_site_option( 'upload_space_check_disabled' ) ) {
		return true;
	}

	$quota = get_space_allowed();
	$used  = get_space_used();

	if ( $used > $quota ) {
		$percentused = '100';
	} else {
		$percentused = ( $used / $quota ) * 100;
	}
	$used_class  = ( $percentused >= 70 ) ? ' warning' : '';
	$used        = round( $used, 2 );
	$percentused = number_format( $percentused );

	?>
	<h3 class="mu-storage"><?php _e( 'Storage Space' ); ?></h3>
	<div class="mu-storage">
	<ul>
		<li class="storage-count">
			<?php
			$text = sprintf(
				/* translators: %s: number of megabytes */
				__( '%s MB Space Allowed' ),
				number_format_i18n( $quota )
			);
			printf(
				'<a href="%1$s">%2$s <span class="screen-reader-text">(%3$s)</span></a>',
				esc_url( admin_url( 'upload.php' ) ),
				$text,
				__( 'Manage Uploads' )
			);
			?>
		</li><li class="storage-count <?php echo $used_class; ?>">
			<?php
			$text = sprintf(
				/* translators: 1: number of megabytes, 2: percentage */
				__( '%1$s MB (%2$s%%) Space Used' ),
				number_format_i18n( $used, 2 ),
				$percentused
			);
			printf(
				'<a href="%1$s" class="musublink">%2$s <span class="screen-reader-text">(%3$s)</span></a>',
				esc_url( admin_url( 'upload.php' ) ),
				$text,
				__( 'Manage Uploads' )
			);
			?>
		</li>
	</ul>
	</div>
	<?php
}

// Display Browser Nag Meta Box
function wp_dashboard_browser_nag() {
	$notice   = '';
	$response = wp_check_browser_version();

	if ( $response ) {
		if ( $response['insecure'] ) {
			/* translators: %s: browser name and link */
			$msg = sprintf(
				__( "It looks like you're using an insecure version of %s. Using an outdated browser makes your computer unsafe. For the best ClassicPress experience, please update your browser." ),
				sprintf( '<a href="%s">%s</a>', esc_url( $response['update_url'] ), esc_html( $response['name'] ) )
			);
		} else {
			/* translators: %s: browser name and link */
			$msg = sprintf(
				__( "It looks like you're using an old version of %s. For the best ClassicPress experience, please update your browser." ),
				sprintf( '<a href="%s">%s</a>', esc_url( $response['update_url'] ), esc_html( $response['name'] ) )
			);
		}

		$browser_nag_class = '';
		if ( ! empty( $response['img_src'] ) ) {
			$img_src = ( is_ssl() && ! empty( $response['img_src_ssl'] ) ) ? $response['img_src_ssl'] : $response['img_src'];

			$notice           .= '<div class="alignright browser-icon"><a href="' . esc_attr( $response['update_url'] ) . '"><img src="' . esc_attr( $img_src ) . '" alt="" /></a></div>';
			$browser_nag_class = ' has-browser-icon';
		}
		$notice .= "<p class='browser-update-nag{$browser_nag_class}'>{$msg}</p>";

		$browsehappy = 'https://browsehappy.com/';
		$locale      = get_user_locale();
		if ( 'en_US' !== $locale ) {
			$browsehappy = add_query_arg( 'locale', $locale, $browsehappy );
		}

		$notice .= '<p>' . sprintf( __( '<a href="%1$s" class="update-browser-link">Update %2$s</a> or learn how to <a href="%3$s" class="browse-happy-link">browse happy</a>' ), esc_attr( $response['update_url'] ), esc_html( $response['name'] ), esc_url( $browsehappy ) ) . '</p>';
		$notice .= '<p class="hide-if-no-js"><a href="" class="dismiss" aria-label="' . esc_attr__( 'Dismiss the browser warning panel' ) . '">' . __( 'Dismiss' ) . '</a></p>';
		$notice .= '<div class="clear"></div>';
	}

	/**
	* Filters the notice output for the 'Browse Happy' nag meta box.
	*
	* @since WP-3.2.0
	*
	* @param string $notice   The notice content.
	* @param array  $response An array containing web browser information.
	*/
	echo apply_filters( 'browse-happy-notice', $notice, $response );
}

/**
 * @since WP-3.2.0
 *
 * @param array $classes
 * @return array
 */
function dashboard_browser_nag_class( $classes ) {
	$response = wp_check_browser_version();

	if ( $response && $response['insecure'] ) {
		$classes[] = 'browser-insecure';
	}

	return $classes;
}

/**
 * Check if the user needs a browser update
 *
 * @since WP-3.2.0
 *
 * @return array|bool False on failure, array of browser data on success.
 */
function wp_check_browser_version() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return false;
	}

	$key = md5( $_SERVER['HTTP_USER_AGENT'] );

	if ( false === ( $response = get_site_transient( 'browser_' . $key ) ) ) {
		// include an unmodified $wp_version
		include ABSPATH . WPINC . '/version.php';

		$url     = 'https://api.wordpress.org/core/browse-happy/1.1/';
		$options = array(
			'body'       => array( 'useragent' => $_SERVER['HTTP_USER_AGENT'] ),
			'user-agent' => classicpress_user_agent(),
		);

		$response = wp_remote_post( $url, $options );

		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		/**
		 * Response should be an array with:
		 *  'platform' - string - A user-friendly platform name, if it can be determined
		 *  'name' - string - A user-friendly browser name
		 *  'version' - string - The version of the browser the user is using
		 *  'current_version' - string - The most recent version of the browser
		 *  'upgrade' - boolean - Whether the browser needs an upgrade
		 *  'insecure' - boolean - Whether the browser is deemed insecure
		 *  'update_url' - string - The url to visit to upgrade
		 *  'img_src' - string - An image representing the browser
		 *  'img_src_ssl' - string - An image (over SSL) representing the browser
		 */
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $response ) ) {
			return false;
		}

		set_site_transient( 'browser_' . $key, $response, WEEK_IN_SECONDS );
	}

	return $response;
}

/**
 * Empty function usable by plugins to output empty dashboard widget (to be populated later by JS).
 */
function wp_dashboard_empty() {}

/**
 * Displays a welcome panel to introduce users to ClassicPress.
 *
 * @since WP-3.3.0
 */
function wp_welcome_panel() {
	$display_version = classicpress_version();
	?>
<div class="welcome-panel-content">
	<h2><?php _e( 'Welcome to ClassicPress!' ); ?></h2>
	<p class="welcome-panel-tagline"><?php printf( __( 'Thank you for trying ClassicPress&nbsp;%s!' ), $display_version ); ?></p>
	<p>
		<?php
		_e(
			'<strong>Join our growing community</strong> and help us build the platform <strong>you</strong> want to see!'
		);
		?>
	</p>
	<p>
	<?php
	if ( get_locale() === 'en_US' ) {
		printf(
			/* translators: link to "About ClassicPress" dashboard page */
			__( 'To see how you can help, visit the <a href="%s">About ClassicPress</a> page.' ),
			esc_url( self_admin_url( 'about.php' ) )
		);
	} else {
		printf(
			/* translators: link to learn more about translating ClassicPress */
			__( 'Help us translate ClassicPress into your language! <a href="%s">Learn more</a>.' ),
			'https://www.classicpress.net/translating-classicpress/'
		);
		echo '</p><p>';
		printf(
			/* translators: link to "About ClassicPress" dashboard page */
			__( 'For other ways you can help, visit the <a href="%s">About ClassicPress</a> page.' ),
			esc_url( self_admin_url( 'about.php' ) )
		);
	}
	?>
	</p>
</div>
	<?php
}

/**
 * Callback function for the petitions dashboard widget
 *
 * @since 1.0.0
 */
function cp_dashboard_petitions() {
	$feeds = array(
		'trending'    => array(
			'title' => __( 'Trending' ),
		),
		'most-wanted' => array(
			'title' => __( 'Most Wanted' ),
		),
		'recent'      => array(
			'title' => __( 'Recent' ),
		),
	);

	wp_dashboard_cached_rss_widget( 'dashboard_petitions', 'cp_dashboard_petitions_output', $feeds );
}

/**
 * Display the ClassicPress petitions feeds.
 *
 * Query the ClassicPress.net API for data about ClassicPress petitions, and
 * echo the results as HTML.
 *
 * @since 1.0.0
 *
 * @param string $widget_id Widget ID.
 * @param array  $feeds     Array of petition feeds (possible sort orders).
 */
function cp_dashboard_petitions_output( $widget_id, $feeds ) {
	$api_url = 'https://api-v1.classicpress.net/features/1.0/';

	/**
	 * Response body should be an object with:
	 *  'link'        - string - Link to the ClassicPress petitions website.
	 *  'most-wanted' - object - Petitions sorted by number of votes.
	 *  'trending'    - object - Petitions sorted by activity and votes.
	 *  'recent'      - object - Petitions sorted by date created.
	 * Each of these 'object' keys should have a 'data' property which is an
	 * array of the top petitions sorted in the order represented by the key.
	 */
	$response      = wp_remote_get( $api_url );
	$response_code = wp_remote_retrieve_response_code( $response );

	if ( ! is_wp_error( $response ) && 200 !== $response_code ) {
		$response = new WP_Error(
			'api-error',
			/* translators: %d: numeric HTTP status code, e.g. 400, 403, 500, 504, etc. */
			sprintf( __( 'Invalid API response code (%d)' ), $response_code )
		);
	}

	if ( ! is_wp_error( $response ) ) {
		$response = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $response ) || ! is_object( $response ) || ! isset( $response->link ) ) {
			$response = new WP_Error(
				'api-error',
				__( 'Invalid API response (invalid JSON)' )
			);
		}
	}

	if ( is_wp_error( $response ) ) {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			echo '<p><strong>' . __( 'Error:' ) . '</strong> ' . $response->get_error_message() . '</p>';
		}
		return;
	}

	?>
	<div class="sub">
		<a href="<?php echo esc_url( $response->link ); ?>" target="_blank" class="cp_petitions_link"><?php esc_html_e( 'Your voice counts, create and vote on petitions.' ); ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)' ); ?></span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>
	</div>

	<ul class="petitions-tabs">

	<?php
	$keys   = array_keys( $feeds );
	$active = array_shift( $keys );

	foreach ( $feeds as $name => $args ) {
		$class = $name === $active ? ' class="active"' : '';
		?>
		<li<?php echo $class; ?>><a href="#<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $args['title'] ); ?></a></li>
		<?php
	}
	?>

	</ul>

	<div class="petitions-content">

	<?php
	foreach ( $feeds as $name => $args ) {

		if ( empty( $response->$name ) ) {
			continue;
		}

		$data   = $response->$name->data;
		$class  = $name === $active ? 'petitions-pane active' : 'petitions-pane';
		$active = $name === array_shift( $keys ) ? ' active' : '';
		?>

		<div id="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<table class="cp_petitions">
				<thead>
					<tr>
						<td><?php esc_html_e( 'Votes' ); ?></td>
						<td><?php esc_html_e( 'Petitions' ); ?></td>
					</tr>
				</thead>

				<?php
				foreach ( $data as $petition ) {
					?>
					<tr>
						<td class="votes-count"><?php echo esc_html( $petition->votesCount ); ?></td>

						<td class="petition">
							<a target="_blank" href="<?php echo esc_url( $petition->link ); ?>"><?php echo esc_html( $petition->title ); ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)' ); ?></span></a>
							<?php
							if ( 'open' === $petition->status ) {
								echo esc_html__( ' - ' ) . ' ' . sprintf( __( '%s ago' ), human_time_diff( strtotime( $petition->createdAt ), current_time( 'timestamp' ) ) );
							} else {
								echo ' - ' . '<span class="' . esc_attr( $petition->status ) . '">' . esc_html( $petition->status ) . '</span>';
							}
							?>
						</td>
					</tr>
					<?php
				}
				?>
				</table>

			</div>
			<?php
	}
	?>
	</div>
	<?php
}

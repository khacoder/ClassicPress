<?php
/**
 * User API: WP_Roles class
 *
 * @package ClassicPress
 * @subpackage Users
 * @since WP-4.4.0
 */

/**
 * Core class used to implement a user roles API.
 *
 * The role option is simple, the structure is organized by role name that store
 * the name in value of the 'name' key. The capabilities are stored as an array
 * in the value of the 'capability' key.
 *
 *     array (
 *          'rolename' => array (
 *              'name' => 'rolename',
 *              'capabilities' => array()
 *          )
 *     )
 *
 * @since WP-2.0.0
 */
class WP_Roles {
	/**
	 * List of roles and capabilities.
	 *
	 * @since WP-2.0.0
	 * @var array
	 */
	public $roles;

	/**
	 * List of the role objects.
	 *
	 * @since WP-2.0.0
	 * @var array
	 */
	public $role_objects = array();

	/**
	 * List of role names.
	 *
	 * @since WP-2.0.0
	 * @var array
	 */
	public $role_names = array();

	/**
	 * Option name for storing role list.
	 *
	 * @since WP-2.0.0
	 * @var string
	 */
	public $role_key;

	/**
	 * Whether to use the database for retrieval and storage.
	 *
	 * @since WP-2.1.0
	 * @var bool
	 */
	public $use_db = true;

	/**
	 * The site ID the roles are initialized for.
	 *
	 * @since WP-4.9.0
	 * @var int
	 */
	protected $site_id = 0;

	/**
	 * Constructor
	 *
	 * @since WP-2.0.0
	 * @since WP-4.9.0 The $site_id argument was added.
	 *
	 * @global array $wp_user_roles Used to set the 'roles' property value.
	 *
	 * @param int $site_id Site ID to initialize roles for. Default is the current site.
	 */
	public function __construct( $site_id = null ) {
		global $wp_user_roles;

		$this->use_db = empty( $wp_user_roles );

		$this->for_site( $site_id );
	}

	/**
	 * Make private/protected methods readable for backward compatibility.
	 *
	 * @since WP-4.0.0
	 *
	 * @param callable $name      Method to call.
	 * @param array    $arguments Arguments to pass when calling.
	 * @return mixed|false Return value of the callback, false otherwise.
	 */
	public function __call( $name, $arguments ) {
		if ( '_init' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	/**
	 * Set up the object properties.
	 *
	 * The role key is set to the current prefix for the $wpdb object with
	 * 'user_roles' appended. If the $wp_user_roles global is set, then it will
	 * be used and the role option will not be updated or used.
	 *
	 * @since WP-2.1.0
	 * @deprecated WP-4.9.0 Use WP_Roles::for_site()
	 */
	protected function _init() {
		_deprecated_function( __METHOD__, 'WP-4.9.0', 'WP_Roles::for_site()' );

		$this->for_site();
	}

	/**
	 * Reinitialize the object
	 *
	 * Recreates the role objects. This is typically called only by switch_to_blog()
	 * after switching wpdb to a new site ID.
	 *
	 * @since WP-3.5.0
	 * @deprecated WP-4.7.0 Use WP_Roles::for_site()
	 */
	public function reinit() {
		_deprecated_function( __METHOD__, 'WP-4.7.0', 'WP_Roles::for_site()' );

		$this->for_site();
	}

	/**
	 * Add role name with capabilities to list.
	 *
	 * Updates the list of roles, if the role doesn't already exist.
	 *
	 * The capabilities are defined in the following format `array( 'read' => true );`
	 * To explicitly deny a role a capability you set the value for that capability to false.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name.
	 * @param string $display_name Role display name.
	 * @param array $capabilities List of role capabilities in the above format.
	 * @return WP_Role|void WP_Role object, if role is added.
	 */
	public function add_role( $role, $display_name, $capabilities = array() ) {
		if ( empty( $role ) || isset( $this->roles[ $role ] ) ) {
			return;
		}

		$this->roles[ $role ] = array(
			'name'         => $display_name,
			'capabilities' => $capabilities,
		);
		if ( $this->use_db ) {
			update_option( $this->role_key, $this->roles );
		}
		$this->role_objects[ $role ] = new WP_Role( $role, $capabilities );
		$this->role_names[ $role ]   = $display_name;
		return $this->role_objects[ $role ];
	}

	/**
	 * Remove role by name.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name.
	 */
	public function remove_role( $role ) {
		if ( ! isset( $this->role_objects[ $role ] ) ) {
			return;
		}

		unset( $this->role_objects[ $role ] );
		unset( $this->role_names[ $role ] );
		unset( $this->roles[ $role ] );

		if ( $this->use_db ) {
			update_option( $this->role_key, $this->roles );
		}

		if ( get_option( 'default_role' ) == $role ) {
			update_option( 'default_role', 'subscriber' );
		}
	}

	/**
	 * Add capability to role.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 * @param bool $grant Optional, default is true. Whether role is capable of performing capability.
	 */
	public function add_cap( $role, $cap, $grant = true ) {
		if ( ! isset( $this->roles[ $role ] ) ) {
			return;
		}

		$this->roles[ $role ]['capabilities'][ $cap ] = $grant;
		if ( $this->use_db ) {
			update_option( $this->role_key, $this->roles );
		}
	}

	/**
	 * Remove capability from role.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $role, $cap ) {
		if ( ! isset( $this->roles[ $role ] ) ) {
			return;
		}

		unset( $this->roles[ $role ]['capabilities'][ $cap ] );
		if ( $this->use_db ) {
			update_option( $this->role_key, $this->roles );
		}
	}

	/**
	 * Retrieve role object by name.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name.
	 * @return WP_Role|null WP_Role object if found, null if the role does not exist.
	 */
	public function get_role( $role ) {
		if ( isset( $this->role_objects[ $role ] ) ) {
			return $this->role_objects[ $role ];
		} else {
			return null;
		}
	}

	/**
	 * Retrieve list of role names.
	 *
	 * @since WP-2.0.0
	 *
	 * @return array List of role names.
	 */
	public function get_names() {
		return $this->role_names;
	}

	/**
	 * Whether role name is currently in the list of available roles.
	 *
	 * @since WP-2.0.0
	 *
	 * @param string $role Role name to look up.
	 * @return bool
	 */
	public function is_role( $role ) {
		return isset( $this->role_names[ $role ] );
	}

	/**
	 * Initializes all of the available roles.
	 *
	 * @since WP-4.9.0
	 */
	public function init_roles() {
		if ( empty( $this->roles ) ) {
			return;
		}

		$this->role_objects = array();
		$this->role_names   = array();
		foreach ( array_keys( $this->roles ) as $role ) {
			$this->role_objects[ $role ] = new WP_Role( $role, $this->roles[ $role ]['capabilities'] );
			$this->role_names[ $role ]   = $this->roles[ $role ]['name'];
		}

		/**
		 * After the roles have been initialized, allow plugins to add their own roles.
		 *
		 * @since WP-4.7.0
		 *
		 * @param WP_Roles $this A reference to the WP_Roles object.
		 */
		do_action( 'wp_roles_init', $this );
	}

	/**
	 * Sets the site to operate on. Defaults to the current site.
	 *
	 * @since WP-4.9.0
	 *
	 * @global wpdb $wpdb ClassicPress database abstraction object.
	 *
	 * @param int $site_id Site ID to initialize roles for. Default is the current site.
	 */
	public function for_site( $site_id = null ) {
		global $wpdb;

		if ( ! empty( $site_id ) ) {
			$this->site_id = absint( $site_id );
		} else {
			$this->site_id = get_current_blog_id();
		}

		$this->role_key = $wpdb->get_blog_prefix( $this->site_id ) . 'user_roles';

		if ( ! empty( $this->roles ) && ! $this->use_db ) {
			return;
		}

		$this->roles = $this->get_roles_data();

		$this->init_roles();
	}

	/**
	 * Gets the ID of the site for which roles are currently initialized.
	 *
	 * @since WP-4.9.0
	 *
	 * @return int Site ID.
	 */
	public function get_site_id() {
		return $this->site_id;
	}

	/**
	 * Gets the available roles data.
	 *
	 * @since WP-4.9.0
	 *
	 * @global array $wp_user_roles Used to set the 'roles' property value.
	 *
	 * @return array Roles array.
	 */
	protected function get_roles_data() {
		global $wp_user_roles;

		if ( ! empty( $wp_user_roles ) ) {
			return $wp_user_roles;
		}

		if ( is_multisite() && $this->site_id != get_current_blog_id() ) {
			remove_action( 'switch_blog', 'wp_switch_roles_and_user', 1 );

			$roles = get_blog_option( $this->site_id, $this->role_key, array() );

			add_action( 'switch_blog', 'wp_switch_roles_and_user', 1, 2 );

			return $roles;
		}

		return get_option( $this->role_key, array() );
	}
}

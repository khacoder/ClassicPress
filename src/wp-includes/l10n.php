<?php
/**
 * Core Translation API
 *
 * @package ClassicPress
 * @subpackage i18n
 * @since WP-1.2.0
 */

/**
 * Retrieves the current locale.
 *
 * If the locale is set, then it will filter the locale in the {@see 'locale'}
 * filter hook and return the value.
 *
 * If the locale is not set already, then the WPLANG constant is used if it is
 * defined. Then it is filtered through the {@see 'locale'} filter hook and
 * the value for the locale global set and the locale is returned.
 *
 * The process to get the locale should only be done once, but the locale will
 * always be filtered using the {@see 'locale'} hook.
 *
 * @since WP-1.5.0
 *
 * @global string $locale
 * @global string $wp_local_package
 *
 * @return string The locale of the blog or from the {@see 'locale'} hook.
 */
function get_locale() {
	global $locale, $wp_local_package;

	if ( isset( $locale ) ) {
		/**
		 * Filters the locale ID of the ClassicPress installation.
		 *
		 * @since WP-1.5.0
		 *
		 * @param string $locale The locale ID.
		 */
		return apply_filters( 'locale', $locale );
	}

	if ( isset( $wp_local_package ) ) {
		$locale = $wp_local_package;
	}

	// WPLANG was defined in wp-config.
	if ( defined( 'WPLANG' ) ) {
		$locale = WPLANG;
	}

	// If multisite, check options.
	if ( is_multisite() ) {
		// Don't check blog option when installing.
		if ( wp_installing() || ( false === $ms_locale = get_option( 'WPLANG' ) ) ) {
			$ms_locale = get_site_option( 'WPLANG' );
		}

		if ( $ms_locale !== false ) {
			$locale = $ms_locale;
		}
	} else {
		$db_locale = get_option( 'WPLANG' );
		if ( $db_locale !== false ) {
			$locale = $db_locale;
		}
	}

	if ( empty( $locale ) ) {
		$locale = 'en_US';
	}

	/** This filter is documented in wp-includes/l10n.php */
	return apply_filters( 'locale', $locale );
}

/**
 * Retrieves the locale of a user.
 *
 * If the user has a locale set to a non-empty string then it will be
 * returned. Otherwise it returns the locale of get_locale().
 *
 * @since WP-4.7.0
 *
 * @param int|WP_User $user_id User's ID or a WP_User object. Defaults to current user.
 * @return string The locale of the user.
 */
function get_user_locale( $user_id = 0 ) {
	$user = false;
	if ( 0 === $user_id && function_exists( 'wp_get_current_user' ) ) {
		$user = wp_get_current_user();
	} elseif ( $user_id instanceof WP_User ) {
		$user = $user_id;
	} elseif ( $user_id && is_numeric( $user_id ) ) {
		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user ) {
		return get_locale();
	}

	$locale = $user->locale;
	return $locale ? $locale : get_locale();
}

/**
 * Retrieve the translation of $text.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * *Note:* Don't use translate() directly, use __() or related functions.
 *
 * @since WP-2.2.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 * @return string Translated text
 */
function translate( $text, $domain = 'default' ) {
	$translations = get_translations_for_domain( $domain );
	$translation  = $translations->translate( $text );

	/**
	 * Filters text with its translation.
	 *
	 * @since WP-2.0.11
	 *
	 * @param string $translation  Translated text.
	 * @param string $text         Text to translate.
	 * @param string $domain       Text domain. Unique identifier for retrieving translated strings.
	 */
	return apply_filters( 'gettext', $translation, $text, $domain );
}

/**
 * Remove last item on a pipe-delimited string.
 *
 * Meant for removing the last item in a string, such as 'Role name|User role'. The original
 * string will be returned if no pipe '|' characters are found in the string.
 *
 * @since WP-2.8.0
 *
 * @param string $string A pipe-delimited string.
 * @return string Either $string or everything before the last pipe.
 */
function before_last_bar( $string ) {
	$last_bar = strrpos( $string, '|' );
	if ( false === $last_bar ) {
		return $string;
	} else {
		return substr( $string, 0, $last_bar );
	}
}

/**
 * Retrieve the translation of $text in the context defined in $context.
 *
 * If there is no translation, or the text domain isn't loaded the original
 * text is returned.
 *
 * *Note:* Don't use translate_with_gettext_context() directly, use _x() or related functions.
 *
 * @since WP-2.8.0
 *
 * @param string $text    Text to translate.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string Translated text on success, original text on failure.
 */
function translate_with_gettext_context( $text, $context, $domain = 'default' ) {
	$translations = get_translations_for_domain( $domain );
	$translation  = $translations->translate( $text, $context );
	/**
	 * Filters text with its translation based on context information.
	 *
	 * @since WP-2.8.0
	 *
	 * @param string $translation  Translated text.
	 * @param string $text         Text to translate.
	 * @param string $context      Context information for the translators.
	 * @param string $domain       Text domain. Unique identifier for retrieving translated strings.
	 */
	return apply_filters( 'gettext_with_context', $translation, $text, $context, $domain );
}

/**
 * Retrieve the translation of $text.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * @since WP-2.1.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 * @return string Translated text.
 */
function __( $text, $domain = 'default' ) {
	return translate( $text, $domain );
}

/**
 * Retrieve the translation of $text and escapes it for safe use in an attribute.
 *
 * If there is no translation, or the text domain isn't loaded, the original text is returned.
 *
 * @since WP-2.8.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 * @return string Translated text on success, original text on failure.
 */
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( translate( $text, $domain ) );
}

/**
 * Retrieve the translation of $text and escapes it for safe use in HTML output.
 *
 * If there is no translation, or the text domain isn't loaded, the original text
 * is escaped and returned..
 *
 * @since WP-2.8.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 * @return string Translated text
 */
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( translate( $text, $domain ) );
}

/**
 * Display translated text.
 *
 * @since WP-1.2.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 */
function _e( $text, $domain = 'default' ) {
	echo translate( $text, $domain );
}

/**
 * Display translated text that has been escaped for safe use in an attribute.
 *
 * @since WP-2.8.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 */
function esc_attr_e( $text, $domain = 'default' ) {
	echo esc_attr( translate( $text, $domain ) );
}

/**
 * Display translated text that has been escaped for safe use in HTML output.
 *
 * @since WP-2.8.0
 *
 * @param string $text   Text to translate.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html( translate( $text, $domain ) );
}

/**
 * Retrieve translated string with gettext context.
 *
 * Quite a few times, there will be collisions with similar translatable text
 * found in more than two places, but with different translated context.
 *
 * By including the context in the pot file, translators can translate the two
 * strings differently.
 *
 * @since WP-2.8.0
 *
 * @param string $text    Text to translate.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string Translated context string without pipe.
 */
function _x( $text, $context, $domain = 'default' ) {
	return translate_with_gettext_context( $text, $context, $domain );
}

/**
 * Display translated string with gettext context.
 *
 * @since WP-3.0.0
 *
 * @param string $text    Text to translate.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string Translated context string without pipe.
 */
function _ex( $text, $context, $domain = 'default' ) {
	echo _x( $text, $context, $domain );
}

/**
 * Translate string with gettext context, and escapes it for safe use in an attribute.
 *
 * @since WP-2.8.0
 *
 * @param string $text    Text to translate.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string Translated text
 */
function esc_attr_x( $text, $context, $domain = 'default' ) {
	return esc_attr( translate_with_gettext_context( $text, $context, $domain ) );
}

/**
 * Translate string with gettext context, and escapes it for safe use in HTML output.
 *
 * @since WP-2.9.0
 *
 * @param string $text    Text to translate.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string Translated text.
 */
function esc_html_x( $text, $context, $domain = 'default' ) {
	return esc_html( translate_with_gettext_context( $text, $context, $domain ) );
}

/**
 * Translates and retrieves the singular or plural form based on the supplied number.
 *
 * Used when you want to use the appropriate form of a string based on whether a
 * number is singular or plural.
 *
 * Example:
 *
 *     printf( _n( '%s person', '%s people', $count, 'text-domain' ), number_format_i18n( $count ) );
 *
 * @since WP-2.8.0
 *
 * @param string $single The text to be used if the number is singular.
 * @param string $plural The text to be used if the number is plural.
 * @param int    $number The number to compare against to use either the singular or plural form.
 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
 *                       Default 'default'.
 * @return string The translated singular or plural form.
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	$translations = get_translations_for_domain( $domain );
	$translation  = $translations->translate_plural( $single, $plural, $number );

	/**
	 * Filters the singular or plural form of a string.
	 *
	 * @since WP-2.2.0
	 *
	 * @param string $translation Translated text.
	 * @param string $single      The text to be used if the number is singular.
	 * @param string $plural      The text to be used if the number is plural.
	 * @param string $number      The number to compare against to use either the singular or plural form.
	 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
	 */
	return apply_filters( 'ngettext', $translation, $single, $plural, $number, $domain );
}

/**
 * Translates and retrieves the singular or plural form based on the supplied number, with gettext context.
 *
 * This is a hybrid of _n() and _x(). It supports context and plurals.
 *
 * Used when you want to use the appropriate form of a string with context based on whether a
 * number is singular or plural.
 *
 * Example of a generic phrase which is disambiguated via the context parameter:
 *
 *     printf( _nx( '%s group', '%s groups', $people, 'group of people', 'text-domain' ), number_format_i18n( $people ) );
 *     printf( _nx( '%s group', '%s groups', $animals, 'group of animals', 'text-domain' ), number_format_i18n( $animals ) );
 *
 * @since WP-2.8.0
 *
 * @param string $single  The text to be used if the number is singular.
 * @param string $plural  The text to be used if the number is plural.
 * @param int    $number  The number to compare against to use either the singular or plural form.
 * @param string $context Context information for the translators.
 * @param string $domain  Optional. Text domain. Unique identifier for retrieving translated strings.
 *                        Default 'default'.
 * @return string The translated singular or plural form.
 */
function _nx( $single, $plural, $number, $context, $domain = 'default' ) {
	$translations = get_translations_for_domain( $domain );
	$translation  = $translations->translate_plural( $single, $plural, $number, $context );

	/**
	 * Filters the singular or plural form of a string with gettext context.
	 *
	 * @since WP-2.8.0
	 *
	 * @param string $translation Translated text.
	 * @param string $single      The text to be used if the number is singular.
	 * @param string $plural      The text to be used if the number is plural.
	 * @param string $number      The number to compare against to use either the singular or plural form.
	 * @param string $context     Context information for the translators.
	 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
	 */
	return apply_filters( 'ngettext_with_context', $translation, $single, $plural, $number, $context, $domain );
}

/**
 * Registers plural strings in POT file, but does not translate them.
 *
 * Used when you want to keep structures with translatable plural
 * strings and use them later when the number is known.
 *
 * Example:
 *
 *     $message = _n_noop( '%s post', '%s posts', 'text-domain' );
 *     ...
 *     printf( translate_nooped_plural( $message, $count, 'text-domain' ), number_format_i18n( $count ) );
 *
 * @since WP-2.5.0
 *
 * @param string $singular Singular form to be localized.
 * @param string $plural   Plural form to be localized.
 * @param string $domain   Optional. Text domain. Unique identifier for retrieving translated strings.
 *                         Default null.
 * @return array {
 *     Array of translation information for the strings.
 *
 *     @type string $0        Singular form to be localized. No longer used.
 *     @type string $1        Plural form to be localized. No longer used.
 *     @type string $singular Singular form to be localized.
 *     @type string $plural   Plural form to be localized.
 *     @type null   $context  Context information for the translators.
 *     @type string $domain   Text domain.
 * }
 */
function _n_noop( $singular, $plural, $domain = null ) {
	return array(
		0          => $singular,
		1          => $plural,
		'singular' => $singular,
		'plural'   => $plural,
		'context'  => null,
		'domain'   => $domain,
	);
}

/**
 * Registers plural strings with gettext context in POT file, but does not translate them.
 *
 * Used when you want to keep structures with translatable plural
 * strings and use them later when the number is known.
 *
 * Example of a generic phrase which is disambiguated via the context parameter:
 *
 *     $messages = array(
 *          'people'  => _nx_noop( '%s group', '%s groups', 'people', 'text-domain' ),
 *          'animals' => _nx_noop( '%s group', '%s groups', 'animals', 'text-domain' ),
 *     );
 *     ...
 *     $message = $messages[ $type ];
 *     printf( translate_nooped_plural( $message, $count, 'text-domain' ), number_format_i18n( $count ) );
 *
 * @since WP-2.8.0
 *
 * @param string $singular Singular form to be localized.
 * @param string $plural   Plural form to be localized.
 * @param string $context  Context information for the translators.
 * @param string $domain   Optional. Text domain. Unique identifier for retrieving translated strings.
 *                         Default null.
 * @return array {
 *     Array of translation information for the strings.
 *
 *     @type string $0        Singular form to be localized. No longer used.
 *     @type string $1        Plural form to be localized. No longer used.
 *     @type string $2        Context information for the translators. No longer used.
 *     @type string $singular Singular form to be localized.
 *     @type string $plural   Plural form to be localized.
 *     @type string $context  Context information for the translators.
 *     @type string $domain   Text domain.
 * }
 */
function _nx_noop( $singular, $plural, $context, $domain = null ) {
	return array(
		0          => $singular,
		1          => $plural,
		2          => $context,
		'singular' => $singular,
		'plural'   => $plural,
		'context'  => $context,
		'domain'   => $domain,
	);
}

/**
 * Translates and retrieves the singular or plural form of a string that's been registered
 * with _n_noop() or _nx_noop().
 *
 * Used when you want to use a translatable plural string once the number is known.
 *
 * Example:
 *
 *     $message = _n_noop( '%s post', '%s posts', 'text-domain' );
 *     ...
 *     printf( translate_nooped_plural( $message, $count, 'text-domain' ), number_format_i18n( $count ) );
 *
 * @since WP-3.1.0
 *
 * @param array  $nooped_plural Array with singular, plural, and context keys, usually the result of _n_noop() or _nx_noop().
 * @param int    $count         Number of objects.
 * @param string $domain        Optional. Text domain. Unique identifier for retrieving translated strings. If $nooped_plural contains
 *                              a text domain passed to _n_noop() or _nx_noop(), it will override this value. Default 'default'.
 * @return string Either $single or $plural translated text.
 */
function translate_nooped_plural( $nooped_plural, $count, $domain = 'default' ) {
	if ( $nooped_plural['domain'] ) {
		$domain = $nooped_plural['domain'];
	}

	if ( $nooped_plural['context'] ) {
		return _nx( $nooped_plural['singular'], $nooped_plural['plural'], $count, $nooped_plural['context'], $domain );
	} else {
		return _n( $nooped_plural['singular'], $nooped_plural['plural'], $count, $domain );
	}
}

/**
 * Load a .mo file into the text domain $domain.
 *
 * If the text domain already exists, the translations will be merged. If both
 * sets have the same string, the translation from the original value will be taken.
 *
 * On success, the .mo file will be placed in the $l10n global by $domain
 * and will be a MO object.
 *
 * @since WP-1.5.0
 *
 * @global array $l10n          An array of all currently loaded text domains.
 * @global array $l10n_unloaded An array of all text domains that have been unloaded again.
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @param string $mofile Path to the .mo file.
 * @return bool True on success, false on failure.
 */
function load_textdomain( $domain, $mofile ) {
	global $l10n, $l10n_unloaded;

	$l10n_unloaded = (array) $l10n_unloaded;

	/**
	 * Filters whether to override the .mo file loading.
	 *
	 * @since WP-2.9.0
	 *
	 * @param bool   $override Whether to override the .mo file loading. Default false.
	 * @param string $domain   Text domain. Unique identifier for retrieving translated strings.
	 * @param string $mofile   Path to the MO file.
	 */
	$plugin_override = apply_filters( 'override_load_textdomain', false, $domain, $mofile );

	if ( true == $plugin_override ) {
		unset( $l10n_unloaded[ $domain ] );

		return true;
	}

	/**
	 * Fires before the MO translation file is loaded.
	 *
	 * @since WP-2.9.0
	 *
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 * @param string $mofile Path to the .mo file.
	 */
	do_action( 'load_textdomain', $domain, $mofile );

	/**
	 * Filters MO file path for loading translations for a specific text domain.
	 *
	 * @since WP-2.9.0
	 *
	 * @param string $mofile Path to the MO file.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 */
	$mofile = apply_filters( 'load_textdomain_mofile', $mofile, $domain );

	if ( ! is_readable( $mofile ) ) {
		return false;
	}

	$mo = new MO();
	if ( ! $mo->import_from_file( $mofile ) ) {
		return false;
	}

	if ( isset( $l10n[ $domain ] ) ) {
		$mo->merge_with( $l10n[ $domain ] );
	}

	unset( $l10n_unloaded[ $domain ] );

	$l10n[ $domain ] = &$mo;

	return true;
}

/**
 * Unload translations for a text domain.
 *
 * @since WP-3.0.0
 *
 * @global array $l10n          An array of all currently loaded text domains.
 * @global array $l10n_unloaded An array of all text domains that have been unloaded again.
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @return bool Whether textdomain was unloaded.
 */
function unload_textdomain( $domain ) {
	global $l10n, $l10n_unloaded;

	$l10n_unloaded = (array) $l10n_unloaded;

	/**
	 * Filters whether to override the text domain unloading.
	 *
	 * @since WP-3.0.0
	 *
	 * @param bool   $override Whether to override the text domain unloading. Default false.
	 * @param string $domain   Text domain. Unique identifier for retrieving translated strings.
	 */
	$plugin_override = apply_filters( 'override_unload_textdomain', false, $domain );

	if ( $plugin_override ) {
		$l10n_unloaded[ $domain ] = true;

		return true;
	}

	/**
	 * Fires before the text domain is unloaded.
	 *
	 * @since WP-3.0.0
	 *
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 */
	do_action( 'unload_textdomain', $domain );

	if ( isset( $l10n[ $domain ] ) ) {
		unset( $l10n[ $domain ] );

		$l10n_unloaded[ $domain ] = true;

		return true;
	}

	return false;
}

/**
 * Load default translated strings based on locale.
 *
 * Loads the .mo file in WP_LANG_DIR constant path from ClassicPress root.
 * The translated (.mo) file is named based on the locale.
 *
 * @see load_textdomain()
 *
 * @since WP-1.5.0
 *
 * @param string $locale Optional. Locale to load. Default is the value of get_locale().
 * @return bool Whether the textdomain was loaded.
 */
function load_default_textdomain( $locale = null ) {
	if ( null === $locale ) {
		$locale = is_admin() ? get_user_locale() : get_locale();
	}

	// Unload previously loaded strings so we can switch translations.
	unload_textdomain( 'default' );

	$return = load_textdomain( 'default', WP_LANG_DIR . "/$locale.mo" );

	if ( ( is_multisite() || ( defined( 'WP_INSTALLING_NETWORK' ) && WP_INSTALLING_NETWORK ) ) && ! file_exists( WP_LANG_DIR . "/admin-$locale.mo" ) ) {
		load_textdomain( 'default', WP_LANG_DIR . "/ms-$locale.mo" );
		return $return;
	}

	if ( is_admin() || wp_installing() || ( defined( 'WP_REPAIRING' ) && WP_REPAIRING ) ) {
		load_textdomain( 'default', WP_LANG_DIR . "/admin-$locale.mo" );
	}

	if ( is_network_admin() || ( defined( 'WP_INSTALLING_NETWORK' ) && WP_INSTALLING_NETWORK ) ) {
		load_textdomain( 'default', WP_LANG_DIR . "/admin-network-$locale.mo" );
	}

	return $return;
}

/**
 * Loads a plugin's translated strings.
 *
 * If the path is not given then it will be the root of the plugin directory.
 *
 * The .mo file should be named based on the text domain with a dash, and then the locale exactly.
 *
 * @since WP-1.5.0
 * @since WP-4.6.0 The function now tries to load the .mo file from the languages directory first.
 *
 * @param string $domain          Unique identifier for retrieving translated strings
 * @param string $deprecated      Optional. Use the $plugin_rel_path parameter instead. Default false.
 * @param string $plugin_rel_path Optional. Relative path to WP_PLUGIN_DIR where the .mo file resides.
 *                                Default false.
 * @return bool True when textdomain is successfully loaded, false otherwise.
 */
function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
	/**
	 * Filters a plugin's locale.
	 *
	 * @since WP-3.0.0
	 *
	 * @param string $locale The plugin's current locale.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 */
	$locale = apply_filters( 'plugin_locale', is_admin() ? get_user_locale() : get_locale(), $domain );

	$mofile = $domain . '-' . $locale . '.mo';

	// Try to load from the languages directory first.
	if ( load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $mofile ) ) {
		return true;
	}

	if ( false !== $plugin_rel_path ) {
		$path = WP_PLUGIN_DIR . '/' . trim( $plugin_rel_path, '/' );
	} elseif ( false !== $deprecated ) {
		_deprecated_argument( __FUNCTION__, 'WP-2.7.0' );
		$path = ABSPATH . trim( $deprecated, '/' );
	} else {
		$path = WP_PLUGIN_DIR;
	}

	return load_textdomain( $domain, $path . '/' . $mofile );
}

/**
 * Load the translated strings for a plugin residing in the mu-plugins directory.
 *
 * @since WP-3.0.0
 * @since WP-4.6.0 The function now tries to load the .mo file from the languages directory first.
 *
 * @param string $domain             Text domain. Unique identifier for retrieving translated strings.
 * @param string $mu_plugin_rel_path Optional. Relative to `WPMU_PLUGIN_DIR` directory in which the .mo
 *                                   file resides. Default empty string.
 * @return bool True when textdomain is successfully loaded, false otherwise.
 */
function load_muplugin_textdomain( $domain, $mu_plugin_rel_path = '' ) {
	/** This filter is documented in wp-includes/l10n.php */
	$locale = apply_filters( 'plugin_locale', is_admin() ? get_user_locale() : get_locale(), $domain );

	$mofile = $domain . '-' . $locale . '.mo';

	// Try to load from the languages directory first.
	if ( load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $mofile ) ) {
		return true;
	}

	$path = WPMU_PLUGIN_DIR . '/' . ltrim( $mu_plugin_rel_path, '/' );

	return load_textdomain( $domain, $path . '/' . $mofile );
}

/**
 * Load the theme's translated strings.
 *
 * If the current locale exists as a .mo file in the theme's root directory, it
 * will be included in the translated strings by the $domain.
 *
 * The .mo files must be named based on the locale exactly.
 *
 * @since WP-1.5.0
 * @since WP-4.6.0 The function now tries to load the .mo file from the languages directory first.
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @param string $path   Optional. Path to the directory containing the .mo file.
 *                       Default false.
 * @return bool True when textdomain is successfully loaded, false otherwise.
 */
function load_theme_textdomain( $domain, $path = false ) {
	/**
	 * Filters a theme's locale.
	 *
	 * @since WP-3.0.0
	 *
	 * @param string $locale The theme's current locale.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 */
	$locale = apply_filters( 'theme_locale', is_admin() ? get_user_locale() : get_locale(), $domain );

	$mofile = $domain . '-' . $locale . '.mo';

	// Try to load from the languages directory first.
	if ( load_textdomain( $domain, WP_LANG_DIR . '/themes/' . $mofile ) ) {
		return true;
	}

	if ( ! $path ) {
		$path = get_template_directory();
	}

	return load_textdomain( $domain, $path . '/' . $locale . '.mo' );
}

/**
 * Load the child themes translated strings.
 *
 * If the current locale exists as a .mo file in the child themes
 * root directory, it will be included in the translated strings by the $domain.
 *
 * The .mo files must be named based on the locale exactly.
 *
 * @since WP-2.9.0
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @param string $path   Optional. Path to the directory containing the .mo file.
 *                       Default false.
 * @return bool True when the theme textdomain is successfully loaded, false otherwise.
 */
function load_child_theme_textdomain( $domain, $path = false ) {
	if ( ! $path ) {
		$path = get_stylesheet_directory();
	}
	return load_theme_textdomain( $domain, $path );
}

/**
 * Loads plugin and theme textdomains just-in-time.
 *
 * When a textdomain is encountered for the first time, we try to load
 * the translation file from `wp-content/languages`, removing the need
 * to call load_plugin_texdomain() or load_theme_texdomain().
 *
 * @since WP-4.6.0
 * @access private
 *
 * @see get_translations_for_domain()
 * @global array $l10n_unloaded An array of all text domains that have been unloaded again.
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @return bool True when the textdomain is successfully loaded, false otherwise.
 */
function _load_textdomain_just_in_time( $domain ) {
	global $l10n_unloaded;

	$l10n_unloaded = (array) $l10n_unloaded;

	// Short-circuit if domain is 'default' which is reserved for core.
	if ( 'default' === $domain || isset( $l10n_unloaded[ $domain ] ) ) {
		return false;
	}

	$translation_path = _get_path_to_translation( $domain );
	if ( false === $translation_path ) {
		return false;
	}

	return load_textdomain( $domain, $translation_path );
}

/**
 * Gets the path to a translation file for loading a textdomain just in time.
 *
 * Caches the retrieved results internally.
 *
 * @since WP-4.7.0
 * @access private
 *
 * @see _load_textdomain_just_in_time()
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @param bool   $reset  Whether to reset the internal cache. Used by the switch to locale functionality.
 * @return string|false The path to the translation file or false if no translation file was found.
 */
function _get_path_to_translation( $domain, $reset = false ) {
	static $available_translations = array();

	if ( true === $reset ) {
		$available_translations = array();
	}

	if ( ! isset( $available_translations[ $domain ] ) ) {
		$available_translations[ $domain ] = _get_path_to_translation_from_lang_dir( $domain );
	}

	return $available_translations[ $domain ];
}

/**
 * Gets the path to a translation file in the languages directory for the current locale.
 *
 * Holds a cached list of available .mo files to improve performance.
 *
 * @since WP-4.7.0
 * @access private
 *
 * @see _get_path_to_translation()
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @return string|false The path to the translation file or false if no translation file was found.
 */
function _get_path_to_translation_from_lang_dir( $domain ) {
	static $cached_mofiles = null;

	if ( null === $cached_mofiles ) {
		$cached_mofiles = array();

		$locations = array(
			WP_LANG_DIR . '/plugins',
			WP_LANG_DIR . '/themes',
		);

		foreach ( $locations as $location ) {
			$mofiles = glob( $location . '/*.mo' );
			if ( $mofiles ) {
				$cached_mofiles = array_merge( $cached_mofiles, $mofiles );
			}
		}
	}

	$locale = is_admin() ? get_user_locale() : get_locale();
	$mofile = "{$domain}-{$locale}.mo";

	$path = WP_LANG_DIR . '/plugins/' . $mofile;
	if ( in_array( $path, $cached_mofiles ) ) {
		return $path;
	}

	$path = WP_LANG_DIR . '/themes/' . $mofile;
	if ( in_array( $path, $cached_mofiles ) ) {
		return $path;
	}

	return false;
}

/**
 * Return the Translations instance for a text domain.
 *
 * If there isn't one, returns empty Translations instance.
 *
 * @since WP-2.8.0
 *
 * @global array $l10n
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @return Translations|NOOP_Translations A Translations instance.
 */
function get_translations_for_domain( $domain ) {
	global $l10n;
	if ( isset( $l10n[ $domain ] ) || ( _load_textdomain_just_in_time( $domain ) && isset( $l10n[ $domain ] ) ) ) {
		return $l10n[ $domain ];
	}

	static $noop_translations = null;
	if ( null === $noop_translations ) {
		$noop_translations = new NOOP_Translations;
	}

	return $noop_translations;
}

/**
 * Whether there are translations for the text domain.
 *
 * @since WP-3.0.0
 *
 * @global array $l10n
 *
 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
 * @return bool Whether there are translations.
 */
function is_textdomain_loaded( $domain ) {
	global $l10n;
	return isset( $l10n[ $domain ] );
}

/**
 * Translates role name.
 *
 * Since the role names are in the database and not in the source there
 * are dummy gettext calls to get them into the POT file and this function
 * properly translates them back.
 *
 * The before_last_bar() call is needed, because older installations keep the roles
 * using the old context format: 'Role name|User role' and just skipping the
 * content after the last bar is easier than fixing them in the DB. New installations
 * won't suffer from that problem.
 *
 * @since WP-2.8.0
 *
 * @param string $name The role name.
 * @return string Translated role name on success, original name on failure.
 */
function translate_user_role( $name ) {
	return translate_with_gettext_context( before_last_bar( $name ), 'User role' );
}

/**
 * Get all available languages based on the presence of *.mo files in a given directory.
 *
 * The default directory is WP_LANG_DIR.
 *
 * @since WP-3.0.0
 * @since WP-4.7.0 The results are now filterable with the {@see 'get_available_languages'} filter.
 *
 * @param string $dir A directory to search for language files.
 *                    Default WP_LANG_DIR.
 * @return array An array of language codes or an empty array if no languages are present. Language codes are formed by stripping the .mo extension from the language file names.
 */
function get_available_languages( $dir = null ) {
	$languages = array();

	$lang_files = glob( ( is_null( $dir ) ? WP_LANG_DIR : $dir ) . '/*.mo' );
	if ( $lang_files ) {
		foreach ( $lang_files as $lang_file ) {
			$lang_file = basename( $lang_file, '.mo' );
			if ( 0 !== strpos( $lang_file, 'continents-cities' ) && 0 !== strpos( $lang_file, 'ms-' ) &&
				0 !== strpos( $lang_file, 'admin-' ) ) {
				$languages[] = $lang_file;
			}
		}
	}

	/**
	 * Filters the list of available language codes.
	 *
	 * @since WP-4.7.0
	 *
	 * @param array  $languages An array of available language codes.
	 * @param string $dir       The directory where the language files were found.
	 */
	return apply_filters( 'get_available_languages', $languages, $dir );
}

/**
 * Get installed translations.
 *
 * Looks in the wp-content/languages directory for translations of
 * plugins or themes.
 *
 * @since WP-3.7.0
 *
 * @param string $type What to search for. Accepts 'plugins', 'themes', 'core'.
 * @return array Array of language data.
 */
function wp_get_installed_translations( $type ) {
	if ( $type !== 'themes' && $type !== 'plugins' && $type !== 'core' ) {
		return array();
	}

	$dir = 'core' === $type ? '' : "/$type";

	if ( ! is_dir( WP_LANG_DIR ) ) {
		return array();
	}

	if ( $dir && ! is_dir( WP_LANG_DIR . $dir ) ) {
		return array();
	}

	$files = scandir( WP_LANG_DIR . $dir );
	if ( ! $files ) {
		return array();
	}

	$language_data = array();

	foreach ( $files as $file ) {
		if ( '.' === $file[0] || is_dir( WP_LANG_DIR . "$dir/$file" ) ) {
			continue;
		}
		if ( substr( $file, -3 ) !== '.po' ) {
			continue;
		}
		if ( ! preg_match( '/(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?).po/', $file, $match ) ) {
			continue;
		}
		if ( ! in_array( substr( $file, 0, -3 ) . '.mo', $files ) ) {
			continue;
		}

		list( , $textdomain, $language ) = $match;
		if ( '' === $textdomain ) {
			$textdomain = 'default';
		}
		$language_data[ $textdomain ][ $language ] = wp_get_pomo_file_data( WP_LANG_DIR . "$dir/$file" );
	}
	return $language_data;
}

/**
 * Extract headers from a PO file.
 *
 * @since WP-3.7.0
 *
 * @param string $po_file Path to PO file.
 * @return array PO file headers.
 */
function wp_get_pomo_file_data( $po_file ) {
	$headers = get_file_data(
		$po_file,
		array(
			'POT-Creation-Date'  => '"POT-Creation-Date',
			'PO-Revision-Date'   => '"PO-Revision-Date',
			'Project-Id-Version' => '"Project-Id-Version',
			'X-Generator'        => '"X-Generator',
		)
	);
	foreach ( $headers as $header => $value ) {
		// Remove possible contextual '\n' and closing double quote.
		$headers[ $header ] = preg_replace( '~(\\\n)?"$~', '', $value );
	}
	return $headers;
}

/**
 * Language selector.
 *
 * @since WP-4.0.0
 * @since WP-4.3.0 Introduced the `echo` argument.
 * @since WP-4.7.0 Introduced the `show_option_site_default` argument.
 *
 * @see get_available_languages()
 * @see wp_get_available_translations()
 *
 * @param string|array $args {
 *     Optional. Array or string of arguments for outputting the language selector.
 *
 *     @type string   $id                           ID attribute of the select element. Default 'locale'.
 *     @type string   $name                         Name attribute of the select element. Default 'locale'.
 *     @type array    $languages                    List of installed languages, contain only the locales.
 *                                                  Default empty array.
 *     @type array    $translations                 List of available translations. Default result of
 *                                                  wp_get_available_translations().
 *     @type string   $selected                     Language which should be selected. Default empty.
 *     @type bool|int $echo                         Whether to echo the generated markup. Accepts 0, 1, or their
 *                                                  boolean equivalents. Default 1.
 *     @type bool     $show_available_translations  Whether to show available translations. Default true.
 *     @type bool     $show_option_site_default     Whether to show an option to fall back to the site's locale. Default false.
 * }
 * @return string HTML content
 */
function wp_dropdown_languages( $args = array() ) {

	$parsed_args = wp_parse_args(
		$args,
		array(
			'id'                          => 'locale',
			'name'                        => 'locale',
			'languages'                   => array(),
			'translations'                => array(),
			'selected'                    => '',
			'echo'                        => 1,
			'show_available_translations' => true,
			'show_option_site_default'    => false,
		)
	);

	// Bail if no ID or no name.
	if ( ! $parsed_args['id'] || ! $parsed_args['name'] ) {
		return;
	}

	// English (United States) uses an empty string for the value attribute.
	if ( 'en_US' === $parsed_args['selected'] ) {
		$parsed_args['selected'] = '';
	}

	$translations = $parsed_args['translations'];
	if ( empty( $translations ) ) {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$translations = wp_get_available_translations();
	}

	/*
	 * $parsed_args['languages'] should only contain the locales. Find the locale in
	 * $translations to get the native name. Fall back to locale.
	 */
	$languages = array();
	foreach ( $parsed_args['languages'] as $locale ) {
		if ( isset( $translations[ $locale ] ) ) {
			$translation = $translations[ $locale ];
			$languages[] = array(
				'language'    => $translation['language'],
				'native_name' => $translation['native_name'],
				'lang'        => current( $translation['iso'] ),
			);

			// Remove installed language from available translations.
			unset( $translations[ $locale ] );
		} else {
			$languages[] = array(
				'language'    => $locale,
				'native_name' => $locale,
				'lang'        => '',
			);
		}
	}

	$translations_available = ( ! empty( $translations ) && $parsed_args['show_available_translations'] );

	// Holds the HTML markup.
	$structure = array();

	// List installed languages.
	if ( $translations_available ) {
		$structure[] = '<optgroup label="' . esc_attr_x( 'Installed', 'translations' ) . '">';
	}

	// Site default.
	if ( $parsed_args['show_option_site_default'] ) {
		$structure[] = sprintf(
			'<option value="site-default" data-installed="1"%s>%s</option>',
			selected( 'site-default', $parsed_args['selected'], false ),
			_x( 'Site Default', 'default site language' )
		);
	}

	// Always show English.
	$structure[] = sprintf(
		'<option value="" lang="en" data-installed="1"%s>English (United States)</option>',
		selected( '', $parsed_args['selected'], false )
	);

	// List installed languages.
	foreach ( $languages as $language ) {
		$structure[] = sprintf(
			'<option value="%s" lang="%s"%s data-installed="1">%s</option>',
			esc_attr( $language['language'] ),
			esc_attr( $language['lang'] ),
			selected( $language['language'], $parsed_args['selected'], false ),
			esc_html( $language['native_name'] )
		);
	}
	if ( $translations_available ) {
		$structure[] = '</optgroup>';
	}

	// List available translations.
	if ( $translations_available ) {
		$structure[] = '<optgroup label="' . esc_attr_x( 'Available', 'translations' ) . '">';
		foreach ( $translations as $translation ) {
			$structure[] = sprintf(
				'<option value="%s" lang="%s"%s>%s</option>',
				esc_attr( $translation['language'] ),
				esc_attr( current( $translation['iso'] ) ),
				selected( $translation['language'], $parsed_args['selected'], false ),
				esc_html( $translation['native_name'] )
			);
		}
		$structure[] = '</optgroup>';
	}

	// Combine the output string.
	$output  = sprintf( '<select name="%s" id="%s">', esc_attr( $parsed_args['name'] ), esc_attr( $parsed_args['id'] ) );
	$output .= join( "\n", $structure );
	$output .= '</select>';

	if ( $parsed_args['echo'] ) {
		echo $output;
	}

	return $output;
}

/**
 * Checks if current locale is RTL.
 *
 * @since WP-3.0.0
 *
 * @global WP_Locale $wp_locale
 *
 * @return bool Whether locale is RTL.
 */
function is_rtl() {
	global $wp_locale;
	if ( ! ( $wp_locale instanceof WP_Locale ) ) {
		return false;
	}
	return $wp_locale->is_rtl();
}

/**
 * Switches the translations according to the given locale.
 *
 * @since WP-4.7.0
 *
 * @global WP_Locale_Switcher $wp_locale_switcher
 *
 * @param string $locale The locale.
 * @return bool True on success, false on failure.
 */
function switch_to_locale( $locale ) {
	/* @var WP_Locale_Switcher $wp_locale_switcher */
	global $wp_locale_switcher;

	return $wp_locale_switcher->switch_to_locale( $locale );
}

/**
 * Restores the translations according to the previous locale.
 *
 * @since WP-4.7.0
 *
 * @global WP_Locale_Switcher $wp_locale_switcher
 *
 * @return string|false Locale on success, false on error.
 */
function restore_previous_locale() {
	/* @var WP_Locale_Switcher $wp_locale_switcher */
	global $wp_locale_switcher;

	return $wp_locale_switcher->restore_previous_locale();
}

/**
 * Restores the translations according to the original locale.
 *
 * @since WP-4.7.0
 *
 * @global WP_Locale_Switcher $wp_locale_switcher
 *
 * @return string|false Locale on success, false on error.
 */
function restore_current_locale() {
	/* @var WP_Locale_Switcher $wp_locale_switcher */
	global $wp_locale_switcher;

	return $wp_locale_switcher->restore_current_locale();
}

/**
 * Whether switch_to_locale() is in effect.
 *
 * @since WP-4.7.0
 *
 * @global WP_Locale_Switcher $wp_locale_switcher
 *
 * @return bool True if the locale has been switched, false otherwise.
 */
function is_locale_switched() {
	/* @var WP_Locale_Switcher $wp_locale_switcher */
	global $wp_locale_switcher;

	return $wp_locale_switcher->is_switched();
}

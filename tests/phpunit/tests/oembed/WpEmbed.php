<?php

/**
 * @group oembed
 */
class Tests_WP_Embed extends WP_UnitTestCase {
	/**
	 * @var WP_Embed
	 */
	protected $wp_embed;

	public function setUp() {
		$this->wp_embed = new WP_Embed();
	}

	public function _embed_handler_callback( $matches, $attr, $url, $rawattr ) {
		return sprintf( 'Embedded %s', $url );
	}

	public function _pre_oembed_result_callback() {
		return '<b>Embedded content</b>';
	}

	public function test_maybe_run_ajax_cache_should_return_nothing_if_there_is_no_post() {
		$this->expectOutputString( '' );
		$this->wp_embed->maybe_run_ajax_cache();
	}

	public function test_maybe_run_ajax_cache_should_return_nothing_if_there_is_no_message() {
		$GLOBALS['post'] = $this->factory()->post->create_and_get(
			array(
				'post_title' => 'Hello World',
			)
		);

		$this->expectOutputString( '' );

		$this->wp_embed->maybe_run_ajax_cache();
		unset( $GLOBALS['post'] );
	}

	public function test_maybe_run_ajax_cache_should_return_javascript() {
		$GLOBALS['post'] = $this->factory()->post->create_and_get(
			array(
				'post_title' => 'Hello World',
			)
		);
		$_GET['message'] = 'foo';

		$url    = admin_url( 'admin-ajax.php?action=oembed-cache&post=' . $GLOBALS['post']->ID, 'relative' );
		$actual = get_echo( array( $this->wp_embed, 'maybe_run_ajax_cache' ) );

		unset( $GLOBALS['post'] );
		unset( $GLOBALS['message'] );

		$this->assertContains( $url, $actual );
	}

	public function test_wp_maybe_load_embeds() {
		$this->assertEqualSets( array( 10, 9999 ), array_keys( $GLOBALS['wp_embed']->handlers ) );
		$this->assertEqualSets(
			array(
				'youtube_embed_url',
			),
			array_keys( $GLOBALS['wp_embed']->handlers[10] )
		);
		$this->assertEqualSets(
			array(
				'audio',
				'video',
			),
			array_keys( $GLOBALS['wp_embed']->handlers[9999] )
		);
	}

	public function test_wp_embed_register_handler() {
		$handle   = __FUNCTION__;
		$regex    = '#https?://example\.com/embed/([^/]+)#i';
		$callback = array( $this, '_embed_handler_callback' );

		wp_embed_register_handler( $handle, $regex, $callback );

		$expected = array(
			'regex'    => $regex,
			'callback' => $callback,
		);
		$actual   = $GLOBALS['wp_embed']->handlers[10];

		wp_embed_unregister_handler( $handle );

		$this->assertContains( $expected, $actual );
	}

	public function test_wp_embed_unregister_handler() {
		$this->assertArrayHasKey( 'youtube_embed_url', $GLOBALS['wp_embed']->handlers[10] );

		wp_embed_unregister_handler( 'youtube_embed_url' );

		$handlers = $GLOBALS['wp_embed']->handlers[10];

		// Restore.
		wp_embed_register_handler( 'youtube_embed_url', '#https?://(www.)?youtube\.com/(?:v|embed)/([^/]+)#i', 'wp_embed_handler_youtube' );

		$this->assertArrayNotHasKey( 'youtube_embed_url', $handlers );
	}

	public function test_autoembed_should_do_nothing_without_matching_handler() {
		$content = "\nhttp://example.com/embed/foo\n";

		$actual = $this->wp_embed->autoembed( $content );
		$this->assertEquals( $content, $actual );
	}

	public function test_autoembed_should_return_modified_content() {
		$handle   = __FUNCTION__;
		$regex    = '#https?://example\.com/embed/([^/]+)#i';
		$callback = array( $this, '_embed_handler_callback' );

		wp_embed_register_handler( $handle, $regex, $callback );

		$content = "\nhttp://example.com/embed/foo\n";

		$actual = $GLOBALS['wp_embed']->autoembed( $content );
		wp_embed_unregister_handler( $handle );

		$this->assertEquals( "\nEmbedded http://example.com/embed/foo\n", $actual );
	}

	public function test_delete_oembed_caches() {
		$post_id = $this->factory()->post->create();

		add_post_meta( $post_id, '_oembed_foo', 'bar' );
		add_post_meta( $post_id, '_oembed_foo', 'baz' );
		add_post_meta( $post_id, '_oembed_baz', 'foobar', true );

		$this->wp_embed->delete_oembed_caches( $post_id );

		$this->assertEquals( array(), get_post_meta( $post_id, '_oembed_foo' ) );
		$this->assertEquals( array(), get_post_meta( $post_id, '_oembed_baz' ) );
	}

	public function test_cache_oembed_invalid_post_type() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'nav_menu_item' ) );

		$this->wp_embed->cache_oembed( $post_id );
		$this->assertNotSame( $post_id, $this->wp_embed->post_ID );
	}

	public function test_cache_oembed_empty_content() {
		$post_id = $this->factory()->post->create( array( 'post_content' => '' ) );

		$this->wp_embed->cache_oembed( $post_id );
		$this->assertNotSame( $post_id, $this->wp_embed->post_ID );
	}

	public function test_cache_oembed_for_post() {
		$url           = 'https://example.com/';
		$expected      = '<b>Embedded content</b>';
		$key_suffix    = md5( $url . serialize( wp_embed_defaults( $url ) ) );
		$cachekey      = '_oembed_' . $key_suffix;
		$cachekey_time = '_oembed_time_' . $key_suffix;

		$post_id = $this->factory()->post->create( array( 'post_content' => 'https://example.com/' ) );

		add_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );
		$this->wp_embed->cache_oembed( $post_id );
		remove_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );

		$this->assertSame( $post_id, $this->wp_embed->post_ID );
		$this->assertEquals( $expected, get_post_meta( $post_id, $cachekey, true ) );
		$this->assertNotEmpty( get_post_meta( $post_id, $cachekey_time, true ) );
	}

	public function test_shortcode_should_get_cached_data_from_post_meta_for_known_post() {
		global $post;

		$post       = $this->factory()->post->create_and_get();
		$url        = 'https://example.com/';
		$expected   = '<b>Embedded content</b>';
		$key_suffix = md5( $url . serialize( wp_embed_defaults( $url ) ) );
		$cachekey   = '_oembed_' . $key_suffix;

		add_post_meta( $post->ID, $cachekey, $expected );

		add_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );
		$actual = $this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );

		$actual_2 = $this->wp_embed->shortcode( array(), $url );

		$cached = get_post_meta( $post->ID, $cachekey, true );

		// Cleanup.
		unset( $post );

		$this->assertEquals( $expected, $actual );
		$this->assertEquals( $expected, $actual_2 );
		$this->assertEquals( $expected, $cached );
	}

	public function test_shortcode_should_get_cached_failure_from_post_meta_for_known_post() {
		global $post;

		$post          = $this->factory()->post->create_and_get();
		$url           = 'https://example.com/';
		$expected      = '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
		$key_suffix    = md5( $url . serialize( wp_embed_defaults( $url ) ) );
		$cachekey      = '_oembed_' . $key_suffix;
		$cachekey_time = '_oembed_time_' . $key_suffix;

		add_post_meta( $post->ID, $cachekey, '{{unknown}}' );
		add_post_meta( $post->ID, $cachekey_time, 0 );

		add_filter( 'pre_oembed_result', '__return_empty_string' );
		$actual = $this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', '__return_empty_string' );

		// Result should be cached.
		$actual_2 = $this->wp_embed->shortcode( array(), $url );

		$cached      = get_post_meta( $post->ID, $cachekey, true );
		$cached_time = get_post_meta( $post->ID, $cachekey_time, true );

		// Cleanup.
		unset( $post );

		$this->assertEquals( $expected, $actual );
		$this->assertEquals( '{{unknown}}', $cached );
		$this->assertEmpty( $cached_time );
		$this->assertEquals( $expected, $actual_2 );
	}

	/**
	 * @see https://core.trac.wordpress.org/ticket/34115
	 */
	public function test_shortcode_should_cache_data_in_custom_post() {
		$url        = 'https://example.com/';
		$expected   = '<b>Embedded content</b>';
		$key_suffix = md5( $url . serialize( wp_embed_defaults( $url ) ) );

		add_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );
		$actual = $this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );

		$oembed_post_id = $this->wp_embed->find_oembed_post_id( $key_suffix );
		$post_content   = get_post( $oembed_post_id )->post_content;

		// Result should be cached.
		$actual_2 = $this->wp_embed->shortcode( array(), $url );

		wp_delete_post( $oembed_post_id );

		$this->assertNotNull( $oembed_post_id );
		$this->assertEquals( $expected, $post_content );
		$this->assertEquals( $expected, $actual );
		$this->assertEquals( $expected, $actual_2 );
	}

	/**
	 * @see https://core.trac.wordpress.org/ticket/34115
	 */
	public function test_shortcode_should_cache_failure_in_custom_post() {
		$url        = 'https://example.com/';
		$expected   = '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
		$key_suffix = md5( $url . serialize( wp_embed_defaults( $url ) ) );

		add_filter( 'pre_oembed_result', '__return_empty_string' );
		$actual = $this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', '__return_empty_string' );

		$oembed_post_id = $this->wp_embed->find_oembed_post_id( $key_suffix );
		$post_content   = get_post( $oembed_post_id )->post_content;

		// Result should be cached.
		$actual_2 = $this->wp_embed->shortcode( array(), $url );

		wp_delete_post( $oembed_post_id );

		$this->assertEquals( $expected, $actual );
		$this->assertEquals( $expected, $actual_2 );
		$this->assertNotNull( $oembed_post_id );
		$this->assertEquals( '{{unknown}}', $post_content );
	}

	/**
	 * Test that parsing an embed shortcode should cause oembed_cache to be updated.
	 *
	 * @see https://core.trac.wordpress.org/ticket/42310
	 */
	public function test_shortcode_should_update_custom_post() {
		add_filter( 'oembed_ttl', '__return_zero' );

		$url        = 'https://example.com/';
		$embedded   = '<b>Embedded content</b>';
		$key_suffix = md5( $url . serialize( wp_embed_defaults( $url ) ) );

		add_filter( 'pre_oembed_result', '__return_empty_string' );
		$this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', '__return_empty_string' );

		$oembed_post_id = $this->wp_embed->find_oembed_post_id( $key_suffix );

		$this->assertSame( '{{unknown}}', get_post( $oembed_post_id )->post_content );

		$previous_usecache        = $this->wp_embed->usecache;
		$this->wp_embed->usecache = false;

		// The update cannot be empty because empty responses won't overwrite the cache.
		add_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );
		$this->wp_embed->shortcode( array(), $url );
		remove_filter( 'pre_oembed_result', array( $this, '_pre_oembed_result_callback' ) );

		$this->assertSame( $embedded, get_post( $oembed_post_id )->post_content );

		$this->wp_embed->usecache = $previous_usecache;
		remove_filter( 'oembed_ttl', '__return_zero' );
	}

	public function test_shortcode_should_get_url_from_src_attribute() {
		$url    = 'http://example.com/embed/foo';
		$actual = $this->wp_embed->shortcode( array( 'src' => $url ) );

		$this->assertEquals( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $actual );
	}

	public function test_shortcode_should_return_empty_string_for_missing_url() {
		$this->assertEmpty( $this->wp_embed->shortcode( array() ) );
	}

	public function test_shortcode_should_make_link_for_unknown_url() {
		$url    = 'http://example.com/embed/foo';
		$actual = $this->wp_embed->shortcode( array(), $url );

		$this->assertEquals( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $actual );
	}

	public function test_run_shortcode_url_only() {
		$url    = 'http://example.com/embed/foo';
		$actual = $this->wp_embed->run_shortcode( '[embed]' . $url . '[/embed]' );
		$this->assertEquals( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $actual );
	}

	public function test_maybe_make_link() {
		$url    = 'http://example.com/embed/foo';
		$actual = $this->wp_embed->maybe_make_link( $url );

		$this->assertEquals( '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $actual );
	}

	public function test_maybe_make_link_return_false_on_fail() {
		$this->wp_embed->return_false_on_fail = true;
		$this->assertFalse( $this->wp_embed->maybe_make_link( 'http://example.com/' ) );
	}

	public function test_maybe_make_link_do_not_link_if_unknown() {
		$url = 'http://example.com/';

		$this->wp_embed->linkifunknown = false;
		$this->assertEquals( $url, $this->wp_embed->maybe_make_link( $url ) );
	}
}

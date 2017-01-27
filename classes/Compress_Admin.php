<?php


 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');

class Compress_Admin {
	const SLUG = 'compress';
	const NONCE = 'compress-nonce';

	/** @var Compress_Admin */
	private static $instance;

	private function add_hooks() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ), 10, 0 );
		add_action( 'load-settings_page_'.self::SLUG, array( $this, 'process_action' ), 10, 0 );
	}

	public function process_action() {
		if ( !empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], self::NONCE) ) {
			switch ( $_REQUEST['action'] ) {
				case 'flush':
					$shrinker = Compress::get_instance();
					$shrinker->flush_cache();
					add_settings_error(
						self::SLUG,
						self::SLUG,
						__('Caches flushed', 'compress'),
						'updated'
					);
					set_transient('settings_errors', get_settings_errors());
					break;
			}
			wp_redirect(add_query_arg('settings-updated', 'true', $this->admin_url()));
			exit();
		}
	}

	public function register_settings_page() {
		add_options_page(
			__( 'JavaScript/CSS Minification Settings', 'compress' ),
			__( 'Compress', 'compress' ),
			'manage_options',
			self::SLUG,
			array($this, 'display_settings_page')
		);

		add_settings_section(
			'flush',
			__( 'Flush Cache', 'compress' ),
			array($this, 'display_flush_section'),
			self::SLUG
		);

		add_settings_section(
			'default',
			__( 'Configuration', 'compress' ),
			array($this, 'display_settings_section'),
			self::SLUG
		);

		add_settings_field(
			Compress_Option::SHRINK_CSS,
			__( 'Compress CSS', 'compress' ),
			array( $this, 'display_css_field' ),
			self::SLUG
		);
		add_settings_field(
			Compress_Option::SHRINK_JS,
			__( 'Compress JavaScript', 'compress' ),
			array( $this, 'display_js_field' ),
			self::SLUG
		);
		add_settings_field(
			Compress_Option::EXCLUSIONS,
			__( 'Excluded Page Urls', 'compress' ),
			[ $this, 'display_exclusions_field' ],
			self::SLUG
		);
		register_setting(
			self::SLUG,
			Compress_Option::SHRINK_CSS
		);
		register_setting(
			self::SLUG,
			Compress_Option::SHRINK_JS
		);
		register_setting(
			self::SLUG,
			Compress_Option::EXCLUSIONS
		);
	}

	public function display_settings_page() {
		$title = __( 'JavaScript/CSS Minification Settings', 'compress' );
		ob_start();
		echo "<form action='".admin_url('options.php')."' method='post'>";
		settings_fields( self::SLUG );
		do_settings_sections(self::SLUG);
		submit_button();
		echo "</form>";
		$content = ob_get_clean();
		include( Compress::plugin_path('views/settings-page-wrapper.php') );
	}

	public function display_settings_section() {

	}


	public function display_exclusions_field() {
		$current = Compress_Option::get_exclusions();

		?>
        <p>
            <textarea name="<?php echo Compress_Option::EXCLUSIONS; ?>" class="regular-text" ><?php echo implode( ',', $current ); ?></textarea>
        </p>
        <p class="description">
			<?php _e( 'These page will receive the uncompress/concatenated css and js.', 'compress' ); ?>
        </p>
        <p class="description">
            <?php _e( 'Urls relative to root of site.Comma Separated. RegEx e.g. ^invoice[\S]*', 'compress' ); ?>
        </p>
		<?php
	}

	public function display_css_field() {
		$current = Compress_Option::get_css_setting();
		?>
		<p><select name="<?php echo Compress_Option::SHRINK_CSS; ?>">
				<option value="compress" <?php selected( $current, 'compress' ); ?>><?php _e('Compress and Concatenate', 'compress'); ?></option>
				<option value="concatenate" <?php selected( $current, 'concatenate' ); ?>><?php _e('Concatenate', 'compress'); ?></option>
				<option value="none" <?php selected( $current, 'none' ); ?>><?php _e('None (Leave as is)', 'compress'); ?></option>
			</select></p>
		<?php
	}

	public function display_js_field() {
		$current = Compress_Option::get_js_setting();
		?>
		<p><select name="<?php echo Compress_Option::SHRINK_JS; ?>">
				<option value="compress" <?php selected( $current, 'compress' ); ?>><?php _e('Compress and Concatenate', 'compress'); ?></option>
				<option value="concatenate" <?php selected( $current, 'concatenate' ); ?>><?php _e('Concatenate', 'compress'); ?></option>
				<option value="none" <?php selected( $current, 'none' ); ?>><?php _e('None (Leave as is)', 'compress'); ?></option>
			</select></p>
		<?php
	}

	public function display_flush_section() {
		printf( '<p>%s</p>',
		__('Click this button to delete all compressed JavaScript and CSS files from the cache.', 'compress'));
		$this->flush_button();
	}

	private function flush_button() {
		printf(
			'<a class="button secondary" href="%s">%s</a>',
			wp_nonce_url(add_query_arg(array('action' => 'flush'), $this->admin_url()), self::NONCE),
			__( 'Flush Cache', 'compress' )
		);
	}

	private function admin_url() {
		return add_query_arg(array('page' => self::SLUG), admin_url('options-general.php'));
	}

	/********** Singleton *************/

	/**
	 * Create the instance of the class
	 *
	 * @static
	 * @return void
	 */
	public static function init() {
		self::$instance = self::get_instance();
	}

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 * @static
	 * @return Compress_Admin
	 */
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	final public function __clone() {
		trigger_error( "No cloning allowed!", E_USER_ERROR );
	}

	final public function __sleep() {
		trigger_error( "No serialization allowed!", E_USER_ERROR );
	}

	protected function __construct() {
		$this->add_hooks();
	}
}

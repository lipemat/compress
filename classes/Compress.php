<?php

 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');


class Compress {
	const CACHE_FOLDER = 'resources';
	const CRON_HOOK = 'compress_cron';
	const DEBUG = FALSE;

	private $cache_dir = '';
	private $cache_url = '';
	private $cache_queue = array();

	/** @var Compress */
	private static $instance;

	private function add_hooks() {
		if ( !is_admin() ) {
			add_action( 'init', array( $this, 'setup_globals' ), -100, 0 );
			add_action( 'shutdown', array( $this, 'maybe_queue' ), 10, 0 );
			add_action( self::CRON_HOOK, array( $this, 'maybe_concatenate' ), 0, 0 );
			add_action( 'wp', array( $this, 'clear_cache_on_404' ), 10, 1 );
		}
	}

	/**
	 * Setup our WP_Scripts object in place of the default
	 */
	public function setup_globals() {
		global $wp_scripts, $wp_styles;
		$scripts_option = Compress_Option::get_js_setting();
		if ( $scripts_option != Compress_Option::SHRINK_OPT_NONE ) {
			$wp_scripts = new Compress_WP_Scripts();
		}
		$style_option = Compress_Option::get_css_setting();
		if ( $style_option != Compress_Option::SHRINK_OPT_NONE ) {
			$wp_styles = new Compress_WP_Styles();
		}
	}

	public function flush_cache() {
		$cache_dir = $this->cache_path();
		if ( $cache_dir && file_exists($cache_dir) && is_dir($cache_dir) ) {
			if ( $handle = opendir($cache_dir) ) {
				while ( ( $file = readdir($handle) ) !== FALSE ) {
					if ( strpos($file, '.') !== 0 && file_exists($cache_dir.$file) ) {
						@unlink($cache_dir.$file);
					}
				}
				closedir($handle);
			}
		}
		$this->flush_page_cache();
	}


	public function cache_url( $file = '' ) {
		return trailingslashit($this->cache_url).$file;
	}

	public function cache_path( $file = '' ) {
		return $this->cache_dir.DIRECTORY_SEPARATOR.$file;
	}

	public function get_js_url( array $scripts ) {
		$hash_array = $scripts;
		// account for compression setting in building the cache
		$hash_array['compression'] = Compress_Option::get_js_setting();
		$hash = $this->get_hash( $hash_array );
		$filename = $hash.'.js';
		if ( $this->cache_file_exists($filename) ) {
			return $this->cache_url($filename);
		} else {
			$this->print_raw_js( $scripts );
			$this->cache_queue[] = array(
				'type' => 'js',
				'filename' => $filename,
				'data' => $scripts
			);
			do_action('do_not_cache');
			return false;
		}
	}

	private function print_raw_js( array $scripts ) {
		foreach ( $scripts as $src ) {
			echo "<script type='text/javascript' src='$src'></script>\n";
		}
	}

	public function get_css_url( array $stylesheets, $media = 'screen', $condition = FALSE ) {
		$hash_array = $stylesheets;
		// account for compression setting in building the cache
		$hash_array['compression'] = Compress_Option::get_css_setting();
		$hash = $this->get_hash( $hash_array );
		$filename = $hash.'.css';
		if ( $this->cache_file_exists($filename) ) {
			return $this->cache_url($filename);
		} else {
			$this->print_raw_css( $stylesheets, $media, $condition );
			$this->cache_queue[] = array(
				'type' => 'css',
				'filename' => $filename,
				'data' => $stylesheets
			);
			do_action('do_not_cache');
			return false;
		}
	}

	private function print_raw_css( array $stylesheets, $media = 'screen', $condition = FALSE ) {
		foreach ( $stylesheets as $src ) {
			$tag = "<link rel='stylesheet' type='text/css' href='$src' media='$media' />\n";
			if ( $condition ) {
				$tag = "<!--[if $condition]>\n".$tag."<![endif]-->\n";
			}
			echo $tag;
		}
	}

	private function get_hash( array $resources ) {
		$string = serialize($resources);
		return md5($string);
	}

	private function cache_file_exists( $filename ) {
		$path = $this->cache_path($filename);
		if ( self::DEBUG ) {
			return FALSE;
		}
		return file_exists($path);
	}

	private function get_file_contents( $url ) {
		// Ensure that local files are referred to as complete urls.
		if( ('http' != substr( $url, 0, 4 ) ) ){
			if( "//" == substr( $url, 0, 2 ) ){
				$url = is_ssl() ? "https:$url" : "http:$url";
			} else {
				$url = home_url( $url );
			}
		}

		// If this url exists on local disk, read it from local disk
		if (is_int(strpos($url, home_url()))) {
			$local_path = ABSPATH.substr($url,strlen(home_url()));
		}

		$data = '';

		// If local file exists read it.
		if (isset($local_path) && file_exists($local_path)) {
			$file = file_get_contents($local_path);
			if ($file) {
				$data = $file;
			}
			unset($local_path);
		}

		// If file is not local, then retrieve from external source.
		if ( !isset($file) ) {
			$file = wp_remote_request($url);
			if ( is_wp_error($file) ) {
				error_log(  "File read error on $url" );
				do_action('log', "File read error on $url", 'minification');
			} else {
				$data = $file['body'];
			}
		}

		return $data;
	}

	public function maybe_queue() {
		if ( !empty( $this->cache_queue ) ) {
			$transient = get_transient('compress_minification_queue');
			if ( empty($transient) ) { $transient = array(); }
			foreach ( $this->cache_queue as $queue ) {
				if ( $queue && isset( $queue['filename'] ) ) {
					$transient[$queue['filename']] = $queue['data'];
				}
			}
			set_transient('compress_minification_queue', $transient);
			unset( $this->cache_queue );
			wp_schedule_single_event(time(), self::CRON_HOOK);
		}
	}

	/**
	 * Delay Concatenation / Processing
	 */
	public function maybe_concatenate() {
		$transient = get_transient('compress_minification_queue');
		if ( is_array( $transient ) ) {
			foreach ( $transient as $filename => $data ) {
				if ( $data && is_array($data) ) {
					if ( substr($filename, -2) == 'js' ) {
						$this->create_js_cache($filename, $data);
					} elseif ( substr($filename, -3) == 'css' ) {
						$this->create_css_cache($filename, $data);
					}
				}
			}
		}
		delete_transient('compress_minification_queue');
	}

	/**
	 * @param WP $wp
	 */
	public function clear_cache_on_404( $wp ) {
		if ( !is_404() ) {
			return; // nothing to do here
		}
		$cache_url = $this->cache_url();
		if( !empty( $_SERVER['SCRIPT_URI'] ) ){
			if( strpos( $_SERVER[ 'SCRIPT_URI' ], $cache_url ) == 0 ){
				$this->flush_page_cache();
			}
		}
	}

	public function flush_page_cache() {
		if ( function_exists('wp_cache_clean_cache') ) {
			global $file_prefix, $supercachedir;
			if ( !$supercachedir ) {
				$supercachedir = get_supercache_dir();
			}
			@wp_cache_clean_cache($file_prefix, TRUE);
		}
	}

	private function create_js_cache( $filename, $scripts ) {
		$concatenated = $this->concatenate_js($scripts);

		$path = $this->cache_path($filename);
		file_put_contents($path, $concatenated);
	}

	private function concatenate_js( $scripts ) {
		$concatenated = '';
		require_once( self::plugin_path('includes/JShrink/Minifier.php') );

		foreach ($scripts as $handle => $url) {

			$concatenated .= "\n\n/**************** SHRINK : $handle ****************/\n";
			$concatenated .= "/**************** $url ****************/\n\n;";

			$data = $this->get_file_contents($url);

			if ( !empty( $data ) ) {
				$script_processing = Compress_Option::get_js_setting();
				if ( $script_processing == Compress_Option::SHRINK_OPT_COMPRESS ) {
					try {
						if ( $minified = trim( \JShrink\Minifier::minify( $data ) ) ) {
							$data = $minified;
						}
					} catch ( Exception $e ) {
						error_log("Could not minify script: $url");
					}
				}
				$concatenated .= $data;
			}

			unset( $file );
			unset( $data );
		}

		return $concatenated;
	}

	private function create_css_cache( $filename, $stylesheets ) {
		$concatenated = $this->concatenate_css($stylesheets);

		$path = $this->cache_path($filename);
		file_put_contents($path, $concatenated);
	}

	private function concatenate_css( $stylesheets ) {
		$concatenated = '';

		foreach ($stylesheets as $handle => $url) {

			$concatenated .= "\n\n/**************** SHRINK : $handle ****************/\n";
			$concatenated .= "/**************** $url ****************/\n\n";

			$data = $this->get_file_contents($url);

			if ( !empty( $data ) ) {
				$style_processing = Compress_Option::get_css_setting();
				if ( $style_processing == Compress_Option::SHRINK_OPT_COMPRESS ) {
					if ( $optimized = $this->optimize_css( $data ) ) {
						$data = $optimized;
					}
				}

				if ( $this->is_local_url($url) ) {
					// search $data for "url(" where the next 4 characters are not "http" and replace with an accurate relative URL
					$relative_path = trailingslashit($this->relative_url(parse_url($this->cache_url(), PHP_URL_PATH), parse_url(substr( $url, 0, strrpos($url,'/') + 1 ), PHP_URL_PATH)));
				} else {
					$relative_path = trailingslashit(dirname($url));
				}
				$data = preg_replace('/url\(([\'\"])?([^hd\/])/',"url($1$relative_path$2",$data);

				$concatenated .= $data;
			}

			unset( $file );
			unset( $data );
		}

		return $concatenated;
	}

	/**
	 * Determine if the URL is for something hosted on the same site
	 *
	 * @param string $url
	 * @return bool
	 */
	private function is_local_url( $url ) {
		if ( substr($url, 0, 4) != 'http' && substr($url, 0, 2) != '//' ) {
			return TRUE; // local relative path
		}
		$url_domain = parse_url($url, PHP_URL_HOST);
		$request_domain = $_SERVER['HTTP_HOST'];
		if ( function_exists('domain_mapping_siteurl') ) {
			$mapped_domain = parse_url(domain_mapping_siteurl(FALSE), PHP_URL_HOST);
		} else {
			$mapped_domain = FALSE;
		}
		if ( $url_domain == $request_domain || $url_domain == $mapped_domain ) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Calculate the relative path from $from to $to
	 *
	 * @see http://www.php.net/manual/en/function.realpath.php#105876
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $ps Path separator
	 * @return string
	 */
	private	function relative_url($from, $to, $ps = '/') {
		$arFrom = explode($ps, rtrim($from, $ps));
		$arTo = explode($ps, rtrim($to, $ps));
		while(count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
			array_shift($arFrom);
			array_shift($arTo);
		}
		return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
	}


	/**
	 * CSS Optimization helper
	 *
	 * @param string $css
	 * @return string $css
	 * @access private
	 * @since 1.0
	 */
	private function optimize_css( $css ) {
		require_once(self::plugin_path('includes/csstidy/class.csstidy.php'));
		$csstidy = new csstidy();
		$csstidy->set_cfg('optimise_shorthands', false);
		$csstidy->set_cfg('compress_colors', false);
		$csstidy->set_cfg('compress_font-weight', true);
		$csstidy->set_cfg('remove_bslash', true);
		$csstidy->set_cfg('merge_selectors', 0);
		$csstidy->set_cfg('discard_invalid_properties', false);
		$csstidy->load_template('highest_compression');
		$csstidy->parse($css);
		$css = $csstidy->print->plain();
		unset($csstidy);
		$css = trim($css);
		return $css;
	}


	/**
	 * Get the absolute system path to the plugin directory, or a file therein
	 * @static
	 * @param string $path
	 * @return string
	 */
	public static function plugin_path( $path ) {
		$base = dirname(dirname(__FILE__));
		if ( $path ) {
			return trailingslashit($base).$path;
		} else {
			return untrailingslashit($base);
		}
	}

	/**
	 * Get the absolute URL to the plugin directory, or a file therein
	 * @static
	 * @param string $path
	 * @return string
	 */
	public static function plugin_url( $path, $version = NULL ) {
		$path = plugins_url($path, dirname(__FILE__));
		if ( !is_null($version) ) {
			$path = add_query_arg(array('version' => $version), $path);
		}
		return $path;
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
	 * @return Compress
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
		$this->cache_dir = WP_CONTENT_DIR . '/uploads/cache/' . self::CACHE_FOLDER . '/' . get_current_blog_id();
		wp_mkdir_p($this->cache_dir);
		$this->cache_url = WP_CONTENT_URL . '/uploads/cache/' . self::CACHE_FOLDER . '/' . get_current_blog_id();
		$this->add_hooks();
	}
}

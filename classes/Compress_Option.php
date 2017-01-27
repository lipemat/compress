<?php

 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');


class Compress_Option {
	const SHRINK_JS = 'compress-do-js';
	const SHRINK_CSS = 'compress-do-css';
	const EXCLUSIONS = 'compress_exclusions';

	const SHRINK_OPT_COMPRESS = 'compress';
	const SHRINK_OPT_CONCAT = 'concatenate';
	const SHRINK_OPT_NONE = 'none';

	public static function get_css_setting( $default = self::SHRINK_OPT_COMPRESS ) {
		$setting = get_option( self::SHRINK_CSS, $default );
		return self::filter_compression_settings( $setting, $default );
	}

	public static function set_css_setting( $setting ) {
		$setting = self::filter_compression_settings( $setting, $default );
		update_option( self::SHRINK_CSS, $setting );
	}


	public static function get_exclusions(){
		$setting = get_option( self::EXCLUSIONS, '' );
		$setting = apply_filters( 'compress_exclusions', $setting );

		$array = explode( ',', $setting );
		return array_map( 'trim', $array );
	}


	public static function set_exclusions( $setting ){
		update_option( self::EXCLUSIONS, $setting );
	}

	public static function get_js_setting( $default = self::SHRINK_OPT_COMPRESS ) {
		$setting = get_option( self::SHRINK_JS, $default );
		return self::filter_compression_settings( $setting, $default );
	}

	public static function set_js_setting( $setting ) {
		$setting = self::filter_compression_settings( $setting, $default );
		update_option( self::SHRINK_JS, $setting );
	}

	private static function filter_compression_settings( $setting, $default = self::SHRINK_OPT_COMPRESS ) {
		if ( in_array( $setting, array( self::SHRINK_OPT_COMPRESS, self::SHRINK_OPT_CONCAT, self::SHRINK_OPT_NONE ) ) ) {
			return $setting;
		} else {
			return $default;
		}
	}
}

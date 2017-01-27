<?php

 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');


class Compress_WP_Styles extends WP_Styles {
	private $concat_groups = array();

	/**
	 * Do the dependencies
	 *
	 * Process the items passed to it or the queue.  Processes all dependencies.
	 *
	 * @param mixed $handles (optional) items to be processed. (void) processes queue, (string) process that item, (array of strings) process those items
	 * @return array Items that have been processed
	 */
	public function do_items( $handles = false, $group = false ) {
		// Print the queue if nothing is passed. If a string is passed, print that script. If an array is passed, print those scripts.
		$handles = false === $handles ? $this->queue : (array) $handles;
		$this->all_deps( $handles );

		foreach( $this->to_do as $key => $handle ) {
			if ( !in_array($handle, $this->done, true) && isset($this->registered[$handle]) ) {

				if ( ! $this->registered[$handle]->src ) { // Defines a group.
					$this->done[] = $handle;
					continue;
				}

				if ( $this->do_item( $handle, $group ) )
					$this->done[] = $handle;

				unset( $this->to_do[$key] );
			}
		}

		$shrinker = Compress::get_instance();
		foreach ( $this->concat_groups as $media => $groups ) {
			foreach ( $groups as $condition => $stylesheets ) {
				if ( $stylesheets ) {
					$href = $shrinker->get_css_url( $stylesheets, $media, $condition );
					if ($href) {
						$tag = "<link rel='stylesheet' type='text/css' href='$href' media='$media' />\n";
						if ( $condition ) {
							$tag = "<!--[if $condition]>\n".$tag."<![endif]-->\n";
						}
						if ( $this->do_concat ) {
							$this->print_html .= $tag;
						} else {
							echo $tag;
						}
					}
				}
			}
		}

		$this->concat_groups = array();
		return $this->done;
	}

	public function do_item( $handle ) {
		if ( !isset($this->registered[$handle]) )
			return false;

		$obj = $this->registered[$handle];

		// don't try to concatenate/minify alternate stylesheets
		$rel = isset($obj->extra['alt']) && $obj->extra['alt'] ? 'alternate stylesheet' : 'stylesheet';
		if ( $rel != 'stylesheet' ) {
			return parent::do_item($handle);
		}

		if ( null === $obj->ver )
			$ver = '';
		else
			$ver = $obj->ver ? $obj->ver : $this->default_version;

		if ( isset($this->args[$handle]) )
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];

		if ( isset($obj->args) )
			$media = esc_attr( $obj->args );
		else
			$media = 'all';

		$href = $this->_css_href( $obj->src, $ver, $handle );
		$title = isset($obj->extra['title']) ? "title='" . esc_attr( $obj->extra['title'] ) . "'" : '';

		$end_cond = $tag = '';
		if ( isset($obj->extra['conditional']) && $obj->extra['conditional'] ) {
			$condition = $obj->extra['conditional'];
		} else {
			$condition = 0;
		}

		if ( !isset($this->concat_groups[$media][$condition]) ) {
			$this->concat_groups[$media][$condition] = array();
		}

		$this->concat_groups[$media][$condition][$handle] = $href;
		if ( 'rtl' === $this->text_direction && isset($obj->extra['rtl']) && $obj->extra['rtl'] ) {
			if ( is_bool( $obj->extra['rtl'] ) ) {
				$suffix = isset( $obj->extra['suffix'] ) ? $obj->extra['suffix'] : '';
				$rtl_href = str_replace( "{$suffix}.css", "-rtl{$suffix}.css", $this->_css_href( $obj->src , $ver, "$handle-rtl" ));
			} else {
				$rtl_href = $this->_css_href( $obj->extra['rtl'], $ver, "$handle-rtl" );
			}
			$this->concat_groups[$media][$condition][$handle.'-rtl'] = $rtl_href;
		}

		return true;
	}
}

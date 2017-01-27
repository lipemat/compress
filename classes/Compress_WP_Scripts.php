<?php

 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');


class Compress_WP_Scripts extends WP_Scripts {

	private $concat_group = array();

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

		if ( $this->concat_group ) {
			$shrinker = Compress::get_instance();
			$src = $shrinker->get_js_url( $this->concat_group );
			if ( $src ) {
				if ( $this->do_concat )
					$this->print_html .= "<script type='text/javascript' src='$src'></script>\n";
				else
					echo "<script type='text/javascript' src='$src'></script>\n";

				$this->concat_group = array();
			}
		}

		return $this->done;
	}

	public function do_item( $handle, $group = false ) {
		if ( !isset($this->registered[$handle]) )
			return false;

		if ( 0 === $group && $this->groups[$handle] > 0 ) {
			$this->in_footer[] = $handle;
			return false;
		}

		if ( false === $group && in_array($handle, $this->in_footer, true) )
			$this->in_footer = array_diff( $this->in_footer, (array) $handle );

		if ( null === $this->registered[$handle]->ver )
			$ver = '';
		else
			$ver = $this->registered[$handle]->ver ? $this->registered[$handle]->ver : $this->default_version;

		if ( isset($this->args[$handle]) )
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];

		$src = $this->registered[$handle]->src;

		if ( $this->do_concat ) {
			$srce = apply_filters( 'script_loader_src', $src, $handle );
			if ( $this->in_default_dir($srce) ) {
				$this->print_code .= $this->print_extra_script( $handle, false );
				$this->concat .= "$handle,";
				$this->concat_version .= "$handle$ver";
				return true;
			} else {
				$this->ext_handles .= "$handle,";
				$this->ext_version .= "$handle$ver";
			}
		}

		$this->print_extra_script( $handle );
		if ( !preg_match('|^https?://|', $src) && ! ( $this->content_url && 0 === strpos($src, $this->content_url) ) ) {
			if( '//' != substr( $src, 0, 2 ) ){
				$src = $this->base_url . $src;
			}

		}

		if ( !empty($ver) )
			$src = add_query_arg('ver', $ver, $src);

		$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );

		$this->concat_group[$handle] = $src;

		return true;
	}

	private function get_concat_group_url() {

	}

}

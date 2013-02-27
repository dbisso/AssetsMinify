<?php
/**
 * @package Assets Minify
 */

namespace AssetsMinify;

use Assetic\Factory\AssetFactory;
use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Filter\JSMinFilter;
use Assetic\Cache\FilesystemCache;

/**
 * Class that holds plugin's logic.
 */
class Init {

	public $js;

	protected $assetsPath, $assetsUrl;
	protected $jsFilters = array(), $cssFilters = array();
	protected $styles    = array(), $mTimesStyles = array();
	protected $scripts   = array( 'header' => array(), 'footer' => array() );
	protected $mTimes    = array( 'header' => array(), 'footer' => array() );
	protected $jsMin     = 'JSMin';

	public function __construct() {

		//Init assetic's object to manage js minify
		$this->js = new AssetFactory( getcwd() );
		$this->js->setAssetManager( new AssetManager );
		$this->js->setFilterManager( new FilterManager );
		$this->css = new AssetFactory( getcwd() );
		$this->css->setAssetManager( new AssetManager );
		$this->css->setFilterManager( new FilterManager );

		//Define filter for js minify
		$this->js->getFilterManager()->set($this->jsMin, new JSMinFilter);
		$this->jsFilters []= $this->jsMin;
		/*$this->css->getFilterManager()->set($this->jsMin, new JSMinFilter);
		$this->cssFilters []= $this->jsMin;*/

		//Define assets path to save asseticized files
		$uploadsDir = wp_upload_dir();
		$this->assetsUrl  = $uploadsDir['baseurl'] . '/am_assets/';
		$this->assetsPath = $uploadsDir['basedir'] . '/am_assets/';
		if ( !is_dir($this->assetsPath) )
			mkdir($this->assetsPath, 0777);

		$this->cache = new FilesystemCache( $this->assetsPath );

		//Detect all js added to wordpress and deny their inclusion
		add_action( 'wp_print_styles', array( $this, 'extractStyles' ) );
		add_action( 'wp_print_scripts', array( $this, 'extractScripts' ) );

		//Inclusion of scripts in <head> and before </body>
		add_action( 'wp_head', array( $this, 'headerScripts' ) );
		add_action( 'wp_footer', array( $this, 'footerScripts' ) );

	}

	public function extractScripts() {
		global $wp_scripts;

		foreach( $wp_scripts->queue as $handle ) {

			$where = 'footer';
			//Unfortunately not every WP plugin developer is a JS ninja.
			//So... let's put it in the header.
			if ( empty($wp_scripts->registered[$handle]->extra) )
				$where = 'header';

			//Save the source filename for every script enqueued.
			$this->scripts[ $where ][ $handle ] = getcwd() . str_replace( "http://{$_SERVER['SERVER_NAME']}", "", $wp_scripts->registered[$handle]->src );

			$this->mTimes[ $where ][ $handle ] = filemtime( $this->scripts[ $where ][ $handle ] );

			//Remove scripts from the queue so this plugin will be
			//responsible to include all the scripts.
			$wp_scripts->dequeue( $handle );

		}
	}

	public function extractStyles() {
		global $wp_styles;
		foreach( $wp_styles->queue as $handle ) {

			$style = str_replace( "http://{$_SERVER['SERVER_NAME']}", "", $wp_styles->registered[$handle]->src );

			if ( strpos($style, "http") === 0 )
				continue;

			//Save the source filename for every script enqueued.
			$this->styles[ $handle ] = getcwd() . $style;

			$this->mTimesStyles[ $handle ] = filemtime( $this->styles[ $handle ] );

			//Remove scripts from the queue so this plugin will be
			//responsible to include all the scripts.
			$wp_styles->dequeue( $handle );

		}
	}

	public function headerScripts() {
		if ( !empty($this->scripts['header']) ) {
			$mtime = md5(implode('&', $this->mTimes['header']));

			if ( !$this->cache->has( "head.js" ) || get_option('as_minify_head_mtime') != $mtime ) {
				update_option( 'as_minify_head_mtime', $mtime );

				//Save the asseticized header scripts
				$this->cache->set( "head.js", $this->js->createAsset( $this->scripts['header'], $this->jsFilters )->dump() );
			}

			//Print <script> inclusion in the page
			$this->dumpJs( 'head.js' );
		}
		if ( !empty($this->styles) ) {
			$mtime = md5(implode('&', $this->mTimesStyles));

			if ( !$this->cache->has( "head.css" ) || get_option('as_minify_head_css_mtime') != $mtime ) {
				update_option( 'as_minify_head_css_mtime', $mtime );

				//Save the asseticized header scripts
				$this->cache->set( "head.css", $this->css->createAsset( $this->styles, $this->cssFilters )->dump() );
			}

			//Print <script> inclusion in the page
			$this->dumpCss( 'head.css' );
		}
	}

	public function footerScripts() {
		if ( empty($this->scripts['footer']) )
			return false;

		$mtime = md5(implode('&', $this->mTimes['footer']));

		if ( !$this->cache->has( "foot.js" ) || get_option('as_minify_foot_mtime') != $mtime ) {
			update_option( 'as_minify_foot_mtime', $mtime );

			//Save the asseticized footer scripts
			$this->cache->set( "foot.js", $this->js->createAsset( $this->scripts['footer'], $this->jsFilters )->dump() );
		}

		//Print <script> inclusion in the page
		$this->dumpJs( 'foot.js' );
	}

	protected function dumpJs( $filename ) {
		echo "<script type='text/javascript' src='" . $this->assetsUrl . $filename . "'></script>";
	}

	protected function dumpCss( $filename ) {
		echo "<link href='" . $this->assetsUrl . $filename . "' media='screen, projection' rel='stylesheet' type='text/css'>";
	}
}

new Init;
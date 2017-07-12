<?php
/*
Plugin Name: Gunner Technology Async Asset Loader
Plugin URI: http://gunnertech.com/2012/02/modernizr-wordpress-plugin
Description: A plugin leverages Modernizr to load JavaScript files asynchronously 
Version: 0.0.2
Author: gunnertech, codyswann
Author URI: http://gunnnertech.com
License: GPL2
*/


define('GT_ASYNC_ASSET_LOADER_VERSION', '0.0.2');
define('GT_ASYNC_ASSET_LOADER_URL', plugin_dir_url( __FILE__ ));

class GtAsyncAssetLoader {
  private static $instance;
  public static $is_https_request;
  
  public static function activate() {
    update_option("gt_async_asset_loader_db_version", GT_ASYNC_ASSET_LOADER_VERSION);
  }
  
  public static function deactivate() { }
  
  public static function uninstall() { }
  
  public static function update_db_check() {
    
    $installed_ver = get_option( "gt_async_asset_loader_db_version" );
    
    if( $installed_ver != GT_ASYNC_ASSET_LOADER_VERSION ) {
      self::activate();
    }
  }
  
  private function __construct() {
    self::$is_https_request = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
    
    add_action("wp_head", function() { ?>
      <script src="<?php echo GT_ASYNC_ASSET_LOADER_URL ?>js/lib/modernizr.js"></script>
    <?php }, 1);
    
    remove_action("wp_head","wp_print_head_scripts",9);
    remove_action('wp_footer', 'wp_print_footer_scripts');
    
    add_action("wp_head", function() {
      global $wp_scripts, $hbgs_scripts, $hbgs_inline_scripts;
      if ( ! did_action('wp_print_scripts') ) {
        do_action('wp_print_scripts');
      }
      
      if ( !is_a($wp_scripts, 'WP_Scripts') ) {
        return array(); // no need to run if nothing is queued
      }
      
      ob_start();
      
      print_head_scripts();
      echo get_option("footer_javascript");
      print_footer_scripts();

      $output = ob_get_contents();
      ob_end_clean();

      $matches = array();
      preg_match_all('|<script.+src=["\'](.+)["\'].+<\/script>|',$output,$matches);
      $output = preg_replace('|<script.+src=["\'](.+)["\'].+<\/script>|',"",$output);

      $hbgs_scripts = implode("','",$matches[1]);

      if(GtAsyncAssetLoader::$is_https_request) {
        $hbgs_scripts = preg_replace('/http:\/\//',"https://",$hbgs_scripts);
      }

      if($hbgs_scripts) {
        $allowed_tags = '<b><i><sup><sub><em><strong><u><br><p><div><section><aside><article><h1><h2><h3><h4><h5><h6>';
        $hbgs_inline_scripts = strip_tags($hbgs_inline_scripts,$allowed_tags);
        $output = preg_replace('/<script type=\'text\/javascript\'>/',"",$output);
        $output = preg_replace('/<\/script>/',"",$output);
        $hbgs_scripts = preg_replace('/&#038;/','&amp;',$hbgs_scripts);
        $output = preg_replace('/var (.+) = {/',"window.$1 ={",$output);

        $new_output = "<script>Modernizr.load({test: Modernizr.hbgs_loaded, nope:['$hbgs_scripts'], complete:function(){ Modernizr.hbgs_loaded = true; (function($){ ".$hbgs_inline_scripts.trim($output)."}(jQuery)) }});</script>";
      } else {
        $new_output = "";
      }

      echo $new_output;

      return $wp_scripts->done; 
    },9);
  }
  
  
  public static function setup() {
    self::update_db_check();
    self::singleton();
  }
  
  public static function singleton() {
    if (!isset(self::$instance)) {
      $className = __CLASS__;
      self::$instance = new $className;
    }
    
    return self::$instance;
  }
}

register_activation_hook( __FILE__, array('GtAsyncAssetLoader', 'activate') );
register_activation_hook( __FILE__, array('GtAsyncAssetLoader', 'deactivate') );
register_activation_hook( __FILE__, array('GtAsyncAssetLoader', 'uninstall') );

add_action('plugins_loaded', array('GtAsyncAssetLoader', 'setup') );


<?php
/**
 * Plugin Name: LDAP Gatekeeper
 * Description: Page-level LDAP gate with plugin-managed session and clean redirects
 * Version: 0.2.6
 * Author: Songmin Kim with ChatGPT 5
 * Text Domain: ldap-gatekeeper
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'LG_VERSION' ) ) define( 'LG_VERSION', '0.2.6' );
if ( ! defined( 'LG_PATH' ) )    define( 'LG_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'LG_URL' ) )     define( 'LG_URL',  plugin_dir_url( __FILE__ ) );

require_once LG_PATH . 'includes/class-lg-auth.php';
require_once LG_PATH . 'includes/class-lg-guard.php';
require_once LG_PATH . 'includes/class-lg-admin.php';

add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'ldap-gatekeeper', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    \LG\Admin::init();
    \LG\Guard::init();
} );

// Disable Divi's generated/cached CSS ONLY on gated + unauthenticated requests.
add_action('after_setup_theme', function () {
    if ( class_exists('\LG\Guard') && \LG\Guard::should_disable_divi_css() ) {
        // ① 생성 단계에서 막기 (필터)
        add_filter('et_use_dynamic_css', function(){ return false; }, 99);
        add_filter('et_core_enable_static_css_file_generation', function(){ return false; }, 99);
        add_filter('et_load_unified_styles', function(){ return false; }, 99);
    }
}, 1);
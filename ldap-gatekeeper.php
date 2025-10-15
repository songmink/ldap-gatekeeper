<?php
/**
 * Plugin Name: LDAP Gatekeeper
 * Description: Page-level LDAP gate with plugin-managed session
 * Version: 0.2.5
 * Author: Songmin Kim with ChatGPT 5
 * Text Domain: ldap-gatekeeper
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'LG_VERSION' ) ) define( 'LG_VERSION', '0.2.5' );
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

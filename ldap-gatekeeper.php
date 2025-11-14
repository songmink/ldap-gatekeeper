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

// Login and logout handlers
add_action( 'admin_post_nopriv_lg_logout', [ 'LG\\Guard', 'handle_logout' ] );
add_action( 'admin_post_lg_logout',        [ 'LG\\Guard', 'handle_logout' ] );

/**
 * Render session banner via template (theme overrideable).
 *
 * @param array $info  session_info() 결과
 */
function lg_render_session_banner( array $info ) {
    // 세션 만료까지 남은 시간 계산
    $remain   = (int) ($info['remaining'] ?? 0);
    $mm       = floor($remain / 60);
    $ss       = $remain % 60;
    $logout_url = wp_nonce_url(
        admin_url('admin-post.php?action=lg_logout'),
        'lg_logout'
    );

    // 템플릿 변수 세팅
    $lg_session = [
        'info'       => $info,
        'remaining'  => $remain,
        'mm'         => $mm,
        'ss'         => $ss,
        'logout_url' => $logout_url,
    ];

    // 1) 테마 템플릿(오버라이드) 먼저 찾기
    $theme_template = locate_template( 'ldap-gatekeeper/session-banner.php' );

    if ( $theme_template && file_exists( $theme_template ) ) {
        // 테마 오버라이드 사용
        $template = $theme_template;
    } else {
        // 2) 플러그인 기본 템플릿 사용
        $template = __DIR__ . '/templates/session-banner.php';
    }

    // 템플릿 안에서 $lg_session 쓸 수 있게 만들기
    if ( file_exists( $template ) ) {
        /** @var array $lg_session */
        include $template;
    }
}


/**
 * Display plugin-level LDAP login status banner in the front-end header.
 */
add_action('wp_head', function () {
    if ( ! class_exists('LG\\Guard') ) return;

    $info = \LG\Guard::session_info();
    if ( ! $info ) return;

    lg_render_session_banner( $info );
});

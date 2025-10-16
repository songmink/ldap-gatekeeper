<?php
namespace LG;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Guard {
    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_gate' ], 1 );
        add_action( 'init', [ __CLASS__, 'routes' ] );
        add_action( 'admin_post_nopriv_lg_login', [ __CLASS__, 'handle_login' ] );
        add_action( 'admin_post_lg_login', [ __CLASS__, 'handle_login' ] );
        add_action( 'admin_post_lg_logout', [ __CLASS__, 'handle_logout' ] );
    }
    public static function routes() {}
    private static function opts() { return get_option( 'lg_options', [] ); }
    private static function session_key() { return 'lg_session'; }
    private static function current_session() {
        $token = isset($_COOKIE[self::session_key()]) ? sanitize_text_field($_COOKIE[self::session_key()]) : '';
        if ( ! $token ) return false;
        $data = get_transient( 'lg_sess_' . $token );
        return $data ?: false;
    }
    private static function set_session( $user ) {
        $minutes = max(1, (int)( self::opts()['session_minutes'] ?? 120 ) );
        $token = wp_generate_password( 32, false, false );
        set_transient( 'lg_sess_' . $token, $user, $minutes * 60 );
        setcookie( self::session_key(), $token, [
            'expires'  => time() + $minutes * 60,
            'path'     => COOKIEPATH ?: '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
    }
    private static function clear_session() {
        if ( isset($_COOKIE[self::session_key()]) ) {
            $token = sanitize_text_field($_COOKIE[self::session_key()]);
            delete_transient( 'lg_sess_' . $token );
            setcookie( self::session_key(), '', time() - 3600, COOKIEPATH ?: '/' );
        }
    }
    private static function clean_url( $url ) {
        return remove_query_arg( 'lg_err', $url );
    }
    public static function maybe_gate() {
        if ( ! is_page() ) return;
        global $post;
        if ( ! $post ) return;
        $require = (int) get_post_meta( $post->ID, '_lg_require_ldap', true );
        if ( ! $require ) return;
        $session = self::current_session(); 
        if ( $session ) return;

        status_header( 401 ); 
        nocache_headers();
        $error = isset($_GET['lg_err']) ? sanitize_text_field(wp_unslash($_GET['lg_err'])) : '';
        self::render_login( $error ); 
        exit;
    }
    public static function render_login( $error = '' ) {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $raw  = $scheme . $host . $uri;
        $redirect = esc_url_raw( self::clean_url( $raw ) );

        $template = locate_template( [ 'ldap-gatekeeper/login-form.php' ] );
        if ( ! $template ) $template = LG_PATH . 'templates/login-form.php';
        $error_msg = $error;
        $action = esc_url( admin_url( 'admin-post.php?action=lg_login' ) );
        include $template;
    }
    public static function handle_login() {
        check_admin_referer( 'lg_login' );
        $username = isset($_POST['lg_user']) ? sanitize_text_field($_POST['lg_user']) : '';
        $password = isset($_POST['lg_pass']) ? (string) $_POST['lg_pass'] : '';
        $redirect = isset($_POST['lg_redirect']) ? esc_url_raw($_POST['lg_redirect']) : home_url('/');

        $auth = new Auth( self::opts() );
        $res  = $auth->authenticate( $username, $password );
        if ( is_wp_error( $res ) ) {
            wp_safe_redirect( add_query_arg( 'lg_err', rawurlencode( $res->get_error_message() ), $redirect ) );
            exit;
        }
        self::set_session( [ 'user' => [ 'login' => $res['login'], 'email' => $res['email'], 'dn' => $res['dn'] ], 'ts' => time() ] );
        wp_safe_redirect( self::clean_url( $redirect ) );
        exit;
    }
    public static function handle_logout() {
        check_admin_referer( 'lg_logout' );
        self::clear_session();
        $back = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/');
        wp_safe_redirect( self::clean_url($back) );
        exit;
    }
}

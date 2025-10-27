<?php
namespace LG;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'settings' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
        add_action( 'save_post_page', [ __CLASS__, 'save_metabox' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
        add_action( 'admin_post_lg_test', [ __CLASS__, 'handle_test' ] );
        add_action( 'admin_post_lg_clearlog', [ __CLASS__, 'handle_clearlog' ] );

        add_action( 'admin_post_lg_kill_sess', [ __CLASS__, 'handle_kill_sess' ] );
        add_action( 'admin_post_lg_kill_all',  [ __CLASS__, 'handle_kill_all'  ] );
    }
    public static function menu() {
        add_options_page( __( 'LDAP Gatekeeper', 'ldap-gatekeeper' ), __( 'LDAP Gatekeeper', 'ldap-gatekeeper' ), 'manage_options', 'lg-settings', [ __CLASS__, 'render' ] );
    }
    public static function assets( $hook ) {
        if ( $hook === 'settings_page_lg-settings' ) {
            wp_add_inline_style( 'wp-admin', '.lg-log{background:#0b1020;color:#e5e7eb;padding:12px;border-radius:8px;font-family:ui-monospace, SFMono-Regular, Menlo, monospace;font-size:12px;max-height:300px;overflow:auto} .lg-log .ok{color:#86efac} .lg-log .err{color:#fda4af} .lg-log .info{color:#93c5fd} .lg-callout{background:#eef2ff;border-left:4px solid #6366f1;padding:10px 12px;border-radius:6px;margin:12px 0;} .lg-actions{display:flex;gap:8px;align-items:center;margin:8px 0 16px 0;}' );
        }
    }
    public static function settings() {
        register_setting( 'lg_group', 'lg_options', [ __CLASS__, 'sanitize' ] );
        add_settings_section( 'lg_ldap', __( 'LDAP Settings', 'ldap-gatekeeper' ), function(){
            echo '<p>' . esc_html__( 'Configure your LDAP directory connection. Inspired by Authorizer.', 'ldap-gatekeeper' ) . '</p>';
        }, 'lg-settings' );
        $fields = [
            ['ldap_host','text','Host'],
            ['ldap_port','number','Port'],
            ['ldap_encryption','select','Encryption',['none'=>'none','ldaps'=>'ldaps','starttls'=>'starttls']],
            ['ldap_base_dn','text','Base DN'],
            ['ldap_bind_dn','text','Bind DN'],
            ['ldap_bind_pw','password','Bind Password'],
            ['ldap_search_attr','text','Search Attribute'],
            ['ldap_filter','text','Search Filter'],
            ['ldap_user_attr_login','text','Login Attribute'],
            ['ldap_user_attr_email','text','Email Attribute'],
            ['ldap_group_attribute','text','Group Attribute'],
            ['ldap_allowed_groups','text','Allowed Groups'],
            ['ldap_timeout','number','Timeout'],
            ['ldap_referrals','checkbox','Follow Referrals'],
            ['session_minutes','number','Session Duration (minutes)'],
        ];
        foreach ( $fields as $f ) {
            add_settings_field( $f[0], esc_html($f[2]), function() use($f){
                $opt = get_option( 'lg_options', [] );
                $name = esc_attr( $f[0] );
                $val  = isset($opt[$name]) ? $opt[$name] : '';
                $type = $f[1];
                if ( $type === 'select' ) {
                    echo '<select name="lg_options['.$name.']">';
                    foreach ( $f[3] as $k=>$v ) echo '<option value="'.esc_attr($k).'" '.selected($val,$k,false).'>'.esc_html($v).'</option>';
                    echo '</select>';
                } elseif ( $type === 'checkbox' ) {
                    echo '<input type="checkbox" name="lg_options['.$name.']" value="1" '.checked($val,1,false).'>';
                } else {
                    echo '<input type="'.$type.'" name="lg_options['.$name.']" value="'.esc_attr($val).'" class="regular-text">';
                }
            }, 'lg-settings', 'lg_ldap' );
        }
    }
    public static function sanitize( $in ) {
        $in['ldap_port'] = (int) ($in['ldap_port'] ?? 389);
        $in['ldap_timeout'] = (int) ($in['ldap_timeout'] ?? 5);
        $in['ldap_referrals'] = empty($in['ldap_referrals']) ? 0 : 1;
        $in['session_minutes'] = max(1, (int)($in['session_minutes'] ?? 120));
        return $in;
    }
    public static function render() {
        echo '<div class="wrap"><h1>'.esc_html__('LDAP Gatekeeper','ldap-gatekeeper').'</h1>';
        echo '<div class="lg-callout"><strong>'.esc_html__('Override Login Template','ldap-gatekeeper').':</strong> ';
        echo esc_html__('Copy the template to your theme to customize:', 'ldap-gatekeeper').' ';
        echo '<code>yourtheme/ldap-gatekeeper/login-form.php</code></div>';

        $status = isset($_GET['lg_status']) ? sanitize_text_field($_GET['lg_status']) : '';
        $msg    = isset($_GET['lg_msg']) ? wp_kses_post(wp_unslash($_GET['lg_msg'])) : '';
        if ( $status ) { $cls = ($status==='success')?'notice-success':'notice-error'; echo '<div class="notice '.$cls.' is-dismissible"><p>'.$msg.'</p></div>'; }
        echo '<form method="post" action="options.php">'; settings_fields('lg_group'); do_settings_sections('lg-settings'); submit_button(); echo '</form>';

        echo '<hr><h2>'.esc_html__('Connection Test','ldap-gatekeeper').'</h2>';
        echo '<p>'.esc_html__('Enter a directory username/password to test authentication. Credentials are not stored.','ldap-gatekeeper').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="max-width:680px;">';
        \wp_nonce_field('lg_test');
        echo '<input type="hidden" name="action" value="lg_test">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="lg_test_user">'.esc_html__('Test Username','ldap-gatekeeper').'</label></th><td><input name="lg_test_user" id="lg_test_user" type="text" class="regular-text" autocomplete="username" required></td></tr>';
        echo '<tr><th scope="row"><label for="lg_test_pass">'.esc_html__('Test Password','ldap-gatekeeper').'</label></th><td><input name="lg_test_pass" id="lg_test_pass" type="password" class="regular-text" autocomplete="current-password" required></td></tr>';
        echo '</tbody></table>'; submit_button( __( 'Run Test', 'ldap-gatekeeper' ) ); echo '</form>';

        echo '<h3 style="margin-top:16px">'.esc_html__('Test Log','ldap-gatekeeper').'</h3>';
        echo '<div class="lg-actions" style="margin-top:0;margin-bottom:8px;">';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        \wp_nonce_field('lg_clearlog');
        echo '<input type="hidden" name="action" value="lg_clearlog">';
        submit_button( __( 'Clear Test Log', 'ldap-gatekeeper' ), 'secondary', '', false );
        echo '</form>';
        echo '</div>';

        $log = get_transient('lg_test_log');
        echo '<div class="lg-log">';
        if ( is_array($log) && !empty($log) ) {
            foreach ( $log as $line ) {
                $ts=esc_html($line['t']??''); $lvl=esc_html($line['l']??'info'); $m=esc_html($line['m']??'');
                echo '<div class="'.$lvl.'">['.$ts.'] '.$m.'</div>';
            }
        } else {
            echo '<div class="info">'.esc_html__('No logs yet. Run a test to see details here.','ldap-gatekeeper').'</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '<hr><h2>'.esc_html__('Active LDAP Sessions','ldap-gatekeeper').'</h2>';
        echo '<p>'.esc_html__('These sessions are managed by the plugin (not WordPress login). Forcing logout deletes the server-side session; user cookies will become invalid.','ldap-gatekeeper').'</p>';

        echo '<div class="lg-actions" style="display:flex;gap:8px;align-items:center;margin:8px 0 16px 0;">';
        // Refresh
        echo '<form method="get" action="'.esc_url(admin_url('options-general.php')).'">';
        echo '<input type="hidden" name="page" value="lg-settings">';
        echo '<button class="button">'.esc_html__('Refresh','ldap-gatekeeper').'</button>';
        echo '</form>';
        // Force logout all
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('lg_kill_all');
        echo '<input type="hidden" name="action" value="lg_kill_all">';
        submit_button( __( 'Force Logout All', 'ldap-gatekeeper' ), 'delete', '', false );
        echo '</form>';
        echo '</div>';

        global $wpdb;
        $like = $wpdb->esc_like('_transient_lg_sess_') . '%';
        $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC", $like)
        );

        if ( $rows ) {
        echo '<table class="widefat striped" style="max-width:980px;"><thead><tr>';
        echo '<th>'.esc_html__('User','ldap-gatekeeper').'</th>';
        echo '<th>'.esc_html__('DN','ldap-gatekeeper').'</th>';
        echo '<th>'.esc_html__('IP / UA','ldap-gatekeeper').'</th>';
        echo '<th>'.esc_html__('Started','ldap-gatekeeper').'</th>';
        echo '<th>'.esc_html__('Expires In','ldap-gatekeeper').'</th>';
        echo '<th></th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $r ) {
            $name  = $r->option_name; // _transient_lg_sess_<token>
            $token = substr( $name, strlen('_transient_lg_sess_') );
            $data  = maybe_unserialize( $r->option_value );
            if ( ! is_array($data) || empty($data['user']) ) continue;

            $login = esc_html( $data['user']['login'] ?? '' );
            $email = esc_html( $data['user']['email'] ?? '' );
            $dn    = esc_html( $data['user']['dn'] ?? '' );
            $ip    = esc_html( $data['ip'] ?? '' );
            $ua    = esc_html( $data['ua'] ?? '' );
            $ts    = !empty($data['ts']) ? date_i18n( 'Y-m-d H:i:s', (int)$data['ts'] ) : '';

            // Convert stored UTC timestamp to WordPress timezone for display
            $timeout_name = '_transient_timeout_lg_sess_' . $token;
            $exp_ts = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                $timeout_name
            ) );

            $remain = $exp_ts ? max(0, $exp_ts - time()) : 0;
            $remain_h = floor($remain/3600);
            $remain_m = floor(($remain%3600)/60);

            echo '<tr>';
            echo '<td><strong>'.$login.'</strong><br><span style="color:#6b7280">'.$email.'</span></td>';
            echo '<td style="word-break:break-all">'.$dn.'</td>';
            echo '<td><div>'.$ip.'</div><div style="max-width:320px;word-break:break-all;color:#6b7280">'.$ua.'</div></td>';
            echo '<td>'.$ts.'</td>';
            echo '<td>'.($remain_h ? $remain_h.'h ' : '').$remain_m.'m</td>';
            echo '<td>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('lg_kill_sess');
                echo '<input type="hidden" name="action" value="lg_kill_sess">';
                echo '<input type="hidden" name="lg_token" value="'.esc_attr($token).'">';
                submit_button( __( 'Force Logout', 'ldap-gatekeeper' ), 'secondary', '', false );
                echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        } else {
        echo '<p>'.esc_html__('No active sessions found.','ldap-gatekeeper').'</p>';
        }

    }
    public static function metabox() {
        add_meta_box( 'lg_metabox', __( 'LDAP Gatekeeper', 'ldap-gatekeeper' ), [ __CLASS__, 'metabox_cb' ], 'page', 'side', 'default' );
    }
    public static function metabox_cb( $post ) {
        $value = (int) get_post_meta( $post->ID, '_lg_require_ldap', true );
        \wp_nonce_field( 'lg_metabox', 'lg_metabox_nonce' );
        echo '<label><input type="checkbox" name="_lg_require_ldap" value="1" '.checked( $value, 1, false ).'> '.esc_html__( 'Require LDAP login for this page', 'ldap-gatekeeper' ).'</label>';
    }
    public static function save_metabox( $post_id ) {
        if ( ! isset($_POST['lg_metabox_nonce']) || ! wp_verify_nonce( $_POST['lg_metabox_nonce'], 'lg_metabox' ) ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_page',$post_id) ) return;
        $req = isset($_POST['_lg_require_ldap']) ? 1 : 0;
        update_post_meta( $post_id, '_lg_require_ldap', $req );
    }
    private static function log_push( &$log, $level, $message ) { $log[] = [ 't' => current_time('Y-m-d H:i:s'), 'l' => $level, 'm' => $message ]; }
    public static function handle_test() {
        if ( ! current_user_can('manage_options') ) wp_die( __('Insufficient permissions','ldap-gatekeeper') );
        check_admin_referer('lg_test');
        $user = isset($_POST['lg_test_user']) ? sanitize_text_field($_POST['lg_test_user']) : '';
        $pass = isset($_POST['lg_test_pass']) ? (string) $_POST['lg_test_pass'] : '';
        $opts = get_option('lg_options', []);
        $log = [];
        self::log_push($log,'info','Starting LDAP connectivity test...');
        self::log_push($log,'info','Host: '.($opts['ldap_host']??'').' Port: '.($opts['ldap_port']??'').' Enc: '.($opts['ldap_encryption']??'none'));
        self::log_push($log,'info','Base DN: '.($opts['ldap_base_dn']??''));
        self::log_push($log,'info','Bind DN present: '.( empty($opts['ldap_bind_dn']) ? 'no' : 'yes' ));
        $auth = new Auth($opts);
        $auth->set_logger(function($level,$message) use (&$log){ Admin::log_push($log,$level,$message); });
        $res = $auth->authenticate($user,$pass);
        set_transient('lg_test_log',$log, 10 * MINUTE_IN_SECONDS);
        if ( is_wp_error($res) ) {
            $qs = [ 'page'=>'lg-settings', 'lg_status'=>'error', 'lg_msg'=>rawurlencode($res->get_error_message()) ];
            wp_safe_redirect( add_query_arg($qs, admin_url('options-general.php')) ); exit;
        }
        self::log_push($log,'ok','Authentication success for user '.$res['login'].' ('.$res['email'].')');
        set_transient('lg_test_log',$log, 10 * MINUTE_IN_SECONDS);
        $qs = [ 'page'=>'lg-settings', 'lg_status'=>'success', 'lg_msg'=>rawurlencode( sprintf( __( 'Success! User %1$s authenticated.', 'ldap-gatekeeper' ), $res['login'] ) ) ];
        wp_safe_redirect( add_query_arg($qs, admin_url('options-general.php')) ); exit;
    }
    public static function handle_clearlog() {
        if ( ! current_user_can('manage_options') ) wp_die( __('Insufficient permissions','ldap-gatekeeper') );
        check_admin_referer('lg_clearlog');
        delete_transient('lg_test_log');
        wp_safe_redirect( admin_url('options-general.php?page=lg-settings&lg_status=success&lg_msg='.rawurlencode(__('Test log cleared.','ldap-gatekeeper'))) );
        exit;
    }

    public static function handle_kill_sess() {
        if ( ! current_user_can('manage_options') ) wp_die( __('Insufficient permissions','ldap-gatekeeper') );
        check_admin_referer('lg_kill_sess');

        $token = isset($_POST['lg_token']) ? sanitize_text_field($_POST['lg_token']) : '';
        if ( $token ) {
            delete_transient('lg_sess_' . $token);
        }
        wp_safe_redirect( admin_url('options-general.php?page=lg-settings&lg_status=success&lg_msg='.rawurlencode(__('Session terminated.','ldap-gatekeeper'))) );
        exit;
        }

        public static function handle_kill_all() {
        if ( ! current_user_can('manage_options') ) wp_die( __('Insufficient permissions','ldap-gatekeeper') );
        check_admin_referer('lg_kill_all');

        global $wpdb;
        $like = $wpdb->esc_like('_transient_lg_sess_') . '%';
        $rows = $wpdb->get_col( $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like) );
        if ( $rows ) {
            foreach ( $rows as $name ) {
            $token = substr( $name, strlen('_transient_lg_sess_') );
            delete_transient('lg_sess_' . $token);
            }
        }
        wp_safe_redirect( admin_url('options-general.php?page=lg-settings&lg_status=success&lg_msg='.rawurlencode(__('All sessions terminated.','ldap-gatekeeper'))) );
        exit;
    }

}

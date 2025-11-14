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
 * Display plugin-level LDAP login status banner in the front-end header.
 */
add_action('wp_head', function () {

    echo "<!-- LG wp_head test -->\n";

    if ( ! class_exists('LG\\Guard') ) return;

    $info = \LG\Guard::session_info();
    if ( ! $info ) return; // not logged in → do nothing

    $remain = (int) $info['remaining'];
    $mm = floor($remain / 60);
    $ss = $remain % 60;

    $logout_url = wp_nonce_url(
        admin_url('admin-post.php?action=lg_logout'),
        'lg_logout'
    );
    ?>

    <style>
        .lg-session-banner {
            position: fixed;
            top: 14px;
            right: 14px;
            background: rgba(0,0,0,0.75);
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.3;
            z-index: 999999;
            display: flex;
            gap: 8px;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .lg-session-banner strong { color: #fff; }
        .lg-session-banner .lg-email { color: #cbd5e1; }
        .lg-session-banner a.lg-logout {
            color: #93c5fd;
            text-decoration: none;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #60a5fa;
            font-size: 12px;
        }
        .lg-session-banner a.lg-logout:hover {
            background: #60a5fa;
            color: #000;
        }
    </style>

    <div class="lg-session-banner">
       <strong><?php echo esc_html( $info['login'] ); ?></strong> 

       <!--
        <?php if (!empty($info['email'])): ?>
            <span class="lg-email">(<?php echo esc_html($info['email']); ?>)</span>
        <?php endif; ?>
        -->

        <span id="lg-remaining">
            · <?php echo sprintf("%02d:%02d", $mm, $ss); ?> left
        </span>

        <a class="lg-logout" href="<?php echo esc_url($logout_url); ?>">Logout</a>
    </div>

    <script>
    (function(){
        var el = document.getElementById('lg-remaining');
        if (!el) return;
        var secs = <?php echo (int) $info['remaining']; ?>;

        function tick() {
            if (secs <= 0) return;
            secs--;
            var mm = String(Math.floor(secs / 60)).padStart(2,'0');
            var ss = String(secs % 60).padStart(2,'0');
            el.textContent = '· ' + mm + ':' + ss + ' left';
            setTimeout(tick, 1000);
        }

        setTimeout(tick, 1000);
    })();
    </script>

    <?php
});

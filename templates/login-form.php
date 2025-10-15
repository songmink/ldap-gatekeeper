<?php /** @var string $action */ /** @var string $error_msg */ /** @var string $redirect */ ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html__( 'LDAP Login', 'ldap-gatekeeper' ); ?></title>
    <?php wp_head(); ?>
    <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f8fafc; }
    .lg-container { max-width: 420px; margin: 8vh auto; padding: 32px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
    .lg-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; text-align: center; }
    .lg-error { color: #b91c1c; background:#fee2e2; border:1px solid #fca5a5; border-radius:6px; padding:8px 10px; margin-bottom:15px; text-align:center; }
    .lg-field { margin-bottom: 16px; } .lg-field label { display:block; font-weight:500; margin-bottom:6px; } .lg-field input { width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; }
    button.button-primary { width:100%; padding:10px; font-weight:600; border-radius:8px; background:#2563eb; border:none; color:white; cursor:pointer; }
    button.button-primary:hover { background:#1d4ed8; } .lg-footer { margin-top: 18px; text-align:center; font-size:12px; color:#6b7280; }
    </style>
</head>
<body <?php body_class('lg-login'); ?>>
    <div class="lg-container">
        <div class="lg-title"><?php echo esc_html__( 'Sign in with LDAP', 'ldap-gatekeeper' ); ?></div>
        <?php
        if ( empty($error_msg) && isset($_GET['lg_err']) ) { $error_msg = sanitize_text_field( wp_unslash($_GET['lg_err']) ); }
        if ( ! empty( $error_msg ) ) : ?>
            <div class="lg-error"><?php echo esc_html( $error_msg ); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( $action ); ?>">
            <?php \wp_nonce_field( 'lg_login' ); ?>
            <input type="hidden" name="lg_redirect" value="<?php echo esc_attr( $redirect ); ?>">
            <div class="lg-field"><label for="lg_user"><?php esc_html_e('Username','ldap-gatekeeper'); ?></label><input type="text" id="lg_user" name="lg_user" autocomplete="username" required></div>
            <div class="lg-field"><label for="lg_pass"><?php esc_html_e('Password','ldap-gatekeeper'); ?></label><input type="password" id="lg_pass" name="lg_pass" autocomplete="current-password" required></div>
            <button type="submit" class="button-primary"><?php esc_html_e('Sign In','ldap-gatekeeper'); ?></button>
        </form>
        <div class="lg-footer"><?php esc_html_e('To override, copy this file to your theme:','ldap-gatekeeper'); ?><br><code>yourtheme/ldap-gatekeeper/login-form.php</code></div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

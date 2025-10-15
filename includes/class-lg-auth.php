<?php
namespace LG;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Auth {
    private $conn;
    private $opts;
    private $logger; // callable|null

    public function __construct( array $opts ) { $this->opts = $opts; }
    public function set_logger( $callable ) { if ( is_callable($callable) ) $this->logger = $callable; }
    private function log( $level, $msg ) { if ( is_callable($this->logger) ) call_user_func($this->logger, $level, $msg ); }

    private function connect() {
        if ( ! function_exists('ldap_connect') ) return new \WP_Error('ldap_missing', __('PHP LDAP extension is not installed.', 'ldap-gatekeeper'));

        $host = $this->opts['ldap_host'] ?? '';
        $port = (int)($this->opts['ldap_port'] ?? 389);
        $enc  = $this->opts['ldap_encryption'] ?? 'none';

        // Normalize hostname (SNI hint); allow full URI in settings
        $hostname = preg_replace('#^ldaps?://#i', '', trim($host));
        $hostname = preg_replace('#:\d+$#', '', $hostname);

        // 1) Connect (ldaps prefers URI for SNI/verification stability)
        if ( $enc === 'ldaps' ) {
            $uri = (stripos($host, 'ldaps://') === 0) ? $host : ('ldaps://' . $hostname);
            $this->log('info', 'Connecting via LDAPS URI: ' . $uri);
            $this->conn = @ldap_connect( $uri );
        } else {
            $this->log('info', 'Connecting to host: ' . $hostname . ' port: ' . $port);
            $this->conn = @ldap_connect( $hostname, $port );
        }
        if ( ! $this->conn ) return new \WP_Error('ldap_connect_fail', __('Unable to connect to LDAP host.', 'ldap-gatekeeper'));

        // 2) TLS/global options
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $this->log('info', 'Environment: ' . $env);

        // Minimum TLS version: 1.2 (OpenLDAP value 3)
        @ldap_set_option(NULL, LDAP_OPT_X_TLS_PROTOCOL_MIN, 3);
        // System CA bundle hints (Debian/Ubuntu). RHEL-family will still pass since CACERTFILE is a superset.
        @ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTFILE, '/etc/ssl/certs/ca-certificates.crt');
        @ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTDIR,  '/etc/ssl/certs');

        // SNI / hostname hint (some libldap builds need this)
        if ( defined('LDAP_OPT_X_TLS_HOSTNAME') ) {
            @ldap_set_option($this->conn, LDAP_OPT_X_TLS_HOSTNAME, $hostname);
        }

        // Stage/dev only: relax certificate requirement for troubleshooting
        if ( in_array($env, ['staging','development'], true) ) {
            @ldap_set_option($this->conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            $this->log('info', 'TLS cert validation disabled for non-production.');
        } else {
            @ldap_set_option($this->conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);
            $this->log('info', 'TLS cert validation required in production.');
        }

        // Standard options
        ldap_set_option( $this->conn, LDAP_OPT_PROTOCOL_VERSION, 3 );
        ldap_set_option( $this->conn, LDAP_OPT_REFERRALS, (int)($this->opts['ldap_referrals'] ?? 0) );
        if ( ! empty($this->opts['ldap_timeout']) ) @ldap_set_option( $this->conn, LDAP_OPT_NETWORK_TIMEOUT, (int)$this->opts['ldap_timeout'] );

        // 3) STARTTLS if requested
        if ( $enc === 'starttls' ) {
            $this->log('info','STARTTLS handshake');
            if ( ! @ldap_start_tls( $this->conn ) ) {
                $this->log('err','STARTTLS failed: '. (function_exists('ldap_error') ? @ldap_error($this->conn) : 'error') );
                return new \WP_Error('ldap_tls_fail', __('STARTTLS negotiation failed.', 'ldap-gatekeeper'));
            }
        }

        // 4) Service bind
        $bind_dn = $this->opts['ldap_bind_dn'] ?? '';
        $bind_pw = $this->opts['ldap_bind_pw'] ?? '';
        if ( $bind_dn ) {
            $this->log('info','Service bind with DN');
            if ( ! @ldap_bind( $this->conn, $bind_dn, $bind_pw ) ) {
                $this->log('err','Service bind failed: '. (function_exists('ldap_error') ? @ldap_error($this->conn) : 'error') );
                return new \WP_Error('ldap_bind_fail', __('Bind failed with service account credentials.', 'ldap-gatekeeper'));
            }
            $this->log('ok','Service bind OK');
        } else {
            $this->log('info','Anonymous bind');
            if ( ! @ldap_bind( $this->conn ) ) {
                return new \WP_Error('ldap_bind_fail', __('Anonymous bind failed.', 'ldap-gatekeeper'));
            }
        }
        return true;
    }

    public function authenticate( string $username, string $password ) {
        if ( empty($username) || empty($password) ) return new \WP_Error('invalid_creds', __('Username or password missing.', 'ldap-gatekeeper'));
        $cx = $this->connect(); if ( is_wp_error($cx) ) return $cx;

        $base = $this->opts['ldap_base_dn'] ?? '';
        $attr = $this->opts['ldap_search_attr'] ?? 'uid';
        $filter_tpl = $this->opts['ldap_filter'] ?? '(%attr%=%s)';
        $user_escaped = function_exists('ldap_escape') ? ldap_escape($username, '', LDAP_ESCAPE_FILTER) : addslashes($username);
        $filter = str_replace(['%attr%','%s','%u'], [$attr, $user_escaped, $user_escaped], $filter_tpl);
        $this->log('info','Search filter: '.$filter);

        $attrs = ['dn', $attr, $this->opts['ldap_user_attr_email'] ?? 'mail', $this->opts['ldap_group_attribute'] ?? 'memberOf'];
        $this->log('info','Searching base DN '.$base);
        $search = @ldap_search( $this->conn, $base, $filter, $attrs );
        if ( ! $search ) return new \WP_Error('ldap_search_fail', __('LDAP search failed.', 'ldap-gatekeeper'));

        $entries = @ldap_get_entries( $this->conn, $search );
        if ( empty($entries) || $entries['count'] < 1 ) return new \WP_Error('ldap_user_not_found', __('User not found in LDAP directory.', 'ldap-gatekeeper'));

        $dn = $entries[0]['dn'];
        if ( ! @ldap_bind( $this->conn, $dn, $password ) ) return new \WP_Error('ldap_invalid_password', __('Invalid LDAP credentials.', 'ldap-gatekeeper'));

        // Optional group check
        $allowed_csv = trim($this->opts['ldap_allowed_groups'] ?? '');
        if ( $allowed_csv !== '' ) {
            $allowed = array_map('trim', explode(',', $allowed_csv));
            $grpAttr = strtolower($this->opts['ldap_group_attribute'] ?? 'memberOf');
            $userGroups = [];
            if ( isset($entries[0][$grpAttr]) && $entries[0][$grpAttr]['count'] > 0 ) {
                for ( $i = 0; $i < $entries[0][$grpAttr]['count']; $i++ ) $userGroups[] = $entries[0][$grpAttr][$i];
            }
            $inGroup=false;
            foreach ( $allowed as $g ) {
                foreach ( $userGroups as $ug ) {
                    if ( stripos($ug, $g) !== false ) { $inGroup=true; break 2; }
                }
            }
            if ( ! $inGroup ) return new \WP_Error('ldap_group_denied', __('User not in allowed groups.', 'ldap-gatekeeper'));
        }

        $login_attr = $this->opts['ldap_user_attr_login'] ?? 'uid';
        $email_attr = $this->opts['ldap_user_attr_email'] ?? 'mail';
        $this->log('ok','Authentication succeeded');
        return [
            'dn'    => $dn,
            'login' => $this->get_attr($entries[0], $login_attr) ?: $username,
            'email' => $this->get_attr($entries[0], $email_attr) ?: '',
            'attrs' => $entries[0],
        ];
    }

    private function get_attr( $entry, $name ) {
        $name = strtolower($name);
        return ( isset($entry[$name]) && $entry[$name]['count'] > 0 ) ? $entry[$name][0] : '';
    }
}

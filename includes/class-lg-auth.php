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

        // Accept full URI or host:port
        if ( stripos($host, 'ldap://') === 0 || stripos($host, 'ldaps://') === 0 ) {
            $this->log('info','Using URI: '.$host);
            $this->conn = @ldap_connect( $host );
        } else {
            if ( $enc === 'ldaps' && stripos($host, 'ldaps://') !== 0 ) $host = 'ldaps://' . $host;
            $this->conn = @ldap_connect( $host, $port );
        }
        if ( ! $this->conn ) return new \WP_Error('ldap_connect_fail', __('Unable to connect to LDAP host.', 'ldap-gatekeeper'));

        ldap_set_option( $this->conn, LDAP_OPT_PROTOCOL_VERSION, 3 );
        ldap_set_option( $this->conn, LDAP_OPT_REFERRALS, (int)($this->opts['ldap_referrals'] ?? 0) );
        if ( ! empty($this->opts['ldap_timeout']) ) @ldap_set_option( $this->conn, LDAP_OPT_NETWORK_TIMEOUT, (int)$this->opts['ldap_timeout'] );

        if ( $enc === 'starttls' ) {
            $this->log('info','STARTTLS handshake');
            if ( ! @ldap_start_tls( $this->conn ) ) {
                $this->log('err','STARTTLS failed: '. (function_exists('ldap_error') ? @ldap_error($this->conn) : 'error') );
                return new \WP_Error('ldap_tls_fail', __('STARTTLS negotiation failed.', 'ldap-gatekeeper'));
            }
        }

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
        // Support %attr%, %s, and %u placeholders
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

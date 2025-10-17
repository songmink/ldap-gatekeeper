# LDAP Gatekeeper (WordPress)

**Description:** Page-level LDAP gate with plugin-managed session and clean redirects  
**Author:** Songmin Kim with ChatGPT 5  
**Version:** 0.2.6

## What's new in 0.2.6
- Environment-aware TLS: in `staging/development`, TLS cert validation is relaxed; in `production`, strict validation is enforced.
- TLS 1.2+ enforced, SNI/hostname hint + CA bundle path hints added for Debian/TurnKey compatibility.

## Quick Start
1. Install & activate the plugin.
2. Settings → LDAP Gatekeeper: configure host/port/encryption, base DN, bind DN/pw, search filter.
3. Edit a page → check “Require LDAP login for this page.”
4. Visit that page → LDAP login form appears; on success you return to the intended page.

## Override login template
Copy the template to your theme:
```
yourtheme/ldap-gatekeeper/login-form.php
```

## Environment type
WordPress 5.5+ supports `WP_ENVIRONMENT_TYPE`. Set in `wp-config.php`:
```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' ); // or 'production', 'development', 'local'
```

## Notes
- No WordPress user login is performed; the plugin manages its own session cookie.
- Connection Test includes a live log and a “Clear Test Log” button.

## Theme Compatibility Notes

### Using Divi or Similar Visual Builder Themes
- Divi’s **Dynamic CSS** and **Static CSS File Generation** features can conflict with LDAP-gated pages that issue redirects or non-200 responses (such as login forms).
- When using **Divi**:
  - Set **Dynamic CSS** and **Static CSS File Generation** to **Disabled** under  
   - *Divi → Theme Options → General → Performance → Dynamic CSS*.
   - *Divi → Theme Options → Builder → Advanced → Static CSS File Generation*.
  - Clear the Divi builder cache after making this change.
- Avoid programmatically controlling Divi’s CSS system from this plugin. Future Divi updates may alter hooks and cause unexpected errors.
- Similar builder frameworks (Elementor Pro, Avada, etc.) may require comparable adjustments when using page-level LDAP gating.

# LDAP Gatekeeper (WordPress plugin)

**Description:** Page-level LDAP gate with plugin-managed session and clean redirects  
**Author:** Center for Language and Technology with ChatGPT 5  
**Version:** 0.2.5

## What it does
- Protect **specific pages** with LDAP authentication (no WordPress user login).
- On success, the plugin issues its **own session cookie** and redirects back to the original page.
- Session duration is configurable (in minutes) in **Settings → LDAP Gatekeeper**.
- Includes a **Connection Test** tool with live logs.

## Quick Start
1. Install & activate the plugin.
2. Go to **Settings → LDAP Gatekeeper**, configure your LDAP server (host, port, encryption, base DN, bind DN/pw, filter).
3. Edit a page and check **“Require LDAP login for this page”** in the sidebar.
4. Visit that page to see the **LDAP login form**.

## Customize the login template
Copy the bundled template to your theme to override the design:
```
yourtheme/ldap-gatekeeper/login-form.php
```

## Testing
Use the **Connection Test** at the bottom of the settings page.  
- Logs auto-refresh when you open the settings page.  
- You can also click **Clear Test Log**.

## Notes
- The plugin does **not** use WordPress user accounts or `wp-login.php`.
- Sessions are stored in WordPress transients and identified via a secure cookie.

## License
MIT (or match your project’s license).

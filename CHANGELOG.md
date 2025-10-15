## [v0.2.6] - 2025-10-14
### Added
- Environment-aware TLS handling (WP_ENVIRONMENT_TYPE aware)
- TLS 1.2+ enforcement and SNI hostname hints
### Fixed
- Bind failures on TurnKey/Debian due to missing CA recognition

## [v0.2.7] - 2025-10-15

### Fixed
- Prevented login form from reappearing when users navigate back after successful LDAP login
  - Added client-side handler to detect browser back/forward cache (bfcache)
  - Automatically skips cached login form if valid `lg_session` cookie exists
  - Improves UX by returning users to the previous page instead of showing outdated login form

### Improved
- Enhanced browser navigation flow for pages protected by LDAP Gatekeeper
- No impact on session handling or plugin security mechanisms

### Notes
- This update affects only the front-end browser behavior.
- Server-side authentication, session lifetime, and LDAP validation remain unchanged.

# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in LOOM, please report it responsibly:

**Email:** marcin@marcinzmuda.com

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact

I will respond within 48 hours and work on a fix.

**Please do NOT open a public GitHub issue for security vulnerabilities.**

## Security Measures

LOOM implements the following security practices:

- **Nonce verification** on all 18 AJAX endpoints (`check_ajax_referer`)
- **Capability checks** (`manage_options` / `edit_posts`) on every action
- **Input sanitization** via `sanitize_text_field()` + `wp_unslash()` on all POST data
- **Output escaping** via `esc_html()`, `esc_attr()`, `esc_url()` on all rendered values
- **Prepared statements** via `$wpdb->prepare()` on all queries with dynamic values
- **API key encryption** using AES-256-CBC with random IV
- **Rate limiting** (5-second cooldown per user on suggestion requests)
- **Content backup** to post meta before any `post_content` modification
- **Zero external dependencies**  -  no Composer, no npm, no third-party PHP/JS libraries

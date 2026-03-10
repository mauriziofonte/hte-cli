# LAMP Stack Hardening and Linux Security

([As stated here](/README.md#security)), HTE-CLI is **not intended** for use on internet-facing production environments.

## Why HTE-CLI Should Not Be Used in Production

HTE-CLI generates Apache VirtualHost and PHP-FPM configurations optimized for **development speed and convenience**, not for security. Specifically, the configurations it produces have the following characteristics that make them unsuitable for production use:

### Apache Configuration

- **Self-signed SSL certificates**: HTE-CLI generates self-signed certificates for HTTPS. Production environments require certificates issued by a trusted Certificate Authority (e.g., Let's Encrypt, DigiCert). Self-signed certificates trigger browser warnings and cannot be trusted by external clients.
- **Permissive `AllowOverride All`**: VirtualHosts are configured with `AllowOverride All`, which allows `.htaccess` files to override any directive. In production, this should be restricted to only the directives that are actually needed, both for security and for performance (Apache must scan for `.htaccess` files on every request).
- **No rate limiting or request filtering**: No `mod_security`, `mod_evasive`, or `mod_ratelimit` configuration is applied. Production servers need protection against brute-force attacks, SQL injection, XSS, and other OWASP Top 10 threats.
- **`ServerTokens` and `ServerSignature` not hardened**: Server version information is exposed in HTTP headers and error pages, giving attackers detailed information about the software stack.
- **Localhost HTTPS bypass**: The Force HTTPS feature intentionally allows unencrypted HTTP from `127.0.0.1` and `::1`, which is convenient for local `curl` testing but would be a vulnerability if applied to a production server with local services.

### PHP-FPM Configuration

- **`dev` profile by default**: The default profile disables only a handful of Apache-specific functions. Critical functions like `exec()`, `shell_exec()`, `system()`, `passthru()`, `proc_open()` remain available — these are the primary vectors for Remote Code Execution (RCE) attacks.
- **High memory limits (1024M)**: The `dev` and `staging` profiles set `memory_limit` to 1024M, which makes the server vulnerable to memory exhaustion attacks.
- **`allow_url_fopen` disabled only partially**: While `allow_url_fopen` is disabled, `allow_url_include` handling varies by profile. In production, both should be explicitly disabled.
- **No `open_basedir` in `dev` profile**: PHP processes can read and write anywhere on the filesystem that the FPM user has access to. A compromised application could read `/etc/passwd`, SSH keys, other VirtualHost configurations, or any file readable by the pool user.
- **Pool user matches the developer's user**: PHP-FPM pools run as the developer's own user (e.g., `maurizio`), giving the PHP process the same filesystem permissions as the developer. Production pools should run as isolated, unprivileged users with minimal permissions.

### System-Level Concerns

- **No firewall configuration**: HTE-CLI does not configure `iptables`, `nftables`, or `ufw`. A production LAMP server needs strict ingress rules.
- **No log rotation or monitoring**: Logs are written to the document root with no rotation policy. Production environments need centralized logging with alerting.
- **No fail2ban or intrusion detection**: No integration with fail2ban, OSSEC, or any IDS/IPS.

Even the `hardened` profile, while significantly more restrictive, is designed for **security testing**, not for production workloads. It disables functions that many frameworks (Laravel, Symfony) require to operate, and it does not address the Apache-level and system-level concerns listed above.

**Bottom line**: if you need a production LAMP setup, use a proper configuration management tool (Ansible, Puppet, Chef) with security baselines from CIS Benchmarks, and follow the hardening guides listed below.

## Hardening Resources

If you need to deploy a LAMP stack in production, the following resources provide comprehensive hardening guidance. All links have been verified as of March 2026.

### Apache

| Resource | Description |
|----------|-------------|
| [Apache Security Tips (TecMint)](https://www.tecmint.com/apache-security-tips/) | 18 practical hardening recommendations. Updated October 2024. |
| [20 Ways to Secure Your Apache Configuration (Pete Freitag)](https://www.petefreitag.com/item/505.cfm) | Covers ServerTokens, mod_security, TLS hardening, IP restrictions. Maintained since 2005, updated June 2024. |
| [Apache Web Server Hardening (Geekflare)](https://geekflare.com/apache-web-server-hardening-security/) | ModSecurity, OWASP CRS, HTTP method restrictions, WebDAV hardening. Updated March 2025. |

### MySQL / MariaDB

| Resource | Description |
|----------|-------------|
| [How To Secure MySQL and MariaDB (DigitalOcean)](https://www.digitalocean.com/community/tutorials/how-to-secure-mysql-and-mariadb-databases-in-a-linux-vps) | Foundation-level database security. Published 2013, still relevant for core concepts. |
| [Making MySQL Secure Against Attackers (MySQL 8.4 Docs)](https://dev.mysql.com/doc/refman/8.4/en/security-against-attack.html) | Official MySQL security reference. Use the 8.4 (LTS) or 9.x version. |

### PHP

| Resource | Description |
|----------|-------------|
| [15 LAMP Stack Security Tips (TecAdmin)](https://tecadmin.net/security-tips-for-lamp-stack-on-linux/) | Apache, MySQL, and PHP hardening in one guide. Updated April 2025. |

### Linux System Hardening

| Resource | Description |
|----------|-------------|
| [CIS Benchmarks](https://www.cisecurity.org/cis-benchmarks) | Industry-standard security configuration baselines for 25+ product families (Ubuntu, Debian, Apache, MySQL, PHP). Free PDF downloads. |
| [Security Harden CentOS 7 (HighOn.Coffee)](https://highon.coffee/blog/security-harden-centos-7/) | Comprehensive guide covering partitions, SELinux, auditd, SSH, kernel hardening. Based on OpenSCAP benchmarks. |

### Removed Links

The following links from the previous version of this document have been removed because they are no longer accessible or are severely outdated:

- ~~Hardening CentOS (wiki.centos.org Dojo Madrid 2013 PDF)~~ — 404, CentOS wiki reorganized.
- ~~OWASP Backend Security Project MySQL Hardening~~ — 404, OWASP wiki retired.
- ~~Linux: 25 PHP Security Best Practices (cyberciti.biz)~~ — 403 Forbidden.
- ~~Keeping PHP Database Passwords Secure (uranus.chrysocome.net)~~ — Content from 2005, describes an obsolete Apache module approach. Modern alternatives: environment variables, secrets managers (Vault, AWS Secrets Manager), or `.env` files with restricted permissions.
- ~~CentOS HowTos OS Protection (wiki.centos.org)~~ — Archived read-only page for CentOS 5 (EOL 2017).
- ~~JShielder (GitHub)~~ — Dormant since ~2018, targets Ubuntu 16.04/18.04 (both EOL). 20 unresolved issues.

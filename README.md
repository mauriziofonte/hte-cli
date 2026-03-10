# Ubuntu/Debian compatible LAMP Test Environment Creator CLI Tool

> **Heads Up!** This utility perfectly couples with the [https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup](https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup) WSL2 setup, for a complete **LAMP** stack on Windows 11.
>
> However, this utility can be used in any **LAMP** stack on _Debian_ or _Ubuntu_ , with **Multi PHP-FPM** support.
>
> Ah, one more thing: checking a perfect companion for **HTE-Cli**? Check out the [Gash Bash](https://github.com/mauriziofonte/gash), another creation of mine :)

------

<p align="center">
    <img title="HTE-Cli Tool" width="70%" src="https://raw.githubusercontent.com/mauriziofonte/hte-cli/main/screenshots/hte-create.png" />
</p>

<p align="center">
  <a href="https://raw.githubusercontent.com/mauriziofonte/hte-cli/main/screenshots/hte-create.png">Screenshot of <code>hte-cli create</code></a>
  <a href="https://raw.githubusercontent.com/mauriziofonte/hte-cli/main/screenshots/hte-remove.png">Screenshot of <code>hte-cli remove</code></a>
  <a href="https://raw.githubusercontent.com/mauriziofonte/hte-cli/main/screenshots/hte-details.png">Screenshot of <code>hte-cli details</code></a>
</p>

------

## What is this utility for?

> **HTE** stands for **H**omelab **T**est **E**nvironment.

It eases the process of creating _Test Environments_ on both **Local** or **Staging** environments, when using Apache on a multi PHP-FPM flavour.

Basically, this utility takes care of :

1. Creating a new _VirtualHost_ for **your.local.domain.tld**
2. Configuring the _VirtualHost_ to work via **php-fpm** on the PHP version of _User's Choice_
3. Enabling _http/2_ protocol and _brotli_ compression
4. Configuring the PHP _fpm pool_ by adding a new specific configuration file for **your.local.domain.tld**, with security profiles (dev/staging/hardened)
5. Automatically managing `/etc/hosts` entries for your local domains
6. Providing configurable PHP-FPM security profiles for different use cases

## Security

This tool **is not intended to be used on internet-facing LAMP environments**. It's <ins>intended to be used on local devices</ins>, or any other Cloud VM that **is properly firewalled**.

This is because this tool configures _Apache_ and _PHP_ with presets that are **good only for local developing, testing and benchmarking**.

For more informations, refer to [LAMP Stack Hardening and Linux Security Bookmarks](/LAMP-HARDENING.md).

## Installation

The easiest way to get started with _Hte-Cli_ is to download the Phar files for each of the commands:

```bash
# Download using curl
curl -OL https://github.com/mauriziofonte/hte-cli/raw/main/builds/hte-cli

# Or download using wget
wget https://github.com/mauriziofonte/hte-cli/raw/main/builds/hte-cli

# Then move the cli tool to /usr/local/bin
sudo mv hte-cli /usr/local/bin/hte-cli && sudo chmod +x /usr/local/bin/hte-cli
```

### Global Composer Package

If you use Composer, you can install _Hte-Cli_ system-wide with the following command:

```bash
composer global require "mfonte/hte-cli=*"
```

Make sure you have the composer bin dir in your `PATH`.

The default value _should_ be `~/.composer/vendor/bin/`, but you can check the value that you need to use by running `composer global config bin-dir --absolute`

The `HTE-Cli` Tool will then be available on `$(composer config -g home)/vendor/bin/hte-cli`

It is suggested to modify your **bash profile** to expand your `$PATH` so that it includes the `composer/vendor/bin` directory. To do so, you can modify your `.bashrc` file by executing:

```bash
echo 'export PATH="$(composer config -g home)/vendor/bin:$PATH"' >> ~/.bashrc
```

### Composer Dependency

Alternatively, include a dependency for `mfonte/hte-cli` in your composer.json file on a specific project. For example:

```json
{
    "require-dev": {
        "mfonte/hte-cli": "*"
    }
}
```

You will then be able to run _Hte-Cli_ from the vendor bin directory:

```bash
/path/to/project/dir/vendor/bin/hte-cli -h
```

You can then create some _Bash Aliases_ for your convenience:

```bash
alias hte="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli"
alias hte-create="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli create"
alias hte-remove="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli remove"
alias hte-modify="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli modify"
alias hte-details="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli details"
alias hte-hosts="sudo /usr/bin/php8.4 /path/to/project/dir/vendor/bin/hte-cli hosts"
```

### Git Clone

You can also download the _Hte-Cli_ source, and run your own `hte-cli` build:

```bash
git clone https://github.com/mauriziofonte/hte-cli && cd hte-cli
composer install
php hte app:build hte-cli
```

Build setup is made possible via [humbug/box](https://github.com/box-project/box). See [Laravel Zero Doc](https://laravel-zero.com/docs/build-a-standalone-application) for the internals of building a Laravel Zero app.

## Environment Pre-requisites

As said before, this utility is intended to be used on _Debian_ or _Ubuntu_ , with **Multi PHP-FPM** support.

This means that you must have a _LAMP_ stack compatible with **multiple PHP versions** and configured to use _PHP-FPM_ by default. You can achieve this by following the instructions below:

```console
# APACHE on Multi-PHP-FPM
sudo apt-get --assume-yes --quiet install curl ca-certificates apt-transport-https software-properties-common
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/apache2
sudo apt update && sudo apt upgrade
PHPVERS="8.5 8.4 8.3 8.2 8.1 8.0 7.4 7.3 7.2 7.1 7.0 5.6"
PHPMODS="cli bcmath bz2 curl fpm gd gmp igbinary imagick imap intl mbstring mcrypt memcached msgpack mysql readline redis soap sqlite3 xsl zip"
APTPACKS=$(for VER in $PHPVERS; do echo -n "libapache2-mod-php$VER php$VER "; for MOD in $PHPMODS; do if [[ "$MOD" == "mcrypt" && "${VER/./}" -ge 83 ]]; then continue; fi; echo -n "php$VER-$MOD "; done; done)
sudo apt install -y apache2 brotli openssl libapache2-mod-fcgid $APTPACKS
sudo a2dismod $(for VER in $PHPVERS; do echo -n "php$VER "; done) mpm_prefork
sudo a2enconf $(for VER in $PHPVERS; do echo -n "php$VER-fpm "; done)
sudo a2enmod actions fcgid alias proxy_fcgi setenvif rewrite headers ssl http2 mpm_event brotli
```

The important things are:

1. **disable** `mpm_prefork` module
2. **disable** all `php**` handler modules
3. **enable** all `php**-fpm` handler modules
4. **enable** `fcgid`, `mpm_event`, `proxy_fcgi`, `alias` modules (and, obviously, `ssl` and `rewrite`) - `http2`, `brotli`, `headers`, `setenvif` are optional, but suggested.

## Usage

### Create a new test LAMP Env

To create a new LAMP local _testing/developing_ VirtualHost, run the `HTE-Cli` tool with option `create`.

In this example, the `HTE-Cli` _create_ has been _aliased_ with the `hte-create` command.

```bash
maurizio:~ $ hte-create
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.13 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

 Enter a valid local Domain Name (suggested .test TLD, as "jane.local.test") []:
 > some.localdomain.test

 Enter a valid directory in the filesystem for the DocumentRoot [/home/maurizio]:
 > /home/maurizio/projects/localdomain/public

 Enter a valid PHP version for PHP-FPM (5.6, 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.3, 8.4, 8.5) [8.5]:
 > 8.5

 Do you need HTTPS support? ["yes", "no", "y" or "n"] [y]:
 > y

 Do you want to force HTTPS? ["yes", "no", "y" or "n"] [y]:
 > y

 Add domain to /etc/hosts? ["yes", "no", "y" or "n"] [y]:
 > y

 PHP-FPM hardening profile (dev, staging, hardened) [dev]:
 > dev

VirtualHost configuration for some.localdomain.test created at /etc/apache2/sites-available/008-some.localdomain.test.conf
PHP8.5-FPM configuration for some.localdomain.test created at /etc/php/8.5/fpm/pool.d/some.localdomain.test.conf (profile: dev)
Self-signed SSL certificate script for some.localdomain.test created at /tmp/sscert_some.localdomain.testeNRXv2
Executing the self-signed SSL certificate script for some.localdomain.test...
 > Removing existing previous self-signed certs with pattern some.localdomain.test.*
 > Generating certs for some.localdomain.test
 > Generating RSA private key, 2048 bit long modulus
 > Writing info to /etc/apache2/certs-selfsigned/some.localdomain.test.info
 > Protecting the key with chmod 400 /etc/apache2/certs-selfsigned/some.localdomain.test.key
 > Removing the temporary config file /tmp/openssl.cnf.0XLN2i
Enabling some.localdomain.test on config 008-some.localdomain.test...
Restarting Apache2...
Restarting PHP8.4-FPM...
Adding some.localdomain.test to /etc/hosts...
Added some.localdomain.test to /etc/hosts
VirtualHost some.localdomain.test created successfully!
```

#### Command Line Options for Create

You can also run the create command non-interactively:

```bash
hte-cli create \
    --domain=myapp.test \
    --docroot=/var/www/myapp/public \
    --phpver=8.5 \
    --ssl=yes \
    --forcessl=yes \
    --hosts=yes \
    --profile=dev
```

### Modify an existing VirtualHost

To modify an existing VirtualHost configuration, use the `modify` command. This command provides an interactive menu to change any aspect of your VirtualHost.

```bash
maurizio:~ $ hte-cli modify
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.13 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

+-------+--------------------------+------+-----+-----------+---------+
| Index | Domain                   | PHP  | SSL | Force SSL | Enabled |
+-------+--------------------------+------+-----+-----------+---------+
| 1     | local.phpmyadmin.test    | 8.4  | Yes | Yes       | Yes     |
| 2     | myapp.test               | 8.3  | Yes | Yes       | Yes     |
| 3     | some.localdomain.test    | 8.5  | Yes | Yes       | Yes     |
+-------+--------------------------+------+-----+-----------+---------+

 Select the domain to modify:
  [0] local.phpmyadmin.test
  [1] myapp.test
  [2] some.localdomain.test
 > 2

Modifying VirtualHost: some.localdomain.test

Current configuration:
  Document Root: /home/maurizio/projects/localdomain/public
  PHP Version: 8.5
  SSL: Enabled
  Force HTTPS: Yes
  Profile: dev

 What would you like to modify?
  [0] PHP Version (current: 8.5)
  [1] SSL (current: Enabled)
  [2] Force HTTPS (current: Yes)
  [3] PHP-FPM Profile (current: dev)
  [4] Document Root (current: /home/maurizio/projects/localdomain/public)
  [5] ---
  [6] Done - Apply changes and exit
  [7] Cancel - Exit without changes
 >
```

#### Command Line Options for Modify

You can also run modify non-interactively:

```bash
hte-cli modify \
    --domain=myapp.test \
    --phpver=8.3 \
    --ssl=yes \
    --forcessl=no \
    --profile=staging \
    --docroot=/var/www/myapp/public
```

### Delete a Test Env created via HTE-Cli

To remove a LAMP local _testing/developing_ VirtualHost **previously created with HTE-Cli Tool**, run the `HTE-Cli` tool with option `remove`.

The remove command now features an interactive selection menu and automatic index normalization.

```bash
maurizio:~ $ hte-remove
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.13 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

+-------+--------------------------+------+-----+----------------------------------------+---------+
| Index | Domain                   | PHP  | SSL | Document Root                          | Enabled |
+-------+--------------------------+------+-----+----------------------------------------+---------+
| 1     | local.phpmyadmin.test    | 8.4  | Yes | /home/maurizio/opt/phpmyadmin          | Yes     |
| 2     | myapp.test               | 8.3  | Yes | /home/maurizio/projects/myapp/public   | Yes     |
| 3     | some.localdomain.test    | 8.5  | Yes | /home/maurizio/projects/localdomain    | Yes     |
+-------+--------------------------+------+-----+----------------------------------------+---------+

 Select the VirtualHost to remove:
  [0] local.phpmyadmin.test
  [1] myapp.test
  [2] some.localdomain.test
 > 2

The following will be removed:
  Domain: some.localdomain.test
  Apache config: /etc/apache2/sites-available/003-some.localdomain.test.conf
  Document Root: /home/maurizio/projects/localdomain (will NOT be deleted)
  PHP-FPM config: /etc/php/8.5/fpm/pool.d/some.localdomain.test.conf
  SSL Certificate: /etc/apache2/certs-selfsigned/some.localdomain.test.crt
  SSL Key: /etc/apache2/certs-selfsigned/some.localdomain.test.key
  /etc/hosts entry: Will be removed

 Are you sure you want to remove the VirtualHost for some.localdomain.test? (yes/no) [no]:
 > yes

Removing VirtualHost some.localdomain.test...
Disabling site 003-some.localdomain.test...
Removed Apache configuration: /etc/apache2/sites-available/003-some.localdomain.test.conf
Removed PHP8.5-FPM configuration: /etc/php/8.5/fpm/pool.d/some.localdomain.test.conf
Removed SSL certificate: /etc/apache2/certs-selfsigned/some.localdomain.test.crt
Removed SSL key: /etc/apache2/certs-selfsigned/some.localdomain.test.key
Restarting Apache2...
Apache2 restarted successfully
Restarting PHP8.5-FPM...
PHP8.5-FPM restarted successfully
Removing some.localdomain.test from /etc/hosts...
Removed some.localdomain.test from /etc/hosts

Normalizing VirtualHost indexes...
No renumbering needed

VirtualHost some.localdomain.test removed successfully!
```

#### Command Line Options for Remove

```bash
# Non-interactive removal with automatic confirmation
hte-cli remove --domain=myapp.test --force

# Skip index normalization
hte-cli remove --domain=myapp.test --force --no-normalize
```

### List all Test Envs created via HTE-Cli

To list all LAMP local environments **previously created with HTE-Cli Tool**, run the `HTE-Cli` tool with option `details`.

```bash
maurizio:~ $ hte-details
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.13 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

VHosts Count: 3
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+
| Index | Domain / DocRoot                                          | PHP Version | SSL? | Forced SSL? | Enabled |
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+
| 1     | local.phpmyadmin.test                                     | 8.4         | 1    | 1           | 1       |
|       |  > /home/maurizio/opt/phpmyadmin                          |             |      |             |         |
| 2     | myapp.test                                                | 8.3         | 1    | 1           | 1       |
|       |  > /home/maurizio/projects/myapp/public                   |             |      |             |         |
| 3     | some.localdomain.test                                     | 8.4         | 1    | 1           | 1       |
|       |  > /home/maurizio/projects/localdomain/public             |             |      |             |         |
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+

 Optionally type in a domain name for the PHP-FPM details []:
 > some.localdomain.test

PHP-FPM Configuration for some.localdomain.test:
PHP-FPM Version: 8.4
PHP-FPM Config File: /etc/php/8.4/fpm/pool.d/some.localdomain.test.conf
[some.localdomain.test]
; Profile: dev
user = maurizio
group = maurizio
listen = /var/run/php/php8.4-fpm-some.localdomain.test.sock
listen.owner = maurizio
listen.group = maurizio
listen.mode = 0660

; Security: Disabled functions
php_admin_value[disable_functions] = apache_child_terminate,apache_get_modules,apache_getenv,apache_note,apache_setenv

; Security: URL handling
php_admin_flag[allow_url_fopen] = off

; Process manager
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /

; Logging
catch_workers_output = yes
request_terminate_timeout = 180s
slowlog = /home/maurizio/projects/localdomain/public/php8.4-fpm-slow.log
php_flag[display_errors] = off
php_admin_value[error_log] = /home/maurizio/projects/localdomain/public/php8.4-fpm-errors.log
php_admin_flag[log_errors] = on

; Resource limits
php_admin_value[post_max_size] = 128M
php_admin_value[upload_max_filesize] = 128M
php_admin_value[memory_limit] = 1024M
php_value[memory_limit] = 1024M

; PHP settings
php_value[short_open_tag] = On
```

### Fix old VirtualHost configurations

If you have VirtualHosts created with an older version of HTE-CLI, they may not have the localhost HTTPS bypass feature. The `fix` command updates these configurations.

```bash
maurizio:~ $ sudo hte-cli fix
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.13 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

Found 2 VirtualHost(s) that need the localhost HTTPS bypass fix:

+-------+-------------------+------+-----------------------------+
| Index | Domain            | PHP  | Config File                 |
+-------+-------------------+------+-----------------------------+
| 1     | old-project.test  | 8.3  | 001-old-project.test.conf   |
| 2     | legacy-app.test   | 7.4  | 002-legacy-app.test.conf    |
+-------+-------------------+------+-----------------------------+

This fix will update the Force HTTPS rewrite rules to bypass localhost requests.
After fixing, HTTP requests from 127.0.0.1 and ::1 will not be redirected to HTTPS.

 Do you want to apply the fix to these VirtualHost(s)? (yes/no) [no]:
 > yes

Applying fixes...
Fixing old-project.test...
  Fixed: old-project.test
Fixing legacy-app.test...
  Fixed: legacy-app.test

Restarting Apache2...
Apache2 restarted successfully

All 2 VirtualHost(s) have been fixed successfully!

To verify the fix, run:
  curl -v http://<domain>
HTTP requests from localhost should now work without HTTPS redirect.
```

#### Command Line Options for Fix

```bash
# Fix a specific domain only
hte-cli fix --domain=old-project.test

# Skip confirmation prompt
hte-cli fix --force

# Preview what would be fixed without making changes
hte-cli fix --dry-run

# Combined: fix specific domain without confirmation
hte-cli fix --domain=old-project.test --force
```

### Manage /etc/hosts

The `hosts` command helps you manage `/etc/hosts` entries for domains created by HTE-CLI:

```bash
# List all domains managed by hte-cli in /etc/hosts
hte-cli hosts list

# Sync /etc/hosts with all configured VirtualHosts
# This adds missing entries and removes orphaned ones
hte-cli hosts sync

# Add a domain manually
hte-cli hosts add --domain=myapp.test

# Remove a domain
hte-cli hosts remove --domain=myapp.test
```

Example output for `hosts list`:

```bash
maurizio:~ $ hte-cli hosts list

Domains in /etc/hosts managed by hte-cli:

  • local.phpmyadmin.test
  • myapp.test
  • some.localdomain.test

Total: 3 domain(s)
```

Example output for `hosts sync`:

```bash
maurizio:~ $ sudo hte-cli hosts sync

Synchronizing /etc/hosts with 3 VirtualHost(s)...

Domains to sync:
  • local.phpmyadmin.test
  • myapp.test
  • some.localdomain.test

/etc/hosts is already in sync.
```

## PHP-FPM Security Profiles

HTE-CLI supports three security profiles for PHP-FPM pools, allowing you to balance between development convenience and security:

| Profile | Description | Use Case |
|---------|-------------|----------|
| `dev` | Minimal restrictions, high memory limits (1024M), basic disabled functions | Local development |
| `staging` | Moderate security with `open_basedir`, session hardening, `pm.max_requests` | Staging/testing environments |
| `hardened` | Maximum restrictions, many functions disabled | Security testing only |

### Profile Details

#### Dev Profile (Default)

- **Memory limit:** 1024M
- **Disabled functions:** Basic Apache functions only
- **Best for:** Day-to-day development work

#### Staging Profile

- **Memory limit:** 1024M
- **Additional disabled functions:** `dl`, `highlight_file`, `show_source`, `phpinfo`
- **Additional security:**
  - `open_basedir` restricted to document root + /tmp + /usr/share/php
  - Session cookie `httponly` flag enabled
  - Session strict mode enabled
  - `pm.max_requests = 500` to prevent memory leaks
- **Best for:** Pre-production testing, CI/CD environments

#### Hardened Profile

- **Memory limit:** 256M
- **Additional disabled functions:** `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `proc_close`, `proc_get_status`, `proc_terminate`, `pcntl_exec`, `pcntl_fork`, `pcntl_signal`, `symlink`
- **Additional security:**
  - All staging security features
  - Auto prepend/append files disabled
  - PHP version hidden
  - Reduced resource limits
- **Best for:** Security testing

**Warning:** The `hardened` profile disables `exec`, `proc_open`, and other functions used by **Artisan**, **Composer**, and **PHPUnit**. Use it only for security testing purposes.

### Usage

```bash
# Create with a specific profile
hte-cli create --domain=myapp.test --profile=staging

# Change profile of existing VirtualHost
hte-cli modify --domain=myapp.test --profile=hardened
```

## Command Reference

### Available Commands

| Command | Description |
|---------|-------------|
| `create` | Create a new VirtualHost with PHP-FPM |
| `modify` | Modify an existing VirtualHost configuration |
| `remove` | Delete a VirtualHost and all its configurations |
| `details` | List all VirtualHosts with detailed information |
| `hosts` | Manage /etc/hosts entries |
| `fix` | Fix old VirtualHost configs to add localhost HTTPS bypass |

### Command Options

#### create

| Option | Description | Default |
|--------|-------------|---------|
| `--domain=` | Domain name for the VirtualHost | (interactive) |
| `--docroot=` | Document root path | (interactive) |
| `--phpver=` | PHP version (e.g., 8.4, 8.3, 7.4) | Latest installed |
| `--ssl=` | Enable SSL (yes/no) | yes |
| `--forcessl=` | Force HTTPS redirect (yes/no) | yes |
| `--hosts=` | Add to /etc/hosts (yes/no) | yes |
| `--profile=` | PHP-FPM profile (dev/staging/hardened) | dev |

#### modify

| Option | Description |
|--------|-------------|
| `--domain=` | Domain name to modify |
| `--phpver=` | New PHP version |
| `--ssl=` | Enable/disable SSL (yes/no) |
| `--forcessl=` | Enable/disable force HTTPS (yes/no) |
| `--profile=` | New PHP-FPM profile |
| `--docroot=` | New document root |
| `--interactive` | Force interactive mode |

#### remove

| Option | Description |
|--------|-------------|
| `--domain=` | Domain name to remove |
| `--force` | Skip confirmation prompt |
| `--no-normalize` | Skip index normalization |

#### hosts

| Subcommand | Description |
|------------|-------------|
| `list` | Show domains in /etc/hosts managed by hte-cli |
| `sync` | Sync /etc/hosts with configured VirtualHosts |
| `add --domain=` | Add a domain to /etc/hosts |
| `remove --domain=` | Remove a domain from /etc/hosts |

#### fix

| Option | Description |
|--------|-------------|
| `--domain=` | Fix a specific domain only |
| `--force` | Skip confirmation prompt |
| `--dry-run` | Preview changes without applying them |

## Localhost HTTPS Bypass

When Force HTTPS is enabled, HTE-CLI configures Apache to allow HTTP access from localhost (127.0.0.1 and ::1). This means:

- **From localhost:** `curl http://myapp.test` works without redirect
- **From external:** Browser requests are redirected to HTTPS

This is useful for local development tools, scripts, and API testing that don't handle SSL certificates well.

### .htaccess Override Warning

If your document root contains an `.htaccess` file with its own HTTPS redirect rules, those will override the VirtualHost configuration. The `fix` command automatically detects this situation and provides instructions to modify your `.htaccess` file.

Example `.htaccess` modification for localhost bypass:

```apache
# Replace this:
RewriteCond %{HTTPS} off
RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# With this (adds localhost bypass):
RewriteCond %{HTTPS} off
RewriteCond %{REMOTE_ADDR} !^127\.0\.0\.1$
RewriteCond %{REMOTE_ADDR} !^::1$
RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## Credits

A big thank you goes to [Nuno Maduro](https://github.com/nunomaduro) and [Owen Voke](https://github.com/owenvoke) for their [Laravel Zero](https://github.com/laravel-zero/laravel-zero) micro-framework.

## Contributing

See [DEVELOPMENT.md](DEVELOPMENT.md) for development setup, coding standards, and contribution guidelines.

## License

MIT License - see [LICENSE](LICENSE) for details.

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

> **HTE** stands for **H**andle **T**est **E**nvironment.

It eases the process of creating _Test Environments_ on both **Local** or **Staging** environments, when using Apache on a multi PHP-FPM flavour.

Basically, this utility takes care of :

1. creating a new _VirtualHost_ for **your.local.domain.tld**
2. configure the _VirtualHost_ to work via **php-fpm** on the PHP version of _User's Choice_
3. enable _http/2_ protocol and _brotli_ compression
4. configure the PHP _fpm pool_ by adding a new specific configuration file for **your.local.domain.tld**, with some useful test environment presets.

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
alias hte="sudo /usr/bin/php8.3 /path/to/project/dir/vendor/bin/hte-cli"
alias hte-create="sudo /usr/bin/php8.3 /path/to/project/dir/vendor/bin/hte-cli create"
alias hte-remove="sudo /usr/bin/php8.3 /path/to/project/dir/vendor/bin/hte-cli remove"
alias hte-details="sudo /usr/bin/php8.3 /path/to/project/dir/vendor/bin/hte-cli details"
```

### Git Clone

You can also download the _Hte-Cli_ source, and run your own `hte-cli` build:

```bash
git clone https://github.com/mauriziofonte/hte-cli && cd hte-cli
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
PHPVERS="8.4 8.3 8.2 8.1 8.0 7.4 7.3 7.2 7.1 7.0 5.6"
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

[H]andle [T]est [E]nvironment Cli Tool version 1.0.11 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

 💡 Enter a valid local Domain Name (suggested .test TLD, as "jane.local.test") []:
 > some.localdomain.test

 💡 Enter a valid directory in the filesystem for the DocumentRoot [/home/maurizio]:
 > /home/maurizio/projects/localdomain/public

 💡 Enter a valid PHP version for PHP-FPM (5.6, 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.3, 8.4) [8.4]:
 > 8.4

 💡 Do you need HTTPS support? ["yes", "no", "y" or "n"] [y]:
 > y

 💡 Do you want to force HTTPS? ["yes", "no", "y" or "n"] [y]:
 > y

⏳ VirtualHost configuration for some.localdomain.test created at /etc/apache2/sites-available/008-some.localdomain.test.conf
⏳ PHP8.4-FPM configuration for some.localdomain.test created at /etc/php/8.4/fpm/pool.d/some.localdomain.test.conf
⏳ Self-signed SSL certificate script for some.localdomain.test created at /tmp/sscert_some.localdomain.testeNRXv2
🔐️ Executing the self-signed SSL certificate script for some.localdomain.test...
 > Removing existing previous self-signed certs with pattern some.localdomain.test.*
 > Generating certs for some.localdomain.test
 > Generating RSA private key, 2048 bit long modulus
 > Writing info to /etc/apache2/certs-selfsigned/some.localdomain.test.info
 > Protecting the key with chmod 400 /etc/apache2/certs-selfsigned/some.localdomain.test.key
 > Removing the temporary config file /tmp/openssl.cnf.0XLN2i
⏳ Enabling some.localdomain.test on config 008-some.localdomain.test...
⚡ Restarting Apache2...
⚡ Restarting PHP8.4-FPM...
✅ VirtualHost some.localdomain.test created successfully!
```

After the VirtualHost setup, you can easily modify your `hosts` file by binding `some.localdomain.test` to `127.0.0.1`

### Delete a Test Env created via HTE-Cli

To remove a LAMP local _testing/developing_ VirtualHost **previously created with HTE-Cli Tool**, run the `HTE-Cli` tool with option `remove`.

In this example, the `HTE-Cli` _remove_ has been _aliased_ with the `hte-remove` command.

```bash
maurizio:~ $ hte-remove
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.11 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

+-------+---------------------------------+---------+
| Index | Domain                          | Enabled |
+-------+---------------------------------+---------+
| 1     | local.phpmyadmin.test           | 1       |
| 2     | local.whatever.app.test         | 1       |
| 3     | some.localdomain.test           | 1       |
+-------+---------------------------------+---------+

 💡 Enter the Domain Name you want to remove []:
 > some.localdomain.test

⏳ Deleting some.localdomain.test...
⏳ Disabling some.localdomain.test on config 008-some.localdomain.test...
🗑️ /etc/apache2/sites-available/008-some.localdomain.test.conf deleted
🗑️ /etc/apache2/certs-selfsigned/some.localdomain.test.crt deleted
🗑️ /etc/apache2/certs-selfsigned/some.localdomain.test.key deleted
🗑️ /etc/php/8.3/fpm/pool.d/some.localdomain.test.conf deleted
⏳ Restarting Apache2...
⏳ Restarting PHP8.3-FPM...
✅ VirtualHost some.localdomain.test deleted successfully!
```

### List all Test Envs created via HTE-Cli

To list all LAMP local environments **previously created with HTE-Cli Tool**, run the `HTE-Cli` tool with option `details`.

In this example, the `HTE-Cli` _details_ has been _aliased_ with the `hte-details` command.

```bash
maurizio:~ $ hte-details
[sudo] password for maurizio:
   __ __ ______ ____      _____ __ _
  / // //_  __// __/____ / ___// /(_)
 / _  /  / /  / _/ /___// /__ / // /
/_//_/  /_/  /___/      \___//_//_/

[H]andle [T]est [E]nvironment Cli Tool version 1.0.5 by Maurizio Fonte
WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS. Use it only on local/firewalled networks.

⚙️ VHosts Count: 3
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+
| Index | Domain / DocRoot                                          | PHP Version | SSL? | Forced SSL? | Enabled |
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+
| 1     | local.phpmyadmin.test                                     | 8.3         | 1    | 1           | 1       |
|       |  > /home/maurizio/opt/phpmyadmin                          |             |      |             |         |
| 2     | local.whatever.app.test                                   | 7.4         | 1    | 1           | 1       |
|       |  > /home/maurizio/projects/old/app/public                 |             |      |             |         |
| 3     | some.localdomain.test                                     | 8.4         | 1    | 1           | 1       |
|       |  > /home/maurizio/projects/localdomain/public             |             |      |             |         |
+-------+-----------------------------------------------------------+-------------+------+-------------+---------+

 💡 📋 Optionally type in a domain name for the PHP-FPM details []:
 > some.localdomain.test

📋 PHP-FPM Configuration for some.localdomain.test:
🔍 PHP-FPM Version: 8.4
🔍 PHP-FPM Config File: /etc/php/8.4/fpm/pool.d/some.localdomain.test.conf
[some.localdomain.test]
user = maurizio
group = maurizio
listen = /var/run/php/php8.4-fpm-some.localdomain.test.sock
listen.owner = maurizio
listen.group = maurizio
listen.mode = 0660
php_admin_value[disable_functions] = apache_child_terminate,apache_get_modules,apache_getenv,apache_note,apache_setenv
php_admin_flag[allow_url_fopen] = off
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /

catch_workers_output = yes
request_terminate_timeout = 180s
slowlog = /home/maurizio/projects/localdomain/public/php8.4-fpm-slow.log
php_flag[display_errors] = off
php_admin_value[error_log] = /home/maurizio/projects/localdomain/public/php8.4-fpm-errors.log
php_admin_flag[log_errors] = on
php_admin_value[post_max_size] = 128M
php_admin_value[upload_max_filesize] = 128M
php_admin_value[memory_limit] = 1024M
php_value[memory_limit] = 1024M
php_value[short_open_tag] =  On
```

## Credits

A big thank you goes to [Nuno Maduro](https://github.com/nunomaduro) and [Owen Voke](https://github.com/owenvoke) for their [Laravel Zero](https://github.com/laravel-zero/laravel-zero) micro-framework.

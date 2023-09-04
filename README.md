# Ubuntu/Debian compatible LAMP Test Environment Creator CLI Tool

> **Heads Up!** This utility is intended to be used in combination with [https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup](https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup)
> Anyway, this utility can be used in any **LAMP** stack on _Debian_ or _Ubuntu_ , with **Multi PHP-FPM** support.

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

For more informations, refer to [/LAMP-HARDENING.md](LAMP Stack Hardening and Linux Security Bookmarks).

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
alias hte="sudo /usr/bin/php8.2 /path/to/project/dir/vendor/bin/hte-cli"
alias hte-create="sudo /usr/bin/php8.2 /path/to/project/dir/vendor/bin/hte-cli create"
alias hte-remove="sudo /usr/bin/php8.2 /path/to/project/dir/vendor/bin/hte-cli remove"
alias hte-details="sudo /usr/bin/php8.2 /path/to/project/dir/vendor/bin/hte-cli details"
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

This means that you must configure your _LAMP_ stack with these commands:

```console
# APACHE on Multi-PHP-FPM
PHPVERS="8.2 8.1 8.0 7.4 7.3 7.2 7.1 7.0 5.6"
APTPACKS=$(for VER in $PHPVERS; do echo -n "libapache2-mod-php$VER php$VER "; done)
apt install -y apache2 brotli openssl libapache2-mod-fcgid $APTPACKS
a2dismod $(for VER in $PHPVERS; do echo -n "php$VER "; done) mpm_prefork
a2enconf $(for VER in $PHPVERS; do echo -n "php$VER-fpm "; done)
a2enmod actions fcgid alias proxy_fcgi setenvif rewrite headers ssl http2 mpm_event brotli
```

The important part is to:

1. **disable** `mpm_prefork` module
2. **disable** all `php**` handler modules
3. **enable** all `php**-fpm` handler modules
4. **enable** `fcgid`, `mpm_event`, `proxy_fcgi`, `http2`, `brotli` modules

## Usage

To-Do

## Credits

A big thank you goes to [Nuno Maduro](https://github.com/nunomaduro) and [Owen Voke](https://github.com/owenvoke) for their [Laravel Zero](https://github.com/laravel-zero/laravel-zero) micro-framework.

# Ubuntu/Debian compatible LAMP Test Environment Creator CLI Tool

> **Heads Up!** This utility is intended to be used in combination with [https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup](https://github.com/mauriziofonte/win11-wsl2-ubuntu22-setup)
> Anyway, this utility can be used in any **LAMP** stack on _Debian_ or _Ubuntu_ , with **Multi PHP-FPM** support.

## What is this utility for?

It eases the process of creating _Test Environments_ on both **Local** or **Staging** environments, when using Apache on a multi PHP-FPM flavour.

**HTE** stands for **H**andle **T**est **E**nvironment.

## Installation

To-Do

## Pre-requisites

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

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-03-10

### Added

- **Service contracts**: 9 interfaces in `app/Contracts/` defining clear boundaries for all system interactions (`ApacheManagerInterface`, `PhpFpmManagerInterface`, `HostsManagerInterface`, `SslCertManagerInterface`, `ServiceManagerInterface`, `EnvironmentCheckerInterface`, `UserContextInterface`, `FilesystemInterface`, `ProcessExecutorInterface`).
- **Service implementations**: 9 classes in `app/Services/` implementing the contracts with constructor-injected dependencies (`ApacheManager`, `PhpFpmManager`, `HostsManager`, `SslCertManager`, `ServiceManager`, `EnvironmentChecker`, `UserContext`, `SystemFilesystem`, `SystemProcessExecutor`).
- **Dependency injection**: All services registered as singletons in `AppServiceProvider`. Commands receive dependencies via constructor injection, resolved automatically by the Laravel container.
- **`CriticalErrorException`**: Replaces `exit(1)` calls, making error flow testable. Caught by `CommandWrapper::execute()` to render an error box and return exit code 1.
- **`CommandWrapper::requirePrivileges()`** and **`requireNonRootUser()`**: Guard methods that throw `CriticalErrorException` instead of inline privilege checks.
- **`CommandWrapper::resolveServices()`**: Lazy service resolution from the container, allowing test setups to bind doubles before first access.
- **`modify` command**: Interactive and non-interactive modification of existing VirtualHosts (PHP version, SSL, Force SSL, profile, document root).
- **`fix` command**: Detect and repair old VirtualHost configurations missing the localhost HTTPS bypass. Supports `--dry-run`, `--force`, and `--domain` options.
- **`hosts` command**: Manage `/etc/hosts` entries with `list`, `sync`, `add`, and `remove` subcommands.
- **PHP-FPM security profiles**: Three profiles (`dev`, `staging`, `hardened`) with configurable disabled functions, `open_basedir`, session hardening, and resource limits.
- **`PhpProfiles`**: Static utility class for pure-computation profile lookups (disabled functions, additional config, descriptions).
- **`InteractiveMenu` trait**: Extracted CLI menu functionality using `php-school/cli-menu`.
- **Test doubles**: `InMemoryFilesystem` (full `FilesystemInterface` implementation backed by arrays) and `FakeProcessExecutor` (pattern-matched responses with command recording).
- **Test suite**: 201 tests with 426 assertions across 11 test files covering all services, test doubles, helpers, and profiles.
- **PHPUnit 9.5 configuration**: `phpunit.xml` with `tests/Unit/` test suite.
- **`DEVELOPMENT.md`**: Contributor guide covering architecture, dependency injection, testing with doubles, PHP 7.3 constraints, how to add commands and services, build process, and PR guidelines.
- **`CHANGELOG.md`**: Project changelog following Keep a Changelog format.
- **`VERSION` file**: Single source of truth for the application version, read by `config/app.php` at runtime.
- **`scripts/version-bump.php`**: Helper for semantic version bumping (`patch`, `minor`, `major`).
- **Composer scripts**: `test`, `lint`, `check`, `build`, `release`, `version`, `version:patch`, `version:minor`, `version:major`.
- **PHP 8.5 support**: Added to the list of supported PHP-FPM versions.

### Changed

- **`CommandWrapper`**: Refactored from monolithic base class to thin orchestrator. Environment checks delegated to `EnvironmentCheckerInterface`. User detection delegated to `UserContextInterface`. Process execution delegated to `ProcessExecutorInterface`. `criticalError()` now throws `CriticalErrorException` instead of calling `exit(1)`. TTY detection uses injected process executor.
- **`CreateCommand`**: Refactored from static class calls to constructor-injected services. All filesystem operations use `FilesystemInterface`. All process execution uses `ProcessExecutorInterface` or `ServiceManagerInterface`.
- **`DetailsCommand`**: Refactored to use injected `ApacheManagerInterface`, `PhpFpmManagerInterface`, `HostsManagerInterface`, and `FilesystemInterface`. Profile detection delegated to `PhpFpmManager::detectProfile()`.
- **`RemoveCommand`**: Refactored to use injected services. File operations use `FilesystemInterface`. Service restarts use `ServiceManagerInterface`.
- **`helpers.php`**: Slimmed down to two pure functions (`validate_domain()`, `answer_to_bool()`). Filesystem utilities (`rrmdir`, `fbext`, `dbpat`) and process execution (`proc_exec`) moved to service classes.
- **`AppServiceProvider`**: Rewritten with 9 singleton bindings for all service contracts.
- **`README.md`**: Expanded with complete command reference, CLI option tables, PHP-FPM profile documentation, `modify`/`fix`/`hosts` command examples, localhost HTTPS bypass explanation, and `.htaccess` override guidance.
- **`composer.json`**: Added `php-school/cli-menu` dependency, `phpunit/phpunit` dev dependency, PSR-4 autoload for `Tests\` namespace, and composer scripts for testing/building/releasing.

### Removed

- **`app/Logic/Daemons/Apache2.php`**: Static class replaced by `ApacheManager` service with `ApacheManagerInterface`.
- **`app/Logic/Preprocessors/Php.php`**: Static class replaced by `PhpFpmManager` service with `PhpFpmManagerInterface`.
- **`app/Logic/System/Hosts.php`**: Static class replaced by `HostsManager` service with `HostsManagerInterface`.
- **`app/Logic/Bash/Scripts.php`**: Static class replaced by `SslCertManager` service with `SslCertManagerInterface`.
- **`proc_exec()` helper function**: Replaced by `SystemProcessExecutor` implementing `ProcessExecutorInterface`.
- **`rrmdir()`, `fbext()`, `dbpat()` helper functions**: Replaced by `SystemFilesystem` implementing `FilesystemInterface`.
- **`exit(1)` calls**: Replaced by `CriticalErrorException` throughout the codebase.
- **Direct filesystem calls in commands**: All `is_file()`, `file_get_contents()`, `file_put_contents()`, `unlink()`, `mkdir()`, `chmod()` calls replaced with `FilesystemInterface` methods.

## [1.0.12] - 2025-01-22

### Changed

- Reverted to Laravel Zero 8 for PHP 7.3 compatibility.

### Fixed

- Bugs in sudo privilege handling.

## [1.0.11] - 2024-10-26

### Added

- PHP 8.4 support.

### Fixed

- Numerous bug fixes in VirtualHost management.

## [1.0.10] - 2023-12-22

### Added

- PHP 8.3 support.

### Fixed

- Minor fix on `validate_domain()` regexp.

## [1.0.9] - 2023-10-30

### Added

- Ability to run hte-cli as the root user.

## [1.0.8] - 2023-09-26

### Fixed

- Accept IP addresses as valid domain names.

### Changed

- Improved documentation with usage instructions and screenshots.
- Renamed `delete` command to `remove`.
- Fixed bug in SSL option handling.
- Fixed bug in `CreateCommand`.
- Removed debug dump from `DetailsCommand`.

## [1.0.0] - 2023-09-01

### Added

- Initial release.
- `create` command for Apache VirtualHost creation with PHP-FPM support.
- `remove` command for VirtualHost removal.
- `details` command for listing all configured VirtualHosts.
- Self-signed SSL certificate generation.
- Multi PHP-FPM version support.
- HTTP/2 and Brotli compression support.
- `/etc/hosts` automatic management.

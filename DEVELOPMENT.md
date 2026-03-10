# Development Guide

This document provides instructions for developers who want to contribute to HTE-CLI or build it from source.

## Prerequisites

- **PHP 7.3+** (supports up to PHP 8.5)
- **Composer** (v2 recommended)
- **Git**
- **Apache2 with PHP-FPM** (for manual testing only)

## Installation for Development

```bash
# Clone the repository
git clone https://github.com/mauriziofonte/hte-cli.git
cd hte-cli

# Install dependencies
composer install

# Verify installation
php hte --version
```

## Project Structure

```
hte-cli/
├── app/
│   ├── Commands/                   # CLI commands (auto-discovered by Laravel Zero)
│   │   ├── CreateCommand.php       # Create VirtualHost
│   │   ├── ModifyCommand.php       # Modify VirtualHost
│   │   ├── RemoveCommand.php       # Remove VirtualHost
│   │   ├── DetailsCommand.php      # List VirtualHosts
│   │   ├── HostsCommand.php        # Manage /etc/hosts
│   │   └── FixCommand.php          # Fix old VirtualHost configs
│   ├── Contracts/                  # Service interfaces (9 total)
│   │   ├── ProcessExecutorInterface.php
│   │   ├── FilesystemInterface.php
│   │   ├── ApacheManagerInterface.php
│   │   ├── PhpFpmManagerInterface.php
│   │   ├── HostsManagerInterface.php
│   │   ├── SslCertManagerInterface.php
│   │   ├── ServiceManagerInterface.php
│   │   ├── EnvironmentCheckerInterface.php
│   │   └── UserContextInterface.php
│   ├── Services/                   # Contract implementations (9 total)
│   │   ├── SystemProcessExecutor.php
│   │   ├── SystemFilesystem.php
│   │   ├── ApacheManager.php
│   │   ├── PhpFpmManager.php
│   │   ├── HostsManager.php
│   │   ├── SslCertManager.php
│   │   ├── ServiceManager.php
│   │   ├── EnvironmentChecker.php
│   │   └── UserContext.php
│   ├── Exceptions/
│   │   └── CriticalErrorException.php  # Replaces exit(1) for testability
│   ├── Logic/
│   │   └── Preprocessors/
│   │       └── PhpProfiles.php     # PHP-FPM security profiles (pure static)
│   ├── Providers/
│   │   └── AppServiceProvider.php  # Wires all DI bindings
│   ├── Traits/
│   │   └── InteractiveMenu.php     # CLI menu functionality (cli-menu)
│   ├── CommandWrapper.php          # Base command class with pre-flight checks
│   └── helpers.php                 # Pure functions: validate_domain(), answer_to_bool()
├── tests/
│   ├── Doubles/                    # Test doubles
│   │   ├── InMemoryFilesystem.php  # FilesystemInterface implementation
│   │   └── FakeProcessExecutor.php # ProcessExecutorInterface implementation
│   ├── Unit/                       # Unit tests (11 files)
│   │   ├── ApacheManagerTest.php
│   │   ├── PhpFpmManagerTest.php
│   │   ├── HostsManagerTest.php
│   │   ├── ServiceManagerTest.php
│   │   ├── EnvironmentCheckerTest.php
│   │   ├── UserContextTest.php
│   │   ├── SystemFilesystemTest.php
│   │   ├── SystemProcessExecutorTest.php
│   │   ├── InMemoryFilesystemTest.php
│   │   ├── HelpersTest.php
│   │   └── PhpProfilesTest.php
│   └── TestCase.php                # Base test case with temp dir helpers
├── scripts/
│   └── version-bump.php            # Version bump helper
├── builds/
│   └── hte-cli                     # Built phar file
├── bootstrap/
│   └── app.php                     # Application bootstrap
├── config/
│   └── app.php                     # Application configuration
├── box.json                        # Box phar builder config
├── composer.json
├── phpunit.xml
└── VERSION                         # Current version number
```

## Architecture Overview

HTE-CLI follows a SOLID architecture with dependency injection via Laravel's service container.

### Contracts and Services

Every system interaction is abstracted behind an interface in `app/Contracts/` and implemented in `app/Services/`:

| Contract                       | Implementation          | Responsibility                                 |
| ------------------------------ | ----------------------- | ---------------------------------------------- |
| `ProcessExecutorInterface`     | `SystemProcessExecutor` | Execute shell commands                         |
| `FilesystemInterface`          | `SystemFilesystem`      | Read/write files and directories               |
| `ApacheManagerInterface`       | `ApacheManager`         | Generate and manage Apache VirtualHost configs |
| `PhpFpmManagerInterface`       | `PhpFpmManager`         | Generate and manage PHP-FPM pool configs       |
| `HostsManagerInterface`        | `HostsManager`          | Manage `/etc/hosts` entries                    |
| `SslCertManagerInterface`      | `SslCertManager`        | Generate self-signed SSL certificate scripts   |
| `ServiceManagerInterface`      | `ServiceManager`        | Restart/reload system daemons                  |
| `EnvironmentCheckerInterface`  | `EnvironmentChecker`    | Verify OS, binaries, PHP functions             |
| `UserContextInterface`         | `UserContext`           | Detect user identity and sudo capabilities     |

Services depend only on other interfaces, never on concrete classes. For example, `ApacheManager` receives a `FilesystemInterface` in its constructor, not a `SystemFilesystem`.

### Dependency Graph

```
Low-level infrastructure:
  ProcessExecutorInterface  (no dependencies)
  FilesystemInterface       (no dependencies)

Mid-level:
  EnvironmentCheckerInterface  ← ProcessExecutorInterface
  UserContextInterface         (no dependencies)

Domain services:
  ApacheManagerInterface     ← FilesystemInterface
  PhpFpmManagerInterface     ← FilesystemInterface
  HostsManagerInterface      ← FilesystemInterface, ProcessExecutorInterface
  SslCertManagerInterface    ← FilesystemInterface
  ServiceManagerInterface    ← ProcessExecutorInterface
```

### CommandWrapper

`CommandWrapper` is the base class for all CLI commands. It provides:

- **`preRun()`** -- OS detection, binary/function checks, TTY detection, banner display.
- **`resolveServices()`** -- Lazily resolves `EnvironmentCheckerInterface`, `UserContextInterface`, and `ProcessExecutorInterface` from the container.
- **`criticalError()`** -- Throws `CriticalErrorException` instead of calling `exit(1)`, making error flow testable.
- **`keepAsking()`** -- Interactive input with validation loop.
- **`requirePrivileges()`** / **`requireNonRootUser()`** -- Guard methods for user context.

The `execute()` method wraps `handle()` in a try/catch for `CriticalErrorException`, rendering an error box and returning exit code 1.

### Command Flow

1. User runs `hte-cli create`
2. Laravel Zero resolves `CreateCommand` from the container, auto-injecting all constructor dependencies
3. `handle()` calls `preRun()` for pre-flight checks
4. The command interacts with injected services (`$this->apache`, `$this->phpFpm`, etc.)
5. Services read/write via `FilesystemInterface` and `ProcessExecutorInterface`
6. Errors throw `CriticalErrorException`, caught by `CommandWrapper::execute()`

### Static Classes

Only `PhpProfiles` remains static. It contains pure computation (disabled function lists, security profile settings) with no side effects, making DI unnecessary.

## Dependency Injection

### How Services Are Wired

All bindings live in `app/Providers/AppServiceProvider.php`. Every service is registered as a **singleton** so the same instance is reused throughout a CLI invocation:

```php
// Low-level: no dependencies
$this->app->singleton(ProcessExecutorInterface::class, function () {
    return new SystemProcessExecutor();
});

$this->app->singleton(FilesystemInterface::class, function () {
    return new SystemFilesystem();
});

// Domain services: dependencies resolved from the container
$this->app->singleton(ApacheManagerInterface::class, function ($app) {
    return new ApacheManager($app->make(FilesystemInterface::class));
});
```

### How Commands Get Services

Commands declare their dependencies as constructor parameters typed to interfaces. Laravel Zero's container auto-resolves them:

```php
class CreateCommand extends CommandWrapper
{
    /** @var ApacheManagerInterface */
    private $apache;

    /** @var PhpFpmManagerInterface */
    private $phpFpm;

    public function __construct(
        ApacheManagerInterface $apache,
        PhpFpmManagerInterface $phpFpm,
        // ... other services
    ) {
        parent::__construct();
        $this->apache = $apache;
        $this->phpFpm = $phpFpm;
    }
}
```

`CommandWrapper` itself resolves `EnvironmentCheckerInterface`, `UserContextInterface`, and `ProcessExecutorInterface` lazily via `resolveServices()`, called from `preRun()` or on first access.

## Testing Guide

### Running Tests

```bash
# Run all tests
composer test

# Or directly with PHPUnit
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/ApacheManagerTest.php

# Run specific test method
./vendor/bin/phpunit --filter=testGetConfGeneratesValidHttpConfig

# Run with coverage (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Doubles

Tests replace real system interactions with two purpose-built test doubles in `tests/Doubles/`:

#### InMemoryFilesystem

Implements `FilesystemInterface` with in-memory arrays. Supports files, directories, symlinks, permissions, and all search operations. Constructed with optional pre-populated state:

```php
$fs = new InMemoryFilesystem(
    ['/etc/apache2/sites-available/001-app.conf' => $confContent],  // files
    ['/etc/apache2/sites-available', '/etc/apache2/sites-enabled']   // dirs
);
```

Use `getFiles()`, `getDirs()`, and `getLinks()` for test assertions on filesystem state.

#### FakeProcessExecutor

Implements `ProcessExecutorInterface` with pattern-matched responses. Records all executed commands for verification:

```php
$proc = new FakeProcessExecutor();
$proc->addResponse('systemctl restart apache2', 0, '', '');
$proc->addResponse('which php8.4', 0, '/usr/bin/php8.4', '');
$proc->setDefaultResponse(0, '', '');

// After test execution:
$this->assertTrue($proc->wasExecuted('systemctl restart'));
$this->assertSame(2, $proc->executionCount('systemctl'));
```

### Writing Tests

Service tests instantiate the real service class with test doubles injected:

```php
class ApacheManagerTest extends TestCase
{
    private $fs;
    private $apache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new InMemoryFilesystem([], [
            '/test/sites-available',
            '/test/sites-enabled',
            '/test/certs',
        ]);

        $this->apache = new ApacheManager(
            $this->fs,
            '/test/sites-available',
            '/test/sites-enabled',
            '/test/certs'
        );
    }

    public function testCreateVhost(): void
    {
        $confFile = $this->apache->createVhost('app.test', '/var/www/app', '8.1', true, false);

        $this->assertNotNull($confFile);
        $this->assertTrue($this->fs->fileExists($confFile));
        $this->assertStringContainsString('ServerName app.test', $this->fs->getContents($confFile));
    }
}
```

Key principles:

1. **Test real service methods** -- do not mock the class under test, only its dependencies.
2. **Use InMemoryFilesystem** for all file operations -- never touch the real filesystem in unit tests.
3. **Use FakeProcessExecutor** for shell commands -- verify commands were issued without executing them.
4. **Pre-populate state** when testing read operations (e.g., `getVhostsList` needs config files to exist).
5. **Assert on side effects** -- check what was written to InMemoryFilesystem or which commands FakeProcessExecutor recorded.

### Base TestCase

`tests/TestCase.php` extends PHPUnit's `TestCase` and provides helpers for tests that need a real temp directory:

- `initTestRoot()` -- creates a temp dir under `/tmp`, auto-cleaned in `tearDown()`.
- `createTestFile($relativePath, $content)` -- writes a file under the test root.
- `assertFileContainsString()` / `assertFileNotContainsString()` -- convenience assertions.

Use these for `SystemFilesystem` and `SystemProcessExecutor` tests that validate real I/O behavior. Prefer InMemoryFilesystem for everything else.

## PHP 7.3 Compatibility

This project must maintain **PHP 7.3 compatibility**. The following language features are **not allowed**:

| Feature | Available Since | Alternative |
|---------|----------------|-------------|
| Arrow functions `fn() =>` | PHP 7.4 | `function () { return ...; }` |
| Typed properties | PHP 7.4 | `/** @var Type */` docblocks |
| Named arguments | PHP 8.0 | Positional arguments |
| Union types | PHP 8.0 | `@param` / `@return` docblocks |
| Match expressions | PHP 8.0 | `switch` statements |
| Constructor property promotion | PHP 8.0 | Explicit property + assignment |
| Nullsafe operator `?->` | PHP 8.0 | Explicit null checks |

Example:

```php
// Good -- PHP 7.3 compatible
$filtered = array_filter($items, function ($item) {
    return $item['enabled'] === true;
});

// Bad -- PHP 7.4+ only
$filtered = array_filter($items, fn($item) => $item['enabled'] === true);
```

## Coding Standards

- **PSR-12** coding standard
- **4 spaces** for indentation (no tabs)
- **LF** line endings
- **Max 160 characters** per line
- Use strict comparisons (`===` and `!==`)
- Add type hints where possible (PHP 7.3 compatible: parameter types, return types, no typed properties)
- Depend on interfaces, not concrete classes
- Favor constructor injection over service location

## How to Add a New Command

1. Create `app/Commands/MyCommand.php`:

```php
<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;

class MyCommand extends CommandWrapper
{
    protected $signature = 'mycommand {--option= : Description}';
    protected $description = 'My new command description';

    /** @var ApacheManagerInterface */
    private $apache;

    /** @var FilesystemInterface */
    private $fs;

    public function __construct(
        ApacheManagerInterface $apache,
        FilesystemInterface $fs
    ) {
        parent::__construct();
        $this->apache = $apache;
        $this->fs     = $fs;
    }

    public function handle(): void
    {
        $this->preRun();
        $this->requirePrivileges();

        // Use injected services
        $vhosts = $this->apache->getVhostsList();

        $this->info('Done!');
    }
}
```

1. The command is auto-discovered by Laravel Zero -- no registration needed.

2. Add tests in `tests/Unit/MyCommandTest.php`.

## How to Add a New Service

1. **Define the interface** in `app/Contracts/MyServiceInterface.php`:

```php
<?php

namespace Mfonte\HteCli\Contracts;

interface MyServiceInterface
{
    /**
     * @param string $input
     * @return string
     */
    public function process(string $input);
}
```

1. **Implement the service** in `app/Services/MyService.php`:

```php
<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\MyServiceInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;

class MyService implements MyServiceInterface
{
    /** @var FilesystemInterface */
    private $fs;

    public function __construct(FilesystemInterface $fs)
    {
        $this->fs = $fs;
    }

    public function process(string $input)
    {
        // Implementation using $this->fs for file operations
        return $input;
    }
}
```

1. **Register the binding** in `app/Providers/AppServiceProvider.php`:

```php
$this->app->singleton(MyServiceInterface::class, function ($app) {
    return new MyService($app->make(FilesystemInterface::class));
});
```

1. **Write tests** in `tests/Unit/MyServiceTest.php` using `InMemoryFilesystem` and/or `FakeProcessExecutor`.

2. **Inject into commands** via constructor parameter typed to `MyServiceInterface`.

## Adding a New Profile Option

1. Add constant to `PhpProfiles.php`.
2. Update `getDisabledFunctions()`, `getAdditionalConfig()`, etc.
3. Update `getAll()` and `getDescription()`.
4. Add tests for the new profile.

## Building the Phar

HTE-CLI is distributed as a Phar archive built with [Box](https://github.com/box-project/box).

### Build Commands

```bash
# Build phar (keeps current version)
composer build

# Bump version and build (patch: 1.0.13 -> 1.0.14)
composer release

# Manual version bumps
composer version:patch  # 1.0.13 -> 1.0.14
composer version:minor  # 1.0.13 -> 1.1.0
composer version:major  # 1.0.13 -> 2.0.0

# Check current version
composer version
```

### Build Output

The built phar is placed in `builds/hte-cli`. This file should be committed to the repository for distribution.

## Versioning

HTE-CLI uses [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

The version is stored in the `VERSION` file and read at runtime by `config/app.php`.

### Version Bump Workflow

```bash
# For bug fixes
composer version:patch && composer build

# For new features
composer version:minor && composer build

# For breaking changes
composer version:major && composer build

# Or use the combined release command for patches
composer release
```

## Pull Request Guidelines

### Before Submitting

1. **Run tests**: `composer test`
2. **Check PHP 7.3 compatibility**: No arrow functions, typed properties, named arguments, etc.
3. **Update documentation** if adding features
4. **Update VERSION** for significant changes
5. **Verify DI bindings**: New services must be registered in `AppServiceProvider`

### PR Checklist

- [ ] Tests pass locally
- [ ] Code follows PSR-12 style
- [ ] PHP 7.3 compatible
- [ ] New services have interfaces in `Contracts/` and bindings in `AppServiceProvider`
- [ ] Tests use `InMemoryFilesystem` / `FakeProcessExecutor` (no real I/O in unit tests)
- [ ] Documentation updated (if applicable)
- [ ] Commit messages are clear and descriptive

### Commit Message Format

```
type: short description

Longer description if needed.

Maurizio Fonte
```

Types:

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `test`: Adding/updating tests
- `refactor`: Code change that neither fixes a bug nor adds a feature
- `chore`: Maintenance tasks

## Release Process

1. Ensure all tests pass: `composer test`
2. Update `CHANGELOG.md` (if exists)
3. Bump version: `composer version:patch` (or minor/major)
4. Build phar: `composer build`
5. Test the built phar manually
6. Commit changes: `git add . && git commit -m "chore: release vX.Y.Z"`
7. Tag release: `git tag vX.Y.Z`
8. Push: `git push && git push --tags`

## Troubleshooting

### Build Fails

```bash
# Clear composer cache
composer clear-cache

# Reinstall dependencies
rm -rf vendor
composer install

# Try building again
composer build
```

### Tests Fail

```bash
# Run with verbose output
./vendor/bin/phpunit -v

# Check PHP version
php -v  # Must be 7.3+
```

### Permission Issues

HTE-CLI requires sudo for most operations. For testing:

```bash
# Run with sudo
sudo php hte create

# Or set up proper permissions for Apache/PHP-FPM directories
```

### DI Resolution Errors

If you see "target is not instantiable" errors, verify that:

1. The interface is bound in `AppServiceProvider::register()`
2. The binding closure resolves all constructor dependencies
3. There are no circular dependencies in the service graph

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/mauriziofonte/hte-cli/issues)
- **Discussions**: Open an issue with the `question` label

## License

MIT License - see [LICENSE](LICENSE) for details.

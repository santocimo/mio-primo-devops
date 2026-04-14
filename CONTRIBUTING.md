# Contributing Guidelines

## Code Quality Standards

We maintain high code quality standards using:
- PSR-12 coding standards
- PHPStan static analysis (level max)
- PHPUnit for testing

## Development Setup

### 1. Clone the repository
```bash
git clone <repository-url>
cd business-registry
```

### 2. Install dependencies
```bash
composer install
```

### 3. Set up local environment
```bash
cp .env.example .env
# Edit .env with your local database credentials
```

### 4. Run tests to verify setup
```bash
composer test
```

## Making Changes

### 1. Create a feature branch
```bash
git checkout -b feature/your-feature-name
```

### 2. Make your changes with proper structure
- Follow PSR-4 autoloading conventions
- Place new classes in appropriate `src/` subdirectories
- Add unit tests for new functionality

### 3. Run quality checks before committing
```bash
# Run tests
composer test

# Check code standards
composer lint

# Fix formatting issues automatically
composer lint-fix

# Run static analysis
composer stan
```

### 4. Commit with descriptive messages
```bash
git commit -m "Add feature: brief description"
# Include relevant issue numbers if applicable
```

### 5. Push and create a merge request
```bash
git push origin feature/your-feature-name
```

## Code Style

Follow PSR-12 standard:

```php
<?php
// Namespace declaration
namespace App\Feature;

// Use statements
use App\Database\DatabaseManager;
use App\Logger\Logger;

// Class declaration
class MyClass {
    private $property;

    public function __construct() {
        $this->property = value;
    }

    public function myMethod($param) {
        // Implementation
        return $result;
    }
}
```

## Testing Requirements

- All new features must include unit tests
- Tests should be in `tests/` directory
- Use descriptive test names: `testAddUserWithInvalidEmail()`
- Aim for >80% code coverage

Example test:
```php
<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Auth\AuthManager;

class AuthManagerTest extends TestCase {
    private $authManager;

    protected function setUp(): void {
        $this->authManager = new AuthManager();
    }

    public function testAuthenticateWithValidCredentials() {
        $result = $this->authManager->authenticate('user', 'password');
        $this->assertNotNull($result);
    }
}
```

## Documentation

- Update README.md if adding new features
- Document public methods with PHPDoc comments
- Include examples for complex features
- Update CHANGELOG if making significant changes

## Merge Request Checklist

Before submitting a merge request:

- [ ] Code follows PSR-12 standard
- [ ] Tests pass: `composer test`
- [ ] No linting errors: `composer lint`
- [ ] No static analysis issues: `composer stan`
- [ ] Tests are included for new functionality
- [ ] Documentation is updated
- [ ] No hardcoded values (use config/environment)
- [ ] All dependencies are properly declared in composer.json
- [ ] Commit messages are descriptive

## Reporting Issues

When reporting a bug, include:
1. PHP version and environment
2. Steps to reproduce the issue
3. Expected vs actual behavior
4. Any relevant error logs
5. Environment configuration (sanitized .env)

## Code Review Process

- At least one approval required before merging
- CI/CD checks must pass
- All conversations must be resolved
- Code must meet quality standards

## License

By contributing, you agree that your contributions will be licensed under the same license as the project.

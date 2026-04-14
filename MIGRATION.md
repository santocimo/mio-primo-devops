# Migration Guide: From Legacy to Professional Architecture

## Overview

This project has been refactored to follow professional PHP standards and best practices. The old code still works through backward compatibility, but new code should use the modern architecture.

## Key Changes

### 1. Configuration Management

#### Old Way (Direct Hardcoding)
```php
$host = 'database-santo';
$pass = 'password_segreta';
```

#### New Way (Environment Variables)
```php
require_once __DIR__ . '/bootstrap.php';

$host = getenv('DB_HOST');
$pass = getenv('DB_PASSWORD');
```

Configuration is loaded from `.env` file automatically by `bootstrap.php`.

---

### 2. Database Connection

#### Old Way (Function-based)
```php
require_once __DIR__ . '/db.php';
$pdo = getPDO();
```

#### New Way (Class-based)
```php
require_once __DIR__ . '/bootstrap.php';

use App\Database\DatabaseManager;

$pdo = DatabaseManager::getInstance()->getConnection();
```

The old `getPDO()` function still works for backward compatibility.

---

### 3. Session Management

#### Old Way (Direct $_SESSION)
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 123;
```

#### New Way (SessionManager)
```php
require_once __DIR__ . '/bootstrap.php';

use App\Auth\SessionManager;

SessionManager::set('user_id', 123);
$userId = SessionManager::get('user_id');
```

---

### 4. Authentication

#### Old Way (Inline Login Logic)
```php
if ($u === 'admin' && $p === 'admin123') {
    $_SESSION['admin_logged'] = true;
}
```

#### New Way (AuthManager Class)
```php
require_once __DIR__ . '/bootstrap.php';

use App\Auth\AuthManager;
use App\Auth\SessionManager;

$auth = new AuthManager();
$user = $auth->authenticate($username, $password);

if ($user) {
    SessionManager::set('user_id', $user['id']);
    SessionManager::set('admin_logged', true);
}
```

---

### 5. Logging

#### Old Way (No Logging)
```php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Bad practice!
```

#### New Way (Professional Logging)
```php
require_once __DIR__ . '/bootstrap.php';

use App\Logger\Logger;

$logger = Logger::getInstance();
$logger->error('Something went wrong', ['user_id' => 123]);
$logger->info('User logged in', ['username' => $user]);
```

Logs are stored in `logs/YYYY-MM-DD.log` with configurable levels.

---

### 6. Error Handling

#### Old Way (No Error Handler)
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

#### New Way (Set Error Handlers)
```php
require_once __DIR__ . '/bootstrap.php';

// bootstrap.php sets up error/exception handlers
// They log errors and handle them gracefully
```

Error handlers are automatically configured by `bootstrap.php`.

---

## File Structure Changes

### Before
```
index.php
login.php
db.php
inc/security.php
inc/labels.php
inc/validation.php
```

### After
```
bootstrap.php
db.php (now backward compatibility layer)
config/
  ├── app.php
  └── loader.php
src/
  ├── Auth/
  │   ├── AuthManager.php
  │   └── SessionManager.php
  ├── Database/
  │   └── DatabaseManager.php
  └── Logger/
      └── Logger.php
logs/
  └── YYYY-MM-DD.log
```

---

## Refactoring Your Code

### Step 1: Update Entry Point

Add to top of every PHP file:
```php
<?php
require_once __DIR__ . '/bootstrap.php';
```

Remove old includes:
```php
// DELETE THESE:
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/db.php';
```

### Step 2: Replace Direct PDO Usage

Old:
```php
$pdo = getPDO();
try {
    $stmt = $pdo->prepare("SELECT * FROM users");
    // ...
}
```

New:
```php
use App\Database\DatabaseManager;

$pdo = DatabaseManager::getInstance()->getConnection();
try {
    $stmt = $pdo->prepare("SELECT * FROM users");
    // ...
}
```

Or keep using `getPDO()` - it's still available for backward compatibility.

### Step 3: Replace Direct $_SESSION Usage

Old:
```php
$_SESSION['user_id'] = $user['id'];
$userId = $_SESSION['user_id'] ?? null;
```

New:
```php
use App\Auth\SessionManager;

SessionManager::set('user_id', $user['id']);
$userId = SessionManager::get('user_id');
```

### Step 4: Add Logging

Old:
```php
// No logging
```

New:
```php
use App\Logger\Logger;

$logger = Logger::getInstance();

try {
    // Your code
} catch (Exception $e) {
    $logger->error('Operation failed', ['error' => $e->getMessage()]);
}
```

### Step 5: Handle Authentication Properly

Old:
```php
$u = $_POST['username'] ?? '';
$p = $_POST['password'] ?? '';

if ($u === 'admin' && $p === 'admin123') {
    $_SESSION['admin_logged'] = true;
}
```

New:
```php
use App\Auth\AuthManager;
use App\Auth\SessionManager;

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$auth = new AuthManager();
$user = $auth->authenticate($username, $password);

if ($user) {
    SessionManager::set('user_id', $user['id']);
    SessionManager::set('user_role', $user['role']);
    SessionManager::set('admin_logged', true);
    header('Location: index.php');
    exit;
} else {
    $error = 'Invalid credentials';
}
```

---

## Migration Checklist

### Phase 1: Setup (Do First)
- [ ] Copy `.env.example` to `.env`
- [ ] Update `.env` with your database credentials
- [ ] Update `.env` with app settings
- [ ] Test database connection

### Phase 2: Entry Points
- [ ] Add `require_once __DIR__ . '/bootstrap.php';` to main pages
- [ ] Remove old `require_once` statements
- [ ] Test that pages still load
- [ ] Check logs for any errors

### Phase 3: Database Access
- [ ] Replace custom database initialization
- [ ] Use `DatabaseManager::getInstance()->getConnection()`
- [ ] Test all database operations
- [ ] Verify no hardcoded credentials remain

### Phase 4: Authentication
- [ ] Refactor login.php to use `AuthManager`
- [ ] Update password change logic
- [ ] Test login/logout flows
- [ ] Verify session handling

### Phase 5: Logging
- [ ] Add logging to critical operations
- [ ] Configure `LOG_LEVEL` in `.env`
- [ ] Monitor `logs/` directory
- [ ] Set up log rotation if needed

### Phase 6: Security
- [ ] Verify CSRF tokens are used
- [ ] Ensure no display_errors in production
- [ ] Review `.env` is in `.gitignore`
- [ ] Test with APP_DEBUG=false

### Phase 7: Testing
- [ ] Run: `composer test`
- [ ] Run: `composer lint`
- [ ] Run: `composer stan`
- [ ] No hardcoded credentials in code

### Phase 8: Cleanup (Last)
- [ ] Remove unused files
- [ ] Archive old code if needed
- [ ] Update documentation
- [ ] Deploy to production

---

## Backward Compatibility

The following functions are still available for backward compatibility:

```php
// Still works
$pdo = getPDO();

// Still works  
$value = getAppSetting($pdo, 'key');
setAppSetting($pdo, 'key', 'value');

// Still works
verify_csrf($token);
```

However, new code should use the class-based approach.

---

## Performance Considerations

### Before
- Multiple database connection attempts per request
- Global $_SESSION array accessed directly
- No logging overhead (but no visibility either)

### After
- Single database connection per request (singleton)
- Centralized session management
- Configurable logging with minimal overhead

Performance is equivalent or better with significantly improved code quality.

---

## Troubleshooting Migration Issues

### "Call to undefined function getPDO()"
- Ensure `bootstrap.php` is included at top of file
- Check `db.php` is in project root

### "Class App\Database\DatabaseManager not found"
- Run `composer dump-autoload`
- Verify `src/Database/DatabaseManager.php` exists
- Check `config/loader.php` is being included

### "Undefined index: admin_logged"
- Use `SessionManager::get()` instead of direct $_SESSION access
- It handles missing keys gracefully

### Environment variables not loading
- Verify `.env` file exists and is readable
- Check variable names match exactly
- Ensure `config/loader.php` is included

---

## Questions?

Refer to:
- `README.md` - Feature overview
- `INSTALL.md` - Installation & setup
- `SECURITY.md` - Security practices
- `CONTRIBUTING.md` - Development guidelines

---

**Migration Completed**: April 14, 2026

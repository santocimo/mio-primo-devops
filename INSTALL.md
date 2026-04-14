# INSTALLATION & SETUP GUIDE

## Quick Start

### Prerequisites
- Docker and Docker Compose installed
- OR PHP 7.4+ and MySQL 5.7+ for local development

### Installation Steps

#### 1. Clone/Extract Project
```bash
cd /path/to/project
```

#### 2. Create Environment File
```bash
cp .env.example .env
```

#### 3. Configure Environment
Edit `.env` with your settings, especially:
```ini
DB_HOST=database-santo          # or your-db-host
DB_PORT=3306
DB_NAME=mio_database
DB_USER=root
DB_PASSWORD=change_me_to_secure_password
APP_ENV=production              # or 'development'
APP_DEBUG=false                 # Change to 'true' only for development
```

#### 4. Start with Docker (Recommended)
```bash
docker-compose up -d
```

The application will be available at:
- **Main App**: http://localhost:8081
- **Database Admin (Adminer)**: http://localhost:8082
- **Container Manager (Portainer)**: https://localhost:9443

#### 5. Access Application
Login with default credentials:
- Username: `admin`
- Password: `admin123`

⚠️ **CHANGE THESE IMMEDIATELY!**

---

## Local Development Setup

### Without Docker

#### 1. Prerequisites
```bash
# Ubuntu/Debian
sudo apt-get install php php-mysql php-pdo composer mysql-server

# macOS with Homebrew
brew install php@8.1 mysql composer
```

#### 2. Create Environment File
```bash
cp .env.example .env
```

#### 3. Install Dependencies
```bash
composer install
```

#### 4. Create Database
```bash
# Start MySQL
sudo systemctl start mysql

# Create database
mysql -u root -p
```

```sql
CREATE DATABASE mio_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON mio_database.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 5. Update .env
```ini
DB_HOST=localhost
DB_USER=app_user
DB_PASSWORD=secure_password_here
DB_NAME=mio_database
APP_DEBUG=true
LOG_LEVEL=debug
```

#### 6. Start PHP Development Server
```bash
php -S localhost:8000
```

Then open http://localhost:8000

---

## Architecture & Project Structure

### New Professional Structure

```
project/
├── bootstrap.php              # Application entry point
├── config/
│   ├── app.php               # Main configuration
│   └── loader.php            # Environment loader
├── src/                       # Application classes (PSR-4)
│   ├── Auth/                 # Authentication & Sessions
│   ├── Database/             # Database management
│   └── Logger/               # Logging system
├── logs/                      # Application logs (daily rotation)
├── migrations/                # Database schema migrations
├── tests/                     # Unit tests (PHPUnit)
├── vendor/                    # Composer dependencies
├── .env                       # Local environment (not in git)
├── .env.example              # Environment template
├── .github/workflows/         # CI/CD pipelines
├── docker-compose.yml        # Docker orchestration
├── Dockerfile                # Container definition
└── README.md                 # This file
```

### Key Classes

#### DatabaseManager (App\Database\DatabaseManager)
```php
use App\Database\DatabaseManager;

$db = DatabaseManager::getInstance();
$pdo = $db->getConnection();
```

#### Logger (App\Logger\Logger)
```php
use App\Logger\Logger;

$logger = Logger::getInstance();
$logger->error('Error message', ['context' => 'data']);
$logger->info('Info message');
```

#### AuthManager (App\Auth\AuthManager)
```php
use App\Auth\AuthManager;

$auth = new AuthManager();
$user = $auth->authenticate('username', 'password');
```

#### SessionManager (App\Auth\SessionManager)
```php
use App\Auth\SessionManager;

SessionManager::set('user_id', 123);
$userId = SessionManager::get('user_id');
```

---

## Configuration Guide

### Environment Variables (.env)

#### Application Settings
- `APP_ENV`: `production` or `development`
- `APP_DEBUG`: `true` or `false` (never true in production!)
- `APP_NAME`: Application display name
- `APP_URL`: Full application URL

#### Database
- `DB_HOST`: Database server hostname
- `DB_PORT`: Database port (default 3306)
- `DB_NAME`: Database name
- `DB_USER`: Database username
- `DB_PASSWORD`: Database password (use strong password!)

#### Logging
- `LOG_LEVEL`: `error`, `warning`, `info`, or `debug`
- `LOG_CHANNEL`: Log destination (`file` supported)

#### Security
- `SESSION_LIFETIME`: Session timeout in seconds (default 3600)
- `CSRF_PROTECTION`: `true` or `false`

---

## Docker Deployment

### Build Image
```bash
docker-compose build
```

### Start Services
```bash
docker-compose up -d
```

### Stop Services
```bash
docker-compose down
```

### View Logs
```bash
docker-compose logs -f web-automatico
docker-compose logs -f database-santo
```

### Access MySQL from Docker
```bash
docker-compose exec database-santo mysql -u root -pPASSWORD
```

### Backup Database
```bash
docker-compose exec database-santo mysqldump -u root -pPASSWORD mio_database > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore Database
```bash
docker-compose exec -T database-santo mysql -u root -pPASSWORD mio_database < backup.sql
```

---

## User Management

### Default Accounts
- **Admin User**: admin / admin123 (change immediately!)
- **Operator User**: op / op123 (change immediately!)

### Changing Passwords

#### Via Application (Recommended)
1. Log in
2. Go to Settings → Change Password
3. Enter new secure password
4. Save

#### Via Database (Emergency)
```bash
mysql -u root -p mio_database
```

```sql
-- Get user ID
SELECT id, username FROM users;

-- Generate new password hash (use PHP)
-- php -r "echo password_hash('newpassword', PASSWORD_BCRYPT);"

UPDATE users SET password_hash = '$2y$10$...' WHERE username = 'admin';
```

### Creating New Users
Via application interface:
1. Admin Panel → Users → Add User
2. Fill in username, password, role, and gym assignment
3. Click Create

Via database (emergency):
```sql
INSERT INTO users (username, password_hash, role, gym_id, created_at) 
VALUES ('user@example.com', '$2y$10$...', 'OPERATOR', 1, NOW());
```

---

## Development Tasks

### Running Tests
```bash
composer test
```

### Code Quality Checks
```bash
# Check coding standards
composer lint

# Fix coding standards automatically
composer lint-fix

# Static analysis
composer stan
```

### Adding New Features

1. **Create class in src/**:
```php
<?php
namespace App\Features;

class MyFeature {
    public function doSomething() {
        // Implementation
    }
}
```

2. **Use in your pages**:
```php
<?php
require_once __DIR__ . '/bootstrap.php';

use App\Features\MyFeature;

$feature = new MyFeature();
```

3. **Add tests in tests/**:
```php
<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Features\MyFeature;

class MyFeatureTest extends TestCase {
    public function testDoSomething() {
        $feature = new MyFeature();
        $result = $feature->doSomething();
        $this->assertNotNull($result);
    }
}
```

---

## Troubleshooting

### Database Connection Failed
```
Error: Database connection failed
```
- Check `.env` credentials match actual database
- Verify database server is running
- Check database user has correct permissions
- For Docker: `docker-compose ps` to see if database container is running

### Permission Denied on logs/
```bash
sudo chown -R www-data:www-data logs/
sudo chmod 755 logs/
```

### Class Not Found
- Ensure class is in correct namespace
- Verify autoload in composer.json includes the path
- Run `composer dump-autoload`

### Session Issues
- Check `logs/` for session errors
- Verify `LOG_LEVEL` in `.env`
- Clear temporary files: `rm -rf cache/*`

### Docker Container Won't Start
```bash
# View error logs
docker-compose logs web-automatico

# Rebuild containers
docker-compose down
docker-compose up -d --build

# Check Docker is running
docker ps
```

---

## Security Checklist

- [ ] Changed default admin password
- [ ] Changed default operator password  
- [ ] Set `APP_DEBUG=false` in production
- [ ] Set `APP_ENV=production`
- [ ] Used strong database password
- [ ] Enabled HTTPS/SSL
- [ ] Configured backups
- [ ] Set up monitoring
- [ ] Reviewed .gitignore includes .env
- [ ] Reviewed SECURITY.md

---

## Support & Documentation

- See `README.md` for feature overview
- See `SECURITY.md` for security details
- See `CONTRIBUTING.md` for development guidelines
- Check `logs/` for application errors
- Review Docker logs: `docker-compose logs`

---

**Last Updated**: April 14, 2026
**Version**: 1.0.0

# BusinessRegistry

A professional, secure business management system for gyms, salons, studios, and service-based businesses. Built with PHP 7.4+, featuring multi-location support, appointment management, and user role-based access control.

## Features

- **Multi-Location Management**: Support for multiple business locations/gyms with independent operations
- **Appointment System**: Schedule and manage customer appointments across services
- **Service Management**: Define and manage business services with pricing and capacity
- **User Management**: Role-based access control (Admin, Manager, Operator)
- **Secure Authentication**: Password hashing with bcrypt, CSRF protection, secure sessions
- **Professional Logging**: Comprehensive error and activity logging
- **Database Migrations**: Proper schema versioning and migration strategy
- **RESTful API**: Organized request handling for appointments, services, users, and more
- **Docker Ready**: Fully containerized with Docker Compose for easy deployment

## Tech Stack

- **Backend**: PHP 7.4+ with PDO for database access
- **Database**: MySQL 5.7+ / MariaDB
- **Containerization**: Docker & Docker Compose
- **Testing**: PHPUnit 10.x
- **Code Quality**: PHPStan, PHP CodeSniffer
- **Web Server**: Nginx

## Quick Start

### Using Docker (Recommended)

```bash
# Copy environment template
cp .env.example .env

# Edit configuration (change database password!)
nano .env

# Start the application
docker-compose up -d

# Access application
open http://localhost:8081
```

**Default Credentials**: Username: `admin`, Password: `admin123`

⚠️ **Change these immediately in a production environment!**
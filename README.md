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

- **Backend**: PHP 8.2+ with PDO for database access
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
open http://localhost:8083
```

Set `ADMIN_INITIAL_PASSWORD` to a unique password of at least 12 characters before the first start. API bearer tokens also require a random `APP_TOKEN_SECRET` of at least 32 characters. Never use development/demo credentials in production.

Generate the signing key with `openssl rand -hex 32`, store it only in `.env`, and keep it unchanged across deployments. Existing mobile sessions issued before signed tokens are enabled must sign in again. Existing administrator passwords are not reset automatically; change any previously used default password before exposing the service publicly.

### One-time payments

The monthly and yearly plans are single purchases with no automatic renewal. The current amounts (`4.99` and `49.99` EUR) are provisional; approve final prices before launch. Configure PayPal sandbox credentials to test PayPal. Hosted Stripe Checkout is offered only after both `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` are configured; card details are handled by Stripe, not this application.

Configure the public frontend URL with `APP_FRONTEND_URL`, then register Stripe's webhook endpoint at `https://<api-host>/api/payments/stripe_webhook.php` for `checkout.session.completed` and `checkout.session.async_payment_succeeded`. Keep provider secrets out of source control and use test credentials until an end-to-end sandbox purchase and webhook have been verified. The `subscription_payments` ledger prevents duplicate webhook/callback delivery from granting the same payment twice.

## Session Checkpoint (Fast Resume)

Use the script below at the end of each work session to save progress without staging runtime files from the home folder.

```bash
chmod +x scripts/checkpoint_session.sh
scripts/checkpoint_session.sh \
	-m "checkpoint: short commit title" \
	-s "what was completed" \
	-n "single next step"
```

What it does:
- Updates `SESSION_HANDOFF.md` with branch, last commit, summary, and next step.
- Stages only project paths (`api`, `app-mobile`, `docker-compose.yml`, docs).
- Commits and pushes the current non-main branch.

Safety rules:
- Refuses to run on `master` or `main`.
- Refuses commits with staged files larger than ~95 MB.
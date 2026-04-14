# Security Policy

## Reporting Security Vulnerabilities

If you discover a security vulnerability in BusinessRegistry, please email your findings to your development team instead of using the public issue tracker.

**Do not publicly disclose the vulnerability until it has been addressed.**

## Security Best Practices

### 1. Environment Variables

Always use `.env` files for sensitive information:
- Database passwords
- API keys
- Session secrets

Never commit `.env` to version control. The `.gitignore` file includes `.env`.

### 2. Database

- Use strong passwords (minimum 16 characters with mixed case, numbers, and symbols)
- Regularly backup your database
- Use prepared statements for all SQL queries (this project uses PDO prepared statements)
- Enable database authentication

### 3. Password Management

- Passwords are hashed using PHP's `password_hash()` with bcrypt
- Never store plaintext passwords
- To change admin password:
  1. Log in to the application
  2. Go to Settings
  3. Select "Change Password"
  4. Use a strong password (minimum 12 characters recommended)

### 4. Session Security

- Session cookies are marked as HttpOnly (cannot be accessed by JavaScript)
- Sessions use SameSite=Lax to prevent CSRF attacks
- Session timeout: 3600 seconds (1 hour) by default, configurable via `.env`

### 5. CSRF Protection

All forms include CSRF tokens. Never disable `CSRF_PROTECTION` in `.env`.

### 6. SSL/TLS

Always use HTTPS in production. Use a reverse proxy (Nginx, Apache) with valid SSL certificates.

Example Nginx config:
```nginx
server {
    listen 443 ssl http2;
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Redirect HTTP to HTTPS
    # ... rest of configuration
}
```

### 7. Access Control

The application uses role-based access control:
- **ADMIN**: Full system access
- **MANAGER**: Manage own location
- **OPERATOR**: View and update appointments/services

Always verify user roles before performing sensitive operations.

### 8. Regular Updates

- Keep PHP updated to the latest stable version
- Update Docker images regularly
- Monitor dependencies with `composer outdated`
- Subscribe to security notifications for project dependencies

### 9. Logging and Monitoring

- Enable logging in production (`LOG_LEVEL=warning` or higher)
- Review logs regularly for suspicious activity
- Set up alerts for error conditions
- Monitor database access patterns

### 10. Deployment Checklist

Before deploying to production:

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Use strong, unique database password
- [ ] Enable HTTPS with valid certificates
- [ ] Review and update user credentials
- [ ] Configure backups
- [ ] Set up monitoring and alerts
- [ ] Test all critical functionality
- [ ] Document deployment details
- [ ] Create disaster recovery plan

## Security Headers

The application automatically sends these security headers:

```
X-Frame-Options: DENY                           # Prevents clickjacking
X-Content-Type-Options: nosniff                 # Prevents MIME type sniffing
Referrer-Policy: no-referrer-when-downgrade    # Controls referrer information
Content-Security-Policy: ...                    # Restricts resource loading
```

## Known Limitations

- The application requires PHP 7.4+
- Database should only be accessible from application servers
- File uploads are not currently implemented (consider adding validation/scanning if implemented)

## Support

For security-related questions:
1. Review this policy
2. Check the main README.md
3. Review application logs
4. Contact your system administrator

## Changelog

### Version 1.0.0 (2026-04-14)
- Initial professional security implementation
- Bcrypt password hashing
- CSRF protection
- Secure session management
- Security headers configuration
- Environment-based configuration

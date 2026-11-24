# Home Nursing Application - Installation Guide

## Complete Setup Instructions

This guide will walk you through setting up the Home Nursing application from scratch.

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.1 or higher**
  - Download from: https://www.php.net/downloads
  - Required extensions: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD

- **Composer** (PHP Dependency Manager)
  - Download from: https://getcomposer.org/download/

- **MySQL 5.7 or higher** (or MariaDB 10.3+)
  - Download from: https://dev.mysql.com/downloads/mysql/

- **Node.js & NPM** (for frontend assets)
  - Download from: https://nodejs.org/ (LTS version recommended)

- **Git** (optional, for version control)
  - Download from: https://git-scm.com/downloads

## Step-by-Step Installation

### 1. Install PHP Dependencies

Open a terminal/command prompt in the project directory and run:

```bash
composer install
```

This will install all required PHP packages including:
- Laravel Framework
- Razorpay SDK
- DomPDF for PDF generation
- Maatwebsite Excel for report exports

### 2. Install Node.js Dependencies

```bash
npm install
```

### 3. Environment Configuration

Copy the example environment file:

```bash
# On Windows (PowerShell)
copy .env.example .env

# On Mac/Linux
cp .env.example .env
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Configure Database

Open the `.env` file in a text editor and update the database settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homenursing
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

### 6. Create Database

Create a new MySQL database:

```sql
CREATE DATABASE homenursing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

You can do this via:
- **phpMyAdmin**: Create new database
- **MySQL Workbench**: Execute the SQL command
- **Command Line**:
  ```bash
  mysql -u root -p
  CREATE DATABASE homenursing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  exit;
  ```

### 7. Run Database Migrations

```bash
php artisan migrate
```

This creates all necessary tables in your database.

### 8. Seed Default Data

```bash
php artisan db:seed
```

This will populate:
- Default admin users
- Company settings
- Mandatory employee fields for nursing
- Sample services
- Sample blog posts

### 9. Create Storage Symlink

```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public` for file uploads.

### 10. Configure Razorpay (Payment Gateway)

1. Sign up at https://razorpay.com
2. Get your API keys from the dashboard
3. Update `.env` file:

```env
RAZORPAY_KEY=your_razorpay_key_id
RAZORPAY_SECRET=your_razorpay_secret
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret
```

For testing, use Razorpay test mode keys.

### 11. Compile Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

### 12. Set File Permissions (Mac/Linux only)

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

On Windows, ensure the web server has write permissions to these directories.

### 13. Start Development Server

```bash
php artisan serve
```

The application will be available at: `http://localhost:8000`

## Login Credentials

The seeder creates two accounts:

- **Super Admin**: admin@homenursing.com
- **Regular Admin**: user@homenursing.com

Their password is `SEED_ADMIN_PASSWORD` from `.env` (at least 8 characters).
If that is blank, a random password is generated and printed once in the
`db:seed` output, so copy it then. The seeder refuses to run in production and
never overwrites an existing account, so re-running it is safe.

The regular admin (and any other non-super-admin) also needs a one-time code
emailed to the owner's approver address before the login completes. Set that
address under **Admin → Settings → Login OTP**, and configure real SMTP
(`MAIL_MAILER=log` delivers nothing). See OPERATIONS.md.

## Accessing the Application

- **Public Website**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/admin

## Post-Installation Configuration

### 1. Update Company Settings

1. Login to admin panel
2. Go to Settings
3. Update:
   - Company name
   - Company description
   - Logo (upload)
   - Contact information
   - Email settings

### 2. Configure Employee Fields

1. Go to Admin > Employee Fields
2. Review mandatory nursing fields (cannot be deleted)
3. Add any additional custom fields needed

### 3. Add Services

1. Go to Admin > Services
2. Add/edit services your company offers
3. Set order and active status

### 4. Configure Notifications

1. Go to Admin > Settings
2. Set "Contract Alert Days" (default: 7 days before expiry)

### 5. Optional: Enable Employee Payment

If you want employees to pay for activation:

1. Update `.env`:
```env
EMPLOYEE_PAYMENT_ENABLED=true
EMPLOYEE_PAYMENT_AMOUNT=1000
EMPLOYEE_PAYMENT_VALIDITY_MONTHS=12
```

2. Restart the server

## Scheduled Tasks (Cron Jobs)

For production, set up these cron jobs:

```bash
# Update contract statuses daily
0 0 * * * cd /path/to/project && php artisan contracts:update-status

# Generate notifications daily
0 1 * * * cd /path/to/project && php artisan notifications:generate
```

On Windows, use Task Scheduler instead of cron.

## Troubleshooting

### Issue: "Class not found" errors
**Solution**: Run `composer dump-autoload`

### Issue: Database connection errors
**Solution**: 
- Verify MySQL is running
- Check database credentials in `.env`
- Ensure database exists

### Issue: Storage/upload errors
**Solution**: 
- Run `php artisan storage:link`
- Check file permissions on storage directory

### Issue: Razorpay payment not working
**Solution**:
- Verify API keys are correct
- Check if using test/live mode correctly
- Review Razorpay dashboard logs

### Issue: PDF generation fails
**Solution**:
- Ensure GD extension is enabled in PHP
- Check storage permissions

## Production Deployment

For production deployment:

1. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
2. Run `composer install --optimize-autoloader --no-dev`
3. Run `npm run build`
4. Run `php artisan config:cache`
5. Run `php artisan route:cache`
6. Run `php artisan view:cache`
7. Set up proper web server (Apache/Nginx)
8. Configure SSL certificate
9. Set up automated backups
10. Configure email settings for notifications

## Support

For issues or questions:
- Check Laravel documentation: https://laravel.com/docs
- Check Razorpay documentation: https://razorpay.com/docs
- Review application logs in `storage/logs/`

## Security Checklist

- [ ] Change default admin passwords
- [ ] Update APP_KEY in production
- [ ] Set APP_DEBUG=false in production
- [ ] Configure proper file permissions
- [ ] Set up SSL certificate
- [ ] Configure firewall rules
- [ ] Regular database backups
- [ ] Keep Laravel and dependencies updated
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging


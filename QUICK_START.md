# Quick Start Guide

Get the Home Nursing application up and running in minutes!

## ✅ Current Status

**Good News!** The application is already set up and running! 🎉

- ✅ All dependencies installed
- ✅ Application key generated
- ✅ Development server running at `http://localhost:8000`

## What's Working Now

Visit `http://localhost:8000/welcome` to see the application status page.

---

## Next Steps to Complete Setup

### 1. Configure Database

Open `.env` file and update these lines:

```env
DB_DATABASE=homenursing
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

---

### 2. Create Database

**Option A - Using MySQL Command Line:**
```bash
mysql -u root -p
CREATE DATABASE homenursing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

**Option B - Using phpMyAdmin:**
- Open phpMyAdmin
- Click "New" to create database
- Name it `homenursing`
- Set collation to `utf8mb4_unicode_ci`

---

### 3. Run Migrations

```bash
php artisan migrate
```

**Expected Output**: You should see 14 migrations being executed.

**If you get errors**: Check your database credentials in `.env`

---

### 4. Configure Database

Open `.env` file and update these lines:

```env
DB_DATABASE=homenursing
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

---

### 5. Create Database

**Option A - Using MySQL Command Line:**
```bash
mysql -u root -p
CREATE DATABASE homenursing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

**Option B - Using phpMyAdmin:**
- Open phpMyAdmin
- Click "New" to create database
- Name it `homenursing`
- Set collation to `utf8mb4_unicode_ci`

---

### 6. Run Migrations

```bash
php artisan migrate
```

**Expected Output**: You should see 14 migrations being executed.

**If you get errors**: Check your database credentials in `.env`

---

### 7. Seed Default Data

```bash
php artisan db:seed
```

**Expected Output**: Seeders will create:
- 2 admin users
- 18 default settings
- 12 employee fields (including 5 mandatory nursing documents)
- 6 sample services
- 3 sample blog posts

---

### 8. Create Storage Link

```bash
php artisan storage:link
```

**Expected Output**: `The [public/storage] link has been connected to [storage/app/public].`

---

### 9. Start Development Server

```bash
php artisan serve
```

**Expected Output**: `Laravel development server started: http://127.0.0.1:8000`

---

## Access the Application

### Public Website
Open your browser and visit: **http://localhost:8000**

You should see the home page with:
- Navigation menu
- Company information
- Featured services
- Recent blog posts

### Admin Panel
Visit: **http://localhost:8000/admin**

**Login Credentials:**
- Email: `admin@homenursing.com`
- Password: `password`

**⚠️ IMPORTANT**: Change this password immediately after first login!

---

## Quick Test Checklist

After installation, verify these work:

### Public Website
- [ ] Home page loads
- [ ] About page loads
- [ ] Services page shows services
- [ ] Blogs page shows blog posts
- [ ] Contact form can be submitted
- [ ] Join form can be submitted

### Admin Panel
- [ ] Can login with default credentials
- [ ] Dashboard shows statistics
- [ ] Can view employees list
- [ ] Can view patients list
- [ ] Can view contracts list
- [ ] Can view settings page

---

## Common Issues & Solutions

### Issue: "Class not found" errors
**Solution:**
```bash
composer dump-autoload
```

### Issue: Database connection refused
**Solution:**
- Make sure MySQL is running
- Check database credentials in `.env`
- Verify database exists

### Issue: Storage permission errors
**Solution (Mac/Linux):**
```bash
chmod -R 775 storage bootstrap/cache
```

**Solution (Windows):**
- Right-click `storage` folder → Properties → Security
- Give full control to your user

### Issue: 404 on admin routes
**Solution:**
```bash
php artisan route:clear
php artisan config:clear
```

---

## Next Steps

### 1. Configure Company Settings
1. Login to admin panel
2. Go to Settings
3. Update:
   - Company name
   - Company email and phone
   - Company address
   - Upload company logo

### 2. Review Employee Fields
1. Go to Admin → Employee Fields
2. Review the 5 mandatory nursing document fields
3. Add any additional custom fields you need

### 3. Add Your Services
1. Go to Admin → Services
2. Edit or add services your company offers
3. Set display order

### 4. Optional: Configure Razorpay
If you want to accept online payments:
1. Sign up at https://razorpay.com
2. Get test API keys
3. Update `.env`:
```env
RAZORPAY_KEY=rzp_test_xxxxx
RAZORPAY_SECRET=xxxxxxxxxxxxx
```

---

## Development Workflow

### Making Changes

1. **Edit code** in your favorite editor
2. **Refresh browser** to see changes (Laravel has hot reload for views)
3. **Clear cache** if needed:
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Adding New Features

1. Create migration: `php artisan make:migration create_xxx_table`
2. Create model: `php artisan make:model ModelName`
3. Create controller: `php artisan make:controller ControllerName`
4. Add routes in `routes/web.php` or `routes/admin.php`
5. Create views in `resources/views/`

---

## Getting Help

- **Laravel Docs**: https://laravel.com/docs
- **Check Logs**: `storage/logs/laravel.log`
- **Debug Mode**: Set `APP_DEBUG=true` in `.env` (development only!)

---

## You're All Set! 🎉

Your Home Nursing application is now running. Start by:
1. Logging into the admin panel
2. Updating company settings
3. Adding your first patient
4. Creating your first contract

For detailed feature documentation, see **README.md** and **PROJECT_SUMMARY.md**.


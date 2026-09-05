# Home Nursing - Healthcare Staffing Application

A comprehensive PHP Laravel application for managing home nursing and healthcare staffing business with a public website and secure admin panel.

## Features

### Public Website
- **Home Page**: Dynamic content with company branding and services
- **About Us**: Editable CMS content
- **Contact Us**: Contact form with query management
- **Services**: Dynamic service listings
- **Join With Us**: Employee recruitment with modal form
- **Blogs**: Blog listing and detail pages with pagination
- **Responsive Design**: Mobile-friendly interface

### Admin Panel
- **Dashboard**: Metrics, charts, and key summaries
- **Settings Management**: Company info, notifications, payment configurations
- **Employee Management**: 
  - Configurable employee fields
  - Mandatory nursing documents (license, ID, medical clearance, etc.)
  - Dynamic field system
  - Document uploads
  - Status management
- **Patient Management**: Complete patient records with contract creation
- **Contract Management**:
  - PDF generation
  - Status tracking (Not Assigned, Active, Closing Soon, Expired, Closed)
  - Contract closure with reasons
  - Payment integration
- **Nurse Assignment**: Assign nurses to patients with emergency replacement support
- **Employee Payouts**: Salary management with multiple frequencies
- **Reports**: Patient, employee, assignment, and revenue reports with export
- **CMS Management**: Services, blogs, contact queries, join requests
- **Notifications**: In-app alerts for contract expiry
- **Razorpay Integration**: Payment processing for contracts and employee activation

## Tech Stack

- **Framework**: Laravel 10.x
- **Database**: MySQL
- **PHP**: 8.1+
- **Frontend**: Blade Templates, Bootstrap 5
- **PDF Generation**: DomPDF
- **Excel Export**: Maatwebsite Excel
- **Payment Gateway**: Razorpay

## Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 5.7 or higher
- Node.js & NPM (for asset compilation)

### Setup Instructions

1. **Clone the repository**
```bash
git clone <repository-url>
cd homenursing
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install NPM dependencies**
```bash
npm install
```

4. **Environment Configuration**
```bash
cp .env.example .env
```

5. **Configure your `.env` file**
```env
APP_NAME="Home Nursing"
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homenursing
DB_USERNAME=root
DB_PASSWORD=your_password

# Razorpay Configuration
RAZORPAY_KEY=your_razorpay_key
RAZORPAY_SECRET=your_razorpay_secret
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret

# Contract Alert Settings
CONTRACT_ALERT_DAYS=7

# Employee Payment Settings
EMPLOYEE_PAYMENT_ENABLED=false
EMPLOYEE_PAYMENT_AMOUNT=1000
EMPLOYEE_PAYMENT_VALIDITY_MONTHS=12
```

6. **Generate application key**
```bash
php artisan key:generate
```

7. **Create database**
Create a MySQL database named `homenursing` (or your chosen name)

8. **Run migrations**
```bash
php artisan migrate
```

9. **Seed the database**
```bash
php artisan db:seed
```

10. **Create storage symlink**
```bash
php artisan storage:link
```

11. **Compile assets**
```bash
npm run dev
# or for production
npm run build
```

12. **Start the development server**
```bash
php artisan serve
```

Visit `http://localhost:8000` for the public website
Visit `http://localhost:8000/admin` for the admin panel

## Default Admin Credentials

- **Email**: admin@homenursing.com
- **Password**: password

**Important**: Change these credentials immediately after first login!

## Database Structure

### Core Tables
- `users` - Admin users
- `settings` - Application settings (key-value store)
- `employee_fields` - Configurable employee field definitions
- `employees` - Employee records with dynamic fields
- `employee_documents` - Employee document uploads
- `patients` - Patient records
- `services` - Service offerings
- `contracts` - Patient contracts
- `assignments` - Nurse-to-patient assignments
- `payments` - Payment records (Razorpay & Cash)
- `employee_payouts` - Employee salary/payout records
- `blogs` - Blog posts
- `contact_queries` - Contact form submissions
- `join_requests` - Employee recruitment submissions
- `notifications` - In-app notifications

## Key Features Explained

### 1. Configurable Employee Fields
Admin can define custom fields for employee data collection. The system includes:
- **Mandatory Fields**: Pre-built nursing-specific fields that cannot be removed:
  - Nursing License
  - Government ID Proof
  - Medical Clearance Certificate
  - Background Check Certificate
  - Vaccination Record
- **Custom Fields**: Admin can add/edit/remove additional fields
- **Field Types**: text, textarea, email, phone, number, date, select, file
- **Document Fields**: Support for file uploads with format validation

### 2. Contract Management
- Automatic contract number generation
- PDF contract generation with company and patient details
- Status tracking with color-coded indicators:
  - **Not Assigned** (Yellow): No nurse assigned yet
  - **Active** (Green): Contract active with assigned nurse
  - **Closing Soon** (Orange): Approaching expiry date
  - **Expired** (Red): Past end date
  - **Closed** (Gray): Manually closed by admin
- Automatic status updates based on dates and assignments

### 3. Notification System
- Bell icon in admin header showing unread notifications
- Automatic notifications for:
  - Contracts expiring soon (configurable days)
  - New contact queries
  - New join requests
- Click notification to navigate to relevant page

### 4. Razorpay Integration
- Secure payment processing for:
  - Patient contract payments
  - Optional employee activation payments
- Webhook support for payment confirmation
- Order and payment tracking

### 5. Reports & Export
- Filter by date ranges
- Export to CSV/Excel
- Report types:
  - Patient details
  - Employee details
  - Patient-Employee assignments
  - Revenue details (by payment method, date, patient)

### 6. Employee Payout System
- Define payout frequency: daily, weekly, monthly, yearly
- Track payment status
- Generate payout reports
- Link payouts to specific contracts

## File Structure

```
homenursing/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Admin panel controllers
│   │   │   └── Web/            # Public website controllers
│   │   └── Middleware/         # Custom middleware
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic services
│   │   ├── PaymentService.php  # Razorpay integration
│   │   ├── ContractService.php # Contract management
│   │   └── ReportService.php   # Report generation
│   └── Helpers/
│       └── helpers.php         # Helper functions
├── database/
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── resources/
│   ├── views/
│   │   ├── admin/              # Admin panel views
│   │   ├── web/                # Public website views
│   │   └── layouts/            # Layout templates
│   └── css/                    # Stylesheets
├── routes/
│   ├── web.php                 # Web routes
│   └── admin.php               # Admin routes
├── public/
│   ├── uploads/                # File uploads
│   │   ├── documents/          # Employee documents
│   │   ├── contracts/          # Contract PDFs
│   │   └── images/             # Images
│   └── assets/                 # Public assets
└── storage/
    └── app/
        └── public/             # Publicly accessible storage
```

## Configuration

### Settings Management
All settings are stored in the `settings` table and can be managed via admin panel:
- Company information
- Website content
- Notification preferences
- Payment configurations
- CMS content for public pages

### Employee Payment (Optional Feature)
Enable in settings to require employees to pay for account activation:
1. Set `EMPLOYEE_PAYMENT_ENABLED=true` in `.env`
2. Configure amount and validity period
3. Employees must complete payment via Razorpay
4. System tracks payment validity and auto-deactivates expired accounts

## Security Features

- CSRF protection on all forms
- Password hashing with bcrypt
- SQL injection protection via Eloquent ORM
- XSS protection via Blade templating
- File upload validation
- Admin authentication middleware
- Soft deletes for data recovery

## API Integration

### Razorpay Setup
1. Sign up at https://razorpay.com
2. Get API keys from dashboard
3. Add keys to `.env` file
4. Configure webhook URL: `https://yourdomain.com/api/razorpay/webhook`
5. Set webhook secret in `.env`

## Maintenance

### Update Contract Statuses
Run this command daily via cron:
```bash
php artisan contracts:update-status
```

### Generate Notifications
Run this command daily via cron:
```bash
php artisan notifications:generate
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

## Customization

### Adding New Employee Fields
1. Go to Admin > Settings > Employee Fields
2. Click "Add New Field"
3. Configure field properties
4. Save (field will appear in employee forms)

### Customizing Email Templates
Edit files in `resources/views/emails/`

### Customizing PDF Templates
Edit `resources/views/pdf/contract.blade.php`

## Troubleshooting

### Storage Permission Issues
```bash
chmod -R 775 storage bootstrap/cache
```

### Database Connection Issues
- Verify MySQL is running
- Check `.env` database credentials
- Ensure database exists

### Razorpay Payment Issues
- Verify API keys are correct
- Check webhook configuration
- Review Razorpay dashboard logs

## Support & Documentation

For detailed documentation on specific features, refer to:
- Laravel Documentation: https://laravel.com/docs
- Razorpay Documentation: https://razorpay.com/docs
- DomPDF Documentation: https://github.com/barryvdh/laravel-dompdf

## License

This project is proprietary software for Home Nursing business operations.

## Credits

Developed using Laravel Framework and modern PHP best practices.


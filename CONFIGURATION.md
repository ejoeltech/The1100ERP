# Configuration Instructions

This ERP system contains placeholder values for company information. **You must update these before deploying to production.**

## Required Changes

### 1. Company Information (`config.php`)

Update the following constants:

```php
define('COMPANY_NAME', 'Your Company Name');        // Your actual company name
define('COMPANY_ADDRESS', 'Your Company Address, City, State/Province, Country');  // Your full business address
define('COMPANY_PHONE', '+1234567890');             // Your phone number
define('COMPANY_EMAIL', 'contact@yourcompany.com'); // Your business email
define('COMPANY_WEBSITE', 'www.yourcompany.com');   // Your website URL
```

### 2. Database Configuration (`config.php`)

Update database connection settings:

```php
define('DB_HOST', 'localhost');     // Your database host
define('DB_NAME', '1100erp'); // Your database name
define('DB_USER', 'root');          // Your database username  
define('DB_PASS', '');              // Your database password (change for production!)
```

### 3. Bank Accounts

After installation:
1. Go to **Settings** → **Bank Accounts** tab
2. Delete placeholder accounts
3. Add your real bank account details
4. Select at least 3 to display on documents

### 4. Company Logo

Upload your company logo:
1. Go to **Settings** → **Company Info** tab
2. Upload your logo (PNG/JPG, max 3MB)
3. Logo will automatically be used on documents and as favicon

### 5. Email Configuration (Optional)

For production email sending:
1. Go to **Settings** → **Email Settings** tab
2. Configure SMTP settings for reliable email delivery
3. Recommended: Use Gmail, SendGrid, or Mailgun

### 6. Security (CRITICAL for Production)

- **Change database password**
- **Set secure session keys** (if using)
- **Enable HTTPS** - NO production deployment without SSL/TLS
- **Review user permissions** and update default admin password
- **Back up database** regularly

## Default Login Credentials

⚠️ **CHANGE THESE IMMEDIATELY AFTER FIRST LOGIN**

- Username: `admin`
- Password: `admin123`

## Quick Start

1. Import database: `database/erp_schema.sql`
2. Run migrations in `database/` folder
3. Update `config.php` with your details
4. Access via web browser
5. Login and change admin password
6. Configure settings

## Support

For issues or questions, refer to the documentation or contact your system administrator.

---

**Remember**: All placeholder values MUST be updated before production use!

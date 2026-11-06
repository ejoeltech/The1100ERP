# Configuration Instructions

This ERP system contains placeholder values for company information. **You must update these before deploying to production.**

## Required Changes

### 1. Company Information (Settings)

Sign in as an administrator and open **Settings → Company**. Set the company
name, address, phone, email, website, logo, and document footer there. These
values are stored in the database, not in `config.php`.

### 2. Database Configuration (`.env` / `APP_SECRETS_FILE`)

Put database credentials in `.env` (local) or in the file referenced by
`APP_SECRETS_FILE` (production, outside the web root). Never edit secrets into
`config.php`; it is only the secret-free loader.

```env
DB_HOST=localhost
DB_NAME=1100erp
DB_USER=your_db_user
DB_PASS=your_db_password
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

## First Login

There are no default credentials. The setup wizard creates the first
administrator account with the password you choose. If you install manually,
create the first admin through the wizard or the user-management flow; never
ship a known default password.

## Quick Start

1. Open the setup wizard at `maintenance/setup/` on your server.
2. Enter the database and first-admin details.
3. Set `APP_SECRETS_FILE` (production) or `.env` with the database credentials.
4. Run **Settings → Maintenance → System Updates → Run Schema Patcher**.
5. Log in and configure company details, banks, and AI settings.

## Support

For issues or questions, refer to the documentation or contact your system administrator.

---

**Remember**: All placeholder values MUST be updated before production use!

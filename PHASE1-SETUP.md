# Bluedots Quote Management - Phase 1 Setup

## mPDF Installation
### Option 1: Using Composer (Recommended)

If you have Composer installed:

```bash
cd c:\xampp\htdocs\1100erp
mysql -u root -p bluedots_quotes < database/schema.sql
```

### Step 2: Set Up Dependencies

The system uses mPDF for PDF generation. Follow these steps:

1. Download mPDF from: https://github.com/mpdf/mpdf/releases
2. Extract the contents to `c:\xampp\htdocs\1100erp\vendor\mpdf\`
3. The autoload file will be at: `vendor/autoload.php`

Note: PDF export will not work until mPDF is installed.

## Run Database Migration

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select the `bluedots_quotes` database
3. Click the "SQL" tab
4. Open `database/phase1-migration.sql`  
5. Copy ALL contents and paste into the SQL window
6. Click "Go" to execute

This will:
- Create the `customers` table
- Add `deleted_at` column to documents
- Migrate existing customers
- Add sample customer data

## Testing Phase 1 Features

After setup, test each feature:

1. **Edit Quote**: Go to view-quotes.php and click "Edit" on a draft quote
2. **Delete Quote**: Click "Delete" with confirmation
3. **Duplicate Quote**: Open any quote detail and click "Duplicate"
4. **PDF Export**: Click "Download PDF" (requires mPDF installed)
5. **Customer Dropdown**: Create a new quote and test customer selection

## Troubleshooting

### PDF Export Shows Error
- Verify mPDF is installed in `/vendor/mpdf/`
- Check PHP error log for details
- Ensure `exports/` folder exists and is writable

### Can't Edit Finalized Quotes
- This is intentional - only draft quotes can be edited
- Duplicate the quote if you need to modify it

### Deleted Quotes Still Showing
- Verify `deleted_at` column exists in documents table
- Check that view-quotes.php has `WHERE deleted_at IS NULL` in query

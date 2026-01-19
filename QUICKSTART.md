# QUICK START GUIDE
# Bluedots Technologies Quote Management System - Phase 0

## STEP 1: Create Database

1. Open: http://localhost/phpmyadmin
2. Click "New" (left sidebar)
3. Database name: bluedots_quotes
4. Collation: utf8mb4_unicode_ci
5. Click "Create"

## STEP 2: Import Schema

1. Click on "bluedots_quotes" database (left sidebar)
2. Click "SQL" tab (top menu)
3. Open file: database/schema.sql
4. Copy ALL contents
5. Paste into SQL window
6. Click "Go"
7. ✅ Should see: "3 rows inserted"

## STEP 3: Access Application

1. Open browser
2. Go to: http://localhost/1100erp/
3. You should see Bluedots landing page

## STEP 4: Test the System

### View Sample Quote
- Go to: http://localhost/1100erp/pages/view-quotes.php
- Click "View" on QT-0001
- Click "Print Quote" to test

### Create New Quote
- Go to: http://localhost/1100erp/pages/create-quote.php
- Fill in form
- Add line items
- Watch VAT calculate automatically
- Click "Save & Finalize"

## ✅ SUCCESS CRITERIA

Your setup is successful if:
- ✅ Database exists with 2 tables (documents, line_items)
- ✅ Sample quote QT-0001 displays correctly
- ✅ Can create new quotes
- ✅ VAT calculates correctly (7.5%)
- ✅ Print functionality works

## 🚨 TROUBLESHOOTING

### Can't connect to database?
- Check XAMPP: Apache and MySQL must be running
- Verify database name: bluedots_quotes
- Check config.php credentials

### VAT not calculating?
- Check browser console (F12) for errors
- Clear browser cache
- Verify quote-form.js is loading

### Sample quote not showing?
- Verify schema.sql imported completely
- Check if INSERT statements ran
- Look for 3 rows inserted message

## 📚 FULL DOCUMENTATION

See README.md for:
- Complete installation guide
- VAT calculation examples
- Production deployment steps
- Troubleshooting guide
- Feature documentation

---

## 🎯 WHAT'S NEXT?

After setup:
1. Test with real scenarios
2. Create training quotes
3. Deploy to production (see README.md)
4. Train sales team

---

**Support:** bluedotsng@gmail.com | 07031635955

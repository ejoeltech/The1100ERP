# 1100-ERP - Small Business Management System

A simple, robust ERP system built with PHP, MySQL, and Tailwind CSS. Designed for small businesses to manage quotes, invoices, receipts, and payments.

## Features
- **Quote Management**: Create, edit, and track quotes.
- **Readymade Quotes**: Templates for quick quote generation.
- **Invoicing**: Convert quotes to invoices with one click.
- **Receipts**: Track payments and generate receipts.
- **Customer Management**: CRM-lite features for tracking client details.
- **Audit Logging**: Track all system activities.
- **Dynamic PDF Generation**: High-quality PDF export for all documents.

## Installation

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/yourusername/1100-erp.git
    cd 1100-erp
    ```

2.  **Database Setup**:
    *   Create a database named `1100erp` (or your preferred name).
    *   Import the schema from `database/install-schema.sql`.

3.  **Configuration**:
    *   Rename `config.sample.php` to `config.php`.
    *   Edit `config.php` with your database credentials and TinyMCE API key.
    
    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', '1100erp');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    ```

4.  **Run the Server**:
    *   If using XAMPP/WAMP, place the folder in `htdocs` or `www`.
    *   Or use the built-in PHP server: `php -S localhost:8000`

5.  **First Login**:
    *   The system uses the database users table. Ensure you insert your admin user directly into the database or use a registration script if available.

## Credits
*   Built with [Tailwind CSS](https://tailwindcss.com/)
*   [Tom Select](https://tom-select.js.org/) for dropdowns
*   [TinyMCE](https://www.tiny.cloud/) for rich text editing

## License
MIT License

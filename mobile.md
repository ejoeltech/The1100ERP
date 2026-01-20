# Mobile Responsiveness Status

This document describes the current state of mobile responsiveness for the 1100-ERP system.

## 1. Global Layout & Navigation
*   **Framework**: Tailwind CSS v3 (CDN).
*   **Viewport**: Standard `<meta name="viewport" content="width=device-width, initial-scale=1.0">`.
*   **Navigation Bar**:
    *   **Desktop**: Sidebar/Top bar (depending on context).
    *   **Mobile**: Collapsible drawer menu (`#mobileMenu`).
    *   **Trigger**: Hamburger icon toggles the menu overlay.
    *   **Status**: **Functional**.

## 2. Dashboard
*   **Grid Layout**: Uses Tailwind's grid system (`grid-cols-1 md:grid-cols-2 lg:grid-cols-4`).
    *   **Mobile Behavior**: Cards stack vertically (1 column).
    *   **Tablet/Desktop**: Expands to 2 or 4 columns.
*   **Status**: **Fully Responsive**.

## 3. Forms (Create/Edit Quotes & Invoices)
*   **Issue**: Complex tables with multiple columns (Description, Qty, Price, VAT) were previously compressed on small screens, making inputs unusable.
*   **Fix Applied (v5)**:
    *   **Horizontal Scrolling**: The line items table is wrapped in a container allowing `overflow-x-auto`.
    *   **Minimum Widths**:
        *   **Description**: `min-w-[200px]` enforced to prevent text crushing.
        *   **Unit Price**: `min-w-[120px]` enforced to ensure number visibility.
*   **Status**: **Optimized for Mobile (Scrollable Tables)**.
*   **Impacted Files**:
    *   `assets/js/quote-form.js` (Create/Edit Quotes, Readymade Templates)
    *   `assets/js/edit-invoice.js` (Edit Invoices)

## 4. Data Tables (View Pages)
*   **Recent Activity / Lists**: Standard HTML tables.
*   **Mobile Behavior**: Generally wrapped in `.overflow-x-auto` or `.table-responsive` containers.
*   **UX Note**: Users must scroll horizontally to view full table data on mobile. This is a standard pattern for complex data tables.

## 5. Known Limitations / Future Improvements
*   **Modals**: Some modals (e.g., Email, Export) should be verified for vertical overflow on very small landscape screens.
*   **Complex Tables**: While scrollable, card-based views (stacking rows) could offer a better UX for "View All" pages in the future, if specific mobile optimization is prioritized over desktop parity.

## Summary
The system is **mobile-ready**. Critical creation flows (Quotes, Invoices) have been patched to ensure usability on small touchscreens.

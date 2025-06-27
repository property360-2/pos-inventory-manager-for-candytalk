# POS Inventory Manager for Candytalk

A web-based Point of Sale (POS) and Inventory Management System designed for Candytalk. This application helps manage products, sales, users, and generate insightful reports.

## Features
- **User Authentication**: Secure login/logout for users.
- **Dashboard**: Overview of sales, inventory, and key metrics.
- **Inventory Management**: Add, update, and track products in stock.
- **Sales Management**: Record and manage sales transactions.
- **User Management**: Manage user accounts and permissions.
- **Reports & Analytics**:
  - Interactive charts for sales, revenue, top products, and inventory levels (Chart.js)
  - Filter reports by date, product, and user
  - Download sales and inventory reports as CSV

## Technologies Used
- PHP (Backend)
- MySQL (Database)
- Bootstrap (Frontend UI)
- Chart.js (Charts & Analytics)
- JavaScript (Interactivity)

## File Structure
- `auth/` - Login and logout scripts
- `config/` - Database configuration
- `dashboard.php` - Main dashboard
- `modules/` - Inventory, sales, and user management modules
- `reports/` - Reporting dashboard and report generation
- `includes/` - Header and footer for consistent layout
- `uploads/` - For file uploads (e.g., product images)

## Setup Instructions
1. **Clone the repository** to your web server directory (e.g., XAMPP's `htdocs`).
2. **Import the database**:
   - Use `database.sql` to create the required tables in your MySQL database.
3. **Configure the database connection**:
   - Edit `config/database.php` with your MySQL credentials.
4. **Access the application**:
   - Open your browser and navigate to the project directory (e.g., `http://localhost/pos-inventory-manager-for-candytalk/`).

## Usage
- Log in with your user credentials.
- Use the dashboard and modules to manage inventory, sales, and users.
- Visit the Reports section to view analytics and download CSV reports.

## Notes
- Report generation logic is handled in `reports/generate.php`.
- Chart data is fetched dynamically from `reports/summary_data.php`.

---

For questions or support, please contact the developer or open an issue.

---

**This website is created by Jun Alvior. If you ever want to have a copy of this repository, ask me for permission—I will give it to you for free.** 
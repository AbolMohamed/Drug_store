# Drug_store
A web-based Drugstore Stock Management System built with PHP and MySQL to track pharmaceutical inventory, monitor expiration dates, manage suppliers, and streamline sales.

# Drugstore Stock Management System

A robust, web-based **Drugstore Stock Management System** built using **PHP** and **MySQL**. This application is designed for pharmacies and drugstores to efficiently track medication inventory, monitor expiration dates, manage supplier relationships, and process daily sales transactions.

## 🚀 Key Features

### 📦 Inventory & Stock Control
* **Real-time Tracking:** Monitor current stock levels of medicines, syrups, and medical equipment.
* **Batch & Expiry Management:** Track unique batch numbers and receive alerts for expiring or expired items.
* **Low-Stock Alerts:** Automatically flag items falling below a set minimum threshold to prevent shortages.

### 💼 Sales & Billing
* **Point of Sale (POS):** Quick billing interface to generate invoices for retail customers.
* **Prescription Logging:** Keep records of prescription-based sales for compliance.
* **Tax & Discount Calculation:** Automatically apply customizable taxes (e.g., VAT) or customer discounts.

### 🤝 Supplier & Purchase Management
* **Supplier Directory:** Maintain contact details, order histories, and outstanding balances for vendors.
* **Purchase Orders:** Easily log new inventory arrivals and map them to specific suppliers.

### 📊 Reports & Analytics
* **Sales Reports:** View daily, weekly, or monthly revenue data.
* **Inventory Valuation:** Generate reports showing the total financial value of stock currently on hand.

---

## 🛠️ Tech Stack

* **Backend:** PHP (OOP or Procedural)
* **Database:** MySQL
* **Frontend:** HTML5, CSS3, Bootstrap, JavaScript / jQuery
* **Authentication:** Secure Session-based Login with role-based access control (Admin / Pharmacist / Cashier)

---

## 💻 Installation & Setup

1. **Clone the Repository:**
   ```bash
   git clone https://github.com
   ```
2. **Move to Server Directory:** Place the project folder in your local server directory (e.g., `htdocs` for XAMPP or `www` for WAMP).
3. **Database Setup:**
   * Open **phpMyAdmin**.
   * Create a new database named `drugstore_db`.
   * Import the `database.sql` file provided in the root folder.
4. **Configure Database Connection:**
   * Open `config.php` (or your database connection file).
   * Update the database credentials (`hostname`, `username`, `password`, `database_name`) to match your local setup.
5. **Run the Application:** Open your browser and navigate to `http://localhost/drugstore-stock-management`.

---

## 🔒 Security Measures Implemented
* **Password Hashing:** Passwords securely stored using PHP's `password_hash()`.
* **SQL Injection Prevention:** Implemented prepared statements to secure database queries.
* **Session Validation:** Unauthorized users are restricted from accessing dashboard routes.

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
# Drug

# RevenueQR Platform

## �� Overview
This is a lightweight, role-based SaaS application for vending machine businesses. It enables:
- Admin dashboard access
- Vendor management for QR voting campaigns and discounts
- Public vending users to vote, spin for prizes, and scan QR codes

No frameworks. No Tailwind. Just pure PHP and Bootstrap.

---

## 📁 Folder Structure

```
/qr-vending-app/
├── core/              # Shared logic, config, includes
├── assets/            # Bootstrap, JS, logos
├── admin/             # Admin login & controls
├── business/          # Vendor (machine owner) dashboard
├── user/              # Public-facing vending experience
├── index.php          # Landing page
└── README.md          # This file
```

---

## 🔐 Roles

- **Admin** – Manage users and vendors
- **Business** – Edit items, create QR codes, view votes
- **User** – Vote on items, spin for prizes, view results

---

## 🎯 Features

- Multi-role login system
- Voting for vending machine items (per IP)
- Spin-to-win prize wheel (individual + community rewards)
- QR Code generator with:
  - Label text (e.g., "Scan to Vote!")
  - Logo overlay
  - Error correction level
- Bootstrap 5 layout with navbar/footer (no sidebar)

---

## 📦 Requirements

- PHP 7.4 or higher
- MySQL/MariaDB
- Composer (for QR libraries like `endroid/qr-code`)
- Bootstrap 5.x CSS & JS
- PHPMailer (for email notifications)

---

## 🚀 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/qr-vending-app.git
   cd qr-vending-app
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create database and import schema:
   ```bash
   mysql -u your_username -p your_database < core/schema.sql
   ```

4. Configure the application:
   - Copy `core/config.example.php` to `core/config.php`
   - Update database credentials
   - Set SMTP settings for email notifications
   - Configure site URL and other constants

5. Set up web server:
   - Point document root to the project directory
   - Ensure PHP has write access to `assets/img/` for QR codes and logos
   - Configure URL rewriting if needed

6. Create initial admin account:
   ```sql
   INSERT INTO users (name, email, password, role) 
   VALUES ('Admin', 'admin@example.com', '$2y$10$...', 'admin');
   ```

---

## ⚙️ Configuration

### Database Settings
Edit `core/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'qr_vending_app');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### SMTP Settings
Configure in admin dashboard or directly in database:
```sql
UPDATE system_settings 
SET value = 'your_smtp_host' 
WHERE setting_key = 'smtp_host';
```

### System Limits
Adjust in admin dashboard:
- Max votes per IP
- Max QR codes per business
- Max items per business
- File upload size limits

---

## 👥 User Guides

### Admin
1. Login at `/admin/login.php`
2. Manage users and businesses
3. Configure system settings
4. View system-wide reports

### Business
1. Login at `/business/login.php`
2. Add/edit vending machine items
3. Generate QR codes for voting
4. View voting analytics
5. Manage business profile

### Public Users
1. Scan QR code on vending machine
2. Vote for favorite items
3. Spin wheel for prizes
4. View voting results

---

## 🔒 Security

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`
- CSRF protection on forms
- Rate limiting for votes and spins
- IP-based restrictions

---

## 📧 Email Notifications

The system sends emails for:
- Welcome messages
- Password resets
- Prize notifications
- Business account approvals

Configure SMTP settings in admin dashboard or database.

---

## 🎨 Customization

- Edit Bootstrap theme in `assets/css/custom.css`
- Modify email templates in admin dashboard
- Adjust spin wheel probabilities
- Customize QR code settings

---

## 🐛 Troubleshooting

1. Email not sending:
   - Check SMTP settings
   - Verify PHP mail() function
   - Check error logs

2. QR codes not generating:
   - Verify write permissions
   - Check PHP GD extension
   - Validate QR library installation

3. Database connection issues:
   - Verify credentials
   - Check MySQL service
   - Validate database schema

---

## 📝 License

MIT License - See LICENSE file for details

---

## 👥 Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

---

## 💬 Support

For support, email support@revenueqr.com or create an issue.

---

## 🙏 Credits

Created by [Your Name]  
With help from OpenAI & CursorAI tools 
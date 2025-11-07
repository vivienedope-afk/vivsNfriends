# Maia Alta Homes - HOA Management System

A complete Homeowners Association management system built for Maia Alta Homes subdivision.

## Features

### Admin Features (Treasurer/Management)
- 📊 Dashboard with financial overview
- 💰 Payment tracking and dues management
- 👥 Resident management
- 🏠 Household/unit management
- 📢 Post announcements
- ✅ Approve/reject facility bookings
- 📊 Generate financial reports
- 🚗 Vehicle registration management

### Resident Features
- 👤 Personal profile management
- 💵 View and pay monthly dues
- 📜 Payment history/ledger
- 📅 Book facilities (clubhouse, basketball court, etc)
- 📢 View announcements
- 🔧 Submit maintenance requests
- 🚗 Register vehicles

## Technology Stack
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** Apache (XAMPP)

## Installation Guide

### Prerequisites
- XAMPP installed (or Apache + MySQL + PHP)
- Web browser

### Step 1: Setup Database

1. Start XAMPP and run Apache and MySQL
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Import the database:
   - Go to "Import" tab
   - Click "Choose File" and select `database.sql`
   - Click "Go" to import

### Step 2: Configure Database Connection

1. Open `config/database.php`
2. Update credentials if needed (default is):
   ```php
   DB_HOST = 'localhost'
   DB_USER = 'root'
   DB_PASS = ''
   DB_NAME = 'maia_alta_hoa'
   ```

### Step 3: Access the System

1. Open browser and go to: `http://localhost/vivsNfriends/vivsNfriends/login.php`

### Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Resident Account:**
- Username: `jdoe`
- Password: `admin123`

⚠️ **IMPORTANT:** Change these passwords after first login!

## Project Structure

```
vivsNfriends/
├── admin/                  # Admin panel pages
│   ├── dashboard.php
│   ├── residents.php
│   ├── payments.php
│   └── bookings.php
├── auth/                   # Authentication files
│   ├── login_process.php
│   ├── logout.php
│   └── session_check.php
├── config/                 # Configuration files
│   └── database.php
├── css/                    # Stylesheets
│   ├── login.css
│   └── admin.css
├── pics/                   # Images/logos
├── database.sql           # Database schema
├── login.php              # Login page
├── index.php              # Resident dashboard
└── account.php            # Account management
```

## Usage

### For Admin (Treasurer):
1. Login with admin credentials
2. Access admin dashboard to:
   - View payment statistics
   - Manage resident accounts
   - Verify payments
   - Approve facility bookings
   - Post announcements

### For Residents:
1. Login with resident credentials
2. Access features:
   - View monthly dues
   - Upload payment proof
   - Book facilities
   - View announcements
   - Update profile

## Security Notes

- All passwords are hashed using PHP's `password_hash()`
- Session-based authentication
- SQL injection protection with prepared statements
- Role-based access control (Admin/Resident)

## Development Notes

This system is designed specifically for Maia Alta Homes HOA with features requested by the treasurer for managing subdivision operations.

### Future Enhancements:
- Email notifications
- SMS alerts
- Online payment integration (GCash, PayMaya)
- Mobile app version
- Document management system
- Visitor management

## Support

For issues or questions, contact the developer.

## License

Proprietary - Built for Maia Alta Homes HOA

---
**Version:** 1.0  
**Last Updated:** November 7, 2025  
**Developer:** vivsNfriends team

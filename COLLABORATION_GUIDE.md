# 🤝 Collaboration Guide - Maia Alta HOA System

## Para sa Collaborator (Bagong Developer)

### 📋 Prerequisites
Siguraduhing naka-install ang mga sumusunod:
- **XAMPP** (Apache + MySQL + PHP)
- **Git** (para sa version control)
- **Code Editor** (VS Code recommended)
- **GitHub Account**

---

## 🚀 Step 1: Clone the Repository

1. Open terminal/command prompt
2. Navigate sa XAMPP htdocs folder:
```bash
cd C:\xampp\htdocs
```

3. Clone the repository:
```bash
git clone https://github.com/vivienedope-afk/vivsNfriends.git
cd vivsNfriends
```

4. Switch to main branch (if not already):
```bash
git checkout main
```

---

## 🗄️ Step 2: Setup Database

### Option A: Using phpMyAdmin (Recommended)

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

2. **Access phpMyAdmin**
   - Open browser: `http://localhost/phpmyadmin`

3. **Create Database**
   - Click "New" sa left sidebar
   - Database name: `maia_alta_hoa`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

4. **Import SQL File**
   - Select `maia_alta_hoa` database
   - Click "Import" tab
   - Click "Choose File"
   - Select: `database.sql` from project folder
   - Click "Go" sa bottom
   - Wait for success message

### Option B: Using MySQL Command Line

```bash
# Navigate to project folder
cd C:\xampp\htdocs\vivsNfriends\vivsNfriends

# Login to MySQL
C:\xampp\mysql\bin\mysql.exe -u root -p

# Create database (sa MySQL prompt)
CREATE DATABASE maia_alta_hoa;
USE maia_alta_hoa;
SOURCE database.sql;
exit;
```

---

## ⚙️ Step 3: Configure Database Connection

1. Open file: `config/database.php`
2. Verify ang settings (usually default lang for XAMPP):

```php
<?php
$host = 'localhost';
$username = 'root';
$password = '';  // Usually blank for XAMPP
$database = 'maia_alta_hoa';
```

3. Kung iba ang MySQL password mo, update ang `$password`

---

## 🔐 Step 4: Test Login Access

### Admin Account
- **URL**: `http://localhost/vivsNfriends/vivsNfriends/login.php`
- **Account Number**: `ADMIN001`
- **Password**: `admin123`

### Resident Account 1
- **Account Number**: `MAIA-2025-001`
- **Password**: `admin123`

### Resident Account 2
- **Account Number**: `MAIA-2025-002`
- **Password**: `admin123`

---

## 🌿 Step 5: Git Workflow (Para sa Development)

### Check Current Branch
```bash
git branch
```

### Create Your Own Branch
```bash
# Gumawa ng sariling branch para sa features mo
git checkout -b feature/your-name-feature-description

# Example:
git checkout -b feature/john-payment-module
```

### Daily Workflow
```bash
# 1. Update your local copy (every morning)
git checkout main
git pull origin main

# 2. Switch to your feature branch
git checkout feature/your-branch-name

# 3. Merge latest changes from main
git merge main

# 4. Work on your code...

# 5. Check what files changed
git status

# 6. Stage your changes
git add .

# 7. Commit with clear message
git commit -m "Add: Payment verification feature"

# 8. Push to GitHub
git push origin feature/your-branch-name
```

### Commit Message Guidelines
- `Add: ` - New feature
- `Fix: ` - Bug fix
- `Update: ` - Modification
- `Remove: ` - Deletion
- `Refactor: ` - Code improvement

Examples:
```bash
git commit -m "Add: Resident registration form validation"
git commit -m "Fix: Payment calculation bug in ledger"
git commit -m "Update: Dashboard color scheme to gold theme"
```

---

## 🔄 Step 6: Create Pull Request

1. Push your branch to GitHub:
```bash
git push origin feature/your-branch-name
```

2. Go to GitHub repository
3. Click "Compare & pull request" button
4. Add description:
   - What changed?
   - Why it changed?
   - How to test?
5. Assign reviewer (usually vivienedope-afk)
6. Click "Create pull request"

---

## 📁 Project Structure

```
vivsNfriends/
├── admin/                      # Admin-only pages
│   ├── dashboard.php          # Admin dashboard
│   ├── residents.php          # Manage residents
│   └── residents_action.php   # Resident CRUD operations
├── auth/                       # Authentication
│   ├── login_process.php      # Login handler
│   ├── logout.php             # Logout handler
│   └── session_check.php      # Session validation
├── config/                     # Configuration
│   └── database.php           # Database connection
├── css/                        # Stylesheets
│   ├── admin.css              # Admin styles
│   ├── home.css               # Home page styles
│   └── login.css              # Login page styles
├── pics/                       # Images
│   └── Courtyard.png          # Logo
├── database.sql               # Database schema
├── login.php                  # Login page
├── home.php                   # Resident home page
├── index.php                  # Dashboard page
├── account.php                # Account management
└── style.css                  # Global styles
```

---

## 🎨 Design Guidelines

### Color Palette (Gold/Tan Theme)
```css
Primary Light:  #fedea3
Primary:        #f5d18a
Secondary:      #c17f59
Dark:           #79491b
Accent:         #d4a574
```

### Important Rules
- ❌ **NO ICONS** - We use text-based design
- ✅ Use unicode symbols if needed (☰, ✕, ←, 👁, ⚠, ✓)
- ✅ Stick to gold/tan color scheme
- ✅ Keep design clean and professional

---

## 🐛 Troubleshooting

### Database Connection Error
```
Error: Connection failed: Access denied
```
**Solution**: 
- Check `config/database.php` credentials
- Make sure MySQL is running sa XAMPP
- Verify database `maia_alta_hoa` exists

### Page Not Loading (404)
```
Not Found: The requested URL was not found
```
**Solution**:
- Check if XAMPP Apache is running
- Verify URL: `http://localhost/vivsNfriends/vivsNfriends/login.php`
- Check if file exists sa correct folder

### Session Not Working
```
Redirecting to login.php repeatedly
```
**Solution**:
- Clear browser cookies/cache
- Check if `session_start()` is in `session_check.php`
- Verify `auth/session_check.php` is included sa page

### Git Merge Conflicts
```
CONFLICT (content): Merge conflict in file.php
```
**Solution**:
```bash
# 1. Open conflicted file
# 2. Look for <<<<<<< HEAD markers
# 3. Choose which code to keep
# 4. Remove conflict markers
# 5. Stage and commit
git add .
git commit -m "Resolve: Merge conflict in file.php"
```

---

## 📞 Communication

### Before You Start Coding
1. Check existing issues sa GitHub
2. Create new issue kung walang related
3. Assign yourself sa issue
4. Inform team lead (vivienedope-afk)

### When You're Done
1. Test thoroughly
2. Create pull request
3. Add screenshots if UI changes
4. Wait for code review
5. Address feedback if any

---

## 🔒 Security Notes

⚠️ **IMPORTANT**: Current version uses **plain text passwords** for testing only!

### For Production (Future):
- Enable password hashing sa `auth/login_process.php`
- Use `password_hash()` and `password_verify()`
- Add HTTPS
- Implement CSRF protection
- Add rate limiting

---

## 📝 Testing Checklist

Before submitting pull request:

- [ ] Code follows project structure
- [ ] No console errors
- [ ] Tested on Chrome/Firefox
- [ ] Mobile responsive
- [ ] Follows color scheme
- [ ] No icons added (text only)
- [ ] Database queries use prepared statements
- [ ] Session checks in place
- [ ] Error handling implemented
- [ ] Comments added for complex logic

---

## 🚀 Quick Start (TL;DR)

```bash
# 1. Clone
cd C:\xampp\htdocs
git clone https://github.com/vivienedope-afk/vivsNfriends.git
cd vivsNfriends/vivsNfriends

# 2. Database
# Import database.sql via phpMyAdmin to 'maia_alta_hoa'

# 3. Start XAMPP (Apache + MySQL)

# 4. Test
# http://localhost/vivsNfriends/vivsNfriends/login.php
# Login: ADMIN001 / admin123

# 5. Create branch
git checkout -b feature/yourname-feature

# 6. Code, commit, push
git add .
git commit -m "Add: Your feature description"
git push origin feature/yourname-feature

# 7. Create Pull Request on GitHub
```

---

## 📚 Additional Resources

- **PHP Documentation**: https://www.php.net/docs.php
- **MySQL Tutorial**: https://dev.mysql.com/doc/
- **Git Guide**: https://git-scm.com/book/en/v2
- **VS Code PHP**: https://code.visualstudio.com/docs/languages/php

---

## 👥 Team

**Project Lead**: vivienedope-afk
**For Questions**: Create issue on GitHub or contact project lead

---

**Happy Coding! 🎉**

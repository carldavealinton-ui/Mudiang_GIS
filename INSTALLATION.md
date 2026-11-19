# INSTALLATION GUIDE
## Barangay Mudiang GIS Complaint System

### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache**
3. Start **MySQL**

### Step 2: Create Database (Choose ONE method)

#### METHOD A: Using phpMyAdmin (RECOMMENDED)
1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click on "New" in the left sidebar
3. Database name: `barangay_gis`
4. Click "Create"
5. Click on the `barangay_gis` database you just created
6. Click on "SQL" tab at the top
7. Copy and paste the ENTIRE content from `database.sql` file
8. Click "Go" button
9. You should see "2 rows affected" message

#### METHOD B: Using Automatic Update Script
1. Open browser and go to: `http://localhost/System/update_database.php`
2. Wait for the script to complete
3. You should see green checkmarks
4. Delete the `update_database.php` file after completion

### Step 3: Verify Installation
1. Go to: `http://localhost/System/`
2. You should see the home page with map
3. Click "Login"
4. Use these credentials:
   - Username: `admin`
   - Password: `password`
5. You should be redirected to Admin Dashboard

### Step 4: Test Registration
1. Go to: `http://localhost/System/register.php`
2. Fill in the registration form
3. Submit
4. Login with your new account
5. You should see Resident Dashboard

### Troubleshooting

#### Error: "Unknown column 'full_name'"
**Solution:** Run the update script
1. Go to: `http://localhost/System/update_database.php`
2. Wait for completion
3. Try again

#### Error: "Table 'barangay_gis.users' doesn't exist"
**Solution:** Import database.sql
1. Open phpMyAdmin
2. Select `barangay_gis` database
3. Click "Import" tab
4. Choose `database.sql` file
5. Click "Go"

#### Error: "Access denied for user 'root'"
**Solution:** Check database credentials in `config.php`
```php
$host = 'localhost';
$dbname = 'barangay_gis';
$username = 'root';
$password = ''; // Usually empty for XAMPP
```

### Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `password`

**IMPORTANT:** Change the admin password after first login!

### System URLs

- Home Page: `http://localhost/System/`
- Login: `http://localhost/System/login.php`
- Register: `http://localhost/System/register.php`
- Admin Dashboard: `http://localhost/System/admin/dashboard.php`
- Annual Report: `http://localhost/System/admin/annual_report.php`

### Features

✅ User Registration & Login
✅ Interactive Google Maps
✅ Pin exact location on map
✅ Submit complaints (Guest & Resident)
✅ Admin complaint management
✅ Status tracking (Pending/In Progress/Resolved)
✅ Priority levels (Low/Medium/High)
✅ Annual reports with charts
✅ Resolution notes

### Support

If you encounter any issues:
1. Check that Apache and MySQL are running in XAMPP
2. Verify database exists in phpMyAdmin
3. Check config.php settings
4. Run update_database.php script
5. Clear browser cache and try again

### Security Notes

⚠️ **IMPORTANT:**
1. Change default admin password immediately
2. Delete `update_database.php` after running it
3. Use strong passwords for all accounts
4. Enable HTTPS in production
5. Keep system files outside public web directory in production

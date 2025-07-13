# SMTP Tester - Installation Instructions

## 🚀 Quick Installation via Web Interface

The SMTP Tester now includes an automated web-based installation system. No manual database setup required!

### Step 1: Upload Files
1. Upload all files to your web server's document root (usually `public_html` or `httpdocs`)
2. Ensure proper file permissions (755 for directories, 644 for files)

### Step 2: Run Installation
1. Visit your website: `https://your-domain.com/install`
2. Enter your database connection details:
   - **Database Host**: Usually `localhost`
   - **Database Name**: Choose any name (e.g., `smtp_tester`)
   - **Database Username**: Your database username
   - **Database Password**: Your database password
3. Click "Test Connection & Continue"
4. Click "Create Database Tables" to complete installation

### Step 3: Clean Up
1. **Important**: Delete the `/install` folder after successful installation
2. The installer will provide specific instructions for your hosting environment

## 🛡️ Security Notes

- The installation wizard automatically creates an `install.lock` file to prevent re-installation
- Delete the `/install` directory immediately after setup for security
- The installer validates database permissions and creates tables automatically

## 📋 Installation Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.3+)
- **Database Privileges**: CREATE, INSERT, UPDATE, DELETE, SELECT
- **Extensions**: PDO, PDO_MySQL, OpenSSL, cURL

## 🔧 Manual Installation (Alternative)

If you prefer manual installation:

1. Create a MySQL database
2. Import `database_schema.sql` into your database
3. Update database credentials in `config/config.php`
4. Create an empty file: `config/install.lock`
5. Delete the `/install` folder

## 🆘 Troubleshooting

### Common Issues:

**"Database connection failed"**
- Verify database credentials
- Ensure MySQL service is running
- Check if your hosting provider uses a different host (not localhost)

**"Permission denied"**
- Ensure your database user has CREATE privileges
- Check file permissions on the server

**"Installation already completed"**
- Delete `config/install.lock` to run installer again
- Or manually update database credentials in `config/config.php`

### Getting Help:

1. Check your hosting provider's database documentation
2. Contact hosting support for database credentials
3. Verify PHP version and MySQL version compatibility

## 🎯 Post-Installation

After successful installation:

1. Visit your main application: `https://your-domain.com`
2. Test SMTP functionality with your email server
3. Configure any proxy settings if needed
4. Set up proper SSL certificates for production use

## 📁 File Structure

```
/
├── install/              # Installation wizard (delete after setup)
│   ├── index.php        # Installation interface
│   └── style.css        # Installation styling
├── config/
│   ├── config.php       # Main configuration
│   └── install.lock     # Installation completion marker
├── index.html           # Main application
├── api/                 # Backend API files
├── assets/              # Frontend assets
└── database_schema.sql  # Database structure
```

---

**Ready to test your SMTP server configurations!** 🎉
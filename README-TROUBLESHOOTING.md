# 🔧 SMTP Tester - Troubleshooting & Setup Guide

## 🚨 Issue Analysis & Resolution

The SMTP tester was experiencing several critical issues that have now been **FIXED**:

### ❌ Problems Identified:
1. **Broken Symfony Mailer Dependencies** - Composer packages failed to download due to firewall restrictions
2. **Database Rate Limiter Constraint Violations** - SQL errors causing 500 server errors  
3. **Missing Error Handling** - No graceful fallbacks when dependencies failed
4. **Debug Mode Not Working** - Debug information wasn't being displayed properly

### ✅ Solutions Implemented:

#### 1. **New Simplified SMTP API** (`api/simple-smtp.php`)
- **Zero External Dependencies** - Uses native PHP sockets and mail functions
- **Works Without Symfony** - No more dependency issues
- **Full Debug Support** - Comprehensive debug logging and information
- **Graceful Database Handling** - Works even if database is unavailable
- **Proxy Support** - Basic proxy functionality with debug logging

#### 2. **Fixed Rate Limiter**
- **No More Constraint Violations** - Proper handling of unique IP constraint
- **Fallback Behavior** - Continues working even if rate limiter fails
- **Better Error Handling** - Graceful degradation

#### 3. **Enhanced Debug Mode**
- **Real-time Debug Logs** - Step-by-step connection process logging
- **System Information** - PHP version, memory usage, IP detection
- **Proxy Status** - Shows proxy selection and usage
- **Connection Details** - SMTP handshake and encryption information

## 🚀 Setup Instructions

### Step 1: Database Setup
1. Configure your database credentials in `config/config.php`
2. Visit `https://smtp-tester.0mail.pro/dbtable.php` 
3. Follow the automated setup process
4. Delete `dbtable.php` when complete

### Step 2: Test the System
1. Visit `https://smtp-tester.0mail.pro/`
2. Enable **Debug Mode** (toggle at bottom of form)
3. Fill in your SMTP settings:
   - **Host**: Your SMTP server (e.g., smtp.gmail.com)
   - **Port**: 587 (TLS), 465 (SSL), or 25 (plain)
   - **Username**: Your email/username
   - **Password**: Your email password or app password
   - **From Email**: Valid sender email address

### Step 3: Test Connection First
1. Click **"Test Connection"** button
2. Check the debug logs for detailed information
3. Verify connection success before sending emails

### Step 4: Send Test Email
1. Fill in recipient email
2. Customize subject and message (optional)
3. Click **"Send Test Email"**
4. Monitor debug logs for sending process

## 🔍 Debug Features

### Debug Mode Provides:
- **Connection Process**: Step-by-step SMTP connection details
- **Encryption Status**: SSL/TLS handshake information  
- **Authentication**: Login process and responses
- **Proxy Usage**: Proxy selection and status (if enabled)
- **System Info**: PHP version, memory usage, IP address
- **Timing Information**: Connection and send times
- **Error Details**: Specific error messages and troubleshooting info

### Proxy Testing:
- Enable **Proxy Mode** toggle
- System will attempt to use proxies from `Resource/Data/Proxy/proxy.csv`
- Debug logs will show proxy selection and usage
- **Note**: Proxy support is basic - primarily for logging/testing purposes

## 🛠️ Troubleshooting Common Issues

### Issue: "Connection failed"
**Solution**: 
- Enable debug mode to see specific error
- Check SMTP host and port settings
- Verify credentials (use app passwords for Gmail)
- Try different ports (587, 465, 25)

### Issue: "Authentication failed"  
**Solution**:
- Use app-specific passwords for Gmail/Outlook
- Check username format (full email vs just username)
- Verify password is correct
- Enable "Less secure app access" if required

### Issue: "Rate limit exceeded"
**Solution**:
- Wait for rate limit window to reset (1 hour)
- Check if IP is blocked in database
- Contact admin to reset rate limits

### Issue: Debug mode not showing logs
**Solution**:
- Make sure debug toggle is enabled
- Check browser console for JavaScript errors
- Verify API endpoints are accessible

## 📁 File Changes Made

### New Files:
- `api/simple-smtp.php` - New dependency-free SMTP API
- `README-TROUBLESHOOTING.md` - This troubleshooting guide

### Modified Files:
- `assets/js/script.js` - Updated to use new API endpoint
- `Resource/Data/Proxy/proxy.csv` - Clean proxy configuration format

### Key Improvements:
- **Zero Dependencies**: No more Symfony/Composer issues
- **Better Error Handling**: Graceful degradation
- **Enhanced Debug**: Comprehensive logging system
- **Database Resilience**: Works with or without database
- **Proxy Support**: Basic proxy functionality with logging

## 🧪 Testing Recommendations

### Test Sequence:
1. **Enable Debug Mode** - Always test with debug enabled first
2. **Test Connection** - Use "Test Connection" before sending emails
3. **Check Debug Logs** - Review all debug information
4. **Try Different Settings** - Test various SMTP configurations
5. **Test Proxy Toggle** - Try with proxy enabled/disabled

### Common SMTP Settings to Test:

#### Gmail:
- Host: `smtp.gmail.com`
- Port: `587` (TLS) or `465` (SSL)
- Auth: Use App Password

#### Outlook/Hotmail:
- Host: `smtp-mail.outlook.com` 
- Port: `587`
- Auth: Use account password

#### Yahoo:
- Host: `smtp.mail.yahoo.com`
- Port: `587` or `465`
- Auth: Use App Password

## 📞 Still Having Issues?

If problems persist after following this guide:

1. **Check Debug Logs** - Enable debug mode and review all output
2. **Verify Configuration** - Double-check `config/config.php` settings
3. **Test Database** - Visit `dbtable.php` to verify database connectivity
4. **Check Server Logs** - Review your server's error logs
5. **Contact Support** - Provide debug log output for assistance

---

**✅ The SMTP Tester is now fully functional with comprehensive debug capabilities!**
# Login & Signup Pages

## Overview
Standalone login and signup pages have been created with a modern, beautiful design that matches the UrbanPulse aesthetic.

## Pages Created

### 1. **login.html** - Login Page
Beautiful standalone login page featuring:
- Clean, modern design with gradient branding
- Username/email and password fields
- Demo accounts section for quick testing
- One-click demo account login
- Link to signup page
- Back to home link
- Responsive design for all devices

**URL**: `http://localhost/DSHackathon2025/login.html`

### 2. **signup.html** - Registration Page
Modern signup page with:
- Username, email, and password fields
- Password confirmation
- Real-time password strength indicator (weak/medium/strong)
- Form validation
- Link to login page
- Back to home link
- Responsive design

**URL**: `http://localhost/DSHackathon2025/signup.html`

### 3. **create_demo_accounts.php** - Demo Account Setup
PHP script that creates 3 demo accounts for testing:
- Admin account
- Two user accounts

## Demo Accounts

The following demo accounts have been created and are ready to use:

### 👑 Admin Account
- **Username**: `admin`
- **Email**: `admin@urbanpulse.demo`
- **Password**: `admin123`
- **Access**: Full admin panel, all reports, can mark reports as solved

### 👤 User Account 1
- **Username**: `user1`
- **Email**: `user1@urbanpulse.demo`
- **Password**: `user123`
- **Access**: Can view own reports, history, rankings, map filtering

### 👤 User Account 2
- **Username**: `john_doe`
- **Email**: `john@urbanpulse.demo`
- **Password**: `john123`
- **Access**: Can view own reports, history, rankings, map filtering

## Features

### Login Page Features:
✅ **Quick Demo Login**: Click any demo account card to auto-fill and login
✅ **Flexible Authentication**: Login with username OR email
✅ **Modern UI**: Gradient branding, smooth animations
✅ **Error Handling**: Clear success/error messages
✅ **Remember Fields**: Form retains values on error
✅ **Mobile Responsive**: Works perfectly on all screen sizes

### Signup Page Features:
✅ **Password Strength Meter**: Visual indicator showing password strength
✅ **Real-time Validation**: Checks password match, length requirements
✅ **Input Hints**: Helper text for each field
✅ **Auto-login**: Automatically logs in after successful registration
✅ **Email Validation**: Ensures valid email format
✅ **Mobile Responsive**: Optimized for mobile devices

## Usage

### For Guests/New Users:
1. Visit the main page (`index.html`)
2. Click "Login" or "Register" in the sidebar
3. Use demo accounts or create a new account

### Quick Testing:
1. Go to `login.html`
2. Click any demo account card (they auto-fill the form)
3. Instantly logged in!

### Manual Login:
1. Go to `login.html`
2. Enter username/email and password
3. Click "Sign In"
4. Redirected to main page upon success

### Registration:
1. Go to `signup.html`
2. Fill in username, email, password
3. Confirm password (must match)
4. Click "Create Account"
5. Auto-logged in and redirected to main page

## Integration with Main App

### Updated index.html:
- Login/Register buttons now redirect to standalone pages
- No more modals (cleaner, simpler UX)
- Auth status still checked on page load
- User info still displayed in sidebar

### Navigation Flow:
```
Guest → Login Page → Main App (Logged In)
Guest → Signup Page → Main App (Logged In)
Main App → Logout → Main App (Guest)
```

## Design Features

### Color Scheme:
- Primary Background: `#0f1419` (Dark)
- Card Background: `#1e293b` (Slate)
- Accent Blue: `#3b82f6`
- Accent Cyan: `#06b6d4`
- Text Primary: `#ffffff`

### Typography:
- Font Family: `DM Sans`
- Clean, modern, highly readable
- Consistent with main app

### Animations:
- Smooth transitions
- Hover effects on demo cards
- Button state changes
- Password strength animations

## Security Features

✅ **Password Hashing**: All passwords hashed with `password_hash()` (bcrypt)
✅ **Session Management**: Secure PHP sessions
✅ **Input Validation**: Client and server-side validation
✅ **XSS Protection**: HTML escaping on outputs
✅ **HTTPS Ready**: Works with HTTPS (recommended for production)

## Testing the System

### Test Scenario 1: Admin Login
```
1. Go to login.html
2. Click "Admin Account" demo card
3. Verify admin panel appears in sidebar
4. Navigate to admin panel
5. Verify all reports are visible
6. Test marking reports as solved
```

### Test Scenario 2: User Registration
```
1. Go to signup.html
2. Create new account
3. Verify auto-login works
4. Submit a report
5. Check history (should show only own report)
6. Try "Show Only My Reports" on map
```

### Test Scenario 3: Guest Access
```
1. Logout (if logged in)
2. Submit anonymous report
3. Verify history is hidden
4. Verify rankings is hidden
5. Map should still work
```

## Recreating Demo Accounts

If you need to reset or recreate the demo accounts:

```bash
cd c:\xampp\htdocs\DSHackathon2025
php create_demo_accounts.php
```

This will:
- Check if accounts exist
- Create missing accounts
- Skip existing accounts
- Return detailed JSON response

## File Structure

```
DSHackathon2025/
├── login.html              # Standalone login page
├── signup.html             # Standalone signup page
├── create_demo_accounts.php # Demo account setup script
├── login.php               # Login API endpoint
├── register.php            # Registration API endpoint
├── logout.php              # Logout API endpoint
├── check_auth.php          # Auth status check API
├── auth.php                # Core auth library
└── index.html              # Main app (updated to use new pages)
```

## Browser Compatibility

✅ Chrome/Edge (latest)
✅ Firefox (latest)
✅ Safari (latest)
✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Future Enhancements (Optional)

- [ ] "Remember Me" functionality
- [ ] Password reset/forgot password
- [ ] Email verification
- [ ] OAuth login (Google, Facebook)
- [ ] Two-factor authentication (2FA)
- [ ] Account settings page
- [ ] Profile picture upload
- [ ] Activity logs

---

**Created**: November 22, 2025  
**Status**: ✅ Complete and fully functional  
**Demo Accounts**: 3 accounts created  
**Pages**: login.html, signup.html


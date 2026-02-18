# BeautyBook - Beauty Professionals Booking Platform

A comprehensive PHP-based web application for beauty professionals and clients to connect, book appointments, and manage their beauty business.

## Features

### For Clients
- Browse and search beauty professionals
- View professional profiles and portfolios
- Book appointments with real-time availability
- Manage appointment history
- Direct messaging with professionals
- Leave reviews and ratings

### For Professionals
- Professional dashboard with analytics
- Manage services and pricing
- Set availability and working hours
- Upload portfolio/gallery items
- Appointment management
- Client communication
- Route optimization for mobile services

### General Features
- User authentication and authorization
- Responsive design for mobile and desktop
- Real-time messaging system
- Image upload and gallery management
- Secure payment processing (ready for integration)
- Review and rating system

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Custom CSS with responsive design
- **Server**: Apache (XAMPP compatible)

## Installation Requirements

- XAMPP (or similar LAMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- Modern web browser

## Installation Instructions

### 1. Download and Setup XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP following the installation wizard
3. Start Apache and MySQL services from XAMPP Control Panel

### 2. Database Setup

1. Open phpMyAdmin by navigating to `http://localhost/phpmyadmin`
2. Create a new database named `beauty_booking`
3. Import the database schema:
   - Click on the `beauty_booking` database
   - Go to the "Import" tab
   - Select the `database_schema.sql` file
   - Click "Go" to import

### 3. Application Setup

1. Copy the entire `beauty_booking_xampp_complete` folder to your XAMPP `htdocs` directory
   - Default location: `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac)

2. Rename the folder to `beauty_booking` (optional, for cleaner URLs)

3. Configure database connection:
   - Open `config.php` in a text editor
   - Update database credentials if needed (default should work with XAMPP):
     ```php
     $host = 'localhost';
     $dbname = 'beauty_booking';
     $username = 'root';
     $password = ''; // Empty for XAMPP default
     ```

### 4. File Permissions

Create the uploads directory and set proper permissions:

1. Create folder: `uploads/gallery/` inside your application directory
2. Set write permissions for the web server (usually automatic on XAMPP)

### 5. Access the Application

1. Open your web browser
2. Navigate to `http://localhost/beauty_booking/` (or your chosen folder name)
3. You should see the BeautyBook homepage

## Default Test Accounts

The system starts with no default accounts. You can:

1. Register as a new client or professional
2. Create test accounts through the registration page

## Configuration

### Database Configuration
Edit `config.php` to modify database settings:

```php
// Database configuration
$host = 'localhost';
$dbname = 'beauty_booking';
$username = 'root';
$password = '';
```

### Upload Settings
The application supports image uploads up to 5MB. To change this:

1. Edit `php.ini` in your XAMPP installation
2. Modify these settings:
   ```ini
   upload_max_filesize = 5M
   post_max_size = 5M
   max_execution_time = 300
   ```
3. Restart Apache

## File Structure

```
beauty_booking/
├── config.php                 # Database configuration
├── index.php                  # Homepage
├── login.php                  # User login
├── register.php               # User registration
├── logout.php                 # Logout functionality
├── api.php                    # Main API endpoints
├── get-services.php           # AJAX service loader
├── get-available-times.php    # AJAX time slot loader
├── appointments.php           # Appointment management
├── browse-professionals.php   # Professional directory
├── new-appointment.php        # Booking form
├── professional-dashboard.php # Professional dashboard
├── client-dashboard.php       # Client dashboard
├── gallery.php               # Gallery viewer
├── gallery-upload.php        # Gallery upload
├── messages.php              # Messaging system
├── database_schema.sql       # Database structure
├── css/
│   └── styles.css            # Main stylesheet
├── js/
│   └── main.js               # Main JavaScript
└── uploads/
    └── gallery/              # Uploaded images
```

## Usage Guide

### For New Users

1. **Registration**: Visit the homepage and click "Sign Up"
2. **Choose Role**: Select either "Client" or "Professional"
3. **Complete Profile**: Fill in your information
4. **Verify Email**: (Feature ready for implementation)

### For Clients

1. **Find Professionals**: Use the search and filter options
2. **View Profiles**: Check portfolios and reviews
3. **Book Appointments**: Select services and available times
4. **Manage Bookings**: View and manage your appointments
5. **Communicate**: Message professionals directly

### For Professionals

1. **Setup Profile**: Complete your professional profile
2. **Add Services**: Define your services and pricing
3. **Set Availability**: Configure your working hours
4. **Upload Portfolio**: Showcase your work
5. **Manage Appointments**: Accept, confirm, and complete bookings
6. **Communicate**: Respond to client messages

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check if MySQL is running in XAMPP
   - Verify database credentials in `config.php`
   - Ensure `beauty_booking` database exists

2. **File Upload Issues**
   - Check if `uploads/gallery/` directory exists
   - Verify write permissions
   - Check PHP upload limits in `php.ini`

3. **Page Not Found (404)**
   - Ensure files are in the correct XAMPP htdocs directory
   - Check that Apache is running
   - Verify the URL path

4. **Styling Issues**
   - Clear browser cache
   - Check if CSS files are loading (F12 Developer Tools)
   - Verify file paths are correct

### Error Logs

- PHP errors: Check XAMPP error logs
- Database errors: Check MySQL error logs
- Browser console: F12 → Console tab for JavaScript errors

## Security Considerations

### For Production Deployment

1. **Change Database Credentials**: Use strong passwords
2. **Enable HTTPS**: Configure SSL certificates
3. **Update PHP Settings**: Disable error display, enable logging
4. **File Permissions**: Restrict write access to necessary directories only
5. **Input Validation**: All user inputs are validated and sanitized
6. **SQL Injection Protection**: Uses prepared statements
7. **XSS Protection**: All outputs are escaped

### Recommended Production Settings

```php
// In config.php for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');
```

## Customization

### Branding
- Edit CSS variables in `css/styles.css` for colors and fonts
- Replace logo and images with your branding
- Modify text content in PHP files

### Features
- Add new service categories in the database
- Customize appointment time slots in `get-available-times.php`
- Extend user profiles with additional fields

## Support

For technical support or questions:

1. Check this README file
2. Review the code comments
3. Check browser console for JavaScript errors
4. Verify XAMPP logs for server errors

## License

This project is provided as-is for educational and commercial use.

## Version History

- **v1.0.0**: Initial release with core functionality
  - User authentication and authorization
  - Appointment booking system
  - Gallery and messaging features
  - Responsive design
  - XAMPP compatibility


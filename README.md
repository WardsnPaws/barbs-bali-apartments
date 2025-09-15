# Barbs Bali Apartments - Booking System

A comprehensive booking management system for Barbs Bali Apartments.

## Project Structure

- `config/` - Configuration files
- `includes/` - Core PHP utilities and functions
- `public/` - Public-facing pages
- `api/` - API endpoints and processing scripts
- `admin/` - Administrative interface
- `assets/` - Static assets (CSS, JS, images)
- `templates/` - Email and page templates
- `scripts/` - Scheduled tasks and utility scripts
- `docs/` - Documentation
- `sql/` - Database schema and migrations

## Setup

1. Configure database settings in `config/config.php`
2. Set up email credentials in `config/config_secrets.php`
3. Run composer install for dependencies
4. Import database schema from `sql/` directory

## Development

This project uses a modular structure for easy maintenance and development.
# Barbs Bali Apartments - Booking Management System

A comprehensive booking management system for Barbs Bali Apartments featuring online reservations, payment processing, automated emails, and administrative tools.

## Project Structure

```
barbs-bali-apartments/
├── README.md                           # Project documentation
├── .gitignore                          # Git ignore rules
├── composer.json                       # PHP dependencies
├── composer.lock                       # Locked dependency versions
│
├── config/                             # Configuration Files
│   ├── config.php                      # Main database and app configuration
│   └── config_secrets.php              # Email credentials and API keys (not in git)
│
├── includes/                           # Core PHP Utilities
│   ├── core.php                        # Database connection and core functions
│   ├── email-utils.php                 # Email sending and template functions
│   └── price-calc.php                  # Booking price calculation logic
│
├── public/                             # Public-Facing Pages
│   ├── index.html                      # Main landing/booking page
│   ├── booking-form.html               # Enhanced booking form
│   ├── my-booking.php                  # Booking lookup and management
│   ├── pay-balance.php                 # Balance payment interface
│   └── thank-you.html                  # Booking confirmation page
│
├── api/                                # API Endpoints & Processing
│   ├── booking-process.php             # Main booking creation handler
│   ├── check-availability.php          # Real-time availability checking
│   ├── get-calendar-data.php           # Calendar data for availability display
│   ├── get-session-booking.php         # Retrieve booking data from session
│   ├── mark-paid.php                   # Payment status updates
│   ├── pay-balance-process.php         # Balance payment processing
│   ├── resend-confirmation.php         # Resend confirmation emails
│   ├── secure-payment.php              # Secure payment page handler
│   ├── square-payment.php              # Square payment integration
│   └── update-extras.php               # Update booking extras/add-ons
│
├── admin/                              # Administrative Interface
│   ├── index.php                       # Admin dashboard home
│   ├── auth.php                        # Authentication functions
│   ├── login.php                       # Admin login page
│   ├── logout.php                      # Session cleanup
│   ├── file-check.php                  # System file verification
│   ├── update-booking.php              # Booking modification interface
│   │
│   ├── views/                          # Admin Page Views
│   │   ├── dashboard.php               # Main admin dashboard
│   │   ├── bookings.php                # Booking management list
│   │   ├── booking-edit.php            # Individual booking editor
│   │   ├── calendar.php                # Calendar view of bookings
│   │   ├── payments.php                # Payment tracking and management
│   │   ├── email-log.php               # Email send history and logs
│   │   ├── settings.php                # System settings management
│   │   └── delete-booking.php          # Booking deletion handler
│   │
│   └── tools/                          # Admin Utilities
│       ├── index.php                   # Tools dashboard
│       ├── create-fake-booking.php     # Test booking creation
│       ├── fake-payment.php            # Test payment simulation
│       ├── fetch-booking-summary.php   # Booking data export
│       ├── reset-test-data.php         # Development data cleanup
│       ├── send-all-scheduled.php      # Manual email queue processing
│       └── send-scheduled-emails.php   # Individual scheduled email sender
│
├── templates/                          # Email & Page Templates
│   └── email/                          # Email Templates
│       ├── booking-confirmation.html   # Initial booking confirmation
│       ├── balance-reminder.html       # Payment reminder emails
│       ├── checkin-reminder.html       # Pre-arrival information
│       └── housekeeping-notice.html    # Housekeeping coordination
│
├── scripts/                            # Background & Scheduled Tasks
│   └── send-scheduled-emails.php       # Automated email scheduling system
│
├── assets/                             # Static Assets
│   ├── css/                            # Stylesheets
│   ├── js/                             # JavaScript files
│   └── images/                         # Images and media
│
├── docs/                               # Documentation
│   ├── old-structure.txt               # Previous file organization
│   └── implementation-requirements.md  # Email system enhancement specs
│
├── sql/                                # Database Schema & Migrations
│   └── (to be added)                   # Database setup files
│
└── vendor/                             # Composer Dependencies
    └── (composer packages)             # Third-party PHP libraries
```

## File Descriptions

### Configuration Files
- **config.php**: Database connection, timezone, payment settings, and general app configuration
- **config_secrets.php**: Sensitive data like email passwords, API keys, and SMTP settings (excluded from version control)

### Core System Files
- **core.php**: Database PDO connection, common utility functions, and session management
- **email-utils.php**: Email template processing, SMTP configuration, and email queue management
- **price-calc.php**: Dynamic pricing calculations including seasonal rates, extras, and discounts

### Public Interface
- **index.html**: Main booking page with availability calendar and room selection
- **booking-form.html**: Detailed booking form with guest information and preferences
- **my-booking.php**: Customer portal for viewing bookings and making payments
- **pay-balance.php**: Secure payment interface for outstanding balances
- **thank-you.html**: Post-booking confirmation with next steps

### API Layer
- **booking-process.php**: Validates and processes new booking requests
- **check-availability.php**: Real-time availability checking for date ranges
- **get-calendar-data.php**: Provides booking data for calendar displays
- **square-payment.php**: Integration with Square payment processing
- **update-extras.php**: Handles booking modifications and add-on services

### Administrative System
- **Dashboard Views**: Complete booking management, calendar overview, payment tracking
- **Admin Tools**: Testing utilities, data management, and system maintenance
- **Authentication**: Secure login system with session management

### Email System
- **Templates**: HTML email templates with dynamic content placeholders
- **Scheduling**: Automated email sending based on booking dates and payment status
- **Tracking**: Email delivery status and customer engagement metrics

## Key Features

### Booking Management
- Real-time availability checking
- Dynamic pricing with seasonal adjustments
- Guest information collection and validation
- Booking modification and cancellation handling

### Payment Processing
- Secure Square payment integration
- Partial payment and balance tracking
- Automated payment reminders
- Payment history and receipts

### Email Automation
- Booking confirmation emails
- Pre-arrival information and check-in details
- Payment reminders and balance notifications
- Post-departure follow-up communications

### Administrative Tools
- Comprehensive booking dashboard
- Calendar view with occupancy status
- Payment tracking and reporting
- Email log and delivery monitoring
- Test data management for development

## Setup Instructions

1. **Database Configuration**
   - Update database credentials in `config/config.php`
   - Import database schema (to be added to `sql/` directory)

2. **Email Setup**
   - Configure SMTP settings in `config/config_secrets.php`
   - Set up email templates with appropriate branding

3. **Payment Integration**
   - Add Square API credentials to `config/config_secrets.php`
   - Configure payment processing endpoints

4. **Dependencies**
   - Run `composer install` to install required packages
   - Verify all file permissions are correctly set

## Development Workflow

This modular structure allows for:
- **Separation of Concerns**: Clear distinction between public, admin, and API functionality
- **Easy Maintenance**: Related files are grouped logically
- **Version Control**: Sensitive configuration files are excluded from git
- **Testing**: Dedicated admin tools for creating test data and scenarios
- **Scalability**: Clean API layer for future mobile app or third-party integrations

## Security Considerations

- Configuration files with secrets are excluded from version control
- Admin authentication required for management functions
- Payment processing uses secure, validated endpoints
- Input validation and sanitization throughout the system
- Session management with appropriate timeouts

## Future Enhancements

- Enhanced email template management system
- Advanced reporting and analytics
- Multi-language support
- Mobile app API extensions
- Integration with property management systems

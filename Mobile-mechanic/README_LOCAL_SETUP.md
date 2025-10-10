# Mobile Mechanic - Local Setup

## 🚀 Local Installation Complete!

The Mobile Mechanic application is now running locally on your system.

### 📍 Access URLs
- **Main Application**: http://localhost:8081/
- **Admin Login**: http://localhost:8081/login.php
- **Registration**: http://localhost:8081/register.php

### 🔐 Default Login Credentials

**Admin Login:**
- Email: `admin@gmail.com`
- Password: `admin`

### 🗄️ Database Information
- **Database Name**: `mm`
- **Host**: `localhost`
- **User**: `root`
- **Password**: (empty)

### 📋 Database Tables
- `admin` - Administrator accounts
- `customer_reg` - Customer registrations
- `mechanic_reg` - Mechanic registrations  
- `servicerequest` - Service requests/appointments
- `vehicledescription` - Vehicle information

### 🎯 Application Features

#### For Customers:
- Register and login
- Request mechanic services
- Add vehicle information
- Track service appointments
- Rate mechanics

#### For Mechanics:
- Register and login  
- View assigned service requests
- Update service status
- Manage profile information

#### For Administrators:
- Manage mechanics
- Add vehicle types
- View all service requests
- System administration

### 🔧 Development Server

The application is currently running on PHP's built-in development server:
```bash
php -S localhost:8081
```

To stop the server: Press `Ctrl+C` in the terminal where it's running.

### 🐛 Troubleshooting

1. **Database Connection Issues**: 
   - Ensure MariaDB/MySQL is running: `sudo systemctl status mysql`
   - Verify database exists: `sudo mysql -e "USE mm; SHOW TABLES;"`

2. **PHP Issues**:
   - Check PHP version: `php --version`
   - Required extensions: mysqli, pdo_mysql

3. **Port Already in Use**:
   - Try different port: `php -S localhost:8082`

### 📱 Testing the Application

1. Open http://localhost:8081/ in your browser
2. Navigate to the registration page to create test accounts
3. Try the admin login with the credentials above
4. Test the service request functionality

### 🔄 Restarting the Application

If you need to restart:
```bash
cd /home/kylewee/mechanicsaintaugustine.com/site/Mobile-mechanic
php -S localhost:8081
```

Enjoy testing your Mobile Mechanic application! 🚗⚙️
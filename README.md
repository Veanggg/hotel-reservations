# Hotel Reservation System

## Database Management System Final Project

A comprehensive hotel reservation system built with PHP and MySQL that demonstrates proper database design, SQL operations, and web integration.

## Project Overview

This Hotel Reservation System is a database-driven web application that allows hotel staff to manage rooms, reservations, guests, and generate reports, while guests can browse available rooms and make bookings online.

## Technology Stack

- **Database**: MySQL
- **Backend/Server-side**: PHP
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Database Connection**: Custom MySQLi connection class
- **Deployment**: Localhost (XAMPP/WAMP/Laragon)

## Features

### Admin Features
- **Dashboard**: Overview with statistics and recent reservations
- **Room Management**: Full CRUD operations for hotel rooms
- **Reservation Management**: Create, view, edit, and manage reservations
- **Guest Management**: Manage guest information and history
- **Reports & Analytics**: Comprehensive reports with SQL queries
- **User Management**: Admin and user account management

### User Features
- **User Dashboard**: Browse available rooms and make bookings
- **Booking Management**: View and manage personal reservations
- **Profile Management**: Update personal information and change password
- **Search & Filter**: Find rooms by type, status, and availability

## Database Schema

### Tables (8 main tables)

1. **users** - User authentication and roles
2. **room_types** - Room categories and pricing
3. **rooms** - Individual hotel rooms
4. **guests** - Guest information
5. **reservations** - Booking records
6. **payments** - Payment transactions
7. **services** - Additional hotel services
8. **guest_services** - Service usage tracking

### Relationships
- Users → Reservations (one-to-many)
- Guests → Reservations (one-to-many)
- Rooms → Reservations (one-to-many)
- Room Types → Rooms (one-to-many)
- Reservations → Payments (one-to-many)
- Services → Guest Services (one-to-many)

## Installation & Setup

### Prerequisites
- XAMPP/WAMP/Laragon or similar localhost server
- MySQL database
- PHP 7.4 or higher

### Steps

1. **Clone/Download the project** to your localhost directory (e.g., `htdocs/hotel-reservation`)

2. **Import the database**:
   ```sql
   -- Create database and import database.sql
   mysql -u root -p hotel_reservation < database.sql
   ```

3. **Configure database connection** (if needed):
   - Edit `config/database.php`
   - Update database credentials if different from default

4. **Start your local server**:
   - Start Apache and MySQL services
   - Access the application at `http://localhost/hotel-reservation`

## Default Login Credentials

### Admin Account
- **Username**: admin
- **Password**: 12345678

### User Accounts
- **Username**: john_doe
- **Password**: password123

- **Username**: jane_smith  
- **Password**: password123

## Project Structure

```
hotel-reservation/
├── admin/
│   ├── dashboard.php          # Admin dashboard
│   ├── rooms.php              # Room management
│   ├── reservations.php       # Reservation management
│   ├── guests.php             # Guest management
│   ├── reports.php            # Reports and analytics
│   └── users.php              # User management
├── user/
│   ├── dashboard.php          # User dashboard
│   ├── bookings.php           # User bookings
│   └── profile.php            # User profile
├── config/
│   └── database.php           # Database connection class
├── functions/
│   └── auth.php               # Authentication functions
├── login.php                  # Login page
├── logout.php                 # Logout script
├── database.sql               # Database schema and sample data
└── README.md                  # This file
```

## SQL Requirements Met

### Joins (5+ queries implemented)
1. Reservation details with guest and room information
2. Room occupancy by type
3. Guest statistics with reservation data
4. Top guests by revenue
5. Monthly trends with guest counts
6. Service usage with reservation details
7. Payment methods analysis

### Aggregate Functions (2+ functions used)
1. **COUNT()**: Total reservations, guests, rooms
2. **SUM()**: Total revenue, total spent
3. **AVG()**: Average stay duration, average payment
4. **MAX()**: Highest payment
5. **MIN()**: Lowest payment

### Database Constraints Applied
- **Primary Keys**: All tables have proper primary keys
- **Foreign Keys**: Proper relationships between tables
- **NOT NULL**: Required fields
- **UNIQUE**: Username, email, room number, ID number
- **ENUM**: Status fields with predefined values
- **DEFAULT VALUES**: Created timestamps, default statuses

### Normalization
- **1NF**: All attributes are atomic
- **2NF**: No partial dependencies
- **3NF**: No transitive dependencies

## Key Features Demonstration

### CRUD Operations
- **Create**: Add rooms, guests, reservations
- **Read**: View all data with search and filters
- **Update**: Edit room details, reservation status
- **Delete**: Remove rooms, guests (with constraints)

### Search & Filter
- Search by name, email, room number
- Filter by status, date range, room type
- Real-time filtering in admin panels

### Reports
1. **Revenue Analysis**: Monthly revenue trends
2. **Occupancy Reports**: Room utilization by type
3. **Guest Analytics**: Top guests and statistics
4. **Payment Analysis**: Payment method breakdown
5. **Service Usage**: Most popular services

### Security Features
- Session-based authentication
- Role-based access control
- Input sanitization
- SQL injection prevention

## Screenshots & Demonstration

### Login Page
- Modern gradient design
- Role-based authentication
- Demo account information

### Admin Dashboard
- Real-time statistics
- Recent reservations overview
- Quick navigation to all modules

### Room Management
- Visual room cards
- Status indicators
- Quick edit/delete actions

### Reports Section
- Comprehensive analytics
- Interactive charts
- Exportable data

### User Dashboard
- Room browsing interface
- One-click booking
- Personal reservation history

## Database Views

The system includes optimized database views for reporting:

1. **reservation_details**: Complete reservation information
2. **room_occupancy**: Room status by type
3. **revenue_report**: Monthly revenue summary

## Advanced Features (Optional)

- Database views for optimized reporting
- Transaction support for data integrity
- Session management
- Responsive design
- Real-time status updates
- Automated calculations

## Project Requirements Fulfilled

✅ **Database Design**: 8+ tables with proper relationships  
✅ **Normalization**: 1NF, 2NF, 3NF applied  
✅ **SQL Operations**: CREATE, INSERT, SELECT, UPDATE, DELETE, JOIN  
✅ **Aggregate Queries**: COUNT, SUM, AVG, MAX, MIN functions  
✅ **Constraints**: Primary keys, foreign keys, NOT NULL, UNIQUE, DEFAULT  
✅ **CRUD Operations**: Full implementation on all main entities  
✅ **Search & Filter**: Multiple search and filter options  
✅ **Reports**: 7 comprehensive reports with SQL queries  
✅ **Login System**: Admin and user roles with session management  
✅ **Website Interface**: Clean, responsive, professional design  
✅ **Localhost Setup**: Fully functional on XAMPP/WAMP  

## Presentation Guide

### Demonstration Flow

1. **Login Demonstration**
   - Show admin and user login
   - Display role-based redirection

2. **Admin Features**
   - Dashboard overview
   - Room management (CRUD)
   - Reservation management
   - Guest management
   - Reports and analytics

3. **User Features**
   - Room browsing and booking
   - Reservation history
   - Profile management

4. **Database Demonstration**
   - Show table relationships
   - Explain normalization
   - Demonstrate SQL queries

### Key Points to Highlight

- **Database Design**: Explain the ERD and relationships
- **SQL Queries**: Show complex JOIN and aggregate queries
- **Security**: Input validation and session management
- **User Experience**: Intuitive interface and responsive design
- **Scalability**: Normalized database structure

## Future Enhancements

- Payment gateway integration
- Email notifications
- Mobile app development
- Advanced reporting with charts
- Multi-language support
- Hotel chain management

## Contact & Support

For questions or support regarding this project, please refer to the project documentation or contact the development team.

---

**Project Completion Date**: May 15, 2024  
**Database Management System Final Project**  
**Group of 6 Students**  
**Hotel Reservation System**
#   h o t e l _ r e s e r v a t i o n  
 #   h o t e l _ r e s e r v a t i o n  
 
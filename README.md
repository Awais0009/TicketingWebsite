You are my coding partner. I am building a small event planning & ticket booking website for an assignment. 
The assignment requires:
- Frontend → HTML, CSS, JavaScript (kept in separate files/folders)
- Backend → PHP (server-side logic, database, sessions)
- Database → SQL (PostgreSQL on Neon DB, connected via PHP PDO)
- **Website must be hosted online** (required for marks)

I want the repo to be a single project with this structure:

```
ticket-website/
│
├── assets/
│   ├── css/style.css        # all CSS here
│   ├── js/validation.js     # all JS here
│   └── images/              # event/user images
│
├── inc/                     # backend helpers
│   ├── db.php               # PDO connection to Neon DB
│   ├── header.php           # Bootstrap navbar, includes CSS/JS links
│   ├── footer.php           # closing tags + footer
│   └── security.php         # CSRF protection & validation helpers
│
├── auth/                    
│   ├── register.php
│   ├── login.php
│   └── logout.php
│
├── organizer/               
│   ├── create_event.php
│   └── organizer_dashboard.php
│
├── admin/
│   └── admin_dashboard.php
│
├── index.php                # homepage, list events
├── event.php                # event detail + booking
├── orders.php               # user bookings
├── schema.sql               # SQL schema for Neon DB
├── README.md                # setup + hosting instructions
└── deploy.md                # deployment guide for hosting platforms
```

## Assignment Compliance Requirements
This project must meet specific academic standards:
- Website hosted online with working URL (mandatory for grading)
- Professional error handling with user-friendly messages
- Both client-side AND server-side validation on all forms
- Comprehensive testing documentation for the report
- Security best practices implementation
- Bootstrap alerts for success/error feedback

## Roles (must be enforced in PHP)
- User → register/login, view events, book tickets, view their bookings
- Organizer → create/manage events  
- Admin → manage all users/events
- Role stored in users table ("role" column), checked via PHP sessions with proper security

## Essential Features
1. **User Registration & Login** with enhanced security:
   - Password hashing (password_hash/password_verify)
   - Email validation using filter_var(FILTER_VALIDATE_EMAIL)
   - Password strength requirements (min 8 chars, mixed case, numbers)
   - CSRF protection on all forms
   - Rate limiting for login attempts

2. **Role-based dashboards** with access control:
   - User → bookings page
   - Organizer → create/manage events
   - Admin → manage users/events

3. **Event Management**:
   - Event listing (index.php) → responsive Bootstrap cards
   - Event detail (event.php) → show info, booking form
   - Search/filter events by date, venue, price range
   - Event categories/tags for better organization

4. **Ticket Booking System**:
   - Decrements available tickets atomically
   - Generates unique booking references
   - Email confirmation (bonus: using PHPMailer)
   - Prevents overbooking with proper validation

5. **Orders Management**:
   - User bookings page (orders.php)
   - Booking history with status tracking

6. **Security & Performance**:
   - PDO prepared statements for all queries
   - Input sanitization with htmlspecialchars() for output
   - Session security (httponly, secure flags)
   - Database indexes for performance

7. **Responsive Design**:
   - Bootstrap 5 for all screens (mobile-first approach)
   - Interactive elements with hover effects
   - Loading states and user feedback

## Database Schema (Neon DB / PostgreSQL)

```sql
-- Core tables
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'organizer', 'admin')),
    email_verified BOOLEAN DEFAULT FALSE,
    reset_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    organizer_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    venue VARCHAR(255) NOT NULL,
    date TIMESTAMP NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_tickets INTEGER NOT NULL,
    tickets_sold INTEGER DEFAULT 0 CHECK (tickets_sold <= total_tickets),
    image_url VARCHAR(500),
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    event_id INTEGER REFERENCES events(id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Performance indexes
CREATE INDEX idx_events_date ON events(date);
CREATE INDEX idx_events_category ON events(category);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_booking_ref ON orders(booking_reference);

-- Sample seed data
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@test.com', '$2y$10$example_hash', 'admin'),
('John Organizer', 'organizer@test.com', '$2y$10$example_hash', 'organizer'),
('Jane User', 'user@test.com', '$2y$10$example_hash', 'user');

INSERT INTO events (organizer_id, title, description, venue, date, price, total_tickets, category) VALUES
(2, 'Tech Conference 2025', 'Annual technology conference', 'Convention Center', '2025-11-15 09:00:00', 99.99, 500, 'Technology'),
(2, 'Music Festival', 'Three-day music festival', 'City Park', '2025-12-01 18:00:00', 149.99, 1000, 'Music');
```

## Coding Guidelines & Best Practices

**Security First:**
- Always use PDO prepared statements - never concatenate SQL
- CSRF tokens on all forms that modify data
- Sanitize all output with htmlspecialchars()
- Validate and sanitize all input server-side
- Use secure session configuration

**Code Organization:**
- HTML in .php files outputs the frontend
- CSS → always in assets/css/style.css (never inline)
- JS → always in assets/js/validation.js (client-side validation + UX enhancements)
- PHP backend logic in inc/ folder + inline when needed
- Bootstrap 5 for responsive design everywhere

**Database Connection (inc/db.php):**
```php
<?php
// Neon DB PostgreSQL connection
$host = 'your-neon-hostname';
$dbname = 'your-database-name'; 
$user = 'your-username';
$password = 'your-password';

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>
```

## Step-by-Step Workflow
We will build this project in small, manageable steps. After each step, explain how the files connect and work together before moving to the next phase.

### Step 1: Foundation Setup
- Create folder structure as above
- Set up inc/db.php with secure Neon DB connection
- Create inc/security.php with CSRF protection functions
- Create header.php with Bootstrap navbar + all CSS/JS links
- Create footer.php with proper closing tags
- **Explain:** How the include system works and file relationships

### Step 2: Database Schema & Security Foundation  
- Write complete schema.sql with all tables and indexes
- Add comprehensive seed data (multiple users, events, sample orders)
- Implement CSRF token generation and validation functions
- Set up secure session configuration
- **Explain:** Database relationships and security token flow

### Step 3: Authentication System
- register.php → Complete form with validation + PHP processing
  - Email validation, password strength checking
  - CSRF protection, duplicate email prevention
  - Success/error messaging with Bootstrap alerts
- login.php → Form + session logic with rate limiting
- logout.php → Secure session destruction
- **Explain:** Authentication flow and session management

### Step 4: Event Display & Booking Core
- index.php → Responsive event cards with search/filter functionality
- event.php → Event details + secure booking form
- Implement atomic booking process (prevent overbooking)
- Generate unique booking references
- **Explain:** Event display logic and booking transaction handling

### Step 5: User Order Management
- orders.php → User's booking history with status display
- Implement order details and cancellation (if applicable)
- Add booking reference lookup
- **Explain:** Order management and user experience flow

### Step 6: Organizer Dashboard & Event Management
- organizer/create_event.php → Complete event creation form
  - Image upload with validation
  - Category selection, pricing validation
  - Date/time validation
- organizer/organizer_dashboard.php → Manage their events
  - Edit events, view booking statistics
  - Disable/enable events
- **Explain:** Role-based access and event management workflow

### Step 7: Admin Dashboard & User Management
- admin/admin_dashboard.php → Complete admin panel
  - User management (promote/demote roles)
  - Event moderation (delete inappropriate events)
  - System statistics and reports
- **Explain:** Admin privileges and system oversight capabilities

### Step 8: Advanced Features & Polish
- Enhanced search with category filters
- Email notifications for bookings (PHPMailer integration)
- QR code generation for tickets (bonus feature)
- Performance optimization (caching, query optimization)
- **Explain:** Advanced feature integration and performance considerations

### Step 9: Testing & Deployment Preparation
- Comprehensive testing of all user flows
- Edge case testing (invalid inputs, concurrent bookings)
- Mobile responsiveness verification  
- Deployment setup for hosting platforms (Heroku, Railway, Vercel)
- **Explain:** Testing methodology and deployment process

## Testing Requirements (for Report Documentation)
Document all testing performed:

**Functional Testing:**
- User registration → login → event browsing → ticket booking → order viewing
- Role restrictions (users can't access admin/organizer pages)
- Booking edge cases (insufficient tickets, invalid quantities)
- Form validation (both client-side and server-side)

**Security Testing:**
- CSRF protection on all forms
- SQL injection prevention (test with malicious inputs)
- Password hashing verification
- Session security and timeout

**Responsive Design Testing:**
- Mobile (320px+), Tablet (768px+), Desktop (1200px+)
- Bootstrap component behavior across screen sizes
- Form usability on touch devices

**Performance Testing:**
- Database query optimization
- Page load times with large datasets
- Concurrent booking scenarios

## Deployment & Hosting Requirements

**Platform Options:**
- Railway (recommended for PostgreSQL)
- Heroku with PostgreSQL addon
- Vercel with Neon DB
- Traditional shared hosting with PHP support

**Deployment Checklist:**
- Environment variables for database credentials
- Error reporting disabled in production
- HTTPS enforcement
- File upload security (if implemented)
- Database migrations and seed data setup

## Advanced Features (for Higher Grade Achievement)

**Enhanced User Experience:**
- Real-time ticket availability updates
- Event recommendation system
- Social sharing integration
- Progressive Web App features (offline capability)

**Business Logic:**
- Dynamic pricing based on demand
- Group booking discounts
- Event waiting lists
- Refund and cancellation policies

**Analytics & Reporting:**
- Event popularity metrics
- Revenue tracking for organizers
- User engagement statistics
- Automated reporting for admins

## Important Development Notes:

**Code Quality Standards:**
- Comprehensive inline comments explaining complex logic
- Consistent naming conventions throughout
- Error handling with user-friendly messages
- Input validation on both client and server side
- Database transactions for critical operations

**Security Checklist:**
- ✅ CSRF tokens on all forms
- ✅ Prepared statements for all queries  
- ✅ Password hashing with PASSWORD_DEFAULT
- ✅ Input sanitization and output escaping
- ✅ Secure session configuration
- ✅ File upload validation (if implemented)

**Performance Optimization:**
- Database indexes on frequently queried columns
- Efficient SQL queries (avoid N+1 problems)
- Image optimization for event photos
- CSS/JS minification for production
- Caching strategies for repeated queries

Remember: Always explain what you're building and how each component fits into the overall system architecture before implementing. Focus on creating a professional, secure, and user-friendly application that demonstrates mastery of full-stack web development principles.
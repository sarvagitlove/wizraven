# ATEA Seattle - Laravel Backend

## Overview
This is the Laravel backend for the ATEA (Asian Technology Entrepreneurs Association) Seattle chapter member management system. The system provides Google-based authentication, member profile management, and admin-controlled onboarding.

## Features
### 🚀 **Current Working Features (Oct 2025)**

#### Member Signup & Activation
- Admin creates user and sends activation link (45-day expiry)
- User signs up via activation link, fills multi-step profile form
- Local form validation in frontend (Angular/Ionic)
- Backend validates activation link (expired, used, deactivated)
- Backend validates profile fields and password
- Only on successful profile save, user status changes to 'approval_pending'
- If submission fails, backend returns clear error messages (validation, expired link, etc.)
- Frontend displays user-friendly error messages from backend
- No false status updates if API fails

#### Admin Dashboard & Approval
- Admin dashboard shows all members and their statuses
- Approve/reject buttons for 'approval_pending' members
- Status badge shows 'Sign-up Pending', 'Approval Pending', 'Active', etc.
- Approve button disabled for 'signup_pending', enabled for 'approval_pending'
- After approval, member status changes to 'active' and profile is visible

#### Error Handling & Sync
- All backend errors (validation, expired link, server) returned with clear messages
- Frontend parses and displays backend error messages and validation errors
- End-to-end sync: successful submission → 'approval_pending'; failed submission → error message, status unchanged

### 🔐 **Authentication System**
- Google OAuth integration via Firebase
- Laravel Sanctum for API authentication
- Role-based access control (Admin/User)
- Activation link system with 45-day expiry

### 👥 **User Management**
- Admin-created user accounts
- Email-based activation system
- User status tracking (pending/active/disabled)
- Google profile integration

### 📋 **Member Profiles**
- Comprehensive business profile fields
- Multi-step profile completion
- Admin approval workflow
- Public member directory

### 🛡️ **Admin Features**
- Dashboard with statistics
- User management (create, update, delete)
- Member profile approval/rejection
- Activation link management
- Activity monitoring

## Database Schema

### Users Table
- `id`, `name`, `email`, `google_id`, `avatar`
- `role_id` (foreign key to roles)
- `status` (pending/active/disabled)
- `email_verified_at`, `created_at`, `updated_at`

### Roles Table
- `id`, `role_name` (admin/user), `description`

### Member Profiles Table
- Complete business information (name, type, industry, description)
- Contact details (website, phone, business email)
- Address information (line 1&2, city, state, zip, country)
- Business details (year established, employees, services, target market)
- Approval workflow (status, approved_by, rejection_reason)

### Activation Links Table
- `id`, `user_id`, `token` (UUID), `email`
- `expires_at` (45 days), `used_at`, `sent_at`
- `is_active` flag

## API Endpoints

### 🔓 **Public Endpoints**
```
POST /api/auth/google                     # Google OAuth login
GET  /api/activate/{token}                # Activate account
GET  /api/activate/check/{token}          # Check activation link
POST /api/activate/resend                 # Resend activation link
GET  /api/members/directory               # Public member directory
GET  /api/members/profile/{id}            # Public member profile
```

### 🔒 **Protected Endpoints (Authentication Required)**
```
POST /api/auth/logout                     # Logout user
GET  /api/auth/user                       # Get current user
GET  /api/member/profile                  # Get user's profile
POST /api/member/profile                  # Update user's profile
POST /api/member/profile/submit           # Submit profile for approval
```

### 👑 **Admin-Only Endpoints**
```
# Dashboard
GET  /api/admin/dashboard                 # Dashboard statistics
GET  /api/admin/activity                  # System activity log

# User Management
GET  /api/admin/users                     # List all users
POST /api/admin/users                     # Create new user
PATCH /api/admin/users/{user}/status      # Update user status
DELETE /api/admin/users/{user}            # Delete user
POST /api/admin/users/{user}/resend-activation # Resend activation

# Profile Management
GET  /api/admin/profiles/pending          # Get pending profiles
POST /api/admin/profiles/{profile}/approve # Approve profile
POST /api/admin/profiles/{profile}/reject  # Reject profile

# Activation Management
GET  /api/admin/activation/user/{user}/links # Get user's activation links
POST /api/admin/activation/user/{user}/generate # Generate new link
DELETE /api/admin/activation/link/{link}  # Deactivate link
```

## Setup Instructions

### 1. Firebase Configuration
Create a Firebase project and download the service account credentials. Update `.env`:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nyour-private-key-here\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account-email@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
```

### 2. Database Setup
```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

### 3. Start Development Server
```bash
php artisan serve --port=8001
```

### 4. Create First Admin User
Use the API to create an admin user or manually insert into database:
```sql
INSERT INTO users (name, email, role_id, status, created_at, updated_at) 
VALUES ('Admin User', 'admin@atea-seattle.org', 1, 'active', NOW(), NOW());
```

## Security Features

### 🔒 **Authentication**
- Firebase ID token verification
- Laravel Sanctum token-based API auth
- CORS configuration for frontend domains

### 🛡️ **Authorization**
- Role-based middleware (admin/user)
- Route protection with auth:sanctum
- Custom AdminMiddleware for admin-only routes

### 🔐 **Data Protection**
- Password nullable for Google-only auth
- Secure activation token generation (UUID)
- Rate limiting on activation link requests

## Member Onboarding Workflow

1. **Admin Creates User** → User record with 'signup_pending' status
2. **Activation Email Sent** → 45-day expiry activation link
3. **User Clicks Link** → Opens signup form, fills profile, sets password
4. **Profile Submitted** → Backend validates, status changes to 'approval_pending' only if save succeeds
5. **Admin Reviews** → Approves or rejects with reason
6. **Member Active** → Appears in public directory
7. **Error Handling** → Any failure returns clear message, status unchanged

## Environment Variables

```env
# Database
DB_CONNECTION=sqlite

# Firebase/Google Auth
FIREBASE_PROJECT_ID=
FIREBASE_PRIVATE_KEY_ID=
FIREBASE_PRIVATE_KEY=
FIREBASE_CLIENT_EMAIL=
FIREBASE_CLIENT_ID=
GOOGLE_CLIENT_ID=

# CORS
FRONTEND_URL=http://localhost:8100

# Email (Configure for production)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@atea-seattle.org"
MAIL_FROM_NAME="ATEA Seattle"
```

## TODO for Production

- [ ] Implement email service for activation links
- [ ] Add proper audit logging system
- [ ] Configure production mail driver
- [ ] Set up proper error monitoring
- [ ] Add API rate limiting
- [ ] Implement file upload for member photos
- [ ] Add search indexing for member directory
- [ ] Set up automated backups

## Testing

Test the API endpoints using tools like Postman or curl:

```bash
# Test public endpoint
curl http://localhost:8001/api/members/directory

# Test with authentication
curl -H "Authorization: Bearer {token}" http://localhost:8001/api/auth/user
```

## Support

For technical support or questions about the ATEA Seattle member management system, please contact the development team.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

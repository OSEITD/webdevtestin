# WD Parcel Management System - Complete Documentation

## 🎯 Overview
A comprehensive parcel delivery management system with two main applications:
- **Customer App**: Public-facing parcel tracking and verification
- **Outlet App**: Internal management system for outlets, managers, and drivers

## 📦 System Architecture

### Customer App (`customer-app/`)
Customer-facing Progressive Web App (PWA) for secure parcel tracking.

**Key Features:**
- 🔐 **Multi-Factor Verification**: Track# + Phone + NRC validation
- 📍 **Real-Time GPS Tracking**: Live delivery vehicle location with Leaflet maps
- 🔔 **Push Notifications**: Parcel status updates (arrival, in-transit, delivered)
- 📱 **Progressive Web App**: Offline-capable, installable on mobile devices
- 🔒 **Secure Sessions**: 24-hour authenticated tracking sessions
- 🗺️ **Interactive Maps**: Full route visualization with ETA

**Core Files:**
- `track_parcel.php` - Main tracking interface with destination map
- `track_details.php` - Detailed parcel information (post-verification)
- `secure_tracking.html` - Multi-factor identity verification portal
- `gps_tracking.html` - Live GPS tracking with UberStyleGPSTracker
- `api/secure_track.php` - Verification endpoint
- `api/check_gps_data.php` - Real-time location updates

### Outlet App (`outlet-app/`)
Multi-tenant parcel management system for business operations.

**Key Features:**
- 👥 **Role-Based Access Control**: Manager, Driver, Outlet Staff roles
- 📦 **Parcel Management**: Registration, tracking, delivery management
- 🚚 **Trip Planning**: Route optimization, driver assignment, multi-stop trips
- 📊 **Dashboard Analytics**: Real-time metrics, revenue reports, performance stats
- 🔔 **Push Notifications**: Trip assignments, status updates, alerts
- 💰 **Payment Integration**: Flutterwave payment gateway, COD support
- 📸 **Photo Uploads**: Parcel photos stored in Supabase Storage
- 🗺️ **GPS Tracking**: Driver location tracking and route monitoring
- 💬 **SMS Notifications**: Customer notifications via SMS service
- 🏢 **Multi-Tenancy**: Company/outlet isolation with Row Level Security

**Application Structure:**

#### 1. Manager/Outlet Dashboard
**Pages:**
- `pages/outlet_dashboard.php` - Main dashboard with analytics
- `pages/parcel_management.php` - Parcel registration and management
- `pages/manager_trips.php` - Trip management and assignment
- `pages/trip_wizard.php` - Multi-step trip creation wizard
- `pages/trip_tracking.php` - Real-time trip monitoring

**Features:**
- Dashboard analytics (parcels, revenue, delivery stats)
- Parcel registration with barcode generation
- Trip creation and driver assignment
- Push notification management
- Revenue and billing reports

#### 2. Driver App (`drivers/`)
**Pages:**
- `drivers/dashboard.php` - Driver dashboard with active trips
- `drivers/pages/deliveries.php` - Delivery management
- `drivers/pages/live-tracking.php` - GPS location sharing
- `drivers/pages/route.php` - Route navigation
- `drivers/pages/profile.php` - Driver profile and settings

**Features:**
- Active trip view with route details
- Parcel delivery confirmation
- GPS location broadcasting
- Proof of delivery (photos, signatures)
- Performance metrics

#### 3. API Layer (`api/`)
**Core Endpoints:**
- `api/login.php` - Session validation
- `api/parcels/create_parcel.php` - Parcel registration
- `api/trips/create_trip.php` - Trip creation
- `api/dashboard/*` - Dashboard metrics and analytics
- `api/notifications/*` - Push notification management
- `api/payments/*` - Payment processing

## 🔧 Technical Stack

### Frontend
- **JavaScript**: ES6+, Service Workers, Push API
- **CSS**: Bootstrap 5, Custom responsive design
- **Maps**: Leaflet.js for interactive mapping
- **PWA**: Service Workers, Web App Manifest, offline support

### Backend
- **PHP**: 7.4+ with sessions, CURL, JSON handling
- **Database**: Supabase (PostgreSQL) with REST API
- **Storage**: Supabase Storage for photos
- **Push Notifications**: Web Push with VAPID keys
- **SMS**: SMS service integration

### Security
- **Authentication**: Session-based with secure cookies
- **Authorization**: Role-based access control (RBAC)
- **Row Level Security**: Supabase RLS policies for multi-tenancy
- **SSL/TLS**: HTTPS enforcement, SSL verification enabled
- **CSRF Protection**: Token-based CSRF protection
- **Input Validation**: Server-side validation and sanitization

## 🚀 Setup Instructions

### 1. Environment Configuration
Create `.env` file in `outlet-app/` and `customer-app/`:

```env
# Supabase Configuration
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your_anon_key
SUPABASE_SERVICE_KEY=your_service_key

# Push Notifications (outlet-app only)
VAPID_PUBLIC_KEY=your_vapid_public_key
VAPID_PRIVATE_KEY=your_vapid_private_key
VAPID_SUBJECT=mailto:admin@yourcompany.com

# Application Settings
APP_ENV=production
BASE_URL=https://yourproductiondomain.com
SESSION_LIFETIME=3600

# Security
CSRF_TOKEN_NAME=csrf_token
RATE_LIMIT_ENABLED=true
```

### 2. Composer Dependencies
```bash
cd outlet-app
composer install
```

Required packages:
- `minishlink/web-push` - Push notifications
- `graham-campbell/result-type` - Error handling
- `brick/math` - Mathematical operations

### 3. Database Setup
1. Create Supabase project
2. Run SQL migrations from `sql/` directory:
   - `sql/DATABASE_SETUP_GUIDE.md` - Complete setup guide
   - `sql/push_subscriptions_rls_policies.sql` - RLS policies
   - `sql/create_tracking_audit_table.sql` - Audit logging

3. Key tables:
   - `parcels` - Parcel records
   - `trips` - Delivery trips
   - `drivers` - Driver information
   - `outlets` - Outlet/branch information
   - `push_subscriptions` - Push notification subscriptions
   - `tracking_sessions` - Customer tracking sessions

### 4. Web Server Configuration
**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Enable CORS for API endpoints
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
```

**File Permissions:**
```bash
chmod 600 .env
chmod 644 *.php
chmod 755 api/ pages/ drivers/
```

## 🔒 Security Features (Production-Ready)

### Implemented Security Measures:
✅ **SSL Verification Enabled** - All CURL requests verify certificates  
✅ **Environment Variables** - Credentials stored in .env (not in code)  
✅ **Dynamic URLs** - Service workers adapt to any domain automatically  
✅ **.gitignore Protection** - Sensitive files excluded from version control  
✅ **Error Display Disabled** - No sensitive info leaked in production  
✅ **Secure Sessions** - httponly, secure, samesite cookies  
✅ **Row Level Security** - Database-level multi-tenancy isolation  
✅ **Input Validation** - All user inputs sanitized  

### Security Configuration:
- **Session Security**: `includes/session_manager.php`
- **CSRF Protection**: `includes/csrf.php`
- **Rate Limiting**: `includes/rate_limiter.php`
- **Security Headers**: `includes/security_headers.php`

## 📱 Push Notifications

### Customer App
- Parcel arrival notifications
- In-transit status updates
- Delivery confirmations

### Outlet App
**Manager Notifications:**
- New trip assignments
- Trip started/completed alerts
- Parcel arrival notifications

**Driver Notifications:**
- Trip assignments
- Route updates
- Stop notifications

**Implementation:**
- Service Workers: `service-worker.js`, `sw.js`, `sw-manager.js`
- Backend: `includes/push_notification_service.php`
- Subscription management: `api/save_push_subscription.php`

## 🗺️ GPS Tracking

### Customer Side
- Real-time driver location on map
- Route visualization
- ETA calculations
- Auto-refresh every 10 seconds

### Driver Side
- Location broadcasting
- Route navigation
- Stop management
- Offline location queuing

## 💼 Business Features

### Parcel Management
- Barcode generation and scanning
- Photo upload (Supabase Storage)
- Status tracking (registered, in-transit, delivered, returned)
- Customer notifications (SMS + Push)
- Delivery proof capture

### Trip Management
- Multi-stop route planning
- Driver assignment
- Vehicle tracking
- Real-time status updates
- Completion workflow

### Analytics & Reporting
- Dashboard metrics (parcels, revenue, performance)
- Driver performance stats
- Revenue reports
- Delivery success rates
- Custom date range filtering

### Payment Processing
- Flutterwave integration
- Cash on Delivery (COD)
- Payment verification
- Transaction history

## 🧪 Testing

### Local Development
```bash
# Access via subdomain (recommended)
http://outlet.localhost/outlet-app/
http://customer.localhost/customer-app/

# Or standard localhost
http://localhost/outlet-app/
http://localhost/customer-app/
```

### Test Credentials
Configure in Supabase or use sample data from `sql/` migrations.

## 📦 Production Deployment

### Pre-Deployment Checklist
- [ ] Update `.env` with production credentials
- [ ] Set `BASE_URL` to production domain
- [ ] Set `APP_ENV=production`
- [ ] Verify SSL certificates installed
- [ ] Test all API endpoints
- [ ] Test push notifications
- [ ] Verify database RLS policies
- [ ] Configure proper file permissions
- [ ] Enable security headers
- [ ] Set up monitoring/logging

### Post-Deployment
1. Monitor error logs: `logs/` directory
2. Check push notification delivery
3. Verify GPS tracking accuracy
4. Test payment gateway integration
5. Monitor database performance

## 🎯 Production Readiness: 9/10

**Status: PRODUCTION READY** ✅

### ✅ Completed:
- All critical security vulnerabilities fixed
- SSL verification enabled
- Environment-based configuration
- Dynamic URL handling (works on any domain)
- Secure session management
- Multi-tenancy isolation
- Push notifications fully functional
- GPS tracking operational

### ⚠️ Optional Improvements:
- Remove console.log statements (50+ instances)
- Add rate limiting to all API endpoints
- Implement comprehensive error monitoring
- Add API request logging
- Set up automated backups

## 📚 Additional Documentation

- `PRODUCTION_FIXES_APPLIED.md` - Security fixes summary
- `SECURITY_FIXES_APPLIED.md` - Customer-app security details
- `SQL/DATABASE_SETUP_GUIDE.md` - Complete database setup
- `WORKFLOW_GUIDE.md` - User workflow documentation

## 🆘 Support & Troubleshooting

### Common Issues:

**Push Notifications Not Working:**
- Verify VAPID keys in `.env`
- Check service worker registration
- Ensure HTTPS enabled (required for push)

**GPS Tracking Not Updating:**
- Verify location permissions granted
- Check `api/check_gps_data.php` response
- Ensure driver app broadcasting location

**Login Issues:**
- Clear sessions: `sessions/`
- Verify Supabase credentials
- Check `includes/session_manager.php`

**Database Connection Errors:**
- Verify `.env` Supabase credentials
- Check RLS policies enabled
- Ensure service role key has proper permissions

## 📄 License
Proprietary - All rights reserved

## 👥 Contributors
WD Parcel Development Team

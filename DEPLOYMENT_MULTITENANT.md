# Multi-Tenant Deployment Guide for Render

## Architecture Overview

Your WD Parcel System uses a **multi-tenant subdomain architecture**:

- **customer.yourdomain.com** → Customer tracking app (`customer-app/`)
- **outlet.yourdomain.com** → Outlet management app (`outlet-app/`)
- **admin.yourdomain.com** → Admin dashboard (`Admins/super_admin/`)
- **drivers.yourdomain.com** → Driver dashboard (`outlet-app/drivers/`)
- **company1.yourdomain.com** → Company-specific outlet app (multi-tenant)

## Deployment Steps

### 1. Root Directory Setting in Render
**LEAVE EMPTY** ✅  
Do NOT set a root directory. The Apache virtual hosts configuration handles routing to the correct apps based on subdomain.

### 2. Push Updated Code to GitHub

```bash
git add -A
git commit -m "Add multi-tenant Apache virtual hosts configuration"
git push origin main
```

### 3. Deploy to Render

1. **Create Web Service** on Render
   - Connect to: `https://github.com/OSEITD/webdevtestin`
   - Name: `wd-parcel-system`
   - Environment: **Docker**
   - **Root Directory**: *(leave empty)*
   - Plan: Free (or Starter for production)

2. **Set Environment Variables**
   
   In Render Dashboard → Environment section, add these variables:
   
   **CRITICAL - Required for Core Functionality:**
   ```
   APP_ENV=production
   APP_DOMAIN=webdevtestin.onrender.com
   SUPABASE_URL=https://xerpchdsykqafrsxbqef.supabase.co
   SUPABASE_ANON_KEY=<your_supabase_anon_key>
   SUPABASE_SERVICE_ROLE_KEY=<your_supabase_service_role_key>
   ```
   
   **CRITICAL - Required for Push Notifications:**
   Generate VAPID keys at: https://web-push-codelab.glitch.me/
   ```
   VAPID_SUBJECT=mailto:admin@yourcompany.com
   VAPID_PUBLIC_KEY=<your_vapid_public_key>
   VAPID_PRIVATE_KEY=<your_vapid_private_key>
   ```
   
   **Optional - Payment Integration (Lenco):**
   ```
   LENCO_API_KEY=<your_lenco_api_key>
   LENCO_BUSINESS_ID=<your_lenco_business_id>
   LENCO_WEBHOOK_SECRET=<your_lenco_webhook_secret>
   ```
   
   **Optional - Email Fallback Notifications:**
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=<your_email@gmail.com>
   SMTP_PASSWORD=<your_gmail_app_password>
   SMTP_FROM_EMAIL=noreply@yourcompany.com
   SMTP_FROM_NAME=WD Parcel System
   ```
   
   ⚠️ **Without VAPID keys, trip creation will fail with 500 errors!**

3. **Deploy**
   - Click "Create Web Service"
   - Wait for build to complete (~5-10 minutes)

### 4. Configure Custom Domains (After Initial Deploy)

In Render Dashboard → Your Service → Settings → Custom Domains:

Add these custom domains:
1. `customer.yourdomain.com`
2. `outlet.yourdomain.com`
3. `admin.yourdomain.com`
4. `drivers.yourdomain.com`
5. `*.yourdomain.com` (wildcard for company subdomains)

For each domain, Render will provide CNAME records to add to your DNS:

#### DNS Configuration Example

| Type  | Name     | Value                           |
|-------|----------|---------------------------------|
| CNAME | customer | your-app.onrender.com           |
| CNAME | outlet   | your-app.onrender.com           |
| CNAME | admin    | your-app.onrender.com           |
| CNAME | drivers  | your-app.onrender.com           |
| CNAME | *        | your-app.onrender.com           |

### 5. Testing Multi-Tenant Access

After deployment and DNS propagation:

- **Customer App**: `https://customer.yourdomain.com/secure_tracking.html`
- **Outlet App**: `https://outlet.yourdomain.com/login.php`
- **Admin Dashboard**: `https://admin.yourdomain.com/auth/login.php`
- **Driver App**: `https://drivers.yourdomain.com/dashboard.php`
- **Company App**: `https://company1.yourdomain.com/login.php`

### 6. Using Render's Default Domain (No Custom Domain)

If using Render's default domain (`your-app.onrender.com`), you can test with path-based routing:

- Main app: `https://your-app.onrender.com/`
- Customer: `https://your-app.onrender.com/customer-app/`
- Outlet: `https://your-app.onrender.com/outlet-app/`
- Admin: `https://your-app.onrender.com/Admins/super_admin/`
- Drivers: `https://your-app.onrender.com/outlet-app/drivers/`

**Note**: Subdomain routing requires custom domains. Path-based routing works with the default domain but won't support company-specific subdomains.

## Troubleshooting

### Issue: Apps not routing correctly
**Solution**: Check that `APP_DOMAIN` environment variable matches your custom domain in Render.

### Issue: Subdomain not working
**Solution**: Verify DNS CNAME records are set correctly and propagated (use `dig` or `nslookup`).

### Issue: 404 errors
**Solution**: Check Apache logs in Render dashboard. Ensure `.htaccess` files exist in each app folder.

### Issue: Multi-tenant company subdomains not working
**Solution**: Add wildcard CNAME record (`*.yourdomain.com`) pointing to your Render URL.

## Architecture Benefits

✅ **Single deployment** - All apps in one container  
✅ **Shared resources** - Database, authentication, shared libraries  
✅ **Subdomain isolation** - Clean URL structure for each tenant  
✅ **Apache routing** - Automatic routing based on HTTP Host header  
✅ **Scalable** - Easy to add new company subdomains  

## Next Steps

1. Set up SSL certificates (automatic on Render with custom domains)
2. Configure Supabase RLS policies for multi-tenancy
3. Test company subdomain creation flow
4. Set up monitoring and logging
5. Configure backup strategy for uploaded files (barcodes, reports)

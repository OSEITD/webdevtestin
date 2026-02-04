<?php

/**
 * Returns an array of system pages and functionalities for search
 */
function getSystemPages() {
    return [
        [
            'id' => 'dashboard',
            'title' => 'Dashboard',
            'description' => 'Overview of system statistics and performance metrics',
            'url' => '../pages/dashboard.php',
            'icon' => 'fa-gauge'
        ],
        [
            'id' => 'companies',
            'title' => 'Companies Management',
            'description' => 'Manage delivery companies, view company details and performance',
            'url' => '../pages/companies.php',
            'icon' => 'fa-building'
        ],
        [
            'id' => 'users',
            'title' => 'User Management',
            'description' => 'Manage system users, roles, and permissions',
            'url' => '../pages/users.php',
            'icon' => 'fa-users'
        ],
        [
            'id' => 'parcels',
            'title' => 'Parcel Tracking',
            'description' => 'Track and manage parcel deliveries across the system',
            'url' => '../pages/parcels.php',
            'icon' => 'fa-box'
        ],
        [
            'id' => 'outlets',
            'title' => 'Outlet Management',
            'description' => 'Manage delivery outlets and their locations',
            'url' => '../pages/outlets.php',
            'icon' => 'fa-store'
        ],
        [
            'id' => 'drivers',
            'title' => 'Driver Management',
            'description' => 'Manage delivery drivers and their assignments',
            'url' => '../pages/drivers.php',
            'icon' => 'fa-truck'
        ],
        [
            'id' => 'reports',
            'title' => 'Reports',
            'description' => 'Generate and view system reports and analytics',
            'url' => '../pages/reports.php',
            'icon' => 'fa-chart-line'
        ],
        [
            'id' => 'settings',
            'title' => 'System Settings',
            'description' => 'Configure system-wide settings and preferences',
            'url' => '../pages/settings.php',
            'icon' => 'fa-gear'
        ],
        [
            'id' => 'notifications',
            'title' => 'Notifications',
            'description' => 'View and manage system notifications',
            'url' => '../pages/notifications.php',
            'icon' => 'fa-bell'
        ],
        [
            'id' => 'profile',
            'title' => 'Profile Settings',
            'description' => 'Manage your account settings and preferences',
            'url' => '../pages/profile.php',
            'icon' => 'fa-user'
        ],
        // Common functionalities
        [
            'id' => 'add-company',
            'title' => 'Add New Company',
            'description' => 'Register a new delivery company in the system',
            'url' => '../pages/add-company.php',
            'icon' => 'fa-plus'
        ],
        [
            'id' => 'add-outlet',
            'title' => 'Add New Outlet',
            'description' => 'Add a new delivery outlet location',
            'url' => '../pages/add-outlet.php',
            'icon' => 'fa-plus'
        ],
        [
            'id' => 'add-driver',
            'title' => 'Add New Driver',
            'description' => 'Register a new delivery driver',
            'url' => '../pages/add-driver.php',
            'icon' => 'fa-plus'
        ],
        [
            'id' => 'track-parcel',
            'title' => 'Track Parcel',
            'description' => 'Track the status and location of a parcel',
            'url' => '../pages/track-parcel.php',
            'icon' => 'fa-magnifying-glass-location'
        ],
        [
            'id' => 'help',
            'title' => 'Help & Support',
            'description' => 'Get help and support for using the system',
            'url' => '../pages/help.php',
            'icon' => 'fa-circle-question'
        ]
    ];
}
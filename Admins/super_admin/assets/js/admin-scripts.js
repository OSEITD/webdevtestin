// admin-scripts.js

document.addEventListener('DOMContentLoaded', function () {
    const supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
    const supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';

    // Use singleton pattern to prevent multiple client instances
    let supabase = null;
    if (window.supabase) {
        // Check if client already exists globally
        if (!window._supabaseClient) {
            window._supabaseClient = window.supabase.createClient(supabaseUrl, supabaseKey);
        }
        supabase = window._supabaseClient;
    } else {
        console.warn('Supabase library not loaded, some features may not work');
    }

    // Get all required elements
    const menuBtn = document.getElementById('menuBtn');
    const closeBtn = document.getElementById('closeMenu');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('menuOverlay');
    // Debug: verify elements exist
    console.debug('admin-scripts init:', {
        menuBtn: !!menuBtn,
        closeBtn: !!closeBtn,
        sidebar: !!sidebar,
        overlay: !!overlay
    });

    // Populate on-page debugStatus if present
    const debugStatus = document.getElementById('debugStatus');
    const debugContent = document.getElementById('debugStatusContent');
    const debugCloseBtn = document.getElementById('debugCloseBtn');
    if (debugContent) {
        debugContent.textContent = `menuBtn: ${!!menuBtn}\ncloseBtn: ${!!closeBtn}\nsidebar: ${!!sidebar}\noverlay: ${!!overlay}`;
        if (debugStatus) debugStatus.style.display = 'block';
    }
    if (debugCloseBtn && debugStatus) {
        debugCloseBtn.addEventListener('click', () => debugStatus.style.display = 'none');
    }

    // Toggle-based sidebar (mirrors company app behavior)
    function toggleMenu() {
        if (!sidebar || !overlay) return;
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }

    /*
    // DEBUG: observe unexpected additions of the 'show' class during runtime
    try {
        const observeTarget = sidebar || overlay;
        if (observeTarget && window.MutationObserver) {
            const obs = new MutationObserver((mutations) => {
                mutations.forEach(m => {
                    if (m.attributeName === 'class') {
                        const target = m.target;
                        if (target.classList && target.classList.contains('show')) {
                            console.warn('DEBUG: .show added to', target, 'stack:', new Error().stack);
                            // Also write to on-page debugStatus if available
                            try {
                                const debugContent = document.getElementById('debugStatusContent');
                                if (debugContent) {
                                    const now = new Date().toISOString();
                                    debugContent.textContent += `\n[${now}] .show added to ${target.id || target.className}`;
                                    document.getElementById('debugStatus').style.display = 'block';
                                }
                            } catch (e) { }
                        }
                    }
                });
            });
            if (sidebar) obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
            if (overlay) obs.observe(overlay, { attributes: true, attributeFilter: ['class'] });
        }
    } catch (err) {
        console.debug('Menu mutation observer init failed', err);
    }
    */

    // Menu button click event
    if (menuBtn) {
        menuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleMenu();
        });
    }

    // Close button click event
    if (closeBtn) closeBtn.addEventListener('click', toggleMenu);

    // Overlay click event
    if (overlay) overlay.addEventListener('click', toggleMenu);

    // Close sidebar when clicking any menu link
    document.querySelectorAll('.menu-items a').forEach(item => {
        item.addEventListener('click', toggleMenu);
    });


    // Add Outlet button functionality
    const addOutletBtn = document.getElementById('addOutletBtn');
    if (addOutletBtn) {
        addOutletBtn.addEventListener('click', () => {
            window.location.href = 'add-outlet.php';
        });
    }

    // Add Company button functionality
    const addCompanyBtn = document.getElementById('addCompanyBtn');
    if (addCompanyBtn) {
        addCompanyBtn.addEventListener('click', () => {
            window.location.href = 'add-company.php';
        });
    }

    // Fetch and display users
    const usersTableBody = document.getElementById('usersTableBody');
    let allUsers = []; // Store all users for filtering

    async function fetchActiveUsers() {
        try {
            const response = await fetch('../api/fetch_users.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.status === 'success') {
                const activeUsersElement = document.getElementById('activeUsers');
                const totalUsersElement = document.getElementById('totalUsers');

                if (activeUsersElement) {
                    activeUsersElement.textContent = result.active_users.toLocaleString();
                }
                if (totalUsersElement) {
                    totalUsersElement.textContent = result.total_users.toLocaleString();
                }
            } else {
                throw new Error(result.message || 'Failed to fetch active users count');
            }
        } catch (error) {
            console.error('Error fetching active users:', error);
            // Don't show error to user unless they explicitly request user stats
            if (document.getElementById('activeUsers')) {
                document.getElementById('activeUsers').textContent = 'Error';
            }
            if (document.getElementById('totalUsers')) {
                document.getElementById('totalUsers').textContent = 'Error';
            }
        }
    }

    function displayUsers(users) {
        console.log('displayUsers called with:', users);
        if (!usersTableBody) {
            console.error('usersTableBody element not found');
            return;
        }

        usersTableBody.innerHTML = '';
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td data-label="Name">${user.name || ''}</td>
                <td data-label="Role(s)">${user.role || ''}</td>
                <td data-label="Status">${user.status || 'Active'}</td>
                <td data-label="Actions">
                    <button class="edit-btn" data-id="${user.id}">Edit</button>
                    <button class="delete-btn" data-id="${user.id}">Delete</button>
                </td>
            `;
            usersTableBody.appendChild(row);
        });

        // Add event listeners to the new buttons
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.dataset.id;
                showMessage('Edit user functionality will be implemented soon.');
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.dataset.id;
                showMessage('Delete user functionality will be implemented soon.');
            });
        });
    }

    // Search and filter functionality is handled by individual pages (e.g. users.php)
    // to avoid conflicts and ensure correct behavior.

    // Initial fetch of active user count only
    if (document.getElementById('activeUsers')) {
        fetchActiveUsers();
    }

    // Notifications (PHP Polling)
    const notificationCount = document.getElementById('notification-count');
    let lastNotificationCount = 0;

    async function fetchNotificationCount() {
        try {
            const response = await fetch('../api/fetch_notifications.php', {
                credentials: 'include'
            });
            const data = await response.json();

            if (data.success) {
                updateNotificationCount(data.count);
                if (data.count > lastNotificationCount) {
                    // Optional: Play a sound for new notifications
                    const audio = new Audio('../assets/sounds/notification.mp3');
                    audio.play().catch(e => console.error("Audio play failed:", e));
                }
                lastNotificationCount = data.count;
            } else {
                console.error('Failed to fetch notification count:', data.error);
            }
        } catch (error) {
            console.error('Error fetching notification count:', error);
        }
    }

    function updateNotificationCount(count) {
        if (notificationCount) {
            notificationCount.textContent = count;
        }
    }

    // Initial fetch and then poll every 15 seconds
    fetchNotificationCount();
    setInterval(fetchNotificationCount, 15000);
});

// Message box function for showing notifications
window.showMessage = function (text) {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.right = '0';
    overlay.style.bottom = '0';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
    overlay.style.zIndex = '999';
    document.body.appendChild(overlay);

    // Create message box
    const messageBox = document.createElement('div');
    messageBox.style.backgroundColor = 'white';
    messageBox.style.padding = '2rem';
    messageBox.style.borderRadius = '0.5rem';
    messageBox.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    messageBox.style.textAlign = 'center';
    messageBox.style.maxWidth = '90%';
    messageBox.style.width = '400px';
    messageBox.style.position = 'fixed';
    messageBox.style.top = '50%';
    messageBox.style.left = '50%';
    messageBox.style.transform = 'translate(-50%, -50%)';
    messageBox.style.zIndex = '1000';

    // Create message text
    const messageParagraph = document.createElement('p');
    messageParagraph.textContent = text || 'An error occurred';
    messageParagraph.style.fontSize = '1.25rem';
    messageParagraph.style.marginBottom = '1.5rem';
    messageParagraph.style.color = '#333';

    // Create close button
    const closeButton = document.createElement('button');
    closeButton.textContent = 'OK';
    closeButton.style.backgroundColor = '#3b82f6';
    closeButton.style.color = 'white';
    closeButton.style.padding = '0.75rem 1.5rem';
    closeButton.style.borderRadius = '0.375rem';
    closeButton.style.fontWeight = 'bold';
    closeButton.style.cursor = 'pointer';
    closeButton.style.border = 'none';

    // Add hover effects
    closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
    closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';

    // Close functionality
    closeButton.onclick = function () {
        document.body.removeChild(overlay);
        document.body.removeChild(messageBox);
    };

    // Assemble and show
    messageBox.appendChild(messageParagraph);
    messageBox.appendChild(closeButton);
    document.body.appendChild(messageBox);
};
<?php
session_start();
require_once __DIR__ . '/../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

// Pagination settings
$itemsPerPage = 25;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch users data
try {
    // Query the `all_users` view (expects columns: id,email,full_name,role,company_name,created_at)
    $endpoint = 'all_users?select=*';

    // Apply Filters: search on full_name, and role exact match
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = urlencode('%' . $_GET['search'] . '%');
        $endpoint .= "&or=(full_name.ilike.{$search},email.ilike.{$search})";
    }

    if (isset($_GET['role']) && !empty($_GET['role'])) {
        // Use case-insensitive comparison for role filter
        $role = $_GET['role'];
        $endpoint .= "&role=ilike.{$role}";
    }

    // Apply ordering and pagination at the API level
    $endpoint .= "&order=created_at.desc&limit={$itemsPerPage}&offset={$offset}";

        error_log("[users.php] Calling Supabase endpoint: $endpoint");
        // Use service key for super_admin pages to avoid RLS/permission filtering
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
            try {
                $allUsers = callSupabaseWithServiceKey($endpoint);
                error_log('[users.php] Fetched users with service key: ' . print_r($allUsers, true));
            } catch (Exception $e) {
                error_log('[users.php] Service key fetch failed, falling back to anon: ' . $e->getMessage());
                $allUsers = callSupabase($endpoint);
            }
        } else {
            $allUsers = callSupabase($endpoint);
        }
        error_log("[users.php] Raw Supabase response: " . print_r($allUsers, true));

    // Ensure $allUsers is an array
    if (!is_array($allUsers)) $allUsers = [];
    
    error_log('[users.php] Applied filters - search: ' . ($_GET['search'] ?? 'none') . ', role: ' . ($_GET['role'] ?? 'none'));
    error_log('[users.php] Fetched ' . count($allUsers) . ' users');

    // Total count: try to fetch count separately
    $totalUsers = 0;
    try {
        $countEndpoint = 'all_users?select=count';
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = urlencode('%' . $_GET['search'] . '%');
            $countEndpoint .= "&or=(full_name.ilike.{$search},email.ilike.{$search})";
        }
        if (isset($_GET['role']) && !empty($_GET['role'])) {
            $role = $_GET['role'];
            $countEndpoint .= "&role=ilike.{$role}";
        }
        $countRes = callSupabase($countEndpoint);
        if (is_array($countRes) && isset($countRes[0]['count'])) {
            $totalUsers = (int)$countRes[0]['count'];
        } else {
            $totalUsers = count($allUsers);
        }
    } catch (Exception $e) {
        $totalUsers = count($allUsers);
    }

    // If anon key returned no rows but count indicates users exist, retry with service key
    if (empty($allUsers) && $totalUsers > 0) {
        error_log("[users.php] anon key returned 0 rows but count={$totalUsers}; retrying with service key");
        try {
            $allUsers = callSupabaseWithServiceKey('all_users', 'GET', ['select' => '*', 'limit' => $itemsPerPage, 'offset' => $offset, 'order' => 'created_at.desc']);
            error_log('[users.php] Service-key Supabase response: ' . print_r($allUsers, true));
            if (!is_array($allUsers)) $allUsers = [];
        } catch (Exception $e) {
            error_log('[users.php] Service-key retry failed: ' . $e->getMessage());
        }
    }

    $totalPages = max(1, ceil($totalUsers / $itemsPerPage));
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Get users for current page
    // If the API applied limit/offset already, use the returned rows directly
    $users = $allUsers;
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $totalPages = 1;
}

$pageTitle = 'Admin - user';
require_once __DIR__ . '/../includes/header.php';
?>
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-content h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .user-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-type-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .user-type-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-type-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #3b82f6;
        }

        .user-type-card span {
            font-weight: 500;
            color: #374151;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            color: #4b5563;
            background-color: #f3f4f6;
        }

        /* Create Role Modal Form Styles */
        .role-form-group {
            margin-bottom: 1.25rem;
        }

        .role-form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .role-form-group input,
        .role-form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .role-form-group input:focus,
        .role-form-group textarea:focus {
            outline: none;
            border-color: #2E0D2A;
            box-shadow: 0 0 0 3px rgba(46, 13, 42, 0.12);
        }

        .role-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .role-form-group .field-error {
            color: #dc2626;
            font-size: 0.8rem;
            margin-top: 0.35rem;
            display: none;
        }

        .role-form-group input.input-error,
        .role-form-group textarea.input-error {
            border-color: #dc2626;
        }

        .role-submit-btn {
            display: block;
            width: 100%;
            padding: 0.8rem;
            background-color: #2E0D2A;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .role-submit-btn:hover {
            background-color: #4a1545;
        }

        .role-submit-btn:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        @media (max-width: 640px) {
            .user-type-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
    <!-- Sidebar toggle is handled by the centralized admin scripts and a guarded inline fallback near the end of this file -->
    <div class="mobile-dashboard">


        <!-- Create Role Modal -->
        <div id="createRoleModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Create New Role</h2>
                    <button class="modal-close" id="closeRoleModal">&times;</button>
                </div>
                <form id="createRoleForm" novalidate>
                    <div class="role-form-group">
                        <label for="roleName">Role Name <span style="color:#dc2626">*</span></label>
                        <input type="text" id="roleName" name="name" placeholder="e.g. Warehouse Manager" required>
                        <div class="field-error" id="roleNameError"></div>
                    </div>
                    <div class="role-form-group">
                        <label for="roleDescription">Description</label>
                        <textarea id="roleDescription" name="description" placeholder="Optional description of this role's responsibilities"></textarea>
                    </div>
                    <button type="submit" class="role-submit-btn" id="roleSubmitBtn">Create Role</button>
                </form>
            </div>
        </div>

        <!-- Main Content Area for All Users -->
        <main class="main-content">
            <div class="content-header">
                <h1>All Users</h1>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="add-user.php" class="add-btn" style="text-decoration:none;">
                        <i class="fas fa-user-plus"></i> Add New User
                    </a>
                    <button class="add-btn" id="createRoleBtn" style="background-color: #4a1545;">
                        <i class="fas fa-shield-halved"></i> Create Role
                    </button>
                </div>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchUsers" placeholder="Search by name" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="filter-dropdown">
                    <select id="filterRole">
                        <!-- populated dynamically from DB via ./api/get_roles.php -->
                        <option value="">Role</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>

                            <th>Role(s)</th>
                            <th>Status</th>
                            <th>Company</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php

                        try {
                            // Use the already paginated $users array from the PHP section above
                            if (empty($users)) {
                                echo '<tr><td colspan="6" class="text-center">No users found.</td></tr>';
                            } else {
                                foreach ($users as $user) {
                                    $role = $user['role'] ?? 'N/A';
                                    $viewPage = 'view-user.php';

                                    // Derive display values from the view columns
                                    // Show only full_name in the Name column; do not fall back to email
                                    $displayName = $user['full_name'] ?? 'N/A';
                                    $companyName = $user['company_name'] ?? 'N/A';
                                    $createdAt = isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : '';
                                    $statusVal = $user['status'] ?? 'active';

                                    echo '<tr>';
                                    echo '<td data-label="Name">' . htmlspecialchars($displayName) . '</td>';

                                    echo '<td data-label="Role(s)">' . htmlspecialchars($role) . '</td>';
                                    // Status label (read-only)
                                    // Set data-user-id so presence.js events can find this element
                                    echo '<td data-label="Status" class="user-status-cell" data-user-id="' . htmlspecialchars($user['id'] ?? '') . '">';
                                    echo '<span class="status-indicator" style="display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; background-color:#ccc;"></span>';
                                    echo '<span class="status-text">' . htmlspecialchars(ucfirst($statusVal)) . '</span>';
                                    echo '</td>';
                                    echo '<td data-label="Company">' . htmlspecialchars($companyName) . '</td>';
                                    echo '<td data-label="Actions"><a href="' . $viewPage . '?id=' . urlencode($user['id'] ?? '') . '" class="action-btn view-btn">View</a></td>';
                                    echo '</tr>';
                                }
                            }
                        } catch (Exception $e) {
                            echo '<tr><td colspan="6" class="text-center">Error fetching users: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-container" style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">
                <a href="?page=1<?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage == 1 ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage == 1 ? 'onclick="return false;"' : '' ?>>
                    <i class="fas fa-chevron-left"></i> First
                </a>
                
                <a href="?page=<?= max(1, $currentPage - 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage == 1 ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage == 1 ? 'onclick="return false;"' : '' ?>>
                    <i class="fas fa-chevron-left"></i> Previous
                </a>

                <div class="page-numbers" style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <?php
                    // Show page numbers (max 7 visible)
                    $startPage = max(1, $currentPage - 3);
                    $endPage = min($totalPages, $startPage + 6);
                    if ($endPage - $startPage < 6) {
                        $startPage = max(1, $endPage - 6);
                    }
                    
                    for ($page = $startPage; $page <= $endPage; $page++):
                    ?>
                        <a href="?page=<?= $page ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : '' ?>"
                           class="page-number <?= $page == $currentPage ? 'active' : '' ?>"
                           <?= $page == $currentPage ? 'onclick="return false;"' : '' ?>>
                            <?= $page ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <a href="?page=<?= min($totalPages, $currentPage + 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                    Next <i class="fas fa-chevron-right"></i>
                </a>

                <a href="?page=<?= $totalPages ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['role']) ? '&role=' . urlencode($_GET['role']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                    Last <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </main>

    </div>

    <!-- Include admin scripts first -->
    <script>
        // Function to display a custom message box instead of alert()
        function showMessageBox(message) {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;
            overlay.id = 'messageBoxOverlay';

            const messageBox = document.createElement('div');
            messageBox.style.cssText = `
                background-color: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 90%;
                width: 400px;
            `;

            const messageParagraph = document.createElement('p');
            messageParagraph.textContent = message;
            messageParagraph.style.cssText = `
                font-size: 1.25rem;
                margin-bottom: 1.5rem;
                color: #333;
            `;

            const closeButton = document.createElement('button');
            closeButton.textContent = 'OK';
            closeButton.style.cssText = `
                background-color: #3b82f6; /* Blue-600 */
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
            closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }

        // "View" link functionality (for each user)
        document.querySelectorAll('.view-btn').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default link behavior
                // Use the href from the link element instead of hardcoding
                window.location.href = link.getAttribute('href');
            });
        });

        // Basic Search and Filter functionality (client-side for demo)
        const searchUsersInput = document.getElementById('searchUsers');
        const filterRoleSelect = document.getElementById('filterRole');

        // Fetch roles from server and populate the role dropdown; fallback to defaults on failure
        (function loadRoles() {
            fetch('../api/get_roles.php')
                .then(r => r.json())
                .then(data => {
                    // clear except first placeholder
                    while (filterRoleSelect.options.length > 1) filterRoleSelect.remove(1);
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(role => {
                            const opt = document.createElement('option');
                            opt.value = role.name;
                            // Format role name nicely (capitalize first letter)
                            const displayName = role.name.charAt(0).toUpperCase() + role.name.slice(1);
                            opt.textContent = displayName;
                            // preserve selection from query string (case-insensitive)
                            const currentRole = <?php echo json_encode($_GET['role'] ?? ''); ?>;
                            if (currentRole && currentRole.toLowerCase() === role.name.toLowerCase()) {
                                opt.selected = true;
                            }
                            filterRoleSelect.appendChild(opt);
                        });
                    } else {
                        console.warn('No roles returned from server');
                        // Fallback to common roles
                        const hardcoded = ['admin','company','outlet','driver','customer'];
                        hardcoded.forEach(r => {
                            const opt = document.createElement('option');
                            opt.value = r;
                            opt.textContent = r.charAt(0).toUpperCase() + r.slice(1);
                            const currentRole = <?php echo json_encode($_GET['role'] ?? ''); ?>;
                            if (currentRole && currentRole.toLowerCase() === r.toLowerCase()) {
                                opt.selected = true;
                            }
                            filterRoleSelect.appendChild(opt);
                        });
                    }
                })
                .catch(err => {
                    console.error('Failed to load roles, using fallback', err);
                    const hardcoded = ['admin','company','outlet','driver','customer'];
                    hardcoded.forEach(r => {
                        const opt = document.createElement('option');
                        opt.value = r;
                        opt.textContent = r.charAt(0).toUpperCase() + r.slice(1);
                        const currentRole = <?php echo json_encode($_GET['role'] ?? ''); ?>;
                        if (currentRole && currentRole.toLowerCase() === r.toLowerCase()) {
                            opt.selected = true;
                        }
                        filterRoleSelect.appendChild(opt);
                    });
                });
        })();


        
        const usersTableBody = document.getElementById('usersTableBody');
        const allUserRows = Array.from(usersTableBody.querySelectorAll('tr')); // Store all rows initially

        function applyFilters() {
            const searchTerm = searchUsersInput.value.trim();
            const selectedRole = filterRoleSelect.value.trim();
            
            const url = new URL(window.location.href);
            url.searchParams.set('page', '1'); // Reset to page 1 on filter change
            
            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }
            
            if (selectedRole) {
                url.searchParams.set('role', selectedRole);
            } else {
                url.searchParams.delete('role');
            }
            
            console.log('Applying filters - Search:', searchTerm, 'Role:', selectedRole);
            window.location.href = url.toString();
        }

        // Debounce search and submit on Enter; wire filter button
        let timeout = null;
        searchUsersInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(applyFilters, 500);
        });
        searchUsersInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });

        // Apply filter when role selection changes
        filterRoleSelect.addEventListener('change', applyFilters);

        // Handle status changes
        document.addEventListener('change', function(e) {
            const el = e.target;
            if (el && el.classList && el.classList.contains('status-select')) {
                const userId = el.getAttribute('data-user-id');
                const newStatus = el.value;
                fetch('../api/update_user_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: userId, status: newStatus })
                }).then(r => r.json()).then(json => {
                    if (!json.success) {
                        showMessageBox('Failed to update status: ' + (json.message || '')); 
                    }
                }).catch(err => {
                    showMessageBox('Failed to update status');
                });
            }
        });

        // No need for initial filterUsers() call as PHP handles rendering filtered data

        // Real-time Presence Updates
        document.addEventListener('presence_sync', function(e) {
            const onlineUsers = e.detail; // Map of userId -> userData
            
            // Find all status cells on the page
            const statusCells = document.querySelectorAll('.user-status-cell');
            
            statusCells.forEach(cell => {
                const userId = cell.getAttribute('data-user-id');
                const indicator = cell.querySelector('.status-indicator');
                const textSpan = cell.querySelector('.status-text');
                
                if (indicator && textSpan) {
                    if (onlineUsers.hasOwnProperty(userId)) {
                        // User is online!
                        indicator.style.backgroundColor = '#22c55e'; // Green
                        textSpan.textContent = 'Online';
                        textSpan.style.color = '#15803d'; // Darker green text
                        textSpan.style.fontWeight = 'bold';
                    } else {
                        // User is offline!
                        indicator.style.backgroundColor = '#9ca3af'; // Gray
                        textSpan.textContent = 'Offline';
                        textSpan.style.color = '';
                        textSpan.style.fontWeight = 'normal';
                    }
                }
            });
        });

        // Menu functionality (guarded attachments)
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');

        // === Create Role Modal ===
        const createRoleModal = document.getElementById('createRoleModal');
        const createRoleBtn = document.getElementById('createRoleBtn');
        const closeRoleModal = document.getElementById('closeRoleModal');
        const createRoleForm = document.getElementById('createRoleForm');
        const roleNameInput = document.getElementById('roleName');
        const roleNameError = document.getElementById('roleNameError');
        const roleSubmitBtn = document.getElementById('roleSubmitBtn');

        // Open modal
        createRoleBtn.addEventListener('click', () => {
            createRoleModal.style.display = 'flex';
            roleNameInput.focus();
        });

        // Close modal
        closeRoleModal.addEventListener('click', () => {
            createRoleModal.style.display = 'none';
            createRoleForm.reset();
            clearRoleErrors();
        });

        createRoleModal.addEventListener('click', (e) => {
            if (e.target === createRoleModal) {
                createRoleModal.style.display = 'none';
                createRoleForm.reset();
                clearRoleErrors();
            }
        });

        function clearRoleErrors() {
            roleNameError.style.display = 'none';
            roleNameError.textContent = '';
            roleNameInput.classList.remove('input-error');
        }

        function showRoleError(msg) {
            roleNameError.textContent = msg;
            roleNameError.style.display = 'block';
            roleNameInput.classList.add('input-error');
        }

        createRoleForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearRoleErrors();

            const name = roleNameInput.value.trim();
            if (!name) {
                showRoleError('Please enter a role name.');
                roleNameInput.focus();
                return;
            }

            roleSubmitBtn.disabled = true;
            roleSubmitBtn.textContent = 'Creating...';

            try {
                const res = await fetch('./api/create_role.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: name,
                        description: document.getElementById('roleDescription').value.trim()
                    })
                });
                const json = await res.json();

                if (!res.ok || !json.success) {
                    // Show field-level error if available
                    if (json.errors && json.errors.name) {
                        showRoleError(json.errors.name);
                    } else {
                        showRoleError(json.error || 'Failed to create role.');
                    }
                    return;
                }

                // Success
                createRoleModal.style.display = 'none';
                createRoleForm.reset();
                Swal.fire({
                    icon: 'success',
                    title: 'Role Created',
                    text: 'The role "' + name + '" has been created successfully.',
                    confirmButtonColor: '#2E0D2A'
                }).then(() => {
                    location.reload();
                });
            } catch (err) {
                console.error('Create role error:', err);
                showRoleError('An unexpected error occurred. Please try again.');
            } finally {
                roleSubmitBtn.disabled = false;
                roleSubmitBtn.textContent = 'Create Role';
            }
        });


        // Log initial state for debugging
        console.log('Users page loaded. Current role filter:', <?php echo json_encode($_GET['role'] ?? 'none'); ?>);
        console.log('Current search filter:', <?php echo json_encode($_GET['search'] ?? 'none'); ?>);
    </script>

    <style>
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            color: #374151;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .action-btn:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid #5a2d7a;
            background-color: #2E0D2A;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .view-btn:hover {
            background-color: #4A1C40;
            border-color: #6b3a8f;
        }

        .action-btn i {
            font-size: 12px;
        }
    </style>
</body>
</html>

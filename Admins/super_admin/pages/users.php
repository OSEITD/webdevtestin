<?php
session_start();
require_once '../api/supabase-client.php';

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
    $allUsers = callSupabase('all_users?select=*');
    $totalUsers = count($allUsers);
    $totalPages = ceil($totalUsers / $itemsPerPage);
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Get users for current page
    $users = array_slice($allUsers, $offset, $itemsPerPage);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $totalPages = 1;
}

$pageTitle = 'Admin - user';
require_once '../includes/header.php';
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
        <!-- User Type Selection Modal -->
        <div id="userTypeModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Select User Type</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="user-type-grid">
                    <div class="user-type-card" data-type="admin">
                        <i class="fas fa-user-shield"></i>
                        <span>Add Admin</span>
                    </div>
                    <div class="user-type-card" data-type="driver">
                        <i class="fas fa-truck"></i>
                        <span>Add Driver</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area for All Users -->
        <main class="main-content">
            <div class="content-header">
                <h1>All Users</h1>
                <button class="add-btn" id="addUserBtn">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchUsers" placeholder="Search by name">
                </div>
                <div class="filter-dropdown">
                    <select id="filterRole">
                        <option value="">Role</option>
                        <option value="Admin">Admin</option>
                        <option value="Company">Company</option>
                        <option value="Outlet">Outlet</option>
                        <option value="Driver">Driver</option>
                        <option value="Customer">Customer</option>
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
                                    $status_class = isset($user['status']) && strtolower($user['status']) === 'active' ? 'status-active' : 'status-suspended';
                                    
                                    // Determine the view page based on role
                                    $role = strtolower($user['role'] ?? '');
                                    
                                    // Map role to specific view page
                                    if ($role === 'company') {
                                        $viewPage = 'view-company.php';
                                    } elseif ($role === 'outlet') {
                                        $viewPage = 'view-outlet.php';
                                    } elseif ($role === 'driver') {
                                        $viewPage = 'view-driver.php';
                                    } else {
                                        // Skip other roles or handle as needed
                                        continue;
                                    }
                                    
                                    echo '<tr>';
                                    echo '<td data-label="Name">' . htmlspecialchars($user['name'] ?? 'N/A') . '</td>';

                                    echo '<td data-label="Role(s)">' . htmlspecialchars($user['role'] ?? 'N/A') . '</td>';
                                    echo '<td data-label="Status"><span class="status-badge ' . $status_class . '">' . htmlspecialchars($user['status'] ?? 'N/A') . '</span></td>';
                                    echo '<td data-label="Company/Outlet">' . htmlspecialchars($user['associated_entity'] ?? 'N/A') . '</td>';
                                    echo '<td data-label="Actions"><a href="' . $viewPage . '?email=' . urlencode($user['contact_email'] ?? '') . '" class="action-link view-details-link">View</a></td>';
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
            <div class="pagination-container" style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
                <a href="?page=1<?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage == 1 ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage == 1 ? 'onclick="return false;"' : '' ?>>
                    <i class="fas fa-chevron-left"></i> First
                </a>
                
                <a href="?page=<?= max(1, $currentPage - 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
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
                        <a href="?page=<?= $page ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"
                           class="page-number"
                           style="<?= $page == $currentPage ? 'background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;' : 'padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;' ?>"
                           <?= $page == $currentPage ? 'onclick="return false;"' : '' ?>>
                            <?= $page ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <a href="?page=<?= min($totalPages, $currentPage + 1) ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
                   class="pagination-btn" 
                   style="<?= $currentPage >= $totalPages ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>"
                   <?= $currentPage >= $totalPages ? 'onclick="return false;"' : '' ?>>
                    Next <i class="fas fa-chevron-right"></i>
                </a>

                <a href="?page=<?= $totalPages ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" 
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
        document.querySelectorAll('.view-details-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default link behavior
                // Use the href from the link element instead of hardcoding
                window.location.href = link.getAttribute('href');
            });
        });

        // Basic Search and Filter functionality (client-side for demo)
        const searchUsersInput = document.getElementById('searchUsers');
        const filterRoleSelect = document.getElementById('filterRole');

        // User Type Modal Functionality
        const modal = document.getElementById('userTypeModal');
        const addUserBtn = document.getElementById('addUserBtn');
        
        // Show modal when clicking Add User button
        addUserBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        // Close modal when clicking the close button
        document.querySelector('.modal-close').addEventListener('click', () => {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Handle user type card clicks
        document.querySelectorAll('.user-type-card').forEach(card => {
            card.addEventListener('click', () => {
                const userType = card.getAttribute('data-type');
                if (!userType) return;
                
                const typeToPage = {
                    'admin': 'add-admin.php',
                    'driver': 'add-driver.php'
                };
                
                const targetPage = typeToPage[userType];
                if (targetPage) {
                    window.location.href = targetPage;
                }
            });
        });
        
        const usersTableBody = document.getElementById('usersTableBody');
        const allUserRows = Array.from(usersTableBody.querySelectorAll('tr')); // Store all rows initially

        function filterUsers() {
            const searchTerm = searchUsersInput.value.toLowerCase();
            const selectedRole = filterRoleSelect.value.toLowerCase();

            usersTableBody.innerHTML = ''; // Clear current table body

            allUserRows.forEach(row => {
                const name = row.querySelector('td[data-label="Name"]').textContent.toLowerCase();
                const role = row.querySelector('td[data-label="Role(s)"]').textContent.toLowerCase();

                const matchesSearch = name.includes(searchTerm);
                const matchesRole = selectedRole === '' || role.includes(selectedRole);

                if (matchesSearch && matchesRole) {
                    usersTableBody.appendChild(row);
                }
            });
        }

        searchUsersInput.addEventListener('input', filterUsers);
        filterRoleSelect.addEventListener('change', filterUsers);

        // Initial filter on page load (to apply any default selections)
        filterUsers();

        // Menu functionality (guarded attachments)
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
    </script>
</body>
</html>

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

// Fetch outlets data from Supabase with company information
try {
    $allOutlets = callSupabaseWithServiceKey('outlets?select=*,companies(company_name)', 'GET');
    $totalOutlets = count($allOutlets);
    $totalPages = ceil($totalOutlets / $itemsPerPage);
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Get outlets for current page
    $outlets = array_slice($allOutlets, $offset, $itemsPerPage);
} catch (Exception $e) {
    error_log("Error fetching outlets: " . $e->getMessage());
    $outlets = [];
    $totalOutlets = 0;
    $totalPages = 1;
}

$pageTitle = 'Admin - Outlets';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Outlets -->
        <main class="main-content">
            <div class="content-header">
                <h1>Outlets</h1>
                <button class="add-btn" id="addOutletBtn">
                    <i class="fas fa-plus-circle"></i> Add Outlet
                </button>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOutlets" placeholder="Search outlets">
                </div>
                <!-- Optional: Add a filter dropdown for Company here if needed -->
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Outlet Name</th>
                            <th>Company</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="outletsTableBody">
                    <?php if (empty($outlets)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No outlets found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outlets as $outlet): ?>
                        <tr>
                            <td data-label="Outlet Name"><?php echo htmlspecialchars($outlet['outlet_name']); ?></td>
                            <td data-label="Company"><?php echo htmlspecialchars($outlet['companies']['company_name']); ?></td>
                            <td data-label="Address"><?php echo htmlspecialchars($outlet['address']); ?></td>
                            <td data-label="Contact"><?php echo htmlspecialchars($outlet['contact_phone']); ?></td>
                            <td data-label="Status">
                                <span class="status-badge <?php echo ($outlet['status'] === 'active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($outlet['status'])); ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <a href="view-outlet.php?id=<?php echo htmlspecialchars($outlet['id']); ?>" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
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

        // Add Outlet Button functionality
        document.getElementById('addOutletBtn').addEventListener('click', () => {
            window.location.href = 'add-outlet.php';
        });

        // Action links functionality (View)
        // Remove the click event listener since we're using direct links now
        // The href in the HTML will handle navigation

        document.querySelectorAll('.edit-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const outletName = e.target.closest('tr').querySelector('td[data-label="Outlet Name"]').textContent;
                showMessageBox(`Editing outlet: ${outletName} (Functionality not implemented)`);
                // In a real application, this would navigate to an "Edit Outlet" form page.
            });
        });

        document.querySelectorAll('.deactivate-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const outletName = e.target.closest('tr').querySelector('td[data-label="Outlet Name"]').textContent;
                showMessageBox(`Deactivating outlet: ${outletName} (Functionality not implemented)`);
                // In a real application, this would send an API request to change status.
            });
        });

        document.querySelectorAll('.activate-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const outletName = e.target.closest('tr').querySelector('td[data-label="Outlet Name"]').textContent;
                showMessageBox(`Activating outlet: ${outletName} (Functionality not implemented)`);
                // In a real application, this would send an API request to change status.
            });
        });

        // Menu functionality handled by admin-scripts.js

        // Basic Search functionality (client-side for demo)
        const searchOutletsInput = document.getElementById('searchOutlets');
        const outletsTableBody = document.getElementById('outletsTableBody');
        const allOutletRows = Array.from(outletsTableBody.querySelectorAll('tr')); // Store all rows initially

        function filterOutlets() {
            const searchTerm = searchOutletsInput.value.toLowerCase();
            outletsTableBody.innerHTML = ''; // Clear current table body

            allOutletRows.forEach(row => {
                const outletName = row.querySelector('td[data-label="Outlet Name"]').textContent.toLowerCase();
                const company = row.querySelector('td[data-label="Company"]').textContent.toLowerCase();
                const address = row.querySelector('td[data-label="Address"]').textContent.toLowerCase();
                const contact = row.querySelector('td[data-label="Contact"]').textContent.toLowerCase();

                if (outletName.includes(searchTerm) || company.includes(searchTerm) || address.includes(searchTerm) || contact.includes(searchTerm)) {
                    outletsTableBody.appendChild(row);
                }
            });
        }

        searchOutletsInput.addEventListener('input', filterOutlets);

        // Initial filter on page load
        filterOutlets();
    </script>
</body>
</html>

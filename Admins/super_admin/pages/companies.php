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

try {
    // Fetch all companies to get total count
    $allCompanies = callSupabase('companies?select=*');
    $totalCompanies = count($allCompanies);
    $totalPages = ceil($totalCompanies / $itemsPerPage);
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Get companies for current page
    $companies = array_slice($allCompanies, $offset, $itemsPerPage);
} catch (Exception $e) {
    error_log('Error fetching companies: ' . $e->getMessage());
    $companies = [];
    $totalCompanies = 0;
    $totalPages = 1;
}
$pageTitle = 'Admin - Companies';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Companies -->
        <main class="main-content">
            <div class="content-header">
                <h1>Delivery Companies</h1>
                <a href="add-company.php" class="add-btn" id="addCompanyBtn" style="text-decoration: none;">
                    <i class="fas fa-plus-circle"></i> Add Company
                </a>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchCompanies" placeholder="Search companies">
                </div>
                <!-- for future dropdown for companies  -->
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                         <tr>
        <th>#</th>
        <th>Name</th>
        <th>Revenue</th>
        <th>Mananger</th>
        <th>Email</th>
        <th>Status</th>
      </tr>
                    </thead>
                    <tbody>
            <?php foreach ($companies as $index => $company): ?>
                <tr data-company-id="<?= htmlspecialchars($company['id'] ?? '') ?>">
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($company['company_name'] ?? '') ?></td>
                    <td>$<?= number_format($company['revenue'] ?? 0, 2) ?></td>
          <td><?= htmlspecialchars($company['contact_person'] ?? '') ?></td>
          <td><?= htmlspecialchars($company['contact_email'] ?? '') ?></td>
          <td style="color: <?php 
            $status = strtolower($company['status'] ?? '');
            if ($status === 'active') {
                echo 'green';
            } elseif ($status === 'suspended') {
                echo 'red';
            } else {
                echo 'inherit';
            }
          ?>">
            <?= htmlspecialchars($company['status'] ?? '') ?>
          </td>
        </tr>
      <?php endforeach; ?>
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

   <script>
        // Add Company Button functionality is now handled by the HTML link

        // Make table rows clickable to view company details
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', () => {
                // Get the company data from the data attributes
                const companyId = row.getAttribute('data-company-id');
                window.location.href = `view-company.php?id=${encodeURIComponent(companyId)}`;
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchCompanies');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                document.querySelectorAll('.data-table tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
                
                // Update pagination display
                updatePaginationDisplay(searchTerm);
            });
        }

        function updatePaginationDisplay(searchTerm) {
            const rows = document.querySelectorAll('.data-table tbody tr');
            const visibleCount = Array.from(rows).filter(row => row.style.display !== 'none').length;
            
            // Show/hide pagination based on search results
            const paginationContainer = document.querySelector('.pagination-container');
            if (paginationContainer) {
                if (searchTerm.length > 0 && visibleCount < 25) {
                    paginationContainer.style.display = 'none';
                } else {
                    paginationContainer.style.display = 'flex';
                }
            }
        }

        // Menu functionality handled by admin-scripts.js

        // Add hover effect to table rows
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = '#f3f4f6';
            });
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = '';
            });
        });
    </script>
</body>
</html>

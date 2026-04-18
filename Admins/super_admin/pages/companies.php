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

try {
    // Get total count of non-deleted companies using optimized exact count header
    $countEndpoint = 'companies?deleted_at=is.null';
    
    // Apply search filter if present
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = urlencode('%' . $_GET['search'] . '%');
        $countEndpoint .= "&or=(company_name.ilike.{$search},contact_email.ilike.{$search},contact_person.ilike.{$search})";
    }
    
    $totalCompanies = callSupabaseCount($countEndpoint);
    
    $totalPages = max(1, ceil($totalCompanies / $itemsPerPage));
    
    // Ensure current page is within valid range
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
    }
    
    // Build query to fetch paginated companies
    $endpoint = 'companies?deleted_at=is.null&select=*&order=id.desc';
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = urlencode('%' . $_GET['search'] . '%');
        $endpoint .= "&or=(company_name.ilike.{$search},contact_email.ilike.{$search},contact_person.ilike.{$search})";
    }
    $endpoint .= "&limit={$itemsPerPage}&offset={$offset}";
    
    $companiesRes = callSupabase($endpoint);
    $companies = is_array($companiesRes) ? $companiesRes : [];
} catch (Exception $e) {
    error_log('Error fetching companies: ' . $e->getMessage());
    $companies = [];
    $totalCompanies = 0;
    $totalPages = 1;
}
$pageTitle = 'Admin - Companies';
require_once __DIR__ . '/../includes/header.php';
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

        <th>Name</th>
        <th>Mananger</th>
        <th>Email</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
                    </thead>
                    <tbody>
            <?php foreach ($companies as $index => $company): ?>
                <tr data-company-id="<?= htmlspecialchars($company['id'] ?? '') ?>">

                    <td data-label="Name"><?= htmlspecialchars($company['company_name'] ?? '') ?></td>

          <td data-label="Manager"><?= htmlspecialchars($company['contact_person'] ?? '') ?></td>
          <td data-label="Email"><?= htmlspecialchars($company['contact_email'] ?? '') ?></td>
          <td data-label="Status">
            <?php
                $companyStatus = strtolower($company['status'] ?? 'inactive');
                $companyStatusClass = 'status-inactive';
                if ($companyStatus === 'active') $companyStatusClass = 'status-active';
                elseif ($companyStatus === 'suspended') $companyStatusClass = 'status-suspended';
            ?>
            <span class="status-badge <?= $companyStatusClass ?>">
                <?= ucfirst(htmlspecialchars($companyStatus)) ?>
            </span>
          </td>
          <td data-label="Action">
            <button class="action-btn view-btn" data-company-id="<?= htmlspecialchars($company['id'] ?? '') ?>" title="View company details">
              View
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>

                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-container" style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">
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
                           class="page-number <?= $page == $currentPage ? 'active' : '' ?>"
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
        // View button functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const companyId = btn.getAttribute('data-company-id');
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

        // Auto-refresh page every 30 seconds to check for new companies
        const autoRefreshInterval = setInterval(() => {
            location.reload();
        }, 30000); // 30 seconds
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
<?php include __DIR__ . '/../includes/footer.php'; ?>

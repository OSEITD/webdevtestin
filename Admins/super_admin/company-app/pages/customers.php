<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Company - Outlets</title>
    <!-- Poppins Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS file -->
    <link rel="stylesheet" href="../assets/css/company.css">
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="header-content">
                <img src="../assets/images/Logo.png" alt="SwiftShip" class="app-logo">
                <div class="header-icons">
                    <button class="icon-btn search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="icon-btn notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </button>
                    <button class="icon-btn menu-btn" id="menuBtn">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Overlay for sidebar -->

        <!-- Main Content Area for Outlets -->
        <main class="main-content">
            <div class="content-header">
                <h1>Outlets</h1>
                <button class="add-btn" id="addOutletBtn">
                    <i class="fas fa-plus-circle"></i> New Outlet
                </button>
            </div>

            <div class="filter-bar">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOutlets" placeholder="Search outlets">
                </div>
                <div class="filter-dropdown">
                    <select id="filterStatus">
                        <option value="">Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table outlets-table">
                    <thead>
                        <tr>
                            <th>Outlet Name</th>
                            <th>Address</th>
                            <th>Contact Person</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="outletsTableBody">
                        <tr>
                            <td data-label="Outlet Name">Main Street Outlet</td>
                            <td data-label="Address">123 Main St, Anytown, USA</td>
                            <td data-label="Contact Person">Alice Johnson</td>
                            <td data-label="Status"><span class="status-badge status-active">Active</span></td>
                            <td data-label="Actions">
                                <a href="#" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Outlet Name">Downtown Depot</td>
                            </main>
                            <script>
                                (function(){
                                    const tbody = document.getElementById('outletsTableBody');
                                    if (!tbody) return;
                                    const rows = Array.from(tbody.querySelectorAll('tr'));
                                    const itemsPerPage = 25;
                                    let currentPage = 1;

                                    function renderPage() {
                                        const total = rows.length;
                                        const totalPages = Math.max(1, Math.ceil(total / itemsPerPage));
                                        if (currentPage > totalPages) currentPage = totalPages;
                                        const start = (currentPage -1) * itemsPerPage;
                                        rows.forEach((r,i) => r.style.display = (i>=start && i< start + itemsPerPage) ? '' : 'none');
                                        renderPagination(total);
                                    }

                                    function renderPagination(totalItems){
                                        const container = document.getElementById('customersPagination');
                                        if (!container) return;
                                        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
                                        let html = '';
                                        html += `<a href='?page=1' class='pagination-btn' style='${currentPage===1?"opacity: 0.5; cursor: not-allowed;":""}' ${currentPage===1?"onclick=\"return false;\"":"onclick=\"currentPage=1;renderPage();return false;\""}><i class="fas fa-chevron-left"></i> First</a>`;
                                        html += `<a href='?page=${Math.max(1,currentPage-1)}' class='pagination-btn' style='${currentPage===1?"opacity: 0.5; cursor: not-allowed;":""}' ${currentPage===1?"onclick=\"return false;\"":`onclick=\"currentPage=${Math.max(1,currentPage-1)};renderPage();return false;\"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;
                                        const startPage = Math.max(1, currentPage -3);
                                        let endPage = Math.min(totalPages, startPage + 6);
                                        for(let p = startPage; p <= endPage; p++){
                                            if (p === currentPage) html += `<a href='?page=${p}' class='page-number' style='background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;' onclick='return false;'>${p}</a>`;
                                            else html += `<a href='?page=${p}' class='page-number' style='padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;' onclick='currentPage=${p};renderPage();return false;'>${p}</a>`;
                                        }
                                        html += `<a href='?page=${Math.min(totalPages,currentPage+1)}' class='pagination-btn' style='${currentPage>=totalPages?"opacity: 0.5; cursor: not-allowed;":""}' ${currentPage>=totalPages?"onclick=\"return false;\"":`onclick=\"currentPage=${Math.min(totalPages,currentPage+1)};renderPage();return false;\"`}>Next <i class="fas fa-chevron-right"></i></a>`;
                                        html += `<a href='?page=${totalPages}' class='pagination-btn' style='${currentPage>=totalPages?"opacity: 0.5; cursor: not-allowed;":""}' ${currentPage>=totalPages?"onclick=\"return false;\"":`onclick=\"currentPage=${totalPages};renderPage();return false;\"`}>Last <i class="fas fa-chevron-right"></i></a>`;
                                        container.innerHTML = html;
                                    }

                                    renderPage();
                                })();
                            </script>
                            <td data-label="Contact Person">Bob Williams</td>
                            <td data-label="Status"><span class="status-badge status-active">Active</span></td>
                            <td data-label="Actions">
                                <a href="#" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Outlet Name">Uptown Hub</td>
                            <td data-label="Address">789 Pine Ln, Anytown, USA</td>
                            <td data-label="Contact Person">Carol Davis</td>
                            <td data-label="Status"><span class="status-badge status-inactive">Inactive</span></td>
                            <td data-label="Actions">
                                <a href="#" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Outlet Name">Westside Center</td>
                            <td data-label="Address">101 Elm Rd, Anytown, USA</td>
                            <td data-label="Contact Person">David Miller</td>
                            <td data-label="Status"><span class="status-badge status-active">Active</span></td>
                            <td data-label="Actions">
                                <a href="#" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Outlet Name">Eastside Terminal</td>
                            <td data-label="Address">222 Maple Dr, Anytown, USA</td>
                            <td data-label="Contact Person">Eve Clark</td>
                            <td data-label="Status"><span class="status-badge status-inactive">Inactive</span></td>
                            <td data-label="Actions">
                                <a href="#" class="action-link view-details-link">View</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="customersPagination" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;"></div>
        </main>
    </div>

    <!-- Link to the external JavaScript file -->
    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>

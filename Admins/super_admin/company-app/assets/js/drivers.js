// Cache DOM elements
const driversTableBody = document.getElementById('driversTableBody');
const searchInput = document.getElementById('searchDrivers');
const filterStatus = document.getElementById('filterStatus');
const loadingSpinner = document.getElementById('loadingSpinner');
const errorMessage = document.getElementById('errorMessage');

let drivers = []; // Store all drivers for filtering
// Pagination state
let driversCurrentPage = 1;
const driversItemsPerPage = 25;

function renderDriversPagination(totalItems) {
    const container = document.getElementById('driversPagination');
    if (!container) return;
    const totalPages = Math.max(1, Math.ceil(totalItems / driversItemsPerPage));
    let html = '';
    // First
    html += `<a href="?page=1" class="pagination-btn" style="${driversCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${driversCurrentPage === 1 ? 'onclick="return false;"' : 'onclick="changeDriversPage(1);return false;"'}><i class="fas fa-chevron-left"></i> First</a>`;
    // Previous
    html += `<a href="?page=${Math.max(1, driversCurrentPage-1)}" class="pagination-btn" style="${driversCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${driversCurrentPage === 1 ? 'onclick="return false;"' : `onclick="changeDriversPage(${Math.max(1, driversCurrentPage-1)});return false;"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;

    let startPage = Math.max(1, driversCurrentPage - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if (endPage - startPage < 6) {
        startPage = Math.max(1, endPage - 6);
    }
    for (let p = startPage; p <= endPage; p++) {
        if (p === driversCurrentPage) {
            html += `<a href="?page=${p}" class="page-number" style="background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;" onclick="return false;">${p}</a>`;
        } else {
            html += `<a href="?page=${p}" class="page-number" style="padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;" onclick="changeDriversPage(${p});return false;">${p}</a>`;
        }
    }

    // Next
    html += `<a href="?page=${Math.min(totalPages, driversCurrentPage+1)}" class="pagination-btn" style="${driversCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${driversCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeDriversPage(${Math.min(totalPages, driversCurrentPage+1)});return false;"`}>Next <i class="fas fa-chevron-right"></i></a>`;
    // Last
    html += `<a href="?page=${totalPages}" class="pagination-btn" style="${driversCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${driversCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeDriversPage(${totalPages});return false;"`}>Last <i class="fas fa-chevron-right"></i></a>`;
    container.innerHTML = html;
}

function changeDriversPage(page) {
    driversCurrentPage = page;
    // Re-run filter to render correct slice
    filterDrivers();
}

// Function to format status badge
// Normalize status values (map legacy active/inactive to DB values)
function normalizeStatus(status) {
    const s = (status || '').toString().toLowerCase().trim();
    if (s === 'active') return 'available';
    if (s === 'inactive') return 'unavailable';
    // keep DB values as-is
    return s || 'unavailable';
}

// Function to format status badge
function getStatusBadge(status) {
    const s = normalizeStatus(status);
    const label = s === 'available' ? 'Available' : 'Unavailable';
    // sanitize class name
    const cls = s.replace(/[^a-z0-9_-]/g, '');
    return `<span class="status-badge status-${cls}">${label}</span>`;
}

// Function to format license info
function formatLicenseInfo(license) {
    return license || 'N/A';
}

// Function to render drivers table
function renderDrivers(driversToShow) {
    if (!driversToShow.length) {
        driversTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">No drivers found</td>
            </tr>
        `;
        return;
    }

    driversTableBody.innerHTML = driversToShow.map(driver => `
        <tr data-id="${driver.id}">
            <td data-label="Name">
                <div class="flex items-center">
                    <div class="ml-3">
                        <div class="font-medium">${driver.driver_name || 'N/A'}</div>
                    </div>
                </div>
            </td>
            <td data-label="Contact">
                <div class="text-sm">
                    <div>${driver.driver_phone || 'N/A'}</div>
                </div>
            </td>
            <td data-label="Vehicle/License">
                <div class="text-sm">
                    <div>${formatLicenseInfo(driver.license_number)}</div>
                </div>
            </td>
            <td data-label="Status">${getStatusBadge(driver.status || 'inactive')}</td>
            <td data-label="Actions">
                <div class="flex space-x-2">
                    <a href="company-view-driver.php?id=${driver.id}" class="action-link view-profile-link">
                        <i class="fas fa-eye"></i> View
                    </a>
                </div>
            </td>
        </tr>
    `).join('');
}

// (fetchDrivers defined later; DOMContentLoaded listener at bottom will run it)

// Function to filter drivers
function filterDrivers() {
    const searchTerm = (searchInput.value || '').toLowerCase();
    const rawStatusFilter = (filterStatus.value || '').toLowerCase();
    const statusFilter = rawStatusFilter === 'active' ? 'available' : (rawStatusFilter === 'inactive' ? 'unavailable' : rawStatusFilter);
    
    if (!drivers || !Array.isArray(drivers)) {
        console.error('No drivers data available');
        return;
    }

    const filtered = drivers.filter(driver => {
        const name = String(driver.driver_name ?? '').toLowerCase();
        const phone = String(driver.driver_phone ?? '').toLowerCase();
        const license = String(driver.license_number ?? '').toLowerCase();

        const matchesSearch = !searchTerm || name.includes(searchTerm) || phone.includes(searchTerm) || license.includes(searchTerm);

        const driverStatus = normalizeStatus(driver.status);
        const matchesStatus = !statusFilter || driverStatus === statusFilter;

        return matchesSearch && matchesStatus;
    });

    // paginate
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / driversItemsPerPage));
    if (driversCurrentPage > totalPages) driversCurrentPage = totalPages;
    const start = (driversCurrentPage - 1) * driversItemsPerPage;
    const slice = filtered.slice(start, start + driversItemsPerPage);
    renderDrivers(slice);
    renderDriversPagination(total);
}

// Function to fetch drivers
async function fetchDrivers() {
    try {
        if (loadingSpinner) {
            loadingSpinner.style.display = 'block';
        }
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
        
        const response = await fetch('api/fetch_drivers.php');
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.error || `HTTP error ${response.status}`);
        }
        
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Failed to fetch drivers');
        }

        drivers = result.data;
        driversCurrentPage = 1;
        filterDrivers();
        
    } catch (error) {
        console.error('Error fetching drivers:', error);
        
        if (error.message.includes('Please log in again') || error.message.includes('Session expired')) {
            // Redirect to login page if session expired
            window.location.href = '../auth/login.php';
            return;
        }
        
        if (errorMessage) {
            errorMessage.textContent = error.message;
            errorMessage.style.display = 'block';
        }
        
        driversTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <div class="text-red-600">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Error loading drivers</p>
                        <button onclick="fetchDrivers()" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-sync"></i> Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
    } finally {
        if (loadingSpinner) {
            loadingSpinner.style.display = 'none';
        }
    }
}

// Event listeners
if (searchInput) {
    searchInput.addEventListener('input', filterDrivers);
}
if (filterStatus) {
    filterStatus.addEventListener('change', filterDrivers);
}

// Fix the "Add Driver" button link
const addDriverBtn = document.getElementById('addDriverBtn');
if (addDriverBtn) {
    addDriverBtn.onclick = function() {
        window.location.href = 'company-add-driver.php';
    };
}

// Initial load
document.addEventListener('DOMContentLoaded', fetchDrivers);
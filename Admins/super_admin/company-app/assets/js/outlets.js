// Cache DOM elements
const outletsTableBody = document.getElementById('outletsTableBody');
const searchInput = document.getElementById('searchOutlets');
const filterStatus = document.getElementById('filterStatus');
const loadingSpinner = document.getElementById('loadingSpinner');
const errorMessage = document.getElementById('errorMessage');

let outlets = []; // Store all outlets for filtering
// Pagination state
let outletsCurrentPage = 1;
const outletsItemsPerPage = 25;

function renderOutletsPagination(totalItems) {
    const container = document.getElementById('outletsPagination');
    if (!container) return;
    const totalPages = Math.max(1, Math.ceil(totalItems / outletsItemsPerPage));
    let html = '';
    html += `<a href="?page=1" class="pagination-btn" style="${outletsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${outletsCurrentPage === 1 ? 'onclick="return false;"' : 'onclick="changeOutletsPage(1);return false;"'}><i class="fas fa-chevron-left"></i> First</a>`;
    html += `<a href="?page=${Math.max(1, outletsCurrentPage-1)}" class="pagination-btn" style="${outletsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${outletsCurrentPage === 1 ? 'onclick="return false;"' : `onclick="changeOutletsPage(${Math.max(1, outletsCurrentPage-1)});return false;"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;
    let startPage = Math.max(1, outletsCurrentPage - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if (endPage - startPage < 6) {
        startPage = Math.max(1, endPage - 6);
    }
    for (let p = startPage; p <= endPage; p++) {
        if (p === outletsCurrentPage) html += `<a href="?page=${p}" class="page-number" style="background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;" onclick="return false;">${p}</a>`;
        else html += `<a href="?page=${p}" class="page-number" style="padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;" onclick="changeOutletsPage(${p});return false;">${p}</a>`;
    }
    html += `<a href="?page=${Math.min(totalPages, outletsCurrentPage+1)}" class="pagination-btn" style="${outletsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${outletsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeOutletsPage(${Math.min(totalPages, outletsCurrentPage+1)});return false;"`}>Next <i class="fas fa-chevron-right"></i></a>`;
    html += `<a href="?page=${totalPages}" class="pagination-btn" style="${outletsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${outletsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeOutletsPage(${totalPages});return false;"`}>Last <i class="fas fa-chevron-right"></i></a>`;
    container.innerHTML = html;
}

function changeOutletsPage(page) {
    outletsCurrentPage = page;
    filterOutlets();
}

// Function to format status badge
function getStatusBadge(status) {
    return `<span class="status-badge status-${status.toLowerCase()}">${status}</span>`;
}

// Function to render outlets table
function renderOutlets(outletsToShow) {
    if (!Array.isArray(outletsToShow) || !outletsToShow.length) {
        outletsTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">No outlets found</td>
            </tr>
        `;
        return;
    }

    outletsTableBody.innerHTML = outletsToShow.map(outlet => `
        <tr data-id="${outlet.id || ''}">
            <td data-label="Outlet Name">${outlet.outlet_name || 'N/A'}</td>
            <td data-label="Address">${outlet.address || 'N/A'}</td>
            <td data-label="Contact Person">${outlet.contact_person || 'N/A'}</td>
            <td data-label="Status">${getStatusBadge(outlet.status || 'inactive')}</td>
            <td data-label="Actions">
                <a href="company-view-outlet.php?id=${outlet.id}" class="action-link view-details-link">
                    <i class="fas fa-eye"></i> View
                </a>
            </td>
        </tr>
    `).join('');
}

// Function to filter outlets
function filterOutlets() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusFilter = filterStatus.value.toLowerCase();

    const filtered = outlets.filter(outlet => {
        const matchesSearch = (outlet.outlet_name || '').toLowerCase().includes(searchTerm) ||
                            (outlet.address || '').toLowerCase().includes(searchTerm) ||
                            (outlet.contact_person || '').toLowerCase().includes(searchTerm);
        
        const matchesStatus = !statusFilter || (outlet.status || '').toLowerCase() === statusFilter;
        
        return matchesSearch && matchesStatus;
    });

    // paginate
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / outletsItemsPerPage));
    if (outletsCurrentPage > totalPages) outletsCurrentPage = totalPages;
    const start = (outletsCurrentPage - 1) * outletsItemsPerPage;
    const slice = filtered.slice(start, start + outletsItemsPerPage);
    renderOutlets(slice);
    renderOutletsPagination(total);
}

// Function to fetch outlets
async function fetchOutlets() {
    try {
        loadingSpinner.style.display = 'block';
        errorMessage.style.display = 'none';
        
        const response = await fetch('../api/fetch_outlets.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin' // Include cookies for session
        });
        
        if (!response.ok) {
            const errorResult = await response.json();
            throw new Error(errorResult.error || `HTTP error ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result); // Debug log
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to fetch outlets');
        }

        if (!Array.isArray(result.data)) {
            throw new Error('Invalid data format received from server');
        }

        outlets = result.data;
        outletsCurrentPage = 1;
        filterOutlets();
        
    } catch (error) {
        console.error('Error fetching outlets:', error);
        
        if (error.message.includes('Please log in again')) {
            // Redirect to login page if session expired
            window.location.href = '../auth/login.php';
            return;
        }
        
        errorMessage.textContent = error.message;
        errorMessage.style.display = 'block';
        outletsTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">Error loading outlets</td>
            </tr>
        `;
    } finally {
        loadingSpinner.style.display = 'none';
    }
}

// Event listeners
searchInput.addEventListener('input', filterOutlets);
filterStatus.addEventListener('change', filterOutlets);

// Fix the "Add Outlet" button link
document.getElementById('addOutletBtn').onclick = function() {
    window.location.href = 'company-add-outlet.php';
};

// Initial load
document.addEventListener('DOMContentLoaded', fetchOutlets);
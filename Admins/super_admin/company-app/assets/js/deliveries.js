// Utility function to escape HTML and prevent XSS
function escapeHtml(unsafe) {
    if (unsafe == null) return '';
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Global state for filters
const currentFilters = {
    status: '',
    dateRange: '',
    search: '',
    driver_id: '',
    outlet_id: ''
};

// Pagination for deliveries
let parcelsCurrentPage = 1;
const parcelsItemsPerPage = 25;

function renderDeliveriesPagination(totalItems) {
    const container = document.getElementById('deliveriesPagination');
    if (!container) return;
    const totalPages = Math.max(1, Math.ceil(totalItems / parcelsItemsPerPage));
    let html = '';
    html += `<a href="?page=1" class="pagination-btn" style="${parcelsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${parcelsCurrentPage === 1 ? 'onclick="return false;"' : 'onclick="changeParcelsPage(1);return false;"'}><i class="fas fa-chevron-left"></i> First</a>`;
    html += `<a href="?page=${Math.max(1, parcelsCurrentPage - 1)}" class="pagination-btn" style="${parcelsCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${parcelsCurrentPage === 1 ? 'onclick="return false;"' : `onclick="changeParcelsPage(${Math.max(1, parcelsCurrentPage - 1)});return false;"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;
    let startPage = Math.max(1, parcelsCurrentPage - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if (endPage - startPage < 6) {
        startPage = Math.max(1, endPage - 6);
    }
    for (let p = startPage; p <= endPage; p++) {
        if (p === parcelsCurrentPage) html += `<a href="?page=${p}" class="page-number" style="background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;" onclick="return false;">${p}</a>`;
        else html += `<a href="?page=${p}" class="page-number" style="padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;" onclick="changeParcelsPage(${p});return false;">${p}</a>`;
    }
    html += `<a href="?page=${Math.min(totalPages, parcelsCurrentPage + 1)}" class="pagination-btn" style="${parcelsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${parcelsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeParcelsPage(${Math.min(totalPages, parcelsCurrentPage + 1)});return false;"`}>Next <i class="fas fa-chevron-right"></i></a>`;
    html += `<a href="?page=${totalPages}" class="pagination-btn" style="${parcelsCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${parcelsCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeParcelsPage(${totalPages});return false;"`}>Last <i class="fas fa-chevron-right"></i></a>`;
    container.innerHTML = html;
}

function changeParcelsPage(page) {
    parcelsCurrentPage = page;
    // Fetch directly from server with new page filter
    loadParcels();
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    // Populate driver and outlet selects, then load parcels
    loadDrivers();
    loadOutlets();
    loadParcels();
});

// Load drivers to populate the driver filter select
async function loadDrivers() {
    const select = document.getElementById('filterDriver');
    if (!select) return;
    try {
        const resp = await fetch('../api/fetch_drivers.php', {
            credentials: 'include'
        });
        if (!resp.ok) throw new Error('Failed to fetch drivers');
        const json = await resp.json();
        if (!json.success) throw new Error(json.error || 'API error');
        const drivers = json.data || [];
        // Clear and add default option
        select.innerHTML = '<option value="">Driver</option>' + drivers.map(d => {
            const name = d.driver_name || d.driver_email || ('Driver ' + d.id);
            return `<option value="${escapeHtml(d.id)}">${escapeHtml(name)}</option>`;
        }).join('');
    } catch (err) {
        console.error('Error loading drivers:', err);
    }
}

// Load outlets to populate the outlet filter select
async function loadOutlets() {
    const select = document.getElementById('filterOutlet');
    if (!select) return;
    try {
        const resp = await fetch('../api/fetch_outlets.php', {
            credentials: 'include'
        });
        if (!resp.ok) throw new Error('Failed to fetch outlets');
        const json = await resp.json();
        if (!json.success) throw new Error(json.error || 'API error');
        const outlets = json.data || [];
        select.innerHTML = '<option value="">Outlet</option>' + outlets.map(o => {
            const name = o.outlet_name || ('Outlet ' + o.id);
            return `<option value="${escapeHtml(o.id)}">${escapeHtml(name)}</option>`;
        }).join('');
    } catch (err) {
        console.error('Error loading outlets:', err);
    }
}

function setupEventListeners() {
    // Search input with debounce
    const searchInput = document.getElementById('searchDeliveries');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentFilters.search = e.target.value;
                parcelsCurrentPage = 1;
                loadParcels();
            }, 500);
        });
    }

    // Status filter
    const statusFilter = document.getElementById('filterStatus');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            currentFilters.status = e.target.value;
            loadParcels();
        });
    }

    // Date range filter
    const dateRangeFilter = document.getElementById('filterDateRange');
    if (dateRangeFilter) {
        dateRangeFilter.addEventListener('change', (e) => {
            currentFilters.dateRange = e.target.value;
            loadParcels();
        });
    }

    // Driver filter
    const driverFilter = document.getElementById('filterDriver');
    if (driverFilter) {
        driverFilter.addEventListener('change', (e) => {
            currentFilters.driver_id = e.target.value;
            loadParcels();
        });
    }

    // Outlet filter
    const outletFilter = document.getElementById('filterOutlet');
    if (outletFilter) {
        outletFilter.addEventListener('change', (e) => {
            currentFilters.outlet_id = e.target.value;
            loadParcels();
        });
    }

    // Modal close button
    const closeModalBtn = document.querySelector('.close-modal');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeParcelModal);
    }

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('parcelModal');
        if (e.target === modal) {
            closeParcelModal();
        }
    });
}

// Main function to load and display parcels
async function loadParcels() {
    showLoading();

    try {
        const result = await fetchParcelsWithFilters();
        if (!result || typeof result !== 'object') {
            throw new Error('Invalid response format');
        }
        // With server-side pagination, we no longer cache all parcels
        // But we store the result locally to safely re-render if needed
        window.__parcelsCache = result;
        renderParcelsResult(result);
    } catch (error) {
        console.error('Error loading parcels:', error);

        // Extract useful information from the error
        let errorMessage = 'Failed to load parcels. ';

        if (error.message.includes('JWT expired') || error.message.includes('Not authenticated')) {
            errorMessage = 'Your session has expired. Please log in again.';
            // Redirect to login page
            window.location.href = '../auth/login.php';
            return;
        } else if (error.message.includes('Invalid JSON')) {
            errorMessage = 'The server returned an invalid response. Technical details: ' + error.message;
        } else if (error.message.includes('debug_info')) {
            errorMessage = error.message; // Use the detailed error from the API
        } else if (error.message.includes('Failed to fetch')) {
            errorMessage = 'Could not connect to the server. Please check your internet connection.';
        } else {
            errorMessage += error.message;
        }

        showError(errorMessage);
    }
}

// Show loading indicator
function showLoading() {
    const tableBody = document.getElementById('deliveriesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">
                    <div class="loading-indicator">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading deliveries...</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Show error message
function showError(message = 'Failed to load parcels. Please try again.') {
    const tableBody = document.getElementById('deliveriesTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-red-500">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${escapeHtml(message)}</p>
                    <button onclick="loadParcels()" class="retry-button">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </td>
            </tr>
        `;
    }
}

// Fetch parcels from API with filters
async function fetchParcelsWithFilters() {
    let response;
    try {
        // Always request only delivered parcels for this page (server enforces filter)
        let url = '../api/fetch_deliveries.php';
        const params = new URLSearchParams();
        // Append supported filters
        if (currentFilters.dateRange) params.append('dateRange', currentFilters.dateRange);
        if (currentFilters.search) params.append('search', currentFilters.search);
        if (currentFilters.driver_id) params.append('driver_id', currentFilters.driver_id);
        // The parcels table uses origin_outlet_id/destination_outlet_id — send origin_outlet_id
        if (currentFilters.outlet_id) params.append('origin_outlet_id', currentFilters.outlet_id);

        // Append pagination parameter
        params.append('page', parcelsCurrentPage);

        if (params.toString()) url += '?' + params.toString();

        console.log('Fetching from URL:', url);
        response = await fetch(url, {
            credentials: 'include'
        });

        // Log response headers for debugging
        console.log('Response headers:', {
            status: response.status,
            statusText: response.statusText,
            contentType: response.headers.get('content-type')
        });

        const responseText = await response.text();
        console.log('Raw response:', responseText);

        // Try to parse the response as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
        }

        // Check for API error response
        if (!result.success) {
            const errorMessage = result.error || 'Unknown error occurred';
            const debugInfo = result.debug_info ? ` (${JSON.stringify(result.debug_info)})` : '';
            throw new Error(errorMessage + debugInfo);
        }

        return result;
    } catch (error) {
        console.error('Fetch error:', error);
        if (response) {
            console.error('Response status:', response.status, response.statusText);
        }
        throw error;
    }
}

function renderParcelsResult(result) {
    const tableBody = document.getElementById('deliveriesTableBody');
    if (tableBody) {
        // Data is now page-specific
        const data = result.data || [];
        if (!data || data.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <i class="fas fa-box"></i>
                        No deliveries found
                    </td>
                </tr>
            `;
            // clear pagination
            renderDeliveriesPagination(0);
        } else {
            // total is now supplied by the API
            const total = result.total || 0;
            const totalPages = Math.max(1, Math.ceil(total / parcelsItemsPerPage));
            if (parcelsCurrentPage > totalPages) parcelsCurrentPage = Math.max(1, totalPages);

            // Server-side returning exactly what we need, no slicing locally!
            const slice = data;
            tableBody.innerHTML = slice.map(parcel => `
                <tr>
                    <td data-label="Tracking Number">${escapeHtml(parcel.track_number)}</td>
                    <td data-label="Sender">${escapeHtml(parcel.sender_name || parcel.sender)}</td>
                    <td data-label="Receiver">${escapeHtml(parcel.receiver_name || parcel.receiver)}</td>
                    <td data-label="Status">
                        <span class="status-badge ${escapeHtml((parcel.status || '').toLowerCase())}">${escapeHtml(parcel.status || '')}</span>
                    </td>
                    <td data-label="Actions">
                        <button onclick="viewParcelDetails('${escapeHtml(parcel.id)}')" class="action-btn view-btn">
                            View
                        </button>
                    </td>
                </tr>
            `).join('');
            renderDeliveriesPagination(total);
        }
    }
}

// View parcel details in modal
async function viewParcelDetails(parcelId) {
    if (!parcelId) return;

    try {
        const response = await fetch(`../api/fetch_parcel_details.php?id=${encodeURIComponent(parcelId)}`, {
            credentials: 'include'
        });
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load parcel details');
        }

        const parcel = result.data;

        // Populate modal data
        document.getElementById('modalTrackingNumber').textContent = `Tracking Number: ${parcel.track_number || 'N/A'}`;
        document.getElementById('modalParcelStatusDisplay').textContent = parcel.status ? parcel.status.replace('_', ' ').toUpperCase() : 'UNKNOWN';
        document.getElementById('modalParcelStatusDisplay').className = `parcel-status-display status-${(parcel.status || 'pending').toLowerCase()}`;

        document.getElementById('modalRecipientName').textContent = parcel.receiver_name || parcel.receiver || 'N/A';
        document.getElementById('modalDeliveryAddress').textContent = parcel.receiver_address || parcel.destination_address || 'N/A';
        document.getElementById('modalContactNumber').textContent = parcel.receiver_phone || 'N/A';

        document.getElementById('modalParcelWeight').textContent = parcel.parcel_weight ? `${parcel.parcel_weight} kg` : (parcel.weight ? `${parcel.weight} kg` : 'N/A');
        document.getElementById('modalDeliveryFee').textContent = parcel.delivery_fee ? `ZMW ${parcel.delivery_fee}` : 'N/A';
        document.getElementById('modalSpecialInstructions').textContent = parcel.special_instructions || parcel.description || 'None';

        document.getElementById('modalSenderName').textContent = parcel.sender_name || parcel.sender || 'N/A';
        document.getElementById('modalSenderPhone').textContent = parcel.sender_phone || 'N/A';

        document.getElementById('modalOriginOutlet').textContent = parcel.origin_outlet_name || parcel.origin_address || 'Unknown';
        document.getElementById('modalDestinationOutlet').textContent = parcel.destination_outlet_name || parcel.destination_address || 'Unknown';

        openParcelModal();
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load parcel details. Please try again.');
    }
}

// Modal control functions
function openParcelModal() {
    const modal = document.getElementById('parcelModal');
    if (modal) {
        // Use a CSS class to show the modal so flex-centering rules apply
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }
}

function closeParcelModal() {
    const modal = document.getElementById('parcelModal');
    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }
}
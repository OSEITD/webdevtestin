// Script is included at the bottom of the page; run initialization immediately
try {
    loadVehicles();
    // Wire filter controls
    const searchEl = document.getElementById('vehicleSearch');
    const statusEl = document.getElementById('vehicleStatusFilter');
    if (searchEl) {
        searchEl.addEventListener('input', debounce(applyVehicleFilters, 250));
        console.debug('vehicles.js: search input listener attached');
    }
    if (statusEl) {
        statusEl.addEventListener('change', applyVehicleFilters);
        console.debug('vehicles.js: status select listener attached');
    }
} catch (err) {
    console.error('vehicles.js init error:', err);
}

// Pagination state for vehicles
let vehiclesCurrentPage = 1;
const vehiclesItemsPerPage = 25;

function renderVehiclesPagination(totalItems) {
    const container = document.getElementById('vehiclesPagination');
    if (!container) return;
    const totalPages = Math.max(1, Math.ceil(totalItems / vehiclesItemsPerPage));
    let html = '';
    html += `<a href="?page=1" class="pagination-btn" style="${vehiclesCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${vehiclesCurrentPage === 1 ? 'onclick="return false;"' : 'onclick="changeVehiclesPage(1);return false;"'}><i class="fas fa-chevron-left"></i> First</a>`;
    html += `<a href="?page=${Math.max(1, vehiclesCurrentPage-1)}" class="pagination-btn" style="${vehiclesCurrentPage === 1 ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${vehiclesCurrentPage === 1 ? 'onclick="return false;"' : `onclick="changeVehiclesPage(${Math.max(1, vehiclesCurrentPage-1)});return false;"`}> <i class="fas fa-chevron-left"></i> Previous</a>`;
    let startPage = Math.max(1, vehiclesCurrentPage - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if (endPage - startPage < 6) {
        startPage = Math.max(1, endPage - 6);
    }
    for (let p = startPage; p <= endPage; p++) {
        if (p === vehiclesCurrentPage) html += `<a href="?page=${p}" class="page-number" style="background-color: #3b82f6; color: white; border-radius: 4px; padding: 5px 10px; cursor: default;" onclick="return false;">${p}</a>`;
        else html += `<a href="?page=${p}" class="page-number" style="padding: 5px 10px; border-radius: 4px; border: 1px solid #d1d5db;" onclick="changeVehiclesPage(${p});return false;">${p}</a>`;
    }
    html += `<a href="?page=${Math.min(totalPages, vehiclesCurrentPage+1)}" class="pagination-btn" style="${vehiclesCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${vehiclesCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeVehiclesPage(${Math.min(totalPages, vehiclesCurrentPage+1)});return false;"`}>Next <i class="fas fa-chevron-right"></i></a>`;
    html += `<a href="?page=${totalPages}" class="pagination-btn" style="${vehiclesCurrentPage >= totalPages ? 'opacity: 0.5; cursor: not-allowed;' : ''}" ${vehiclesCurrentPage >= totalPages ? 'onclick="return false;"' : `onclick="changeVehiclesPage(${totalPages});return false;"`}>Last <i class="fas fa-chevron-right"></i></a>`;
    container.innerHTML = html;
}

function changeVehiclesPage(page) {
    vehiclesCurrentPage = page;
    applyVehicleFilters();
}

async function loadVehicles() {
    const vehiclesGrid = document.getElementById('vehiclesGrid');
    
    try {
        const response = await fetch('api/fetch_vehicles.php');
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load vehicles');
        }

        if (!result.vehicles || result.vehicles.length === 0) {
            vehiclesGrid.innerHTML = `
                <div class="no-vehicles">
                    <i class="fas fa-truck"></i>
                    <p>No vehicles found</p>
                    <button onclick="window.location.href='company-add-vehicle.php'" class="add-button">
                        <i class="fas fa-plus"></i> Add Your First Vehicle
                    </button>
                </div>
            `;
            return;
        }

    // Cache fetched vehicles and display vehicles
    window.__vehiclesCache = result.vehicles || [];
    vehiclesCurrentPage = 1;
    applyVehicleFilters();

    } catch (error) {
        console.error('Error:', error);
        vehiclesGrid.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p>Failed to load vehicles. Please try again.</p>
                <button onclick="loadVehicles()" class="retry-button">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        `;
    }
}

function editVehicle(id) {
    // Implement edit functionality
    console.log('Edit vehicle:', id);
    // You can redirect to an edit page or show a modal
    window.location.href = `company-edit-vehicle.php?id=${id}`;
}


function renderVehicles(list) {
    const vehiclesGrid = document.getElementById('vehiclesGrid');
    if (!list || list.length === 0) {
        vehiclesGrid.innerHTML = `
            <div class="no-vehicles">
                <i class="fas fa-truck"></i>
                <p>No vehicles found</p>
                <button onclick="window.location.href='company-add-vehicle.php'" class="add-button">
                    <i class="fas fa-plus"></i> Add Your First Vehicle
                </button>
            </div>
        `;
        return;
    }

        console.debug(`vehicles.js: renderVehicles called with ${list.length} items`);

    vehiclesGrid.innerHTML = list.map(vehicle => `
        <div class="vehicle-card">
            <div class="vehicle-header">
                <h3>${vehicle.name}</h3>
                <span class="status-badge ${String(vehicle.status || '').toLowerCase()}">${vehicle.status}</span>
            </div>
            <div class="vehicle-info">
                <p><i class="fas fa-id-card"></i> ${vehicle.plate_number}</p>
                <p><i class="fas fa-clock"></i> Added: ${new Date(vehicle.created_at).toLocaleDateString()}</p>
            </div>
            <div class="vehicle-actions">
                <button onclick="editVehicle('${vehicle.id}')" class="action-button edit">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    `).join('');
}

function applyVehicleFilters() {
    const searchEl = document.getElementById('vehicleSearch');
    const statusEl = document.getElementById('vehicleStatusFilter');
    const term = (searchEl?.value || '').toLowerCase();
    const rawStatus = (statusEl?.value || '');
    const status = rawStatus ? rawStatus.toString().toLowerCase().replace(/[^a-z0-9]/g, '_') : '';
    const all = window.__vehiclesCache || [];
    console.debug(`vehicles.js: applyVehicleFilters search="${term}" status="${status}" cached=${all.length}`);
    const filtered = all.filter(v => {
        const name = String(v.name || '').toLowerCase();
        const plate = String(v.plate_number || '').toLowerCase();
        const matchesTerm = !term || name.includes(term) || plate.includes(term);
        const vstatus = String(v.status || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
        const matchesStatus = !status || vstatus === status;
        return matchesTerm && matchesStatus;
    });
    console.debug(`vehicles.js: applyVehicleFilters -> filtered=${filtered.length}`);
    // paginate
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / vehiclesItemsPerPage));
    if (vehiclesCurrentPage > totalPages) vehiclesCurrentPage = totalPages;
    const start = (vehiclesCurrentPage - 1) * vehiclesItemsPerPage;
    const slice = filtered.slice(start, start + vehiclesItemsPerPage);
    renderVehicles(slice);
    renderVehiclesPagination(total);
}

// Simple debounce helper
function debounce(fn, wait) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}
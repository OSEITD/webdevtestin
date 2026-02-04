function getApiPath() {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/pages/')) {
        return '../api/';
    }
    return 'api/';
}

document.addEventListener('DOMContentLoaded', function() {
    loadAssignments();
    loadVehicleFilter();
    document.getElementById('viewMode').value = 'table';
});

window.currentAssignments = [];
window.allAssignments = [];

function toggleViewMode() {
    const viewMode = document.getElementById('viewMode').value;
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');

    if (viewMode === 'table') {
        tableView.className = 'view-active';
        cardView.className = 'view-hidden';
        loadTableView();
    } else {
        tableView.className = 'view-hidden';
        cardView.className = 'view-active';
        loadCardView();
    }
}

async function loadAssignments() {
    const viewMode = document.getElementById('viewMode').value;

    if (viewMode === 'table') {
        loadTableView();
    } else {
        loadCardView();
    }
}

async function loadTableView() {
    const tableBody = document.getElementById('assignmentsTableBody');
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-8">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i>
                    <p class="mt-4 text-gray-600">Loading assignments...</p>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`${getApiPath()}fetch_assignment_tracking.php`);
        console.log('Table view - Response status:', response.status);

        const responseText = await response.text();
        console.log('Table view - Raw response:', responseText.substring(0, 500) + (responseText.length > 500 ? '...' : ''));

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Table view - JSON Parse Error:', parseError);
            console.error('Table view - Response text that failed to parse:', responseText);
            throw new Error('Invalid JSON response from server');
        }

        if (data.success && data.assignments && data.assignments.length > 0) {
            displayTableAssignments(data.assignments);
        } else if (data.success && data.assignments && data.assignments.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-12">
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list fa-3x text-gray-400 mb-4"></i>
                            <h3 class="text-gray-600 font-semibold">No Assignments Found</h3>
                            <p class="text-gray-500 mt-2">There are currently no parcel assignments to display.</p>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            throw new Error(data.error || 'Failed to load assignment data');
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-12">
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle fa-3x text-red-500 mb-4"></i>
                        <h3 class="text-red-600 font-semibold">Error Loading Data</h3>
                        <p class="text-gray-600 mt-2">Unable to load assignment tracking information.</p>
                        <p class="text-gray-500 text-sm mt-1">${error.message}</p>
                        <button class="btn btn-primary mt-4" onclick="loadAssignments()">
                            <i class="fas fa-retry"></i> Try Again
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
}

async function loadCardView() {
    const container = document.getElementById('cardView');
    container.innerHTML = `
        <div class="loading-spinner text-center py-8">
            <i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i>
            <p class="mt-4 text-gray-600">Loading assignments...</p>
        </div>
    `;

    try {
        const response = await fetch(`${getApiPath()}fetch_assignment_tracking.php`);
        console.log('Card view - Response status:', response.status);

        const responseText = await response.text();
        console.log('Card view - Raw response:', responseText.substring(0, 500) + (responseText.length > 500 ? '...' : ''));

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Card view - JSON Parse Error:', parseError);
            console.error('Card view - Response text that failed to parse:', responseText);
            throw new Error('Invalid JSON response from server');
        }

        if (data.success && data.assignments && data.assignments.length > 0) {
            displayCardAssignments(data.assignments, container);
        } else if (data.success && data.assignments && data.assignments.length === 0) {
            container.innerHTML = `
                <div class="empty-state text-center py-12">
                    <i class="fas fa-clipboard-list fa-3x text-gray-400 mb-4"></i>
                    <h3 class="text-gray-600 font-semibold">No Assignments Found</h3>
                    <p class="text-gray-500 mt-2">There are currently no parcel assignments to display.</p>
                </div>
            `;
        } else {
            throw new Error(data.error || 'Failed to load assignment data');
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
        container.innerHTML = `
            <div class="error-state text-center py-12">
                <i class="fas fa-exclamation-triangle fa-3x text-red-500 mb-4"></i>
                <h3 class="text-red-600 font-semibold">Error Loading Data</h3>
                <p class="text-gray-600 mt-2">Unable to load assignment tracking information.</p>
                <p class="text-gray-500 text-sm mt-1">${error.message}</p>
                <button class="btn btn-primary mt-4" onclick="loadAssignments()">
                    <i class="fas fa-retry"></i> Try Again
                </button>
            </div>
        `;
    }
}

function displayTableAssignments(assignments) {
    const tableBody = document.getElementById('assignmentsTableBody');

    if (arguments[1] !== 'filtered') {
        window.currentAssignments = assignments;
        window.allAssignments = [...assignments];
    }

    let html = '';
    assignments.forEach(assignment => {
        const statusClass = getStatusClass(assignment.status);
        const assignedDate = new Date(assignment.assigned_at).toLocaleString();

        html += `
            <tr>
                <td>
                    <div class="tracking-id">${assignment.track_number}</div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${assignment.status}</span>
                </td>
                <td>
                    <div class="parcel-info">
                        <div><strong>From:</strong> ${assignment.sender_name || 'N/A'}</div>
                        <div><strong>To:</strong> ${assignment.recipient_name || 'N/A'}</div>
                        <div><strong>Weight:</strong> ${assignment.weight ? assignment.weight + ' kg' : 'N/A'}</div>
                        <div><strong>Type:</strong> ${assignment.parcel_type || 'Standard'}</div>
                    </div>
                </td>
                <td>
                    <div class="vehicle-info">
                        <div class="font-semibold">${assignment.vehicle_name || 'Unassigned'}</div>
                        ${assignment.vehicle_plate ? `<div class="text-sm"><i class="fas fa-id-card"></i> ${assignment.vehicle_plate}</div>` : ''}
                        ${assignment.vehicle_status ? `<div class="text-sm status-${assignment.vehicle_status}"><i class="fas fa-circle"></i> ${assignment.vehicle_status}</div>` : ''}
                    </div>
                </td>
                <td>
                    ${assignment.vehicle_number || assignment.vehicle_type ?
                        `<div class="vehicle-badge">
                            <div><i class="fas fa-truck"></i> ${assignment.vehicle_number || 'N/A'}</div>
                            <div>${assignment.vehicle_type || 'Unknown Type'}</div>
                        </div>` :
                        '<span class="text-gray-400">No Vehicle Info</span>'
                    }
                </td>
                <td>
                    <div class="assignment-details-info">
                        <div><strong>Assigned:</strong></div>
                        <div>${assignedDate}</div>
                        ${assignment.assigned_by_name ? `<div><strong>By:</strong> ${assignment.assigned_by_name}</div>` : ''}
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="viewAssignmentDetails('${assignment.track_number}')">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

function displayCardAssignments(assignments, container) {

    if (arguments[2] !== 'filtered') {
        window.currentAssignments = assignments;
        window.allAssignments = [...assignments];
    }

    let html = '';
    assignments.forEach(assignment => {
        const statusClass = `status-${assignment.status.replace(' ', '-').toLowerCase()}`;
        const assignedDate = new Date(assignment.assigned_at).toLocaleString();

        html += `
            <div class="assignment-card">
                <div class="assignment-header">
                    <div class="track-number">${assignment.track_number}</div>
                    <div class="assignment-status ${statusClass}">${assignment.status}</div>
                </div>

                <div class="assignment-details">
                    <div class="detail-section">
                        <div class="section-title">
                            <i class="fas fa-box"></i>
                            Parcel Information
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sender:</span>
                            <span class="detail-value">${assignment.sender_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Recipient:</span>
                            <span class="detail-value">${assignment.recipient_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Weight:</span>
                            <span class="detail-value">${assignment.weight ? assignment.weight + ' kg' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Delivery Address:</span>
                            <span class="detail-value">${assignment.delivery_address || 'N/A'}</span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="section-title">
                            <i class="fas fa-truck"></i>
                            Vehicle Information
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Name:</span>
                            <span class="detail-value">${assignment.vehicle_name || 'Unassigned'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Plate Number:</span>
                            <span class="detail-value">${assignment.vehicle_plate || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">${assignment.vehicle_status || 'N/A'}</span>
                        </div>

                        ${assignment.vehicle_number || assignment.vehicle_type ?
                            `<div class="vehicle-info">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <i class="fas fa-truck"></i>
                                    <strong>Vehicle Information</strong>
                                </div>
                                <div>Vehicle Number: ${assignment.vehicle_number || 'N/A'}</div>
                                <div>Vehicle Type: ${assignment.vehicle_type || 'N/A'}</div>
                            </div>` :
                            ''
                        }

                        <div class="detail-item">
                            <span class="detail-label">Assigned At:</span>
                            <span class="detail-value">${assignedDate}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Assigned By:</span>
                            <span class="detail-value">${assignment.assigned_by_name || 'System'}</span>
                        </div>
                    </div>
                </div>

                <div class="card-action" style="padding: 16px 0; border-top: 1px solid #e5e7eb; margin-top: 16px; text-align: center;">
                    <button class="btn btn-outline" onclick="viewAssignmentDetails('${assignment.track_number}')" style="width: 100%; padding: 12px;">
                        <i class="fas fa-eye"></i> View Full Details
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function viewAssignmentDetails(trackNumber) {
    const modal = document.getElementById('assignmentModal');
    const modalContent = document.getElementById('modalContent');

    modal.classList.add('show');
    modalContent.innerHTML = `
        <div class="loading-spinner text-center py-8">
            <i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i>
            <p class="mt-4">Loading assignment details...</p>
        </div>
    `;

    const currentAssignments = window.currentAssignments || [];
    const assignment = currentAssignments.find(a => a.track_number === trackNumber);

    if (!assignment) {
        modalContent.innerHTML = `
            <div class="error-state text-center py-8">
                <i class="fas fa-exclamation-triangle fa-3x text-red-500 mb-4"></i>
                <h3 class="text-red-600 font-semibold">Assignment Not Found</h3>
                <p class="text-gray-600 mt-2">Could not find assignment details for track number: ${trackNumber}</p>
            </div>
        `;
        return;
    }

    setTimeout(() => {
        modalContent.innerHTML = generateAssignmentDetailsHTML(assignment);
    }, 300);
}

function generateAssignmentDetailsHTML(assignment) {
    const statusClass = getStatusClass(assignment.status);
    const assignedDate = assignment.assigned_at ? new Date(assignment.assigned_at).toLocaleString() : 'Not specified';
    const estimatedDelivery = assignment.estimated_delivery_date ? new Date(assignment.estimated_delivery_date).toLocaleDateString() : 'Not specified';

    return `
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">
                    <i class="fas fa-box"></i> Parcel Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Track Number</span>
                    <span class="detail-value" style="font-family: monospace; font-weight: 700; color: #667eea;">${assignment.track_number}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Weight</span>
                    <span class="detail-value">${assignment.weight ? assignment.weight + ' kg' : 'Not specified'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type</span>
                    <span class="detail-value">${assignment.parcel_type || 'Standard'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Value</span>
                    <span class="detail-value">${assignment.parcel_value ? 'ZMW ' + assignment.parcel_value : 'Not declared'}</span>
                </div>
                ${assignment.special_instructions ? `
                <div class="detail-row">
                    <span class="detail-label">Instructions</span>
                    <span class="detail-value">${assignment.special_instructions}</span>
                </div>
                ` : ''}
            </div>

            <div class="detail-card status">
                <div class="detail-card-title">
                    <i class="fas fa-info-circle"></i> Status Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Current Status</span>
                    <span class="status-badge ${statusClass}">${assignment.status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Assigned Date</span>
                    <span class="detail-value">${assignedDate}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Assigned By</span>
                    <span class="detail-value">${assignment.assigned_by_name || 'System'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estimated Delivery</span>
                    <span class="detail-value">${estimatedDelivery}</span>
                </div>
            </div>

            <div class="detail-card sender">
                <div class="detail-card-title">
                    <i class="fas fa-user"></i> Sender Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">${assignment.sender_name || 'Not specified'}</span>
                </div>
                ${assignment.sender_phone ? `
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">${assignment.sender_phone}</span>
                </div>
                ` : ''}
                ${assignment.sender_email ? `
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">${assignment.sender_email}</span>
                </div>
                ` : ''}
                ${assignment.sender_address ? `
                <div class="detail-row">
                    <span class="detail-label">Address</span>
                    <span class="detail-value">${assignment.sender_address}</span>
                </div>
                ` : ''}
            </div>

            <div class="detail-card recipient">
                <div class="detail-card-title">
                    <i class="fas fa-user-check"></i> Recipient Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">${assignment.recipient_name || 'Not specified'}</span>
                </div>
                ${assignment.recipient_phone ? `
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">${assignment.recipient_phone}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value">${assignment.delivery_address || 'Not specified'}</span>
                </div>
            </div>

            <div class="detail-card vehicle">
                <div class="detail-card-title">
                    <i class="fas fa-truck"></i> Vehicle Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Vehicle Name</span>
                    <span class="detail-value">${assignment.vehicle_name || 'Unassigned'}</span>
                </div>
                ${assignment.vehicle_plate ? `
                <div class="detail-row">
                    <span class="detail-label">Plate Number</span>
                    <span class="detail-value">${assignment.vehicle_plate}</span>
                </div>
                ` : ''}
                ${assignment.vehicle_status ? `
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">${assignment.vehicle_status}</span>
                </div>
                ` : ''}
                ${assignment.vehicle_number ? `
                <div class="detail-row">
                    <span class="detail-label">Vehicle</span>
                    <span class="detail-value">${assignment.vehicle_number} (${assignment.vehicle_type || 'Unknown'})</span>
                </div>
                ` : ''}
            </div>
        </div>

        <div class="modal-actions">
            <button class="modal-btn modal-btn-secondary" onclick="printAssignmentDetails('${assignment.track_number}')">
                <i class="fas fa-print"></i> Print Details
            </button>
            <button class="modal-btn modal-btn-primary" onclick="closeAssignmentModal()">
                <i class="fas fa-check"></i> Close
            </button>
        </div>
    `;
}

function closeAssignmentModal() {
    const modal = document.getElementById('assignmentModal');
    modal.classList.remove('show');
}

function printAssignmentDetails(trackNumber) {
    const currentAssignments = window.currentAssignments || [];
    const assignment = currentAssignments.find(a => a.track_number === trackNumber);

    if (!assignment) {
        alert('Assignment details not found for printing.');
        return;
    }

    const printContent = `
        <html>
        <head>
            <title>Assignment Details - ${trackNumber}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
                .section h3 { margin-top: 0; color: #333; }
                .row { display: flex; justify-content: space-between; margin: 8px 0; }
                .label { font-weight: bold; }
                .value { text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Parcel Assignment Details</h1>
                <h2>Track Number: ${assignment.track_number}</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>

            <div class="section">
                <h3>Parcel Information</h3>
                <div class="row"><span class="label">Weight:</span><span class="value">${assignment.weight || 'N/A'} kg</span></div>
                <div class="row"><span class="label">Type:</span><span class="value">${assignment.parcel_type || 'Standard'}</span></div>
                <div class="row"><span class="label">Status:</span><span class="value">${assignment.status}</span></div>
            </div>

            <div class="section">
                <h3>Sender Information</h3>
                <div class="row"><span class="label">Name:</span><span class="value">${assignment.sender_name || 'N/A'}</span></div>
                <div class="row"><span class="label">Phone:</span><span class="value">${assignment.sender_phone || 'N/A'}</span></div>
                <div class="row"><span class="label">Email:</span><span class="value">${assignment.sender_email || 'N/A'}</span></div>
            </div>

            <div class="section">
                <h3>Recipient Information</h3>
                <div class="row"><span class="label">Name:</span><span class="value">${assignment.recipient_name || 'N/A'}</span></div>
                <div class="row"><span class="label">Phone:</span><span class="value">${assignment.recipient_phone || 'N/A'}</span></div>
                <div class="row"><span class="label">Address:</span><span class="value">${assignment.delivery_address || 'N/A'}</span></div>
            </div>

            <div class="section">
                <h3>Vehicle Information</h3>
                <div class="row"><span class="label">Name:</span><span class="value">${assignment.vehicle_name || 'Unassigned'}</span></div>
                <div class="row"><span class="label">Plate:</span><span class="value">${assignment.vehicle_plate || 'N/A'}</span></div>
                <div class="row"><span class="label">Status:</span><span class="value">${assignment.vehicle_status || 'N/A'}</span></div>
            </div>
        </body>
        </html>
    `;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('assignmentModal');
    if (event.target === modal) {
        closeAssignmentModal();
    }
});

function filterAssignments() {
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const vehicleFilter = document.getElementById('vehicleFilter').value;
    const trackFilter = document.getElementById('trackFilter').value.toLowerCase().trim();

    console.log('🔍 Filtering with:', {
        status: statusFilter,
        vehicle: vehicleFilter,
        track: trackFilter,
        totalAssignments: window.allAssignments.length
    });

    let filteredAssignments = [...window.allAssignments];

    if (statusFilter && statusFilter !== '') {
        filteredAssignments = filteredAssignments.filter(assignment =>
            assignment.status.toLowerCase().includes(statusFilter)
        );
        console.log('📊 After status filter:', filteredAssignments.length);
    }

    if (vehicleFilter && vehicleFilter !== '') {
        filteredAssignments = filteredAssignments.filter(assignment =>
            assignment.vehicle_id === vehicleFilter
        );
        console.log('🚛 After vehicle filter:', filteredAssignments.length);
    }

    if (trackFilter && trackFilter !== '') {
        filteredAssignments = filteredAssignments.filter(assignment =>
            assignment.track_number.toLowerCase().includes(trackFilter)
        );
        console.log('📦 After track filter:', filteredAssignments.length);
    }

    console.log('✅ Final filtered assignments:', filteredAssignments.length);

    window.currentAssignments = filteredAssignments;

    const viewMode = document.getElementById('viewMode').value;
    if (viewMode === 'table') {
        displayFilteredTableAssignments(filteredAssignments);
    } else {
        const container = document.getElementById('cardView');
        displayFilteredCardAssignments(filteredAssignments, container);
    }

    updateFilterInfo(filteredAssignments.length, window.allAssignments.length);
}

function updateFilterInfo(filteredCount, totalCount) {
    console.log(`Showing ${filteredCount} of ${totalCount} assignments`);
}

function displayFilteredTableAssignments(assignments) {
    const tableBody = document.getElementById('assignmentsTableBody');

    if (assignments.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-12">
                    <div class="empty-state">
                        <i class="fas fa-filter fa-3x text-gray-400 mb-4"></i>
                        <h3 class="text-gray-600 font-semibold">No Matching Assignments</h3>
                        <p class="text-gray-500 mt-2">No assignments match your current filter criteria.</p>
                        <button class="btn btn-primary mt-4" onclick="clearAllFilters()">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    assignments.forEach(assignment => {
        const statusClass = getStatusClass(assignment.status);
        const assignedDate = new Date(assignment.assigned_at).toLocaleString();

        html += `
            <tr>
                <td>
                    <div class="tracking-id">${assignment.track_number}</div>
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${assignment.status}</span>
                </td>
                <td>
                    <div class="parcel-info">
                        <div><strong>From:</strong> ${assignment.sender_name || 'N/A'}</div>
                        <div><strong>To:</strong> ${assignment.recipient_name || 'N/A'}</div>
                        <div><strong>Weight:</strong> ${assignment.weight ? assignment.weight + ' kg' : 'N/A'}</div>
                        <div><strong>Type:</strong> ${assignment.parcel_type || 'Standard'}</div>
                    </div>
                </td>
                <td>
                    <div class="vehicle-info">
                        <div class="font-semibold">${assignment.vehicle_name || 'Unassigned'}</div>
                        ${assignment.vehicle_plate ? `<div class="text-sm"><i class="fas fa-id-card"></i> ${assignment.vehicle_plate}</div>` : ''}
                        ${assignment.vehicle_status ? `<div class="text-sm status-${assignment.vehicle_status}"><i class="fas fa-circle"></i> ${assignment.vehicle_status}</div>` : ''}
                    </div>
                </td>
                <td>
                    ${assignment.vehicle_number || assignment.vehicle_type ?
                        `<div class="vehicle-badge">
                            <div><i class="fas fa-truck"></i> ${assignment.vehicle_number || 'N/A'}</div>
                            <div>${assignment.vehicle_type || 'Unknown Type'}</div>
                        </div>` :
                        '<span class="text-gray-400">No Vehicle Info</span>'
                    }
                </td>
                <td>
                    <div class="assignment-details-info">
                        <div><strong>Assigned:</strong></div>
                        <div>${assignedDate}</div>
                        ${assignment.assigned_by_name ? `<div><strong>By:</strong> ${assignment.assigned_by_name}</div>` : ''}
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="viewAssignmentDetails('${assignment.track_number}')">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

function displayFilteredCardAssignments(assignments, container) {

    if (assignments.length === 0) {
        container.innerHTML = `
            <div class="empty-state text-center py-12">
                <i class="fas fa-filter fa-3x text-gray-400 mb-4"></i>
                <h3 class="text-gray-600 font-semibold">No Matching Assignments</h3>
                <p class="text-gray-500 mt-2">No assignments match your current filter criteria.</p>
                <button class="btn btn-primary mt-4" onclick="clearAllFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
        `;
        return;
    }

    let html = '';
    assignments.forEach(assignment => {
        const statusClass = `status-${assignment.status.replace(' ', '-').toLowerCase()}`;
        const assignedDate = new Date(assignment.assigned_at).toLocaleString();

        html += `
            <div class="assignment-card">
                <div class="assignment-header">
                    <div class="track-number">${assignment.track_number}</div>
                    <div class="assignment-status ${statusClass}">${assignment.status}</div>
                </div>

                <div class="assignment-details">
                    <div class="detail-section">
                        <div class="section-title">
                            <i class="fas fa-box"></i>
                            Parcel Information
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sender:</span>
                            <span class="detail-value">${assignment.sender_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Recipient:</span>
                            <span class="detail-value">${assignment.recipient_name || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Weight:</span>
                            <span class="detail-value">${assignment.weight ? assignment.weight + ' kg' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Delivery Address:</span>
                            <span class="detail-value">${assignment.delivery_address || 'N/A'}</span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="section-title">
                            <i class="fas fa-truck"></i>
                            Vehicle Information
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Name:</span>
                            <span class="detail-value">${assignment.vehicle_name || 'Unassigned'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Plate Number:</span>
                            <span class="detail-value">${assignment.vehicle_plate || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">${assignment.vehicle_status || 'N/A'}</span>
                        </div>

                        ${assignment.vehicle_number || assignment.vehicle_type ?
                            `<div class="vehicle-info">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <i class="fas fa-truck"></i>
                                    <strong>Vehicle Information</strong>
                                </div>
                                <div>Vehicle Number: ${assignment.vehicle_number || 'N/A'}</div>
                                <div>Vehicle Type: ${assignment.vehicle_type || 'N/A'}</div>
                            </div>` :
                            ''
                        }

                        <div class="detail-item">
                            <span class="detail-label">Assigned At:</span>
                            <span class="detail-value">${assignedDate}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Assigned By:</span>
                            <span class="detail-value">${assignment.assigned_by_name || 'System'}</span>
                        </div>
                    </div>
                </div>

                <div class="card-action" style="padding: 16px 0; border-top: 1px solid #e5e7eb; margin-top: 16px; text-align: center;">
                    <button class="btn btn-outline" onclick="viewAssignmentDetails('${assignment.track_number}')" style="width: 100%; padding: 12px;">
                        <i class="fas fa-eye"></i> View Full Details
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

async function loadVehicleFilter() {
    try {
        const response = await fetch(`${getApiPath()}fetch_available_vehicles.php`);
        const data = await response.json();

        if (data.success && data.vehicles) {
            const vehicleFilter = document.getElementById('vehicleFilter');
            data.vehicles.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = vehicle.display_name || vehicle.name;
                vehicleFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading vehicle filter:', error);
    }
}

document.getElementById('statusFilter').addEventListener('change', filterAssignments);
document.getElementById('vehicleFilter').addEventListener('change', filterAssignments);
document.getElementById('trackFilter').addEventListener('input', filterAssignments);

function clearAllFilters() {
    document.getElementById('statusFilter').value = '';
    document.getElementById('vehicleFilter').value = '';
    document.getElementById('trackFilter').value = '';

    console.log('Filters cleared, reloading all assignments');

    const viewMode = document.getElementById('viewMode').value;
    if (viewMode === 'table') {
        displayFilteredTableAssignments(window.allAssignments);
    } else {
        displayFilteredCardAssignments(window.allAssignments, document.getElementById('cardView'));
    }
}

function getStatusClass(status) {
    switch(status.toLowerCase()) {
        case 'pending':
            return 'status-pending';
        case 'assigned':
            return 'status-assigned';
        case 'in transit':
            return 'status-in-transit';
        case 'delivered':
            return 'status-delivered';
        case 'at_outlet':
            return 'status-at_outlet';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-default';
    }
}

setTimeout(function() {
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenu = document.getElementById('closeMenu');

    console.log('🔥 Assignment Tracking - Elements check:', {
        menuBtn: !!menuBtn,
        sidebar: !!sidebar,
        menuOverlay: !!menuOverlay,
        closeMenu: !!closeMenu,
        menuBtnType: menuBtn ? menuBtn.tagName : 'null',
        sidebarClasses: sidebar ? sidebar.className : 'null'
    });

    if (menuBtn && sidebar && menuOverlay) {
        menuBtn.onclick = function(e) {
            console.log('🔥 Assignment Tracking - Menu clicked!');
            e.preventDefault();
            e.stopPropagation();

            const isOpen = sidebar.classList.contains('show');
            console.log('🔥 Assignment Tracking - Sidebar currently open:', isOpen);

            if (isOpen) {
                sidebar.classList.remove('show');
                menuOverlay.classList.remove('show');
                console.log('🔥 Assignment Tracking - Sidebar closed');
            } else {
                sidebar.classList.add('show');
                menuOverlay.classList.add('show');
                console.log('🔥 Assignment Tracking - Sidebar opened');
            }

            console.log('🔥 Assignment Tracking - Final sidebar classes:', sidebar.className);
        };

        if (menuOverlay) {
            menuOverlay.onclick = function() {
                console.log('🔥 Assignment Tracking - Overlay clicked - closing sidebar');
                sidebar.classList.remove('show');
                menuOverlay.classList.remove('show');
            };
        }

        if (closeMenu) {
            closeMenu.onclick = function(e) {
                console.log('🔥 Assignment Tracking - Close button clicked');
                e.preventDefault();
                sidebar.classList.remove('show');
                menuOverlay.classList.remove('show');
            };
        }

        console.log('🔥 Assignment Tracking - Sidebar handlers set up successfully');

        window.forceSidebarOpen = function() {
            console.log('🔥 Assignment Tracking - Force opening sidebar...');
            sidebar.classList.add('show');
            menuOverlay.classList.add('show');
            console.log('🔥 Assignment Tracking - Sidebar forced open, classes:', sidebar.className);
        };

    } else {
        console.error('🔥 Assignment Tracking - Missing elements for sidebar!', {
            menuBtn: menuBtn,
            sidebar: sidebar,
            menuOverlay: menuOverlay,
            closeMenu: closeMenu
        });
    }
}, 1000);

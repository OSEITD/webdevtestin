
class ParcelDetailsEnhanced {
    constructor() {
        this.parcel = null;
        this.map = null;
        this.isLoading = false;
        
        this.init();
    }

    init() {
        this.showLoadingSkeleton();
        this.loadParcelData();
        this.initializeEventListeners();
    }

    showLoadingSkeleton() {
        const container = document.getElementById('parcel-details-container');
        if (container) {
            container.innerHTML = `
                <div class="detail-section-enhanced">
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                </div>
                <div class="detail-section-enhanced">
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                </div>
            `;
        }
    }

    async loadParcelData() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const parcelId = urlParams.get('parcel_id');
            
            if (!parcelId) {
                throw new Error('No parcel ID provided');
            }

            console.log('🔥 Loading parcel data for ID:', parcelId);

            const response = await fetch(`api/fetch_parcel_details.php?parcel_id=${parcelId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.parcel = data.parcel;
                this.renderParcelDetails();
                this.initializeMap();
                this.updatePageTitle();
            } else {
                throw new Error(data.error || 'Failed to load parcel details');
            }
        } catch (error) {
            console.error('🔥 Error loading parcel data:', error);
            this.showErrorState(error.message);
        }
    }

    renderParcelDetails() {
        const container = document.getElementById('parcel-details-container');
        if (!container || !this.parcel) return;

        const status = this.parcel.status || 'pending';
        const statusIcon = this.getStatusIcon(status);
        
        container.innerHTML = `
            <!-- Parcel Information Section -->
            <div class="detail-section-enhanced">
                <h2><i class="fas fa-box"></i> Parcel Information</h2>
                
                <div class="detail-item-enhanced">
                    <i class="fas fa-barcode"></i>
                    <div>
                        <div class="label">Tracking Number</div>
                        <div class="value">${this.parcel.tracking_number || 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge ${status}">
                                <i class="${statusIcon}"></i>
                                ${status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-tag"></i>
                    <div>
                        <div class="label">Type</div>
                        <div class="value">${this.parcel.type || 'Standard'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-weight-hanging"></i>
                    <div>
                        <div class="label">Weight</div>
                        <div class="value">${this.parcel.weight ? this.parcel.weight + ' kg' : 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-dollar-sign"></i>
                    <div>
                        <div class="label">Total Amount</div>
                        <div class="value">₱${this.parcel.total_amount ? parseFloat(this.parcel.total_amount).toFixed(2) : '0.00'}</div>
                    </div>
                </div>
            </div>

            <!-- Sender Information -->
            <div class="detail-section-enhanced">
                <h2><i class="fas fa-user"></i> Sender Information</h2>
                
                <div class="detail-item-enhanced">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <div class="label">Name</div>
                        <div class="value">${this.parcel.sender_name || 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="label">Contact Number</div>
                        <div class="value">${this.parcel.sender_contact || 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <div class="label">Address</div>
                        <div class="value">${this.parcel.sender_address || 'N/A'}</div>
                    </div>
                </div>
            </div>

            <!-- Receiver Information -->
            <div class="detail-section-enhanced">
                <h2><i class="fas fa-user-check"></i> Receiver Information</h2>
                
                <div class="detail-item-enhanced">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <div class="label">Name</div>
                        <div class="value">${this.parcel.receiver_name || 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="label">Contact Number</div>
                        <div class="value">${this.parcel.receiver_contact || 'N/A'}</div>
                    </div>
                </div>

                <div class="detail-item-enhanced">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <div class="label">Address</div>
                        <div class="value">${this.parcel.receiver_address || 'N/A'}</div>
                    </div>
                </div>
            </div>

            <!-- Delivery Information -->
            <div class="detail-section-enhanced">
                <h2><i class="fas fa-shipping-fast"></i> Delivery Information</h2>
                
                <div class="detail-item-enhanced">
                    <i class="fas fa-store"></i>
                    <div>
                        <div class="label">Destination Outlet</div>
                        <div class="value">${this.parcel.destination_outlet_name || 'N/A'}</div>
                    </div>
                </div>

                ${this.parcel.driver_name ? `
                <div class="detail-item-enhanced">
                    <i class="fas fa-user-tie"></i>
                    <div>
                        <div class="label">Assigned Driver</div>
                        <div class="value">${this.parcel.driver_name}</div>
                    </div>
                </div>
                ` : ''}

                <div class="detail-item-enhanced">
                    <i class="fas fa-calendar-plus"></i>
                    <div>
                        <div class="label">Created At</div>
                        <div class="value">${this.formatDateTime(this.parcel.created_at)}</div>
                    </div>
                </div>

                ${this.parcel.updated_at && this.parcel.updated_at !== this.parcel.created_at ? `
                <div class="detail-item-enhanced">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <div class="label">Last Updated</div>
                        <div class="value">${this.formatDateTime(this.parcel.updated_at)}</div>
                    </div>
                </div>
                ` : ''}
            </div>

            <!-- Delivery Map -->
            <div class="detail-section-enhanced">
                <h2><i class="fas fa-map"></i> Delivery Location</h2>
                <div class="map-container-enhanced">
                    <div class="map-overlay">
                        <i class="fas fa-map-marker-alt"></i> Delivery Route
                    </div>
                    <div id="deliveryMap"></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons-enhanced">
                <button class="action-btn-enhanced info" onclick="parcelDetails.openContactModal()">
                    <i class="fas fa-phone"></i> Contact Customer
                </button>
                
                ${status !== 'delivered' ? `
                <button class="action-btn-enhanced primary" onclick="parcelDetails.openStatusModal()">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                ` : ''}
                
                ${status === 'pending' ? `
                <button class="action-btn-enhanced success" onclick="parcelDetails.openDriverModal()">
                    <i class="fas fa-user-tie"></i> Assign Driver
                </button>
                ` : ''}
                
                <button class="action-btn-enhanced primary" onclick="parcelDetails.printDetails()">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        `;
    }

    getStatusIcon(status) {
        const icons = {
            'pending': 'fas fa-clock',
            'assigned': 'fas fa-user-check',
            'in_transit': 'fas fa-truck',
            'delivered': 'fas fa-check-circle',
            'returned': 'fas fa-undo'
        };
        return icons[status] || 'fas fa-info-circle';
    }

    formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        } catch (error) {
            return dateString;
        }
    }

    initializeMap() {
        if (this.map) {
            this.map.remove();
        }

        setTimeout(() => {
            const mapElement = document.getElementById('deliveryMap');
            if (!mapElement) return;

            
            let lat = 14.5995;
            let lng = 120.9842;

            
            if (this.parcel && this.parcel.receiver_latitude && this.parcel.receiver_longitude) {
                lat = parseFloat(this.parcel.receiver_latitude);
                lng = parseFloat(this.parcel.receiver_longitude);
            }

            this.map = L.map('deliveryMap').setView([lat, lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);

            
            const marker = L.marker([lat, lng]).addTo(this.map);
            marker.bindPopup(`
                <div>
                    <strong>Delivery Address</strong><br>
                    ${this.parcel.receiver_address || 'N/A'}<br>
                    <small>Contact: ${this.parcel.receiver_contact || 'N/A'}</small>
                </div>
            `).openPopup();
        }, 100);
    }

    openContactModal() {
        if (!this.parcel) return;
        
        const modal = this.createModal('Contact Customer', `
            <div class="detail-item-enhanced">
                <i class="fas fa-user"></i>
                <div>
                    <div class="label">Customer Name</div>
                    <div class="value">${this.parcel.receiver_name || 'N/A'}</div>
                </div>
            </div>
            <div class="detail-item-enhanced">
                <i class="fas fa-phone"></i>
                <div>
                    <div class="label">Phone Number</div>
                    <div class="value">
                        <a href="tel:${this.parcel.receiver_contact}" class="action-btn-enhanced primary">
                            <i class="fas fa-phone"></i> Call ${this.parcel.receiver_contact}
                        </a>
                    </div>
                </div>
            </div>
            <div class="detail-item-enhanced">
                <i class="fas fa-sms"></i>
                <div>
                    <div class="label">SMS</div>
                    <div class="value">
                        <a href="sms:${this.parcel.receiver_contact}" class="action-btn-enhanced info">
                            <i class="fas fa-comment"></i> Send SMS
                        </a>
                    </div>
                </div>
            </div>
        `);
        
        document.body.appendChild(modal);
        modal.style.display = 'block';
    }

    openStatusModal() {
        const statuses = [
            { value: 'pending', label: 'Pending', icon: 'fas fa-clock' },
            { value: 'assigned', label: 'Assigned', icon: 'fas fa-user-check' },
            { value: 'in_transit', label: 'In Transit', icon: 'fas fa-truck' },
            { value: 'delivered', label: 'Delivered', icon: 'fas fa-check-circle' },
            { value: 'returned', label: 'Returned', icon: 'fas fa-undo' }
        ];

        const statusOptions = statuses.map(status => `
            <label class="status-option">
                <input type="radio" name="status" value="${status.value}" 
                       ${this.parcel.status === status.value ? 'checked' : ''}>
                <span class="status-label">
                    <i class="${status.icon}"></i>
                    ${status.label}
                </span>
            </label>
        `).join('');

        const modal = this.createModal('Update Status', `
            <form id="statusUpdateForm">
                <div class="status-options">
                    ${statusOptions}
                </div>
                <div style="margin-top: 1.5rem;">
                    <label for="status_notes" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Additional Notes (Optional):
                    </label>
                    <textarea id="status_notes" name="notes" rows="3" 
                              style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px;"></textarea>
                </div>
            </form>
        `, {
            primaryAction: {
                text: 'Update Status',
                onClick: () => this.updateParcelStatus()
            }
        });

        document.body.appendChild(modal);
        modal.style.display = 'block';
    }

    async updateParcelStatus() {
        const form = document.getElementById('statusUpdateForm');
        if (!form) return;

        const formData = new FormData(form);
        const status = formData.get('status');
        const notes = formData.get('notes');

        if (!status) {
            alert('Please select a status');
            return;
        }

        const button = document.querySelector('.modal-footer .action-btn-enhanced.primary');
        if (button) {
            button.classList.add('loading');
            button.textContent = 'Updating...';
        }

        try {
            const response = await fetch('api/update_parcel_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    parcel_id: this.parcel.id,
                    status: status,
                    notes: notes
                })
            });

            const result = await response.json();

            if (result.success) {
                
                document.querySelector('.modal-enhanced').remove();
                
                
                this.showLoadingSkeleton();
                await this.loadParcelData();
                
                
                this.showNotification('Status updated successfully!', 'success');
            } else {
                throw new Error(result.error || 'Failed to update status');
            }
        } catch (error) {
            console.error('🔥 Error updating status:', error);
            this.showNotification('Failed to update status: ' + error.message, 'error');
        }
    }

    createModal(title, content, actions = {}) {
        const modal = document.createElement('div');
        modal.className = 'modal-enhanced';
        
        modal.innerHTML = `
            <div class="modal-content-enhanced">
                <div class="modal-header">
                    <h2>${title}</h2>
                    <button class="modal-close-btn" onclick="this.closest('.modal-enhanced').remove()">
                        &times;
                    </button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    <button class="action-btn-enhanced" style="background: #6b7280;" 
                            onclick="this.closest('.modal-enhanced').remove()">
                        Cancel
                    </button>
                    ${actions.primaryAction ? `
                        <button class="action-btn-enhanced primary" onclick="${actions.primaryAction.onClick.toString().replace('function', '').replace('{', '').replace('}', '')}">
                            ${actions.primaryAction.text}
                        </button>
                    ` : ''}
                </div>
            </div>
        `;

        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });

        return modal;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            ${type === 'success' ? 'background: #10b981;' : 'background: #ef4444;'}
        `;
        
        notification.textContent = message;
        document.body.appendChild(notification);

        
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);

        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    showErrorState(message) {
        const container = document.getElementById('parcel-details-container');
        if (container) {
            container.innerHTML = `
                <div class="detail-section-enhanced" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                    <h2 style="color: #ef4444; margin-bottom: 1rem;">Error Loading Parcel</h2>
                    <p style="color: #6b7280; margin-bottom: 2rem;">${message}</p>
                    <button class="action-btn-enhanced primary" onclick="location.reload()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
        }
    }

    updatePageTitle() {
        if (this.parcel && this.parcel.tracking_number) {
            document.title = `Parcel ${this.parcel.tracking_number} - WD Parcel`;
        }
    }

    printDetails() {
        window.print();
    }

    initializeEventListeners() {
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal-enhanced');
                if (modal) {
                    modal.remove();
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.parcelDetails = new ParcelDetailsEnhanced();
});

const statusCSS = `
.status-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.status-option:hover {
    border-color: #2E0D2A;
    background: #f8fafc;
}

.status-option input[type="radio"] {
    margin: 0;
}

.status-option input[type="radio"]:checked + .status-label {
    color: #2E0D2A;
    font-weight: 600;
}

.status-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.notification {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}
`;

const style = document.createElement('style');
style.textContent = statusCSS;
document.head.appendChild(style);

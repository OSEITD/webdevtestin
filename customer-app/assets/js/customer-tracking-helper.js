
class CustomerTrackingHelper {
    constructor(apiBaseUrl = '/customer-app/api') {
        this.apiBaseUrl = apiBaseUrl;
    }

    async trackParcel(trackNumber) {
        if (!trackNumber || trackNumber.trim() === '') {
            throw new Error('Tracking number is required');
        }

        try {
            const response = await fetch(
                `${this.apiBaseUrl}/customer_tracking.php?track_number=${encodeURIComponent(trackNumber)}`
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to track parcel');
            }

            return data;

        } catch (error) {
            console.error('Tracking error:', error);
            throw error;
        }
    }

    isTrackingVisible(trackingData) {
        return trackingData.tracking_visible === true;
    }

    hasArrived(trackingData) {
        return trackingData.parcel?.has_arrived === true;
    }

    isInTransit(trackingData) {
        return trackingData.parcel?.is_in_transit === true;
    }

    getDestinationCoordinates(trackingData) {
        const dest = trackingData.destination;
        
        if (!dest || !dest.latitude || !dest.longitude) {
            return null;
        }

        return {
            latitude: parseFloat(dest.latitude),
            longitude: parseFloat(dest.longitude)
        };
    }

    getStatusColor(trackingData) {
        return trackingData.parcel?.status_display?.color || '#6b7280';
    }

    getStatusIcon(trackingData) {
        return trackingData.parcel?.status_display?.icon || 'box';
    }

    formatStatus(trackingData) {
        const display = trackingData.parcel?.status_display || {};
        
        return {
            label: display.label || 'Unknown',
            description: display.description || 'Status unavailable',
            color: display.color || '#6b7280',
            icon: display.icon || 'box'
        };
    }

    getEstimatedArrival(trackingData) {
        const eta = trackingData.parcel?.estimated_delivery_date;
        return eta ? new Date(eta) : null;
    }

    getDeliveryDate(trackingData) {
        const delivered = trackingData.parcel?.delivered_at;
        return delivered ? new Date(delivered) : null;
    }

    createDestinationMarker(trackingData, L) {
        const coords = this.getDestinationCoordinates(trackingData);
        
        if (!coords) {
            return null;
        }

        const icon = L.divIcon({
            className: 'customer-destination-marker',
            html: '<i class="fas fa-map-marker-alt"></i>',
            iconSize: [50, 50],
            iconAnchor: [25, 50]
        });

        const marker = L.marker([coords.latitude, coords.longitude], { icon });

        const destination = trackingData.destination;
        const parcel = trackingData.parcel;
        
        const popupContent = `
            <div style="font-family: Poppins; padding: 10px; min-width: 200px;">
                <h3 style="margin: 0 0 10px 0; color: #10b981;">
                    <i class="fas fa-store"></i> ${destination.outlet_name}
                </h3>
                <p style="margin: 5px 0; color: #6b7280;">
                    <i class="fas fa-map-marker-alt"></i> ${destination.address || 'Address not available'}
                </p>
                ${destination.contact_phone ? `
                    <p style="margin: 5px 0; color: #6b7280;">
                        <i class="fas fa-phone"></i> ${destination.contact_phone}
                    </p>
                ` : ''}
                ${parcel.has_arrived ? `
                    <p style="margin: 10px 0 0 0; padding: 8px; background: #d1fae5; color: #065f46; border-radius: 6px; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Your parcel is ready for pickup!
                    </p>
                ` : `
                    <p style="margin: 10px 0 0 0; padding: 8px; background: #dbeafe; color: #1e40af; border-radius: 6px;">
                        <i class="fas fa-info-circle"></i> Your parcel will arrive here
                    </p>
                `}
            </div>
        `;

        marker.bindPopup(popupContent);

        return marker;
    }

    static generateTrackingUrl(trackNumber, baseUrl = window.location.origin) {
        return `${baseUrl}/customer-app/track_parcel.php?track=${encodeURIComponent(trackNumber)}`;
    }

    static generateTrackingQR(trackNumber, container) {
        if (typeof QRCode === 'undefined') {
            console.error('QRCode library not loaded. Include qrcode.js first.');
            return;
        }

        const url = CustomerTrackingHelper.generateTrackingUrl(trackNumber);
        
        new QRCode(container, {
            text: url,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    getTimeline(trackingData) {
        if (!trackingData.history || !Array.isArray(trackingData.history)) {
            return [];
        }

        return trackingData.history.map(item => ({
            status: item.status,
            label: item.status_display?.label || item.status,
            description: item.status_display?.description || '',
            icon: item.status_display?.icon || 'circle',
            color: item.status_display?.color || '#6b7280',
            timestamp: new Date(item.timestamp)
        }));
    }

    shouldShowDriverInfo(trackingData) {
        return false;
    }

    static validateTrackingNumber(trackNumber) {
        if (!trackNumber || typeof trackNumber !== 'string') {
            return false;
        }

        const regex = /^[A-Z0-9]{3,20}$/i;
        return regex.test(trackNumber.trim());
    }

    static formatPhone(phone) {
        if (!phone) return 'N/A';
        
        const cleaned = phone.toString().replace(/\D/g, '');
        
        if (cleaned.length === 10) {
            return `${cleaned.slice(0, 4)} ${cleaned.slice(4, 7)} ${cleaned.slice(7)}`;
        }
        
        return phone;
    }

    static formatDate(date) {
        if (!date) return 'N/A';
        
        const d = date instanceof Date ? date : new Date(date);
        
        if (isNaN(d.getTime())) return 'Invalid date';
        
        return d.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    static formatTimestamp(timestamp) {
        if (!timestamp) return 'N/A';
        
        const d = timestamp instanceof Date ? timestamp : new Date(timestamp);
        
        if (isNaN(d.getTime())) return 'Invalid timestamp';
        
        return d.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CustomerTrackingHelper;
}

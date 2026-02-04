

class TripParcelBridgeClient {
    constructor() {
        this.apiUrl = './api/trip_parcel_bridge.php';
        this.context = null;
        this.init();
    }

    async init() {
        try {
            await this.loadContext();
            this.setupEventListeners();
            this.displayWorkflowSuggestions();
        } catch (error) {
            console.error('Bridge initialization failed:', error);
        }
    }

    
    async loadContext() {
        try {
            const response = await fetch(this.apiUrl);
            const data = await response.json();
            
            if (data.success) {
                this.context = data.context;
                return data;
            }
            throw new Error(data.error || 'Failed to load context');
        } catch (error) {
            console.error('Context loading error:', error);
            return null;
        }
    }

    
    async setActiveTrip(tripId, tripDetails = {}) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'set_active_trip',
                    trip_id: tripId,
                    trip_details: tripDetails
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'success');
                
                if (result.redirect_suggestion) {
                    setTimeout(() => {
                        if (confirm('Trip selected! Would you like to register parcels for this trip?')) {
                            window.location.href = result.redirect_suggestion;
                        }
                    }, 1000);
                }
                
                return result;
            }
            throw new Error(result.error);
        } catch (error) {
            console.error('Set active trip error:', error);
            this.showNotification('Failed to set active trip', 'error');
        }
    }

    
    async addPendingParcel(parcelId, parcelDetails = {}) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_pending_parcel',
                    parcel_id: parcelId,
                    parcel_details: parcelDetails
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message, 'info');
                this.displayWorkflowSuggestions(); 
                return result;
            }
            throw new Error(result.error);
        } catch (error) {
            console.error('Add pending parcel error:', error);
            this.showNotification('Failed to add parcel to pending list', 'error');
        }
    }

    
    async displayWorkflowSuggestions() {
        try {
            const data = await this.loadContext();
            if (!data || !data.suggestions) return;

            const container = this.getSuggestionContainer();
            if (!container) return;

            let html = '';
            data.suggestions.forEach(suggestion => {
                html += `
                    <div class="workflow-suggestion ${suggestion.type}">
                        <div class="suggestion-content">
                            <i class="fas ${this.getSuggestionIcon(suggestion.type)}"></i>
                            <span>${suggestion.message}</span>
                        </div>
                        <button class="suggestion-action" onclick="window.location.href='${suggestion.action}'">
                            ${suggestion.action_text}
                        </button>
                    </div>
                `;
            });

            if (html) {
                container.innerHTML = `
                    <div class="suggestions-header">
                        <h4><i class="fas fa-lightbulb"></i> Workflow Suggestions</h4>
                        <button onclick="bridgeClient.dismissSuggestions()" class="dismiss-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="suggestions-content">
                        ${html}
                    </div>
                `;
                container.style.display = 'block';
            }

        } catch (error) {
            console.error('Display suggestions error:', error);
        }
    }

    
    getSuggestionContainer() {
        let container = document.getElementById('workflowSuggestions');
        if (!container) {
            container = document.createElement('div');
            container.id = 'workflowSuggestions';
            container.className = 'workflow-suggestions';
            
            
            const mainContent = document.querySelector('.main-content, .content-container');
            if (mainContent) {
                mainContent.insertBefore(container, mainContent.firstChild);
            } else {
                document.body.appendChild(container);
            }
        }
        return container;
    }

    
    getSuggestionIcon(type) {
        const icons = {
            'success': 'fa-check-circle',
            'info': 'fa-info-circle',
            'warning': 'fa-exclamation-triangle',
            'error': 'fa-times-circle'
        };
        return icons[type] || 'fa-info-circle';
    }

    
    dismissSuggestions() {
        const container = document.getElementById('workflowSuggestions');
        if (container) {
            container.style.display = 'none';
        }
    }

    
    showNotification(message, type = 'info', duration = 4000) {
        
        document.querySelectorAll('.bridge-notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `bridge-notification ${type}`;
        notification.innerHTML = `
            <i class="fas ${this.getSuggestionIcon(type)}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" class="close-btn">×</button>
        `;

        document.body.appendChild(notification);

        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }

    
    setupEventListeners() {
        
        document.addEventListener('tripCreated', (event) => {
            if (event.detail && event.detail.tripId) {
                this.setActiveTrip(event.detail.tripId, event.detail.tripData);
            }
        });

        
        document.addEventListener('parcelRegistered', (event) => {
            if (event.detail && event.detail.parcelId && !event.detail.tripAssigned) {
                this.addPendingParcel(event.detail.parcelId, event.detail.parcelData);
            }
        });

        
        const urlParams = new URLSearchParams(window.location.search);
        const contextParam = urlParams.get('context');
        if (contextParam) {
            try {
                const context = JSON.parse(decodeURIComponent(contextParam));
                console.log('Received navigation context:', context);
            } catch (error) {
                console.error('Invalid navigation context:', error);
            }
        }
    }

    
    async resetBridge() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reset_bridge'
                })
            });

            const result = await response.json();
            if (result.success) {
                this.showNotification('Workflow reset', 'success');
                this.dismissSuggestions();
                await this.loadContext();
            }
        } catch (error) {
            console.error('Reset bridge error:', error);
        }
    }
}

let bridgeClient = null;

document.addEventListener('DOMContentLoaded', () => {
    bridgeClient = new TripParcelBridgeClient();
});

window.TripParcelBridge = {
    setActiveTrip: (tripId, tripDetails) => bridgeClient?.setActiveTrip(tripId, tripDetails),
    addPendingParcel: (parcelId, parcelDetails) => bridgeClient?.addPendingParcel(parcelId, parcelDetails),
    reset: () => bridgeClient?.resetBridge(),
    getInstance: () => bridgeClient
};

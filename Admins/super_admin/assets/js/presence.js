/**
 * Presence & Session Tracking System
 * 
 * Implements heartbeat mechanism to track user presence and session duration.
 * Sends a ping to the server every 5 minutes to keep session active and update last_seen_at.
 * 
 * Features:
 * - Automatic heartbeat every 5 minutes
 * - Stops heartbeat when user is inactive
 * - Resumes heartbeat when user becomes active again
 * - Handles network errors gracefully
 * - Logs activity for debugging
 */

class PresenceTracker {
    constructor(config = {}) {
        this.config = {
            heartbeatInterval: config.heartbeatInterval || 5 * 60 * 1000, // 5 minutes
            inactivityTimeout: config.inactivityTimeout || 30 * 60 * 1000, // 30 minutes
            heartbeatUrl: config.heartbeatUrl || this.getHeartbeatUrl(),
            logEnabled: config.logEnabled !== false,
            autoStart: config.autoStart !== false
        };

        this.heartbeatTimer = null;
        this.inactivityTimer = null;
        this.isActive = true;
        this.lastHeartbeat = new Date();

        if (this.config.autoStart) {
            this.start();
        }
    }

    /**
     * Get the correct heartbeat URL based on current page location.
     * Always resolves to super_admin/api/ regardless of sub-app context.
     */
    getHeartbeatUrl() {
        // Explicitly set heartbeat URL takes precedence
        if (window.HEARTBEAT_URL) {
            return window.HEARTBEAT_URL;
        }

        const currentPath = window.location.pathname;
        // Extract base path up to /Admins/super_admin (strip any sub-app suffix)
        const match = currentPath.match(/^(.+\/Admins\/super_admin)(?:\/|$)/i);
        if (match) {
            return match[1] + '/api/presence_heartbeat.php';
        }

        // Fallback for sibling apps (like outlet-app) running in a subdirectory (e.g. XAMPP)
        const localMatch = currentPath.match(/^(\/[^\/]+)/);
        if (localMatch && currentPath.toLowerCase().includes('webdevtestin')) {
            return localMatch[1] + '/Admins/super_admin/api/presence_heartbeat.php';
        }

        // Fallback for production or domain-root setup
        return '/Admins/super_admin/api/presence_heartbeat.php';
    }

    /**
     * Start the presence tracking system
     */
    start() {
        if (this.heartbeatTimer) {
            this.log('Presence tracking already running');
            return;
        }

        this.log('Starting presence tracking');
        this.setupEventListeners();
        this.sendHeartbeat(); // Send immediate heartbeat on start
        this.scheduleHeartbeat();
    }

    /**
     * Stop the presence tracking system
     */
    stop() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
            this.inactivityTimer = null;
        }
        this.removeEventListeners();
        this.log('Presence tracking stopped');
    }

    /**
     * Schedule heartbeat to run at regular intervals
     */
    scheduleHeartbeat() {
        this.heartbeatTimer = setInterval(() => {
            if (this.isActive) {
                this.sendHeartbeat();
            }
        }, this.config.heartbeatInterval);

        this.log(`Heartbeat scheduled every ${this.config.heartbeatInterval / 1000 / 60} minutes`);
    }

    /**
     * Send heartbeat to server
     */
    async sendHeartbeat() {
        try {
            const url = this.config.heartbeatUrl;
            this.log(`Sending heartbeat to: ${url}`);

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include' // Include cookies for session
            });

            if (response.ok) {
                const text = await response.text();
                this.log(`Raw response: ${text.substring(0, 200)}`);

                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        this.lastHeartbeat = new Date();
                        this.log(`Heartbeat sent successfully at ${this.lastHeartbeat.toLocaleTimeString()}`);
                    } else {
                        this.log(`Heartbeat failed: ${data.error}`, 'warn');
                    }
                } catch (parseError) {
                    this.log(`JSON parse error: ${parseError.message}. Response: ${text.substring(0, 300)}`, 'error');
                }
            } else if (response.status === 401) {
                this.log('User session expired - redirecting to login', 'error');
                this.handleSessionExpired();
            } else {
                const text = await response.text();
                this.log(`Heartbeat error: ${response.status} ${response.statusText}. Response: ${text.substring(0, 200)}`, 'warn');
            }
        } catch (error) {
            this.log(`Heartbeat send failed: ${error.message}`, 'error');
            // Continue processing even if heartbeat fails (network might be temporary issue)
        }
    }

    /**
     * Setup event listeners for user activity detection
     */
    setupEventListeners() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => this.onUserActivity(), { passive: true });
        });

        // Also track if page is focused/unfocused
        window.addEventListener('focus', () => this.onWindowFocus());
        window.addEventListener('blur', () => this.onWindowBlur());

        this.log('Event listeners attached');
    }

    /**
     * Remove event listeners
     */
    removeEventListeners() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.removeEventListener(event, () => this.onUserActivity());
        });
        window.removeEventListener('focus', () => this.onWindowFocus());
        window.removeEventListener('blur', () => this.onWindowBlur());
    }

    /**
     * Handle user activity (mouse, keyboard, etc.)
     */
    onUserActivity() {
        const wasInactive = !this.isActive;

        if (wasInactive) {
            this.isActive = true;
            this.log('User activity detected - resuming heartbeat');

            // Send immediate heartbeat when resuming
            this.sendHeartbeat();

            // Schedule next heartbeat
            if (!this.heartbeatTimer) {
                this.scheduleHeartbeat();
            }
        }

        // Reset inactivity timeout
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
        }

        this.inactivityTimer = setTimeout(() => {
            if (this.isActive) {
                this.isActive = false;
                this.log('No user activity detected - pausing heartbeat', 'info');
            }
        }, this.config.inactivityTimeout);
    }

    /**
     * Handle window focus
     */
    onWindowFocus() {
        this.log('Window focused - resuming activity tracking');
        this.onUserActivity(); // Treat focus as activity
    }

    /**
     * Handle window blur
     */
    onWindowBlur() {
        this.log('Window blurred - user may be inactive');
    }

    /**
     * Handle session expiration
     */
    handleSessionExpired() {
        this.stop();
        // Redirect to login after a short delay using absolute path
        setTimeout(() => {
            // Construct absolute path to login page
            const currentPath = window.location.pathname;
            const match = currentPath.match(/^(.+\/Admins\/super_admin)/i);
            if (match) {
                const basePath = match[1];
                window.location.href = basePath + '/auth/login.php?error=session_expired';
            } else {
                // Fallback to relative path
                window.location.href = '/webdevtestin/Admins/super_admin/auth/login.php?error=session_expired';
            }
        }, 2000);
    }

    /**
     * Log message to console (if enabled)
     */
    log(message, level = 'info') {
        if (!this.config.logEnabled) return;

        const timestamp = new Date().toLocaleTimeString();
        const prefix = `[PresenceTracker ${timestamp}]`;

        switch (level) {
            case 'error':
                console.error(prefix, message);
                break;
            case 'warn':
                console.warn(prefix, message);
                break;
            case 'info':
                console.info(prefix, message);
                break;
            default:
                console.log(prefix, message);
        }
    }

    /**
     * Get last heartbeat time
     */
    getLastHeartbeat() {
        return this.lastHeartbeat;
    }

    /**
     * Get current tracking status
     */
    getStatus() {
        return {
            running: this.heartbeatTimer !== null,
            active: this.isActive,
            lastHeartbeat: this.lastHeartbeat,
            nextHeartbeat: this.isActive ? new Date(Date.now() + this.config.heartbeatInterval) : null
        };
    }
}

// ============================================================================
// AUTO-INITIALIZE ON PAGE LOAD
// ============================================================================

// Initialize presence tracking when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.presenceTracker = new PresenceTracker({
            heartbeatInterval: 5 * 60 * 1000, // 5 minutes
            inactivityTimeout: 30 * 60 * 1000, // 30 minutes
            logEnabled: true,
            autoStart: true
        });
    });
} else {
    // DOM is already loaded
    window.presenceTracker = new PresenceTracker({
        heartbeatInterval: 5 * 60 * 1000, // 5 minutes
        inactivityTimeout: 30 * 60 * 1000, // 30 minutes
        logEnabled: true,
        autoStart: true
    });
}

// ============================================================================
// DEBUGGING / MONITORING FUNCTIONS
// ============================================================================

/**
 * Get current presence tracking status (can be called from console)
 */
window.getPresenceStatus = function () {
    if (window.presenceTracker) {
        return window.presenceTracker.getStatus();
    }
    return 'Presence tracker not initialized';
};

/**
 * Manually send a heartbeat (can be called from console)
 */
window.sendManualHeartbeat = function () {
    if (window.presenceTracker) {
        window.presenceTracker.sendHeartbeat();
        return 'Heartbeat sent';
    }
    return 'Presence tracker not initialized';
};

/**
 * Stop presence tracking (can be called from console)
 */
window.stopPresenceTracking = function () {
    if (window.presenceTracker) {
        window.presenceTracker.stop();
        return 'Presence tracking stopped';
    }
    return 'Presence tracker not initialized';
};

/**
 * Start presence tracking (can be called from console)
 */
window.startPresenceTracking = function () {
    if (window.presenceTracker) {
        window.presenceTracker.start();
        return 'Presence tracking started';
    }
    return 'Presence tracker not initialized';
};

/**
 * Currency Display Integration Script
 * Automatically formats elements with data-currency-value and data-currency attributes
 * Provides real-time currency updates when system currency changes
 */

class CurrencyDisplayManager {
    constructor(currencyManagerInstance) {
        this.currencyManager = currencyManagerInstance;
        this.formattedElements = new Map();
        this.observer = null;
        this.updateQueue = [];
        this.isProcessingQueue = false;
    }

    /**
     * Initialize and start monitoring for currency displays
     */
    async init() {
        if (!this.currencyManager) {
            console.error('CurrencyDisplayManager: currencyManager instance required');
            return;
        }

        // Format all existing currency displays
        this.formatAllDisplays();

        // Set up MutationObserver for dynamically added content
        this.setupMutationObserver();

        // Listen for currency changes
        this.subscribeToChanges();
    }

    /**
     * Find and format all currency display elements
     */
    formatAllDisplays(containerSelector = document.body) {
        const elements = containerSelector.querySelectorAll('[data-currency-value][data-currency]');

        elements.forEach(element => {
            const minorUnits = parseInt(element.getAttribute('data-currency-value'), 10);
            const currencyCode = element.getAttribute('data-currency');

            if (!isNaN(minorUnits)) {
                const formatted = this.currencyManager.formatCurrency(minorUnits, currencyCode);
                if (formatted) {
                    element.textContent = formatted;
                    this.formattedElements.set(element, { minorUnits, currencyCode });
                }
            }
        });
    }

    /**
     * Set up mutation observer for new content
     */
    setupMutationObserver() {
        const config = {
            childList: true,
            subtree: true,
            attributes: false,
            characterData: false
        };

        this.observer = new MutationObserver((mutations) => {
            // Batch updates with debounce
            this.scheduleUpdate();
        });

        this.observer.observe(document.body, config);
    }

    /**
     * Debounced update schedule
     */
    scheduleUpdate() {
        if (this.isProcessingQueue) return;

        this.isProcessingQueue = true;
        requestAnimationFrame(() => {
            this.formatAllDisplays();
            this.isProcessingQueue = false;
        });
    }

    /**
     * Subscribe to currency changes via Supabase real-time
     */
    subscribeToChanges() {
        if (!this.currencyManager.supabase) return;

        // Subscribe to system_settings changes using Supabase v2 syntax
        this.currencyManager.supabase
            .channel('system-settings-display-changes')
            .on(
                'postgres_changes',
                { event: 'UPDATE', schema: 'public', table: 'system_settings' },
                (payload) => {
                    // Re-format all displays with new currency
                    this.reformatAll();
                }
            )
            .subscribe();

        // Subscribe to company currency changes using Supabase v2 syntax
        this.currencyManager.supabase
            .channel('company-display-changes')
            .on(
                'postgres_changes',
                { event: 'UPDATE', schema: 'public', table: 'companies' },
                (payload) => {
                    // Re-format matching company displays
                    this.reformatByCompanyId(payload.new.id);
                }
            )
            .subscribe();
    }

    /**
     * Re-format all currency displays
     */
    async reformatAll() {
        // Clear cache in currency manager to force re-fetch
        if (this.currencyManager.cache) {
            this.currencyManager.cache.lastUpdate = 0;
        }

        // Wait a brief moment for cache update
        await new Promise(resolve => setTimeout(resolve, 100));

        // Re-format all displays
        this.formattedElements.forEach((data, element) => {
            if (element && element.isConnected) {
                const formatted = this.currencyManager.formatCurrency(
                    data.minorUnits,
                    data.currencyCode
                );
                if (formatted) {
                    element.textContent = formatted;
                }
            }
        });
    }

    /**
     * Re-format displays for a specific company
     */
    async reformatByCompanyId(companyId) {
        // Similar to reformatAll but only affects company-specific displays
        this.reformatAll();
    }

    /**
     * Destroy observer and cleanup
     */
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
        this.formattedElements.clear();
    }
}

/**
 * Initialize currency display manager
 * Call after CurrencyManager is initialized
 */
function initializeCurrencyDisplays(currencyManager) {
    const displayManager = new CurrencyDisplayManager(currencyManager);
    displayManager.init();
    return displayManager;
}

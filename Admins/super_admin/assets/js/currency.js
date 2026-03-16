/**
 * Centralized Currency Management System
 * =====================================
 * Production-ready module for handling multi-currency formatting and display
 * 
 * Features:
 * - Real-time currency updates via Supabase
 * - Integer-based storage (prevents floating-point errors)
 * - Automatic DOM updates without full page refresh
 * - Support for global system currency + per-company overrides
 * - Performance optimized with batched updates
 * 
 * @requires supabase-js
 */

class CurrencyManager {
    static getInstance() {
        if (!CurrencyManager.instance) {
            CurrencyManager.instance = new CurrencyManager();
        }
        return CurrencyManager.instance;
    }

    constructor() {
        // Cache for settings
        this.cache = {
            globalCurrency: 'USD',
            currencyConfig: {},
            companyCurrencies: {},
            lastUpdate: 0
        };

        // Configuration
        this.config = {
            cacheMaxAge: 5 * 60 * 1000, // 5 minutes
            batchUpdateDelay: 500, // 500ms for DOM batching
            observerConfig: {
                attributes: true,
                attributeFilter: ['data-currency-value', 'data-minor-units'],
                subtree: true
            }
        };

        // State
        this.initialized = false;
        this.supabase = null;
        this.currentUserId = null;
        this.updateQueue = new Map();
        this.updateTimer = null;
        this.subscription = null;
    }

    /**
     * Initialize the currency manager
     * @param {object} supabaseClient - Supabase client instance
     * @param {string} userId - Current authenticated user ID
     * @returns {Promise<boolean>} Success status
     */
    async init(supabaseClient, userId) {
        try {
            this.supabase = supabaseClient;
            this.currentUserId = userId;

            // Load initial settings - FORCE REFRESH to avoid stale cache
            await this.loadSettings(true);

            // Set up real-time subscriptions
            this.subscribeToChanges();

            // Observe DOM for currency elements
            this.observeCurrencyElements();

            this.initialized = true;
            console.log('✓ CurrencyManager initialized successfully');
            return true;
        } catch (error) {
            console.error('✗ CurrencyManager initialization failed:', error);
            return false;
        }
    }

    /**
     * Load settings from Supabase (with caching)
     * @returns {Promise<boolean>}
     */
    async loadSettings(forceRefresh = false) {
        // Check cache validity
        const now = Date.now();
        if (!forceRefresh && this.cache.lastUpdate && (now - this.cache.lastUpdate) < this.config.cacheMaxAge) {
            return true; // Cache still valid
        }

        try {
            // Fetch global settings
            const { data: settings, error: settingsError } = await this.supabase
                .from('system_settings')
                .select('global_currency, currency_config')
                .limit(1)
                .maybeSingle();

            if (settingsError && settingsError.code !== 'PGRST116') throw settingsError;

            this.cache.globalCurrency = settings?.global_currency || 'USD';
            this.cache.currencyConfig = settings?.currency_config || this.getDefaultConfig();
            this.cache.lastUpdate = now;

            // Dispatch custom event for other modules
            this.dispatchEvent('currencySettingsUpdated', {
                globalCurrency: this.cache.globalCurrency,
                config: this.cache.currencyConfig
            });

            return true;
        } catch (error) {
            console.error('✗ Failed to load settings:', error);
            // Use defaults on failure
            this.cache.currencyConfig = this.getDefaultConfig();
            return false;
        }
    }

    /**
     * Get default currency configuration for common currencies
     * @returns {object}
     */
    getDefaultConfig() {
        return {
            USD: { minor_units: 2, symbol: '$', name: 'US Dollar' },
            EUR: { minor_units: 2, symbol: '€', name: 'Euro' },
            GBP: { minor_units: 2, symbol: '£', name: 'British Pound' },
            ZMW: { minor_units: 2, symbol: 'ZK', name: 'Zambian Kwacha' },
            NGN: { minor_units: 2, symbol: '₦', name: 'Nigerian Naira' },
            ZAR: { minor_units: 2, symbol: 'R', name: 'South African Rand' },
            KES: { minor_units: 2, symbol: 'Ksh', name: 'Kenyan Shilling' },
            UGX: { minor_units: 0, symbol: 'Ush', name: 'Ugandan Shilling' },
            JPY: { minor_units: 0, symbol: '¥', name: 'Japanese Yen' }
        };
    }

    /**
     * Get the effective currency for a company or default to global
     * @param {string} companyId - UUID of company (optional)
     * @returns {string} ISO 4217 currency code
     */
    getActiveCurrency(companyId = null) {
        if (companyId && this.cache.companyCurrencies[companyId]) {
            return this.cache.companyCurrencies[companyId];
        }
        return this.cache.globalCurrency;
    }

    /**
     * Format amount in minor units to display string
     * 
     * Example:
     *   formatCurrency(2500, 'USD') → "$25.00"
     *   formatCurrency(250000, 'ZMW') → "ZK2,500.00"
     *   formatCurrency(100000, 'JPY') → "¥100,000"
     * 
     * @param {number} minorUnits - Amount in smallest currency unit
     * @param {string} currencyCode - ISO 4217 code (defaults to global)
     * @param {object} options - Formatting options
     * @returns {string} Formatted currency display
     */
    formatCurrency(minorUnits, currencyCode = null, options = {}) {
        try {
            // Validate and normalize input
            const amount = Number(minorUnits) || 0;
            const currency = currencyCode || this.cache.globalCurrency;

            // Get currency config
            const config = this.cache.currencyConfig[currency] || this.getCurrencyDefaults(currency);
            const { minor_units, symbol } = config;

            // Convert minor units to major units
            const divisor = Math.pow(10, minor_units);
            const majorAmount = amount / divisor;

            // Format with locale and currency
            const formatted = this.formatNumber(majorAmount, minor_units, options);

            // Prefix symbol or use locale formatting
            return options.useLocale ? formatted : `${symbol}${formatted}`;
        } catch (error) {
            console.error('✗ Currency formatting error:', error);
            return '—'; // Display em-dash on error
        }
    }

    /**
     * Helper: Format number with proper thousand separators
     * @private
     */
    formatNumber(amount, decimalPlaces = 2, options = {}) {
        const locale = options.locale || 'en-US';
        const formatter = new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimalPlaces,
            maximumFractionDigits: decimalPlaces,
            useGrouping: true
        });
        return formatter.format(amount);
    }

    /**
     * Helper: Get defaults for unknown currencies
     * @private
     */
    getCurrencyDefaults(code) {
        return {
            minor_units: 2,
            symbol: code,
            name: code
        };
    }

    /**
     * Parse display currency back to minor units
     * 
     * Useful for form inputs before sending to backend
     * 
     * @param {string} displayValue - Formatted display value (e.g., "$25.50")
     * @param {string} currencyCode - ISO 4217 code
     * @returns {number} Amount in minor units
     */
    parseToMinorUnits(displayValue, currencyCode = null) {
        try {
            const currency = currencyCode || this.cache.globalCurrency;
            const config = this.cache.currencyConfig[currency] || this.getCurrencyDefaults(currency);

            // Remove all non-numeric characters except decimal point
            const cleaned = displayValue.replace(/[^\d.]/g, '');
            const majorAmount = parseFloat(cleaned) || 0;

            // Convert to minor units (multiply by divisor)
            const divisor = Math.pow(10, config.minor_units);
            return Math.round(majorAmount * divisor);
        } catch (error) {
            console.error('✗ Currency parsing error:', error);
            return 0;
        }
    }

    /**
     * Batch update all currency displays on page
     * Safe for repeated calls (debounced)
     */
    async refreshMoneyDisplays(targetSelector = '[data-currency-value]') {
        // Clear any pending updates
        if (this.updateTimer) {
            clearTimeout(this.updateTimer);
        }

        // Debounce updates to prevent excessive reflows
        this.updateTimer = setTimeout(() => {
            this.performBatchUpdate(targetSelector);
        }, this.config.batchUpdateDelay);
    }

    /**
     * Perform actual batch DOM updates
     * @private
     */
    performBatchUpdate(selector) {
        try {
            const elements = document.querySelectorAll(selector);

            if (elements.length === 0) return;

            // Use DocumentFragment for batching
            const fragment = document.createDocumentFragment();

            elements.forEach((el) => {
                const minorUnits = el.getAttribute('data-currency-value');
                const currencyCode = el.getAttribute('data-currency') || null;

                if (minorUnits) {
                    const formatted = this.formatCurrency(minorUnits, currencyCode);
                    el.textContent = formatted;
                    el.setAttribute('data-currency-formatted', 'true');
                }
            });

            // Trigger layout update if needed
            this.dispatchEvent('currencyDisplaysRefreshed', { count: elements.length });
        } catch (error) {
            console.error('✗ Batch update failed:', error);
        }
    }

    /**
     * Update a single element's currency display
     */
    updateElementCurrency(element, minorUnits, currencyCode = null) {
        try {
            const formatted = this.formatCurrency(minorUnits, currencyCode);
            element.textContent = formatted;
            element.setAttribute('data-currency-value', minorUnits);
            if (currencyCode) element.setAttribute('data-currency', currencyCode);
        } catch (error) {
            console.error('✗ Element update failed:', error);
        }
    }

    /**
     * Subscribe to real-time currency changes
     * @private
     */
    subscribeToChanges() {
        // Subscribe to system_settings changes using Supabase v2 syntax
        this.subscription = this.supabase
            .channel('system-settings-changes')
            .on(
                'postgres_changes',
                { event: 'UPDATE', schema: 'public', table: 'system_settings' },
                (payload) => {
                    this.cache.globalCurrency = payload.new.global_currency || this.cache.globalCurrency;
                    this.cache.currencyConfig = payload.new.currency_config || this.cache.currencyConfig;
                    this.cache.lastUpdate = 0; // Invalidate cache

                    // Refresh all displays
                    this.refreshMoneyDisplays();

                    this.dispatchEvent('currencyChanged', {
                        type: 'global',
                        newCurrency: this.cache.globalCurrency
                    });
                }
            )
            .subscribe();
    }

    /**
     * Set company-specific currency override
     * (Used when loading company context)
     */
    setCompanyCurrency(companyId, currencyCode) {
        this.cache.companyCurrencies[companyId] = currencyCode;
    }

    /**
     * Observe DOM for new currency elements and auto-format them
     * @private
     */
    observeCurrencyElements() {
        const observer = new MutationObserver((mutations) => {
            let hasCurrencyChanges = false;

            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length > 0) {
                    const hasNewCurrency = Array.from(mutation.addedNodes).some(node => {
                        return node.nodeType === 1 && (
                            node.hasAttribute('data-currency-value') ||
                            node.querySelector('[data-currency-value]')
                        );
                    });
                    if (hasNewCurrency) hasCurrencyChanges = true;
                }
            });

            if (hasCurrencyChanges) {
                this.refreshMoneyDisplays();
            }
        });

        observer.observe(document.body, this.config.observerConfig);
    }

    /**
     * Helper: Dispatch custom events
     * @private
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, { detail });
        document.dispatchEvent(event);
    }

    /**
     * Clean up resources
     */
    destroy() {
        if (this.subscription) {
            this.subscription.unsubscribe();
        }
        if (this.updateTimer) {
            clearTimeout(this.updateTimer);
        }
        this.initialized = false;
    }

    /**
     * Get current cache state (for debugging)
     */
    getState() {
        return {
            ...this.cache,
            initialized: this.initialized
        };
    }
}

// Export singleton instance
const currencyManager = new CurrencyManager();

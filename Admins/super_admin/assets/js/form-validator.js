/**
 * FormValidator — Reusable client-side form validation utility
 * 
 * Usage:
 *   const validator = new FormValidator('myFormId', {
 *       fieldName: { required: true, email: true, minLength: 3, ... },
 *       ...
 *   });
 *
 * Supported rules:
 *   required, email, phone, minLength, maxLength, numeric,
 *   subdomain, match (field ID), password, custom (function)
 */

/* --- Country code data (code, name, dial, expected local digit count) --- */
const COUNTRY_CODES = [
    { code: 'ZM', name: 'Zambia', dial: '+260', digits: 9, flag: '🇿🇲' },
    { code: 'ZW', name: 'Zimbabwe', dial: '+263', digits: 9, flag: '🇿🇼' },
    { code: 'ZA', name: 'South Africa', dial: '+27', digits: 9, flag: '🇿🇦' },
    { code: 'BW', name: 'Botswana', dial: '+267', digits: 7, flag: '🇧🇼' },
    { code: 'MW', name: 'Malawi', dial: '+265', digits: 9, flag: '🇲🇼' },
    { code: 'MZ', name: 'Mozambique', dial: '+258', digits: 9, flag: '🇲🇿' },
    { code: 'NA', name: 'Namibia', dial: '+264', digits: 9, flag: '🇳🇦' },
    { code: 'TZ', name: 'Tanzania', dial: '+255', digits: 9, flag: '🇹🇿' },
    { code: 'CD', name: 'DR Congo', dial: '+243', digits: 9, flag: '🇨🇩' },
    { code: 'AO', name: 'Angola', dial: '+244', digits: 9, flag: '🇦🇴' },
    { code: 'KE', name: 'Kenya', dial: '+254', digits: 9, flag: '🇰🇪' },
    { code: 'UG', name: 'Uganda', dial: '+256', digits: 9, flag: '🇺🇬' },
    { code: 'NG', name: 'Nigeria', dial: '+234', digits: 10, flag: '🇳🇬' },
    { code: 'GH', name: 'Ghana', dial: '+233', digits: 9, flag: '🇬🇭' },
    { code: 'ET', name: 'Ethiopia', dial: '+251', digits: 9, flag: '🇪🇹' },
    { code: 'RW', name: 'Rwanda', dial: '+250', digits: 9, flag: '🇷🇼' },
    { code: 'SN', name: 'Senegal', dial: '+221', digits: 9, flag: '🇸🇳' },
    { code: 'CM', name: 'Cameroon', dial: '+237', digits: 9, flag: '🇨🇲' },
    { code: 'CI', name: 'Côte d\'Ivoire', dial: '+225', digits: 10, flag: '🇨🇮' },
    { code: 'GB', name: 'United Kingdom', dial: '+44', digits: 10, flag: '🇬🇧' },
    { code: 'US', name: 'United States', dial: '+1', digits: 10, flag: '🇺🇸' },
    { code: 'IN', name: 'India', dial: '+91', digits: 10, flag: '🇮🇳' },
    { code: 'CN', name: 'China', dial: '+86', digits: 11, flag: '🇨🇳' },
    { code: 'AE', name: 'UAE', dial: '+971', digits: 9, flag: '🇦🇪' },
];

class FormValidator {
    /**
     * @param {string} formId - The id of the form element
     * @param {Object} rules - Validation rules keyed by field name
     * @param {Object} [options] - Optional settings
     */
    constructor(formId, rules, options = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error(`FormValidator: form #${formId} not found`);
            return;
        }
        this.rules = rules;
        this.options = Object.assign({
            validateOnBlur: true,
            validateOnInput: true,
            defaultCountryCode: 'ZM',
            scrollToFirstError: true,
        }, options);

        // Track selected country per phone field
        this._phoneCountrySelections = {};

        this.errors = {};
        this._init();
    }

    /* ---------- INITIALIZATION ---------- */

    _init() {
        // Inject error containers for each field
        for (const fieldName of Object.keys(this.rules)) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (!field) continue;

            // Create error element if not present
            const group = field.closest('.form-group');
            if (group && !group.querySelector('.field-error')) {
                const errEl = document.createElement('div');
                errEl.className = 'field-error';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i><span></span>';
                // Insert after the field (or after help-text if exists)
                const helpText = group.querySelector('.help-text');
                const insertAfter = helpText || field;
                insertAfter.parentNode.insertBefore(errEl, insertAfter.nextSibling);
            }

            // Add password strength meter
            if (this.rules[fieldName].password && fieldName !== 'confirm_password') {
                this._addPasswordStrength(field);
            }

            // Add password toggle eye for all password fields
            if (this.rules[fieldName].password) {
                this._addPasswordToggle(field);
            }

            // Phone auto-format
            if (this.rules[fieldName].phone) {
                this._setupPhoneField(field);
            }

            // Real-time validation events
            if (this.options.validateOnBlur) {
                field.addEventListener('blur', () => this.validateField(fieldName));
            }
            if (this.options.validateOnInput) {
                field.addEventListener('input', () => {
                    // Only clear error on input; full validate on blur
                    if (this.errors[fieldName]) {
                        this.validateField(fieldName);
                    }
                    // Update password strength in real-time
                    if (this.rules[fieldName].password && fieldName !== 'confirm_password') {
                        this._updatePasswordStrength(field);
                    }
                });
            }
        }
    }

    /* ---------- VALIDATION ENGINE ---------- */

    /**
     * Validate a single field. Returns true if field is valid.
     */
    validateField(fieldName) {
        const fieldRules = this.rules[fieldName];
        if (!fieldRules) return true;

        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return true;

        const value = field.value.trim();
        let error = null;

        // Required
        if (fieldRules.required && value === '') {
            const label = this._getFieldLabel(field);
            error = `${label} is required`;
        }

        // Min length
        if (!error && fieldRules.minLength && value.length > 0 && value.length < fieldRules.minLength) {
            const label = this._getFieldLabel(field);
            error = `${label} must be at least ${fieldRules.minLength} characters`;
        }

        // Max length
        if (!error && fieldRules.maxLength && value.length > fieldRules.maxLength) {
            const label = this._getFieldLabel(field);
            error = `${label} must be at most ${fieldRules.maxLength} characters`;
        }

        // Email
        if (!error && fieldRules.email && value.length > 0) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                error = 'Please enter a valid email address';
            }
        }

        // Phone — validate local digits against selected country's expected length
        if (!error && fieldRules.phone && value.length > 0) {
            const sel = this._phoneCountrySelections[fieldName];
            const country = sel
                ? COUNTRY_CODES.find(c => c.code === sel)
                : COUNTRY_CODES.find(c => c.code === this.options.defaultCountryCode);

            // Field value is local digits only (formatted with spaces)
            const localDigits = value.replace(/\D/g, '');

            if (country) {
                if (localDigits.length !== country.digits) {
                    const placeholder = 'X'.repeat(country.digits).replace(/(.{3})/g, '$1 ').trim();
                    error = `Phone must be ${country.digits} digits (${country.dial} ${placeholder})`;
                }
            } else {
                if (localDigits.length < 7 || localDigits.length > 15) {
                    error = 'Please enter a valid phone number';
                }
            }
        }

        // Numeric only
        if (!error && fieldRules.numeric && value.length > 0) {
            if (!/^\d+$/.test(value)) {
                error = 'This field must contain numbers only';
            }
        }

        // Subdomain (lowercase alphanumeric)
        if (!error && fieldRules.subdomain && value.length > 0) {
            if (!/^[a-z0-9]+$/.test(value)) {
                error = 'Subdomain must contain only lowercase letters and numbers';
            }
        }

        // Password strength (8-16 chars)
        if (!error && fieldRules.password && value.length > 0) {
            if (value.length < 8) {
                error = 'Password must be at least 8 characters';
            } else if (value.length > 16) {
                error = 'Password must be at most 16 characters';
            }
        }

        // Match another field (e.g., confirm password)
        if (!error && fieldRules.match && value.length > 0) {
            const matchField = this.form.querySelector(`[name="${fieldRules.match}"]`);
            if (matchField && value !== matchField.value) {
                error = 'Passwords do not match';
            }
        }

        // Custom validator function
        if (!error && fieldRules.custom && typeof fieldRules.custom === 'function') {
            error = fieldRules.custom(value, field);
        }

        // Apply or clear error
        if (error) {
            this._showFieldError(field, error);
            this.errors[fieldName] = error;
        } else {
            this._clearFieldError(field);
            delete this.errors[fieldName];
        }

        return !error;
    }

    /**
     * Validate all fields. Returns true if all valid.
     */
    validateAll() {
        let allValid = true;
        let firstErrorField = null;

        for (const fieldName of Object.keys(this.rules)) {
            const isValid = this.validateField(fieldName);
            if (!isValid && !firstErrorField) {
                firstErrorField = this.form.querySelector(`[name="${fieldName}"]`);
            }
            if (!isValid) allValid = false;
        }

        // Shake invalid fields
        if (!allValid) {
            const errorGroups = this.form.querySelectorAll('.form-group.has-error');
            errorGroups.forEach(group => {
                group.classList.remove('shake');
                // Trigger reflow for re-animation
                void group.offsetWidth;
                group.classList.add('shake');
            });

            // Scroll to first error
            if (this.options.scrollToFirstError && firstErrorField) {
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrorField.focus();
            }

            // Show summary toast with SweetAlert2
            this._showErrorSummary();
        }

        return allValid;
    }

    /**
     * Get all current errors as an object.
     */
    getErrors() {
        return { ...this.errors };
    }

    /**
     * Apply server-returned errors to the form fields.
     * @param {Object} serverErrors - { fieldName: "error message", ... }
     */
    applyServerErrors(serverErrors) {
        for (const [fieldName, message] of Object.entries(serverErrors)) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this._showFieldError(field, message);
                this.errors[fieldName] = message;
            }
        }

        // Shake and scroll
        const errorGroups = this.form.querySelectorAll('.form-group.has-error');
        errorGroups.forEach(group => {
            group.classList.remove('shake');
            void group.offsetWidth;
            group.classList.add('shake');
        });

        const firstError = this.form.querySelector('.form-group.has-error input, .form-group.has-error textarea, .form-group.has-error select');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }

    /* ---------- UI HELPERS ---------- */

    _showFieldError(field, message) {
        const group = field.closest('.form-group');
        if (!group) return;

        group.classList.add('has-error');
        group.classList.remove('has-success');

        const errEl = group.querySelector('.field-error');
        if (errEl) {
            const span = errEl.querySelector('span');
            if (span) span.textContent = message;
        }
    }

    _clearFieldError(field) {
        const group = field.closest('.form-group');
        if (!group) return;

        group.classList.remove('has-error', 'shake');

        // Only mark success if field has a value
        if (field.value.trim().length > 0) {
            group.classList.add('has-success');
        } else {
            group.classList.remove('has-success');
        }

        const errEl = group.querySelector('.field-error');
        if (errEl) {
            const span = errEl.querySelector('span');
            if (span) span.textContent = '';
        }
    }

    _getFieldLabel(field) {
        const group = field.closest('.form-group');
        if (group) {
            const label = group.querySelector('label');
            if (label) {
                // Remove asterisk text from label
                return label.textContent.replace(/\s*\*\s*$/, '').trim();
            }
        }
        // Fallback: convert field name to readable
        return field.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    _showErrorSummary() {
        const errorCount = Object.keys(this.errors).length;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Validation Failed',
                text: `Please fix ${errorCount} error${errorCount > 1 ? 's' : ''} highlighted in the form.`,
                confirmButtonColor: '#2e0d2a',
                confirmButtonText: 'Got it'
            });
        }
    }

    /* ---------- PASSWORD STRENGTH ---------- */

    _addPasswordStrength(field) {
        const group = field.closest('.form-group');
        if (!group || group.querySelector('.password-strength')) return;

        const meter = document.createElement('div');
        meter.className = 'password-strength';
        meter.innerHTML = `
            <div class="password-strength-bar">
                <div class="password-strength-fill"></div>
            </div>
            <span class="password-strength-text"></span>
        `;

        // Insert after field-error or after field
        const errEl = group.querySelector('.field-error');
        const insertAfter = errEl || field;
        insertAfter.parentNode.insertBefore(meter, insertAfter.nextSibling);
    }

    _updatePasswordStrength(field) {
        const group = field.closest('.form-group');
        if (!group) return;

        const fill = group.querySelector('.password-strength-fill');
        const text = group.querySelector('.password-strength-text');
        if (!fill || !text) return;

        const value = field.value;

        // Clear all strength classes
        fill.className = 'password-strength-fill';
        text.className = 'password-strength-text';

        if (value.length === 0) {
            text.textContent = '';
            return;
        }

        let score = 0;
        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        let strength, label;
        if (score <= 1) {
            strength = 'weak'; label = 'Weak';
        } else if (score === 2) {
            strength = 'fair'; label = 'Fair';
        } else if (score === 3) {
            strength = 'good'; label = 'Good';
        } else {
            strength = 'strong'; label = 'Strong';
        }

        fill.classList.add(`strength-${strength}`);
        text.classList.add(`strength-${strength}`);
        text.textContent = label;
    }

    /* ---------- PASSWORD VISIBILITY TOGGLE ---------- */

    _addPasswordToggle(field) {
        const group = field.closest('.form-group');
        if (!group || group.querySelector('.password-toggle-btn')) return;

        // Wrap the input in a wrapper for positioning
        const wrapper = document.createElement('div');
        wrapper.className = 'password-wrapper';
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);

        // Create the eye button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle-btn';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        toggleBtn.setAttribute('tabindex', '-1');
        toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
        wrapper.appendChild(toggleBtn);

        // Toggle logic
        toggleBtn.addEventListener('click', () => {
            const isPassword = field.type === 'password';
            field.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword
                ? '<i class="fas fa-eye-slash"></i>'
                : '<i class="fas fa-eye"></i>';
            field.focus();
        });
    }

    /* ---------- PHONE WITH COUNTRY CODE SELECTOR ---------- */

    _setupPhoneField(field) {
        const fieldName = field.name;
        const defaultCode = this.options.defaultCountryCode;
        const defaultCountry = COUNTRY_CODES.find(c => c.code === defaultCode) || COUNTRY_CODES[0];

        // Store selected country
        this._phoneCountrySelections[fieldName] = defaultCountry.code;

        const group = field.closest('.form-group');
        if (!group) return;

        // --- Build phone input wrapper ---
        const wrapper = document.createElement('div');
        wrapper.className = 'phone-input-group';

        // Country code button (trigger)
        const codeBtn = document.createElement('button');
        codeBtn.type = 'button';
        codeBtn.className = 'country-code-btn';
        codeBtn.innerHTML = `<span class="country-flag">${defaultCountry.flag}</span><span class="country-dial">${defaultCountry.dial}</span><i class="fas fa-chevron-down country-arrow"></i>`;

        // Dropdown panel
        const dropdown = document.createElement('div');
        dropdown.className = 'country-code-dropdown';
        dropdown.innerHTML = `
            <div class="country-search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="country-search-input" placeholder="Search countries..." autocomplete="off">
            </div>
            <ul class="country-list"></ul>
        `;

        // Populate country list
        const list = dropdown.querySelector('.country-list');
        COUNTRY_CODES.forEach(c => {
            const li = document.createElement('li');
            li.className = 'country-item' + (c.code === defaultCode ? ' selected' : '');
            li.dataset.code = c.code;
            li.innerHTML = `<span class="country-flag">${c.flag}</span><span class="country-name">${c.name}</span><span class="country-dial">${c.dial}</span>`;
            list.appendChild(li);
        });

        // Wrap the existing input
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(codeBtn);
        wrapper.appendChild(field);
        wrapper.appendChild(dropdown);

        // Update placeholder
        this._updatePhonePlaceholder(field, defaultCountry);

        // --- Format hint ---
        if (!group.querySelector('.format-hint')) {
            const hint = document.createElement('div');
            hint.className = 'format-hint phone-format-hint';
            hint.innerHTML = `<i class="fas fa-info-circle"></i> <span>Format: ${defaultCountry.dial} ${'X'.repeat(defaultCountry.digits).replace(/(.{3})/g, '$1 ').trim()}</span>`;
            const errEl = group.querySelector('.field-error');
            if (errEl) {
                errEl.parentNode.insertBefore(hint, errEl);
            } else {
                wrapper.parentNode.insertBefore(hint, wrapper.nextSibling);
            }
        }

        // --- Toggle dropdown ---
        codeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('open');
            // Close all other dropdowns first
            document.querySelectorAll('.country-code-dropdown.open').forEach(d => d.classList.remove('open'));
            if (!isOpen) {
                dropdown.classList.add('open');
                dropdown.querySelector('.country-search-input').value = '';
                this._filterCountryList(dropdown, '');
                setTimeout(() => dropdown.querySelector('.country-search-input').focus(), 50);
            }
        });

        // Close on outside click
        document.addEventListener('click', () => dropdown.classList.remove('open'));
        dropdown.addEventListener('click', e => e.stopPropagation());

        // --- Search filter ---
        const searchInput = dropdown.querySelector('.country-search-input');
        searchInput.addEventListener('input', () => {
            this._filterCountryList(dropdown, searchInput.value);
        });

        // --- Select country ---
        list.addEventListener('click', (e) => {
            const item = e.target.closest('.country-item');
            if (!item) return;

            const selectedCode = item.dataset.code;
            const country = COUNTRY_CODES.find(c => c.code === selectedCode);
            if (!country) return;

            // Update state
            this._phoneCountrySelections[fieldName] = selectedCode;

            // Update button
            codeBtn.querySelector('.country-flag').textContent = country.flag;
            codeBtn.querySelector('.country-dial').textContent = country.dial;

            // Update selected class
            list.querySelectorAll('.country-item').forEach(li => li.classList.remove('selected'));
            item.classList.add('selected');

            // Update hint
            const hintSpan = group.querySelector('.phone-format-hint span');
            if (hintSpan) {
                const placeholder = 'X'.repeat(country.digits).replace(/(.{3})/g, '$1 ').trim();
                hintSpan.textContent = `Format: ${country.dial} ${placeholder}`;
            }

            // Update placeholder & re-format current value
            this._updatePhonePlaceholder(field, country);
            this._reformatPhoneValue(field, country);

            dropdown.classList.remove('open');
            field.focus();

            // Re-validate if there was an error
            if (this.errors[fieldName]) {
                this.validateField(fieldName);
            }
        });

        // --- Auto-format on input ---
        field.addEventListener('input', () => {
            const selCode = this._phoneCountrySelections[fieldName];
            const country = COUNTRY_CODES.find(c => c.code === selCode) || defaultCountry;
            this._reformatPhoneValue(field, country);
        });
    }

    _updatePhonePlaceholder(field, country) {
        const digits = 'X'.repeat(country.digits).replace(/(.{3})/g, '$1 ').trim();
        field.placeholder = `${digits}`;
    }

    _reformatPhoneValue(field, country) {
        let raw = field.value.replace(/[^\d]/g, '');

        // Remove country dial digits from the start if user pasted full number
        const dialDigits = country.dial.replace('+', '');
        if (raw.startsWith(dialDigits)) {
            raw = raw.substring(dialDigits.length);
        }
        // Remove leading 0
        if (raw.startsWith('0')) {
            raw = raw.substring(1);
        }

        // Trim to max digits
        raw = raw.substring(0, country.digits);

        // Format in groups of 3
        let formatted = '';
        for (let i = 0; i < raw.length; i += 3) {
            if (formatted) formatted += ' ';
            formatted += raw.substring(i, i + 3);
        }

        field.value = formatted;
    }

    _filterCountryList(dropdown, query) {
        const items = dropdown.querySelectorAll('.country-item');
        const q = query.toLowerCase();
        items.forEach(item => {
            const name = item.querySelector('.country-name').textContent.toLowerCase();
            const dial = item.querySelector('.country-dial').textContent;
            item.style.display = (name.includes(q) || dial.includes(q)) ? '' : 'none';
        });
    }
}

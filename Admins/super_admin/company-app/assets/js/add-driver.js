async function handleAddDriver(event) {
    event.preventDefault();
    console.log('Form submission started');

    // Get form elements
    const formElements = {
        driverName: document.getElementById('driverName'),
        driver_phone: document.getElementById('driver_phone'),
        driver_email: document.getElementById('driver_email'),
        license_number: document.getElementById('license_number'),
        password: document.getElementById('password'),
        confirmPassword: document.getElementById('confirmPassword'),
        employmentStatus: document.getElementById('employmentStatus')
    };

    // Check if any elements are missing
    const missingElements = Object.entries(formElements)
        .filter(([key, element]) => !element)
        .map(([key]) => key);

    if (missingElements.length > 0) {
        console.error('Missing elements:', missingElements);
        alert('Form initialization error. Missing elements: ' + missingElements.join(', '));
        return false;
    }

    // Get submit button
    const submitBtn = event.target.querySelector('button[type="submit"]');
    if (!submitBtn) {
        console.error('Submit button not found');
        alert('Form error: Submit button not found');
        return false;
    }

    // Collect form data
    const formData = {
        driverName: formElements.driverName.value.trim(),
        driver_phone: formElements.driver_phone.value.trim(),
        driver_email: formElements.driver_email.value.trim(),
        license_number: formElements.license_number.value.trim(),
        status: formElements.employmentStatus.value
    };

    // Password handling: only include if provided; validate confirm match
    const pwdEl = formElements.password;
    const confirmEl = formElements.confirmPassword;
    const pwdVal = pwdEl ? pwdEl.value : '';
    const confirmVal = confirmEl ? confirmEl.value : '';
    if (pwdVal || confirmVal) {
        // if any password field provided, require both and validate
        if (!pwdVal || !confirmVal) {
            alert('Please fill both password fields or leave both empty to auto-generate a temporary password.');
            if (!pwdVal) pwdEl.focus(); else confirmEl.focus();
            return false;
        }
        if (pwdVal.length < 8) {
            alert('Password must be at least 8 characters long');
            pwdEl.focus();
            return false;
        }
        if (pwdVal !== confirmVal) {
            alert('Passwords do not match');
            confirmEl.focus();
            return false;
        }
        formData.password = pwdVal;
    }

    // Validate required fields
    for (const [key, value] of Object.entries(formData)) {
        if (!value) {
            alert(`Please fill in the ${key.replace('_', ' ')}`);
            formElements[key].focus();
            return false;
        }
    }

    try {
        // Disable submit button while processing
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        // Send data to API
        const response = await fetch('../api/add_driver.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });

        let result;
        try {
            result = await response.json();
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            throw new Error('Server returned invalid response. Please try again.');
        }

        console.log('API Response:', result);

        if (result.success === true) {
            // Show credentials before redirecting
            const credentials = result.data.driver;
            alert(`Driver added successfully!\n\nPlease provide these login credentials to ${credentials.name}:\n\nEmail: ${credentials.email}\nTemporary Password: ${credentials.temp_password}\n\nPlease ask them to change their password upon first login.`);
            window.location.href = 'drivers.php';
            return;
        }

        // If we get here, there was an error
        throw new Error(result.error || 'Failed to add driver');

    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'An unexpected error occurred. Please try again.');

        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
    }

    return false;
}

// Add event listener when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded');
    const form = document.getElementById('addDriverForm');
    if (form) {
        form.addEventListener('submit', handleAddDriver);
    } else {
        console.error('Add driver form not found');
    }
});
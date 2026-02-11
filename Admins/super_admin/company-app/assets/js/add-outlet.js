// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded');
    // Pre-verify all elements exist
    const ids = ['outletName', 'address', 'contactPerson', 'contact_email',
        'contact_phone', 'password', 'confirmPassword', 'status', 'formError'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        console.log(`Element ${id}: ${el ? 'Found' : 'Missing'}`);
    });
});

async function handleAddOutlet(event) {
    event.preventDefault();
    console.log('Form submission started');

    // Ensure all required elements are present
    const requiredElements = {
        form: event.target,
        outletName: document.getElementById('outletName'),
        address: document.getElementById('address'),
        contactPerson: document.getElementById('contactPerson'),
        contact_email: document.getElementById('contact_email'),
        contact_phone: document.getElementById('contact_phone'),
        password: document.getElementById('password'),
        confirmPassword: document.getElementById('confirmPassword'),
        status: document.getElementById('status'),
        errorDiv: document.getElementById('formError')
    };

    // Debug log found elements
    console.log('Found elements:', Object.fromEntries(
        Object.entries(requiredElements).map(([key, element]) =>
            [key, element ? 'Found' : 'Missing']
        )
    ));

    // Check if any elements are missing
    const missingElements = Object.entries(requiredElements)
        .filter(([key, element]) => !element)
        .map(([key]) => key);

    if (missingElements.length > 0) {
        console.error('Missing elements:', missingElements);
        alert('Form initialization error. Missing elements: ' + missingElements.join(', '));
        return false;
    }

    // Get submit button
    const submitBtn = requiredElements.form.querySelector('button[type="submit"]');
    if (!submitBtn) {
        console.error('Submit button not found');
        alert('Form error: Submit button not found');
        return false;
    }

    // Clear previous error
    requiredElements.errorDiv.style.display = 'none';
    requiredElements.errorDiv.textContent = '';

    // Validate passwords match
    if (requiredElements.password.value !== requiredElements.confirmPassword.value) {
        requiredElements.errorDiv.textContent = 'Passwords do not match';
        requiredElements.errorDiv.style.display = 'block';
        return false;
    }

    // Collect form data
    const formData = {
        outletName: requiredElements.outletName.value,
        address: requiredElements.address.value,
        contactPerson: requiredElements.contactPerson.value,
        contact_email: requiredElements.contact_email.value,
        contact_phone: requiredElements.contact_phone.value,
        password: requiredElements.password.value,
        status: requiredElements.status.value
    };

    try {
        // Disable submit button while processing
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        // Send data to API
        const response = await fetch('../api/add_outlet.php', {
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
            // Success - redirect to outlets page
            alert('Outlet created successfully!');
            window.location.href = 'outlets.php';
            return;
        }

        // If we get here, there was an error
        console.error('API Error:', result);
        let errorMessage = result.error || 'Failed to create outlet';

        // Make error message more user-friendly
        if (errorMessage.includes('already exists')) {
            errorMessage = 'An outlet with this name already exists in your company. Please use a different name.';
        }

        throw new Error(errorMessage);

    } catch (error) {
        // Show error message
        console.error('Error:', error);
        if (requiredElements && requiredElements.errorDiv) {
            requiredElements.errorDiv.textContent = error.message || 'An unexpected error occurred. Please try again.';
            requiredElements.errorDiv.style.display = 'block';
        } else {
            alert(error.message || 'An unexpected error occurred');
        }

        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save';
        }
    }

    return false;
}
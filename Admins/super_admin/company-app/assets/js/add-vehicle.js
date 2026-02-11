// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded');
    const form = document.getElementById('addVehicleForm');
    if (form) {
        form.addEventListener('submit', handleAddVehicle);
    } else {
        console.error('Add vehicle form not found');
    }
});

async function handleAddVehicle(event) {
    event.preventDefault();
    console.log('Form submission started');

    // Get form elements
    const formElements = {
        name: document.getElementById('name'),
        plate_number: document.getElementById('plate_number'),
        status: document.getElementById('status')
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
        name: formElements.name.value.trim(),
        plate_number: formElements.plate_number.value.trim(), // Still using plateNumber for API compatibility
        status: formElements.status.value
    };

    // Validate required fields
    const requiredFields = ['name', 'plate_number', 'status'];
    for (const field of requiredFields) {
        if (!formData[field]) {
            // Show user-friendly field name
            const displayName = field === 'plate_number' ? 'plate number' : field.replace(/([A-Z])/g, ' $1').toLowerCase();
            alert(`Please fill in the ${displayName}`);
            formElements[field].focus();
            return false;
        }
    }

    try {
        // Disable submit button while processing
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        // Send data to API
        const response = await fetch('../api/add_vehicle.php', {
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
            // Success - show message and redirect to vehicles page
            alert('Vehicle added successfully!');
            window.location.href = 'company-vehicles.php';
            return;
        }

        // If we get here, there was an error
        throw new Error(result.error || 'Failed to add vehicle');

    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'An unexpected error occurred. Please try again.');

        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Vehicle';
    }

    return false;
}
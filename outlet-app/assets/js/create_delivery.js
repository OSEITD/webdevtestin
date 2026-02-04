document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('newParcelForm');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = {
            senderName: form.senderName.value.trim(),
            senderEmail: form.senderEmail.value.trim(),
            senderPhone: form.senderPhone.value.trim(),
            recipientName: form.recipientName.value.trim(),
            recipientAddress: form.recipientAddress.value.trim(),
            parcelWeight: form.parcelWeight.value.trim(),
            parcelDescription: form.parcelDescription.value.trim(),
            deliveryOption: form.deliveryOption.value,
            originOutletId: form.originOutletId.value,
            companyId: form.companyId.value
        };

        try {
            const response = await fetch('api/create_parcel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                alert('Parcel created successfully! Tracking Number: ' + (result.parcel[0]?.track_number || 'N/A'));
                form.reset();
            } else {
                alert('Error creating parcel: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Network error: ' + error.message);
        }
    });

    
    const menuBtn = document.getElementById('menuBtn');
    const closeMenuBtn = document.getElementById('closeMenu');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');

    function openSidebar() {
        sidebar.classList.add('show');
        menuOverlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        menuOverlay.classList.remove('show');
    }

    menuBtn.addEventListener('click', openSidebar);
    closeMenuBtn.addEventListener('click', closeSidebar);
    menuOverlay.addEventListener('click', closeSidebar);
});

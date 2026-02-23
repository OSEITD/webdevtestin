console.log('🔥 Parcel Registration JS Loading...');

let selectedPhotos = [];
const maxPhotos = 5;
const maxFileSize = 5 * 1024 * 1024; 

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔥 DOM loaded for parcel registration');
    
    initializeFormHandlers();
    initializePhotoUpload();
    loadDestinationOutlets();
    
    setupFeeCalculation();
    setupTripSelection(); 
    setupAutofill(); 
    setupDestinationOutletChangeHandler();
    setupPaymentFieldTracking();
});

function initializeFormHandlers() {
    const form = document.getElementById('newParcelForm');
    const cancelBtn = document.getElementById('cancelBtn');
    
    if (form) {
        form.addEventListener('submit', handleParcelSubmission);
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to cancel? All entered data will be lost.')) {
                window.location.href = 'outlet_dashboard.php';
            }
        });
    }
}

function initializePhotoUpload() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('parcelPhotos');
    const photoPreview = document.getElementById('photoPreview');
    const cameraBtn = document.getElementById('cameraBtn');

    if (!uploadZone || !fileInput || !photoPreview) {
        console.error('Photo upload elements missing:', { uploadZone, fileInput, photoPreview });
        return;
    }

    console.log('📷 Initializing photo upload...');

    // Click to upload
    uploadZone.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput.removeAttribute('capture');
        fileInput.setAttribute('multiple', 'multiple');
        fileInput.click();
    });

    // Handle file selection
    fileInput.addEventListener('change', (e) => {
        console.log('📁 Files selected:', e.target.files.length);
        handleFileSelection(e.target.files);
    });

    // Drag and drop handlers
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        console.log('📁 Files dropped:', files.length);
        handleFileSelection(files);
    });

    // Camera button
    if (cameraBtn) {
        cameraBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            // Check if we can use getUserMedia for camera
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                openCameraModal();
            } else {
                // Fallback: use file input with capture attribute
                fileInput.removeAttribute('multiple');
                fileInput.setAttribute('accept', 'image/*');
                fileInput.setAttribute('capture', 'environment');
                fileInput.click();
            }
        });
    }

    console.log('✅ Photo upload initialized');
}

/**
 * Handle file selection from input or drag-drop
 */
function handleFileSelection(files) {
    const photoPreview = document.getElementById('photoPreview');
    
    if (!files || files.length === 0) {
        console.log('No files selected');
        return;
    }

    // Check max photos limit
    if (selectedPhotos.length >= maxPhotos) {
        alert(`Maximum ${maxPhotos} photos allowed. Please remove some photos first.`);
        return;
    }

    const remainingSlots = maxPhotos - selectedPhotos.length;
    const filesToProcess = Array.from(files).slice(0, remainingSlots);

    filesToProcess.forEach(file => {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert(`${file.name} is not an image file.`);
            return;
        }

        // Validate file size
        if (file.size > maxFileSize) {
            alert(`${file.name} is too large. Maximum size is 5MB.`);
            return;
        }

        // Add to selected photos
        selectedPhotos.push(file);
        console.log(`📷 Added photo: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`);

        // Create preview
        createPhotoPreview(file, photoPreview);
    });

    // Update upload zone text
    updateUploadZoneText();
}

/**
 * Create photo preview element
 */
function createPhotoPreview(file, container) {
    const reader = new FileReader();
    
    reader.onload = (e) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'photo-preview-item';
        previewItem.style.cssText = `
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            background: white;
        `;
        
        previewItem.innerHTML = `
            <img src="${e.target.result}" alt="${file.name}" style="width: 100%; height: 100px; object-fit: cover; display: block;">
            <div class="preview-overlay" style="
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: linear-gradient(transparent, rgba(0,0,0,0.7));
                padding: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <span class="file-name" style="
                    color: white;
                    font-size: 10px;
                    font-weight: 500;
                    max-width: 70px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                ">${file.name}</span>
                <button type="button" class="remove-btn" data-filename="${file.name}" style="
                    background: rgba(220, 38, 38, 0.9);
                    color: white;
                    border: none;
                    border-radius: 50%;
                    width: 22px;
                    height: 22px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                ">×</button>
            </div>
        `;

        // Add remove handler
        const removeBtn = previewItem.querySelector('.remove-btn');
        removeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            removePhoto(file.name, previewItem);
        });

        container.appendChild(previewItem);
    };

    reader.readAsDataURL(file);
}

/**
 * Remove photo from selection
 */
function removePhoto(filename, previewElement) {
    selectedPhotos = selectedPhotos.filter(f => f.name !== filename);
    previewElement.remove();
    updateUploadZoneText();
    console.log(`🗑️ Removed photo: ${filename}`);
}

/**
 * Update upload zone text based on selection
 */
function updateUploadZoneText() {
    const uploadZoneText = document.getElementById('uploadZoneText');
    if (uploadZoneText) {
        if (selectedPhotos.length === 0) {
            uploadZoneText.textContent = 'Click here or drag and drop images';
        } else if (selectedPhotos.length >= maxPhotos) {
            uploadZoneText.textContent = `Maximum ${maxPhotos} photos reached`;
        } else {
            uploadZoneText.textContent = `${selectedPhotos.length}/${maxPhotos} photos selected. Click to add more.`;
        }
    }
}

/**
 * Open camera modal for taking photos
 */
function openCameraModal() {
    console.log('📹 Opening camera modal...');
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'cameraModal';
    modal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
    `;

    modal.innerHTML = `
        <div style="position: relative; width: 100%; max-width: 640px; background: #000; border-radius: 12px; overflow: hidden;">
            <video id="cameraVideo" autoplay playsinline style="width: 100%; display: block;"></video>
            <canvas id="cameraCanvas" style="display: none;"></canvas>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; background: linear-gradient(transparent, rgba(0,0,0,0.8)); display: flex; justify-content: center; gap: 20px;">
                <button id="captureBtn" style="
                    width: 70px;
                    height: 70px;
                    border-radius: 50%;
                    border: 4px solid white;
                    background: rgba(255,255,255,0.2);
                    cursor: pointer;
                    transition: all 0.2s;
                "></button>
                <button id="closeCameraBtn" style="
                    position: absolute;
                    top: -60px;
                    right: 10px;
                    background: rgba(255,255,255,0.2);
                    border: none;
                    color: white;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 20px;
                ">×</button>
            </div>
        </div>
        <p style="color: white; margin-top: 15px; font-size: 14px;">Tap the button to capture a photo</p>
    `;

    document.body.appendChild(modal);

    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const captureBtn = document.getElementById('captureBtn');
    const closeBtn = document.getElementById('closeCameraBtn');

    let stream = null;

    // Start camera
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'environment',
            width: { ideal: 1280 },
            height: { ideal: 720 }
        } 
    })
    .then(mediaStream => {
        stream = mediaStream;
        video.srcObject = stream;
        console.log('✅ Camera started');
    })
    .catch(err => {
        console.error('❌ Camera error:', err);
        alert('Unable to access camera. Please check permissions.');
        closeCameraModal();
    });

    // Capture photo
    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        canvas.toBlob(blob => {
            if (blob) {
                const file = new File([blob], `camera_${Date.now()}.jpg`, { type: 'image/jpeg' });
                handleFileSelection([file]);
                console.log('📸 Photo captured');
            }
            closeCameraModal();
        }, 'image/jpeg', 0.9);
    });

    // Close modal
    closeBtn.addEventListener('click', closeCameraModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeCameraModal();
    });

    function closeCameraModal() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        modal.remove();
        console.log('📹 Camera modal closed');
    }
}

async function loadDestinationOutlets() {
    const select = document.getElementById('destinationOutletId');
    // Get companyId from hidden input
    const companyIdInput = document.getElementById('companyId');
    const companyId = companyIdInput ? companyIdInput.value : '';
    if (!select) return;

    try {
        console.log('🔥 Loading destination outlets...');
        const response = await fetch(`../api/outlets/fetch_company_outlets.php?company_id=${encodeURIComponent(companyId)}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Check content type before parsing JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const responseText = await response.text();
            console.error('Server returned non-JSON response:', responseText);
            throw new Error('Server error - expected JSON but received HTML/text response');
        }

        const data = await response.json();

        // Clear existing options except the first one
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        if (data.success && data.outlets && data.outlets.length > 0) {
            data.outlets.forEach(outlet => {
                const option = document.createElement('option');
                option.value = outlet.id;
                option.textContent = `${outlet.name} - ${outlet.location}`;
                select.appendChild(option);
            });
            console.log(`🔥 Loaded ${data.outlets.length} destination outlets`);
        } else {
            console.warn('No outlets available or failed to load outlets:', data);

            // Add a message if no outlets available
            const option = document.createElement('option');
            option.value = '';
            option.textContent = data.message || 'No outlets available';
            option.disabled = true;
            select.appendChild(option);
        }
    } catch (error) {
        console.error('❌ Error loading destination outlets:', error);

        // Add error message option
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Error loading outlets - please refresh';
        option.disabled = true;
        select.appendChild(option);
    }
}

function setupFeeCalculation() {
    const weightInput = document.getElementById('parcelWeight');
    const valueInput = document.getElementById('value');
    const deliveryOption = document.getElementById('deliveryOption');
    const insuranceInput = document.getElementById('insuranceAmount');
    const deliveryFeeInput = document.getElementById('deliveryFee');
    const cashAmountInput = document.getElementById('cashAmount');
    const codAmountInput = document.getElementById('codAmount');
    const dimensionsInput = document.getElementById('dimensions');
    
    // Track if user has manually overridden the delivery fee
    let userOverrodeDeliveryFee = false;
    
    if (deliveryFeeInput) {
        deliveryFeeInput.addEventListener('focus', function() {
            userOverrodeDeliveryFee = true;
        });
    }
    
    // Auto-calculate delivery fee when weight, dimensions, or delivery option change
    [weightInput, dimensionsInput, deliveryOption].forEach(input => {
        if (input) {
            input.addEventListener('change', function() {
                userOverrodeDeliveryFee = false; // Reset override on relevant field changes
                autoCalculateDeliveryFee();
            });
            input.addEventListener('input', debounce(function() {
                userOverrodeDeliveryFee = false;
                autoCalculateDeliveryFee();
            }, 500));
        }
    });
    
    // Update summary when insurance or delivery fee changes
    [insuranceInput, deliveryFeeInput, cashAmountInput, codAmountInput].forEach(input => {
        if (input) {
            input.addEventListener('change', updatePaymentSummarySection);
            input.addEventListener('input', debounce(updatePaymentSummarySection, 300));
        }
    });
    
    // Initial calculation
    autoCalculateDeliveryFee();
    
    // Auto-calculate delivery fee using billing config
    function autoCalculateDeliveryFee() {
        const config = window.BILLING_CONFIG;
        if (!config) return;
        
        const weight = parseFloat(weightInput?.value) || 0;
        if (weight <= 0 && !userOverrodeDeliveryFee) {
            if (deliveryFeeInput) deliveryFeeInput.value = '';
            updatePaymentSummarySection();
            return;
        }
        
        const option = deliveryOption?.value || 'standard';
        const parcelValue = parseFloat(valueInput?.value) || 0;
        const insurance = parseFloat(insuranceInput?.value) || 0;
        
        // Parse dimensions
        let length = 0, width = 0, height = 0;
        const dimStr = dimensionsInput?.value || '';
        if (dimStr.match(/\d/)) {
            const dims = dimStr.split(/[x,×]/).map(s => s.trim()).filter(Boolean);
            if (dims.length === 3 && dims.every(d => !isNaN(d))) {
                length = parseFloat(dims[0]);
                width = parseFloat(dims[1]);
                height = parseFloat(dims[2]);
            }
        }
        
        // Calculate using local billing config (same logic as server)
        const deliveryOptions = config.additional_rules?.delivery_options || {};
        let baseFee = config.base_rate;
        if (deliveryOptions[option]) {
            baseFee = deliveryOptions[option].base_fee || config.base_rate;
        }
        
        let weightFee = weight * config.rate_per_kg;
        let volumetricFee = 0;
        
        if (length > 0 && width > 0 && height > 0) {
            const volumetricWeight = (length * width * height) / config.volumetric_divisor;
            const chargeableWeight = Math.max(weight, volumetricWeight);
            volumetricFee = (chargeableWeight - weight) * config.rate_per_kg;
            weightFee = chargeableWeight * config.rate_per_kg;
        }
        
        let insuranceFee = 0;
        if (insurance > 0) {
            const insuranceRate = config.additional_rules?.insurance_rate || 0.02;
            insuranceFee = Math.max(parcelValue, insurance) * insuranceRate;
        }
        
        let total = baseFee + weightFee + volumetricFee + insuranceFee;
        const minFee = config.additional_rules?.min_fee || 0;
        if (minFee > 0 && total < minFee) {
            total = minFee;
        }
        
        total = Math.round(total * 100) / 100;
        
        if (!userOverrodeDeliveryFee && deliveryFeeInput) {
            deliveryFeeInput.value = total.toFixed(2);
            // Update help text to show breakdown
            const helpEl = document.getElementById('deliveryFeeHelp');
            if (helpEl) {
                helpEl.textContent = `Base: K${baseFee.toFixed(0)} + Weight: K${weightFee.toFixed(0)}${volumetricFee > 0 ? ' + Vol: K' + volumetricFee.toFixed(0) : ''}${insuranceFee > 0 ? ' + Ins: K' + insuranceFee.toFixed(0) : ''}`;
            }
        }
        
        updatePaymentSummarySection();
    }
}

// Update the payment summary section and auto-fill cash/cod amounts
function updatePaymentSummarySection() {
    const deliveryFee = parseFloat(document.getElementById('deliveryFee')?.value) || 0;
    const insuranceAmount = parseFloat(document.getElementById('insuranceAmount')?.value) || 0;
    const commissionPct = parseFloat(document.getElementById('commissionPercentage')?.value) || 0;
    const paymentMethod = document.getElementById('paymentMethod')?.value || 'cash';
    
    const totalDue = deliveryFee + insuranceAmount;
    const commissionAmount = Math.round((totalDue * commissionPct / 100) * 100) / 100;
    const netAmount = Math.round((totalDue - commissionAmount) * 100) / 100;
    
    // Update commission amount field
    const commAmountField = document.getElementById('commissionAmount');
    if (commAmountField) commAmountField.value = commissionAmount.toFixed(2);
    
    // Update summary display
    const summaryDeliveryFee = document.getElementById('summaryDeliveryFee');
    const summaryInsurance = document.getElementById('summaryInsurance');
    const summaryCommPct = document.getElementById('summaryCommPct');
    const summaryCommission = document.getElementById('summaryCommission');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryNetAmount = document.getElementById('summaryNetAmount');
    
    if (summaryDeliveryFee) summaryDeliveryFee.textContent = `K ${deliveryFee.toFixed(2)}`;
    if (summaryInsurance) summaryInsurance.textContent = `K ${insuranceAmount.toFixed(2)}`;
    if (summaryCommPct) summaryCommPct.textContent = commissionPct.toFixed(1);
    if (summaryCommission) summaryCommission.textContent = `K ${commissionAmount.toFixed(2)}`;
    if (summaryTotal) summaryTotal.innerHTML = `<strong>K ${totalDue.toFixed(2)}</strong>`;
    if (summaryNetAmount) summaryNetAmount.textContent = `K ${netAmount.toFixed(2)}`;
    
    // Auto-fill cash amount when cash is selected
    if (paymentMethod === 'cash') {
        const cashField = document.getElementById('cashAmount');
        // Only auto-fill if the user hasn't manually changed it or it's empty
        if (cashField && (!cashField.dataset.userEdited || cashField.value === '' || cashField.value === '0')) {
            cashField.value = totalDue.toFixed(2);
        }
    }
    
    // Auto-fill COD amount when COD is selected
    if (paymentMethod === 'cod') {
        const codField = document.getElementById('codAmount');
        if (codField && (!codField.dataset.userEdited || codField.value === '' || codField.value === '0')) {
            codField.value = totalDue.toFixed(2);
        }
    }
    
    // Also update the Lenco payment sections if they exist
    const mobileTotalAmount = document.getElementById('mobileTotalAmount');
    const cardTotalAmount = document.getElementById('cardTotalAmount');
    if (mobileTotalAmount) mobileTotalAmount.textContent = `K ${totalDue.toFixed(2)}`;
    if (cardTotalAmount) cardTotalAmount.textContent = `K ${totalDue.toFixed(2)}`;
    
    // Legacy display elements
    const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
    const insuranceDisplay = document.getElementById('insuranceDisplay');
    const totalFeeDisplay = document.getElementById('totalFeeDisplay');
    if (deliveryFeeDisplay) deliveryFeeDisplay.textContent = `ZMW ${deliveryFee.toFixed(2)}`;
    if (insuranceDisplay) insuranceDisplay.textContent = `ZMW ${insuranceAmount.toFixed(2)}`;
    if (totalFeeDisplay) totalFeeDisplay.innerHTML = `<strong>ZMW ${totalDue.toFixed(2)}</strong>`;
}

// Track manual edits on cash/cod fields so auto-fill doesn't overwrite user input
function setupPaymentFieldTracking() {
    const cashField = document.getElementById('cashAmount');
    const codField = document.getElementById('codAmount');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    
    if (cashField) {
        cashField.addEventListener('input', function() {
            this.dataset.userEdited = 'true';
        });
    }
    if (codField) {
        codField.addEventListener('input', function() {
            this.dataset.userEdited = 'true';
        });
    }
    
    // When payment method changes, reset the user-edited flag and recalculate
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            if (cashField) cashField.dataset.userEdited = '';
            if (codField) codField.dataset.userEdited = '';
            updatePaymentSummarySection();
        });
    }
}

async function loadTrips() {
    console.log('📡 loadTrips() called');
    
    const select = document.getElementById('tripId');
    if (!select) {
        console.error('❌ Trip select element not found!');
        return;
    }

    // Get the selected destination outlet
    const destinationOutlet = document.getElementById('destinationOutletId')?.value;
    console.log('  - Destination outlet value:', destinationOutlet);

    try {
        let url = '../api/trips/fetch_trips.php';
        if (destinationOutlet) {
            url += `?destination_outlet=${encodeURIComponent(destinationOutlet)}`;
        }
        
        console.log('  - Fetching from URL:', url);

        const response = await fetch(url, {
            credentials: 'same-origin'
        });
        
        console.log('  - Response status:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Check content type before parsing JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const responseText = await response.text();
            console.error('Server returned non-JSON response:', responseText);
            throw new Error('Server error - expected JSON but received HTML/text response');
        }
        
        const data = await response.json();
        
        console.log('  - API Response:', data);
        console.log('  - Trips count:', data.trips ? data.trips.length : 0);
        
        // Clear existing options and add the default "Skip Assignment" option
        select.innerHTML = '<option value="">Skip Trip Assignment - Store Parcel Only</option>';
        
        if (data.success && data.trips && data.trips.length > 0) {
            data.trips.forEach(trip => {
                const option = document.createElement('option');
                option.value = trip.id;
                
                // Enhanced display: Show route with visual indicators
                const route = trip.route_full || trip.display_name;
                const stopCount = trip.total_stops || 0;
                option.textContent = `${route} (${stopCount} stops)`;
                
                option.setAttribute('data-vehicle', trip.vehicle_name || 'Not assigned');
                option.setAttribute('data-plate-number', trip.plate_number || 'N/A');
                option.setAttribute('data-driver-name', trip.driver_name || 'Not assigned');
                option.setAttribute('data-manager-name', trip.manager_name || 'Not assigned');
                option.setAttribute('data-manager-phone', trip.manager_phone || 'N/A');
                option.setAttribute('data-status', trip.status || '');
                option.setAttribute('data-departure', trip.departure_time || '');
                option.setAttribute('data-route-full', trip.route_full || '');
                option.setAttribute('data-origin', trip.origin || '');
                option.setAttribute('data-destination', trip.destination || '');
                option.setAttribute('data-intermediate-stops', JSON.stringify(trip.intermediate_stops || []));
                option.setAttribute('data-total-stops', trip.total_stops || 0);
                select.appendChild(option);
            });
            
            console.log(`🔥 Loaded ${data.trips.length} trips matching the destination outlet`);
            
            // Show info if trips were loaded for a specific destination
            const destinationSelect = document.getElementById('destinationOutletId');
            if (destinationSelect && destinationSelect.value) {
                const destinationName = destinationSelect.options[destinationSelect.selectedIndex]?.text;
                if (data.trips.length > 0) {
                    showInfo(`✅ Found ${data.trips.length} trip(s) that go to ${destinationName}`);
                } else {
                    showInfo(`⚠️ No trips found that go to ${destinationName}. You can still create the parcel without a trip assignment.`);
                }
            }
        } else {
            console.warn('No trips available or failed to load trips:', data);
            
            // Show message in info
            const destinationSelect = document.getElementById('destinationOutletId');
            if (destinationSelect && destinationSelect.value) {
                const destinationName = destinationSelect.options[destinationSelect.selectedIndex]?.text;
                showInfo(`⚠️ No trips found that go to ${destinationName}. You can still create the parcel without a trip assignment.`);
            }
        }
    } catch (error) {
        console.error('Error loading trips:', error);
        
        // Reset dropdown on error
        if (select) {
            select.innerHTML = '<option value="">Error loading trips - Please refresh</option>';
        }
        showError('Failed to load trips. Please refresh the page and try again.');
    }
}

function setupTripSelection() {
    const tripSelect = document.getElementById('tripId');
    const tripInfo = document.getElementById('tripInfo');
    const tripVehicle = document.getElementById('tripVehicle');
    const tripPlateNumber = document.getElementById('tripPlateNumber');
    const tripStatus = document.getElementById('tripStatus');
    const tripDeparture = document.getElementById('tripDeparture');
    const tripRoute = document.getElementById('tripRoute');
    const tripOrigin = document.getElementById('tripOrigin');
    const tripDestination = document.getElementById('tripDestination');
    const tripTotalStops = document.getElementById('tripTotalStops');
    const tripManager = document.getElementById('tripManager');
    const tripManagerPhone = document.getElementById('tripManagerPhone');
    
    if (tripSelect) {
        tripSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                // Show trip details with enhanced route information
                const vehicle = selectedOption.getAttribute('data-vehicle') || 'Not assigned';
                const plateNumber = selectedOption.getAttribute('data-plate-number') || 'N/A';
                const driverName = selectedOption.getAttribute('data-driver-name') || 'Not assigned';
                const managerName = selectedOption.getAttribute('data-manager-name') || 'Not assigned';
                const managerPhone = selectedOption.getAttribute('data-manager-phone') || 'N/A';
                const status = selectedOption.getAttribute('data-status') || 'Unknown';
                const departure = selectedOption.getAttribute('data-departure') || 'Not scheduled';
                const routeFull = selectedOption.getAttribute('data-route-full') || '-';
                const origin = selectedOption.getAttribute('data-origin') || 'Unknown';
                const destination = selectedOption.getAttribute('data-destination') || 'Unknown';
                const totalStops = selectedOption.getAttribute('data-total-stops') || '0';
                
                // Parse intermediate stops
                let intermediateStopsText = '';
                try {
                    const intermediateStops = JSON.parse(selectedOption.getAttribute('data-intermediate-stops') || '[]');
                    if (intermediateStops.length > 0) {
                        intermediateStopsText = ' (via ' + intermediateStops.join(', ') + ')';
                    }
                } catch (e) {
                    console.warn('Error parsing intermediate stops:', e);
                }
                
                // Update UI elements
                if (tripVehicle) tripVehicle.textContent = vehicle;
                if (tripPlateNumber) tripPlateNumber.textContent = plateNumber;
                if (tripStatus) tripStatus.textContent = status;
                if (tripDeparture) tripDeparture.textContent = departure;
                if (tripRoute) tripRoute.textContent = routeFull;
                if (tripOrigin) tripOrigin.textContent = origin;
                if (tripDestination) tripDestination.textContent = destination + intermediateStopsText;
                if (tripTotalStops) tripTotalStops.textContent = totalStops + ' stops';
                if (tripManager) tripManager.textContent = managerName;
                if (tripManagerPhone) tripManagerPhone.textContent = managerPhone;
                
                if (tripInfo) tripInfo.style.display = 'block';
                
                console.log('🔥 Trip selected:', {
                    vehicle: vehicle,
                    plateNumber: plateNumber,
                    driver: driverName,
                    manager: managerName,
                    managerPhone: managerPhone,
                    route: routeFull,
                    origin: origin,
                    destination: destination,
                    totalStops: totalStops
                });
            } else {
                // Hide trip details
                if (tripInfo) tripInfo.style.display = 'none';
            }
        });
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function setupAutofill() {
    const senderNRC = document.getElementById('senderNRC');
    const senderPhone = document.getElementById('senderPhone');
    const senderName = document.getElementById('senderName');
    const senderEmail = document.getElementById('senderEmail');
    const senderAddress = document.getElementById('senderAddress');

    const recipientNRC = document.getElementById('recipientNRC');
    const recipientPhone = document.getElementById('recipientPhone');
    const recipientName = document.getElementById('recipientName');
    const recipientEmail = document.getElementById('recipientEmail');
    const recipientAddress = document.getElementById('recipientAddress');

    // OPTIMIZED: Fast fetch with minimal overhead
    async function fetchAndFill(query, isSender, isFromNRCField = false) {
        if (!query || query.length < 3) return;

        try {
            // Show loading indicator
            const targetName = isSender ? senderName : recipientName;
            if (targetName) {
                targetName.style.backgroundColor = '#f0f8ff';
            }

            // Better detection: Check if from NRC field OR contains '/' OR looks like NRC pattern
            const isNRC = isFromNRCField || query.includes('/') || /^\d{6}\/\d{2}\/\d{1}/.test(query);
            let param = isNRC ? `nrc=${encodeURIComponent(query)}` : `phone=${encodeURIComponent(query)}`;

            console.log('🔍 Autofill search:', { query, isNRC, param, isSender });

            const response = await fetch(`../api/customers/get_customer_by_nrc.php?${param}`, {
                method: 'GET',
                credentials: 'same-origin',
                signal: AbortSignal.timeout(8000) // 8 second timeout
            });

            if (!response.ok) {
                console.warn('❌ Autofill API error:', response.status, response.statusText);
                return;
            }

            const data = await response.json();
            console.log('✅ Autofill response:', data);

            if (data.success && data.customer) {
                const c = data.customer;
                console.log('🎯 Found customer:', c.full_name || c.customer_name);

                if (isSender) {
                    if (senderName && !senderName.value) senderName.value = c.full_name || c.customer_name || '';
                    if (senderEmail && !senderEmail.value) senderEmail.value = c.email || '';
                    if (senderAddress && !senderAddress.value) senderAddress.value = c.Address || c.address || '';
                    if (senderNRC && !senderNRC.value && c.nrc) senderNRC.value = c.nrc;
                    if (senderPhone && !senderPhone.value && c.phone) senderPhone.value = c.phone;
                } else {
                    if (recipientName && !recipientName.value) recipientName.value = c.full_name || c.customer_name || '';
                    if (recipientEmail && !recipientEmail.value) recipientEmail.value = c.email || '';
                    if (recipientAddress && !recipientAddress.value) recipientAddress.value = c.Address || c.address || '';
                    if (recipientNRC && !recipientNRC.value && c.nrc) recipientNRC.value = c.nrc;
                    if (recipientPhone && !recipientPhone.value && c.phone) recipientPhone.value = c.phone;
                }

                // Show success feedback
                if (targetName) {
                    targetName.style.backgroundColor = '#f0fff0';
                    setTimeout(() => {
                        targetName.style.backgroundColor = '';
                    }, 1000);
                }
            } else {
                console.log('❌ No customer found or API error:', data);
                if (targetName) {
                    targetName.style.backgroundColor = '';
                }
            }
        } catch (error) {
            console.error('❌ Autofill error:', error);
            const targetName = isSender ? senderName : recipientName;
            if (targetName) {
                targetName.style.backgroundColor = '';
            }
            if (error && error.name === 'TimeoutError') {
                alert('Autofill request timed out. Please check your connection or try again.');
            }
        }
    }

    // OPTIMIZED: Debounced autofill with 600ms delay
    const debouncedFetch = debounce((query, isSender, isFromNRCField) => fetchAndFill(query, isSender, isFromNRCField), 600);

    // Event listeners for sender fields with debouncing
    if (senderNRC) {
        senderNRC.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            console.log('📝 Sender NRC input:', value);
            if (value && value.length >= 3) debouncedFetch(value, true, true);
        });
    }

    if (senderPhone) {
        senderPhone.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            console.log('📱 Sender Phone input:', value);
            if (value && value.length >= 10) debouncedFetch(value, true, false);
        });
    }

    // Event listeners for recipient fields with debouncing
    if (recipientNRC) {
        recipientNRC.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            console.log('📝 Recipient NRC input:', value);
            if (value && value.length >= 3) debouncedFetch(value, false, true);
        });
    }

    if (recipientPhone) {
        recipientPhone.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            console.log('📱 Recipient Phone input:', value);
            if (value && value.length >= 10) debouncedFetch(value, false, false);
        });
    }
}

function setupDestinationOutletChangeHandler() {
    const destinationSelect = document.getElementById('destinationOutletId');
    const tripSelect = document.getElementById('tripId');
    const tripInstructionMessage = document.getElementById('tripInstructionMessage');
    const tripAssignmentContent = document.getElementById('tripAssignmentContent');
    
    console.log('🔧 Setting up destination outlet change handler...');
    console.log('  - destinationSelect:', destinationSelect ? '✅ Found' : '❌ Not found');
    console.log('  - tripSelect:', tripSelect ? '✅ Found' : '❌ Not found');
    
    if (!destinationSelect) {
        console.error('❌ Destination outlet select not found! Cannot setup handler.');
        return;
    }

    destinationSelect.addEventListener('change', function() {
        const selectedOutletId = this.value;
        const selectedOutletName = this.options[this.selectedIndex]?.text || '';
        
        console.log('🎯 DESTINATION CHANGED!');
        console.log('  - Selected ID:', selectedOutletId);
        console.log('  - Selected Name:', selectedOutletName);
        
        // Reset trip selection
        if (tripSelect) {
            const previousValue = tripSelect.value;
            tripSelect.value = '';
            
            console.log('  - Clearing previous trip selection:', previousValue);
            
            // Clear trip detail fields
            const detailFields = [
                'tripVehicle', 'tripPlateNumber', 'tripRoute', 
                'tripOrigin', 'tripDestination', 'tripStatus',
                'tripDeparture', 'tripManager', 'tripManagerPhone', 'tripTotalStops'
            ];
            detailFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.textContent = '-';
            });
            
            // Hide trip info
            const tripInfo = document.getElementById('tripInfo');
            if (tripInfo) {
                tripInfo.style.display = 'none';
            }
        }
        
        // Toggle UI based on whether destination is selected
        if (selectedOutletId) {
            console.log('  ✅ Destination selected - showing trip UI and loading trips');
            
            // Show trip selection UI and hide instruction message
            if (tripInstructionMessage) {
                tripInstructionMessage.style.display = 'none';
                console.log('    - Instruction message hidden');
            }
            if (tripAssignmentContent) {
                tripAssignmentContent.style.display = 'block';
                console.log('    - Trip assignment content shown');
            }
            if (tripSelect) {
                tripSelect.disabled = false;
                tripSelect.innerHTML = '<option value="">Loading trips...</option>';
                console.log('    - Trip select enabled with loading message');
            }
            
            // Load trips for this destination
            showInfo(`🔄 Loading trips that go to ${selectedOutletName}...`);
            console.log('  📡 Calling loadTrips()...');
            loadTrips();
        } else {
            console.log('  ⚠️ No destination selected - showing instruction message');
            
            // Show instruction message and hide trip selection UI
            if (tripInstructionMessage) {
                tripInstructionMessage.style.display = 'flex';
                console.log('    - Instruction message shown');
            }
            if (tripAssignmentContent) {
                tripAssignmentContent.style.display = 'none';
                console.log('    - Trip assignment content hidden');
            }
            if (tripSelect) {
                tripSelect.disabled = true;
                tripSelect.innerHTML = '<option value="">-- Select Destination First --</option>';
                console.log('    - Trip select disabled');
            }
        }
    });
    
    console.log('✅ Destination outlet change handler setup complete');
}

// Helper function to show info messages
function showInfo(message) {
    // Create or update info message
    let infoDiv = document.getElementById('infoMessage');
    if (!infoDiv) {
        infoDiv = document.createElement('div');
        infoDiv.id = 'infoMessage';
        infoDiv.className = 'alert alert-info';
        infoDiv.style.cssText = 'background: #e3f2fd; color: #1976d2; padding: 12px; border-radius: 6px; margin: 10px 0; display: flex; align-items: center; gap: 10px;';
        const form = document.querySelector('form');
        if (form && form.parentNode) {
            form.parentNode.insertBefore(infoDiv, form);
        }
    }
    
    infoDiv.innerHTML = `
        <i class="fas fa-info-circle"></i>
        <span>${message}</span>
    `;
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (infoDiv.parentNode) {
            infoDiv.remove();
        }
    }, 4000);
}

async function handleParcelSubmission(event) {
    event.preventDefault();
    
    console.log('🔥 Form submission started');
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
    }
    
    try {
        // Validate form; validator now returns true or an error string
        const validation = validateForm();
        if (validation !== true) {
            // show the specific message rather than generic
            throw new Error(validation);
        }
        
        // Prepare form data
        const formData = new FormData();
        
        // Basic parcel information
        formData.append('originOutletId', document.getElementById('originOutletId').value);
        formData.append('companyId', document.getElementById('companyId').value);
        
        // Sender information
        formData.append('senderName', document.getElementById('senderName').value);
        formData.append('senderEmail', document.getElementById('senderEmail').value || '');
        formData.append('senderPhone', document.getElementById('senderPhone').value);
        formData.append('senderAddress', document.getElementById('senderAddress').value);
        formData.append('senderNRC', document.getElementById('senderNRC').value || '');
        
        // Recipient information
        formData.append('recipientName', document.getElementById('recipientName').value);
        formData.append('recipientEmail', document.getElementById('recipientEmail').value || '');
        formData.append('recipientPhone', document.getElementById('recipientPhone').value);
        formData.append('recipientAddress', document.getElementById('recipientAddress').value);
        formData.append('recipientNRC', document.getElementById('recipientNRC').value || '');
        
        // Parcel details
        formData.append('parcelWeight', document.getElementById('parcelWeight').value);
        formData.append('declaredValue', document.getElementById('value')?.value || '0');
        // Parse dimensions as JSON {L:x,W:y,H:z} if possible
        const dimStr = document.getElementById('dimensions')?.value || '';
        let dimensionsJson = '';
        if (dimStr.match(/\d/)) {
            // Try to parse "L x W x H" or "L,W,H"
            let dims = dimStr.split(/[x,]/).map(s => s.trim()).filter(Boolean);
            if (dims.length === 3 && dims.every(d => !isNaN(d))) {
                dimensionsJson = JSON.stringify({L: parseFloat(dims[0]), W: parseFloat(dims[1]), H: parseFloat(dims[2])});
            } else {
                dimensionsJson = dimStr;
            }
        }
        formData.append('dimensions', dimensionsJson);
        formData.append('itemDescription', document.getElementById('itemDescription').value || '');
        formData.append('specialInstructions', document.getElementById('specialInstructions')?.value || '');
        
        // Delivery information
        // Validate delivery option
        const deliveryOption = document.getElementById('deliveryOption')?.value || 'standard';
        const allowedDeliveryOptions = ['standard', 'express', 'sameday'];
        if (!allowedDeliveryOptions.includes(deliveryOption)) {
            showError('Invalid delivery option selected.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
            return;
        }
        formData.append('deliveryOption', deliveryOption);
        formData.append('destinationOutlet', document.getElementById('destinationOutletId')?.value || '');

        // Delivery date (fix for API)
        formData.append('delivery_date', document.getElementById('deliveryDate')?.value || '');

        // Trip assignment
        const tripIdValue = document.getElementById('tripId')?.value;
        if (tripIdValue && tripIdValue !== '') {
            formData.append('tripId', tripIdValue);
        }

        // Financial information
        formData.append('deliveryFee', document.getElementById('deliveryFee')?.value || '0');
        formData.append('insuranceAmount', document.getElementById('insuranceAmount')?.value || '0');
        formData.append('codAmount', document.getElementById('codAmount')?.value || '0');
        formData.append('cashAmount', document.getElementById('cashAmount')?.value || '0');
        formData.append('commissionAmount', document.getElementById('commissionAmount')?.value || '0');
        // Validate payment method
        const paymentMethod = document.getElementById('paymentMethod')?.value || '';
            // Accept both legacy and Lenco-specific values
            const allowedPaymentMethods = ['cash', 'mobile_money', 'visa', 'cod', 'lenco_mobile', 'lenco_card'];
            if (!allowedPaymentMethods.includes(paymentMethod)) {
                showError('Invalid payment method selected.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                return;
            }
            // Normalize Lenco-specific values for backend (e.g., 'lenco_mobile' -> 'mobile_money')
            let normalizedPaymentMethod = paymentMethod;
            if (paymentMethod === 'lenco_mobile') normalizedPaymentMethod = 'mobile_money';
            if (paymentMethod === 'lenco_card') normalizedPaymentMethod = 'card';
            // Send both normalized and original provider info
            formData.append('paymentMethod', normalizedPaymentMethod);
            formData.append('paymentProvider', paymentMethod);
        const uniquePhotos = [];
        selectedPhotos.forEach(photo => {
            if (!uniquePhotos.some(f => f.name === photo.name && f.lastModified === photo.lastModified)) {
                uniquePhotos.push(photo);
            }
        });
        uniquePhotos.forEach(photo => {
            formData.append('parcelPhotos[]', photo);
        });
        
        console.log('🔥 Sending parcel data to API');
        
        // Send to API
        let result;
        try {
            const response = await fetch('../api/parcels/create_parcel.php', {
                method: 'POST',
                body: formData
            });
            try {
                result = await response.json();
            } catch (jsonError) {
                showError('Server error: Could not parse response. Please check your network or contact support.');
                return;
            }
        } catch (error) {
            showError('Network or server error: ' + error.message);
            return;
        }
        console.log('🔥 API Response:', result);
        if (result && result.success) {
            // Use trackingNumber from backend response, fallback to track_number and parcel.track_number
            let trackingNumber = result.trackingNumber || result.track_number || (result.parcel && result.parcel.track_number);
            if (!trackingNumber) {
                // Try other possible keys (sometimes backend may send as tracking_number)
                trackingNumber = result.tracking_number || (result.parcel && result.parcel.tracking_number);
            }
            showSuccessModal(trackingNumber || 'N/A', result, selectedPhotos);
            // Reset form
            document.getElementById('newParcelForm').reset();
            selectedPhotos = [];
            const photoPreview = document.getElementById('photoPreview');
            if (photoPreview) photoPreview.innerHTML = '';
            updateUploadZoneText();
        } else {
            showError(result?.error || 'Failed to register parcel');
        }
        
    } catch (error) {
        console.error('🔥 Error registering parcel:', error);
        showError('Error: ' + error.message);
        
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

function validateForm() {
    const required = [
        'senderName', 'senderPhone', 'senderAddress',
        'recipientName', 'recipientPhone', 'recipientAddress',
        'itemDescription', 'parcelWeight', 'destinationOutletId'
    ];
    let isValid = true;
    let message = '';

    required.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field || !field.value.trim()) {
            field?.classList.add('error');
            isValid = false;
            message = 'Please fill in all required fields.';
        } else {
            field?.classList.remove('error');
        }
    });
    // Delivery fee must be > 0
    const deliveryFee = document.getElementById('deliveryFee');
    if (deliveryFee && (parseFloat(deliveryFee.value) <= 0 || !deliveryFee.value)) {
        deliveryFee.classList.add('error');
        isValid = false;
        message = 'Delivery fee must be greater than zero.';
    }
    
    // Validate trip-destination compatibility
    const tripSelect = document.getElementById('tripId');
    const destinationSelect = document.getElementById('destinationOutletId');
    
    if (tripSelect && tripSelect.value && destinationSelect && destinationSelect.value) {
        // A trip is selected - verify it's compatible with destination
        const selectedOption = tripSelect.options[tripSelect.selectedIndex];
        const tripDestination = selectedOption.getAttribute('data-destination');
        const destinationName = destinationSelect.options[destinationSelect.selectedIndex]?.text;
        
        // Check if trip includes this destination
        const intermediateStops = JSON.parse(selectedOption.getAttribute('data-intermediate-stops') || '[]');
        const allStops = [selectedOption.getAttribute('data-origin'), ...intermediateStops, tripDestination];
        
        if (!allStops.includes(destinationName)) {
            const err = '⚠️ The selected trip does not go to the selected destination outlet. Please choose a different trip or destination.';
            showError(err);
            tripSelect.classList.add('error');
            isValid = false;
            message = err;
        } else {
            tripSelect.classList.remove('error');
        }
    }

    // Validate mobile money fields when mobile payment is selected
    const paymentMethod = document.getElementById('paymentMethod')?.value || '';
    if (paymentMethod === 'lenco_mobile' || paymentMethod === 'mobile_money') {
        const mobileNumberField = document.getElementById('mobileNumber');
        const mobileProviderChecked = document.querySelector('input[name="mobileProvider"]:checked');
        const mobileVal = mobileNumberField?.value?.trim() || '';

        if (!mobileProviderChecked) {
            const err = 'Please select your mobile network provider for mobile money payment.';
            showError(err);
            const providerContainer = document.querySelector('.mobile-money-providers');
            if (providerContainer) providerContainer.classList.add('error');
            isValid = false;
            message = err;
        } else {
            const providerContainer = document.querySelector('.mobile-money-providers');
            if (providerContainer) providerContainer.classList.remove('error');
        }

        // Basic validation for Zambian numbers using network detection helper
        const cleaned = mobileVal.replace(/\D/g, '');
        let networkNumber = cleaned;
        // if international, convert to local leading zero
        if (networkNumber.startsWith('260') && networkNumber.length === 12) {
            networkNumber = '0' + networkNumber.slice(3);
        }
        const network = typeof detectZambianNetwork === 'function' ? detectZambianNetwork(networkNumber) : null;
        if (!mobileVal || !network) {
            if (mobileNumberField) mobileNumberField.classList.add('error');
            const err = 'Please enter a valid Zambian mobile money number using MTN (096,076) or Airtel (097,077,057) prefixes.';
            showError(err);
            isValid = false;
            message = err;
        } else {
            if (mobileNumberField) mobileNumberField.classList.remove('error');
        }
    }

    return isValid ? true : (message || 'Please fill in all required fields');
}

function showError(message) {
    // Create or update error message
    let errorDiv = document.getElementById('errorMessage');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'errorMessage';
        errorDiv.className = 'alert alert-error';
        errorDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin: 10px 0; display: flex; align-items: center; gap: 10px; border: 1px solid #f5c6cb;';
        const container = document.querySelector('.content-container') || document.querySelector('form')?.parentNode;
        if (container) {
            container.insertBefore(errorDiv, container.firstChild);
        }
    }
    
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        <span>${message}</span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #721c24; cursor: pointer; font-size: 18px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Scroll to error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Auto-remove after 8 seconds
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
    }, 8000);
}

function showSuccessModal(trackingNumber, result, selectedPhotos) {
    // Remove any existing modals
    document.querySelectorAll('.success-modal-overlay').forEach(m => m.remove());
    
    const modalHTML = `
        <div class="success-modal-overlay" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.8);
            z-index: 1050;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            overflow-y: auto;
        ">
            <div class="success-modal-content" style="
                background: white;
                border-radius: 12px;
                overflow: hidden;
                max-width: 600px;
                width: 95%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                margin: 20px;
            ">
                <!-- Header -->
                <div style="
                    background: linear-gradient(135deg, #2E0D2A, #4A1C40);
                    color: white;
                    padding: 20px;
                    text-align: center;
                    position: relative;
                ">
                    <h3 style="margin: 0; font-size: 1.3rem;">
                        <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
                        Parcel Registered Successfully!
                    </h3>
                    <button onclick="document.querySelector('.success-modal-overlay').remove()" style="
                        position: absolute;
                        top: 15px;
                        right: 15px;
                        background: none;
                        border: none;
                        color: white;
                        font-size: 24px;
                        cursor: pointer;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">×</button>
                </div>
                
                <!-- Body -->
                <div style="padding: 20px;">
                    <!-- Tracking Number -->
                    <div style="
                        background: linear-gradient(135deg, #2E0D2A, #4A1C40);
                        color: white;
                        padding: 15px;
                        margin-bottom: 20px;
                        border-radius: 8px;
                        box-shadow: 0 2px 8px rgba(46, 13, 42, 0.2);
                    ">
                        <strong style="color: white;">Tracking Number:</strong>
                        <div style="font-family: monospace; font-size: 1.1rem; font-weight: bold; color: #FFE082; margin-top: 5px;">
                            ${trackingNumber}
                        </div>
                    </div>
                    
                    <!-- Parcel & Delivery Event IDs -->
                    <div style="
                        background: #f8f9fa;
                        border-radius: 8px;
                        padding: 15px;
                        border: 1px solid #e9ecef;
                        margin-bottom: 20px;
                    ">
                        <div style="margin-bottom: 8px;">
                            <strong>Parcel ID:</strong> <span style="font-family: monospace; color: #4A1C40;">${result?.parcel_id || 'N/A'}</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Delivery Event ID:</strong> <span style="font-family: monospace; color: #4A1C40;">${result?.delivery_id || 'N/A'}</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Trip Assigned:</strong> <span style="color: ${result?.trip_assigned ? '#28a745' : '#dc3545'}; font-weight: bold;">${result?.trip_assigned ? '✓ Yes' : '✗ Not Assigned'}</span>
                        </div>
                        ${result?.trip_assignment_error ? `<div style='color:#dc3545; margin-bottom:8px;'><strong>Trip Error:</strong> ${result.trip_assignment_error}</div>` : ''}
                        ${result?.warning ? `<div style='color:#ffc107; margin-bottom:8px;'><strong>Warning:</strong> ${result.warning}</div>` : ''}
                        <div style="margin-bottom: 8px;">
                            <strong>Status:</strong> <span style="color: #ffc107; font-weight: bold;">Pending</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong>Photos:</strong> ${selectedPhotos?.length || 0} uploaded
                        </div>
                        <div>
                            <strong>Payment:</strong> <span style="color: #17a2b8;">${result?.payment_record || 'Not required'}</span>
                        </div>
                    </div>

                    <!-- QR Code & Barcode Section -->
                    ${result?.codes ? `
                    <div style="
                        background: #f8f9fa;
                        border-radius: 8px;
                        padding: 15px;
                        border: 1px solid #e9ecef;
                        margin-bottom: 20px;
                    ">
                        <h4 style="margin: 0 0 15px 0; color: #2E0D2A;">
                            <i class="fas fa-qrcode" style="margin-right: 8px;"></i>
                            Tracking Codes
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div style="text-align: center;">
                                <strong>QR Code</strong>
                                <div style="margin: 10px 0;">
                                    <img src="${result.codes.qr_code_url}" alt="QR Code" style="max-width: 120px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <small>Scan for tracking</small>
                            </div>
                            <div style="text-align: center;">
                                <strong>Barcode</strong>
                                <div style="margin: 10px 0;">
                                    <img src="${result.codes.barcode_url}" alt="Barcode" style="max-width: 120px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <small>For label printing</small>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <!-- Footer -->
                <div style="
                    padding: 20px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                    background: #f8f9fa;
                ">
                    <div style="
                        display: flex;
                        flex-wrap: wrap;
                        justify-content: center;
                        gap: 15px;
                        max-width: 500px;
                        margin: 0 auto;
                    ">
                        <button onclick="printLabel('${trackingNumber}', '${result?.codes?.qr_code_url || ''}', '${result?.codes?.barcode_url || ''}')" style="
                            background: #17a2b8;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                            transition: background 0.3s;
                            flex: 1;
                            min-width: 140px;
                            max-width: 160px;
                        ">
                            <i class="fas fa-print"></i> Print Label
                        </button>
                        <button onclick="document.querySelector('.success-modal-overlay').remove(); window.location.reload();" style="
                            background: #2E0D2A;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                            transition: background 0.3s;
                            flex: 1;
                            min-width: 140px;
                            max-width: 160px;
                        ">
                            <i class="fas fa-plus"></i> Register Another
                        </button>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button onclick="window.location.href='outlet_dashboard.php'" style="
                            background: #6c757d;
                            color: white;
                            border: none;
                            padding: 12px 30px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                            transition: background 0.3s;
                            min-width: 140px;
                        ">
                            <i class="fas fa-home"></i> Dashboard
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add click-outside-to-close
    document.querySelector('.success-modal-overlay').onclick = function(e) {
        if (e.target === this) {
            this.remove();
        }
    };
}

// Print label function
function printLabel(trackingNumber, qrCodeUrl, barcodeUrl) {
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Parcel Label - ${trackingNumber}</title>
            <script src='https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js'><\/script>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    padding: 20px;
                    margin: 0;
                }
                .label {
                    border: 2px solid #000;
                    padding: 20px;
                    margin: 0 auto;
                    width: 300px;
                }
                .tracking-number {
                    font-size: 18px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .code-section {
                    margin: 15px 0;
                }
                .code-section img, .code-section svg {
                    max-width: 220px;
                    height: auto;
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="label">
                <h2>Parcel Label</h2>
                <div class="tracking-number">${trackingNumber}</div>
                <div class="code-section">
                    <svg id="barcode"></svg>
                </div>
                ${qrCodeUrl ? `<div class="code-section"><img src="${qrCodeUrl}" alt="QR Code"></div>` : ''}
                <div style="margin-top: 20px; font-size: 12px;">
                    Generated on: ${new Date().toLocaleString()}
                </div>
            </div>
            <div class="no-print" style="margin-top: 20px;">
                <button onclick="window.print()">Print Label</button>
                <button onclick="window.close()">Close</button>
            </div>
            <script>
                window.onload = function() {
                    JsBarcode("#barcode", "${trackingNumber}", {
                        format: "CODE128",
                        displayValue: true,
                        fontSize: 18,
                        height: 60,
                        width: 2
                    });
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function initializePaymentHandlers() {
    console.log('initializePaymentHandlers called - stub implementation');
    // TODO: Implement payment handlers initialization
}

async function verifyFlutterwavePayment(transactionId) {
    try {
        
        const response = await fetch('../api/payments/verify_flutterwave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                transaction_id: transactionId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ Payment verified:', result);
            return result;
        } else {
            throw new Error(result.error || 'Payment verification failed');
        }
    } catch (error) {
        console.error('❌ Payment verification error:', error);
        throw error;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initializePaymentHandlers();
});


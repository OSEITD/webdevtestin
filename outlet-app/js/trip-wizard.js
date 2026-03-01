let currentStep = 1;
const maxSteps = 4;
let tripData = {
    vehicle: null,
    originOutlet: null,
    destinationOutlet: null,
    stops: [],
    parcels: [],
    departureTime: null,
    selectedParcels: []
};
let availableParcels = [];

document.addEventListener('DOMContentLoaded', function() {
    loadInitialData();
    updateStepDisplay();

    setTimeout(() => {
        setupDateTimeInput();
    }, 500);

    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-stop-btn')) {
            const button = e.target.closest('.remove-stop-btn');
            e.preventDefault();
            e.stopPropagation();

            const index = parseInt(button.getAttribute('data-stop-index'));

            if (!isNaN(index)) {
                removeIntermediateStop(index);
            } else {
            }
        }
    });
});

function setupDateTimeInput() {
    const departureTimeInput = document.getElementById('departureTime');
    if (departureTimeInput) {

        departureTimeInput.addEventListener('click', function(e) {
            this.focus();
            this.click();
        });

        departureTimeInput.addEventListener('focus', function(e) {
            if (this.showPicker && typeof this.showPicker === 'function') {
                try {
                    this.showPicker();
                } catch (err) {
                }
            }
        });

        departureTimeInput.removeAttribute('readonly');
        departureTimeInput.removeAttribute('disabled');

    } else {
    }
}

async function loadInitialData() {
    try {
        showMessage('Loading data...', 'info');

        const vehicleResponse = await fetch('api/fetch_vehicles.php', {
            credentials: 'same-origin'
        });
        if (vehicleResponse.ok) {
            const vehicleData = await vehicleResponse.json();
            if (vehicleData.success && vehicleData.vehicles) {
                populateVehicleOptions(vehicleData.vehicles);
            } else if (vehicleData.error) {
            } else if (Array.isArray(vehicleData)) {
                populateVehicleOptions(vehicleData);
            } else {
            }
        } else {
            const errorData = await vehicleResponse.json().catch(() => ({}));
        }

        const outletResponse = await fetch('api/fetch_company_outlets.php', {
            credentials: 'same-origin'
        });
        if (outletResponse.ok) {
            const outletData = await outletResponse.json();
            if (outletData.success && outletData.outlets) {
                populateOutletOptions(outletData.outlets);
            } else {
            }
        } else {
            const errorData = await outletResponse.json().catch(() => ({}));
        }

        const currentOutletId = document.getElementById('currentOutletId').value;
        if (currentOutletId) {
            const originSelect = document.getElementById('originOutlet');
            if (originSelect) {
                originSelect.value = currentOutletId;
                handleOriginChange();
            }
        }

        initializeDateTimeInput();

        showMessage('Data loaded successfully', 'success');
    } catch (error) {
        showMessage('Error loading data: ' + error.message, 'error');
    }
}

function initializeDateTimeInput() {
    const departureTimeInput = document.getElementById('departureTime');
    if (departureTimeInput) {

        const testInput = document.createElement('input');
        testInput.type = 'datetime-local';
        const isSupported = testInput.type === 'datetime-local';

        const now = new Date();
        const minDateTime = new Date(now.getTime() + (30 * 60000));
        departureTimeInput.min = minDateTime.toISOString().slice(0, 16);

        const defaultDateTime = new Date(now.getTime() + (60 * 60000));
        departureTimeInput.value = defaultDateTime.toISOString().slice(0, 16);

        departureTimeInput.addEventListener('change', function() {
            const selectedTime = new Date(this.value);
            const minTime = new Date(this.min);

            if (selectedTime < minTime) {
                showMessage('Departure time must be at least 30 minutes from now', 'error');
                this.value = minTime.toISOString().slice(0, 16);
            }
        });

        departureTimeInput.style.position = 'relative';
        departureTimeInput.style.zIndex = '10';

        if (!isSupported) {
            departureTimeInput.type = 'text';
            departureTimeInput.placeholder = 'YYYY-MM-DD HH:MM';
            departureTimeInput.pattern = '\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}';
        }

    }
}

function populateVehicleOptions(vehicles) {
    const select = document.getElementById('vehicleId');
    if (!select) return;

    select.innerHTML = '<option value="">Select a vehicle</option>';

    if (Array.isArray(vehicles)) {
        vehicles.forEach(vehicle => {
            if (vehicle && vehicle.status) {
                const option = document.createElement('option');
                option.value = vehicle.id;
                const vehicleName = vehicle.name || 'Vehicle';
                const plateNumber = vehicle.plate_number || 'No Plate';
                option.textContent = `${vehicleName} (${plateNumber})`;
                select.appendChild(option);
            }
        });
    }
}

function populateOutletOptions(outlets) {
    const originSelect = document.getElementById('originOutlet');
    const destSelect = document.getElementById('destinationOutlet');
    const intermediateSelect = document.getElementById('intermediateOutlet');

    [originSelect, destSelect, intermediateSelect].forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Select outlet</option>';
        }
    });

    if (Array.isArray(outlets)) {
        outlets.forEach(outlet => {
            if (outlet && (outlet.name || outlet.outlet_name)) {
                [originSelect, destSelect, intermediateSelect].forEach(select => {
                    if (select) {
                        const option = document.createElement('option');
                        option.value = outlet.id;
                        option.textContent = outlet.name || outlet.outlet_name;
                        option.dataset.location = outlet.location || outlet.address || '';
                        select.appendChild(option);
                    }
                });
            }
        });
    }

    if (originSelect) {
        originSelect.addEventListener('change', handleOriginChange);
    }
    if (destSelect) {
        destSelect.addEventListener('change', handleDestinationChange);
    }
}

function handleDestinationChange() {
    updateStopsDisplay();

    if (currentStep === 3) {
        const originSelect = document.getElementById('originOutlet');
        const destSelect = document.getElementById('destinationOutlet');
        const originId = originSelect?.value;
        const destId = destSelect?.value;

        if (originId && destId) {
            setTimeout(() => loadAvailableParcels(), 100);
        }
    }
}

function handleOriginChange() {
    const originSelect = document.getElementById('originOutlet');
    const destSelect = document.getElementById('destinationOutlet');
    const intermediateSelect = document.getElementById('intermediateOutlet');

    if (!originSelect) return;

    const originId = originSelect.value;

    [destSelect, intermediateSelect].forEach(select => {
        if (select) {
            Array.from(select.options).forEach(option => {
                option.disabled = option.value === originId;
            });
        }
    });

    updateStopsDisplay();

    if (currentStep === 3) {
        const destId = destSelect?.value;
        if (originId && destId) {
            setTimeout(() => loadAvailableParcels(), 100);
        }
    }
}

function updateStopsDisplay() {
    const originSelect = document.getElementById('originOutlet');
    const destSelect = document.getElementById('destinationOutlet');
    const stopsDisplay = document.getElementById('stopsDisplay');

    if (!stopsDisplay) {
        return;
    }

    const originId = originSelect?.value;
    const destId = destSelect?.value;

    if (!originId && !destId) {
        stopsDisplay.innerHTML = '<p style="color: #6c757d; text-align: center;">Select origin and destination to see route</p>';
        return;
    }

    let html = '';

    if (originId && originSelect) {
        const originText = originSelect.options[originSelect.selectedIndex]?.text || 'Origin';
        html += `
            <div class="stop-item origin">
                <div>
                    <strong><i class="fas fa-play"></i> Origin: ${originText}</strong>
                    <small style="display: block; color: #666;">Stop 1</small>
                </div>
            </div>
        `;
    }

    tripData.stops.forEach((stop, index) => {
        html += `
            <div class="stop-item intermediate">
                <div>
                    <strong><i class="fas fa-map-marker"></i> ${stop.name}</strong>
                    <small style="display: block; color: #666;">Stop ${index + 2}</small>
                </div>
                <button type="button"
                        class="btn btn-danger remove-stop-btn"
                        data-stop-index="${index}"
                        onclick="event.stopPropagation(); removeIntermediateStop(${index})"
                        style="padding: 5px 10px; cursor: pointer;">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        `;
    });

    if (destId && destSelect) {
        const destText = destSelect.options[destSelect.selectedIndex]?.text || 'Destination';
        html += `
            <div class="stop-item destination">
                <div>
                    <strong><i class="fas fa-flag-checkered"></i> Destination: ${destText}</strong>
                    <small style="display: block; color: #666;">Stop ${tripData.stops.length + 2}</small>
                </div>
            </div>
        `;
    }

    stopsDisplay.innerHTML = html;
    updateRouteInfo();
}

function updateRouteInfo() {
    const routeInfo = document.getElementById('routeOutletsList');
    if (!routeInfo) return;

    const routeOutlets = getRouteOutletIds();
    const originSelect = document.getElementById('originOutlet');
    const destSelect = document.getElementById('destinationOutlet');

    if (routeOutlets.length === 0) {
        routeInfo.textContent = 'Select origin and destination first';
        routeInfo.style.color = '#6c757d';
        return;
    }

    const routeNames = [];

    if (originSelect?.value) {
        const originText = originSelect.options[originSelect.selectedIndex]?.text || 'Origin';
        routeNames.push(originText);
    }

    tripData.stops.forEach(stop => {
        routeNames.push(stop.name);
    });

    if (destSelect?.value && destSelect.value !== originSelect?.value) {
        const destText = destSelect.options[destSelect.selectedIndex]?.text || 'Destination';
        routeNames.push(destText);
    }

    routeInfo.innerHTML = routeNames.join(' <i class="fas fa-arrow-right" style="margin: 0 5px; color: #4A1C40;"></i> ');
    routeInfo.style.color = '#2E0D2A';
}

function addIntermediateStop() {
    const select = document.getElementById('intermediateOutlet');
    if (!select || !select.value) {
        showMessage('Please select an outlet for the intermediate stop', 'warning');
        return;
    }

    const selectedId = select.value;
    const selectedText = select.options[select.selectedIndex].text;

    if (tripData.stops.some(stop => stop.id === selectedId)) {
        showMessage('This outlet is already added as a stop', 'warning');
        return;
    }

    tripData.stops.push({
        id: selectedId,
        name: selectedText,
        order: tripData.stops.length + 2
    });

    select.value = '';
    updateStopsDisplay();
    showMessage('Intermediate stop added successfully', 'success');

    if (currentStep === 3) {
        setTimeout(() => loadAvailableParcels(), 100);
    }
}

function removeIntermediateStop(index) {
    if (index < 0 || index >= tripData.stops.length) {
        showMessage('Error: Invalid stop index', 'error');
        return;
    }

    try {
        tripData.stops.splice(index, 1);
        updateStopsDisplay();
        showMessage('Intermediate stop removed', 'info');

        if (currentStep === 3) {
            setTimeout(() => loadAvailableParcels(), 100);
        }
    } catch (error) {
        showMessage('Error removing stop: ' + error.message, 'error');
    }
}

function changeStep(direction) {
    const newStep = currentStep + direction;

    if (newStep < 1 || newStep > maxSteps) return;

    if (direction === 1 && !validateCurrentStep()) {
        return;
    }

    currentStep = newStep;
    updateStepDisplay();
}

function goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > maxSteps) {
        return;
    }

    if (stepNumber > currentStep) {
        if (!validateCurrentStep()) {
            return;
        }
    }

    currentStep = stepNumber;
    updateStepDisplay();
}

function updateStepDisplay() {
    document.querySelectorAll('.wizard-step').forEach((step, index) => {
        const stepNum = index + 1;
        step.classList.remove('active', 'completed');

        if (stepNum === currentStep) {
            step.classList.add('active');
        } else if (stepNum < currentStep) {
            step.classList.add('completed');
        }
    });

    document.querySelectorAll('.step-content').forEach((content, index) => {
        const stepNum = index + 1;
        const isActive = stepNum === currentStep;
        content.classList.toggle('active', isActive);
    });

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (prevBtn) prevBtn.disabled = currentStep === 1;
    if (nextBtn) nextBtn.style.display = currentStep === maxSteps ? 'none' : 'inline-flex';
    if (submitBtn) {
        submitBtn.style.display = currentStep === maxSteps ? 'inline-flex' : 'none';
        if (currentStep === maxSteps) {
            updateSubmitButtonState();
        }
    }

    if (currentStep === 3) {
        const originSelect = document.getElementById('originOutlet');
        const destSelect = document.getElementById('destinationOutlet');

        if (originSelect?.value && destSelect?.value) {
            setTimeout(() => loadAvailableParcels(), 200);
        }
    } else if (currentStep === 4) {
        updateTripSummary();
        updateSubmitButtonState();
    }
}

function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            const vehicleId = document.getElementById('vehicleId')?.value;
            const originId = document.getElementById('originOutlet')?.value;
            const destinationId = document.getElementById('destinationOutlet')?.value;

            if (!vehicleId && !originId && !destinationId) {
                showMessage('Complete the form for full validation', 'info');
                return true;
            }

            if (vehicleId && originId && destinationId) {
                if (originId === destinationId) {
                    showMessage('Origin and destination cannot be the same', 'error');
                    return false;
                }

                tripData.vehicle = vehicleId;
                tripData.originOutlet = originId;
                tripData.destinationOutlet = destinationId;
                tripData.departureTime = document.getElementById('departureTime')?.value;
            }

            return true;

        case 2:
            return true;

        case 3:
            return true;

        default:
            return true;
    }
}

function updateTripSummary() {
    const summaryContainer = document.getElementById('tripSummary');
    if (!summaryContainer) return;

    const vehicleSelect = document.getElementById('vehicleId');
    const originSelect = document.getElementById('originOutlet');
    const destSelect = document.getElementById('destinationOutlet');

    const vehicleText = vehicleSelect?.options[vehicleSelect.selectedIndex]?.text || 'No vehicle selected';
    const originText = originSelect?.options[originSelect.selectedIndex]?.text || 'No origin selected';
    const destText = destSelect?.options[destSelect.selectedIndex]?.text || 'No destination selected';

    const selectedParcelCount = tripData.selectedParcels.length;
    const totalWeight = tripData.selectedParcels.reduce((sum, parcelId) => {
        const parcel = availableParcels.find(p => p.id === parcelId);
        return sum + (parseFloat(parcel?.parcel_weight || 0));
    }, 0);

    const totalValue = tripData.selectedParcels.reduce((sum, parcelId) => {
        const parcel = availableParcels.find(p => p.id === parcelId);
        return sum + (parseFloat(parcel?.declared_value || 0));
    }, 0);

    let html = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                <h4><i class="fas fa-truck"></i> Vehicle</h4>
                <p>${vehicleText}</p>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                <h4><i class="fas fa-play"></i> Origin</h4>
                <p>${originText}</p>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                <h4><i class="fas fa-flag-checkered"></i> Destination</h4>
                <p>${destText}</p>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                <h4><i class="fas fa-map-marker"></i> Stops</h4>
                <p>${tripData.stops.length} intermediate</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                <h4><i class="fas fa-box"></i> Parcels</h4>
                <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">${selectedParcelCount}</p>
            </div>
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                <h4><i class="fas fa-weight"></i> Total Weight</h4>
                <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">${totalWeight.toFixed(2)} kg</p>
            </div>
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid #4A1C40;">
                <h4><i class="fas fa-dollar-sign"></i> Total Value</h4>
                <p style="font-size: 1.5rem; font-weight: bold; color: #2E0D2A;">ZMW ${totalValue.toFixed(2)}</p>
            </div>
        </div>
    `;

    if (selectedParcelCount > 0) {
        html += `
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <h4 style="color: #2E0D2A; margin-bottom: 15px;">
                    <i class="fas fa-list"></i> Parcel Manifest
                </h4>
                <div style="max-height: 300px; overflow-y: auto;">
        `;

        tripData.selectedParcels.forEach((parcelId, index) => {
            const parcel = availableParcels.find(p => p.id === parcelId);
            if (parcel) {
                const weight = parcel.weight_display || (parcel.parcel_weight != null ? parcel.parcel_weight + ' kg' : 'N/A');
                const value = parcel.parcel_value ?? parcel.declared_value ?? parcel.delivery_fee ?? 0;
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: white; margin-bottom: 8px; border-radius: 6px; border-left: 4px solid #4A1C40;">
                        <div>
                            <strong>${parcel.track_number}</strong>
                            <small style="display: block; color: #666;">${parcel.sender_name || 'Unknown'} → ${parcel.destination_outlet?.outlet_name || 'N/A'}</small>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 500;">${weight}</div>
                            <small style="color: #666;">ZMW ${parseFloat(value).toFixed(2)}</small>
                        </div>
                    </div>
                `;
            }
        });

        html += `
                </div>
            </div>
        `;
    }

    summaryContainer.innerHTML = html;
    updateSubmitButtonState();
}

async function submitTrip() {
    if (!confirm('Create this trip? This action cannot be undone.')) {
        return;
    }

    if (!validateTripData()) {
        return;
    }

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;

    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Trip...';

        showMessage('Creating trip...', 'info');

        const tripPayload = {
            vehicle_id: document.getElementById('vehicleId').value,
            origin_outlet: document.getElementById('originOutlet').value,
            destination_outlet: document.getElementById('destinationOutlet').value,
            departure_time: document.getElementById('departureTime').value,
            route_stops: tripData.stops.map(stop => ({
                id: stop.id,
                name: stop.name,
                order: stop.order
            })),
            selected_parcels: tripData.selectedParcels
        };

        const response = await fetch('api/create_trip.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(tripPayload)
        });

        if (response.ok) {
            const result = await response.json();

            if (result.success) {
                showMessage(`Trip created successfully! Trip ID: ${result.trip_id}`, 'success');

                displayTripCreationSuccess(result);

                setTimeout(() => {
                    if (confirm('Trip created successfully! Would you like to go back to the dashboard?')) {
                        window.location.href = 'outlet_dashboard.php';
                    }
                }, 3000);

            } else {
                throw new Error(result.error || 'Failed to create trip');
            }
        } else {
            const errorResult = await response.json().catch(() => ({}));
            throw new Error(errorResult.error || `Server error: ${response.status}`);
        }

    } catch (error) {
        showMessage('Error creating trip: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

function validateTripData(showMessages = true) {
    const vehicleId = document.getElementById('vehicleId').value;
    const originOutlet = document.getElementById('originOutlet').value;
    const destOutlet = document.getElementById('destinationOutlet').value;
    const departureTime = document.getElementById('departureTime').value;

    if (!vehicleId) {
        if (showMessages) {
            showMessage('Please select a vehicle for the trip', 'error');
            goToStep(1);
        }
        return false;
    }

    if (!originOutlet || !destOutlet) {
        if (showMessages) {
            showMessage('Please select both origin and destination outlets', 'error');
            goToStep(2);
        }
        return false;
    }

    if (!departureTime) {
        if (showMessages) {
            showMessage('Please set a departure time for the trip', 'error');
            goToStep(1);
        }
        return false;
    }

    if (tripData.selectedParcels.length === 0) {
        if (showMessages) {
            showMessage('Please select at least one parcel for this trip', 'error');
            goToStep(3);
        }
        return false;
    }

    const now = new Date();
    const selectedTime = new Date(departureTime);
    if (selectedTime <= now) {
        if (showMessages) {
            showMessage('Departure time must be in the future', 'error');
            goToStep(1);
        }
        return false;
    }

    return true;
}

function displayTripCreationSuccess(result) {
    const summaryContainer = document.getElementById('tripSummary');
    if (!summaryContainer) return;

    const successHtml = `
        <div style="background: linear-gradient(135deg, #d4edda, #c3e6cb); padding: 30px; border-radius: 15px; text-align: center; border: 3px solid #28a745;">
            <div style="font-size: 3rem; color: #28a745; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 style="color: #155724; margin-bottom: 20px;">Trip Created Successfully!</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
                <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-route"></i> Trip ID</h4>
                    <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">${result.trip_id}</p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-map-marker-alt"></i> Stops Created</h4>
                    <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">
                        ${(() => {
                            const serverCount = result.trip_stops_created;
                            if (serverCount && serverCount > 0) return serverCount;
                            let local = 0;
                            if (typeof tripData !== 'undefined') {
                                local = (tripData.stops ? tripData.stops.length : 0) + 2;
                                if (document.getElementById('originOutlet').value === document.getElementById('destinationOutlet').value) {
                                    local = (tripData.stops ? tripData.stops.length : 0) + 1;
                                }
                            }
                            return local;
                        })()}
                    </p>
                </div>
                <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #28a745;">
                    <h4 style="color: #28a745; margin-bottom: 10px;"><i class="fas fa-box"></i> Parcels Assigned</h4>
                    <p style="font-size: 1.2rem; font-weight: bold; color: #155724;">
                        ${result.parcels_assigned > 0 ? result.parcels_assigned : (typeof tripData !== 'undefined' ? tripData.selectedParcels.length : 0)}
                    </p>
                </div>
            </div>

            <div style="margin-top: 25px;">
                <p style="font-size: 1.1rem; color: #155724; margin-bottom: 15px;">
                    <strong>Status:</strong> Scheduled and ready for dispatch
                </p>
                <p style="color: #666; font-style: italic;">
                    You can now manage this trip from the dashboard or assign a driver.
                </p>
            </div>

            <div style="margin-top: 30px;">
                <button onclick="window.location.href='outlet_dashboard.php'" class="btn btn-primary" style="margin-right: 15px;">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </button>
                <button onclick="window.location.reload()" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Create Another Trip
                </button>
            </div>
        </div>
    `;

    summaryContainer.innerHTML = successHtml;
}

function updateSubmitButtonState() {
    const submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) return;

    const isFormComplete = validateTripData(false);
    const hasMinimumData =
        document.getElementById('vehicleId')?.value &&
        document.getElementById('originOutlet')?.value &&
        document.getElementById('destinationOutlet')?.value &&
        document.getElementById('departureTime')?.value;

    if (isFormComplete) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-success');
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Create Trip';
        submitBtn.title = 'All requirements met - ready to create trip';
    } else if (hasMinimumData) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-warning');
        submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Create Trip (Validation Required)';
        submitBtn.title = 'Some validation issues - click to see details';
    } else {
        submitBtn.disabled = true;
        submitBtn.classList.remove('btn-success', 'btn-warning');
        submitBtn.classList.add('btn-secondary');
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Incomplete Form';
        submitBtn.title = 'Please complete all required fields';
    }
}

function getRouteOutletIds() {
    const routeOutlets = [];

    const originSelect = document.getElementById('originOutlet');
    if (originSelect && originSelect.value) {
        routeOutlets.push(originSelect.value);
    }

    if (tripData.stops && tripData.stops.length > 0) {
        tripData.stops.forEach(stop => {
            if (stop.id && !routeOutlets.includes(stop.id)) {
                routeOutlets.push(stop.id);
            }
        });
    }

    const destSelect = document.getElementById('destinationOutlet');
    if (destSelect && destSelect.value) {
        if (!routeOutlets.includes(destSelect.value)) {
            routeOutlets.push(destSelect.value);
        }
    }

    return routeOutlets;
}

async function loadAvailableParcels() {
    const container = document.getElementById('parcelsContainer');
    const loadBtn = document.getElementById('loadParcelsBtn');
    const statsDiv = document.getElementById('parcelStats');

    if (!container || !loadBtn) return;

    loadBtn.disabled = true;
    loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    container.innerHTML = '<div class="loading-spinner"></div><p style="text-align: center; color: #666;">Fetching available parcels...</p>';

    try {
        const routeOutlets = getRouteOutletIds();

        if (routeOutlets.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #6c757d; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;"><i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i><p>Please select origin and destination outlets first to load matching parcels.</p></div>';
            loadBtn.disabled = false;
            loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Load Parcels';
            return;
        }

        const response = await fetch('api/fetch_available_parcels.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                outlet_filter: routeOutlets,
                status_filter: ['pending', 'ready_for_dispatch']
            })
        });

        if (response.ok) {
            const responseText = await response.text();

            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error('Invalid JSON response from server. The server may have returned HTML or PHP errors.');
            }

            if (Array.isArray(responseData)) {
                availableParcels = responseData;
            } else if (responseData.success && Array.isArray(responseData.parcels)) {
                availableParcels = responseData.parcels;
            } else if (responseData.success && Array.isArray(responseData.data)) {
                availableParcels = responseData.data;
            } else if (responseData.error) {
                throw new Error(responseData.error);
            } else {
                throw new Error('Unexpected response format');
            }

            displayParcels(availableParcels);
            updateParcelStats(availableParcels);

            const routeOutletNames = routeOutlets.map(outletId => {
                const originSelect = document.getElementById('originOutlet');
                const destSelect = document.getElementById('destinationOutlet');
                const intermediateSelect = document.getElementById('intermediateOutlet');

                for (let select of [originSelect, destSelect, intermediateSelect]) {
                    if (select) {
                        for (let option of select.options) {
                            if (option.value === outletId) {
                                return option.text;
                            }
                        }
                    }
                }
                return outletId;
            });

            showMessage(`${availableParcels.length} parcels loaded for route: ${routeOutletNames.join(' → ')}`, 'success');
        } else {
            const errorText = await response.text();

            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (parseError) {
                throw new Error(`Server returned non-JSON error response (HTTP ${response.status}). Check server logs.`);
            }

            if (response.status === 401) {
                throw new Error('Session expired. Please refresh the page and login again.');
            } else if (errorData.error) {
                throw new Error(errorData.error);
            } else {
                throw new Error(`Server error: ${response.status}`);
            }
        }
    } catch (error) {
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545; background: #f8d7da; border-radius: 8px; border: 2px solid #f5c6cb;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i><p>Error loading parcels. Please try again.</p></div>';
        showMessage('Error loading parcels: ' + error.message, 'error');
    }

    loadBtn.disabled = false;
    loadBtn.innerHTML = '<i class="fas fa-refresh"></i> Refresh Parcels';
}

function updateParcelStats(parcels) {
    const statsDiv = document.getElementById('parcelStats');
    if (statsDiv && Array.isArray(parcels)) {
        const totalWeight = parcels.reduce((sum, p) => sum + (parseFloat(p.parcel_weight) || 0), 0);
        const totalValue = parcels.reduce((sum, p) => sum + (parseFloat(p.parcel_value ?? p.declared_value ?? 0) || 0), 0);
        statsDiv.innerHTML = `
            <div style="display: flex; justify-content: space-around; padding: 15px; background: #e7f3ff; border-radius: 8px; margin-bottom: 20px;">
                <div style="text-align: center;">
                    <strong style="color: #2E0D2A; font-size: 18px;">${parcels.length}</strong>
                    <br><small style="color: #666;">Available Parcels</small>
                </div>
                <div style="text-align: center;">
                    <strong style="color: #2E0D2A; font-size: 18px;">${totalWeight.toFixed(2)} kg</strong>
                    <br><small style="color: #666;">Total Weight</small>
                </div>
                <div style="text-align: center;">
                    <strong style="color: #2E0D2A; font-size: 18px;">ZMW ${totalValue.toFixed(2)}</strong>
                    <br><small style="color: #666;">Total Value</small>
                </div>
            </div>
        `;
    }
}

function displayParcels(parcels) {
    const container = document.getElementById('parcelsContainer');
    if (!container) return;

    if (!Array.isArray(parcels)) {
        container.innerHTML = '<p style="text-align: center; color: #dc3545;">Error: Invalid parcels data format.</p>';
        return;
    }

    if (parcels.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-inbox fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No available parcels found for assignment</p>
                <small>Parcels must be unassigned and ready for dispatch</small>
            </div>
        `;
        return;
    }

    let html = '';
    parcels.forEach(parcel => {
        const isSelected = tripData.selectedParcels.includes(parcel.id);
        html += `
            <div class="parcel-item ${isSelected ? 'selected' : ''}" onclick="toggleParcelSelection('${parcel.id}')">
                <div class="parcel-header">
                    <div class="parcel-track"># ${parcel.track_number}</div>
                    <div class="parcel-status ${parcel.status}">${parcel.status.replace('_', ' ')}</div>
                </div>

                <div class="parcel-details">
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Sender</div>
                        <div class="parcel-detail-value">${parcel.sender_name || 'N/A'}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Receiver</div>
                        <div class="parcel-detail-value">${parcel.receiver_name || 'N/A'}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Weight</div>
                        <div class="parcel-detail-value">${parcel.weight_display || (parcel.parcel_weight != null ? parcel.parcel_weight + ' kg' : 'N/A')}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Origin</div>
                        <div class="parcel-detail-value">${parcel.origin_outlet?.outlet_name || parcel.origin_outlet_name || 'Current Outlet'}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Destination</div>
                        <div class="parcel-detail-value">${parcel.destination_outlet?.outlet_name || parcel.destination_outlet_name || 'N/A'}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Value</div>
                        <div class="parcel-detail-value">${parcel.value_display || 'ZMW ' + parseFloat(parcel.parcel_value || parcel.delivery_fee || 0).toFixed(2)}</div>
                    </div>
                    <div class="parcel-detail">
                        <div class="parcel-detail-label">Delivery Fee</div>
                        <div class="parcel-detail-value">${parcel.fee_display || 'ZMW ' + parseFloat(parcel.delivery_fee || 0).toFixed(2)}</div>
                    </div>
                </div>

                <div class="parcel-actions">
                    <small style="color: #666;">
                        Created: ${new Date(parcel.created_at).toLocaleDateString()}
                    </small>
                    <button class="parcel-select-btn ${isSelected ? 'deselect' : 'select'}"
                            onclick="event.stopPropagation(); toggleParcelSelection('${parcel.id}')">
                        ${isSelected ? 'Remove' : 'Select'}
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function toggleParcelSelection(parcelId) {
    const index = tripData.selectedParcels.indexOf(parcelId);

    if (index === -1) {
        tripData.selectedParcels.push(parcelId);
        showMessage('Parcel added to selection', 'success');
    } else {
        tripData.selectedParcels.splice(index, 1);
        showMessage('Parcel removed from selection', 'info');
    }

    displayParcels(availableParcels);
    updateSelectedParcelsDisplay();
}

function updateSelectedParcelsDisplay() {
    const section = document.getElementById('selectedParcelsSection');
    const countSpan = document.getElementById('selectedCount');
    const listDiv = document.getElementById('selectedParcelsList');

    if (!section || !countSpan || !listDiv) return;

    if (tripData.selectedParcels.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    countSpan.textContent = tripData.selectedParcels.length;

    let html = '';
    tripData.selectedParcels.forEach(parcelId => {
        const parcel = availableParcels.find(p => p.id === parcelId);
        if (parcel) {
            html += `
                <div class="selected-parcel-item">
                    <div>
                        <strong>${parcel.track_number}</strong><br>
                        <small>${parcel.receiver_name} | ${parcel.weight_display}</small>
                    </div>
                    <button class="btn btn-danger" onclick="toggleParcelSelection('${parcelId}')" style="padding: 5px 10px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
    });

    listDiv.innerHTML = html;
}

function showMessage(message, type = 'info') {
    document.querySelectorAll('.notification').forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; margin-left: 10px;">×</button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 4000);
}


console.log('🔥 create_parcel.js file loaded successfully');

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('newParcelForm');
  const uploadZone = document.getElementById('uploadZone');
  const fileInput = document.getElementById('parcelPhotos');
  const photoPreview = document.getElementById('photoPreview');
  const calculatedFeeInput = document.getElementById('calculatedFee');
  const destinationSelect = document.getElementById('destinationOutlet');

  
  let billingConfig = null;

  
  async function fetchBillingConfig() {
    const companyId = document.getElementById('companyId').value;
    try {
      const res = await fetch(`/WDParcelSendReceiverPWA/outlet-app/api/fetch_billing_config.php?company_id=${encodeURIComponent(companyId)}`);
      const data = await res.json();
      if (data.success) {
        billingConfig = data.config;
        console.log('Billing configuration loaded:', billingConfig);
        
        calculateFee();
      } else {
        console.error('Failed to fetch billing config:', data.error);
        showNotification('Failed to load billing configuration. Using defaults.', 'warning');
      }
    } catch (e) {
      console.error('Failed to fetch billing config:', e);
      showNotification('Failed to load billing configuration. Using defaults.', 'warning');
    }
  }

  
  fetchBillingConfig();

  
  async function fetchOutlets() {
    const companyId = document.getElementById('companyId').value;
    try {
      const res = await fetch(`/WDParcelSendReceiverPWA/outlet-app/api/fetch_company_outlets.php?company_id=${encodeURIComponent(companyId)}`);
      const data = await res.json();
      if (data.success) {
        destinationSelect.innerHTML = '<option value="">Select Destination Outlet</option>';
        data.outlets.forEach(outlet => {
          const opt = document.createElement('option');
          opt.value = outlet.id;
          opt.textContent = outlet.outlet_name;
          destinationSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.error('Failed to fetch outlets:', e);
    }
  }

  
  async function autofillCustomer(prefix) {
    const companyId = document.getElementById('companyId').value;
    const phone = document.getElementById(prefix + 'Phone').value;
    const email = document.getElementById(prefix + 'Email') ? document.getElementById(prefix + 'Email').value : '';
    
    if (!phone && !email) return;

    try {
      const params = new URLSearchParams({ company_id: companyId });
      if (phone) params.append('phone', phone);
      if (email) params.append('email', email);

      const url = `/WDParcelSendReceiverPWA/outlet-app/api/search_customer.php?${params}`;
      console.log('Searching customer with URL:', url);
      
      const res = await fetch(url);
      
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }
      
      
      const responseText = await res.text();
      console.log('Raw response:', responseText);
      
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (jsonError) {
        console.error('JSON parsing error:', jsonError);
        console.error('Response was:', responseText);
        throw new Error('Invalid JSON response from server');
      }
      
      console.log('Parsed response:', data);
      
      if (data.success && data.customer) {
        
        document.getElementById(prefix + 'Name').value = data.customer.customer_name || '';
        if (document.getElementById(prefix + 'Email')) {
          document.getElementById(prefix + 'Email').value = data.customer.email || '';
        }
        document.getElementById(prefix + 'Phone').value = data.customer.phone || '';
        if (document.getElementById(prefix + 'Address')) {
          document.getElementById(prefix + 'Address').value = data.customer.address || '';
        }
        
        
        showNotification('Customer details auto-filled!', 'success');
      } else if (data.success === false && data.error) {
        console.log('Customer not found or error:', data.error);
        
      }
    } catch (e) {
      console.error('Failed to search customer:', e);
      showNotification('Failed to search for existing customer', 'error');
    }
  }

  
  ['sender', 'recipient'].forEach(prefix => {
    const phoneInput = document.getElementById(prefix + 'Phone');
    const emailInput = document.getElementById(prefix + 'Email');
    
    if (phoneInput) {
      phoneInput.addEventListener('blur', () => autofillCustomer(prefix));
    }
    if (emailInput) {
      emailInput.addEventListener('blur', () => autofillCustomer(prefix));
    }
  });

  
  if (uploadZone && fileInput) {
    uploadZone.addEventListener('click', () => fileInput.click());
    
    uploadZone.addEventListener('dragover', e => {
      e.preventDefault();
      uploadZone.classList.add('drag-over');
    });
    
    uploadZone.addEventListener('dragleave', () => {
      uploadZone.classList.remove('drag-over');
    });
    
    uploadZone.addEventListener('drop', e => {
      e.preventDefault();
      uploadZone.classList.remove('drag-over');
      handleFiles(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', e => handleFiles(e.target.files));
  }

  function handleFiles(files) {
    const maxFiles = 5;
    const maxSize = 5 * 1024 * 1024; 
    photoPreview.innerHTML = '';
    
    let validFiles = [];
    
    for (let i = 0; i < files.length && validFiles.length < maxFiles; i++) {
      const file = files[i];
      
      if (!file.type.startsWith('image/')) {
        showNotification(`${file.name} is not an image file`, 'error');
        continue;
      }
      
      if (file.size > maxSize) {
        showNotification(`${file.name} is too large (max 5MB)`, 'error');
        continue;
      }
      
      validFiles.push(file);
      
      const div = document.createElement('div');
      div.className = 'photo-preview-item';
      
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'remove-photo';
      removeBtn.innerHTML = '<i class="fas fa-times"></i>';
      removeBtn.onclick = () => {
        div.remove();
        updateFileInput();
      };
      
      const fileName = document.createElement('span');
      fileName.className = 'file-name';
      fileName.textContent = file.name;
      
      div.append(img, removeBtn, fileName);
      photoPreview.appendChild(div);
    }
    
    
    updateFileInput();
  }

  function updateFileInput() {
    
    const items = photoPreview.querySelectorAll('.photo-preview-item');
    if (items.length === 0) {
      fileInput.value = '';
    }
  }

  
  async function calculateFee() {
    const weight = parseFloat(document.getElementById('parcelWeight').value) || 0;
    const deliveryOption = document.getElementById('deliveryOption').value;
    const parcelValue = parseFloat(document.getElementById('parcelValue').value) || 0;
    const insuranceAmount = parseFloat(document.getElementById('insuranceAmount').value) || 0;
    
    
    const length = parseFloat(document.getElementById('parcelLength')?.value) || 0;
    const width = parseFloat(document.getElementById('parcelWidth')?.value) || 0;
    const height = parseFloat(document.getElementById('parcelHeight')?.value) || 0;

    
    if (!weight || !deliveryOption) {
      calculatedFeeInput.value = '0.00';
      hideFeeBreakdown();
      return;
    }

    const companyId = document.getElementById('companyId').value;
    
    try {
      
      calculatedFeeInput.value = 'Calculating...';
      
      const requestData = {
        company_id: companyId,
        weight: weight,
        delivery_option: deliveryOption,
        parcel_value: parcelValue,
        insurance_amount: insuranceAmount,
        length: length,
        width: width,
        height: height
      };

      const response = await fetch('/WDParcelSendReceiverPWA/outlet-app/api/calculate_delivery_fee.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
      });

      const data = await response.json();
      
      if (data.success) {
        calculatedFeeInput.value = data.fee.toFixed(2);
        
        
        calculatedFeeInput.dataset.breakdown = JSON.stringify(data.breakdown);
        calculatedFeeInput.dataset.currency = data.currency;
        
        
        displayFeeBreakdown(data.breakdown, data.currency);
        
        console.log('Fee calculation:', {
          total: data.fee,
          breakdown: data.breakdown,
          currency: data.currency,
          config_used: data.config_used
        });
      } else {
        console.error('Fee calculation failed:', data.error);
        calculatedFeeInput.value = '0.00';
        hideFeeBreakdown();
        showNotification('Failed to calculate delivery fee', 'error');
      }
    } catch (error) {
      console.error('Fee calculation error:', error);
      calculatedFeeInput.value = '0.00';
      hideFeeBreakdown();
      showNotification('Error calculating delivery fee', 'error');
    }
  }

  
  function displayFeeBreakdown(breakdown, currency) {
    const feeBreakdownDiv = document.getElementById('feeBreakdown');
    if (!feeBreakdownDiv) return;

    let breakdownHTML = '<strong>Fee Breakdown:</strong><br>';
    
    if (breakdown.base_fee > 0) {
      breakdownHTML += `• Base Fee: ${breakdown.base_fee.toFixed(2)} ${currency}<br>`;
    }
    if (breakdown.weight_fee > 0) {
      breakdownHTML += `• Weight Fee: ${breakdown.weight_fee.toFixed(2)} ${currency}<br>`;
    }
    if (breakdown.volumetric_fee > 0) {
      breakdownHTML += `• Volumetric Fee: ${breakdown.volumetric_fee.toFixed(2)} ${currency}<br>`;
    }
    if (breakdown.insurance_fee > 0) {
      breakdownHTML += `• Insurance Fee: ${breakdown.insurance_fee.toFixed(2)} ${currency}<br>`;
    }
    if (breakdown.minimum_applied) {
      breakdownHTML += `• <em>Minimum fee applied</em><br>`;
    }

    feeBreakdownDiv.innerHTML = `<small class="fee-breakdown-text"><i class="fas fa-info-circle"></i> ${breakdownHTML}</small>`;
    feeBreakdownDiv.style.display = 'block';
  }

  
  function hideFeeBreakdown() {
    const feeBreakdownDiv = document.getElementById('feeBreakdown');
    if (feeBreakdownDiv) {
      feeBreakdownDiv.style.display = 'none';
    }
  }

  
  function displayParcelSuccess(result) {
    console.log('🔥 displayParcelSuccess called with result:', result);
    console.log('🔥 Current document.body:', document.body);
    console.log('🔥 CSS stylesheets loaded:', document.styleSheets.length);
    
    
    const successModal = document.createElement('div');
    successModal.className = 'parcel-success-modal';
    successModal.style.display = 'flex'; 
    successModal.innerHTML = `
      <div class="success-modal-content">
        <div class="success-header">
          <h3><i class="fas fa-check-circle"></i> Parcel Created Successfully!</h3>
          <button class="close-modal" onclick="this.closest('.parcel-success-modal').remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="success-body">
          <div class="tracking-info">
            <strong>Tracking Number:</strong> ${result.tracking_number}
          </div>
          ${result.barcode_url ? `
            <div class="barcode-section">
              <strong>Barcode:</strong>
              <div class="barcode-container">
                ${result.barcode_url.endsWith('.html') ? 
                  `<iframe src="${result.barcode_url}" width="300" height="150" frameborder="0"></iframe>` :
                  `<img src="${result.barcode_url}" alt="Barcode" class="barcode-image" onerror="console.error('Failed to load barcode image:', this.src)">`
                }
              </div>
              <div class="barcode-actions">
                <button onclick="window.open('${result.barcode_url}', '_blank')" class="btn btn-small">
                  <i class="fas fa-external-link-alt"></i> View Full Barcode
                </button>
                <button onclick="window.printBarcode('${result.barcode_url}')" class="btn btn-small">
                  <i class="fas fa-print"></i> Print Barcode
                </button>
              </div>
            </div>
          ` : `
            <div class="barcode-section">
              <span class="barcode-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                Barcode could not be generated${result.barcode_error ? ': ' + result.barcode_error : ''}
              </span>
            </div>
          `}
          <div class="parcel-summary">
            <div><strong>Parcel ID:</strong> ${result.parcel_id}</div>
            <div><strong>Status:</strong> ${result.parcel_status}</div>
            ${result.uploaded_photos > 0 ? `<div><strong>Photos:</strong> ${result.uploaded_photos} uploaded</div>` : ''}
          </div>
        </div>
        <div class="success-footer">
          <button onclick="this.closest('.parcel-success-modal').remove()" class="btn btn-primary">
            <i class="fas fa-check"></i> Close
          </button>
        </div>
      </div>
    `;
    
    
    const existingModals = document.querySelectorAll('.parcel-success-modal');
    existingModals.forEach(modal => modal.remove());
    
    
    document.body.appendChild(successModal);
    console.log('🔥 Success modal appended to body');
    console.log('🔥 Modal element:', successModal);
    console.log('🔥 Modal classes:', successModal.className);
    console.log('🔥 Modal style.display:', successModal.style.display);
    console.log('🔥 Body children count:', document.body.children.length);
    
    
    const computedStyle = window.getComputedStyle(successModal);
    console.log('🔥 Computed modal styles:', {
      position: computedStyle.position,
      display: computedStyle.display,
      zIndex: computedStyle.zIndex,
      backgroundColor: computedStyle.backgroundColor,
      visibility: computedStyle.visibility
    });
    
    
    successModal.offsetHeight;
    
    
    successModal.addEventListener('click', function(e) {
      if (e.target === successModal) {
        successModal.remove();
      }
    });
    
    
    setTimeout(() => {
      if (document.body.contains(successModal)) {
        successModal.remove();
      }
    }, 30000);
  }

  
  function printBarcode(barcodeUrl) {
    const printWindow = window.open(barcodeUrl, '_blank');
    if (printWindow) {
      printWindow.onload = function() {
        printWindow.print();
      };
    }
  }

  
  window.printBarcode = printBarcode;
  
  
  function testCreateParcelModal() {
    console.log('🔥 Testing modal directly from create_parcel.php...');
    const testResult = {
      tracking_number: 'TEST123456789',
      parcel_id: 'test-parcel-123',
      parcel_status: 'pending',
      barcode_url: 'api/barcode/TEST123456789.png',
      barcode_generated: true,
      uploaded_photos: 2
    };
    displayParcelSuccess(testResult);
  }
  window.testCreateParcelModal = testCreateParcelModal;
  
  
  window.testSuccessModal = function() {
    const testResult = {
      success: true,
      tracking_number: 'PKG12345TEST',
      parcel_id: 'test-uuid-12345',
      parcel_status: 'pending',
      barcode_url: '../assets/barcodes/test_barcode.png',
      barcode_generated: true,
      uploaded_photos: 2
    };
    console.log('Testing success modal with:', testResult);
    displayParcelSuccess(testResult);
  };
  
  
  window.testSimpleModal = function() {
    const modal = document.createElement('div');
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.8);
      z-index: 99999;
      display: flex;
      justify-content: center;
      align-items: center;
    `;
    modal.innerHTML = `
      <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>Test Modal</h3>
        <p>If you can see this, the modal system is working!</p>
        <button onclick="this.closest('div').parentElement.remove()">Close</button>
      </div>
    `;
    document.body.appendChild(modal);
  };

  
  document.getElementById('parcelWeight').addEventListener('input', calculateFee);
  document.getElementById('deliveryOption').addEventListener('change', calculateFee);
  document.getElementById('parcelValue').addEventListener('input', calculateFee);
  document.getElementById('insuranceAmount').addEventListener('input', calculateFee);
  
  
  const dimensionFields = ['parcelLength', 'parcelWidth', 'parcelHeight'];
  dimensionFields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
      field.addEventListener('input', calculateFee);
    }
  });

  
  form.addEventListener('submit', async e => {
    e.preventDefault();
    
    const submitBtn = document.getElementById('createParcelBtn');
    const originalText = submitBtn.innerHTML;
    
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    submitBtn.disabled = true;

    try {
      const formData = new FormData(form);
      const apiUrl = '/WDParcelSendReceiverPWA/outlet-app/api/create_parcel.php';

      console.log('Submitting form to:', apiUrl);
      
      const res = await fetch(apiUrl, {
        method: 'POST',
        body: formData
      });
      
      console.log('Response status:', res.status);

      
      const responseText = await res.text();
      console.log('Raw response:', responseText);

      
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (jsonError) {
        console.error('JSON parsing error:', jsonError);
        console.error('Response was:', responseText);
        throw new Error('Invalid JSON response from server. Check server logs.');
      }
      
      console.log('Parsed response:', result);

      if (!res.ok) {
        throw new Error(result.error || `HTTP ${res.status}: ${res.statusText}`);
      }

      if (result.success) {
        let successMessage = `Parcel created successfully! Tracking: ${result.tracking_number}`;
        
        
        if (result.barcode_generated && result.barcode_url) {
          successMessage += ' (Barcode generated)';
        } else if (result.barcode_error) {
          console.warn('Barcode generation issue:', result.barcode_error);
        }
        
        showNotification(successMessage, 'success');
        
        
        console.log('Complete API result:', JSON.stringify(result, null, 2));
        
        
        console.log('🔥 About to call displayParcelSuccess with result:', result);
        
        
        if (window.showSuccessModal) {
          window.showSuccessModal(result.tracking_number, result);
        } else {
          displayParcelSuccess(result);
        }
        console.log('🔥 displayParcelSuccess call completed');
        
        
        form.reset();
        photoPreview.innerHTML = '';
        calculatedFeeInput.value = '';
        hideFeeBreakdown();
        
        
        setTimeout(() => {
          
          console.log('Parcel created:', result);
        }, 5000); 
        
      } else {
        throw new Error(result.error || 'Unknown error occurred');
      }

    } catch (err) {
      console.error('Error:', err);
      showNotification(`Error: ${err.message}`, 'error');
    } finally {
      
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  });

  
  function showNotification(message, type = 'info') {
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
      <span>${message}</span>
      <button class="close-notification">&times;</button>
    `;
    
    
    document.body.appendChild(notification);
    
    
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 5000);
    
    
    notification.querySelector('.close-notification').addEventListener('click', () => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    });
  }

  
  fetchOutlets();
});

function testCreateParcelModal() {
  console.log('🔥 Testing modal directly from create_parcel.php...');
  const testResult = {
    tracking_number: 'TEST123456789',
    parcel_id: 'test-parcel-123',
    parcel_status: 'pending',
    barcode_url: '/WDParcelSendReceiverPWA/outlet-app/api/barcode/TEST123456789.png',
    barcode_generated: true,
    uploaded_photos: 2
  };
  
  
  displayParcelSuccessGlobal(testResult);
}

function displayParcelSuccessGlobal(result) {
  console.log('🔥 displayParcelSuccess called with result:', result);
  console.log('🔥 Current document.body:', document.body);
  console.log('🔥 CSS stylesheets loaded:', document.styleSheets.length);
  
  
  const successModal = document.createElement('div');
  successModal.className = 'parcel-success-modal';
  successModal.style.display = 'flex'; 
  successModal.innerHTML = `
    <div class="success-modal-content">
      <div class="success-header">
        <h3><i class="fas fa-check-circle"></i> Parcel Created Successfully!</h3>
        <button class="close-modal" onclick="this.closest('.parcel-success-modal').remove()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="success-body">
        <div class="tracking-info">
          <strong>Tracking Number:</strong> ${result.tracking_number}
        </div>
        ${result.barcode_url ? `
          <div class="barcode-section">
            <strong>Barcode:</strong>
            <div class="barcode-container">
              ${result.barcode_url.endsWith('.html') ? 
                `<iframe src="${result.barcode_url}" width="300" height="150" frameborder="0"></iframe>` :
                `<img src="${result.barcode_url}" alt="Barcode" class="barcode-image" onerror="console.error('Failed to load barcode image:', this.src)">`
              }
            </div>
            <div class="barcode-actions">
              <button onclick="window.open('${result.barcode_url}', '_blank')" class="btn btn-small">
                <i class="fas fa-external-link-alt"></i> View Full Barcode
              </button>
              <button onclick="printBarcodeGlobal('${result.barcode_url}')" class="btn btn-small">
                <i class="fas fa-print"></i> Print Barcode
              </button>
            </div>
          </div>
        ` : `
          <div class="barcode-section">
            <span class="barcode-warning">
              <i class="fas fa-exclamation-triangle"></i> 
              Barcode could not be generated${result.barcode_error ? ': ' + result.barcode_error : ''}
            </span>
          </div>
        `}
        <div class="parcel-summary">
          <div><strong>Parcel ID:</strong> ${result.parcel_id}</div>
          <div><strong>Status:</strong> ${result.parcel_status}</div>
          ${result.uploaded_photos > 0 ? `<div><strong>Photos:</strong> ${result.uploaded_photos} uploaded</div>` : ''}
        </div>
      </div>
      <div class="success-footer">
        <button onclick="this.closest('.parcel-success-modal').remove()" class="btn btn-primary">
          <i class="fas fa-check"></i> Close
        </button>
      </div>
    </div>
  `;
  
  
  const existingModals = document.querySelectorAll('.parcel-success-modal');
  existingModals.forEach(modal => modal.remove());
  
  
  document.body.appendChild(successModal);
  console.log('🔥 Success modal appended to body');
  console.log('🔥 Modal element:', successModal);
  console.log('🔥 Modal classes:', successModal.className);
  console.log('🔥 Modal style.display:', successModal.style.display);
  console.log('🔥 Body children count:', document.body.children.length);
  
  
  const computedStyle = window.getComputedStyle(successModal);
  console.log('🔥 Computed modal styles:', {
    position: computedStyle.position,
    display: computedStyle.display,
    zIndex: computedStyle.zIndex,
    backgroundColor: computedStyle.backgroundColor,
    visibility: computedStyle.visibility
  });
  
  
  successModal.offsetHeight;
  
  
  successModal.addEventListener('click', function(e) {
    if (e.target === successModal) {
      successModal.remove();
    }
  });
  
  
  setTimeout(() => {
    if (document.body.contains(successModal)) {
      successModal.remove();
    }
  }, 30000);
}

function printBarcodeGlobal(barcodeUrl) {
  const printWindow = window.open(barcodeUrl, '_blank');
  if (printWindow) {
    printWindow.onload = function() {
      printWindow.print();
    };
  }
}

function testSimpleModalGlobal() {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
  `;
  modal.innerHTML = `
    <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
      <h3>Test Modal</h3>
      <p>If you can see this, the modal system is working!</p>
      <button onclick="this.closest('div').parentElement.remove()">Close</button>
    </div>
  `;
  document.body.appendChild(modal);
  console.log('Simple modal added to body');
}

window.testCreateParcelModal = testCreateParcelModal;
window.displayParcelSuccessGlobal = displayParcelSuccessGlobal;
window.printBarcodeGlobal = printBarcodeGlobal;
window.testSimpleModalGlobal = testSimpleModalGlobal;

console.log('🔥 Global functions registered:', {
  testCreateParcelModal: typeof window.testCreateParcelModal,
  testSimpleModalGlobal: typeof window.testSimpleModalGlobal,
  displayParcelSuccessGlobal: typeof window.displayParcelSuccessGlobal
});

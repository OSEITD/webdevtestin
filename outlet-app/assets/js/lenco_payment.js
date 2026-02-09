
console.log(' Lenco Payment Handler Loading...');

//  payment state
let lencoPaymentState = {
    reference: null,
    amount: 0,
    method: null,
    inProgress: false
};

// Lenco payment handlers when DOM is ready

document.addEventListener('DOMContentLoaded', function() {
    console.log('Lenco Payment Handler Initialized');
    
    if (typeof LencoPay === 'undefined') {
        console.warn(' Lenco widget not loaded. Payment features may not work.');
    } else {
        console.log('Lenco widget loaded successfully');
    }
 
    setupPaymentMethodHandler();
    
    // Setup fee calculation for payments
    setupLencoFeeCalculation();
});

//payment method change handler
function setupPaymentMethodHandler() {
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const mobileMoneySection = document.getElementById('mobileMoneySection');
    const cardPaymentSection = document.getElementById('cardPaymentSection');
    const cashPaymentSection = document.getElementById('cashPaymentSection');
    const codPaymentSection = document.getElementById('codPaymentSection');
    const mobileNumberField = document.getElementById('mobileNumber');
    const cashAmountField = document.getElementById('cashAmount');
    const codAmountField = document.getElementById('codAmount');
    
    if (!paymentMethodSelect) {
        console.error('Payment method select not found');
        return;
    }
    
    function hideAllPaymentSections() {
        if (mobileMoneySection) mobileMoneySection.style.display = 'none';
        if (cardPaymentSection) cardPaymentSection.style.display = 'none';
        if (cashPaymentSection) cashPaymentSection.style.display = 'none';
        if (codPaymentSection) codPaymentSection.style.display = 'none';
        
        if (mobileNumberField) {
            mobileNumberField.removeAttribute('required');
            mobileNumberField.classList.remove('error');
        }
        if (cashAmountField) {
            cashAmountField.removeAttribute('required');
        }
        if (codAmountField) {
            codAmountField.removeAttribute('required');
        }
    }

    paymentMethodSelect.addEventListener('change', function() {
        const selectedMethod = this.value;
    
        hideAllPaymentSections();
       
        if (selectedMethod === 'lenco_mobile') {
            if (mobileMoneySection) {
                mobileMoneySection.style.display = 'block';
                if (mobileNumberField) {
                    mobileNumberField.setAttribute('required', 'required');
                    mobileNumberField.removeAttribute('disabled');
                }
                updatePaymentSummary('mobile');
            }
        } else if (selectedMethod === 'lenco_card') {
            if (cardPaymentSection) {
                cardPaymentSection.style.display = 'block';
                updatePaymentSummary('card');
            }
        } else if (selectedMethod === 'cash') {
            if (cashPaymentSection) {
                cashPaymentSection.style.display = 'block';
                if (cashAmountField) {
                    cashAmountField.setAttribute('required', 'required');
                }
            }
        } else if (selectedMethod === 'cod') {
            if (codPaymentSection) {
                codPaymentSection.style.display = 'block';
                if (codAmountField) {
                    codAmountField.setAttribute('required', 'required');
                }
            }
        }
        
        // Trigger payment summary update from parcel_registration.js
        if (typeof updatePaymentSummarySection === 'function') {
            updatePaymentSummarySection();
        }
    });

    
    (function init() {
        const initial = paymentMethodSelect.value;
        hideAllPaymentSections();
        if (initial === 'lenco_mobile' && mobileMoneySection) {
            mobileMoneySection.style.display = 'block';
            if (mobileNumberField) mobileNumberField.setAttribute('required', 'required');
            updatePaymentSummary('mobile');
        } else if (initial === 'lenco_card' && cardPaymentSection) {
            cardPaymentSection.style.display = 'block';
            updatePaymentSummary('card');
        } else if (initial === 'cash' && cashPaymentSection) {
            cashPaymentSection.style.display = 'block';
            if (cashAmountField) cashAmountField.setAttribute('required', 'required');
        } else if (initial === 'cod' && codPaymentSection) {
            codPaymentSection.style.display = 'block';
        }
    })();
}


function setupLencoFeeCalculation() {
    const deliveryFeeInput = document.getElementById('deliveryFee');
    const insuranceInput = document.getElementById('insuranceAmount');
    
    if (deliveryFeeInput) {
        deliveryFeeInput.addEventListener('input', () => {
            updatePaymentSummary('mobile');
            updatePaymentSummary('card');
        });
    }
    
    if (insuranceInput) {
        insuranceInput.addEventListener('input', () => {
            updatePaymentSummary('mobile');
            updatePaymentSummary('card');
        });
    }
}

//  payment summary display
function updatePaymentSummary(type) {
    const deliveryFee = parseFloat(document.getElementById('deliveryFee')?.value) || 0;
    const insurance = parseFloat(document.getElementById('insuranceAmount')?.value) || 0;
    const baseAmount = deliveryFee + insurance;
    
    //  transaction fee (2.5% for mobile, 2.9% for card)
    const feePercentage = type === 'mobile' ? 2.5 : 2.9;
    const transactionFee = (baseAmount * feePercentage) / 100;
    const totalAmount = baseAmount + transactionFee;
    
    if (type === 'mobile') {
        const mobileFeeEl = document.getElementById('mobileFeeAmount');
        const mobileTransactionFeeEl = document.getElementById('mobileTransactionFee');
        const mobileTotalEl = document.getElementById('mobileTotalAmount');
        
        if (mobileFeeEl) mobileFeeEl.textContent = `K ${baseAmount.toFixed(2)}`;
        if (mobileTransactionFeeEl) mobileTransactionFeeEl.textContent = `K ${transactionFee.toFixed(2)}`;
        if (mobileTotalEl) mobileTotalEl.textContent = `K ${totalAmount.toFixed(2)}`;
    } else if (type === 'card') {
        const cardTotalEl = document.getElementById('cardTotalAmount');
        if (cardTotalEl) cardTotalEl.textContent = `K ${totalAmount.toFixed(2)}`;
    }
}

// unique payment reference
function generatePaymentReference() {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `WDP-${timestamp}-${random}`;
}

// customer information from form

function collectCustomerInfo() {
    return {
        firstName: document.getElementById('senderName')?.value.split(' ')[0] || '',
        lastName: document.getElementById('senderName')?.value.split(' ').slice(1).join(' ') || '',
        email: document.getElementById('senderEmail')?.value || 'customer@example.com',
        phone: document.getElementById('senderPhone')?.value || document.getElementById('mobileNumber')?.value || ''
    };
}

/**
 *  Lenco payment
 * 
 * @param {string} channel - Payment channel: 'card' or 'mobile-money'
 */
function initiateLencoPayment(channel) {
    console.log(` Initiating Lenco payment - Channel: ${channel}`);
    
    if (lencoPaymentState.inProgress) {
        console.warn('Payment already in progress');
        return;
    }
    
    //  form Validation 
    if (!validatePaymentForm(channel)) {
        return;
    }
    
    if (typeof LencoPay === 'undefined') {
        alert('Payment system is not available. Please refresh the page and try again.');
        console.error('LencoPay widget not loaded');
        return;
    }
    
    if (!window.LENCO_CONFIG || !window.LENCO_CONFIG.publicKey) {
        alert('Payment configuration error. Please contact support.');
        console.error('Lenco configuration not found');
        return;
    }
    
    // Calculating amounts
    const deliveryFee = parseFloat(document.getElementById('deliveryFee')?.value) || 0;
    const insurance = parseFloat(document.getElementById('insuranceAmount')?.value) || 0;
    const amount = deliveryFee + insurance;
    
    if (amount <= 0) {
        alert('Please enter a valid delivery fee before proceeding with payment.');
        return;
    }
    
    const customer = collectCustomerInfo();
    
    //  unique reference
    const reference = generatePaymentReference();
    
    //  state Update
    lencoPaymentState = {
        reference: reference,
        amount: amount,
        method: channel,
        inProgress: true
    };
    
    //  button to load state
    const buttonId = channel === 'card' ? 'cardPayBtn' : 'mobileMoneyPayBtn';
    const button = document.getElementById(buttonId);
    if (button) {
        button.classList.add('loading');
        button.disabled = true;
    }
    
    console.log('Payment Details:', {
        reference,
        amount,
        channel,
        customer,
        currency: window.LENCO_CONFIG.currency
    });
    
    try {
        //  channels array based on selected method
        const channels = channel === 'card' ? ['card'] : ['mobile-money'];
        
        //  Lenco popup widget
        LencoPay.getPaid({
            key: window.LENCO_CONFIG.publicKey,
            reference: reference,
            email: customer.email || 'customer@parcel.co.zm',
            amount: amount,
            currency: window.LENCO_CONFIG.currency || 'ZMW',
            channels: channels,
            label: 'Parcel Delivery Payment',
            bearer: 'merchant',
            customer: {
                firstName: customer.firstName || 'Customer',
                lastName: customer.lastName || '',
                phone: customer.phone || ''
            },
            onSuccess: function(response) {
                console.log(' Lenco Payment Success:', response);
                handlePaymentSuccess(response);
            },
            onClose: function() {
                console.log('Payment window closed');
                handlePaymentClose();
            },
            onConfirmationPending: function() {
                console.log('⏳ Payment confirmation pending');
                handlePaymentPending();
            }
        });
        
    } catch (error) {
        console.error(' Error initiating Lenco payment:', error);
        alert('Failed to initiate payment. Please try again.');
        resetPaymentState(buttonId);
    }
}

/**
 * Detecting mobile network based on Zambian prefix
 * @param {string} phoneNumber - The phone number to check
 * @returns {string|null} 
 */
function detectZambianNetwork(phoneNumber) {
    const cleaned = phoneNumber.replace(/\D/g, '');
    
    if (cleaned.startsWith('097') || cleaned.startsWith('077')) {
        return 'Airtel';
    }
    
    if (cleaned.startsWith('096') || cleaned.startsWith('076')) {
        return 'MTN';
    }
    
    if (cleaned.startsWith('095') || cleaned.startsWith('075')) {
        return 'Zamtel';
    }
    
    return null;
}

/**
 *  payment form Validation before submission
 */
function validatePaymentForm(channel) {
    const senderName = document.getElementById('senderName')?.value.trim();
    const senderPhone = document.getElementById('senderPhone')?.value.trim();
    const deliveryFee = parseFloat(document.getElementById('deliveryFee')?.value) || 0;
    
    if (!senderName) {
        alert('Please enter sender name before proceeding with payment.');
        document.getElementById('senderName')?.focus();
        return false;
    }
    
    if (!senderPhone) {
        alert('Please enter sender phone number before proceeding with payment.');
        document.getElementById('senderPhone')?.focus();
        return false;
    }
    
    if (deliveryFee <= 0) {
        alert('Please enter a delivery fee amount before proceeding with payment.');
        document.getElementById('deliveryFee')?.focus();
        return false;
    }
    
    // For mobile money validating phone number
    if (channel === 'mobile-money') {
        const mobileNumber = document.getElementById('mobileNumber')?.value.trim();
        if (!mobileNumber) {
            alert('Please enter mobile money number for payment.');
            document.getElementById('mobileNumber')?.focus();
            return false;
        }
        
        //  Zambian mobile number format
        const cleaned = mobileNumber.replace(/\D/g, '');
        if (cleaned.length !== 10) {
            alert('Please enter a valid 10-digit Zambian mobile number (e.g., 0971234567).');
            document.getElementById('mobileNumber')?.focus();
            return false;
        }
        
        //  supported network prefixes
        const network = detectZambianNetwork(cleaned);
        if (!network) {
            alert('Unsupported mobile network. Please use:\n• Airtel: 097xxxxxxx or 077xxxxxxx\n• MTN: 096xxxxxxx or 076xxxxxxx\n• Zamtel: 095xxxxxxx or 075xxxxxxx');
            document.getElementById('mobileNumber')?.focus();
            return false;
        }
        
        console.log(` Detected network: ${network} for number ${cleaned}`);
    }
    
    return true;
}

//  successful payment
async function handlePaymentSuccess(response) {
    const reference = response.reference || lencoPaymentState.reference;
    console.log('Processing successful payment - Reference:', reference);
    
    try {
        // Verifying  payment with backend
        const verifyResponse = await fetch(window.LENCO_CONFIG.verifyUrl + '?reference=' + encodeURIComponent(reference), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const verifyData = await verifyResponse.json();
        console.log('Payment verification result:', verifyData);
        
        if (verifyData.success && verifyData.verified) {
            // Payment verified successfully
            showPaymentSuccessMessage(reference, verifyData.data);
            
            //  payment reference  stored in hidden field for form submission
            storePaymentReference(reference);
           
            enableFormSubmission();
            
        } else {
            // Verification failed but payment might still be processing
            console.warn('Payment verification returned:', verifyData);
            alert('Payment is being processed. Please wait for confirmation or check your payment status.');
        }
        
    } catch (error) {
        console.error('Error verifying payment:', error);
        alert('Payment completed but verification failed. Reference: ' + reference + '. Please contact support if needed.');
    }
    
    resetPaymentState();
}


function handlePaymentClose() {
    console.log('Payment cancelled by user');
    alert('Payment was not completed. Please try again when ready.');
    resetPaymentState();
}


function handlePaymentPending() {
    const reference = lencoPaymentState.reference;
    console.log('Payment pending confirmation - Reference:', reference);
    
    alert('Your payment is being processed. Reference: ' + reference + '. You will receive confirmation shortly.');
    
    // Storing reference for tracking
    storePaymentReference(reference);
    
    resetPaymentState();
}

function resetPaymentState(specificButtonId = null) {
    lencoPaymentState.inProgress = false;
    
   
    const buttons = specificButtonId 
        ? [document.getElementById(specificButtonId)]
        : [document.getElementById('cardPayBtn'), document.getElementById('mobileMoneyPayBtn')];
    
    buttons.forEach(button => {
        if (button) {
            button.classList.remove('loading');
            button.disabled = false;
        }
    });
}


function showPaymentSuccessMessage(reference, paymentData) {
    const amount = paymentData?.amount || lencoPaymentState.amount;
    const type = paymentData?.type || lencoPaymentState.method;
    
    //  success notification
    const notification = document.createElement('div');
    notification.className = 'payment-success-notification';
    notification.innerHTML = `
        <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 15px 0;">
            <h4 style="color: #155724; margin: 0 0 10px;"><i class="fas fa-check-circle"></i> Payment Successful!</h4>
            <p style="color: #155724; margin: 5px 0;"><strong>Amount:</strong> K ${parseFloat(amount).toFixed(2)}</p>
            <p style="color: #155724; margin: 5px 0;"><strong>Reference:</strong> ${reference}</p>
            <p style="color: #155724; margin: 5px 0;"><strong>Method:</strong> ${type === 'card' ? 'Card Payment' : 'Mobile Money'}</p>
            <p style="color: #155724; margin: 10px 0 0; font-size: 0.9em;">You can now complete the parcel registration.</p>
        </div>
    `;
    
    const paymentMethod = document.getElementById('paymentMethod');
    if (paymentMethod) {
        paymentMethod.closest('.form-group').after(notification);
    }
    
    const mobileMoneySection = document.getElementById('mobileMoneySection');
    const cardPaymentSection = document.getElementById('cardPaymentSection');
    if (mobileMoneySection) mobileMoneySection.style.display = 'none';
    if (cardPaymentSection) cardPaymentSection.style.display = 'none';
   
    if (paymentMethod) {
        paymentMethod.disabled = true;
    }
}

// payment reference for form submission

function storePaymentReference(reference) {
   
    let refInput = document.getElementById('lencoPaymentReference');
    if (!refInput) {
        refInput = document.createElement('input');
        refInput.type = 'hidden';
        refInput.id = 'lencoPaymentReference';
        refInput.name = 'lencoPaymentReference';
        document.getElementById('newParcelForm')?.appendChild(refInput);
    }
    refInput.value = reference;
    
    let statusInput = document.getElementById('onlinePaymentStatus');
    if (!statusInput) {
        statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.id = 'onlinePaymentStatus';
        statusInput.name = 'onlinePaymentStatus';
        document.getElementById('newParcelForm')?.appendChild(statusInput);
    }
    statusInput.value = 'paid';
}

// form submission after payment
function enableFormSubmission() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.add('payment-complete');
    }
    
    console.log('Form submission enabled after successful payment');
}

window.initiateLencoPayment = initiateLencoPayment;
window.detectZambianNetwork = detectZambianNetwork;

console.log(' Lenco Payment Handler Ready');

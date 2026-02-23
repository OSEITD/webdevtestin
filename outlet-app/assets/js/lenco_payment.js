
// Lenco Payment Handler — Production-ready

// Payment state (kept in closure-like scope)
let lencoPaymentState = {
    reference: null,
    amount: 0,
    method: null,
    inProgress: false
};

// Only log in non-production
const _lencoDebug = (window.LENCO_CONFIG?.environment === 'sandbox');
function lencoLog(...args) { if (_lencoDebug) console.log('[Lenco]', ...args); }
function lencoWarn(...args) { console.warn('[Lenco]', ...args); }

document.addEventListener('DOMContentLoaded', function() {
    lencoLog('Payment handler initialised');
    
    if (typeof LencoPay === 'undefined') {
        lencoWarn('Widget not loaded — payment features unavailable');
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
        lencoWarn('Payment method select not found');
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
    const mobileNumberInput = document.getElementById('mobileNumber');
    
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
    
    // Auto-detect mobile network when user types mobile money number
    if (mobileNumberInput) {
        mobileNumberInput.addEventListener('input', function() {
            const val = this.value.replace(/\D/g, '');
            if (val.length >= 3) {
                const network = detectZambianNetwork(val);
                if (network) {
                    const radioId = network.toLowerCase(); // 'mtn', 'airtel', 'zamtel'
                    const radio = document.getElementById(radioId);
                    if (radio) {
                        radio.checked = true;
                        lencoLog('Auto-detected network:', network);
                    }
                }
            }
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

// Crypto-secure payment reference
function generatePaymentReference() {
    const timestamp = Date.now();
    const randomBytes = new Uint8Array(8);
    crypto.getRandomValues(randomBytes);
    const random = Array.from(randomBytes, b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
    return `WDP-${timestamp}-${random}`;
}

// customer information from form
function collectCustomerInfo() {
    return {
        firstName: document.getElementById('senderName')?.value.split(' ')[0] || '',
        lastName: document.getElementById('senderName')?.value.split(' ').slice(1).join(' ') || '',
        email: document.getElementById('senderEmail')?.value || 'customer@example.com',
        phone: document.getElementById('senderPhone')?.value || '',
        mobileMoneyNumber: document.getElementById('mobileNumber')?.value || ''
    };
}

/**
 * Convert Zambian local phone number to international format for Lenco.
 * 09XXXXXXXX  -> 260XXXXXXXXX
 * +260XXXXXXX -> 260XXXXXXXXX
 * Already in 260... format -> pass through
 */
function toInternationalPhone(phone) {
    if (!phone) return '';
    let cleaned = phone.replace(/[\s\-\(\)\+]/g, '');
    // Local 10-digit starting with 0
    if (cleaned.length === 10 && cleaned.startsWith('0')) {
        return '260' + cleaned.substring(1);
    }
    // Already international 12-digit
    if (cleaned.length === 12 && cleaned.startsWith('260')) {
        return cleaned;
    }
    // 9 digits without leading 0
    if (cleaned.length === 9 && /^[5-9]/.test(cleaned)) {
        return '260' + cleaned;
    }
    return cleaned;
}

/**
 *  Lenco payment
 * 
 * @param {string} channel - Payment channel: 'card' or 'mobile-money'
 */
function initiateLencoPayment(channel) {
    lencoLog('Initiating payment - Channel:', channel);
    
    if (lencoPaymentState.inProgress) {
        lencoWarn('Payment already in progress');
        return;
    }
    
    //  form Validation 
    if (!validatePaymentForm(channel)) {
        return;
    }
    
    if (typeof LencoPay === 'undefined') {
        alert('Payment system is not available. Please refresh the page and try again.');
        lencoWarn('LencoPay widget not loaded');
        return;
    }
    
    if (!window.LENCO_CONFIG || !window.LENCO_CONFIG.publicKey) {
        alert('Payment configuration error. Please contact support.');
        lencoWarn('Lenco configuration not found');
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
    
    lencoLog('Payment initiating:', { reference, amount, channel });
    
    try {
        //  channels array based on selected method
        const channels = channel === 'card' ? ['card'] : ['mobile-money'];
        
        // For mobile money, use the dedicated mobile number field in international format
        const phoneForPayment = channel === 'mobile-money'
            ? toInternationalPhone(customer.mobileMoneyNumber || customer.phone)
            : toInternationalPhone(customer.phone);
        
        lencoLog('Phone for payment (intl):', phoneForPayment);
        
        //  Lenco popup widget
        const paymentConfig = {
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
                phone: phoneForPayment
            },
            onSuccess: function(response) {
                lencoLog('Payment success callback received');
                handlePaymentSuccess(response);
            },
            onClose: function() {
                lencoLog('Payment window closed');
                handlePaymentClose();
            },
            onConfirmationPending: function() {
                lencoLog('Payment confirmation pending');
                handlePaymentPending();
            }
        };
        
        // For mobile money, attach phone number as metadata so Lenco routes the USSD push
        if (channel === 'mobile-money' && phoneForPayment) {
            paymentConfig.metadata = {
                mobileMoneyNumber: phoneForPayment,
                mobileNetwork: detectZambianNetwork(customer.mobileMoneyNumber || customer.phone) || 'Unknown'
            };
        }
        
        LencoPay.getPaid(paymentConfig);
        
    } catch (error) {
        lencoWarn('Error initiating Lenco payment:', error);
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
    
    if (cleaned.startsWith('097') || cleaned.startsWith('077') || cleaned.startsWith('057')) {
        return 'Airtel';
    }

    if (cleaned.startsWith('096') || cleaned.startsWith('076')) {
        return 'MTN';
    }

    // Zamtel and others are not supported by allowed prefixes
    return null;
    
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
        
        //  Zambian mobile number format (accept +260 or local), then use network detection
        const cleaned = mobileNumber.replace(/\D/g, '');
        // convert to local 0XXXXXXXX format for detection
        let networkNumber = cleaned;
        if (networkNumber.startsWith('260') && networkNumber.length === 12) {
            networkNumber = '0' + networkNumber.slice(3);
        }
        const network = detectZambianNetwork(networkNumber);
        if (!network) {
            alert('Please enter a valid Zambian mobile money number using MTN (096,076) or Airtel (097,077,057) prefixes.');
            document.getElementById('mobileNumber')?.focus();
            return false;
        }
        lencoLog('Detected network:', network, 'for number ending', networkNumber.slice(-4));
        
        lencoLog('Detected network:', network, 'for number ending', networkNumber.slice(-4));
    }
    
    return true;
}

//  successful payment
async function handlePaymentSuccess(response) {
    const reference = response.reference || lencoPaymentState.reference;
    lencoLog('Verifying payment:', reference);
    
    try {
        // Server-side verification (includes session cookie for auth)
        const verifyResponse = await fetch(window.LENCO_CONFIG.verifyUrl + '?reference=' + encodeURIComponent(reference), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const verifyData = await verifyResponse.json();
        lencoLog('Verification result:', verifyData.success ? 'OK' : 'FAILED');
        
        if (verifyData.success && verifyData.verified) {
            // Payment verified successfully
            showPaymentSuccessMessage(reference, verifyData.data);
            
            //  payment reference  stored in hidden field for form submission
            storePaymentReference(reference);
           
            enableFormSubmission();
            
        } else {
            // Verification failed but payment might still be processing
            lencoWarn('Payment verification status:', verifyData.success);
            alert('Payment is being processed. Please wait for confirmation or check your payment status.');
        }
        
    } catch (error) {
        lencoWarn('Error verifying payment:', error.message);
        alert('Payment completed but verification failed. Reference: ' + reference + '. Please contact support if needed.');
    }
    
    resetPaymentState();
}


function handlePaymentClose() {
    lencoLog('Payment cancelled by user');
    alert('Payment was not completed. Please try again when ready.');
    resetPaymentState();
}


function handlePaymentPending() {
    const reference = lencoPaymentState.reference;
    lencoLog('Payment pending — Reference:', reference);
    
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
    
    lencoLog('Form submission enabled after successful payment');
}

window.initiateLencoPayment = initiateLencoPayment;
window.detectZambianNetwork = detectZambianNetwork;

lencoLog('Payment handler ready');

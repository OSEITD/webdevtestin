<?php
/**
 * Lenco Payment Webhook Handler
 * 
 * This endpoint receives webhook notifications from Lenco
 * when payment status changes (e.g., collection.successful)
 * 
 * Configure this URL in the Lenco dashboard webhook settings
 */

header('Content-Type: application/json');

require_once 'lenco_config.php';
require_once __DIR__ . '/../../config.php';

$rawInput = file_get_contents('php://input');
error_log("Lenco Webhook Received: " . $rawInput);

// Parse the webhook payload
$payload = json_decode($rawInput, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit();
}

$event = $payload['event'] ?? '';
$data = $payload['data'] ?? [];

error_log("Lenco Webhook Event: {$event}");

try {
    switch ($event) {
        case 'collection.successful':
            handleSuccessfulCollection($data);
            break;
            
        case 'collection.failed':
            handleFailedCollection($data);
            break;
            
        case 'collection.pending':
            handlePendingCollection($data);
            break;
            
        default:
            error_log("Unhandled Lenco webhook event: {$event}");
    }
    
    // Always returns 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    error_log("Lenco Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}


function handleSuccessfulCollection($data) {
    $reference = $data['reference'] ?? '';
    $lencoReference = $data['lencoReference'] ?? '';
    $amount = $data['amount'] ?? 0;
    $currency = $data['currency'] ?? 'ZMW';
    $completedAt = $data['completedAt'] ?? null;

    error_log("Processing successful payment - Reference: {$reference}, Amount: {$amount} {$currency}");

    // Updating payment transaction status
    $paymentUpdated = updatePaymentStatus($reference, 'successful', [
        'lenco_reference' => $lencoReference,
        'amount' => $amount,
        'currency' => $currency,
        'completed_at' => $completedAt,
        'payment_data' => $data
    ]);

    if ($paymentUpdated) {
        // Updating the corresponding parcel's payment status to 'paid'
        updateParcelPaymentStatus($reference);
    }
}

// failed collection webhook

function handleFailedCollection($data) {
    $reference = $data['reference'] ?? '';
    $reasonForFailure = $data['reasonForFailure'] ?? 'Unknown error';
    
    error_log("Payment failed - Reference: {$reference}, Reason: {$reasonForFailure}");
    
    updatePaymentStatus($reference, 'failed', [
        'error_message' => $reasonForFailure,
        'payment_data' => $data
    ]);
}

function handlePendingCollection($data) {
    $reference = $data['reference'] ?? '';
    
    error_log("Payment pending - Reference: {$reference}");
    
    updatePaymentStatus($reference, 'pending', [
        'payment_data' => $data
    ]);
}

/**
 * Updating payment status in database
 * 
 * @param string $reference 
 * @param string $status 
 * @param array $additionalData 
 */
function updatePaymentStatus($reference, $status, $additionalData = []) {
   
    $config = require __DIR__ . '/../../config.php';
    
    if (!$config || !isset($config['supabase'])) {
        error_log("Supabase configuration not found");
        return false;
    }
    
    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];
    
    try {
        //  updating payload
        $updateData = [
            'status' => $status,
            'updated_at' => date('c'),
            'metadata' => json_encode($additionalData)
        ];
        
        if ($status === 'successful' && isset($additionalData['lenco_reference'])) {
            $updateData['flutterwave_tx_ref'] = $additionalData['lenco_reference']; 
            $updateData['paid_at'] = $additionalData['completed_at'] ?? date('c');
        }
        
        if ($status === 'failed' && isset($additionalData['error_message'])) {
            $updateData['error_message'] = $additionalData['error_message'];
            $updateData['failed_at'] = date('c');
        }
       
        $url = $supabaseUrl . '/rest/v1/payment_transactions?tx_ref=eq.' . urlencode($reference);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($updateData),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Payment status updated successfully - Reference: {$reference}, Status: {$status}");
            return true;
        } else {
            error_log("Failed to update payment status - HTTP Code: {$httpCode}, Response: {$response}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Database error updating payment: " . $e->getMessage());
        return false;
    }
}

/**
 *  parcel payment status to 'paid' when payment is successful
 *
 * @param string $reference 
 */
function updateParcelPaymentStatus($reference) {
    
    $config = require __DIR__ . '/../../config.php';

    if (!$config || !isset($config['supabase'])) {
        error_log("Supabase configuration not found");
        return false;
    }

    $supabaseUrl = $config['supabase']['url'];
    $supabaseKey = $config['supabase']['service_role_key'];

    try {
        //getting the parcel_id from the payment transaction
        $getParcelUrl = $supabaseUrl . '/rest/v1/payment_transactions?select=parcel_id&tx_ref=eq.' . urlencode($reference);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $getParcelUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Failed to get parcel_id from payment transaction - HTTP Code: {$httpCode}");
            return false;
        }

        $paymentData = json_decode($response, true);
        if (empty($paymentData) || !isset($paymentData[0]['parcel_id'])) {
            error_log("No parcel_id found for payment reference: {$reference}");
            return false;
        }

        $parcelId = $paymentData[0]['parcel_id'];

        $updateParcelUrl = $supabaseUrl . '/rest/v1/parcels?id=eq.' . urlencode($parcelId);

        $updateData = [
            'payment_status' => 'paid',
            'updated_at' => date('c')
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $updateParcelUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($updateData),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Parcel payment status updated successfully - Parcel ID: {$parcelId}, Payment Reference: {$reference}");
            return true;
        } else {
            error_log("Failed to update parcel payment status - HTTP Code: {$httpCode}, Response: {$response}");
            return false;
        }

    } catch (Exception $e) {
        error_log("Database error updating parcel payment status: " . $e->getMessage());
        return false;
    }
}

?>

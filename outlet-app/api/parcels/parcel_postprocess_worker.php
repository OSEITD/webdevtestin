<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(400);
    echo "This script is for CLI only.";
    exit;
}

if ($argc < 2) {
    error_log('parcel_postprocess_worker: missing payload argument');
    exit(1);
}

$payload = base64_decode($argv[1]);
if ($payload === false) {
    error_log('parcel_postprocess_worker: invalid base64 payload');
    exit(1);
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    error_log('parcel_postprocess_worker: invalid json payload');
    exit(1);
}

require_once __DIR__ . '/../../includes/supabase-helper.php';
require_once __DIR__ . '/../../includes/notification_helper.php';
require_once __DIR__ . '/../../includes/sms_service.php';
require_once __DIR__ . '/../../includes/email_helper.php';

try {
    $notificationHelper = new NotificationHelper();
    $notificationParcelData = [
        'id' => $data['parcel_id'] ?? null,
        'track_number' => $data['track_number'] ?? null,
        'company_id' => $data['company_id'] ?? null,
        'origin_outlet_id' => $data['origin_outlet_id'] ?? null,
        'sender_name' => $data['sender_name'] ?? null,
        'receiver_name' => $data['receiver_name'] ?? null,
        'parcel_weight' => (float)($data['parcel_weight'] ?? 0),
        'status' => 'pending'
    ];

    if (!empty($data['user_id'])) {
        $notificationHelper->createParcelCreatedNotification($notificationParcelData, $data['user_id']);
    }
} catch (Exception $e) {
    error_log('parcel_postprocess_worker: notification error - ' . $e->getMessage());
}

try {
    $smsService = new SMSService();

    if (!empty($data['sender_phone'])) {
        $senderResult = $smsService->notifySender(
            $data['sender_phone'],
            $data['track_number'] ?? '',
            $data['receiver_name'] ?? ''
        );
        error_log('parcel_postprocess_worker: SMS to sender result: ' . json_encode($senderResult));
    }

    if (!empty($data['recipient_phone'])) {
        $receiverResult = $smsService->notifyReceiver(
            $data['recipient_phone'],
            $data['track_number'] ?? '',
            $data['sender_name'] ?? ''
        );
        error_log('parcel_postprocess_worker: SMS to receiver result: ' . json_encode($receiverResult));
    }
} catch (Exception $e) {
    error_log('parcel_postprocess_worker: SMS error - ' . $e->getMessage());
}

try {
    $emailHelper = new EmailHelper();

    $senderEmail = $data['sender_email'] ?? '';
    $receiverEmail = $data['recipient_email'] ?? '';

    $emailResults = $emailHelper->notifyParcelCreated([
        'track_number' => $data['track_number'] ?? '',
        'sender_name' => $data['sender_name'] ?? '',
        'receiver_name' => $data['receiver_name'] ?? '',
        'sender_email' => $senderEmail,
        'receiver_email' => $receiverEmail
    ]);

    error_log('parcel_postprocess_worker: email send results: ' . json_encode($emailResults));
} catch (Exception $e) {
    error_log('parcel_postprocess_worker: email error - ' . $e->getMessage());
}

// End worker
exit(0);

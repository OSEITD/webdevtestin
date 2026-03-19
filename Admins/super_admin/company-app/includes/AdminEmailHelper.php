<?php


$sharedEmailHelperPath = __DIR__ . '/../../../../outlet-app/includes/email_helper.php';
if (file_exists($sharedEmailHelperPath)) {
    require_once $sharedEmailHelperPath;
} else {
    throw new \RuntimeException("Email helper not found at: {$sharedEmailHelperPath}");
}

class AdminEmailHelper {
    private EmailHelper $helper;

    public function __construct() {
        $this->helper = new EmailHelper();
    }

    public function sendEmail(string $toEmail, string $toName, string $subject, string $body, ?string $replyTo = null): array {
        return $this->helper->sendEmail($toEmail, $toName, $subject, $body, $replyTo);
    }
}

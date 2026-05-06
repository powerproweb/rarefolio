<?php
declare(strict_types=1);

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_mailerlite.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

const CONTACT_UPLOAD_DIR = __DIR__ . '/../uploads/contact_support';
if (!is_dir(CONTACT_UPLOAD_DIR)) {
    mkdir(CONTACT_UPLOAD_DIR, 0755, true);
}

function cs_input_payload(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

function cs_value(array $payload, string $key): ?string
{
    $value = trim((string) ($payload[$key] ?? ''));
    return $value === '' ? null : $value;
}

function cs_ref(): string
{
    return 'RF-CS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

$payload = cs_input_payload();
$errors = [];

$inquiryType = strtolower((string) ($payload['inquiry_type'] ?? 'contact'));
if (!in_array($inquiryType, ['contact', 'support'], true)) {
    $errors[] = 'Inquiry type must be contact or support.';
}

$fullName = cs_value($payload, 'full_name');
$email = cs_value($payload, 'email');
$subject = cs_value($payload, 'subject');
$message = cs_value($payload, 'message');
$walletId = cs_value($payload, 'wallet_id');
$orderReference = cs_value($payload, 'order_reference');

if ($fullName === null) {
    $errors[] = 'Full name is required.';
}

if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($subject === null) {
    $errors[] = 'Subject is required.';
}

if ($message === null) {
    $errors[] = 'Message is required.';
}

$consent = !empty($payload['consent_contact']);
if (!$consent) {
    $errors[] = 'Contact consent is required.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $errors,
    ]);
    exit;
}

$ticketRef = cs_ref();
$record = [
    'ticket_ref'      => $ticketRef,
    'created_at_utc'  => gmdate('c'),
    'inquiry_type'    => $inquiryType,
    'full_name'       => $fullName,
    'email'           => $email,
    'subject'         => $subject,
    'message'         => $message,
    'wallet_id'       => $walletId,
    'order_reference' => $orderReference,
    'opt_in_updates'  => !empty($payload['opt_in_updates']) ? 1 : 0,
    'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$recordPath = CONTACT_UPLOAD_DIR . '/' . $ticketRef . '.json';
$saved = file_put_contents($recordPath, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($saved === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save your message. Please try again.',
    ]);
    exit;
}

$mailerLiteFields = [];
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_SUBJECT', '{{MAILERLITE_FIELD_CONTACT_SUBJECT}}', $subject);
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_MESSAGE', '{{MAILERLITE_FIELD_CONTACT_MESSAGE}}', substr((string) $message, 0, 800));
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_TYPE', '{{MAILERLITE_FIELD_CONTACT_TYPE}}', $inquiryType);
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_REF', '{{MAILERLITE_FIELD_CONTACT_REF}}', $ticketRef);
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_WALLET', '{{MAILERLITE_FIELD_CONTACT_WALLET}}', $walletId);
qd_ml_add_field($mailerLiteFields, 'MAILERLITE_FIELD_CONTACT_ORDER', '{{MAILERLITE_FIELD_CONTACT_ORDER}}', $orderReference);

$groupId = $inquiryType === 'support'
    ? qd_ml_config_value('MAILERLITE_GROUP_ID_SUPPORT', '{{MAILERLITE_GROUP_ID_SUPPORT}}')
    : qd_ml_config_value('MAILERLITE_GROUP_ID_CONTACT', '{{MAILERLITE_GROUP_ID_CONTACT}}');

$mailerliteSync = qd_ml_subscribe([
    'email'    => (string) $email,
    'name'     => (string) $fullName,
    'group_id' => $groupId,
    'fields'   => $mailerLiteFields,
]);

if (!$mailerliteSync['success'] && !in_array($mailerliteSync['reason'], ['not_configured', 'missing_email'], true)) {
    error_log('Contact/support MailerLite sync failed: ' . json_encode([
        'reason'    => $mailerliteSync['reason'],
        'http_code' => $mailerliteSync['http_code'] ?? null,
        'ticket_ref'=> $ticketRef,
    ]));
}

echo json_encode([
    'success'      => true,
    'message'      => 'Message received successfully.',
    'ticket_ref'   => $ticketRef,
    'mailerlite'   => [
        'synced' => $mailerliteSync['success'],
        'status' => $mailerliteSync['reason'],
    ],
]);

<?php
declare(strict_types=1);

/**
 * Rarefolio MailerLite helper.
 *
 * Configure these values in your server environment when ready:
 * - MAILERLITE_API_TOKEN={{MAILERLITE_API_TOKEN}}
 * - MAILERLITE_API_BASE_URL=https://connect.mailerlite.com/api
 * - MAILERLITE_GROUP_ID_CONTACT={{MAILERLITE_GROUP_ID_CONTACT}}
 * - MAILERLITE_GROUP_ID_SUPPORT={{MAILERLITE_GROUP_ID_SUPPORT}}
 * - MAILERLITE_GROUP_ID_ARTIST_APPLICATION={{MAILERLITE_GROUP_ID_ARTIST_APPLICATION}}
 *
 * Optional custom field key mappings (set only if those custom fields exist in MailerLite):
 * - MAILERLITE_FIELD_CONTACT_SUBJECT={{MAILERLITE_FIELD_CONTACT_SUBJECT}}
 * - MAILERLITE_FIELD_CONTACT_MESSAGE={{MAILERLITE_FIELD_CONTACT_MESSAGE}}
 * - MAILERLITE_FIELD_CONTACT_TYPE={{MAILERLITE_FIELD_CONTACT_TYPE}}
 * - MAILERLITE_FIELD_CONTACT_REF={{MAILERLITE_FIELD_CONTACT_REF}}
 * - MAILERLITE_FIELD_CONTACT_WALLET={{MAILERLITE_FIELD_CONTACT_WALLET}}
 * - MAILERLITE_FIELD_CONTACT_ORDER={{MAILERLITE_FIELD_CONTACT_ORDER}}
 * - MAILERLITE_FIELD_ARTIST_APP_REF={{MAILERLITE_FIELD_ARTIST_APP_REF}}
 * - MAILERLITE_FIELD_ARTIST_MEDIUM={{MAILERLITE_FIELD_ARTIST_MEDIUM}}
 * - MAILERLITE_FIELD_ARTIST_PORTFOLIO={{MAILERLITE_FIELD_ARTIST_PORTFOLIO}}
 */

if (!function_exists('qd_ml_config_value')) {
    function qd_ml_config_value(string $envName, string $placeholder = ''): string
    {
        $envValue = trim((string) getenv($envName));
        return $envValue !== '' ? $envValue : $placeholder;
    }
}

if (!function_exists('qd_ml_is_configured_value')) {
    function qd_ml_is_configured_value(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (strpos($trimmed, '{{') !== false || strpos($trimmed, '}}') !== false) {
            return false;
        }

        if (stripos($trimmed, 'YOUR_') !== false || stripos($trimmed, 'CHANGE_ME') !== false) {
            return false;
        }

        return true;
    }
}

if (!function_exists('qd_ml_add_field')) {
    function qd_ml_add_field(array &$fields, string $envName, string $placeholder, ?string $value): void
    {
        $fieldValue = trim((string) $value);
        if ($fieldValue === '') {
            return;
        }

        $fieldKey = qd_ml_config_value($envName, $placeholder);
        if (!qd_ml_is_configured_value($fieldKey)) {
            return;
        }

        $fields[$fieldKey] = $fieldValue;
    }
}

if (!function_exists('qd_ml_subscribe')) {
    /**
     * @param array{
     *   email:string,
     *   name?:string,
     *   group_id?:string,
     *   fields?:array<string,string>
     * } $data
     * @return array{success:bool,reason:string,http_code?:int}
     */
    function qd_ml_subscribe(array $data): array
    {
        $apiToken = qd_ml_config_value('MAILERLITE_API_TOKEN', '{{MAILERLITE_API_TOKEN}}');
        if (!qd_ml_is_configured_value($apiToken)) {
            return ['success' => false, 'reason' => 'not_configured'];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'reason' => 'curl_unavailable'];
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            return ['success' => false, 'reason' => 'missing_email'];
        }

        $apiBase = rtrim(qd_ml_config_value('MAILERLITE_API_BASE_URL', 'https://connect.mailerlite.com/api'), '/');

        $name = trim((string) ($data['name'] ?? ''));
        $rawFields = $data['fields'] ?? [];
        if (!is_array($rawFields)) {
            $rawFields = [];
        }

        $fields = [];
        foreach ($rawFields as $key => $value) {
            $fieldKey = trim((string) $key);
            if ($fieldKey === '') {
                continue;
            }
            $fields[$fieldKey] = trim((string) $value);
        }

        if ($name !== '' && !isset($fields['name'])) {
            $fields['name'] = $name;
        }

        $payload = [
            'email'  => $email,
            'fields' => $fields,
            'status' => 'active',
        ];

        $groupId = trim((string) ($data['group_id'] ?? ''));
        if (qd_ml_is_configured_value($groupId)) {
            $payload['groups'] = [$groupId];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonPayload)) {
            return ['success' => false, 'reason' => 'payload_encoding_failed'];
        }

        $curl = curl_init($apiBase . '/subscribers');
        if ($curl === false) {
            return ['success' => false, 'reason' => 'curl_init_failed'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => $jsonPayload,
        ]);

        $responseBody = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError !== '') {
            return ['success' => false, 'reason' => 'curl_error'];
        }

        if ($responseBody === false) {
            return ['success' => false, 'reason' => 'empty_response'];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'reason' => 'ok', 'http_code' => $httpCode];
        }

        return ['success' => false, 'reason' => 'http_error', 'http_code' => $httpCode];
    }
}

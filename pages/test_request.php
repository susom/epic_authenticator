<?php
/**
 * Epic SmartData Element (SDE) test script using Interconnect "SETSMARTDATAVALUES" endpoint.
 *
 * This uses the Epic access token returned by $module->getEpicAccessToken() and performs:
 *   PUT {INTERCONNECT_BASE}/interconnect-.../api/epic/2013/Clinical/Utility/SETSMARTDATAVALUES/SmartData/Values
 *
 * Example request from user:
 *   PUT https://vendorservices.epic.com/interconnect-amcurprd-oauth/api/epic/2013/Clinical/Utility/SETSMARTDATAVALUES/SmartData/Values
 *   Authorization: Bearer TOKEN
 *   {"ContextName":"PATIENT","EntityID":"123123123",...}
 *
 * How to run (query params):
 *   /test_request.php?entity_id=123123123&smartdata_id=REDCAP%23002&value=TEST
 *
 * Required environment variables (recommended):
 *   EPIC_SMARTDATA_URL   Full URL to SETSMARTDATAVALUES endpoint
 *                        e.g. https://vendorservices.epic.com/interconnect-.../api/epic/2013/Clinical/Utility/SETSMARTDATAVALUES/SmartData/Values
 *
 * Optional environment variables:
 *   EPIC_ENTITY_ID_TYPE      Default: Internal
 *   EPIC_USER_ID             Default: 1
 *   EPIC_USER_ID_TYPE        Default: External
 *   EPIC_SOURCE              Default: Web Service
 *   EPIC_CONTEXT_NAME        Default: PATIENT
 *   EPIC_CONTACT_ID          Default: "" (empty)
 *   EPIC_CONTACT_ID_TYPE     Default: DAT
 *   EPIC_SMARTDATA_ID_TYPE   Default: SDI
 */

/** @var \Stanford\EpicAuthenticator\EpicAuthenticator $module */

define('JSON', 'application/json');

function respondJson($payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: ' . JSON);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function httpRequest(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 45,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw ?? '', true);

    return [
        'http_code'  => $code,
        'curl_error' => $err ?: null,
        'raw'        => $raw,
        'json'       => $json,
        'is_json'    => (json_last_error() === JSON_ERROR_NONE),
    ];
}

try {
    // -------------------------
    // 1) Get access token from EM
    // -------------------------
    $token = $module->getEpicAccessToken();

    // Allow either a raw string token or an array return (depending on your implementation)
    $accessToken = is_array($token)
        ? ($token['access_token'] ?? $token['token'] ?? null)
        : $token;

    if (!$accessToken || !is_string($accessToken)) {
        respondJson([
            'error' => 'getEpicAccessToken() did not return an access token string.',
            'token_return' => $token,
        ], 500);
    }

    // -------------------------
    // 2) Inputs
    // -------------------------
    // NOTE: In your example, EntityID is the patient identifier used by this endpoint.
    // Whether this is MRN vs internal patient ID is Epic-build dependent.
    // If you truly mean MRN, confirm with Epic what EntityIDType/system should be.
    $entityId    = trim((string)($_GET['entity_id'] ?? '48317895'));
    $entityIdType = (string)($_GET['entity_id_type'] ?? (getenv('EPIC_ENTITY_ID_TYPE') ?: 'Internal'));

    // Your target SDE
    $smartDataId = trim((string)($_GET['smartdata_id'] ?? 'REDCAP#002'));
    $value       = (string)($_GET['value'] ?? 'TEST');

    // -------------------------
    // 3) Endpoint URL
    // -------------------------
    $smartDataUrl = 'https://epicproxy-np.et0857.epichosted.com/FHIRProxy/api/epic/2013/Clinical/Utility/SETSMARTDATAVALUES/SmartData/Values';
    if ($smartDataUrl === '') {
        respondJson([
            'error' => 'Missing EPIC_SMARTDATA_URL env var (full SETSMARTDATAVALUES endpoint URL).',
            'expected_example' => 'https://vendorservices.epic.com/interconnect-.../api/epic/2013/Clinical/Utility/SETSMARTDATAVALUES/SmartData/Values',
        ], 400);
    }

    // -------------------------
    // 4) Build payload (matches your sample)
    // -------------------------
    $payload = [
        'ContextName'   => getenv('EPIC_CONTEXT_NAME') ?: 'PATIENT',
        'EntityID'      => $entityId,
        'EntityIDType'  => 'Identity identifiers',
        'ContactID'     => getenv('EPIC_CONTACT_ID') ?: '',
        'ContactIDType' => getenv('EPIC_CONTACT_ID_TYPE') ?: 'DAT',
        'UserID'        => getenv('EPIC_USER_ID') ?: '1',
        'UserIDType'    => getenv('EPIC_USER_ID_TYPE') ?: 'External',
        'Source'        => getenv('EPIC_SOURCE') ?: 'Web Service',
        'SmartDataValues' => [[
            'SmartDataID'     => $smartDataId,
            'SmartDataIDType' => getenv('EPIC_SMARTDATA_ID_TYPE') ?: 'SDI',
            'Values'          => [$value],
            'Comments'        => [],
        ]],
    ];

    // -------------------------
    // 5) PUT request to Epic
    // -------------------------
    $resp = httpRequest('PUT', $smartDataUrl, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: ' . JSON,
        'Accept: ' . JSON,
    ], json_encode($payload, JSON_UNESCAPED_SLASHES));

    // -------------------------
    // 6) Return debug-friendly output
    // -------------------------
    respondJson([
        'request' => [
            'method' => 'PUT',
            'url' => $smartDataUrl,
            'headers' => [
                'Authorization' => 'Bearer <redacted>',
                'Content-Type' => JSON,
                'Accept' => JSON,
            ],
            'body' => $payload,
        ],
        'response' => [
            'http_code' => $resp['http_code'],
            'curl_error' => $resp['curl_error'],
            'json' => $resp['json'],
            'raw' => $resp['is_json'] ? null : $resp['raw'],
        ],
        'notes' => [
            'If Epic returns an authorization error, confirm the token scopes/permissions for SmartData filing.',
            'If Epic returns patient-not-found, confirm what EntityIDType expects (Internal vs MRN vs CSN, etc.).',
            'If Epic returns SmartDataID errors, confirm the SmartDataIDType (SDI) and the exact SmartDataID (e.g., REDCAP#002 vs EPIC#...).'
        ],
    ], $resp['http_code'] >= 400 ? $resp['http_code'] : 200);

} catch (Exception $e) {
    respondJson([
        'error' => $e->getMessage(),
        'type'  => get_class($e),
    ], 500);
}

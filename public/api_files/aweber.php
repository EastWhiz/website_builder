<?php
// CONFIGURATION: Replace with your AWeber API credentials
$clientId = "";
$clientSecret = "";
$accountId = "";  // Your AWeber Account ID
$listId = "";  // Your AWeber List ID
$tokenFile = "tokens.json"; // JSON file to store tokens

// Function to load tokens from JSON file
function loadTokens()
{
    global $tokenFile;
    if (!file_exists($tokenFile)) return null;
    return json_decode(file_get_contents($tokenFile), true);
}
// Function to save tokens to JSON file
function saveTokens($newTokens)
{
    global $tokenFile;

    // Load existing tokens
    $existingTokens = file_exists($tokenFile) ? json_decode(file_get_contents($tokenFile), true) : [];

    // Update only the access and refresh tokens
    $existingTokens['access_token'] = $newTokens['access_token'];
    $existingTokens['refresh_token'] = $newTokens['refresh_token'];

    // Save updated tokens back to the file
    file_put_contents($tokenFile, json_encode($existingTokens, JSON_PRETTY_PRINT));
}

// Function to refresh access token if expired
function refreshAccessToken($clientId, $clientSecret, $refreshToken)
{
    $url = "https://auth.aweber.com/oauth2/token";

    $data = [
        "grant_type" => "refresh_token",
        "client_id" => $clientId,
        "client_secret" => $clientSecret,
        "refresh_token" => $refreshToken
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (!$result || !isset($result["access_token"])) {
        die(json_encode(["error" => "Error refreshing token", "details" => $result]));
    }

    // Save new tokens
    saveTokens([
        "access_token" => $result["access_token"],
        "refresh_token" => $result["refresh_token"]
    ]);

    return $result["access_token"];
}

// Get valid access token
$tokens = loadTokens();
if (!$tokens) {
    die(json_encode(["error" => "No tokens found! Please authenticate first."]));
}

$accessToken = $tokens["access_token"];
$refreshToken = $tokens["refresh_token"];

// Function to check if access token is expired
function isTokenExpired($accessToken)
{
    $url = "https://api.aweber.com/1.0/accounts"; // Test API request

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    // Token is expired only if 401 AND a valid JSON response
    return ($httpCode === 401 && isset($decodedResponse['error']));
}

// Check if token is expired and refresh if needed
if (isTokenExpired($accessToken)) {
    $accessToken = refreshAccessToken($clientId, $clientSecret, $refreshToken);
}

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true);

if (!$postData || !isset($postData['email'])) {
    die(json_encode(["error" => "Invalid request data"]));
}

// Prepare Subscriber Data
$subscriberData = [
    'email' => $postData['email'],
    'name' => ($postData['firstname'] ?? '') . ' ' . ($postData['lastname'] ?? ''),
    'custom_fields' => [
        'First Name' => $postData['firstname'] ?? '',
        'Last Name' => $postData['lastname'] ?? '',
        'Phone Number' => $postData['phone'] ?? '',
        'Country' => $postData['country'] ?? '',
        'Address' => $postData['address'] ?? '',
        'Zip code' => $postData['zipcode'] ?? '',
        'Date of birth' => $postData['dob'] ?? '',
    ]
];

$listIdsToUse = [];

$formListIdsRaw = trim((string) ($postData['aweber_list_ids'] ?? ''));
if ($formListIdsRaw !== '') {
    foreach (explode(',', $formListIdsRaw) as $part) {
        $id = trim($part);
        if ($id !== '') {
            $listIdsToUse[] = $id;
        }
    }
}

if (count($listIdsToUse) === 0 && trim((string) $listId) !== '') {
    foreach (explode(',', (string) $listId) as $part) {
        $id = trim($part);
        if ($id !== '') {
            $listIdsToUse[] = $id;
        }
    }
}

if (count($listIdsToUse) === 0) {
    $countryCode = strtoupper(trim($postData['country'] ?? 'DE'));
    if ($countryCode === 'DK') {
        $resolved = '6862276';
    } elseif ($countryCode === 'NL') {
        $resolved = '6862594';
    } elseif ($countryCode === 'DE') {
        $resolved = '6858774';
    } elseif ($countryCode === 'IT') {
        $resolved = '6862281';
    } elseif ($countryCode === 'PL') {
        $resolved = '6862280';
    } elseif ($countryCode === 'PT') {
        $resolved = '6862592';
    } else {
        $resolved = '6858148';
    }
    $listIdsToUse = [$resolved];
}

$perListResults = [];
$allOk = true;

foreach ($listIdsToUse as $oneListId) {
    $url = "https://api.aweber.com/1.0/accounts/$accountId/lists/$oneListId/subscribers";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subscriberData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    $perListResults[] = [
        'list_id' => $oneListId,
        'http_code' => $httpCode,
        'response' => $result,
    ];
    if ($httpCode !== 201) {
        $allOk = false;
    }
}

if ($allOk) {
    echo json_encode([
        'success' => true,
        'status' => true,
        'message' => 'Subscriber added successfully',
        'lists' => $perListResults,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => false,
        'error' => 'Failed to add subscriber to one or more lists',
        'details' => $perListResults,
    ]);
}

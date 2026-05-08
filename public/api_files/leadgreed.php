<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php';
include_once __DIR__ . '/aweber_send_helper.php';

header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getVal($arr, $key)
{
    return isset($arr[$key]) ? $arr[$key] : '';
}

function leadgreedUserFacingApiError($httpCode, $apiErrorMessage, $curlError)
{
    if ($curlError !== '') {
        return $curlError;
    }

    return ($apiErrorMessage !== '') ? $apiErrorMessage : 'API error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    $getData = $_GET;
    $saveLeadSlug = trim((string) ($postData['save_lead_slug'] ?? ''));
    $formTypeForSpam = trim((string) ($postData['form_type'] ?? ''));
    $honeypotApiName = $saveLeadSlug !== '' ? $saveLeadSlug : ($formTypeForSpam !== '' ? $formTypeForSpam : 'unknown');
    
    maybeBlockFakeLeadAndExit($postData, $getData, $honeypotApiName);
    maybeBlockDuplicateLeadAndExit($postData, $getData, $honeypotApiName);
    $dynamicCid = getVal($getData, 'cid') ?? '';
    $dynamicPid = getVal($getData, 'pid') ?? '';
    $dynamicSO = getVal($getData, 'so') ?? '';
    if ($dynamicCid === '' && trim(getVal($postData, 'cid')) !== '') {
        $dynamicCid = trim(getVal($postData, 'cid'));
    }
    if ($dynamicPid === '' && trim(getVal($postData, 'pid')) !== '') {
        $dynamicPid = trim(getVal($postData, 'pid'));
    }
    if ($dynamicSO === '' && trim(getVal($postData, 'so')) !== '') {
        $dynamicSO = trim(getVal($postData, 'so'));
    }


    //areacode in variable -- if + is not require, them remove here
    
    $formType = trim(getVal($postData, 'form_type'));
    $saveLeadSlug = trim(getVal($postData, 'save_lead_slug'));
    $slug = $saveLeadSlug !== '' ? $saveLeadSlug : $formType;
    if ($slug === '') {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('form_type or save_lead_slug is required'));
        exit();
    }

    $endpoint = "";
    $apiKey = "";
    if (empty($endpoint)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('API endpoint not configured'));
        exit();
    }

    // Payload: same shape as public/api_files/electra.php â†’ lcaapi + fields from electra-version-integration.php
    // (firstname/lastname/email/phone/country/area_code/userip/cid/so/pid). RiceLeads/LeadGreed use Rice password + affid from export.
    // - phone: national vs E164 depends on the form (reference uses national; angle template hidden field uses intl-tel full number).
    // - pid â†’ aff_sub3 (common subid slot); cid/so already map to aff_sub / aff_sub5 / funnel.
    $countryRaw = trim(getVal($postData, 'country'));
    $country = '';
    if ($countryRaw !== '') {
        $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $countryRaw), 0, 2));
    }

    $data = [
        'affid' => '36',
        'first_name' => getVal($postData, 'firstname'),
        'last_name' => getVal($postData, 'lastname'),
        'email' => getVal($postData, 'email'),
        'password' => 'Qbwriu48',
        'phone' => getVal($postData, 'phone'),
        'source' => BASE_URL,
        '_ip' => getVal($postData, 'userip'),
        'area_code' => getVal($postData, 'area_code'),
        'country' => $country,
        'funnel' => $dynamicSO,
        'aff_sub' => $dynamicCid,
        'aff_sub2' => 'aff_sub2',
        'aff_sub3' => $dynamicPid,
        'aff_sub5' => $dynamicCid,
    ];

    $lang = trim(getVal($postData, 'lang'));
    if ($lang !== '') {
        $data['lang'] = $lang;
    }

    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true");
    if ($isSelfHosted) {
        $responseArray = ['status' => true, 'message' => 'Lead processed successfully (self-hosted)', 'is_self_hosted' => true];
        saveLead($postData, $getData, $responseArray, $slug, 'success', $data);
        sendToAweberIfEnabled($postData);
        $thankYouParams = [
            'cid' => $dynamicCid,
            'pid' => $dynamicPid,
            'so' => $dynamicSO,
        ];
        $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
        $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
        if ($redirectDelay < 0) {
            $redirectDelay = 0;
        }
        $brokerUrl = trim(getVal($postData, 'broker_url'));
        if ($brokerUrl !== '' && !filter_var($brokerUrl, FILTER_VALIDATE_URL)) {
            $brokerUrl = '';
        }
        if ($redirectToBroker && $brokerUrl !== '') {
            $thankYouParams['redirect_to_broker'] = 'yes';
            $thankYouParams['broker_redirect_delay'] = (string) $redirectDelay;
            $thankYouParams['broker_url'] = $brokerUrl;
        }
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query($thankYouParams));
        exit();
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = $response ? json_decode($response, true) : null;
    $responseArray = [];
    if (is_array($decoded)) {
        $responseArray = $decoded;
    } elseif (is_string($response) && trim($response) !== '') {
        // If API returns plain text / non-JSON, surface it as message.
        $responseArray = ['message' => trim($response)];
    }
    $leadSaveStatus = ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failure';
    saveLead($postData, $getData, $responseArray, $slug, $leadSaveStatus, $data);

    sendToAweberIfEnabled($postData);

    // Configure optional broker redirect for Thank You page
    $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
    $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
    if ($redirectDelay < 0) {
        $redirectDelay = 0;
    }
    $brokerUrl = null;
    if (is_array($responseArray)) {
        $brokerUrl = findBrokerRedirectUrl($responseArray);
    }
    if (($brokerUrl === null || $brokerUrl === '') && trim(getVal($postData, 'broker_url')) !== '') {
        $fromPost = trim(getVal($postData, 'broker_url'));
        if (filter_var($fromPost, FILTER_VALIDATE_URL)) {
            $brokerUrl = $fromPost;
        }
    }

    $apiErrorMessage = extractApiErrorMessage($responseArray);

    if ($curlError || $leadSaveStatus === 'failure') {
        $userMsg = leadgreedUserFacingApiError($httpCode, $apiErrorMessage, $curlError);
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($userMsg));
        exit();
    }

    $thankYouParams = [
        'cid' => $dynamicCid,
        'pid' => $dynamicPid,
        'so' => $dynamicSO,
    ];
    if ($redirectToBroker && $brokerUrl && filter_var($brokerUrl, FILTER_VALIDATE_URL)) {
        $thankYouParams['redirect_to_broker'] = 'yes';
        $thankYouParams['broker_redirect_delay'] = (string) $redirectDelay;
        $thankYouParams['broker_url'] = $brokerUrl;
    }

   

    header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query($thankYouParams));
    exit();
}

$getData = $_GET ?? [];
header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
exit();



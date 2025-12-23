<?php

/** @var \Stanford\EpicAuthenticator\EpicAuthenticator $module */

use Stanford\EpicAuthenticator\GoogleSecretManager;

try{
    if (!defined('SUPER_USER') OR !SUPER_USER) {
        throw new Exception('Access denied. Super users only.');
    }

    $publicKeySecretName = $_GET['public_key_secret_name'];
    if (empty($publicKeySecretName)) {
        throw new Exception('Missing required parameter: public_key_secret_name');
    }
    $googleProjectId = htmlspecialchars($_GET['google_project_id']);

    if(empty($googleProjectId)) {
        throw new Exception('Missing required parameter: google_project_id');
    }
    // Inject our custom Secret Manager
    $module->setSecretManager(new GoogleSecretManager($googleProjectId));
    header('Content-Type: application/json');

    print_r($module->getEpicPublicJwks($publicKeySecretName));
}catch (Exception $e){
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

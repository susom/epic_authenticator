<?php

/** @var \Stanford\EpicAuthenticator\EpicAuthenticator $module */

try{
    $accessToken = $module->getEpicAccessToken();
    echo $accessToken;
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

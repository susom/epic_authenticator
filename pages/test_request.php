<?php
/**
 * Test script to request an Epic OAuth2 token using private_key_jwt
 */
/** @var \Stanford\EpicAuthenticator\EpicAuthenticator $module */
try{
    echo '<pre>';
    print_r($module->getEpicAccessToken());
    echo '</pre>';
}catch (Exception $e){
    echo "Error: " . $e->getMessage();
}

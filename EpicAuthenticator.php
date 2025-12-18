<?php
namespace Stanford\EpicAuthenticator;

use Firebase\JWT\JWT;

require_once ('vendor/autoload.php');

require_once ('classes/GoogleSecretManager.php');

class EpicAuthenticator extends \ExternalModules\AbstractExternalModule {

    const EPIC_CLIENT_ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
    private $secretManager;
    public function __construct() {
        parent::__construct();
        // Other code to run when object is instantiated
    }

    private function getSecretManager(): GoogleSecretManager
    {
        if (!$this->secretManager) {
            $this->secretManager = new GoogleSecretManager(
                $this->getProjectSetting('google-project-id'));
        }
        return $this->secretManager;
    }

    public function setSecretManager(GoogleSecretManager $googleSecretManager): GoogleSecretManager
    {
        if (!$this->secretManager) {
            $this->secretManager = $googleSecretManager;
        }
        return $this->secretManager;
    }

    /**
     * Get an OAuth2 access token from Epic using JWT client assertion.
     *
     * @return string
     * @throws \Exception
     */
    public function getEpicAccessToken(): string
    {
        $clientId = $this->getProjectSetting('epic-client-id');
        $jwksUrl  = $this->getProjectSetting('public-jwks-url');
        $tokenUrl = rtrim($this->getProjectSetting('epic-base-url'), '/') . '/oauth2/token';

        if (empty($clientId) || empty($jwksUrl) || empty($tokenUrl)) {
            throw new \Exception('Missing required Epic OAuth configuration (client id, JWKS URL, or token URL).');
        }

        // Get private key from Google Secret Manager
        $privateKey = $this->getSecretManager()->getSecret($this->getProjectSetting('epic-private-key-secret-name'));
        if (empty($privateKey)) {
            throw new \Exception('Epic private key could not be loaded from Secret Manager.');
        }

        // Fetch JWKS and extract kid
        $jwksJson = @file_get_contents($jwksUrl);
        if ($jwksJson === false) {
            throw new \Exception('Unable to fetch JWKS from URL: ' . $jwksUrl);
        }

        $jwks = json_decode($jwksJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($jwks['keys'][0]['kid'])) {
            throw new \Exception('Invalid JWKS JSON or missing kid in JWKS.');
        }
        $kid = $jwks['keys'][0]['kid'];

        // Build client assertion JWT
        $clientAssertion = $this->buildClientAssertionJwt($clientId, $tokenUrl, $privateKey, $kid);

        // Build token request
        $postFields = http_build_query([
            'grant_type'             => 'client_credentials',
            'client_id'              => $clientId,
            'client_assertion_type'  => self::EPIC_CLIENT_ASSERTION_TYPE,
            'client_assertion'       => $clientAssertion,
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error calling Epic token endpoint: ' . $err);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \Exception('Epic token endpoint returned HTTP ' . $statusCode . ': ' . $responseBody);
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['access_token'])) {
            throw new \Exception('Epic token response is invalid or missing access_token.');
        }

        return $data['access_token'];
    }

    /**
     * Build a JWT for client assertion using RS256.
     *
     * @param string $clientId
     * @param string $audience token URL
     * @param string $privateKey PEM-encoded RSA private key
     * @param string $kid Key ID from JWKS
     * @return string
     * @throws \Exception
     */
    private function buildClientAssertionJwt(string $clientId, string $audience, string $privateKey, string $kid): string
    {
        $now = time();

        $claims = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $audience,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + 300, // 5 minutes
        ];

        $header = [
            'kid' => $kid,
            'typ' => 'JWT',
        ];

        try {
            return JWT::encode($claims, $privateKey, 'RS256', null, $header);
        } catch (\Throwable $e) {
            throw new \Exception('Failed to build client assertion JWT: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * URL-safe base64 encoding.
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Generate a JWKS JSON document from the Epic public key stored in Secret Manager.
     *
     * The public key is expected to be an RSA key in PEM format, stored under the
     * Google Secret name defined by EPIC_PUBLIC_KEY. The resulting JWKS will
     * contain a single RSA key with kty, kid, use, alg, n, and e fields.
     *
     * @return string JWKS JSON
     * @throws \Exception
     */
    public function getEpicPublicJwks(): string
    {
        // Load public key from Google Secret Manager
        $publicKey = $this->getSecretManager()->getSecret($this->getProjectSetting('epic-public-key-secret-name'));
        if (empty($publicKey)) {
            throw new \Exception('Epic public key could not be loaded from Secret Manager.');
        }

        // Extract RSA key details (modulus n and exponent e)
        $res = @openssl_pkey_get_public($publicKey);
        if ($res === false) {
            throw new \Exception('Failed to parse Epic public key as an RSA public key.');
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new \Exception('Unable to extract RSA details (n, e) from Epic public key.');
        }

        // Modulus (n) and exponent (e) in binary, then base64url encode
        $n = $this->base64UrlEncode($details['rsa']['n']);
        $e = $this->base64UrlEncode($details['rsa']['e']);

        // Generate kid the same way as the legacy script (SHA1 of full key, first 16 chars)
        $kid = substr(sha1($details['key']), 0, 16);

        $jwk = [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS384', // match legacy script default
            'n'   => $n,
            'e'   => $e,
        ];

        $jwks = [
            'keys' => [$jwk],
        ];

        $json = json_encode($jwks, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to encode JWKS JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}

# Epic Authenticator (REDCap External Module)

Epic Authenticator is a REDCap External Module (EM) that centralizes and secures authentication with Epic using OAuth 2.0 private_key_jwt. It provides two main features:

1. Generate a JWKS (JSON Web Key Set) from a public key so Epic can verify the signature of OAuth requests.
2. Generate Epic access tokens so project code can call Epic APIs.

This README explains what the module does, how to configure it, and how to use both features.

---

## Table of contents

- Overview
- Prerequisites
- Feature A — Generate JWKS (for Epic)
    - How it works
    - Steps to generate and publish JWKS
    - Example JWKS response
- Feature B — Generate Epic access token
    - Required EM settings (project-level)
    - How to call the EM from your code
- Key rotation and security considerations
- Troubleshooting

---

## Overview

Epic requires a JWK set (JWKS) published at a public URL so it can verify the JWT signature presented in private_key_jwt OAuth flows. This EM helps you:

- Create a JWKS from your public key and host/save it at a public URL that Epic can fetch.
- Produce OAuth access tokens for your REDCap project to access Epic APIs, using the stored private key.

---

## Prerequisites

- A REDCap instance with External Modules enabled.
- A Google Cloud project (or equivalent secret storage) where you will store keys as secrets that REDCap can access.
- Epic-provided values: client ID (Orchard App client id) and token endpoint (auth URL).
- Network access / a public URL where Epic can fetch your JWKS.

---

## Feature A — Generate JWKS (for Epic)

### How it works (short)
1. The EM can take a public key and produce a JWKS JSON document containing a single RSA key entry.
2. You must expose that JWKS at a publicly accessible URL so the Epic team can fetch it and whitelist it in their systems.
3. Storing the public key and publishing the JWKS makes future key rotation easier — you can update the JWKS at the same public URL and Epic will fetch the updated keys.

### Steps to generate and publish JWKS
1. Save your public key in Google Secret Manager using the secret name `epic-public-key` (or another secret system your REDCap can access).
2. In REDCap Control Center, go to the page labeled "Generate Epic JWKS".
3. On that page, copy the generated link and append the Google project id parameter where the public key secret is stored. Example:
    - ...&google_project_id=ABD123
      (Replace `ABD123` with your Google Project ID.)
4. Navigate to that generated link. The EM will return a JWKS document (JSON) which you must save to a public URL reachable by Epic (for example, a file hosted in a repo or a static file host).
5. Share the public JWKS URL with the Epic team so they can whitelist it.

Example: if you saved your public key secret in project `ABD123`, call the "Generate Epic JWKS" endpoint with `&google_project_id=ABD123`. The EM will return the JWKS JSON below.

### Example JWKS response
```json
{
    "keys": [
        {
            "kty": "RSA",
            "kid": "1234654sdfgsdfgsdf",
            "use": "sig",
            "alg": "RS384",
            "n": "sdfsdfgdfgdfghdfghasfgsdgse6r8g4sdef6v84serv68se7r4va6wer9f8v47ser6v897s4efrv6sde897v4bse6df89vb74sdf6b897d4fgb86dfg7b4dfg68b74dfg6b8794dfgb86dfg74bdfg867b4dfg68b74dfgb68d7fg4bd856fg7b4",
            "e": "AQAB"
        }
    ]
}
```

Save the produced file (the JWKS JSON) to a public HTTPS URL where Epic can fetch it (for example, a public GitHub raw URL, a public storage bucket, or a static hosting endpoint). Hosting the JWKS at a stable public URL lets you rotate keys by updating the JWKS file while keeping the same URL, without requiring Epic to change their configuration.

---

## Feature B — Generate Epic access token

This EM also returns Epic access tokens (OAuth 2.0) to REDCap project code by using the private key (stored securely) and communicating with Epic's token endpoint.

### Where to enable the EM
Enable this External Module for the REDCap project that needs to pull or push data to Epic.

### Required Project EM Settings
In the project-level EM settings, provide these values:

1. Google Project ID (setting key often labeled `google-project-id` in the EM)
    - The GCP project that contains the private key secret. It's your responsibility to ensure REDCap has privileges to read secrets from this project.
2. Epic auth URL
    - Example: `https://EPIC-AUTH-URL/oauth2/token`
    - This is Epic's token endpoint for your environment.
3. Epic Orchard App client id
    - The client ID provided by Epic for your Orchard application.
4. Public JWKS URL
    - The public URL where you published the JWKS generated in Feature A. Epic will use this URL to fetch your public keys to verify client assertions.

Note: The EM expects the private key to be stored in a secret in the Google project specified above (ensure the REDCap server/service account can access that secret). Follow your institution's policies for storing and granting access to secrets.

### How to obtain an access token (usage)
Once the project-level settings are configured and saved, your project code can call the module to obtain an Epic access token. Example usage in REDCap PHP code:

```php
$authenticator = \ExternalModules\ExternalModules::getModuleInstance('epic-authenticator-EM-prefix');
$accessToken = $authenticator->getEpicAccessToken();
// $accessToken should contain the token data returned by Epic (check the EM's method docs for exact response shape)
```

Replace `'epic-authenticator-EM-prefix'` with the actual folder name / prefix of this EM installation.

---

## Key rotation and security considerations

- Store private keys in a secure secrets manager (e.g., Google Secret Manager). Do not store private keys as plaintext in the repo or project files.
- Publish only the JWKS (public key material) to the public URL shared with Epic.
- When rotating keys:
    - Add the new public key entry to your JWKS and keep the JWKS URL the same so Epic can fetch it.
    - Coordinate timing: ensure Epic has fetched or been notified of the new key if they require notice.
- Ensure REDCap's service account or runtime environment has permission to read the secrets you configure.

---

## Troubleshooting

- If the JWKS generator returns an error, verify:
    - The Google project ID is correct.
    - The secret name `epic-public-key` exists in that project.
    - REDCap can access Google Secret Manager (proper IAM permissions and network connectivity).
- If token requests fail:
    - Verify the Epic auth URL and client ID are correct.
    - Ensure the private key is present and correctly formatted in the secrets store.
    - Check logs for returned error bodies from Epic — they often include an OAuth error code and message.

---

If you need any edits to this README (add screenshots, CLI commands, or exact secret names used by the EM), tell me which details you want included and I will update it. I have rewritten and organized the README into clear sections and added examples and steps — next you can review and commit this file to the repository.

# Epic Authenticator (REDCap External Module)

Epic Authenticator is a REDCap External Module (EM) that centralizes and secures authentication with Epic using the OAuth 2.0 **private_key_jwt** flow. The module provides two core capabilities:

1. Generate a JWKS (JSON Web Key Set) from a public key so Epic can verify JWT signatures.
2. Generate Epic OAuth access tokens so REDCap project code can securely call Epic APIs.

This README explains what the module does, how to configure it, and how to use both features.

---

## Table of Contents

- Overview
- Prerequisites
- Feature A — Generate JWKS (for Epic)
  - How it works
  - Steps to generate and publish JWKS
  - Example JWKS response
- Feature B — Generate Epic Access Token
  - Required EM settings (project-level)
  - How to call the EM from your code
- Key Rotation and Security Considerations
- Troubleshooting

---

## Overview

Epic requires a JSON Web Key Set (JWKS) to be published at a publicly accessible URL so it can verify the JWT signatures used in **private_key_jwt** OAuth authentication flows. This External Module helps you:

- Generate a JWKS from a stored public key.
- Publish that JWKS at a stable, public URL that Epic can fetch and whitelist.
- Generate OAuth access tokens for Epic APIs using a securely stored private key.

By separating key storage, JWKS generation, and token issuance, the module simplifies key rotation and improves security.

---

## Prerequisites

- A REDCap instance with External Modules enabled.
- A Google Cloud project (or equivalent secrets manager) used to store cryptographic keys.
- Epic-provided credentials:
  - Orchard App client ID
  - OAuth token endpoint (auth URL)
- A publicly accessible HTTPS URL where Epic can fetch the JWKS.

---

## Feature A — Generate JWKS (for Epic)

### How it works

1. The module reads a public RSA key from a secure secret store.
2. It converts the public key into a JWKS JSON document containing a single RSA key entry.
3. You publish the generated JWKS at a public URL so Epic can retrieve and whitelist it.
4. When keys are rotated, the JWKS can be updated at the same URL without requiring Epic to change their configuration.

### Steps to Generate and Publish JWKS

1. Store your **public key** in Google Secret Manager. Note the secret name.
2. In the REDCap Control Center, copy the link for the page labeled **“Generate Epic JWKS.”**
3. Append the public key secret name to the URL:

   ```
   &public_key_secret_name=YOUR_GOOGLE_PUBLIC_KEY_SECRET_NAME
   ```

   Replace `YOUR_GOOGLE_PUBLIC_KEY_SECRET_NAME` with the actual secret name.

4. Append the Google project ID that contains the secret:

   ```
   &google_project_id=YOUR_GOOGLE_PROJECT_ID
   ```

5. Navigate to the fully constructed URL. The module will return a JWKS JSON document.
6. Save the JWKS JSON to a **public HTTPS URL** reachable by Epic (for example, a public GitHub raw file, a static site, or a public storage bucket).
7. Share the public JWKS URL with the Epic team so they can whitelist it.

### Example JWKS Response

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

Host this JWKS file at a stable public URL. Keeping the URL constant allows you to rotate keys by updating the JWKS content without coordinating URL changes with Epic.

---

## Feature B — Generate Epic Access Token

In addition to JWKS generation, this module can request OAuth 2.0 access tokens from Epic on behalf of a REDCap project. The module uses the private key (stored securely) to authenticate with Epic’s token endpoint.

### Where to Enable the Module

Enable this External Module at the **project level** for any REDCap project that needs to read from or write to Epic.

### Required Project-Level EM Settings

Configure the following settings in the project’s External Module configuration:

1. **Google Project ID**
   The Google Cloud project that contains the private key secret. Ensure REDCap has permission to access secrets in this project.

2. **Private Key Secret Name**
   The name of the secret in Google Secret Manager that contains the private RSA key used for signing JWTs.

3. **Epic Base URL**
   The Epic Base token endpoint, for example:
   ```
   https://EPIC-AUTH-URL/
   ```

4. **Epic Orchard App Client ID**
   The client ID provided by Epic for your Orchard application.

5. **Public JWKS URL**
   The public URL where you published the JWKS generated in Feature A. Epic uses this URL to verify client assertions.

The private key must be stored securely in the configured secrets manager. Do not store private keys in source control or project files.

### How to Obtain an Access Token (Usage Example)

Once the project settings are saved, your REDCap project code can request an Epic access token:

```php
$authenticator = \ExternalModules\ExternalModules::getModuleInstance('epic-authenticator-EM-prefix');
$accessToken = $authenticator->getEpicAccessToken();
```

Replace `epic-authenticator-EM-prefix` with the actual directory name (prefix) of the module.

The returned value contains the token response from Epic. Refer to the module’s method documentation for the exact response structure.

---

## Key Rotation and Security Considerations

- Store private keys only in a secure secrets manager (for example, Google Secret Manager).
- Never publish private key material. Only the JWKS (public keys) should be publicly accessible.
- When rotating keys:
  - Add or replace the public key in the JWKS while keeping the same public URL.
  - Coordinate timing with Epic if advance notice is required.
- Ensure the REDCap runtime environment or service account has read access to the configured secrets.

---

## Troubleshooting

**JWKS Generation Issues**
- Verify the Google project ID is correct.
- Confirm the public key secret exists in the specified project.
- Ensure REDCap has permission to access Google Secret Manager.

**Access Token Failures**
- Confirm the Epic auth URL and client ID are correct.
- Verify the private key is present and correctly formatted in the secrets manager.
- Review REDCap logs for OAuth error responses returned by Epic.

---

If you would like this README to include screenshots, exact secret names, CLI commands, or environment-specific examples, let me know and I can extend it accordingly.

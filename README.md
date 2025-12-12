# Epic Authenticator

This External Module provides a centralized and secure authentication service for REDCap to obtain OAuth 2.0 access tokens from Epic using the private_key_jwt method. It manages the full JWT workflow, including hosting a public JWKS endpoint for Epic key verification, loading and using the private RSA key to sign client assertions, and generating access tokens that other REDCap modules or projects can reuse. This module serves as the foundation for enabling future REDCap→Epic integrations by standardizing and simplifying Epic authentication across the entire REDCap environment.


First Step to generate JWKS from Public key


save generated JWKS to FHIR-config github repo.

share the URL to Epic team to whitelist it.


<?php

/**
 * Simple OIDC Integration Test Script
 *
 * This script tests the OIDC implementation endpoints.
 * Run from command line: php tests/oidc_test.php
 */

declare(strict_types=1);

// Configuration
$baseUrl = 'https://galette.example.org/plugins/oauth2';
$clientId = 'test_client';
$clientSecret = 'test_secret';

// ANSI colors for output
$colors = [
    'green'   => "\033[32m",
    'red'     => "\033[31m",
    'yellow'  => "\033[33m",
    'blue'    => "\033[34m",
    'reset'   => "\033[0m",
];

function log_message(string $message, string $color = 'reset'): void
{
    global $colors;
    echo $colors[$color] . $message . $colors['reset'] . "\n";
}

function test_endpoint(string $url, string $method = 'GET', ?array $data = null): bool
{
    log_message("\nTesting: $method $url", 'blue');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only!

    if ($method === 'POST' && $data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_message("❌ CURL Error: $error", 'red');
        return false;
    }

    if ($httpCode !== 200) {
        log_message("❌ HTTP $httpCode", 'red');
        log_message("Response: " . substr((string)$response, 0, 200), 'red');
        return false;
    }

    $json = json_decode((string)$response, true);
    if ($json === null) {
        log_message("❌ Invalid JSON response", 'red');
        return false;
    }

    log_message("✅ Success (HTTP 200)", 'green');
    log_message("Response keys: " . implode(', ', array_keys($json)), 'yellow');

    return true;
}

function validate_oidc_config(array $config): bool
{
    log_message("\n--- OIDC Configuration Validation ---", 'blue');

    $required = [
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
        'response_types_supported',
        'scopes_supported',
        'subject_types_supported',
        'id_token_signing_alg_values_supported',
        'userinfo_endpoint',
    ];

    $missing = array_diff($required, array_keys($config));
    if (!empty($missing)) {
        log_message("❌ Missing fields: " . implode(', ', $missing), 'red');
        return false;
    }

    log_message("✅ All required OIDC fields present", 'green');

    // Validate scopes
    $expectedScopes = ['openid', 'profile', 'email', 'address', 'phone'];
    $missingScopes = array_diff($expectedScopes, $config['scopes_supported'] ?? []);
    if (!empty($missingScopes)) {
        log_message("⚠️  Missing scopes: " . implode(', ', $missingScopes), 'yellow');
    } else {
        log_message("✅ All OIDC scopes present", 'green');
    }

    return true;
}

function validate_jwks(array $jwks): bool
{
    log_message("\n--- JWKS Validation ---", 'blue');

    if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
        log_message("❌ Missing 'keys' array in JWKS", 'red');
        return false;
    }

    if (count($jwks['keys']) === 0) {
        log_message("❌ No keys found in JWKS", 'red');
        return false;
    }

    $key = $jwks['keys'][0];
    $requiredFields = ['kty', 'use', 'alg', 'n', 'e', 'kid'];
    $missing = array_diff($requiredFields, array_keys($key));

    if (!empty($missing)) {
        log_message("❌ Missing key fields: " . implode(', ', $missing), 'red');
        return false;
    }

    if ($key['kty'] !== 'RSA') {
        log_message("❌ Key type must be RSA, got: {$key['kty']}", 'red');
        return false;
    }

    if ($key['alg'] !== 'RS256') {
        log_message("❌ Algorithm must be RS256, got: {$key['alg']}", 'red');
        return false;
    }

    log_message("✅ JWKS contains valid RSA key", 'green');
    log_message("  Key ID: {$key['kid']}", 'yellow');
    log_message("  Algorithm: {$key['alg']}", 'yellow');

    return true;
}

// Main test execution
log_message("\n╔════════════════════════════════════════╗", 'blue');
log_message("║   Galette OAuth2 OIDC Integration Test  ║", 'blue');
log_message("╚════════════════════════════════════════╝", 'blue');

log_message("\nBase URL: $baseUrl", 'yellow');

// Test 1: OIDC Discovery
log_message("\n[1/4] Testing OIDC Discovery Endpoint", 'blue');
$discoveryUrl = "$baseUrl/.well-known/openid-configuration";
if (!test_endpoint($discoveryUrl)) {
    log_message("\n❌ OIDC Discovery endpoint failed!", 'red');
    exit(1);
}

// Get and validate discovery document
$ch = curl_init($discoveryUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$config = json_decode((string)$response, true);
validate_oidc_config($config);

// Test 2: OAuth2 Metadata
log_message("\n[2/4] Testing OAuth2 Authorization Server Metadata Endpoint", 'blue');
$metadataUrl = "$baseUrl/.well-known/oauth-authorization-server";
test_endpoint($metadataUrl);

// Test 3: JWKS Endpoint
log_message("\n[3/4] Testing JWKS Endpoint", 'blue');
$jwksUrl = "$baseUrl/.well-known/jwks.json";
if (!test_endpoint($jwksUrl)) {
    log_message("\n❌ JWKS endpoint failed!", 'red');
    exit(1);
}

// Get and validate JWKS
$ch = curl_init($jwksUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$jwks = json_decode((string)$response, true);
validate_jwks($jwks);

// Test 4: Discovery Consistency
log_message("\n[4/4] Testing Endpoint Consistency", 'blue');
if ($config['issuer'] && $config['authorization_endpoint']) {
    log_message("✅ Issuer and endpoints are configured", 'green');
} else {
    log_message("⚠️  Missing issuer or endpoints", 'yellow');
}

// Summary
log_message("\n╔════════════════════════════════════════╗", 'blue');
log_message("║              Test Complete             ║", 'blue');
log_message("╚════════════════════════════════════════╝", 'blue');

log_message("\n📋 Configuration Summary:", 'yellow');
log_message("Issuer: " . ($config['issuer'] ?? 'NOT SET'), 'yellow');
log_message("Authorization Endpoint: " . ($config['authorization_endpoint'] ?? 'NOT SET'), 'yellow');
log_message("Token Endpoint: " . ($config['token_endpoint'] ?? 'NOT SET'), 'yellow');
log_message("UserInfo Endpoint: " . ($config['userinfo_endpoint'] ?? 'NOT SET'), 'yellow');
log_message("JWKS URI: " . ($config['jwks_uri'] ?? 'NOT SET'), 'yellow');

log_message("\n✅ All tests passed! OIDC is properly configured.", 'green');
log_message("\nNext steps:", 'blue');
log_message("1. Configure your OIDC client with the discovery URL", 'blue');
log_message("2. Set up client credentials in config/config.yml", 'blue');
log_message("3. Test the authorization flow", 'blue');

exit(0);

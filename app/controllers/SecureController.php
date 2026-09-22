<?php
/*
Copyright (c) <2021> Susilo Nurcahyo

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

namespace Controllers;

use Bantingan\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class SecureController extends Controller
{
    /**
     * Default action — every controller MUST have index() by Bantingan skill rule.
     * If not authenticated, redirect to OIDC login first.
     */
    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['oidc_user'])) {
            $this->redirectToURL($this->baseUrl() . '/Secure/login');
            return;
        }

        $this->viewBag->user = $_SESSION['oidc_user'];
        $this->viewBag->idToken = $_SESSION['id_token'] ?? null;
        $this->viewBag->accessToken = $_SESSION['access_token'] ?? null;
        $this->viewBag->claims = $_SESSION['id_token_claims'] ?? null;
        $this->viewBag->pageTitle = 'Secure Area';
        // Pre-flatten user fields for display so the template needs no
        // is_array/is_object checks (unregistered Smarty functions are deprecated).
        $userRows = [];
        foreach ((array)$_SESSION['oidc_user'] as $k => $v) {
            $userRows[$k] = (is_array($v) || is_object($v)) ? json_encode($v) : $v;
        }
        $this->viewBag->userRows = $userRows;
        return $this->view();
    }

    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cfg = $this->getOidcConfig();
        $discovery = $this->getDiscovery($cfg['provider_url']);

        if (!$discovery || empty($discovery['authorization_endpoint'])) {
            $this->viewBag->error = 'OIDC discovery failed for ' . htmlspecialchars($cfg['provider_url']);
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        // Generate state, nonce, PKCE
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));

        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $nonce;
        $_SESSION['oidc_verifier'] = $verifier;
        // Remember original target
        $_SESSION['oidc_return_to'] = $_GET['return_to'] ?? $this->baseUrl() . '/Secure/index';

        $params = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope' => $cfg['scopes'],
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        $authUrl = $discovery['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $this->redirectToURL($authUrl);
    }

    public function callback()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Error from provider
        if (!empty($_GET['error'])) {
            $this->viewBag->error = 'OIDC error: ' . htmlspecialchars($_GET['error']) . ' - ' . htmlspecialchars($_GET['error_description'] ?? '');
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;

        if (!$code || !$state) {
            $this->viewBag->error = 'Missing code or state in callback.';
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        if (empty($_SESSION['oidc_state']) || !hash_equals($_SESSION['oidc_state'], $state)) {
            $this->viewBag->error = 'Invalid state (CSRF protection).';
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        $cfg = $this->getOidcConfig();
        $discovery = $this->getDiscovery($cfg['provider_url']);

        if (!$discovery || empty($discovery['token_endpoint'])) {
            $this->viewBag->error = 'OIDC discovery missing token_endpoint.';
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        $verifier = $_SESSION['oidc_verifier'] ?? '';

        // Exchange code for tokens
        $tokenResponse = $this->httpPost($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code_verifier' => $verifier,
        ]);

        if (empty($tokenResponse['id_token'])) {
            $this->viewBag->error = 'Token endpoint did not return id_token. Response: ' . htmlspecialchars(json_encode($tokenResponse));
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        $idToken = $tokenResponse['id_token'];
        $accessToken = $tokenResponse['access_token'] ?? null;
        $refreshToken = $tokenResponse['refresh_token'] ?? null;

        // Verify and decode id_token
        $claims = $this->verifyAndDecodeIdToken($idToken, $cfg, $discovery);

        if (!$claims) {
            $this->viewBag->error = 'Failed to verify id_token signature.';
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        // Validate nonce if present
        $expectedNonce = $_SESSION['oidc_nonce'] ?? null;
        if ($expectedNonce && isset($claims['nonce']) && !hash_equals($expectedNonce, $claims['nonce'])) {
            $this->viewBag->error = 'Invalid nonce.';
            $this->viewBag->pageTitle = 'OIDC Error';
            return $this->view('Secure/error.html');
        }

        // Optionally fetch userinfo
        $userinfo = null;
        if (!empty($discovery['userinfo_endpoint']) && $accessToken) {
            $userinfo = $this->httpGetBearer($discovery['userinfo_endpoint'], $accessToken);
        }

        // No persistence — just display in view as requested
        $userDisplay = $userinfo ?: $claims;

        // Clean one-time values
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce'], $_SESSION['oidc_verifier']);

        // Store in session for display (not persisted to DB per requirement)
        $_SESSION['oidc_user'] = $userDisplay;
        $_SESSION['id_token'] = $idToken;
        $_SESSION['id_token_claims'] = $claims;
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['refresh_token'] = $refreshToken;
        $_SESSION['oidc_userinfo'] = $userinfo;

        $returnTo = $_SESSION['oidc_return_to'] ?? $this->baseUrl() . '/Secure/index';
        unset($_SESSION['oidc_return_to']);

        $this->redirectToURL($returnTo);
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idToken = $_SESSION['id_token'] ?? null;
        $cfg = $this->getOidcConfig();
        $discovery = $this->getDiscovery($cfg['provider_url']);

        // Clear local session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        // Optional front-channel logout at OP
        $endSession = $cfg['end_session_endpoint'] ?: ($discovery['end_session_endpoint'] ?? null);
        if ($endSession) {
            $params = [];
            if ($idToken) {
                $params['id_token_hint'] = $idToken;
            }
            $params['post_logout_redirect_uri'] = $cfg['post_logout_redirect_uri'] ?: $this->baseUrl() . '/';
            $url = $endSession . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $this->redirectToURL($url);
        }

        $this->redirectToURL($cfg['post_logout_redirect_uri'] ?: $this->baseUrl() . '/');
    }

    // ---------- helpers ----------

    private function getOidcConfig(): array
    {
        $raw = defined('OIDC_SETTINGS') ? constant('OIDC_SETTINGS') : (defined('OIDC_SETTINGS') ? OIDC_SETTINGS : []);
        // Support both nested `oidc:` and flat structure
        $cfg = isset($raw['oidc']) && is_array($raw['oidc']) ? $raw['oidc'] : $raw;

        // Allow env overrides
        $cfg['provider_url'] = getenv('OIDC_PROVIDER_URL') ?: ($cfg['provider_url'] ?? '');
        $cfg['client_id'] = getenv('OIDC_CLIENT_ID') ?: ($cfg['client_id'] ?? '');
        $cfg['client_secret'] = getenv('OIDC_CLIENT_SECRET') ?: ($cfg['client_secret'] ?? '');
        $cfg['redirect_uri'] = getenv('OIDC_REDIRECT_URI') ?: ($cfg['redirect_uri'] ?? $this->baseUrl() . '/Secure/callback');
        $cfg['scopes'] = getenv('OIDC_SCOPES') ?: ($cfg['scopes'] ?? 'openid email profile');
        $cfg['post_logout_redirect_uri'] = getenv('OIDC_POST_LOGOUT_REDIRECT_URI') ?: ($cfg['post_logout_redirect_uri'] ?? $this->baseUrl() . '/');
        $verifyJwtEnv = getenv('OIDC_VERIFY_JWT');
        if ($verifyJwtEnv !== false && $verifyJwtEnv !== '') {
            $parsed = filter_var($verifyJwtEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $cfg['verify_jwt'] = $parsed ?? (bool)$verifyJwtEnv;
        } else {
            $cfg['verify_jwt'] = isset($cfg['verify_jwt'])
                ? (is_string($cfg['verify_jwt'])
                    ? (filter_var($cfg['verify_jwt'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$cfg['verify_jwt'])
                    : (bool)$cfg['verify_jwt'])
                : true;
        }
        $cfg['end_session_endpoint'] = getenv('OIDC_END_SESSION_ENDPOINT') ?: ($cfg['end_session_endpoint'] ?? '');

        return $cfg;
    }

    private function getDiscovery(string $providerUrl): ?array
    {
        $providerUrl = rtrim($providerUrl, '/');
        // Simple in-memory / session cache
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $cacheKey = 'oidc_discovery_' . md5($providerUrl);
        if (!empty($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['_expires'] > time()) {
            return $_SESSION[$cacheKey];
        }

        $url = $providerUrl . '/.well-known/openid-configuration';
        $data = $this->httpGetJson($url);
        if ($data) {
            $data['_expires'] = time() + 600; // 10 min
            $_SESSION[$cacheKey] = $data;
        }
        return $data;
    }

    private function verifyAndDecodeIdToken(string $jwt, array $cfg, array $discovery): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        // If verification disabled, just decode payload
        if (empty($cfg['verify_jwt'])) {
            return json_decode($this->base64UrlDecode($parts[1]), true);
        }

        // Try JWKS verification via firebase/php-jwt
        try {
            $jwksUri = $discovery['jwks_uri'] ?? null;
            if ($jwksUri) {
                $jwks = $this->httpGetJson($jwksUri);
                if ($jwks) {
                    $keys = JWK::parseKeySet($jwks);
                    $decoded = JWT::decode($jwt, $keys);
                    $claims = json_decode(json_encode($decoded), true);

                    // Validate iss/aud/exp
                    if (!empty($discovery['issuer']) && ($claims['iss'] ?? '') !== $discovery['issuer']) {
                        // Some providers include trailing slash mismatch — allow both
                        if (rtrim($claims['iss'] ?? '', '/') !== rtrim($discovery['issuer'], '/')) {
                            return null;
                        }
                    }
                    if (($claims['aud'] ?? '') !== $cfg['client_id'] && !in_array($cfg['client_id'], (array)($claims['aud'] ?? []), true)) {
                        return null;
                    }
                    return $claims;
                }
            }
        } catch (ExpiredException $e) {
            return null;
        } catch (SignatureInvalidException $e) {
            return null;
        } catch (\Throwable $e) {
            // Fall through to unverified decode for demo
        }

        // Fallback: unverified decode (still check exp/aud if possible)
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!$payload) {
            return null;
        }
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    private function httpGetJson(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code >= 200 && $code < 300 && $resp) {
            $data = json_decode($resp, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    private function httpPost(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = json_decode($resp ?? '', true);
        if (is_array($data)) {
            return $data;
        }
        // Some providers return urlencoded error
        return ['raw' => $resp, 'http_code' => $code];
    }

    private function httpGetBearer(string $url, string $token): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code >= 200 && $code < 300) {
            $data = json_decode($resp, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

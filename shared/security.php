<?php
/**
 * Shared, DB-agnostic helpers (used by both SFT and PaySteam).
 * Keep this file free from requires that open DB connections.
 */

function hmac_sign_base64(string $raw, string $secret): string
{
  return base64_encode(hash_hmac('sha256', $raw, $secret, true));
}

function hmac_verify_base64(string $raw, string $sigB64, string $secret): bool
{
  if ($sigB64 === '') return false;
  $calc = hmac_sign_base64($raw, $secret);
  return hash_equals($calc, $sigB64);
}

function validate_email(string $email): bool
{
  $email = trim($email);
  if ($email === '' || strlen($email) > 254) return false;
  return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password(string $password): bool
{
  // Avoids pretending to enforce "strong" passwords with complicated checks.
  return strlen($password) >= 8;
}

/**
 * Simple in-memory rate limiter.
 *
 * @param array  $store  associative array where we keep counters
 * @param string $key    unique key (e.g., "login:1.2.3.4")
 * @param int    $max    max hits allowed in the window
 * @param int    $window window size in seconds
 * @param int|null $now  override current time (useful for tests)
 *
 * @return bool true if allowed, false if blocked
*/
function rate_limiter_allow(array &$store, string $key, int $max, int $window, ?int $now = null): bool
{
  $now = $now ?? time();
  if (!isset($store[$key])) {
    $store[$key] = ['count' => 0, 'start' => $now];
  }

  $start = (int)($store[$key]['start'] ?? $now);
  if (($now - $start) >= $window) {
    $store[$key] = ['count' => 0, 'start' => $now];
  }

  $store[$key]['count'] = (int)($store[$key]['count'] ?? 0) + 1;
  return $store[$key]['count'] <= $max;
}

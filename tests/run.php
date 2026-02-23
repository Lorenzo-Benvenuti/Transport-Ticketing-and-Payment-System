<?php
// Minimal test suite (no external dependencies).
// Run with: php tests/run.php

require_once __DIR__ . '/../shared/security.php';

function ok($cond, $msg)
{
  if (!$cond) {
    fwrite(STDERR, "[FAIL] $msg\n");
    exit(1);
  }
}

// HMAC sign/verify
$secret = 'unit-test-secret';
$raw = json_encode(['a' => 1, 'ts' => 123]);
$sig = hmac_sign_base64($raw, $secret);
ok($sig !== '', 'signature should not be empty');
ok(hmac_verify_base64($raw, $sig, $secret) === true, 'signature should verify');
ok(hmac_verify_base64($raw . 'x', $sig, $secret) === false, 'tampered payload should not verify');

// Email validation
ok(validate_email('user@example.com') === true, 'valid email');
ok(validate_email('not-an-email') === false, 'invalid email');

// Password validation
ok(validate_password('12345678') === true, 'min length');
ok(validate_password('123') === false, 'too short');

// Rate limiter
$store = [];
$k = 'login:1.2.3.4';
for ($i = 1; $i <= 5; $i++) {
  ok(rate_limiter_allow($store, $k, 5, 600, 1000) === true, "hit $i should be allowed");
}
ok(rate_limiter_allow($store, $k, 5, 600, 1000) === false, '6th hit should be blocked');
// Window reset
ok(rate_limiter_allow($store, $k, 5, 600, 2000) === true, 'after window, should be allowed');

echo "OK\n";

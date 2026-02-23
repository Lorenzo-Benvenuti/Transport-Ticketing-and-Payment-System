<?php
// Shared HTTP helpers.

function http_post_json(string $url, array $payload, array $headers = []): array
{
  $headers = array_merge([
    'Content-Type: application/json',
    'Accept: application/json',
  ], $headers);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 10,
  ]);

  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return [
    'ok' => ($err === '' && $code >= 200 && $code < 300),
    'code' => $code,
    'error' => $err,
    'body' => $body,
    'json' => $body ? json_decode($body, true) : null,
  ];
}

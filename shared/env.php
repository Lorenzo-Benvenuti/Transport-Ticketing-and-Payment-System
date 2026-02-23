<?php
/**
 * Minimal .env loader shared by both apps.
 *
 * Notes:
 * - No type casting
 * - Supports comments (#) and empty lines
*/

function load_env(string $path): void
{
  if (!file_exists($path)) return;

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    if (!str_contains($line, '=')) continue;

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    putenv("$key=$value");
    $_ENV[$key] = $value;
  }
}

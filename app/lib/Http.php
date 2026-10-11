<?php

final class Http {
  public static function sendJson(array $data, int $status = 200): void {
    $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($body === false) {
      error_log('solar: json_encode failed: ' . json_last_error_msg());
      $status = 500;
      $body = '{"error":"internal error"}';
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
  }

  public static function fail(string $message, int $status = 400): never {
    self::sendJson(['error' => $message], $status);
    exit;
  }

  // GET entry guard: only GET/HEAD, OPTIONS answered without body
  public static function guardGet(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
      header('Allow: GET, HEAD, OPTIONS');
      http_response_code(204);
      exit;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
      header('Allow: GET, HEAD, OPTIONS');
      self::fail('method not allowed', 405);
    }
  }

  public static function query(string $name): ?string {
    $value = $_GET[$name] ?? null;
    if (!is_string($value)) {
      return null;
    }
    $value = trim($value);
    return $value === '' ? null : $value;
  }
}

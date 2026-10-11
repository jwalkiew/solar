<?php
// configuration: defaults come from the environment (see docker-compose.yml / .env.example)

final class AppConfig {
  public const MIN_YEAR = 1600;
  public const MAX_YEAR = 2400;

  public static function defaultLat(): float {
    return self::envFloat('SOLAR_DEFAULT_LAT', 51.897202, -90, 90);
  }

  public static function defaultLon(): float {
    return self::envFloat('SOLAR_DEFAULT_LON', 21.615002, -180, 180);
  }

  public static function defaultElev(): float {
    return self::envFloat('SOLAR_DEFAULT_ELEV', 100, -500, 9000);
  }

  public static function defaultName(): ?string {
    $name = getenv('SOLAR_DEFAULT_NAME');
    if ($name === false) {
      return 'Garwolin';
    }
    return trim($name) === '' ? null : trim($name);
  }

  public static function cacheDir(): ?string {
    $dir = getenv('SOLAR_CACHE_DIR');
    if ($dir === false || $dir === '') {
      $dir = sys_get_temp_dir() . '/solar-cache';
    }
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
      error_log('solar: cannot create cache dir ' . $dir);
      return null;
    }
    return $dir;
  }

  private static function envFloat(string $name, float $default, float $min, float $max): float {
    $raw = getenv($name);
    if ($raw === false || trim($raw) === '') {
      return $default;
    }
    if (!is_numeric(trim($raw)) || (float)$raw < $min || (float)$raw > $max) {
      error_log("solar: ignoring invalid {$name}, using default");
      return $default;
    }
    return (float)$raw;
  }
}

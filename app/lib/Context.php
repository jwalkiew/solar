<?php
require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/TimeZoneLookup.php';
require_once __DIR__ . '/Places.php';

// reference instant, observer location and its time zone resolved from the query string
final class Context {
  public function __construct(
    public readonly float $unix,
    public readonly float $lat,
    public readonly float $lon,
    public readonly float $elev,
    public readonly ?string $name,
    public readonly bool $isDefault,
    public readonly DateTimeZone $tz,
    public readonly string $tzSource
  ) {
  }

  public static function fromQuery(): self {
    $latRaw = Http::query('lat');
    $lonRaw = Http::query('lon');
    $elevRaw = Http::query('elev');
    if (($latRaw === null) !== ($lonRaw === null)) {
      Http::fail('lat and lon must be given together');
    }
    $isDefault = $latRaw === null;
    $lat = $isDefault ? AppConfig::defaultLat() : self::number($latRaw, 'lat', -90, 90);
    $lon = $isDefault ? AppConfig::defaultLon() : self::number($lonRaw, 'lon', -180, 180);
    if ($elevRaw !== null) {
      $elev = self::number($elevRaw, 'elev', -500, 9000);
    } else {
      $elev = $isDefault ? AppConfig::defaultElev() : 0.0;
    }

    $tzId = Http::query('tz');
    if ($tzId !== null) {
      if (!self::isKnownZone($tzId)) {
        Http::fail('unknown time zone: ' . $tzId);
      }
      $tz = new DateTimeZone($tzId);
      $tzSource = 'parameter';
    } else {
      [$tz, $tzSource] = self::zoneForCoordinates($lat, $lon);
    }

    $unix = self::instant($tz);
    $year = (int)gmdate('Y', (int)floor($unix));
    if ($year < AppConfig::MIN_YEAR || $year > AppConfig::MAX_YEAR) {
      Http::fail('supported years: ' . AppConfig::MIN_YEAR . '-' . AppConfig::MAX_YEAR);
    }

    return new self($unix, $lat, $lon, $elev, $isDefault ? AppConfig::defaultName() : null, $isDefault, $tz, $tzSource);
  }

  // zone from the coordinates; falls back to the nautical zone when the id is unknown to this PHP build
  public static function zoneForCoordinates(float $lat, float $lon): array {
    $id = TimeZoneLookup::lookup($lat, $lon);
    if ($id !== null && self::isKnownZone($id)) {
      return [new DateTimeZone($id), 'coordinates'];
    }
    $hours = (int)round($lon / 15);
    $nautical = $hours === 0 ? 'Etc/GMT' : sprintf('Etc/GMT%+d', -$hours);
    return [new DateTimeZone($nautical), 'longitude'];
  }

  public function location(): array {
    return [
      'lat' => $this->lat,
      'lon' => $this->lon,
      'elev' => $this->elev,
      'name' => $this->name,
      'isDefault' => $this->isDefault,
      'place' => Places::nearest($this->lat, $this->lon),
    ];
  }

  public function timezone(): array {
    $now = (new DateTimeImmutable('@' . (int)floor($this->unix)))->setTimezone($this->tz);
    $offset = $now->getOffset();
    return [
      'id' => $this->tz->getName(),
      'abbr' => $now->format('T'),
      'offsetSeconds' => $offset,
      'offset' => $now->format('P'),
      'dst' => $now->format('I') === '1',
      'source' => $this->tzSource,
    ];
  }

  private static function instant(DateTimeZone $tz): float {
    $t = Http::query('t');
    $local = Http::query('local');
    if ($t !== null && $local !== null) {
      Http::fail('use either t or local, not both');
    }
    if ($t !== null) {
      if (preg_match('/^-?\d{1,12}(\.\d+)?$/', $t)) {
        return (float)$t;
      }
      // ISO-8601 instant: an explicit Z or offset is required
      if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/i', $t)) {
        Http::fail('t must be unix seconds or ISO-8601 with Z or offset; use local= for wall-clock time');
      }
      $dt = date_create_immutable($t);
      $errors = DateTimeImmutable::getLastErrors();
      if ($dt === false || ($errors !== false && $errors['warning_count'] > 0)) {
        Http::fail('invalid t');
      }
      return (float)$dt->format('U.u');
    }
    if ($local !== null) {
      if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $local)) {
        Http::fail('local must be YYYY-MM-DDTHH:MM[:SS]');
      }
      $format = strlen($local) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
      $dt = DateTimeImmutable::createFromFormat($format, $local, $tz);
      $errors = DateTimeImmutable::getLastErrors();
      if ($dt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        Http::fail('invalid local date/time');
      }
      return (float)$dt->format('U');
    }
    return microtime(true);
  }

  private static function number(string $raw, string $name, float $min, float $max): float {
    if (!is_numeric($raw)) {
      Http::fail("{$name} must be a number");
    }
    $value = (float)$raw;
    if (!is_finite($value) || $value < $min || $value > $max) {
      Http::fail("{$name} must be between {$min} and {$max}");
    }
    return $value;
  }

  private static function isKnownZone(string $id): bool {
    static $known = null;
    if ($known === null) {
      $known = array_flip(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));
      $known['UTC'] = true;
    }
    return isset($known[$id]);
  }
}

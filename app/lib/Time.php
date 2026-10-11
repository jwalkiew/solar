<?php
require_once __DIR__ . '/astro/AstroDeltaT.php';

// conversions between unix time, Julian Day (UT) and Julian Ephemeris Day (TT)
final class Time {
  public const UNIX_EPOCH_JD = 2440587.5;

  public static function unixToJd(float $unix): float {
    return $unix / 86400 + self::UNIX_EPOCH_JD;
  }

  public static function jdToUnix(float $jd): float {
    return ($jd - self::UNIX_EPOCH_JD) * 86400;
  }

  // deltaT = TT - UT in seconds
  public static function deltaT(float $jd): float {
    return AstroDeltaT::deltaT(2000 + ($jd - 2451545.0) / 365.25);
  }

  public static function jdToJde(float $jd): float {
    return $jd + self::deltaT($jd) / 86400;
  }

  public static function jdeToJd(float $jde): float {
    // deltaT changes by well under a second per year, one step is exact enough
    return $jde - self::deltaT($jde) / 86400;
  }

  public static function jdeToUnix(float $jde): float {
    return self::jdToUnix(self::jdeToJd($jde));
  }

  // time object of the API contract
  public static function obj(?float $unix, DateTimeZone $tz): ?array {
    if ($unix === null) {
      return null;
    }
    $seconds = (int)floor($unix + 0.5);
    $utc = new DateTimeImmutable('@' . $seconds);
    return [
      'unix' => $seconds,
      'utc' => $utc->format('Y-m-d\TH:i:s\Z'),
      'local' => $utc->setTimezone($tz)->format('Y-m-d\TH:i:sP'),
      'jd' => round(self::unixToJd($unix), 7),
    ];
  }

  public static function objFromJde(?float $jde, DateTimeZone $tz): ?array {
    return $jde === null ? null : self::obj(self::jdeToUnix($jde), $tz);
  }

  // [start, end) unix of the local calendar day containing the instant
  public static function localDay(float $unix, DateTimeZone $tz): array {
    $local = (new DateTimeImmutable('@' . (int)floor($unix)))->setTimezone($tz);
    $start = $local->setTime(0, 0);
    $end = $start->modify('+1 day')->setTime(0, 0);
    return [(float)$start->getTimestamp(), (float)$end->getTimestamp()];
  }

  public static function decimalYear(float $jd): float {
    return 2000 + ($jd - 2451545.0) / 365.25;
  }
}

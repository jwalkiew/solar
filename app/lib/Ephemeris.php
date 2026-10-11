<?php
require_once __DIR__ . '/astro/AstroData.php';
require_once __DIR__ . '/astro/AstroBase.php';
require_once __DIR__ . '/astro/AstroCoord.php';
require_once __DIR__ . '/astro/AstroNutation.php';
require_once __DIR__ . '/astro/AstroApparent.php';
require_once __DIR__ . '/astro/AstroPlanetPosition.php';
require_once __DIR__ . '/astro/AstroSolar.php';
require_once __DIR__ . '/astro/AstroMoonPosition.php';
require_once __DIR__ . '/astro/AstroSidereal.php';
require_once __DIR__ . '/astro/AstroIllum.php';
require_once __DIR__ . '/astro/AstroSaturnRing.php';
require_once __DIR__ . '/astro/AstroSemidiameter.php';

// apparent geocentric positions (equinox of date) of Sun, Moon and planets; angles in radians
final class Ephemeris {
  public const PLANETS = ['mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune'];
  public const AU_KM = 149597870.7;
  public const EARTH_RADIUS_KM = 6378.14;
  public const MOON_RADIUS_KM = 1737.4;

  private static array $vsop = [];
  private static array $nutationCache = [];

  public static function vsop(string $name): AstroPlanet {
    if (!isset(self::$vsop[$name])) {
      self::$vsop[$name] = new AstroPlanet(AstroData::get('vsop87b-' . $name));
    }
    return self::$vsop[$name];
  }

  // [dpsi, deps, true obliquity]
  public static function nutation(float $jde): array {
    $key = sprintf('%.6F', $jde);
    if (!isset(self::$nutationCache[$key])) {
      if (count(self::$nutationCache) > 512) {
        self::$nutationCache = [];
      }
      [$dpsi, $deps] = AstroNutation::nutation($jde);
      self::$nutationCache[$key] = [$dpsi, $deps, AstroNutation::meanObliquity($jde) + $deps];
    }
    return self::$nutationCache[$key];
  }

  public static function sun(float $jde): array {
    $earth = self::vsop('earth');
    $c = AstroSolar::apparentVSOP87($earth, $jde);
    $eps = self::nutation($jde)[2];
    $eq = (new AstroEcliptic($c->lon, $c->lat))->toEquatorial($eps);
    $e = $earth->position($jde);
    return [
      'lon' => AstroBase::pmod($c->lon, 2 * M_PI),
      'lat' => $c->lat,
      'ra' => AstroBase::pmod($eq->ra, 2 * M_PI),
      'dec' => $eq->dec,
      'distAu' => $c->range,
      'earth' => ['lon' => $e->lon, 'lat' => $e->lat, 'r' => $e->range],
    ];
  }

  public static function moon(float $jde): array {
    $c = AstroMoonPosition::position($jde);
    [$dpsi, , $eps] = self::nutation($jde);
    $lon = AstroBase::pmod($c->lon + $dpsi, 2 * M_PI);
    $eq = (new AstroEcliptic($lon, $c->lat))->toEquatorial($eps);
    return [
      'lon' => $lon,
      'lat' => $c->lat,
      'ra' => AstroBase::pmod($eq->ra, 2 * M_PI),
      'dec' => $eq->dec,
      'distAu' => $c->range / self::AU_KM,
      'distKm' => $c->range,
    ];
  }

  // follows astronomia elliptic.position (Meeus ch. 33) and also returns distance and heliocentric position
  public static function planet(string $name, float $jde): array {
    $e = self::vsop('earth')->position($jde);
    $p = self::vsop($name);
    $geo = self::geocentric($p->position($jde), $e);
    $tau = AstroBase::lightTime($geo['dist']);
    $h = $p->position($jde - $tau);
    $geo = self::geocentric($h, $e);

    $lon = atan2($geo['y'], $geo['x']);
    $lat = atan2($geo['z'], hypot($geo['x'], $geo['y']));
    [$dLon, $dLat] = AstroApparent::eclipticAberration($lon, $lat, $jde);
    $fk5 = AstroPlanetPosition::toFK5($lon + $dLon, $lat + $dLat, $jde);
    [$dpsi, , $eps] = self::nutation($jde);
    $lon = AstroBase::pmod($fk5->lon + $dpsi, 2 * M_PI);
    $lat = $fk5->lat;
    $eq = (new AstroEcliptic($lon, $lat))->toEquatorial($eps);
    return [
      'lon' => $lon,
      'lat' => $lat,
      'ra' => AstroBase::pmod($eq->ra, 2 * M_PI),
      'dec' => $eq->dec,
      'distAu' => $geo['dist'],
      'lightTimeDays' => $tau,
      'helio' => ['lon' => AstroBase::pmod($h->lon, 2 * M_PI), 'lat' => $h->lat, 'r' => $h->range],
      'earthR' => $e->range,
    ];
  }

  // geometric geocentric ecliptic longitude/latitude, no light time or aberration; for event scans
  public static function planetFast(string $name, float $jde, object $earthPos): array {
    $geo = self::geocentric(self::vsop($name)->position($jde), $earthPos);
    return [atan2($geo['y'], $geo['x']), atan2($geo['z'], hypot($geo['x'], $geo['y'])), $geo['dist']];
  }

  public static function body(string $id, float $jde): array {
    return match ($id) {
      'sun' => self::sun($jde),
      'moon' => self::moon($jde),
      default => self::planet($id, $jde),
    };
  }

  // visual magnitude, Astronomical Almanac 1984 formulas (Meeus ch. 41); i = phase angle
  public static function magnitude(string $name, float $r, float $dist, float $i, float $jde): ?float {
    return match ($name) {
      'mercury' => AstroIllum::mercury84($r, $dist, $i),
      'venus' => AstroIllum::venus84($r, $dist, $i),
      'mars' => AstroIllum::mars84($r, $dist, $i),
      'jupiter' => AstroIllum::jupiter84($r, $dist, $i),
      'saturn' => self::saturnMagnitude($r, $dist, $jde),
      'uranus' => AstroIllum::uranus84($r, $dist),
      'neptune' => AstroIllum::neptune84($r, $dist),
      default => null,
    };
  }

  private static function saturnMagnitude(float $r, float $dist, float $jde): float {
    [$dU, $b] = AstroSaturnRing::ub($jde, self::vsop('earth'), self::vsop('saturn'));
    return AstroIllum::saturn84($r, $dist, $b, $dU);
  }

  // apparent angular diameter in arcseconds
  public static function angularDiameter(string $name, float $distAu): ?float {
    $s0 = match ($name) {
      'sun' => AstroSemidiameter::Sun,
      'mercury' => AstroSemidiameter::Mercury,
      'venus' => AstroSemidiameter::VenusCloud,
      'mars' => AstroSemidiameter::Mars,
      'jupiter' => AstroSemidiameter::JupiterEquatorial,
      'saturn' => AstroSemidiameter::SaturnEquatorial,
      'uranus' => AstroSemidiameter::Uranus,
      'neptune' => AstroSemidiameter::Neptune,
      default => null,
    };
    if ($s0 === null) {
      return 2 * asin(self::MOON_RADIUS_KM / ($distAu * self::AU_KM)) * 648000 / M_PI;
    }
    return 2 * AstroSemidiameter::semidiameter($s0, $distAu) * 648000 / M_PI;
  }

  // Greenwich apparent sidereal time in radians for a UT Julian Day
  public static function gast(float $jd): float {
    return AstroSidereal::apparent($jd) / 86400 * 2 * M_PI;
  }

  public static function angularDistance(float $ra1, float $dec1, float $ra2, float $dec2): float {
    // haversine form, accurate for small separations
    $a = sin(($dec2 - $dec1) / 2) ** 2 + cos($dec1) * cos($dec2) * sin(($ra2 - $ra1) / 2) ** 2;
    return 2 * asin(min(1.0, sqrt($a)));
  }

  private static function geocentric(object $h, object $e): array {
    $x = $h->range * cos($h->lat) * cos($h->lon) - $e->range * cos($e->lat) * cos($e->lon);
    $y = $h->range * cos($h->lat) * sin($h->lon) - $e->range * cos($e->lat) * sin($e->lon);
    $z = $h->range * sin($h->lat) - $e->range * sin($e->lat);
    return ['x' => $x, 'y' => $y, 'z' => $z, 'dist' => sqrt($x * $x + $y * $y + $z * $z)];
  }
}

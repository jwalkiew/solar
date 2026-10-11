<?php
require_once __DIR__ . '/Context.php';
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/Ephemeris.php';
require_once __DIR__ . '/Observer.php';
require_once __DIR__ . '/MoonPhases.php';
require_once __DIR__ . '/astro/AstroSidereal.php';
require_once __DIR__ . '/astro/AstroJulian.php';
require_once __DIR__ . '/astro/AstroEqTime.php';
require_once __DIR__ . '/astro/AstroIllum.php';

// GET /api/sky.php payload
final class SkyService {
  public const STEP_SEC = 600;
  private const NODE_SEC = 10800;
  private const BODIES = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune'];
  // standard altitudes of rise/set, degrees (Meeus ch. 15); moon depends on parallax
  private const H0_SUN = -0.8333;
  private const H0_STAR = -0.5667;

  private Observer $obs;
  private array $nodes = [];
  private array $nodeUnix = [];

  public function __construct(private Context $ctx) {
    $this->obs = new Observer($ctx->lat, $ctx->lon, $ctx->elev);
  }

  public function build(): array {
    $tz = $this->ctx->tz;
    $unix = $this->ctx->unix;
    $jd = Time::unixToJd($unix);
    $jde = Time::jdToJde($jd);
    $gast = Ephemeris::gast($jd);

    $pos = [];
    foreach (self::BODIES as $id) {
      $pos[$id] = Ephemeris::body($id, $jde);
    }

    [$dayStart, $dayEnd] = Time::localDay($unix, $tz);
    $this->computeNodes($dayStart - self::NODE_SEC, $dayEnd + self::NODE_SEC);
    $samples = (int)floor(($dayEnd - $dayStart) / self::STEP_SEC) + 1;

    $bodies = [];
    $series = [];
    foreach (self::BODIES as $id) {
      $day = $this->dayEvents($id, $dayStart, $dayEnd, $samples);
      $series[$id] = $day['curve'];
      $bodies[$id] = $this->bodyObject($id, $pos, $gast, $jde, $day, $tz);
    }

    $sun = $bodies['sun'];
    $sunDay = $this->sunDay($dayStart, $dayEnd, $samples, $tz);
    $sun += [
      'equationOfTimeMin' => round(AstroEqTime::e($jde, Ephemeris::vsop('earth')) * 720 / M_PI, 3),
      'solarNoon' => $sun['transit'],
      'dayLengthSec' => $this->dayLength($bodies['sun'], $dayStart, $dayEnd),
      'twilight' => $sunDay,
      'subsolar' => $this->subPoint($pos['sun'], $gast),
      'earthHeliocentric' => self::helio($pos['sun']['earth']),
    ];

    $moon = array_merge($bodies['moon'], $this->moonDetails($pos['moon'], $pos['sun'], $gast, $jde));

    $planets = [];
    foreach (Ephemeris::PLANETS as $id) {
      $planets[] = $bodies[$id];
    }

    return [
      'location' => $this->ctx->location(),
      'timezone' => $this->ctx->timezone(),
      'time' => $this->timeBlock($unix, $jd, $jde),
      'sun' => $sun,
      'moon' => $moon,
      'planets' => $planets,
      'separations' => $this->separations($pos),
      'curves' => [
        'start' => Time::obj($dayStart, $tz),
        'end' => Time::obj($dayEnd, $tz),
        'stepSec' => self::STEP_SEC,
        'series' => $series,
      ],
    ];
  }

  private function timeBlock(float $unix, float $jd, float $jde): array {
    $tz = $this->ctx->tz;
    $local = (new DateTimeImmutable('@' . (int)floor($unix)))->setTimezone($tz);
    $jdLocalDate = AstroJulian::CalendarGregorianToJD((int)$local->format('Y'), (int)$local->format('n'), (float)$local->format('j'));
    $jc = AstroJulian::JDToCalendarJulian($jdLocalDate + 0.5);
    $gmst = AstroSidereal::mean($jd) / 3600;
    $gast = AstroSidereal::apparent($jd) / 3600;
    $lonH = $this->ctx->lon / 15;
    return [
      'instant' => Time::obj($unix, $tz),
      'mjd' => round($jd - 2400000.5, 7),
      'jde' => round($jde, 7),
      'deltaT' => round(($jde - $jd) * 86400, 2),
      'julianCalendarDate' => sprintf('%04d-%02d-%02d', $jc['year'], $jc['month'], (int)floor($jc['day'])),
      'dayOfYear' => (int)$local->format('z') + 1,
      'isoWeek' => (int)$local->format('W'),
      'weekday' => (int)$local->format('N'),
      'gmst' => round($gmst, 6),
      'gast' => round($gast, 6),
      'lmst' => round(fmod($gmst + $lonH + 48, 24), 6),
      'last' => round(fmod($gast + $lonH + 48, 24), 6),
      'julianEpoch' => round(2000 + ($jde - 2451545.0) / 365.25, 5),
    ];
  }

  private function bodyObject(string $id, array $pos, float $gast, float $jde, array $day, DateTimeZone $tz): array {
    $p = $pos[$id];
    $sun = $pos['sun'];
    [$az, $alt] = $this->obs->horizontal($p['ra'], $p['dec'], $p['distAu'], $gast);
    $o = [
      'id' => $id,
      'ecliptic' => ['lon' => self::deg($p['lon']), 'lat' => self::deg($p['lat'])],
      'equatorial' => ['ra' => self::deg($p['ra']), 'dec' => self::deg($p['dec'])],
      'horizontal' => ['az' => self::deg($az), 'alt' => self::deg(Observer::refract($alt))],
      'distanceAu' => round($p['distAu'], 8),
      'lightTimeMin' => round($p['distAu'] * AstroBase::lightTime(1.0) * 1440, 4),
      'heliocentric' => isset($p['helio']) ? self::helio($p['helio']) : null,
      'elongation' => 0.0,
      'phaseAngle' => null,
      'illuminated' => null,
      'magnitude' => $id === 'sun' ? -26.74 : null,
      'angularDiameterArcsec' => round(Ephemeris::angularDiameter($id, $p['distAu']), 2),
      'rise' => Time::obj($day['rise'], $tz),
      'transit' => Time::obj($day['transit'], $tz),
      'set' => Time::obj($day['set'], $tz),
      'transitAlt' => $day['transitAlt'] === null ? null : self::deg($day['transitAlt']),
      'alwaysAbove' => $day['alwaysAbove'],
      'alwaysBelow' => $day['alwaysBelow'],
    ];
    if ($id === 'sun') {
      return $o;
    }

    $psi = Ephemeris::angularDistance($p['ra'], $p['dec'], $sun['ra'], $sun['dec']);
    $east = AstroBase::pmod($p['lon'] - $sun['lon'], 2 * M_PI) < M_PI;
    $o['elongation'] = self::deg($east ? $psi : -$psi);
    if ($id === 'moon') {
      return $o;
    }

    $r = $p['helio']['r'];
    $i = AstroIllum::phaseAngle($r, $p['distAu'], $p['earthR']);
    $o['phaseAngle'] = self::deg($i);
    $o['illuminated'] = round((1 + cos($i)) / 2, 4);
    $mag = Ephemeris::magnitude($id, $r, $p['distAu'], $i, $jde);
    $o['magnitude'] = $mag === null ? null : round($mag, 2);
    return $o;
  }

  private function moonDetails(array $moon, array $sun, float $gast, float $jde): array {
    // phase angle and illuminated fraction, Meeus 48.2-48.3
    $psi = Ephemeris::angularDistance($moon['ra'], $moon['dec'], $sun['ra'], $sun['dec']);
    $rKm = $sun['distAu'] * Ephemeris::AU_KM;
    $i = atan2($rKm * sin($psi), $moon['distKm'] - $rKm * cos($psi));
    // position angle of the bright limb, Meeus 48.5
    $chi = atan2(
      cos($sun['dec']) * sin($sun['ra'] - $moon['ra']),
      sin($sun['dec']) * cos($moon['dec']) - cos($sun['dec']) * sin($moon['dec']) * cos($sun['ra'] - $moon['ra'])
    );
    $mag = -12.73 + 0.026 * abs(rad2deg($i)) + 4e-9 * rad2deg($i) ** 4;
    $elong = AstroBase::pmod($moon['lon'] - $sun['lon'], 2 * M_PI);
    $prevNew = MoonPhases::previousNewMoon($jde);
    return [
      'distanceKm' => round($moon['distKm'], 1),
      'illumination' => round((1 + cos($i)) / 2, 4),
      'phaseAngle' => self::deg($i),
      'illuminated' => round((1 + cos($i)) / 2, 4),
      'magnitude' => round($mag, 2),
      'age' => round($jde - $prevNew['jde'], 3),
      'waxing' => $elong < M_PI,
      'phaseName' => self::phaseName(rad2deg($elong)),
      'brightLimbAngle' => self::deg(AstroBase::pmod($chi, 2 * M_PI)),
      'sublunar' => $this->subPoint($moon, $gast),
      'lunation' => $prevNew['lunation'],
    ];
  }

  private static function phaseName(float $elongDeg): string {
    return match (true) {
      $elongDeg < 7 || $elongDeg >= 353 => 'new_moon',
      $elongDeg < 83 => 'waxing_crescent',
      $elongDeg < 97 => 'first_quarter',
      $elongDeg < 173 => 'waxing_gibbous',
      $elongDeg < 187 => 'full_moon',
      $elongDeg < 263 => 'waning_gibbous',
      $elongDeg < 277 => 'last_quarter',
      default => 'waning_crescent',
    };
  }

  private function separations(array $pos): array {
    $out = [];
    $ids = self::BODIES;
    $n = count($ids);
    for ($a = 0; $a < $n; $a++) {
      for ($b = $a + 1; $b < $n; $b++) {
        $pa = $pos[$ids[$a]];
        $pb = $pos[$ids[$b]];
        $out[] = ['a' => $ids[$a], 'b' => $ids[$b], 'deg' => self::deg(Ephemeris::angularDistance($pa['ra'], $pa['dec'], $pb['ra'], $pb['dec']))];
      }
    }
    return $out;
  }

  private function subPoint(array $p, float $gast): array {
    $lon = rad2deg($p['ra'] - $gast);
    $lon = fmod(fmod($lon + 180, 360) + 360, 360) - 180;
    return ['lat' => self::deg($p['dec']), 'lon' => round($lon, 4)];
  }

  // time the Sun is above the horizon within the local day
  private function dayLength(array $sun, float $dayStart, float $dayEnd): ?int {
    if ($sun['alwaysAbove']) {
      return (int)($dayEnd - $dayStart);
    }
    if ($sun['alwaysBelow']) {
      return 0;
    }
    if ($sun['rise'] === null || $sun['set'] === null) {
      return null;
    }
    $rise = $sun['rise']['unix'];
    $set = $sun['set']['unix'];
    // sunset before sunrise: the Sun is up at both ends of the day (high latitudes near polar day)
    return $set >= $rise ? $set - $rise : (int)(($set - $dayStart) + ($dayEnd - $rise));
  }

  // positions every NODE_SEC over the padded local day, RA unwrapped for interpolation
  private function computeNodes(float $from, float $to): void {
    for ($t = $from; $t <= $to + 1; $t += self::NODE_SEC) {
      $this->nodeUnix[] = $t;
    }
    foreach (self::BODIES as $id) {
      $ra = [];
      $dec = [];
      $dist = [];
      foreach ($this->nodeUnix as $k => $t) {
        $p = Ephemeris::body($id, Time::jdToJde(Time::unixToJd($t)));
        $r = $p['ra'];
        if ($k > 0) {
          $prev = $ra[$k - 1];
          $r += 2 * M_PI * round(($prev - $r) / (2 * M_PI));
        }
        $ra[] = $r;
        $dec[] = $p['dec'];
        $dist[] = $p['distAu'];
      }
      $this->nodes[$id] = [$ra, $dec, $dist];
    }
  }

  // 4-point Lagrange interpolation of [ra, dec, distAu] at unix time
  private function interp(string $id, float $t): array {
    $n = count($this->nodeUnix);
    $i = (int)floor(($t - $this->nodeUnix[0]) / self::NODE_SEC);
    $i = max(1, min($n - 3, $i));
    $idx = [$i - 1, $i, $i + 1, $i + 2];
    $out = [0.0, 0.0, 0.0];
    foreach ($idx as $j) {
      $w = 1.0;
      foreach ($idx as $m) {
        if ($m !== $j) {
          $w *= ($t - $this->nodeUnix[$m]) / ($this->nodeUnix[$j] - $this->nodeUnix[$m]);
        }
      }
      for ($c = 0; $c < 3; $c++) {
        $out[$c] += $w * $this->nodes[$id][$c][$j];
      }
    }
    return $out;
  }

  // geocentric geometric altitude minus the standard altitude h0 (radians)
  private function riseFunction(string $id, float $t, ?float $h0Deg = null): float {
    [$ra, $dec, $dist] = $this->interp($id, $t);
    $gast = Ephemeris::gast(Time::unixToJd($t));
    [, $alt] = $this->obs->horizontal($ra, $dec, $dist, $gast, false);
    if ($h0Deg === null) {
      if ($id === 'moon') {
        $parallax = asin(Ephemeris::EARTH_RADIUS_KM / ($dist * Ephemeris::AU_KM));
        $h0Deg = 0.7275 * rad2deg($parallax) + self::H0_STAR;
      } else {
        $h0Deg = $id === 'sun' ? self::H0_SUN : self::H0_STAR;
      }
    }
    return $alt - deg2rad($h0Deg);
  }

  private function apparentAltitude(string $id, float $t): float {
    [$ra, $dec, $dist] = $this->interp($id, $t);
    [, $alt] = $this->obs->horizontal($ra, $dec, $dist, Ephemeris::gast(Time::unixToJd($t)));
    return Observer::refract($alt);
  }

  private function hourAngleAt(string $id, float $t): float {
    [$ra] = $this->interp($id, $t);
    return $this->obs->hourAngle($ra, Ephemeris::gast(Time::unixToJd($t)));
  }

  private function dayEvents(string $id, float $start, float $end, int $samples): array {
    $curve = [];
    $f = [];
    $ha = [];
    for ($k = 0; $k < $samples; $k++) {
      $t = min($start + $k * self::STEP_SEC, $end);
      $curve[] = self::deg($this->apparentAltitude($id, $t));
      $f[] = $this->riseFunction($id, $t);
      $ha[] = $this->hourAngleAt($id, $t);
    }
    [$rise, $set] = $this->crossings($f, $start, $end, fn ($t) => $this->riseFunction($id, $t));

    $transit = null;
    for ($k = 1; $k < $samples; $k++) {
      if ($ha[$k - 1] < 0 && $ha[$k] >= 0 && $ha[$k] - $ha[$k - 1] < M_PI) {
        $transit = self::bisect(fn ($t) => $this->hourAngleAt($id, $t), $start + ($k - 1) * self::STEP_SEC, min($start + $k * self::STEP_SEC, $end));
        break;
      }
    }

    $above = $rise === null && $set === null && min($f) > 0;
    $below = $rise === null && $set === null && max($f) < 0;
    return [
      'curve' => $curve,
      'rise' => $rise,
      'set' => $set,
      'transit' => $transit,
      'transitAlt' => $transit === null ? null : $this->apparentAltitude($id, $transit),
      'alwaysAbove' => $above,
      'alwaysBelow' => $below,
    ];
  }

  private function sunDay(float $start, float $end, int $samples, DateTimeZone $tz): array {
    $out = [];
    foreach (['civil' => -6, 'nautical' => -12, 'astronomical' => -18] as $name => $h0) {
      $f = [];
      for ($k = 0; $k < $samples; $k++) {
        $f[] = $this->riseFunction('sun', min($start + $k * self::STEP_SEC, $end), $h0);
      }
      [$dawn, $dusk] = $this->crossings($f, $start, $end, fn ($t) => $this->riseFunction('sun', $t, $h0), true);
      $out[$name] = ['dawn' => Time::obj($dawn, $tz), 'dusk' => Time::obj($dusk, $tz)];
    }
    return $out;
  }

  // first upward and first downward zero crossing (or last downward when $lastDown)
  private function crossings(array $f, float $start, float $end, callable $fn, bool $lastDown = false): array {
    $up = null;
    $down = null;
    $n = count($f);
    for ($k = 1; $k < $n; $k++) {
      $a = $start + ($k - 1) * self::STEP_SEC;
      $b = min($start + $k * self::STEP_SEC, $end);
      if ($up === null && $f[$k - 1] < 0 && $f[$k] >= 0) {
        $up = self::bisect($fn, $a, $b);
      }
      if (($down === null || $lastDown) && $f[$k - 1] >= 0 && $f[$k] < 0) {
        $down = self::bisect($fn, $a, $b);
      }
    }
    return [$up, $down];
  }

  // root of a monotonic-in-interval function, to about one second
  private static function bisect(callable $fn, float $a, float $b): float {
    $fa = $fn($a);
    for ($i = 0; $i < 11; $i++) {
      $m = ($a + $b) / 2;
      $fm = $fn($m);
      if (($fa < 0) === ($fm < 0)) {
        $a = $m;
        $fa = $fm;
      } else {
        $b = $m;
      }
    }
    return ($a + $b) / 2;
  }

  private static function helio(array $h): array {
    return ['lon' => self::deg($h['lon']), 'lat' => self::deg($h['lat']), 'r' => round($h['r'], 6)];
  }

  private static function deg(float $rad): float {
    return round(rad2deg($rad), 4);
  }
}

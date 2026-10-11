<?php
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/Ephemeris.php';
require_once __DIR__ . '/Observer.php';
require_once __DIR__ . '/Solver.php';
require_once __DIR__ . '/astro/AstroEclipse.php';

// solar and lunar eclipses (Meeus ch. 54) plus local circumstances for an observer
final class Eclipses {
  // astronomia eclipse TYPE values
  private const PARTIAL = 1;
  private const ANNULAR = 2;
  private const HYBRID = 3;
  private const PENUMBRAL = 4;
  private const UMBRAL = 5;
  private const TOTAL = 6;
  private const SUN_RADIUS_KM = 696000.0;
  private const LOCAL_WINDOW_DAYS = 4 / 24;
  private const LOCAL_STEP_DAYS = 4 / 1440;

  public static function next(float $refJde, int $count, Observer $obs, DateTimeZone $tz): array {
    $solar = [];
    $lunar = [];
    $k = (int)floor((Time::decimalYear($refJde) - 2000) * 12.3685) - 1;
    $limit = $k + 12.3685 * 20;
    for (; $k < $limit && (count($solar) < $count || count($lunar) < $count); $k++) {
      if (count($solar) < $count) {
        $s = AstroEclipse::solar(2000 + $k / 12.3685);
        if ($s !== null && ($s['type'] ?? 0) !== 0 && $s['jdeMax'] + 0.15 >= $refJde) {
          $solar[] = self::solarItem($s, $obs, $tz);
        }
      }
      if (count($lunar) < $count) {
        $l = AstroEclipse::lunar(2000 + ($k + 0.5) / 12.3685);
        if ($l !== null && ($l['type'] ?? 0) !== 0 && $l['jdeMax'] + $l['sdPenumbral'] >= $refJde) {
          $lunar[] = self::lunarItem($l, $obs, $tz);
        }
      }
    }
    return ['solar' => $solar, 'lunar' => $lunar];
  }

  private static function solarItem(array $e, Observer $obs, DateTimeZone $tz): array {
    $gamma = $e['distance'];
    $u = $e['umbral'];
    $type = match ($e['type']) {
      self::PARTIAL => 'partial',
      self::ANNULAR => 'annular',
      self::HYBRID => 'hybrid',
      // non-central umbral eclipses are reported as total by Meeus; the umbra radius sign tells them apart
      default => (!$e['central'] && $u > 0) ? 'annular' : 'total',
    };
    if ($type === 'partial') {
      $mag = $e['magnitude'];
    } else {
      // ratio of apparent diameters seen from the point of the Earth nearest to the shadow axis
      $moon = Ephemeris::moon($e['jdeMax']);
      $sun = Ephemeris::sun($e['jdeMax']);
      $dMoon = $moon['distKm'] - Ephemeris::EARTH_RADIUS_KM * sqrt(max(0.0, 1 - $gamma * $gamma));
      $mag = asin(Ephemeris::MOON_RADIUS_KM / $dMoon) / asin(self::SUN_RADIUS_KM / ($sun['distAu'] * Ephemeris::AU_KM));
    }
    return [
      'type' => $type,
      'central' => (bool)$e['central'],
      'time' => Time::objFromJde($e['jdeMax'], $tz),
      'gamma' => round($gamma, 4),
      'magnitude' => round($mag, 4),
      'local' => self::solarLocal($e['jdeMax'], $obs, $tz),
    ];
  }

  // topocentric circumstances found numerically around the time of greatest eclipse
  private static function solarLocal(float $jdeMax, Observer $obs, DateTimeZone $tz): ?array {
    $w = self::LOCAL_WINDOW_DAYS;
    $sunNodes = [];
    foreach ([-$w, 0.0, $w] as $dt) {
      $sunNodes[] = Ephemeris::sun($jdeMax + $dt);
    }
    $state = function (float $jde) use ($obs, $sunNodes, $w, $jdeMax): array {
      // quadratic interpolation of the slowly moving Sun
      $x = ($jde - $jdeMax) / $w;
      $q = fn ($k) => $sunNodes[1][$k] + $x * ($sunNodes[2][$k] - $sunNodes[0][$k]) / 2
        + $x * $x * ($sunNodes[2][$k] - 2 * $sunNodes[1][$k] + $sunNodes[0][$k]) / 2;
      $ra0 = $sunNodes[0]['ra'];
      $sunRa = $sunNodes[1]['ra'] + $x * Solver::wrap($sunNodes[2]['ra'] - $ra0) / 2;
      $moon = Ephemeris::moon($jde);
      $gast = Ephemeris::gast(Time::jdeToJd($jde));
      [$azS, $altS] = $obs->horizontal($sunRa, $q('dec'), $q('distAu'), $gast);
      [$azM, $altM] = $obs->horizontal($moon['ra'], $moon['dec'], $moon['distAu'], $gast);
      $sep = Ephemeris::angularDistance($azS, $altS, $azM, $altM);
      $sS = asin(self::SUN_RADIUS_KM / ($q('distAu') * Ephemeris::AU_KM));
      $dMoonTopo = $moon['distKm'] - Ephemeris::EARTH_RADIUS_KM * sin($altM);
      $sM = asin(Ephemeris::MOON_RADIUS_KM / $dMoonTopo);
      return ['sep' => $sep, 'sS' => $sS, 'sM' => $sM, 'sunAltGeo' => $altS, 'sunAlt' => Observer::refract($altS)];
    };
    $mag = function (float $jde) use ($state): float {
      $s = $state($jde);
      return ($s['sS'] + $s['sM'] - $s['sep']) / (2 * $s['sS']);
    };

    $best = null;
    $bestVal = -INF;
    $vals = [];
    for ($t = $jdeMax - $w; $t <= $jdeMax + $w + 1e-9; $t += self::LOCAL_STEP_DAYS) {
      $v = $mag($t);
      $vals[] = [$t, $v];
      if ($v > $bestVal) {
        $bestVal = $v;
        $best = $t;
      }
    }
    if ($bestVal <= 0) {
      return null;
    }
    [$tMax] = Solver::minimize(fn ($t) => -$mag($t), $best - self::LOCAL_STEP_DAYS, $best + self::LOCAL_STEP_DAYS, 1e-6);
    $start = null;
    $end = null;
    $n = count($vals);
    for ($i = 1; $i < $n; $i++) {
      if ($vals[$i - 1][1] <= 0 && $vals[$i][1] > 0 && $start === null) {
        $start = Solver::root($mag, $vals[$i - 1][0], $vals[$i][0], 1e-6);
      }
      if ($vals[$i - 1][1] > 0 && $vals[$i][1] <= 0) {
        $end = Solver::root($mag, $vals[$i - 1][0], $vals[$i][0], 1e-6);
      }
    }

    $at = $state($tMax);
    $visible = false;
    $from = $start ?? $jdeMax - $w;
    $to = $end ?? $jdeMax + $w;
    for ($t = $from; $t <= $to; $t += self::LOCAL_STEP_DAYS / 2) {
      // standard altitude of the Sun's upper limb, applies to geometric altitude like sunrise/sunset
      if ($state($t)['sunAltGeo'] > deg2rad(-0.8333)) {
        $visible = true;
        break;
      }
    }
    if (!$visible && $state($to)['sunAltGeo'] > deg2rad(-0.8333)) {
      $visible = true;
    }
    return [
      'visible' => $visible,
      'maximum' => Time::objFromJde($tMax, $tz),
      'obscuration' => round(self::obscuration($at['sS'], $at['sM'], $at['sep']), 4),
      'magnitude' => round(($at['sS'] + $at['sM'] - $at['sep']) / (2 * $at['sS']), 4),
      'sunAlt' => round(rad2deg($at['sunAlt']), 2),
      'start' => Time::objFromJde($start, $tz),
      'end' => Time::objFromJde($end, $tz),
    ];
  }

  // fraction of the solar disk area covered by the lunar disk
  private static function obscuration(float $rs, float $rm, float $d): float {
    if ($d >= $rs + $rm) {
      return 0.0;
    }
    if ($d <= abs($rm - $rs)) {
      return $rm >= $rs ? 1.0 : ($rm * $rm) / ($rs * $rs);
    }
    $a1 = $rs * $rs * acos(($d * $d + $rs * $rs - $rm * $rm) / (2 * $d * $rs));
    $a2 = $rm * $rm * acos(($d * $d + $rm * $rm - $rs * $rs) / (2 * $d * $rm));
    $a3 = 0.5 * sqrt(max(0.0, (-$d + $rs + $rm) * ($d + $rs - $rm) * ($d - $rs + $rm) * ($d + $rs + $rm)));
    return ($a1 + $a2 - $a3) / (M_PI * $rs * $rs);
  }

  private static function lunarItem(array $e, Observer $obs, DateTimeZone $tz): array {
    $max = $e['jdeMax'];
    $gamma = $e['distance'];
    $u = $e['penumbral'] - 1.2848;
    $umbralMag = (1.0128 - $u - abs($gamma)) / 0.545;
    $penumbralMag = (1.5573 + $u - abs($gamma)) / 0.545;
    $sdT = $e['sdTotal'] ?? null;
    $sdP = $e['sdPartial'] ?? null;
    $sdN = $e['sdPenumbral'];
    $at = fn (?float $sd, int $sign) => $sd === null ? null : $max + $sign * $sd;
    $contacts = [
      'p1' => $at($sdN, -1), 'u1' => $at($sdP, -1), 'u2' => $at($sdT, -1),
      'u3' => $at($sdT, 1), 'u4' => $at($sdP, 1), 'p4' => $at($sdN, 1),
    ];

    // visibility of the umbral phase (penumbral phase for penumbral eclipses)
    $from = $contacts['u1'] ?? $contacts['p1'];
    $to = $contacts['u4'] ?? $contacts['p4'];
    $above = 0;
    $total = 0;
    for ($t = $from; $t <= $to + 1e-9; $t += 5 / 1440) {
      $moon = Ephemeris::moon($t);
      [, $alt] = $obs->horizontal($moon['ra'], $moon['dec'], $moon['distAu'], Ephemeris::gast(Time::jdeToJd($t)));
      $total++;
      if (Observer::refract($alt) > 0) {
        $above++;
      }
    }
    $moon = Ephemeris::moon($max);
    [, $altMax] = $obs->horizontal($moon['ra'], $moon['dec'], $moon['distAu'], Ephemeris::gast(Time::jdeToJd($max)));

    return [
      'type' => match ($e['type']) {
        self::TOTAL => 'total',
        self::UMBRAL => 'partial',
        default => 'penumbral',
      },
      'time' => Time::objFromJde($max, $tz),
      'gamma' => round($gamma, 4),
      'magnitude' => round($umbralMag, 4),
      'penumbralMagnitude' => round($penumbralMag, 4),
      'contacts' => array_map(fn ($j) => Time::objFromJde($j, $tz), $contacts),
      'local' => [
        'moonAltAtMax' => round(rad2deg(Observer::refract($altMax)), 2),
        'visibility' => $above === 0 ? 'none' : ($above === $total ? 'full' : 'partial'),
      ],
    ];
  }
}

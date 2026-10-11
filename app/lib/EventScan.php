<?php
require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/Ephemeris.php';
require_once __DIR__ . '/Solver.php';

// location independent planet events found by a daily scan and refined with the full ephemeris;
// results are cached per week so stepping through days reuses one scan
final class EventScan {
  private const CACHE_VERSION = 2;
  private const BUCKET_DAYS = 7;
  private const PLANET_DAYS = 366 + self::BUCKET_DAYS + 1;
  private const MOON_DAYS = 31 + self::BUCKET_DAYS + 1;
  private const MOON_STEP_DAYS = 1 / 12;
  private const PLANET_CONJ_MAX = 5.0;
  private const MOON_CONJ_MAX = 3.0;
  private const INNER = ['mercury', 'venus'];

  // ['planetEvents' => [...], 'conjunctions' => [...]] with times as JDE, angles in degrees
  public static function forDay(float $unix): array {
    $dayStart = floor($unix / 86400 / self::BUCKET_DAYS) * self::BUCKET_DAYS * 86400;
    $dir = AppConfig::cacheDir();
    $file = $dir === null ? null : sprintf('%s/scan-v%d-%s.php', $dir, self::CACHE_VERSION, gmdate('Y-m-d', (int)$dayStart));
    if ($file !== null && is_file($file)) {
      $cached = include $file;
      if (is_array($cached)) {
        return $cached;
      }
    }
    $result = self::scan(Time::jdToJde(Time::unixToJd($dayStart - 86400)));
    if ($file !== null) {
      $tmp = $file . '.' . getmypid() . '.tmp';
      if (file_put_contents($tmp, '<?php return ' . var_export($result, true) . ";\n") !== false) {
        rename($tmp, $file);
      } else {
        error_log('solar: cannot write cache ' . $file);
      }
    }
    return $result;
  }

  private static function scan(float $jde0): array {
    $days = [];
    for ($d = 0; $d <= self::PLANET_DAYS; $d++) {
      $jde = $jde0 + $d;
      $e = Ephemeris::vsop('earth')->position($jde);
      $row = ['sun' => [$e->lon + M_PI, -$e->lat, $e->range]];
      foreach (Ephemeris::PLANETS as $p) {
        $row[$p] = Ephemeris::planetFast($p, $jde, $e);
      }
      $days[] = $row;
    }

    $events = array_merge(self::sunEvents($days, $jde0), self::innerElongations($days, $jde0));
    usort($events, fn ($a, $b) => $a['jde'] <=> $b['jde']);
    $conj = array_merge(self::planetConjunctions($days, $jde0), self::moonConjunctions($days, $jde0));
    usort($conj, fn ($a, $b) => $a['jde'] <=> $b['jde']);
    return ['planetEvents' => $events, 'conjunctions' => $conj];
  }

  // conjunctions with the Sun and oppositions: apparent ecliptic longitude difference 0 or 180 degrees
  private static function sunEvents(array $days, float $jde0): array {
    $out = [];
    $n = count($days);
    foreach (Ephemeris::PLANETS as $p) {
      $inner = in_array($p, self::INNER, true);
      $targets = $inner ? [0.0] : [0.0, M_PI];
      foreach ($targets as $target) {
        for ($d = 1; $d < $n; $d++) {
          $a = Solver::wrap($days[$d - 1][$p][0] - $days[$d - 1]['sun'][0] - $target);
          $b = Solver::wrap($days[$d][$p][0] - $days[$d]['sun'][0] - $target);
          if (($a < 0) === ($b < 0) || abs($a) > M_PI / 2 || abs($b) > M_PI / 2) {
            continue;
          }
          $f = fn ($jde) => Solver::wrap(Ephemeris::planet($p, $jde)['lon'] - Ephemeris::sun($jde)['lon'] - $target);
          $jde = Solver::root($f, $jde0 + $d - 1, $jde0 + $d, 1e-5);
          if ($target != 0.0) {
            $type = 'opposition';
          } else if ($inner) {
            $type = $days[$d][$p][2] < $days[$d]['sun'][2] ? 'inferior_conjunction' : 'superior_conjunction';
          } else {
            $type = 'conjunction';
          }
          $out[] = ['planet' => $p, 'type' => $type, 'jde' => $jde, 'elongation' => null];
        }
      }
    }
    return $out;
  }

  private static function innerElongations(array $days, float $jde0): array {
    $out = [];
    $n = count($days);
    foreach (self::INNER as $p) {
      $psi = [];
      foreach ($days as $row) {
        $psi[] = self::sep($row[$p], $row['sun']);
      }
      for ($d = 1; $d < $n - 1; $d++) {
        if (!($psi[$d] > $psi[$d - 1] && $psi[$d] >= $psi[$d + 1])) {
          continue;
        }
        $f = function ($jde) use ($p) {
          $pp = Ephemeris::planet($p, $jde);
          $s = Ephemeris::sun($jde);
          return -Ephemeris::angularDistance($pp['ra'], $pp['dec'], $s['ra'], $s['dec']);
        };
        [$jde, $neg] = Solver::minimize($f, $jde0 + $d - 1, $jde0 + $d + 1, 1e-4);
        $east = Solver::wrap($days[$d][$p][0] - $days[$d]['sun'][0]) > 0;
        $out[] = [
          'planet' => $p,
          'type' => $east ? 'greatest_elongation_east' : 'greatest_elongation_west',
          'jde' => $jde,
          'elongation' => round(rad2deg(-$neg), 2),
        ];
      }
    }
    return $out;
  }

  // geocentric close approaches between planets (local minima of separation)
  private static function planetConjunctions(array $days, float $jde0): array {
    $out = [];
    $n = count($days);
    $ps = Ephemeris::PLANETS;
    for ($i = 0; $i < count($ps); $i++) {
      for ($j = $i + 1; $j < count($ps); $j++) {
        $a = $ps[$i];
        $b = $ps[$j];
        $s = [];
        foreach ($days as $row) {
          $s[] = self::sep($row[$a], $row[$b]);
        }
        for ($d = 1; $d < $n - 1; $d++) {
          if (!($s[$d] < $s[$d - 1] && $s[$d] <= $s[$d + 1]) || rad2deg($s[$d]) > self::PLANET_CONJ_MAX + 0.5) {
            continue;
          }
          $f = function ($jde) use ($a, $b) {
            $pa = Ephemeris::planet($a, $jde);
            $pb = Ephemeris::planet($b, $jde);
            return Ephemeris::angularDistance($pa['ra'], $pa['dec'], $pb['ra'], $pb['dec']);
          };
          [$jde, $sep] = Solver::minimize($f, $jde0 + $d - 1, $jde0 + $d + 1, 1e-4);
          if (rad2deg($sep) <= self::PLANET_CONJ_MAX) {
            $out[] = ['a' => $a, 'b' => $b, 'jde' => $jde, 'separation' => round(rad2deg($sep), 3)];
          }
        }
      }
    }
    return $out;
  }

  private static function moonConjunctions(array $days, float $jde0): array {
    $out = [];
    $steps = (int)round(self::MOON_DAYS / self::MOON_STEP_DAYS);
    $moon = [];
    for ($k = 0; $k <= $steps; $k++) {
      $m = Ephemeris::moon($jde0 + $k * self::MOON_STEP_DAYS);
      $moon[] = [$m['lon'], $m['lat']];
    }
    foreach (Ephemeris::PLANETS as $p) {
      $s = [];
      for ($k = 0; $k <= $steps; $k++) {
        $x = $k * self::MOON_STEP_DAYS;
        $d = (int)floor($x);
        $fr = $x - $d;
        $p0 = $days[$d][$p];
        $p1 = $days[$d + 1][$p];
        $lon = $p0[0] + $fr * Solver::wrap($p1[0] - $p0[0]);
        $lat = $p0[1] + $fr * ($p1[1] - $p0[1]);
        $s[] = self::sep($moon[$k], [$lon, $lat]);
      }
      for ($k = 1; $k < $steps; $k++) {
        if (!($s[$k] < $s[$k - 1] && $s[$k] <= $s[$k + 1]) || rad2deg($s[$k]) > self::MOON_CONJ_MAX + 1) {
          continue;
        }
        $f = function ($jde) use ($p) {
          $m = Ephemeris::moon($jde);
          $pp = Ephemeris::planet($p, $jde);
          return Ephemeris::angularDistance($m['ra'], $m['dec'], $pp['ra'], $pp['dec']);
        };
        $t = $jde0 + $k * self::MOON_STEP_DAYS;
        [$jde, $sep] = Solver::minimize($f, $t - self::MOON_STEP_DAYS, $t + self::MOON_STEP_DAYS, 1e-4);
        if (rad2deg($sep) <= self::MOON_CONJ_MAX) {
          $out[] = ['a' => 'moon', 'b' => $p, 'jde' => $jde, 'separation' => round(rad2deg($sep), 3)];
        }
      }
    }
    return $out;
  }

  // angular distance between two ecliptic [lon, lat] points
  private static function sep(array $a, array $b): float {
    return Ephemeris::angularDistance($a[0], $a[1], $b[0], $b[1]);
  }
}

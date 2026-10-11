<?php
// port of astronomia deltat.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroInterpolation.php';
require_once __DIR__ . '/AstroData.php';

final class AstroDeltaT {
  private static $monthOfYearData = [
      [0, 0, 0.08493150684921602, 0.16164383561635987, 0.24657534246580326, 0.3287671232876619, 0.4136986301368779, 0.4958904109589639, 0.5808219178081799, 0.6657534246576233, 0.747945205479482, 0.832876712328698, 0.915068493150784],
      [0, 0, 0.08743169398917416, 0.1639344262296163, 0.24863387978143692, 0.3306010928961314, 0.4153005464481794, 0.49726775956287383, 0.5819672131146945, 0.6666666666667425, 0.7486338797814369, 0.8333333333332575, 0.9153005464481794]
    ];

  private static function LeapYearGregorian($y) {
    return (fmod($y, 4) == 0 && fmod($y, 100) != 0) || fmod($y, 400) == 0;
  }

  public static function deltaT($dyear) {
    $deltat = AstroData::get('deltat');
    if ($deltat === null) {
      return null;
    }
    if ($dyear < -500) {
      $dT = AstroBase::horner(($dyear - 1820) * 0.01, -20, 0, 32);
    } elseif ($dyear < 500) {
      $dT = AstroBase::horner($dyear * 0.01,
        10583.6, -1014.41, 33.78311, -5.952053, -0.1798452, 0.022174192, 0.0090316521
      );
    } elseif ($dyear < 1600) {
      $dT = AstroBase::horner(($dyear - 1000) * 0.01,
        1574.2, -556.01, 71.23472, 0.319781, -0.8503463, -0.005050998, 0.0083572073
      );
    } elseif ($dyear < $deltat['historic']['first']) {
      $dT = AstroBase::horner(($dyear - 1600), 120, -0.9808, -0.01532, 1 / 7129);
    } elseif ($dyear < $deltat['data']['first']) {
      $dT = self::interpolate($dyear, $deltat['historic']);
    } elseif ($dyear < $deltat['data']['last'] - 0.25) {
      $dT = self::interpolateData($dyear, $deltat['data']);
    } elseif ($dyear < $deltat['prediction']['last']) {
      $dT = self::interpolate($dyear, $deltat['prediction']);
    } elseif ($dyear < 2050) {
      $dT = AstroBase::horner(($dyear - 2000) / 100, 62.92, 32.217, 55.89);
    } elseif ($dyear < 2150) {
      $dT = AstroBase::horner(($dyear - 1820) / 100, -205.72, 56.28, 32);
    } else {
      $u = ($dyear - 1820) / 100;
      $dT = -20 + 32 * $u * $u;
    }
    return $dT;
  }

  private static function interpolate($dyear, $data) {
    $d3 = AstroInterpolation::len3ForInterpolateX($dyear, $data['first'], $data['last'], $data['table']);
    if ($d3 === null) {
      return null;
    }
    return $d3->interpolateX($dyear);
  }

  private static function interpolateData($dyear, $data) {
    [$fyear, $fmonth] = $data['firstYM'];
    $m = self::monthOfYear($dyear);
    $pos = 12 * ($m['year'] - $fyear) + ($m['month'] - $fmonth);
    $table = array_slice($data['table'], $pos, 3);
    $d3 = AstroLen3::make($m['first'], $m['last'], $table);
    if ($d3 === null) {
      return null;
    }
    return $d3->interpolateX($dyear);
  }

  private static function monthOfYear($dyear) {
    $year = (int) $dyear;
    $f = $dyear - $year;
    $d = self::LeapYearGregorian($year) ? 1 : 0;
    $data = self::$monthOfYearData[$d];

    $month = 12;
    while ($month > 0 && $data[$month] > $f) {
      $month--;
    }
    $first = $year + $data[$month];
    $last = $month < 11 ? $year + $data[$month + 2] : $year + 1 + $data[($month + 2) % 12];
    return ['year' => $year, 'month' => $month, 'first' => $first, 'last' => $last];
  }
}

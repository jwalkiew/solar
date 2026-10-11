<?php
// port of astronomia solstice.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroSolar.php';

final class AstroSolstice {
  const D2R = M_PI / 180;

  // table 27.a - for years from -1000 to +1000
  private const mc0 = [1721139.29189, 365242.13740, 0.06134, 0.00111, -0.00071];
  private const jc0 = [1721233.25401, 365241.72562, -0.05323, 0.00907, 0.00025];
  private const sc0 = [1721325.70455, 365242.49558, -0.11677, -0.00297, 0.00074];
  private const dc0 = [1721414.39987, 365242.88257, -0.00769, -0.00933, -0.00006];

  // table 27.b - for years from +1000 to +3000
  private const mc2 = [2451623.80984, 365242.37404, 0.05169, -0.00411, -0.00057];
  private const jc2 = [2451716.56767, 365241.62603, 0.00325, 0.00888, -0.00030];
  private const sc2 = [2451810.21715, 365242.01767, -0.11575, 0.00337, 0.00078];
  private const dc2 = [2451900.05952, 365242.74049, -0.06223, -0.00823, 0.00032];

  // table 27.c, columns: a, b, c
  private static $terms = [
      [485, 324.96, 1934.136],
      [203, 337.23, 32964.467],
      [199, 342.08, 20.186],
      [182, 27.85, 445267.112],
      [156, 73.14, 45036.886],
      [136, 171.52, 22518.443],
      [77, 222.54, 65928.934],
      [74, 296.72, 3034.906],
      [70, 243.58, 9037.513],
      [58, 119.81, 33718.147],
      [52, 297.17, 150.678],
      [50, 21.02, 2281.226],
      [45, 247.54, 29929.562],
      [44, 325.15, 31555.956],
      [29, 60.93, 4443.417],
      [18, 155.12, 67555.328],
      [17, 288.79, 4562.452],
      [16, 198.04, 62894.029],
      [14, 199.76, 31436.921],
      [12, 95.39, 14577.848],
      [12, 287.11, 31931.756],
      [12, 320.81, 34777.259],
      [9, 227.73, 1222.114],
      [8, 15.45, 16859.074]
    ];

  public static function march($y) {
    if ($y < 1000) {
      return self::eq($y, self::mc0);
    }
    return self::eq($y - 2000, self::mc2);
  }

  public static function june($y) {
    if ($y < 1000) {
      return self::eq($y, self::jc0);
    }
    return self::eq($y - 2000, self::jc2);
  }

  public static function september($y) {
    if ($y < 1000) {
      return self::eq($y, self::sc0);
    }
    return self::eq($y - 2000, self::sc2);
  }

  public static function december($y) {
    if ($y < 1000) {
      return self::eq($y, self::dc0);
    }
    return self::eq($y - 2000, self::dc2);
  }

  private static function eq($y, $c) {
    $D2R = self::D2R;
    $J0 = AstroBase::horner($y * 0.001, $c);
    $T = AstroBase::J2000Century($J0);
    $W = 35999.373 * $D2R * $T - 2.47 * $D2R;
    $dLambda = 1 + 0.0334 * cos($W) + 0.0007 * cos(2 * $W);
    $S = 0;
    for ($i = count(self::$terms) - 1; $i >= 0; $i--) {
      $t = self::$terms[$i];
      $S += $t[0] * cos(($t[1] + $t[2] * $T) * $D2R);
    }
    return $J0 + fdiv(0.00001 * $S, $dLambda);
  }

  public static function march2($year, $planet) {
    return self::longitude($year, $planet, 0);
  }

  public static function june2($year, $planet) {
    return self::longitude($year, $planet, M_PI / 2);
  }

  public static function september2($year, $planet) {
    return self::longitude($year, $planet, M_PI);
  }

  public static function december2($year, $planet) {
    return self::longitude($year, $planet, M_PI * 3 / 2);
  }

  public static function longitude($year, $planet, $lon) {
    if ($year < 1000) {
      $ct = [self::mc0, self::jc0, self::sc0, self::dc0];
    } else {
      $ct = [self::mc2, self::jc2, self::sc2, self::dc2];
      $year -= 2000;
    }

    $lon = fmod($lon, M_PI * 2);

    if ($lon < M_PI / 2) {
      $c = $ct[0];
    } elseif ($lon < M_PI) {
      $c = $ct[1];
    } elseif ($lon < M_PI * 3 / 2) {
      $c = $ct[2];
    } else {
      $c = $ct[3];
    }

    return self::eq2($year, $planet, $lon, $c);
  }

  private static function eq2($year, $planet, $lon, $c) {
    $J0 = AstroBase::horner($year * 0.001, $c);

    for (;;) {
      $a = AstroSolar::apparentVSOP87($planet, $J0);
      $cc = 58 * sin($lon - $a->lon);
      $J0 += $cc;
      if (abs($cc) < 0.000005) {
        break;
      }
    }

    return $J0;
  }
}

<?php
// port of astronomia kepler.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroIterate.php';

final class AstroKepler {
  public static function trueAnomaly($E, $e) {
    return 2 * atan(sqrt(fdiv(1 + $e, 1 - $e)) * tan($E * 0.5));
  }

  // js default export alias, backwards-compatibility
  public static function true($E, $e) {
    return self::trueAnomaly($E, $e);
  }

  public static function radius($E, $e, $a) {
    return $a * (1 - $e * cos($E));
  }

  public static function kepler1($e, $m, $places) {
    $f = function ($E0) use ($e, $m) {
      return $m + $e * sin($E0);
    };
    return AstroIterate::decimalPlaces($f, $m, $places, $places * 5);
  }

  public static function kepler2($e, $m, $places) {
    $f = function ($E0) use ($e, $m) {
      [$se, $ce] = AstroBase::sincos($E0);
      return $E0 + fdiv($m + $e * $se - $E0, 1 - $e * $ce);
    };
    return AstroIterate::decimalPlaces($f, $m, $places, $places);
  }

  public static function kepler2a($e, $m, $places) {
    $f = function ($E0) use ($e, $m) {
      [$se, $ce] = AstroBase::sincos($E0);
      return $E0 + asin(sin(fdiv($m + $e * $se - $E0, 1 - $e * $ce)));
    };
    return AstroIterate::decimalPlaces($f, $m, $places, $places * 5);
  }

  public static function kepler2b($e, $m, $places) {
    $f = function ($E0) use ($e, $m) {
      [$se, $ce] = AstroBase::sincos($E0);
      $d = fdiv($m + $e * $se - $E0, 1 - $e * $ce);
      if ($d > 0.5) {
        $d = 0.5;
      } elseif ($d < -0.5) {
        $d = -0.5;
      }
      return $E0 + $d;
    };
    return AstroIterate::decimalPlaces($f, $m, $places, $places);
  }

  public static function kepler3($e, $m) {
    $m = AstroBase::pmod($m, 2 * M_PI);
    $f = 1;
    if ($m > M_PI) {
      $f = -1;
      $m = 2 * M_PI - $m;
    }
    $E0 = M_PI * 0.5;
    $d = M_PI * 0.25;
    for ($i = 0; $i < 53; $i++) {
      $M1 = $E0 - $e * sin($E0);
      if ($m - $M1 < 0) {
        $E0 -= $d;
      } else {
        $E0 += $d;
      }
      $d *= 0.5;
    }
    if ($f < 0) {
      return -$E0;
    }
    return $E0;
  }

  public static function kepler4($e, $m) {
    [$sm, $cm] = AstroBase::sincos($m);
    return atan2($sm, $cm - $e);
  }
}

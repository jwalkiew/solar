<?php
// port of astronomia eqtime.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroSolar.php';

final class AstroEqTime {
  public static function e($jde, $earth) {
    $tau = AstroBase::J2000Century($jde) * 0.1;
    $L0 = self::l0($tau);
    $c = AstroSolar::trueVSOP87($earth, $jde);
    $lon = $c->lon;
    $lat = $c->lat;
    $range = $c->range;
    [$dPsi, $dEps] = AstroNutation::nutation($jde);
    $a = -20.4898 / 3600 * M_PI / 180 / $range;
    $lambda = $lon + $dPsi + $a;
    $eps = AstroNutation::meanObliquity($jde) + $dEps;
    $eq = (new AstroEcliptic($lambda, $lat))->toEquatorial($eps);
    $E = $L0 - 0.0057183 * M_PI / 180 - $eq->ra + $dPsi * cos($eps);
    return AstroBase::pmod($E + M_PI, 2 * M_PI) - M_PI;
  }

  private static function l0($tau) {
    return AstroBase::horner($tau, 280.4664567, 360007.6982779, 0.03032028,
      1.0 / 49931, -1.0 / 15300, -1.0 / 2000000) * M_PI / 180;
  }

  public static function eSmart($jde) {
    $eps = AstroNutation::meanObliquity($jde);
    $t = tan($eps * 0.5);
    $y = $t * $t;
    $T = AstroBase::J2000Century($jde);
    $L0 = self::l0($T * 0.1);
    $e = AstroSolar::eccentricity($T);
    $M = AstroSolar::meanAnomaly($T);
    [$sin2L0, $cos2L0] = AstroBase::sincos(2 * $L0);
    $sinM = sin($M);
    return $y * $sin2L0 - 2 * $e * $sinM + 4 * $e * $y * $sinM * $cos2L0 -
      $y * $y * $sin2L0 * $cos2L0 - 1.25 * $e * $e * sin(2 * $M);
  }
}

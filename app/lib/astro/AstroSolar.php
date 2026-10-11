<?php
// port of astronomia solar.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroPlanetPosition.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroNutation.php';

final class AstroSolar {
  public static function trueLongitude($T) {
    $L0 = AstroBase::horner($T, 280.46646, 36000.76983, 0.0003032) * M_PI / 180;
    $m = self::meanAnomaly($T);
    $C = (AstroBase::horner($T, 1.914602, -0.004817, -0.000014) *
      sin($m) +
      (0.019993 - 0.000101 * $T) * sin(2 * $m) +
      0.000289 * sin(3 * $m)) * M_PI / 180;
    $lon = AstroBase::pmod($L0 + $C, 2 * M_PI);
    $ano = AstroBase::pmod($m + $C, 2 * M_PI);
    return ['lon' => $lon, 'ano' => $ano];
  }

  // js default export alias, backwards-compatibility
  public static function true($T) {
    return self::trueLongitude($T);
  }

  public static function meanAnomaly($T) {
    return AstroBase::horner($T, 357.52911, 35999.05029, -0.0001537) * M_PI / 180;
  }

  public static function eccentricity($T) {
    return AstroBase::horner($T, 0.016708634, -0.000042037, -0.0000001267);
  }

  public static function radius($T) {
    ['lon' => $lon, 'ano' => $ano] = self::trueLongitude($T);
    $e = self::eccentricity($T);
    return 1.000001018 * (1 - $e * $e) / (1 + $e * cos($ano));
  }

  public static function apparentLongitude($T) {
    $omega = self::node($T);
    ['lon' => $lon, 'ano' => $ano] = self::trueLongitude($T);
    return $lon - 0.00569 * M_PI / 180 - 0.00478 * M_PI / 180 * sin($omega);
  }

  private static function node($T) {
    return 125.04 * M_PI / 180 - 1934.136 * M_PI / 180 * $T;
  }

  public static function true2000($T) {
    ['lon' => $lon, 'ano' => $ano] = self::trueLongitude($T);
    $lon -= 0.01397 * M_PI / 180 * $T * 100;
    return ['lon' => $lon, 'ano' => $ano];
  }

  public static function trueEquatorial($jde) {
    ['lon' => $lon, 'ano' => $ano] = self::trueLongitude(AstroBase::J2000Century($jde));
    $eps = AstroNutation::meanObliquity($jde);
    [$ss, $cs] = AstroBase::sincos($lon);
    [$sEps, $cEps] = AstroBase::sincos($eps);
    $ra = atan2($cEps * $ss, $cs);
    $dec = $sEps * $ss;
    return new AstroBaseCoord($ra, $dec);
  }

  public static function apparentEquatorial($jde) {
    $T = AstroBase::J2000Century($jde);
    $lambda = self::apparentLongitude($T);
    $eps = AstroNutation::meanObliquity($jde);
    [$sLambda, $cLambda] = AstroBase::sincos($lambda);
    [$sEps, $cEps] = AstroBase::sincos($eps + 0.00256 * M_PI / 180 * cos(self::node($T)));
    $ra = atan2($cEps * $sLambda, $cLambda);
    $dec = asin($sEps * $sLambda);
    return new AstroBaseCoord($ra, $dec);
  }

  public static function trueVSOP87($planet, $jde) {
    $p = $planet->position($jde);
    $lon = $p->lon;
    $lat = $p->lat;
    $range = $p->range;
    $s = $lon + M_PI;
    $lambdap = AstroBase::horner(AstroBase::J2000Century($jde),
      $s, -1.397 * M_PI / 180, -0.00031 * M_PI / 180);
    [$sLambdap, $cLambdap] = AstroBase::sincos($lambdap);
    $dBeta = 0.03916 / 3600 * M_PI / 180 * ($cLambdap - $sLambdap);
    $lon = AstroBase::pmod($s - 0.09033 / 3600 * M_PI / 180, 2 * M_PI);
    $lat = $dBeta - $lat;
    return new AstroBaseCoord($lon, $lat, $range);
  }

  public static function apparentVSOP87($planet, $jde) {
    $c = self::trueVSOP87($planet, $jde);
    $lon = $c->lon;
    $lat = $c->lat;
    $range = $c->range;
    $dPsi = AstroNutation::nutation($jde)[0];
    $a = self::aberration($range);
    $lon = $lon + $dPsi + $a;
    return new AstroBaseCoord($lon, $lat, $range);
  }

  public static function apparentEquatorialVSOP87($planet, $jde) {
    $c = self::trueVSOP87($planet, $jde);
    $lon = $c->lon;
    $lat = $c->lat;
    $range = $c->range;
    [$dPsi, $dEps] = AstroNutation::nutation($jde);
    $a = self::aberration($range);
    $lambda = $lon + $dPsi + $a;
    $eps = AstroNutation::meanObliquity($jde) + $dEps;
    $equ = (new AstroEcliptic($lambda, $lat))->toEquatorial($eps);
    return new AstroBaseCoord($equ->ra, $equ->dec, $range);
  }

  public static function aberration($range) {
    return -20.4898 / 3600 * M_PI / 180 / $range;
  }
}

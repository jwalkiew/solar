<?php
// port of astronomia solarxyz.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroSolar.php';

final class AstroSolarXyz {
  private const zetat = [2306.2181, 0.30188, 0.017998];
  private const zt = [2306.2181, 1.09468, 0.018203];
  private const thetat = [2004.3109, -0.42665, -0.041833];

  public static function position($earth, $jde) {
    $c = AstroSolar::trueVSOP87($earth, $jde);
    $lon = $c->lon;
    $lat = $c->lat;
    $range = $c->range;
    [$sEps, $cEps] = AstroBase::sincos(AstroNutation::meanObliquity($jde));
    [$ss, $cs] = AstroBase::sincos($lon);
    $sBeta = sin($lat);
    $x = $range * $cs;
    $y = $range * ($ss * $cEps - $sBeta * $sEps);
    $z = $range * ($ss * $sEps + $sBeta * $cEps);
    return ['x' => $x, 'y' => $y, 'z' => $z];
  }

  public static function longitudeJ2000($earth, $jde) {
    $lon = $earth->position2000($jde)->lon;
    return AstroBase::pmod($lon + M_PI - 0.09033 / 3600 * M_PI / 180, 2 * M_PI);
  }

  public static function positionJ2000($earth, $jde) {
    ['x' => $x, 'y' => $y, 'z' => $z] = self::xyz($earth, $jde);
    return [
      'x' => $x + 0.00000044036 * $y - 0.000000190919 * $z,
      'y' => -0.000000479966 * $x + 0.917482137087 * $y - 0.397776982902 * $z,
      'z' => 0.397776982902 * $y + 0.917482137087 * $z
    ];
  }

  public static function xyz($earth, $jde) {
    $c = $earth->position2000($jde);
    $lon = $c->lon;
    $lat = $c->lat;
    $range = $c->range;
    $s = $lon + M_PI;
    $beta = -$lat;
    [$ss, $cs] = AstroBase::sincos($s);
    [$sBeta, $cBeta] = AstroBase::sincos($beta);
    $x = $range * $cBeta * $cs;
    $y = $range * $cBeta * $ss;
    $z = $range * $sBeta;
    return ['x' => $x, 'y' => $y, 'z' => $z];
  }

  public static function positionB1950($earth, $jde) {
    ['x' => $x, 'y' => $y, 'z' => $z] = self::xyz($earth, $jde);
    return [
      'x' => 0.999925702634 * $x + 0.012189716217 * $y + 0.000011134016 * $z,
      'y' => -0.011179418036 * $x + 0.917413998946 * $y - 0.397777041885 * $z,
      'z' => -0.004859003787 * $x + 0.397747363646 * $y + 0.917482111428 * $z
    ];
  }

  public static function positionEquinox($earth, $jde, $epoch) {
    $xyz = self::positionJ2000($earth, $jde);
    $x0 = $xyz['x'];
    $y0 = $xyz['y'];
    $z0 = $xyz['z'];
    $t = ($epoch - 2000) * 0.01;
    $zeta = AstroBase::horner($t, self::zetat) * $t * M_PI / 180 / 3600;
    $z = AstroBase::horner($t, self::zt) * $t * M_PI / 180 / 3600;
    $theta = AstroBase::horner($t, self::thetat) * $t * M_PI / 180 / 3600;
    [$sZeta, $cZeta] = AstroBase::sincos($zeta);
    [$sz, $cz] = AstroBase::sincos($z);
    [$sTheta, $cTheta] = AstroBase::sincos($theta);
    $xx = $cZeta * $cz * $cTheta - $sZeta * $sz;
    $xy = $sZeta * $cz + $cZeta * $sz * $cTheta;
    $xz = $cZeta * $sTheta;
    $yx = -$cZeta * $sz - $sZeta * $cz * $cTheta;
    $yy = $cZeta * $cz - $sZeta * $sz * $cTheta;
    $yz = -$sZeta * $sTheta;
    $zx = -$cz * $sTheta;
    $zy = -$sz * $sTheta;
    $zz = $cTheta;
    return [
      'x' => $xx * $x0 + $yx * $y0 + $zx * $z0,
      'y' => $xy * $x0 + $yy * $y0 + $zy * $z0,
      'z' => $xz * $x0 + $yz * $y0 + $zz * $z0
    ];
  }
}

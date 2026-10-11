<?php
// port of astronomia base.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

final class AstroBase {
  const K = 0.01720209895;
  const AU = 149597870;
  const SOblJ2000 = 0.397777156;
  const COblJ2000 = 0.917482062;
  const JMod = 2400000.5;
  const J2000 = 2451545.0;
  const J1900 = 2415020.0;
  const B1900 = 2415020.3135;
  const B1950 = 2433282.4235;
  const JulianYear = 365.25;
  const JulianCentury = 36525;
  const BesselianYear = 365.2421988;
  const meanSiderealYear = 365.25636;
  const SmallAngle = 10 * M_PI / 180 / 60;
  public static $CosSmallAngle;

  public static function lightTime($dist) {
    return 0.0057755183 * $dist;
  }

  public static function JulianYearToJDE($jy) {
    return self::J2000 + self::JulianYear * ($jy - 2000);
  }

  public static function JDEToJulianYear($jde) {
    return 2000 + ($jde - self::J2000) / self::JulianYear;
  }

  public static function BesselianYearToJDE($by) {
    return self::B1900 + self::BesselianYear * ($by - 1900);
  }

  public static function JDEToBesselianYear($jde) {
    return 1900 + ($jde - self::B1900) / self::BesselianYear;
  }

  public static function J2000Century($jde) {
    return ($jde - self::J2000) / self::JulianCentury;
  }

  public static function illuminated($i) {
    return (1 + cos($i)) * 0.5;
  }

  public static function limb($equ, $appSun) {
    $alpha = $equ->ra;
    $delta = $equ->dec;
    $alpha0 = $appSun->ra;
    $delta0 = $appSun->dec;
    $sDelta = sin($delta);
    $cDelta = cos($delta);
    $sDelta0 = sin($delta0);
    $cDelta0 = cos($delta0);
    $sAlpha0Alpha = sin($alpha0 - $alpha);
    $cAlpha0Alpha = cos($alpha0 - $alpha);
    $chi = atan2($cDelta0 * $sAlpha0Alpha, ($sDelta0 * $cDelta - $cDelta0 * $sDelta * $cAlpha0Alpha));
    if ($chi < 0) {
      $chi += 2 * M_PI;
    }
    return $chi;
  }

  public static function pmod($x, $y) {
    $r = fmod($x, $y);
    if ($r < 0) {
      $r += $y;
    }
    return $r;
  }

  public static function horner($x, ...$c) {
    if (is_array($c[0])) {
      $c = $c[0];
    }
    $i = count($c) - 1;
    $y = $c[$i];
    while ($i > 0) {
      $i--;
      $y = $y * $x + $c[$i];
    }
    return $y;
  }

  public static function floorDiv($x, $y) {
    $q = fdiv($x, $y);
    return floor($q);
  }

  public static function cmp($a, $b) {
    if ($a < $b) {
      return -1;
    }
    if ($a > $b) {
      return 1;
    }
    return 0;
  }

  public static function sincos($eps) {
    return [sin($eps), cos($eps)];
  }

  public static function toRad($deg) {
    return (M_PI / 180.0) * $deg;
  }

  public static function toDeg($rad) {
    return (180.0 / M_PI) * $rad;
  }

  public static function modf($float) {
    $i = $float < 0 ? ceil($float) : floor($float);
    $f = abs($float - $i);
    return [$i, $f];
  }

  public static function round($float, $precision = 14) {
    return (float) sprintf('%.' . $precision . 'F', $float);
  }
}

AstroBase::$CosSmallAngle = cos(AstroBase::SmallAngle);

class AstroBaseCoord {
  public $_ra;
  public $_dec;
  public $range;
  public $elongation;

  public $ra {
    get => $this->_ra;
    set { $this->_ra = $value; }
  }

  public $dec {
    get => $this->_dec;
    set { $this->_dec = $value; }
  }

  public $lon {
    get => $this->_ra;
    set { $this->_ra = $value; }
  }

  public $lat {
    get => $this->_dec;
    set { $this->_dec = $value; }
  }

  public function __construct($ra = null, $dec = null, $range = null, $elongation = null) {
    $this->_ra = (!$ra || is_nan((float) $ra)) ? 0 : $ra;
    $this->_dec = (!$dec || is_nan((float) $dec)) ? 0 : $dec;
    $this->range = $range;
    $this->elongation = $elongation;
  }
}

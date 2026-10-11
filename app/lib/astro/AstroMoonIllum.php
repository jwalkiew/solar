<?php
// port of astronomia moonillum.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroMoonIllum {
  const D2R = M_PI / 180;

  public static function phaseAngleEquatorial($cMoon, $cSun) {
    return self::pa($cMoon->range, $cSun->range, self::cosEq($cMoon->ra, $cMoon->dec, $cSun->ra, $cSun->dec));
  }

  private static function cosEq($alpha, $delta, $alpha0, $delta0) {
    [$sDelta, $cDelta] = AstroBase::sincos($delta);
    [$sDelta0, $cDelta0] = AstroBase::sincos($delta0);
    return $sDelta0 * $sDelta + $cDelta0 * $cDelta * cos($alpha0 - $alpha);
  }

  private static function pa($Delta, $R, $cPsi) {
    $sPsi = sin(acos($cPsi));
    $i = atan(fdiv($R * $sPsi, $Delta - $R * $cPsi));
    if ($i < 0) {
      $i += M_PI;
    }
    return $i;
  }

  public static function phaseAngleEquatorial2($cMoon, $cSun) {
    return acos(-self::cosEq($cMoon->ra, $cMoon->dec, $cSun->ra, $cSun->dec));
  }

  public static function phaseAngleEcliptic($cMoon, $cSun) {
    return self::pa($cMoon->range, $cSun->range, self::cosEcl($cMoon->lon, $cMoon->lat, $cSun->lon));
  }

  private static function cosEcl($lambda, $beta, $lambda0) {
    return cos($beta) * cos($lambda - $lambda0);
  }

  public static function phaseAngleEcliptic2($cMoon, $cSun) {
    return acos(-self::cosEcl($cMoon->lon, $cMoon->lat, $cSun->lon));
  }

  public static function phaseAngle3($jde) {
    $D2R = self::D2R;
    $T = AstroBase::J2000Century($jde);
    $D = AstroBase::horner($T, 297.8501921 * $D2R, 445267.1114034 * $D2R,
      -0.0018819 * $D2R, $D2R / 545868, -$D2R / 113065000);
    $m = AstroBase::horner($T, 357.5291092 * $D2R, 35999.0502909 * $D2R,
      -0.0001536 * $D2R, $D2R / 24490000);
    $m_ = AstroBase::horner($T, 134.9633964 * $D2R, 477198.8675055 * $D2R,
      0.0087414 * $D2R, $D2R / 69699, -$D2R / 14712000);
    return M_PI - AstroBase::pmod($D, 2 * M_PI) +
      -6.289 * $D2R * sin($m_) +
      2.1 * $D2R * sin($m) +
      -1.274 * $D2R * sin(2 * $D - $m_) +
      -0.658 * $D2R * sin(2 * $D) +
      -0.214 * $D2R * sin(2 * $m_) +
      -0.11 * $D2R * sin($D);
  }
}

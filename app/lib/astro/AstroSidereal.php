<?php
// port of astronomia sidereal.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroNutation.php';

final class AstroSidereal {
  const iau82 = [24110.54841, 8640184.812866, 0.093104, -0.0000062];

  public static function JDToCFrac($jd) {
    [$j0, $f] = AstroBase::modf($jd + 0.5);
    return [AstroBase::J2000Century($j0 - 0.5), $f];
  }

  public static function mean($jd) {
    return AstroBase::pmod(self::_mean($jd), 86400);
  }

  private static function _mean($jd) {
    [$s, $f] = self::_mean0UT($jd);
    return $s + $f * 1.00273790935 * 86400;
  }

  public static function mean0UT($jd) {
    [$s] = self::_mean0UT($jd);
    return AstroBase::pmod($s, 86400);
  }

  private static function _mean0UT($jd) {
    [$cen, $f] = self::JDToCFrac($jd);
    return [AstroBase::horner($cen, ...self::iau82), $f];
  }

  public static function apparent($jd) {
    $s = self::_mean($jd);
    $n = AstroNutation::nutationInRA($jd);
    $ns = $n * 3600 * 180 / M_PI / 15;
    return AstroBase::pmod($s + $ns, 86400);
  }

  public static function apparent0UT($jd) {
    [$j0, $f] = AstroBase::modf($jd + 0.5);
    $cen = ($j0 - 0.5 - AstroBase::J2000) / 36525;
    $s = AstroBase::horner($cen, ...self::iau82) + $f * 1.00273790935 * 86400;
    $n = AstroNutation::nutationInRA($j0);
    $ns = $n * 3600 * 180 / M_PI / 15;
    return AstroBase::pmod($s + $ns, 86400);
  }
}

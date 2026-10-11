<?php
// port of astronomia semidiameter.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroParallax.php';

final class AstroSemidiameter {
  const Sun = 959.63 / 3600 * M_PI / 180;
  const Mercury = 3.36 / 3600 * M_PI / 180;
  const VenusSurface = 8.34 / 3600 * M_PI / 180;
  const VenusCloud = 8.41 / 3600 * M_PI / 180;
  const Mars = 4.68 / 3600 * M_PI / 180;
  const JupiterEquatorial = 98.44 / 3600 * M_PI / 180;
  const JupiterPolar = 92.06 / 3600 * M_PI / 180;
  const SaturnEquatorial = 82.73 / 3600 * M_PI / 180;
  const SaturnPolar = 73.82 / 3600 * M_PI / 180;
  const Uranus = 35.02 / 3600 * M_PI / 180;
  const Neptune = 33.50 / 3600 * M_PI / 180;
  const Pluto = 2.07 / 3600 * M_PI / 180;
  const Moon = 358473400 / AstroBase::AU / 3600 * M_PI / 180;

  public static function semidiameter($s0, $Delta) {
    return fdiv($s0, $Delta);
  }

  public static function saturnApparentPolar($Delta, $B) {
    $k = self::SaturnPolar / self::SaturnEquatorial;
    $k = 1 - $k * $k;
    $cB = cos($B);
    return fdiv(self::SaturnEquatorial, $Delta) * sqrt(1 - $k * $cB * $cB);
  }

  // js calls parallax.Horizontal, which does not exist (the export is "horizontal"),
  // so the js function always throws a TypeError; the port returns null for it
  public static function moonTopocentric($Delta, $delta, $H, $rhoSPhip, $rhoCPhip) {
    return null;
  }

  // same js defect as moonTopocentric: always throws in js
  public static function moonTopocentric2($Delta, $h) {
    return null;
  }

  public static function asteroidDiameter($H, $A) {
    return pow(10, 3.12 - 0.2 * $H - 0.5 * log10($A));
  }

  public static function asteroid($d, $Delta) {
    return fdiv(0.0013788 * $d, $Delta) / 3600 * M_PI / 180;
  }
}

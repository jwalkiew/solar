<?php
// port of astronomia elementequinox.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroElementEquinox {
  const S = 0.0001139788;
  const C = 0.9999999935;
  const Lp = 4.50001688 * M_PI / 180;
  const L = 5.19856209 * M_PI / 180;
  const J = 0.00651966 * M_PI / 180;

  public static function reduceB1950ToJ2000($eFrom) {
    $W = $eFrom->node - 174.298782 * M_PI / 180;
    [$si, $ci] = AstroBase::sincos($eFrom->inc);
    [$sW, $cW] = AstroBase::sincos($W);
    $A = $si * $sW;
    $B = self::C * $si * $cW - self::S * $ci;
    $eTo = new AstroElementEquinoxElements();
    $eTo->inc = asin(hypot($A, $B));
    $eTo->node = AstroBase::pmod(174.997194 * M_PI / 180 + atan2($A, $B), 2 * M_PI);
    $eTo->peri = AstroBase::pmod($eFrom->peri + atan2(-self::S * $sW, self::C * $si - self::S * $ci * $cW), 2 * M_PI);
    return $eTo;
  }

  public static function reduceB1950FK4ToJ2000FK5($eFrom) {
    $W = self::L + $eFrom->node;
    [$si, $ci] = AstroBase::sincos($eFrom->inc);
    [$sJ, $cJ] = AstroBase::sincos(self::J);
    [$sW, $cW] = AstroBase::sincos($W);
    $eTo = new AstroElementEquinoxElements();
    $eTo->inc = acos($ci * $cJ - $si * $sJ * $cW);
    $eTo->node = AstroBase::pmod(atan2($si * $sW, $ci * $sJ + $si * $cJ * $cW) - self::Lp, 2 * M_PI);
    $eTo->peri = AstroBase::pmod($eFrom->peri + atan2($sJ * $sW, $si * $cJ + $ci * $sJ * $cW), 2 * M_PI);
    return $eTo;
  }
}

class AstroElementEquinoxElements {
  public $inc;
  public $node;
  public $peri;

  public function __construct($inc = null, $node = null, $peri = null) {
    if (is_object($inc)) {
      // js reads the misspelled property "pode" here, which is undefined
      $node = $inc->pode ?? null;
      $peri = $inc->peri;
      $inc = $inc->inc;
    }
    $this->inc = $inc ?: 0;
    $this->node = $node ?: 0;
    $this->peri = $peri ?: 0;
  }
}

<?php
// port of astronomia moonnode.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroMoonNode {
  public static function ascending($year) {
    return self::node($year, 0);
  }

  public static function descending($year) {
    return self::node($year, 0.5);
  }

  private static function node($y, $h) {
    $k = ($y - 2000.05) * 13.4223;
    $k = floor($k - $h + 0.5) + $h;
    $p = M_PI / 180;
    $ck = 1 / 1342.23;
    $T = $k * $ck;
    $D = AstroBase::horner($T, 183.638 * $p, 331.73735682 * $p / $ck,
      0.0014852 * $p, 0.00000209 * $p, -0.00000001 * $p);
    $M = AstroBase::horner($T, 17.4006 * $p, 26.8203725 * $p / $ck,
      0.0001186 * $p, 0.00000006 * $p);
    $m_ = AstroBase::horner($T, 38.3776 * $p, 355.52747313 * $p / $ck,
      0.0123499 * $p, 0.000014627 * $p, -0.000000069 * $p);
    $omega = AstroBase::horner($T, 123.9767 * $p, -1.44098956 * $p / $ck,
      0.0020608 * $p, 0.00000214 * $p, -0.000000016 * $p);
    $V = AstroBase::horner($T, 299.75 * $p, 132.85 * $p, -0.009173 * $p);
    $P = $omega + 272.75 * $p - 2.3 * $p * $T;
    $E = AstroBase::horner($T, 1, -0.002516, -0.0000074);
    return AstroBase::horner($T, 2451565.1619, 27.212220817 / $ck,
      0.0002762, 0.000000021, -0.000000000088) +
      -0.4721 * sin($m_) +
      -0.1649 * sin(2 * $D) +
      -0.0868 * sin(2 * $D - $m_) +
      0.0084 * sin(2 * $D + $m_) +
      -0.0083 * sin(2 * $D - $M) * $E +
      -0.0039 * sin(2 * $D - $M - $m_) * $E +
      0.0034 * sin(2 * $m_) +
      -0.0031 * sin(2 * ($D - $m_)) +
      0.003 * sin(2 * $D + $M) * $E +
      0.0028 * sin($M - $m_) * $E +
      0.0026 * sin($M) * $E +
      0.0025 * sin(4 * $D) +
      0.0024 * sin($D) +
      0.0022 * sin($M + $m_) * $E +
      0.0017 * sin($omega) +
      0.0014 * sin(4 * $D - $m_) +
      0.0005 * sin(2 * $D + $M - $m_) * $E +
      0.0004 * sin(2 * $D - $M + $m_) * $E +
      -0.0003 * sin(2 * ($D - $M)) * $E +
      0.0003 * sin(4 * $D - $M) * $E +
      0.0003 * sin($V) +
      0.0003 * sin($P);
  }
}

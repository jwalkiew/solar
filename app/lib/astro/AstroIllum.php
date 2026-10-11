<?php
// port of astronomia illum.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroIllum {
  const D2R = M_PI / 180;

  public static function phaseAngle($r, $Delta, $R) {
    return acos(fdiv($r * $r + $Delta * $Delta - $R * $R, 2 * $r * $Delta));
  }

  public static function fraction($r, $Delta, $R) {
    $s = $r + $Delta;
    return fdiv($s * $s - $R * $R, 4 * $r * $Delta);
  }

  public static function phaseAngle2($L, $B, $R, $L0, $R0, $Delta) {
    return acos(fdiv($R - $R0 * cos($B) * cos($L - $L0), $Delta));
  }

  public static function phaseAngle3($L, $B, $x, $y, $z, $Delta) {
    [$sL, $cL] = AstroBase::sincos($L);
    [$sB, $cB] = AstroBase::sincos($B);
    return acos(fdiv($x * $cB * $cL + $y * $cB * $sL + $z * $sB, $Delta));
  }

  public static function fractionVenus($jde) {
    $D2R = self::D2R;
    $T = AstroBase::J2000Century($jde);
    $V = (261.51 + 22518.443 * $T) * $D2R;
    $M = (177.53 + 35999.05 * $T) * $D2R;
    $N = (50.42 + 58517.811 * $T) * $D2R;
    $W = $V + (1.91 * sin($M) + 0.78 * sin($N)) * $D2R;
    $Delta = sqrt(1.52321 + 1.44666 * cos($W));
    $s = 0.72333 + $Delta;
    return fdiv(($s * $s - 1) / 2.89332, $Delta);
  }

  public static function mercury($r, $Delta, $i) {
    $s = AstroBase::toDeg($i) - 50;
    return 1.16 + 5 * log10($r * $Delta) + (0.02838 + 0.0001023 * $s) * $s;
  }

  public static function venus($r, $Delta, $i) {
    $iDeg = AstroBase::toDeg($i);
    return -4 + 5 * log10($r * $Delta) + (0.01322 + 0.0000004247 * $iDeg * $iDeg) * $iDeg;
  }

  public static function mars($r, $Delta, $i) {
    return -1.3 + 5 * log10($r * $Delta) + 0.01486 * AstroBase::toDeg($i);
  }

  public static function jupiter($r, $Delta) {
    return -8.93 + 5 * log10($r * $Delta);
  }

  public static function saturn($r, $Delta, $B, $DeltaU) {
    $s = sin(abs($B));
    return -8.68 + 5 * log10($r * $Delta) + 0.044 * abs(AstroBase::toDeg($DeltaU)) - 2.6 * $s + 1.25 * $s * $s;
  }

  public static function uranus($r, $Delta) {
    return -6.85 + 5 * log10($r * $Delta);
  }

  public static function neptune($r, $Delta) {
    return -7.05 + 5 * log10($r * $Delta);
  }

  public static function mercury84($r, $Delta, $i) {
    return AstroBase::horner(AstroBase::toDeg($i), -0.42 + 5 * log10($r * $Delta),
      0.038, -0.000273, 0.000002);
  }

  public static function venus84($r, $Delta, $i) {
    return AstroBase::horner(AstroBase::toDeg($i), -4.4 + 5 * log10($r * $Delta),
      0.0009, 0.000239, -0.00000065);
  }

  public static function mars84($r, $Delta, $i) {
    return -1.52 + 5 * log10($r * $Delta) + 0.016 * AstroBase::toDeg($i);
  }

  public static function jupiter84($r, $Delta, $i) {
    return -9.4 + 5 * log10($r * $Delta) + 0.005 * AstroBase::toDeg($i);
  }

  public static function saturn84($r, $Delta, $B, $DeltaU) {
    $s = sin(abs($B));
    return -8.88 + 5 * log10($r * $Delta) + 0.044 * abs(AstroBase::toDeg($DeltaU)) - 2.6 * $s + 1.25 * $s * $s;
  }

  public static function uranus84($r, $Delta) {
    return -7.19 + 5 * log10($r * $Delta);
  }

  public static function neptune84($r, $Delta) {
    return -6.87 + 5 * log10($r * $Delta);
  }

  public static function pluto84($r, $Delta) {
    return -1 + 5 * log10($r * $Delta);
  }
}

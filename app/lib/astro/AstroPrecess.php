<?php
// port of astronomia precess.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroElementEquinox.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroSexagesimal.php';

final class AstroPrecess {
  const d = M_PI / 180;
  const s = self::d / 3600;

  public static $zetaT;
  public static $zT;
  public static $thetaT;
  public static $zetat;
  public static $zt;
  public static $thetat;
  public static $etaT;
  public static $piT;
  public static $pT;
  public static $etat;
  public static $pit;
  public static $pt;

  public static function approxAnnualPrecession($eqFrom, $epochFrom, $epochTo) {
    [$m, $na, $nd] = self::mn($epochFrom, $epochTo);
    [$sa, $ca] = AstroBase::sincos($eqFrom->ra);
    $dAlphaS = $m + $na * $sa * tan($eqFrom->dec);
    $dDeltaS = $nd * $ca;
    $ra = (new AstroHourAngle(false, 0, 0, $dAlphaS))->rad();
    $dec = (new AstroAngle(false, 0, 0, $dDeltaS))->rad();
    return ['ra' => $ra, 'dec' => $dec];
  }

  public static function mn($epochFrom, $epochTo) {
    $T = ($epochTo - $epochFrom) * 0.01;
    $m = 3.07496 + 0.00186 * $T;
    $na = 1.33621 - 0.00057 * $T;
    $nd = 20.0431 - 0.0085 * $T;
    return [$m, $na, $nd];
  }

  public static function approxPosition($eqFrom, $epochFrom, $epochTo, $mAlpha = NAN, $mDelta = NAN) {
    ['ra' => $ra, 'dec' => $dec] = self::approxAnnualPrecession($eqFrom, $epochFrom, $epochTo);
    $dy = $epochTo - $epochFrom;
    $eqTo = new AstroEquatorial();
    $eqTo->ra = $eqFrom->ra + ($ra + $mAlpha) * $dy;
    $eqTo->dec = $eqFrom->dec + ($dec + $mDelta) * $dy;
    return $eqTo;
  }

  public static function position($eqFrom, $epochFrom, $epochTo, $mAlpha = NAN, $mDelta = NAN) {
    $p = new AstroPrecessor($epochFrom, $epochTo);
    $t = $epochTo - $epochFrom;
    $eqTo = new AstroEquatorial();
    $eqTo->ra = $eqFrom->ra + $mAlpha * $t;
    $eqTo->dec = $eqFrom->dec + $mDelta * $t;
    return $p->precess($eqTo);
  }

  public static function eclipticPosition($eclFrom, $epochFrom, $epochTo, $mAlpha = null, $mDelta = null) {
    $p = new AstroEclipticPrecessor($epochFrom, $epochTo);

    if ($mAlpha && $mDelta && ($mAlpha->rad() != 0 || $mDelta->rad() != 0)) {
      $pm = self::properMotion($mAlpha->rad(), $mDelta->rad(), $epochFrom, $eclFrom);
      $t = $epochTo - $epochFrom;
      $eclFrom->lon += $pm->lon * $t;
      $eclFrom->lat += $pm->lat * $t;
    }
    return $p->precess($eclFrom);
  }

  public static function properMotion($mAlpha, $mDelta, $epoch, $ecl) {
    $eps = AstroNutation::meanObliquity(AstroBase::JulianYearToJDE($epoch));
    [$epsSin, $epsCos] = AstroBase::sincos($eps);
    $equ = $ecl->toEquatorial($eps);
    [$sAlpha, $cAlpha] = AstroBase::sincos($equ->ra);
    [$sDelta, $cDelta] = AstroBase::sincos($equ->dec);
    $cBeta = cos($ecl->lat);
    $lon = fdiv($mDelta * $epsSin * $cAlpha + $mAlpha * $cDelta * ($epsCos * $cDelta + $epsSin * $sDelta * $sAlpha),
      $cBeta * $cBeta);
    $lat = fdiv($mDelta * ($epsCos * $cDelta + $epsSin * $sDelta * $sAlpha) - $mAlpha * $epsSin * $cAlpha * $cDelta,
      $cBeta);
    return new AstroEcliptic($lon, $lat);
  }

  public static function properMotion3D($eqFrom, $epochFrom, $epochTo, $r, $mr, $mAlpha, $mDelta) {
    [$sAlpha, $cAlpha] = AstroBase::sincos($eqFrom->ra);
    [$sDelta, $cDelta] = AstroBase::sincos($eqFrom->dec);
    $x = $r * $cDelta * $cAlpha;
    $y = $r * $cDelta * $sAlpha;
    $z = $r * $sDelta;
    $mrr = $mr / $r;
    $zmDelta = $z * $mDelta->rad();
    $mx = $x * $mrr - $zmDelta * $cAlpha - $y * $mAlpha->rad();
    $my = $y * $mrr - $zmDelta * $sAlpha + $x * $mAlpha->rad();
    $mz = $z * $mrr + $r * $mDelta->rad() * $cDelta;
    $t = $epochTo - $epochFrom;
    $xp = $x + $t * $mx;
    $yp = $y + $t * $my;
    $zp = $z + $t * $mz;
    $eqTo = new AstroEquatorial();
    $eqTo->ra = atan2($yp, $xp);
    $eqTo->dec = atan2($zp, hypot($xp, $yp));
    return $eqTo;
  }
}

$d = AstroPrecess::d;
$s = AstroPrecess::s;
AstroPrecess::$zetaT = [2306.2181 * $s, 1.39656 * $s, -0.000139 * $s];
AstroPrecess::$zT = [2306.2181 * $s, 1.39656 * $s, -0.000139 * $s];
AstroPrecess::$thetaT = [2004.3109 * $s, -0.8533 * $s, -0.000217 * $s];
AstroPrecess::$zetat = [2306.2181 * $s, 0.30188 * $s, 0.017998 * $s];
AstroPrecess::$zt = [2306.2181 * $s, 1.09468 * $s, 0.018203 * $s];
AstroPrecess::$thetat = [2004.3109 * $s, -0.42665 * $s, -0.041833 * $s];
AstroPrecess::$etaT = [47.0029 * $s, -0.06603 * $s, 0.000598 * $s];
AstroPrecess::$piT = [174.876384 * $d, 3289.4789 * $s, 0.60622 * $s];
AstroPrecess::$pT = [5029.0966 * $s, 2.22226 * $s, -0.000042 * $s];
AstroPrecess::$etat = [47.0029 * $s, -0.03302 * $s, 0.000060 * $s];
AstroPrecess::$pit = [174.876384 * $d, -869.8089 * $s, 0.03536 * $s];
AstroPrecess::$pt = [5029.0966 * $s, 1.11113 * $s, -0.000006 * $s];
unset($d, $s);

class AstroPrecessor {
  public $ζ;
  public $z;
  public $sθ;
  public $cθ;

  public function __construct($epochFrom, $epochTo) {
    $s = AstroPrecess::s;
    $zetaCoeff = AstroPrecess::$zetat;
    $zCoeff = AstroPrecess::$zt;
    $thetaCoeff = AstroPrecess::$thetat;
    if ($epochFrom != 2000) {
      $T = ($epochFrom - 2000) * 0.01;
      $zetaCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$zetaT),
        0.30188 * $s - 0.000344 * $s * $T,
        0.017998 * $s
      ];
      $zCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$zT),
        1.09468 * $s + 0.000066 * $s * $T,
        0.018203 * $s
      ];
      $thetaCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$thetaT),
        -0.42665 * $s - 0.000217 * $s * $T,
        -0.041833 * $s
      ];
    }
    $t = ($epochTo - $epochFrom) * 0.01;
    $this->ζ = AstroBase::horner($t, ...$zetaCoeff) * $t;
    $this->z = AstroBase::horner($t, ...$zCoeff) * $t;
    $theta = AstroBase::horner($t, ...$thetaCoeff) * $t;
    $this->sθ = sin($theta);
    $this->cθ = cos($theta);
  }

  public function precess($eqFrom) {
    [$sDelta, $cDelta] = AstroBase::sincos($eqFrom->dec);
    [$sAlphaZeta, $cAlphaZeta] = AstroBase::sincos($eqFrom->ra + $this->ζ);
    $A = $cDelta * $sAlphaZeta;
    $B = $this->cθ * $cDelta * $cAlphaZeta - $this->sθ * $sDelta;
    $C = $this->sθ * $cDelta * $cAlphaZeta + $this->cθ * $sDelta;
    $eqTo = new AstroEquatorial();
    $eqTo->ra = atan2($A, $B) + $this->z;
    if ($C < AstroBase::$CosSmallAngle) {
      $eqTo->dec = asin($C);
    } else {
      $eqTo->dec = acos(hypot($A, $B));
    }
    return $eqTo;
  }
}

class AstroEclipticPrecessor {
  public $π;
  public $p;
  public $sη;
  public $cη;

  public function __construct($epochFrom, $epochTo) {
    $s = AstroPrecess::s;
    $etaCoeff = AstroPrecess::$etat;
    $piCoeff = AstroPrecess::$pit;
    $pCoeff = AstroPrecess::$pt;
    if ($epochFrom != 2000) {
      $T = ($epochFrom - 2000) * 0.01;
      $etaCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$etaT),
        -0.03302 * $s + 0.000598 * $s * $T,
        0.000060 * $s
      ];
      $piCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$piT),
        -869.8089 * $s - 0.50491 * $s * $T,
        0.03536 * $s
      ];
      $pCoeff = [
        AstroBase::horner($T, ...AstroPrecess::$pT),
        1.11113 * $s - 0.000042 * $s * $T,
        -0.000006 * $s
      ];
    }
    $t = ($epochTo - $epochFrom) * 0.01;
    $this->π = AstroBase::horner($t, ...$piCoeff);
    $this->p = AstroBase::horner($t, ...$pCoeff) * $t;
    $eta = AstroBase::horner($t, ...$etaCoeff) * $t;
    $this->sη = sin($eta);
    $this->cη = cos($eta);
  }

  public function precess($eclFrom) {
    [$sBeta, $cBeta] = AstroBase::sincos($eclFrom->lat);
    [$sd, $cd] = AstroBase::sincos($this->π - $eclFrom->lon);
    $A = $this->cη * $cBeta * $sd - $this->sη * $sBeta;
    $B = $cBeta * $cd;
    $C = $this->cη * $sBeta + $this->sη * $cBeta * $sd;
    $eclTo = new AstroEcliptic($this->p + $this->π - atan2($A, $B));
    if ($C < AstroBase::$CosSmallAngle) {
      $eclTo->lat = asin($C);
    } else {
      $eclTo->lat = acos(hypot($A, $B));
    }
    return $eclTo;
  }

  public function reduceElements($eFrom) {
    $psi = $this->π + $this->p;
    [$si, $ci] = AstroBase::sincos($eFrom->inc);
    [$snp, $cnp] = AstroBase::sincos($eFrom->node - $this->π);
    $eTo = new AstroElementEquinoxElements();
    $eTo->inc = acos($ci * $this->cη + $si * $this->sη * $cnp);
    $eTo->node = atan2($si * $snp, $this->cη * $si * $cnp - $this->sη * $ci) + $psi;
    $eTo->peri = atan2(-$this->sη * $snp, $si * $this->cη - $ci * $this->sη * $cnp) + $eFrom->peri;
    return $eTo;
  }
}

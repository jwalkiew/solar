<?php
// port of astronomia saturnring.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroPlanetPosition.php';

final class AstroSaturnRing {
  public static function ring($jde, $earth, $saturn) {
    [$f1, $f2] = self::cl($jde, $earth, $saturn);
    [$dU, $B] = $f1();
    [$Bp, $P, $aEdge, $bEdge] = $f2();
    return [$B, $Bp, $dU, $P, $aEdge, $bEdge];
  }

  public static function ub($jde, $earth, $saturn) {
    [$f1, $f2] = self::cl($jde, $earth, $saturn);
    return $f1();
  }

  private static function cl($jde, $earth, $saturn) {
    $p = M_PI / 180;
    $i = $Omega = null;
    $l0 = $b0 = $R = null;
    $Delta = 9.0;
    $lambda = $beta = null;
    $si = $ci = $sBeta = $cBeta = $sB = null;
    $sbp = $cbp = $slpOmega = $clpOmega = null;
    $f1 = function () use ($jde, $earth, $saturn, $p, &$i, &$Omega, &$l0, &$b0, &$R, &$Delta, &$lambda, &$beta,
      &$si, &$ci, &$sBeta, &$cBeta, &$sB, &$sbp, &$cbp, &$slpOmega, &$clpOmega) {
      $T = AstroBase::J2000Century($jde);
      $i = AstroBase::horner($T, 28.075216 * $p, -0.012998 * $p, 0.000004 * $p);
      $Omega = AstroBase::horner($T, 169.50847 * $p, 1.394681 * $p, 0.000412 * $p);
      $earthPos = $earth->position($jde);
      $R = $earthPos->range;
      $fk5 = AstroPlanetPosition::toFK5($earthPos->lon, $earthPos->lat, $jde);
      $l0 = $fk5->lon;
      $b0 = $fk5->lat;
      [$sl0, $cl0] = AstroBase::sincos($l0);
      $sb0 = sin($b0);
      $l = 0;
      $b = 0;
      $r = 0;
      $x = 0;
      $y = 0;
      $z = 0;

      $f = function () use ($jde, $saturn, &$Delta, &$r, &$l, &$b, &$x, &$y, &$z, $R, $cl0, $sl0, $sb0) {
        $tau = AstroBase::lightTime($Delta);
        $saturnPos = $saturn->position($jde - $tau);
        $r = $saturnPos->range;
        $fk5 = AstroPlanetPosition::toFK5($saturnPos->lon, $saturnPos->lat, $jde);
        $l = $fk5->lon;
        $b = $fk5->lat;
        [$sl, $cl] = AstroBase::sincos($l);
        [$sb, $cb] = AstroBase::sincos($b);
        $x = $r * $cb * $cl - $R * $cl0;
        $y = $r * $cb * $sl - $R * $sl0;
        $z = $r * $sb - $R * $sb0;
        $Delta = sqrt($x * $x + $y * $y + $z * $z);
      };
      $f();
      $f();
      $lambda = atan2($y, $x);
      $beta = atan(fdiv($z, hypot($x, $y)));
      $si = sin($i);
      $ci = cos($i);
      $sBeta = sin($beta);
      $cBeta = cos($beta);
      $sB = $si * $cBeta * sin($lambda - $Omega) - $ci * $sBeta;
      $B = asin($sB);
      $N = 113.6655 * $p + 0.8771 * $p * $T;
      $lp = $l - fdiv(0.01759 * $p, $r);
      $bp = $b - fdiv(0.000764 * $p * cos($l - $N), $r);
      $sbp = sin($bp);
      $cbp = cos($bp);
      $slpOmega = sin($lp - $Omega);
      $clpOmega = cos($lp - $Omega);
      [$sLambdaOmega, $cLambdaOmega] = AstroBase::sincos($lambda - $Omega);
      $U1 = atan2($si * $sbp + $ci * $cbp * $slpOmega, $cbp * $clpOmega);
      $U2 = atan2($si * $sBeta + $ci * $cBeta * $sLambdaOmega, $cBeta * $cLambdaOmega);
      $dU = abs($U1 - $U2);
      return [$dU, $B];
    };
    $f2 = function () use ($jde, $p, &$i, &$Omega, &$l0, &$Delta, &$lambda, &$beta, &$si, &$ci, &$sBeta, &$cBeta,
      &$sB, &$sbp, &$cbp, &$slpOmega) {
      $aEdge = fdiv(375.35 / 3600 * $p, $Delta);
      $bEdge = $aEdge * abs($sB);
      $sBp = $si * $cbp * $slpOmega - $ci * $sbp;
      $Bp = asin($sBp);
      [$dPsi, $dEps] = AstroNutation::nutation($jde);
      $eps = AstroNutation::meanObliquity($jde) + $dEps;
      $lambda0 = $Omega - M_PI / 2;
      $beta0 = M_PI / 2 - $i;
      [$sl0lambda, $cl0lambda] = AstroBase::sincos($l0 - $lambda);
      $lambda += fdiv(0.005693 * $p * $cl0lambda, $cBeta);
      $beta += 0.005693 * $p * $sl0lambda * $sBeta;
      $lambda0 += $dPsi;
      $lambda += $dPsi;
      $eq = (new AstroEcliptic($lambda0, $beta0))->toEquatorial($eps);
      [$alpha0, $delta0] = [$eq->ra, $eq->dec];
      $eq = (new AstroEcliptic($lambda, $beta))->toEquatorial($eps);
      [$alpha, $delta] = [$eq->ra, $eq->dec];
      [$sDelta0, $cDelta0] = AstroBase::sincos($delta0);
      [$sDelta, $cDelta] = AstroBase::sincos($delta);
      [$sAlpha0Alpha, $cAlpha0Alpha] = AstroBase::sincos($alpha0 - $alpha);
      $P = atan2($cDelta0 * $sAlpha0Alpha, $sDelta0 * $cDelta - $cDelta0 * $sDelta * $cAlpha0Alpha);
      return [$Bp, $P, $aEdge, $bEdge];
    };
    return [$f1, $f2];
  }
}

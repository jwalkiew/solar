<?php
// port of astronomia parallax.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroGlobe.php';
require_once __DIR__ . '/AstroSidereal.php';
require_once __DIR__ . '/AstroSexagesimal.php';

final class AstroParallax {
  const horPar = (8.794 / 3600) * M_PI / 180;

  public static function horizontal($Delta) {
    return asin(fdiv(sin(self::horPar), $Delta));
  }

  public static function topocentric($c, $rhoSPhi, $rhoCPhi, $lon, $jde) {
    [$alpha, $delta, $Delta] = [$c->ra, $c->dec, $c->range];
    $pi = self::horizontal($Delta);
    $theta0 = (new AstroTime(AstroSidereal::apparent($jde)))->rad();
    $H = AstroBase::pmod($theta0 - $lon - $alpha, 2 * M_PI);
    $sPi = sin($pi);
    [$sH, $cH] = AstroBase::sincos($H);
    [$sDelta, $cDelta] = AstroBase::sincos($delta);
    $dAlpha = atan2(-$rhoCPhi * $sPi * $sH, $cDelta - $rhoCPhi * $sPi * $cH);
    $alpha_ = $alpha + $dAlpha;
    $delta_ = atan2(($sDelta - $rhoSPhi * $sPi) * cos($dAlpha), $cDelta - $rhoCPhi * $sPi * $cH);
    return new AstroBaseCoord($alpha_, $delta_);
  }

  public static function topocentric2($c, $rhoSPhi, $rhoCPhi, $lon, $jde) {
    [$alpha, $delta, $Delta] = [$c->ra, $c->dec, $c->range];
    $pi = self::horizontal($Delta);
    $theta0 = (new AstroTime(AstroSidereal::apparent($jde)))->rad();
    $H = AstroBase::pmod($theta0 - $lon - $alpha, 2 * M_PI);
    [$sH, $cH] = AstroBase::sincos($H);
    [$sDelta, $cDelta] = AstroBase::sincos($delta);
    $dAlpha = fdiv(-$pi * $rhoCPhi * $sH, $cDelta);
    $dDelta = -$pi * ($rhoSPhi * $cDelta - $rhoCPhi * $cH * $sDelta);
    return new AstroBaseCoord($dAlpha, $dDelta);
  }

  public static function topocentric3($c, $rhoSPhi, $rhoCPhi, $lon, $jde) {
    [$alpha, $delta, $Delta] = [$c->ra, $c->dec, $c->range];
    $pi = self::horizontal($Delta);
    $theta0 = (new AstroTime(AstroSidereal::apparent($jde)))->rad();
    $H = AstroBase::pmod($theta0 - $lon - $alpha, 2 * M_PI);
    $sPi = sin($pi);
    [$sH, $cH] = AstroBase::sincos($H);
    [$sDelta, $cDelta] = AstroBase::sincos($delta);
    $A = $cDelta * $sH;
    $B = $cDelta * $cH - $rhoCPhi * $sPi;
    $C = $sDelta - $rhoSPhi * $sPi;
    $q = sqrt($A * $A + $B * $B + $C * $C);
    $H_ = atan2($A, $B);
    $delta_ = asin(fdiv($C, $q));
    return [$H_, $delta_];
  }

  public static function topocentricEcliptical($c, $s, $phi, $h, $eps, $theta, $pi) {
    [$lambda, $beta] = [$c->lon, $c->lat];
    [$S, $C] = AstroGlobe::$Earth76->parallaxConstants($phi, $h);
    [$sLambda, $cLambda] = AstroBase::sincos($lambda);
    [$sBeta, $cBeta] = AstroBase::sincos($beta);
    [$sEps, $cEps] = AstroBase::sincos($eps);
    [$sTheta, $cTheta] = AstroBase::sincos($theta);
    $sPi = sin($pi);
    $N = $cLambda * $cBeta - $C * $sPi * $cTheta;
    $lambda_ = atan2($sLambda * $cBeta - $sPi * ($S * $sEps + $C * $cEps * $sTheta), $N);
    if ($lambda_ < 0) {
      $lambda_ += 2 * M_PI;
    }
    $cLambda_ = cos($lambda_);
    $beta_ = atan(fdiv($cLambda_ * ($sBeta - $sPi * ($S * $cEps - $C * $sEps * $sTheta)), $N));
    $s_ = asin(fdiv($cLambda_ * cos($beta_) * sin($s), $N));
    return [$lambda_, $beta_, $s_];
  }
}

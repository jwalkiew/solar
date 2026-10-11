<?php
// port of astronomia elliptic.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroApparent.php';
require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroKepler.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroPlanetPosition.php';
require_once __DIR__ . '/AstroSolarXyz.php';

final class AstroElliptic {
  public static function position($planet, $earth, $jde) {
    $x = 0;
    $y = 0;
    $z = 0;
    $posEarth = $earth->position($jde);
    [$L0, $B0, $R0] = [$posEarth->lon, $posEarth->lat, $posEarth->range];
    [$sB0, $cB0] = AstroBase::sincos($B0);
    [$sL0, $cL0] = AstroBase::sincos($L0);

    $pos = function ($tau = 0) use ($planet, $jde, $R0, $sB0, $cB0, $sL0, $cL0, &$x, &$y, &$z) {
      $p = $planet->position($jde - $tau);
      [$L, $B, $R] = [$p->lon, $p->lat, $p->range];
      [$sB, $cB] = AstroBase::sincos($B);
      [$sL, $cL] = AstroBase::sincos($L);
      $x = $R * $cB * $cL - $R0 * $cB0 * $cL0;
      $y = $R * $cB * $sL - $R0 * $cB0 * $sL0;
      $z = $R * $sB - $R0 * $sB0;
    };

    $pos();
    $delta = sqrt($x * $x + $y * $y + $z * $z);
    $tau = AstroBase::lightTime($delta);
    $pos($tau);

    $lambda = atan2($y, $x);
    $beta = atan2($z, hypot($x, $y));
    [$dLambda, $dBeta] = AstroApparent::eclipticAberration($lambda, $beta, $jde);
    $fk5 = AstroPlanetPosition::toFK5($lambda + $dLambda, $beta + $dBeta, $jde);
    $lambda = $fk5->lon;
    $beta = $fk5->lat;
    [$dPsi, $dEps] = AstroNutation::nutation($jde);
    $lambda += $dPsi;
    $eps = AstroNutation::meanObliquity($jde) + $dEps;
    return (new AstroEcliptic($lambda, $beta))->toEquatorial($eps);
  }

  public static function astrometricJ2000($f, $jde, $earth) {
    $sol = AstroSolarXyz::positionJ2000($earth, $jde);
    [$X, $Y, $Z] = [$sol['x'], $sol['y'], $sol['z']];
    $xi = 0;
    $eta = 0;
    $zeta = 0;
    $delta = 0;

    $fn = function ($tau = 0) use ($f, $jde, $X, $Y, $Z, &$xi, &$eta, &$zeta, &$delta) {
      ['x' => $x, 'y' => $y, 'z' => $z] = $f($jde - $tau);
      $xi = $X + $x;
      $eta = $Y + $y;
      $zeta = $Z + $z;
      $delta = sqrt($xi * $xi + $eta * $eta + $zeta * $zeta);
    };

    $fn();
    $tau = AstroBase::lightTime($delta);
    $fn($tau);

    $alpha = atan2($eta, $xi);
    if ($alpha < 0) {
      $alpha += 2 * M_PI;
    }
    $dec = asin(fdiv($zeta, $delta));
    $R0 = sqrt($X * $X + $Y * $Y + $Z * $Z);
    $psi = acos(fdiv(fdiv($xi * $X + $eta * $Y + $zeta * $Z, $R0), $delta));
    return new AstroBaseCoord($alpha, $dec, null, $psi);
  }

  public static function velocity($a, $r) {
    return 42.1219 * sqrt(fdiv(1, $r) - fdiv(0.5, $a));
  }

  public static function vAphelion($a, $e) {
    return 29.7847 * sqrt(fdiv(fdiv(1 - $e, 1 + $e), $a));
  }

  public static function vPerihelion($a, $e) {
    return 29.7847 * sqrt(fdiv(fdiv(1 + $e, 1 - $e), $a));
  }

  public static function length1($a, $e) {
    $b = $a * sqrt(1 - $e * $e);
    return M_PI * (3 * ($a + $b) - sqrt(($a + 3 * $b) * (3 * $a + $b)));
  }

  public static function length2($a, $e) {
    $b = $a * sqrt(1 - $e * $e);
    $s = $a + $b;
    $p = $a * $b;
    $A = $s * 0.5;
    $G = sqrt($p);
    $H = fdiv(2 * $p, $s);
    return M_PI * (21 * $A - 2 * $G - 3 * $H) * 0.125;
  }

  public static function length4($a, $e) {
    $b = $a * sqrt(1 - $e * $e);
    $m = fdiv($a - $b, $a + $b);
    $m2 = $m * $m;
    $sum0 = 1.0;
    $term = $m2 * 0.25;
    $sum1 = 1.0 + $term;
    $nf = -1.0;
    $df = 2.0;
    while ($sum1 != $sum0) {
      $nf += 2;
      $df += 2;
      $term *= $nf * $nf * $m2 / ($df * $df);
      $sum0 = $sum1;
      $sum1 += $term;
    }
    return 2 * M_PI * $a * $sum0 / (1 + $m);
  }
}

class AstroEllipticElements {
  public $axis;
  public $ecc;
  public $inc;
  public $argP;
  public $node;
  public $timeP;

  public function __construct($axis = null, $ecc = null, $inc = null, $argP = null, $node = null, $timeP = null) {
    $o = [];
    if (is_object($axis) || is_array($axis)) {
      $o = (array) $axis;
    }
    $this->axis = ($o['axis'] ?? null) ?: $axis;
    $this->ecc = ($o['ecc'] ?? null) ?: $ecc;
    $this->inc = ($o['inc'] ?? null) ?: $inc;
    $this->argP = ($o['argP'] ?? null) ?: $argP;
    $this->node = ($o['node'] ?? null) ?: $node;
    $this->timeP = ($o['timeP'] ?? null) ?: $timeP;
  }

  public function position($jde, $earth) {
    $n = AstroBase::K / $this->axis / sqrt($this->axis);
    $sEps = AstroBase::SOblJ2000;
    $cEps = AstroBase::COblJ2000;
    [$sOmega, $cOmega] = AstroBase::sincos($this->node);
    [$si, $ci] = AstroBase::sincos($this->inc);
    $F = $cOmega;
    $G = $sOmega * $cEps;
    $H = $sOmega * $sEps;
    $P = -$sOmega * $ci;
    $Q = $cOmega * $ci * $cEps - $si * $sEps;
    $R = $cOmega * $ci * $sEps + $si * $cEps;
    $A = atan2($F, $P);
    $B = atan2($G, $Q);
    $C = atan2($H, $R);
    $a = hypot($F, $P);
    $b = hypot($G, $Q);
    $c = hypot($H, $R);

    $f = function ($jde) use ($n, $A, $B, $C, $a, $b, $c) {
      $M = $n * ($jde - $this->timeP);
      $E = AstroKepler::kepler2b($this->ecc, $M, 15);
      if ($E === null) {
        $E = AstroKepler::kepler3($this->ecc, $M);
      }
      $nu = AstroKepler::trueAnomaly($E, $this->ecc);
      $r = AstroKepler::radius($E, $this->ecc, $this->axis);
      $x = $r * $a * sin($A + $this->argP + $nu);
      $y = $r * $b * sin($B + $this->argP + $nu);
      $z = $r * $c * sin($C + $this->argP + $nu);
      return ['x' => $x, 'y' => $y, 'z' => $z];
    };
    return AstroElliptic::astrometricJ2000($f, $jde, $earth);
  }
}

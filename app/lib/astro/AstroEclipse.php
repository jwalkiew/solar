<?php
// port of astronomia eclipse.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroMoonPhase.php';

final class AstroEclipse {
  const TYPE = [
    'None' => 0,
    'Partial' => 1,
    'Annular' => 2,
    'AnnularTotal' => 3,
    'Penumbral' => 4,
    'Umbral' => 5,
    'Total' => 6
  ];

  private static function g($k, $jm, $c1, $c2) {
    $ck = 1 / 1236.85;
    $p = M_PI / 180;
    $T = $k * $ck;
    $F = AstroBase::horner($T, 160.7108 * $p, 390.67050284 * $p / $ck,
      -0.0016118 * $p, -0.00000227 * $p, 0.000000011 * $p);
    if (abs(sin($F)) > 0.36) {
      return [false];
    }
    $eclipse = true;
    $E = AstroBase::horner($T, 1, -0.002516, -0.0000074);
    $M = AstroBase::horner($T, 2.5534 * $p, 29.1053567 * $p / $ck,
      -0.0000014 * $p, -0.00000011 * $p);
    $Mp = AstroBase::horner($T, 201.5643 * $p, 385.81693528 * $p / $ck,
      0.0107582 * $p, 0.00001238 * $p, -0.000000058 * $p);
    $omega = AstroBase::horner($T, 124.7746 * $p, -1.56375588 * $p / $ck,
      0.0020672 * $p, 0.00000215 * $p);
    $sOmega = sin($omega);
    $F1 = $F - 0.02665 * $p * $sOmega;
    $A1 = AstroBase::horner($T, 299.77 * $p, 0.107408 * $p / $ck, -0.009173 * $p);
    $jdeMax = $jm +
      $c1 * sin($Mp) +
      $c2 * sin($M) * $E +
      0.0161 * sin(2 * $Mp) +
      -0.0097 * sin(2 * $F1) +
      0.0073 * sin($Mp - $M) * $E +
      -0.005 * sin($Mp + $M) * $E +
      -0.0023 * sin($Mp - 2 * $F1) +
      0.0021 * sin(2 * $M) * $E +
      0.0012 * sin($Mp + 2 * $F1) +
      0.0006 * sin(2 * $Mp + $M) * $E +
      -0.0004 * sin(3 * $Mp) +
      -0.0003 * sin($M + 2 * $F1) * $E +
      0.0003 * sin($A1) +
      -0.0002 * sin($M - 2 * $F1) * $E +
      -0.0002 * sin(2 * $Mp - $M) * $E +
      -0.0002 * $sOmega;
    $P = 0.207 * sin($M) * $E +
      0.0024 * sin(2 * $M) * $E +
      -0.0392 * sin($Mp) +
      0.0116 * sin(2 * $Mp) +
      -0.0073 * sin($Mp + $M) * $E +
      0.0067 * sin($Mp - $M) * $E +
      0.0118 * sin(2 * $F1);
    $Q = 5.2207 +
      -0.0048 * cos($M) * $E +
      0.002 * cos(2 * $M) * $E +
      -0.3299 * cos($Mp) +
      -0.006 * cos($Mp + $M) * $E +
      0.0041 * cos($Mp - $M) * $E;
    [$sF1, $cF1] = AstroBase::sincos($F1);
    $W = abs($cF1);
    $gamma = ($P * $cF1 + $Q * $sF1) * (1 - 0.0048 * $W);
    $u = 0.0059 +
      0.0046 * cos($M) * $E +
      -0.0182 * cos($Mp) +
      0.0004 * cos(2 * $Mp) +
      -0.0005 * cos($M + $Mp);
    return [$eclipse, $jdeMax, $gamma, $u, $Mp];
  }

  private static function snap($y, $q) {
    $k = ($y - 2000) * 12.3685;
    return floor($k - $q + 0.5) + $q;
  }

  public static function solar($year) {
    $eclipseType = self::TYPE['None'];
    $mag = null;

    [$e, $jdeMax, $gamma, $u] = array_pad(self::g(self::snap($year, 0), AstroMoonPhase::meanNew($year), -0.4075, 0.1721), 5, null);

    $p = $u + 0.5461;
    if (!$e) {
      return ['type' => $eclipseType];
    }
    $aGamma = abs($gamma);
    if ($aGamma > 1.5433 + $u) {
      return ['type' => $eclipseType];
    }
    $central = $aGamma < 0.9972;

    if (!$central) {
      $eclipseType = self::TYPE['Partial'];
      if ($aGamma < 1.026) {
        if ($aGamma < 0.9972 + abs($u)) {
          $eclipseType = self::TYPE['Total'];
        }
      }
    } elseif ($u < 0) {
      $eclipseType = self::TYPE['Total'];
    } elseif ($u > 0.0047) {
      $eclipseType = self::TYPE['Annular'];
    } else {
      $omega = 0.00464 * sqrt(1 - $gamma * $gamma);
      if ($u < $omega) {
        $eclipseType = self::TYPE['AnnularTotal'];
      } else {
        $eclipseType = self::TYPE['Annular'];
      }
    }

    if ($eclipseType == self::TYPE['Partial']) {
      $mag = (1.5433 + $u - $aGamma) / (0.5461 + 2 * $u);
    }

    return [
      'type' => $eclipseType,
      'central' => $central,
      'jdeMax' => $jdeMax,
      'magnitude' => $mag,
      'distance' => $gamma,
      'umbral' => $u,
      'penumbral' => $p
    ];
  }

  public static function lunar($year) {
    $eclipseType = self::TYPE['None'];
    $mag = null;
    $sdTotal = null;
    $sdPartial = null;
    $sdPenumbral = null;

    [$e, $jdeMax, $gamma, $u, $Mp] = array_pad(self::g(self::snap($year, 0.5),
      AstroMoonPhase::meanFull($year), -0.4065, 0.1727), 5, null);
    if (!$e) {
      return ['type' => $eclipseType];
    }
    $rho = 1.2848 + $u;
    $sigma = 0.7403 - $u;
    $aGamma = abs($gamma);
    $mag = (1.0128 - $u - $aGamma) / 0.545;

    if ($mag > 1) {
      $eclipseType = self::TYPE['Total'];
    } elseif ($mag > 0) {
      $eclipseType = self::TYPE['Umbral'];
    } else {
      $mag = (1.5573 + $u - $aGamma) / 0.545;
      if ($mag < 0) {
        return ['type' => $eclipseType];
      }
      $eclipseType = self::TYPE['Penumbral'];
    }

    $p = 1.0128 - $u;
    $t = 0.4678 - $u;
    $n = 0.5458 + 0.04 * cos($Mp);
    $gamma2 = $gamma * $gamma;

    switch ($eclipseType) {
      case self::TYPE['Total']:
        $sdTotal = sqrt($t * $t - $gamma2) / $n / 24;
        // fall through
      case self::TYPE['Umbral']:
        $sdPartial = sqrt($p * $p - $gamma2) / $n / 24;
        // fall through
      default:
        $h = 1.5573 + $u;
        $sdPenumbral = sqrt($h * $h - $gamma2) / $n / 24;
    }

    return [
      'type' => $eclipseType,
      'jdeMax' => $jdeMax,
      'magnitude' => $mag,
      'distance' => $gamma,
      'umbral' => $sigma,
      'penumbral' => $rho,
      'sdTotal' => $sdTotal,
      'sdPartial' => $sdPartial,
      'sdPenumbral' => $sdPenumbral
    ];
  }
}

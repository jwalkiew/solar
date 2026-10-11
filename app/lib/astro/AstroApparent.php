<?php
// port of astronomia apparent.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroNutation.php';
require_once __DIR__ . '/AstroPrecess.php';
require_once __DIR__ . '/AstroSolar.php';

final class AstroApparent {
  private const kappa = 20.49552 * M_PI / 180 / 3600;
  private const c = 17314463350;
  public static $rvTerm;

  public static function nutation($alpha, $delta, $jd) {
    $eps = AstroNutation::meanObliquity($jd);
    [$sinEps, $cosEps] = AstroBase::sincos($eps);
    [$dPsi, $dEps] = AstroNutation::nutation($jd);
    [$sinAlpha, $cosAlpha] = AstroBase::sincos($alpha);
    $tanDelta = tan($delta);
    $dAlpha1 = ($cosEps + $sinEps * $sinAlpha * $tanDelta) * $dPsi - $cosAlpha * $tanDelta * $dEps;
    $dDelta1 = $sinEps * $cosAlpha * $dPsi + $sinAlpha * $dEps;
    return [$dAlpha1, $dDelta1];
  }

  public static function perihelion($T) {
    return AstroBase::horner($T, 102.93735, 1.71946, 0.00046) * M_PI / 180;
  }

  public static function eclipticAberration($lambda, $beta, $jd) {
    $T = AstroBase::J2000Century($jd);
    ['lon' => $lon, 'ano' => $ano] = AstroSolar::trueLongitude($T);
    $e = AstroSolar::eccentricity($T);
    $pi = self::perihelion($T);
    [$sBeta, $cBeta] = AstroBase::sincos($beta);
    [$ssLambda, $csLambda] = AstroBase::sincos($lon - $lambda);
    [$sinPiLambda, $cosPiLambda] = AstroBase::sincos($pi - $lambda);
    $dLambda = fdiv(self::kappa * ($e * $cosPiLambda - $csLambda), $cBeta);
    $dBeta = -self::kappa * $sBeta * ($ssLambda - $e * $sinPiLambda);
    return [$dLambda, $dBeta];
  }

  public static function aberration($alpha, $delta, $jd) {
    $eps = AstroNutation::meanObliquity($jd);
    $T = AstroBase::J2000Century($jd);
    ['lon' => $lon, 'ano' => $ano] = AstroSolar::trueLongitude($T);
    $e = AstroSolar::eccentricity($T);
    $pi = self::perihelion($T);
    [$sinAlpha, $cosAlpha] = AstroBase::sincos($alpha);
    [$sinDelta, $cosDelta] = AstroBase::sincos($delta);
    [$sins, $coss] = AstroBase::sincos($lon);
    [$sinPi, $cosPi] = AstroBase::sincos($pi);
    $cosEps = cos($eps);
    $q1 = $cosAlpha * $cosEps;
    $dAlpha2 = fdiv(self::kappa * ($e * ($q1 * $cosPi + $sinAlpha * $sinPi) - ($q1 * $coss + $sinAlpha * $sins)),
      $cosDelta);
    $q2 = $cosEps * (tan($eps) * $cosDelta - $sinAlpha * $sinDelta);
    $q3 = $cosAlpha * $sinDelta;
    $dDelta2 = self::kappa * ($e * ($cosPi * $q2 + $sinPi * $q3) - ($coss * $q2 + $sins * $q3));
    return [$dAlpha2, $dDelta2];
  }

  public static function position($eqFrom, $epochFrom, $epochTo, $mAlpha = NAN, $mDelta = NAN) {
    $eqTo = AstroPrecess::position($eqFrom, $epochFrom, $epochTo, $mAlpha, $mDelta);
    $jd = AstroBase::JulianYearToJDE($epochTo);
    [$dAlpha1, $dDelta1] = self::nutation($eqTo->ra, $eqTo->dec, $jd);
    [$dAlpha2, $dDelta2] = self::aberration($eqTo->ra, $eqTo->dec, $jd);
    $eqTo->ra += $dAlpha1 + $dAlpha2;
    $eqTo->dec += $dDelta1 + $dDelta2;
    return $eqTo;
  }

  public static function aberrationRonVondrak($alpha, $delta, $jd) {
    $T = AstroBase::J2000Century($jd);
    $r = [
      'T' => $T,
      'L2' => 3.1761467 + 1021.3285546 * $T,
      'L3' => 1.7534703 + 628.3075849 * $T,
      'L4' => 6.2034809 + 334.0612431 * $T,
      'L5' => 0.5995465 + 52.9690965 * $T,
      'L6' => 0.8740168 + 21.3299095 * $T,
      'L7' => 5.4812939 + 7.4781599 * $T,
      'L8' => 5.3118863 + 3.8133036 * $T,
      'Lp' => 3.8103444 + 8399.6847337 * $T,
      'D' => 5.1984667 + 7771.3771486 * $T,
      'Mp' => 2.3555559 + 8328.6914289 * $T,
      'F' => 1.6279052 + 8433.4661601 * $T
    ];
    $Xp = 0;
    $Yp = 0;
    $Zp = 0;
    // sum smaller terms first
    for ($i = 35; $i >= 0; $i--) {
      [$x, $y, $z] = (self::$rvTerm[$i])($r);
      $Xp += $x;
      $Yp += $y;
      $Zp += $z;
    }
    [$sinAlpha, $cosAlpha] = AstroBase::sincos($alpha);
    [$sinDelta, $cosDelta] = AstroBase::sincos($delta);
    return [fdiv($Yp * $cosAlpha - $Xp * $sinAlpha, self::c * $cosDelta),
      fdiv(-(($Xp * $cosAlpha + $Yp * $sinAlpha) * $sinDelta - $Zp * $cosDelta), self::c)];
  }

  public static function positionRonVondrak($eqFrom, $epochTo, $mAlpha, $mDelta) {
    $t = $epochTo - 2000;
    $eqTo = new AstroEquatorial();
    $eqTo->ra = $eqFrom->ra + $mAlpha->rad() * $t;
    $eqTo->dec = $eqFrom->dec + $mDelta->rad() * $t;
    $jd = AstroBase::JulianYearToJDE($epochTo);
    [$dAlpha, $dDelta] = self::aberrationRonVondrak($eqTo->ra, $eqTo->dec, $jd);
    $eqTo->ra += $dAlpha;
    $eqTo->dec += $dDelta;
    $eqTo = AstroPrecess::position($eqTo, 2000, $epochTo, 0, 0);
    [$dAlpha1, $dDelta1] = self::nutation($eqTo->ra, $eqTo->dec, $jd);
    $eqTo->ra += $dAlpha1;
    $eqTo->dec += $dDelta1;
    return $eqTo;
  }
}

// r = [T, L2, L3, L4, L5, L6, L7, L8, Lp, D, Mp, F]
AstroApparent::$rvTerm = [
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L3']);
      return [(-1719914 - 2 * $r['T']) * $sinA - 25 * $cosA,
        (25 - 13 * $r['T']) * $sinA + (1578089 + 156 * $r['T']) * $cosA,
        (10 + 32 * $r['T']) * $sinA + (684185 - 358 * $r['T']) * $cosA
      ];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L3']);
      return [(6434 + 141 * $r['T']) * $sinA + (28007 - 107 * $r['T']) * $cosA,
        (25697 - 95 * $r['T']) * $sinA + (-5904 - 130 * $r['T']) * $cosA,
        (11141 - 48 * $r['T']) * $sinA + (-2559 - 55 * $r['T']) * $cosA
      ];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L5']);
      return [715 * $sinA, 6 * $sinA - 657 * $cosA, -15 * $sinA - 282 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['Lp']);
      return [715 * $sinA, -656 * $cosA, -285 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L3']);
      return [(486 - 5 * $r['T']) * $sinA + (-236 - 4 * $r['T']) * $cosA,
        (-216 - 4 * $r['T']) * $sinA + (-446 + 5 * $r['T']) * $cosA, -94 * $sinA - 193 * $cosA
      ];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L6']);
      return [159 * $sinA, 2 * $sinA - 147 * $cosA, -6 * $sinA - 61 * $cosA];
    },
    function ($r) {
      $cosA = cos($r['F']);
      return [0, 26 * $cosA, -59 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['Lp'] + $r['Mp']);
      return [39 * $sinA, -36 * $cosA, -16 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L5']);
      return [33 * $sinA - 10 * $cosA, -9 * $sinA - 30 * $cosA, -5 * $sinA - 13 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L3'] - $r['L5']);
      return [31 * $sinA + $cosA, $sinA - 28 * $cosA, -12 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L3'] - 8 * $r['L4'] + 3 * $r['L5']);
      return [8 * $sinA - 28 * $cosA, 25 * $sinA + 8 * $cosA, 11 * $sinA + 3 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(5 * $r['L3'] - 8 * $r['L4'] + 3 * $r['L5']);
      return [8 * $sinA - 28 * $cosA, -25 * $sinA - 8 * $cosA, -11 * $sinA + -3 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L2'] - $r['L3']);
      return [21 * $sinA, -19 * $cosA, -8 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L2']);
      return [-19 * $sinA, 17 * $cosA, 8 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L7']);
      return [17 * $sinA, -16 * $cosA, -7 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L3'] - 2 * $r['L5']);
      return [16 * $sinA, 15 * $cosA, $sinA + 7 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L8']);
      return [16 * $sinA, $sinA - 15 * $cosA, -3 * $sinA - 6 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L3'] + $r['L5']);
      return [11 * $sinA - $cosA, -$sinA - 10 * $cosA, -$sinA - 5 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L2'] - 2 * $r['L3']);
      return [-11 * $cosA, -10 * $sinA, -4 * $sinA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L3'] - $r['L5']);
      return [-11 * $sinA - 2 * $cosA, -2 * $sinA + 9 * $cosA, -$sinA + 4 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(4 * $r['L3']);
      return [-7 * $sinA - 8 * $cosA, -8 * $sinA + 6 * $cosA, -3 * $sinA + 3 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L3'] - 2 * $r['L5']);
      return [-10 * $sinA, 9 * $cosA, 4 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['L2'] - 2 * $r['L3']);
      return [-9 * $sinA, -9 * $cosA, -4 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L2'] - 3 * $r['L3']);
      return [-9 * $sinA, -8 * $cosA, -4 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L6']);
      return [-9 * $cosA, -8 * $sinA, -3 * $sinA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L2'] - 4 * $r['L3']);
      return [-9 * $cosA, 8 * $sinA, 3 * $sinA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L3'] - 2 * $r['L4']);
      return [8 * $sinA, -8 * $cosA, -3 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['Lp'] + 2 * $r['D'] - $r['Mp']);
      return [8 * $sinA, -7 * $cosA, -3 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(8 * $r['L2'] - 12 * $r['L3']);
      return [-4 * $sinA - 7 * $cosA, -6 * $sinA + 4 * $cosA, -3 * $sinA + 2 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(8 * $r['L2'] - 14 * $r['L3']);
      return [-4 * $sinA - 7 * $cosA, 6 * $sinA - 4 * $cosA, 3 * $sinA - 2 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L4']);
      return [-6 * $sinA - 5 * $cosA, -4 * $sinA + 5 * $cosA, -2 * $sinA + 2 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L2'] - 4 * $r['L3']);
      return [-$sinA - $cosA, -2 * $sinA - 7 * $cosA, $sinA - 4 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L3'] - 2 * $r['L5']);
      return [4 * $sinA - 6 * $cosA, -5 * $sinA - 4 * $cosA, -2 * $sinA - 2 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(3 * $r['L2'] - 3 * $r['L3']);
      return [-7 * $cosA, -6 * $sinA, -3 * $sinA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos(2 * $r['L3'] - 2 * $r['L4']);
      return [5 * $sinA - 5 * $cosA, -4 * $sinA - 5 * $cosA, -2 * $sinA - 2 * $cosA];
    },
    function ($r) {
      [$sinA, $cosA] = AstroBase::sincos($r['Lp'] - 2 * $r['D']);
      return [5 * $sinA, -5 * $cosA, -2 * $cosA];
    }
  ];

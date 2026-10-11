<?php
// port of astronomia globe.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

final class AstroGlobe {
  const RotationRate1996_5 = 7.292114992e-5;
  public static $Earth76;

  public static function oneDegreeOfLongitude($rp) {
    return $rp * M_PI / 180;
  }

  public static function oneDegreeOfLatitude($rm) {
    return $rm * M_PI / 180;
  }

  public static function geocentricLatitudeDifference($phi) {
    return (692.73 * sin(2 * $phi) - 1.16 * sin(4 * $phi)) * M_PI / (180 * 3600);
  }

  public static function approxAngularDistance($p1, $p2) {
    $s1 = sin($p1->lat);
    $c1 = cos($p1->lat);
    $s2 = sin($p2->lat);
    $c2 = cos($p2->lat);
    return $s1 * $s2 + $c1 * $c2 * cos($p1->lon - $p2->lon);
  }

  public static function approxLinearDistance($d) {
    return 6371 * $d;
  }

  public static function sincos2($x) {
    $s = sin($x);
    $c = cos($x);
    return [$s * $s, $c * $c];
  }
}

class AstroEllipsoid {
  public $radius;
  public $flat;

  public function __construct($radius, $flat) {
    $this->radius = $radius;
    $this->flat = $flat;
  }

  public function A() {
    return $this->radius;
  }

  public function B() {
    return $this->radius * (1 - $this->flat);
  }

  public function eccentricity() {
    return sqrt((2 - $this->flat) * $this->flat);
  }

  public function parallaxConstants($phi, $h) {
    $boa = 1 - $this->flat;
    $su = sin(atan($boa * tan($phi)));
    $cu = cos(atan($boa * tan($phi)));
    $s = sin($phi);
    $c = cos($phi);
    $hoa = $h * 1e-3 / $this->radius;
    $rhoSPhi = $su * $boa + $hoa * $s;
    $rhoCPhi = $cu + $hoa * $c;
    return [$rhoSPhi, $rhoCPhi];
  }

  public function rho($phi) {
    return 0.9983271 + 0.0016764 * cos(2 * $phi) - 0.0000035 * cos(4 * $phi);
  }

  public function radiusAtLatitude($phi) {
    $s = sin($phi);
    $c = cos($phi);
    return $this->A() * $c / sqrt(1 - (2 - $this->flat) * $this->flat * $s * $s);
  }

  public function radiusOfCurvature($phi) {
    $s = sin($phi);
    $e2 = (2 - $this->flat) * $this->flat;
    return $this->A() * (1 - $e2) / pow(1 - $e2 * $s * $s, 1.5);
  }

  public function distance($c1, $c2) {
    [$s2f, $c2f] = AstroGlobe::sincos2(($c1->lat + $c2->lat) / 2);
    [$s2g, $c2g] = AstroGlobe::sincos2(($c1->lat - $c2->lat) / 2);
    [$s2lambda, $c2lambda] = AstroGlobe::sincos2(($c1->lon - $c2->lon) / 2);
    $s = $s2g * $c2lambda + $c2f * $s2lambda;
    $c = $c2g * $c2lambda + $s2f * $s2lambda;
    $omega = atan(sqrt(fdiv($s, $c)));
    $r = fdiv(sqrt($s * $c), $omega);
    $d = 2 * $omega * $this->radius;
    $h1 = fdiv(3 * $r - 1, 2 * $c);
    $h2 = fdiv(3 * $r + 1, 2 * $s);
    return $d * (1 + $this->flat * ($h1 * $s2f * $c2g - $h2 * $c2f * $s2g));
  }
}

AstroGlobe::$Earth76 = new AstroEllipsoid(6378.14, 1 / 298.257);

class AstroGlobeCoord {
  public $lat;
  public $lon;

  public function __construct($lat = 0, $lon = 0) {
    $this->lat = $lat;
    $this->lon = $lon;
  }
}

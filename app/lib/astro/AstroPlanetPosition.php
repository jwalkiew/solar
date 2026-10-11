<?php
// port of astronomia planetposition.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroSexagesimal.php';
require_once __DIR__ . '/AstroCoord.php';
require_once __DIR__ . '/AstroPrecess.php';

final class AstroPlanetPosition {
  public static function sum($t, $series) {
    $coeffs = [];
    foreach ($series as $x => $terms) {
      $coeffs[$x] = 0;
      for ($y = count($terms) - 1; $y >= 0; $y--) {
        $a = $terms[$y][0];
        $b = $terms[$y][1];
        $c = $terms[$y][2];
        $coeffs[$x] += $a * cos($b + $c * $t);
      }
    }
    return AstroBase::horner($t, ...$coeffs);
  }

  public static function toFK5($lon, $lat, $jde) {
    $T = AstroBase::J2000Century($jde);
    $Lp = $lon - AstroSexagesimal::angleFromDeg((1.397 + 0.00031 * $T) * $T);
    [$sLp, $cLp] = AstroBase::sincos($Lp);
    $L5 = $lon + AstroSexagesimal::angleFromSec(-0.09033 + 0.03916 * ($cLp + $sLp) * tan($lat));
    $B5 = $lat + AstroSexagesimal::angleFromSec(0.03916 * ($cLp - $sLp));
    return new AstroBaseCoord($L5, $B5);
  }
}

class AstroPlanet {
  public $name;
  public $type;
  public $series;

  // js constructor throws on non-object data: make() returns null instead
  public static function make($planet) {
    if (!is_array($planet)) {
      return null;
    }
    return new self($planet);
  }

  public function __construct($planet) {
    $this->name = $planet['name'] ?? null;
    $this->type = ($planet['type'] ?? null) ?: 'B';
    $this->series = $planet;
  }

  public function position2000($jde) {
    $T = AstroBase::J2000Century($jde);
    $tau = $T * 0.1;
    $lon = AstroBase::pmod(AstroPlanetPosition::sum($tau, $this->series['L']), 2 * M_PI);
    $lat = AstroPlanetPosition::sum($tau, $this->series['B']);
    $range = AstroPlanetPosition::sum($tau, $this->series['R']);

    switch ($this->type) {
      case 'B':
        return new AstroBaseCoord($lon, $lat, $range);
      case 'D':
        $eclFrom = new AstroEcliptic($lon, $lat);
        $epochFrom = AstroBase::JDEToJulianYear($jde);
        $epochTo = 2000.0;
        $eclTo = AstroPrecess::eclipticPosition($eclFrom, $epochFrom, $epochTo);
        return new AstroBaseCoord($eclTo->lon, $eclTo->lat, $range);
    }
    return null;
  }

  public function position($jde) {
    $T = AstroBase::J2000Century($jde);
    $tau = $T * 0.1;
    $lon = AstroBase::pmod(AstroPlanetPosition::sum($tau, $this->series['L']), 2 * M_PI);
    $lat = AstroPlanetPosition::sum($tau, $this->series['B']);
    $range = AstroPlanetPosition::sum($tau, $this->series['R']);

    switch ($this->type) {
      case 'B':
        $eclFrom = new AstroEcliptic($lon, $lat);
        $epochFrom = 2000.0;
        $epochTo = AstroBase::JDEToJulianYear($jde);
        $eclTo = AstroPrecess::eclipticPosition($eclFrom, $epochFrom, $epochTo);
        return new AstroBaseCoord($eclTo->lon, $eclTo->lat, $range);
      case 'D':
        return new AstroBaseCoord($lon, $lat, $range);
    }
    return null;
  }
}

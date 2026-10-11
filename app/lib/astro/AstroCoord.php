<?php
// port of astronomia coord.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroSexagesimal.php';
require_once __DIR__ . '/AstroGlobe.php';

final class AstroCoord {
  const galacticLon0 = 33 * M_PI / 180;
  const galactic0Lon1950 = self::galacticLon0;
  public static $galacticNorth;
  public static $galacticNorth1950;
}

class AstroEcliptic {
  public $lon;
  public $lat;

  public function __construct($lon = null, $lat = null) {
    if (is_object($lon)) {
      $lat = $lon->lat;
      $lon = $lon->lon;
    }
    $this->lon = (!$lon || is_nan((float) $lon)) ? 0 : $lon;
    $this->lat = (!$lat || is_nan((float) $lat)) ? 0 : $lat;
  }

  public function toEquatorial($epsilon) {
    [$epsSin, $epsCos] = AstroBase::sincos($epsilon);
    [$sBeta, $cBeta] = AstroBase::sincos($this->lat);
    [$sLambda, $cLambda] = AstroBase::sincos($this->lon);
    $ra = atan2($sLambda * $epsCos - fdiv($sBeta, $cBeta) * $epsSin, $cLambda);
    if ($ra < 0) {
      $ra += 2 * M_PI;
    }
    $dec = asin($sBeta * $epsCos + $cBeta * $epsSin * $sLambda);
    return new AstroEquatorial($ra, $dec);
  }
}

class AstroEquatorial {
  public $ra;
  public $dec;

  public function __construct($ra = 0, $dec = 0) {
    $this->ra = $ra;
    $this->dec = $dec;
  }

  public function toEcliptic($epsilon) {
    [$epsSin, $epsCos] = AstroBase::sincos($epsilon);
    [$sAlpha, $cAlpha] = AstroBase::sincos($this->ra);
    [$sDelta, $cDelta] = AstroBase::sincos($this->dec);
    $lon = atan2($sAlpha * $epsCos + fdiv($sDelta, $cDelta) * $epsSin, $cAlpha);
    $lat = asin($sDelta * $epsCos - $cDelta * $epsSin * $sAlpha);
    return new AstroEcliptic($lon, $lat);
  }

  public function toHorizontal($g, $st) {
    $H = (new AstroTime($st))->rad() - $g->lon - $this->ra;
    [$sH, $cH] = AstroBase::sincos($H);
    [$sPhi, $cPhi] = AstroBase::sincos($g->lat);
    [$sDelta, $cDelta] = AstroBase::sincos($this->dec);
    $azimuth = atan2($sH, $cH * $sPhi - fdiv($sDelta, $cDelta) * $cPhi);
    $altitude = asin($sPhi * $sDelta + $cPhi * $cDelta * $cH);
    return new AstroHorizontal($azimuth, $altitude);
  }

  public function toGalactic() {
    [$sdAlpha, $cdAlpha] = AstroBase::sincos(AstroCoord::$galacticNorth1950->ra - $this->ra);
    [$sgDelta, $cgDelta] = AstroBase::sincos(AstroCoord::$galacticNorth1950->dec);
    [$sDelta, $cDelta] = AstroBase::sincos($this->dec);
    $x = atan2($sdAlpha, $cdAlpha * $sgDelta - fdiv($sDelta, $cDelta) * $cgDelta);
    $lon = fmod(AstroCoord::galactic0Lon1950 + 1.5 * M_PI - $x, 2 * M_PI);
    $lat = asin($sDelta * $sgDelta + $cDelta * $cgDelta * $cdAlpha);
    return new AstroGalactic($lon, $lat);
  }
}

class AstroHorizontal {
  public $az;
  public $alt;

  public function __construct($az = 0, $alt = 0) {
    $this->az = $az;
    $this->alt = $alt;
  }

  public function toEquatorial($g, $st) {
    [$sA, $cA] = AstroBase::sincos($this->az);
    [$sh, $ch] = AstroBase::sincos($this->alt);
    [$sPhi, $cPhi] = AstroBase::sincos($g->lat);
    $H = atan2($sA, $cA * $sPhi + fdiv($sh, $ch) * $cPhi);
    $ra = AstroBase::pmod((new AstroTime($st))->rad() - $g->lon - $H, 2 * M_PI);
    $dec = asin($sPhi * $sh - $cPhi * $ch * $cA);
    return new AstroEquatorial($ra, $dec);
  }
}

class AstroGalactic {
  public $lon;
  public $lat;

  public function __construct($lon = 0, $lat = 0) {
    $this->lon = $lon;
    $this->lat = $lat;
  }

  public function toEquatorial() {
    [$sdLon, $cdLon] = AstroBase::sincos($this->lon - AstroCoord::galactic0Lon1950 - M_PI / 2);
    [$sgDelta, $cgDelta] = AstroBase::sincos(AstroCoord::$galacticNorth1950->dec);
    [$sb, $cb] = AstroBase::sincos($this->lat);
    $y = atan2($sdLon, $cdLon * $sgDelta - fdiv($sb, $cb) * $cgDelta);
    $ra = AstroBase::pmod($y + AstroCoord::$galacticNorth1950->ra - M_PI, 2 * M_PI);
    $dec = asin($sb * $sgDelta + $cb * $cgDelta * $cdLon);
    return new AstroEquatorial($ra, $dec);
  }
}

AstroCoord::$galacticNorth = new AstroEquatorial((new AstroRA(12, 49, 0))->rad(), 27.4 * M_PI / 180);
AstroCoord::$galacticNorth1950 = AstroCoord::$galacticNorth;

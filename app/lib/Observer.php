<?php

// observer on the Earth's surface; angles in radians, longitude east positive
final class Observer {
  public readonly float $lat;
  public readonly float $lon;
  public readonly float $rhoSin;
  public readonly float $rhoCos;

  public function __construct(float $latDeg, float $lonDeg, float $elevM) {
    $this->lat = deg2rad($latDeg);
    $this->lon = deg2rad($lonDeg);
    // parallax constants, Meeus ch. 11 (IAU 1976 ellipsoid)
    $ba = 0.99664719;
    $u = atan($ba * tan($this->lat));
    $this->rhoSin = $ba * sin($u) + $elevM / 6378140 * sin($this->lat);
    $this->rhoCos = cos($u) + $elevM / 6378140 * cos($this->lat);
  }

  // [azimuth from north, geometric altitude]; topocentric applies diurnal parallax (Meeus 40.2, 40.3)
  public function horizontal(float $ra, float $dec, float $distAu, float $gast, bool $topocentric = true): array {
    $h = $gast + $this->lon - $ra;
    if ($topocentric) {
      $sinPi = sin(deg2rad(8.794 / 3600)) / $distAu;
      $den = cos($dec) - $this->rhoCos * $sinPi * cos($h);
      $dRa = atan2(-$this->rhoCos * $sinPi * sin($h), $den);
      $dec = atan2((sin($dec) - $this->rhoSin * $sinPi) * cos($dRa), $den);
      $h -= $dRa;
    }
    $alt = asin(max(-1.0, min(1.0, sin($this->lat) * sin($dec) + cos($this->lat) * cos($dec) * cos($h))));
    $az = atan2(sin($h), cos($h) * sin($this->lat) - tan($dec) * cos($this->lat)) + M_PI;
    return [fmod($az + 2 * M_PI, 2 * M_PI), $alt];
  }

  // hour angle wrapped to (-pi, pi]
  public function hourAngle(float $ra, float $gast): float {
    $h = fmod($gast + $this->lon - $ra, 2 * M_PI);
    if ($h > M_PI) {
      $h -= 2 * M_PI;
    } else if ($h <= -M_PI) {
      $h += 2 * M_PI;
    }
    return $h;
  }

  // apparent altitude from geometric altitude: Saemundsson (Meeus 16.4) for standard conditions,
  // faded out between -1 and -3 degrees so altitude curves stay continuous
  public static function refract(float $alt): float {
    $deg = rad2deg($alt);
    if ($deg < -3) {
      return $alt;
    }
    $d = max($deg, -1.0);
    $r = 1.02 / tan(deg2rad($d + 10.3 / ($d + 5.11))) / 60;
    if ($deg < -1) {
      $r *= ($deg + 3) / 2;
    }
    return $alt + deg2rad($r);
  }
}

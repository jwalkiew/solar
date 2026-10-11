<?php
// port of astronomia sexagesimal.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroSexagesimal {
  public static function DMSToDeg($neg, $d, $m, $s) {
    $s = ((($d * 60 + $m) * 60) + $s) / 3600;
    if ($neg) {
      return -$s;
    }
    return $s;
  }

  public static function degToDMS($deg) {
    $neg = ($deg < 0);
    $deg = abs($deg);
    [$d, $s] = AstroBase::modf(fmod($deg, 360));
    [$m, $s1] = AstroBase::modf($s * 60);
    $s = AstroBase::round($s1 * 60, 10);
    return [$neg, $d, $m, $s];
  }

  public static function angleFromDeg($deg) {
    return $deg * M_PI / 180;
  }

  public static function angleFromMin($min) {
    return $min / 60 * M_PI / 180;
  }

  public static function angleFromSec($sec) {
    return $sec / 3600 * M_PI / 180;
  }

  public static function degFromAngle($angle) {
    return $angle * 180 / M_PI;
  }

  public static function secFromAngle($angle) {
    return $angle * 3600 * 180 / M_PI;
  }

  public static function secFromHourAngle($ha) {
    return $ha * 240 * 180 / M_PI;
  }
}

class AstroAngle {
  public $angle;

  public function __construct($angleOrNeg = null, $d = 0, $m = 0, $s = 0.0) {
    if (func_num_args() == 1) {
      $this->angle = (float) $angleOrNeg;
    } else {
      $this->setDMS(!!$angleOrNeg, $d, $m, $s);
    }
  }

  public function setDMS($neg = false, $d = 0, $m = 0, $s = 0.0) {
    $this->angle = (AstroSexagesimal::DMSToDeg($neg, $d, $m, $s) * M_PI / 180);
    return $this;
  }

  public function setAngle($angle) {
    $this->angle = $angle;
    return $this;
  }

  public function rad() {
    return $this->angle;
  }

  public function deg() {
    return $this->angle * 180 / M_PI;
  }

  public function toDMS() {
    return AstroSexagesimal::degToDMS($this->deg());
  }
}

class AstroHourAngle extends AstroAngle {
  public function setDMS($neg = false, $h = 0, $m = 0, $s = 0.0) {
    $this->angle = (AstroSexagesimal::DMSToDeg($neg, $h, $m, $s) * 15 * M_PI / 180);
    return $this;
  }

  public function hour() {
    return $this->angle * 12 / M_PI;
  }

  public function deg() {
    return $this->hour();
  }
}

class AstroRA extends AstroHourAngle {
  public function __construct($h = 0, $m = 0, $s = 0) {
    parent::__construct(false, $h, $m, $s);
    if (func_num_args() == 1) {
      $this->angle = $h;
    } else {
      $hr = fmod(AstroSexagesimal::DMSToDeg(false, $h, $m, $s), 24);
      $this->angle = $hr * 15 * M_PI / 180;
    }
  }

  public function hour() {
    $h = $this->angle * 12 / M_PI;
    return fmod(24 + fmod($h, 24), 24);
  }
}

class AstroTime {
  public $time;

  public function __construct($negOrTimeInSecs = null, $h = 0, $m = 0, $s = 0) {
    if (is_int($negOrTimeInSecs) || is_float($negOrTimeInSecs)) {
      $this->time = $negOrTimeInSecs;
    } else {
      $this->setHMS($negOrTimeInSecs, $h, $m, $s);
    }
  }

  public function setHMS($neg = false, $h = 0, $m = 0, $s = 0) {
    $s += (($h * 60 + $m) * 60);
    if ($neg) {
      $s = -$s;
    }
    $this->time = $s;
  }

  public function sec() {
    return $this->time;
  }

  public function min() {
    return $this->time / 60;
  }

  public function hour() {
    return $this->time / 3600;
  }

  public function day() {
    return $this->time / 3600 / 24;
  }

  public function rad() {
    return $this->time * M_PI / 12 / 3600;
  }

  public function toHMS() {
    $t = $this->time;
    $neg = ($t < 0);
    $t = ($neg ? -$t : $t);
    $q = $t / 3600;
    $h = $q < 0 ? ceil($q) : floor($q);
    $t = $t - ($h * 3600);
    $q = $t / 60;
    $m = $q < 0 ? ceil($q) : floor($q);
    $s = $t - ($m * 60);
    return [$neg, $h, $m, $s];
  }
}

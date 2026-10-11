<?php
// port of astronomia refraction.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroSexagesimal.php';

final class AstroRefraction {
  const D2R = M_PI / 180;

  public static $gt15true1;
  public static $gt15true2;
  public static $gt15app1;
  public static $gt15app2;

  public static function gt15True($h0) {
    $t = tan(M_PI / 2 - $h0);
    return self::$gt15true1 * $t - self::$gt15true2 * $t * $t * $t;
  }

  public static function gt15Apparent($h) {
    $t = tan(M_PI / 2 - $h);
    return self::$gt15app1 * $t - self::$gt15app2 * $t * $t * $t;
  }

  public static function bennett($h0) {
    $D2R = self::D2R;
    $c1 = $D2R / 60;
    $c731 = 7.31 * $D2R * $D2R;
    $c44 = 4.4 * $D2R;
    return fdiv($c1, tan($h0 + fdiv($c731, $h0 + $c44)));
  }

  public static function bennett2($h0) {
    $D2R = self::D2R;
    $cMin = 60 / $D2R;
    $c06 = 0.06 / $cMin;
    $c147 = 14.7 * $cMin * $D2R;
    $c13 = 13 * $D2R;
    $R = self::bennett($h0);
    return $R - $c06 * sin($c147 * $R + $c13);
  }

  public static function saemundsson($h) {
    $D2R = self::D2R;
    $c102 = 1.02 * $D2R / 60;
    $c103 = 10.3 * $D2R * $D2R;
    $c511 = 5.11 * $D2R;
    return fdiv($c102, tan($h + fdiv($c103, $h + $c511)));
  }
}

AstroRefraction::$gt15true1 = (new AstroAngle(false, 0, 0, 58.294))->rad();
AstroRefraction::$gt15true2 = (new AstroAngle(false, 0, 0, 0.0668))->rad();
AstroRefraction::$gt15app1 = (new AstroAngle(false, 0, 0, 58.276))->rad();
AstroRefraction::$gt15app2 = (new AstroAngle(false, 0, 0, 0.0824))->rad();

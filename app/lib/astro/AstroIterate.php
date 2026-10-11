<?php
// port of astronomia iterate.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

final class AstroIterate {
  public static function decimalPlaces($better, $start, $places, $maxIterations) {
    $d = pow(10, -$places);
    for ($i = 0; $i < $maxIterations; $i++) {
      $n = $better($start);
      if (abs($n - $start) < $d) {
        return $n;
      }
      $start = $n;
    }
    return null;
  }
}

<?php

// small numeric helpers for event refinement
final class Solver {
  // root of f in [a, b] where f(a), f(b) have opposite signs (Illinois variant of regula falsi)
  public static function root(callable $f, float $a, float $b, float $tol = 1e-6, int $maxIter = 40): float {
    $fa = $f($a);
    $fb = $f($b);
    if ($fa == 0.0) {
      return $a;
    }
    if ($fb == 0.0) {
      return $b;
    }
    $side = 0;
    for ($i = 0; $i < $maxIter; $i++) {
      $c = ($a * $fb - $b * $fa) / ($fb - $fa);
      $fc = $f($c);
      if (abs($b - $a) < $tol || $fc == 0.0) {
        return $c;
      }
      if (($fc < 0) === ($fb < 0)) {
        $b = $c;
        $fb = $fc;
        if ($side === -1) {
          $fa /= 2;
        }
        $side = -1;
      } else {
        $a = $c;
        $fa = $fc;
        if ($side === 1) {
          $fb /= 2;
        }
        $side = 1;
      }
    }
    return ($a + $b) / 2;
  }

  // [x, f(x)] minimizing a unimodal f on [a, b] (golden-section search)
  public static function minimize(callable $f, float $a, float $b, float $tol = 1e-5): array {
    $g = (sqrt(5) - 1) / 2;
    $c = $b - $g * ($b - $a);
    $d = $a + $g * ($b - $a);
    $fc = $f($c);
    $fd = $f($d);
    while (abs($b - $a) > $tol) {
      if ($fc < $fd) {
        $b = $d;
        $d = $c;
        $fd = $fc;
        $c = $b - $g * ($b - $a);
        $fc = $f($c);
      } else {
        $a = $c;
        $c = $d;
        $fc = $fd;
        $d = $a + $g * ($b - $a);
        $fd = $f($d);
      }
    }
    return $fc < $fd ? [$c, $fc] : [$d, $fd];
  }

  // angle wrapped to (-pi, pi]
  public static function wrap(float $a): float {
    $a = fmod($a, 2 * M_PI);
    if ($a > M_PI) {
      $a -= 2 * M_PI;
    } else if ($a <= -M_PI) {
      $a += 2 * M_PI;
    }
    return $a;
  }
}

<?php
// port of astronomia interpolation.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';

final class AstroInterpolation {
  const errorNot3 = 'Argument y must be length 3';
  const errorNot4 = 'Argument y must be length 4';
  const errorNot5 = 'Argument y must be length 5';
  const errorNoXRange = 'Argument x3 (or x5) cannot equal x1';
  const errorNOutOfRange = 'Interpolating factor n must be in range -1 to 1';
  const errorNoExtremum = 'No extremum in table';
  const errorExtremumOutside = 'Extremum falls outside of table';
  const errorZeroOutside = 'Zero falls outside of table';
  const errorNoConverge = 'Failure to converge';

  public static function len3ForInterpolateX($x, $x1, $xN, $y) {
    $y3 = $y;
    if (count($y) > 3) {
      $interval = ($xN - $x1) / (count($y) - 1);
      if ($interval == 0) {
        return null;
      }
      $t = ($x - $x1) / $interval + 0.5;
      $nearestX = (int) ($t < 0 ? ceil($t) : floor($t));
      if ($nearestX < 1) {
        $nearestX = 1;
      } elseif ($nearestX > count($y) - 2) {
        $nearestX = count($y) - 2;
      }
      $y3 = array_slice($y, $nearestX - 1, 3);
      $xN = $x1 + ($nearestX + 1) * $interval;
      $x1 = $x1 + ($nearestX - 1) * $interval;
    }
    return AstroLen3::make($x1, $xN, $y3);
  }

  public static function iterate($n0, $f) {
    for ($limit = 0; $limit < 50; $limit++) {
      $n1 = $f($n0);
      if (!is_finite($n1)) {
        break;
      }
      if (abs(fdiv($n1 - $n0, $n0)) < 1e-15) {
        return [$n1, true];
      }
      $n0 = $n1;
    }
    return [0, false];
  }

  public static function len4Half($y) {
    if (count($y) != 4) {
      return null;
    }
    return (9 * ($y[1] + $y[2]) - $y[0] - $y[3]) / 16;
  }

  public static function lagrange($x, $table) {
    $sum = 0;
    foreach ($table as $i => $ti) {
      $xi = $ti[0];
      $prod = 1.0;
      foreach ($table as $j => $tj) {
        if ($i != $j) {
          $xj = $tj[0];
          $prod *= fdiv($x - $xj, $xi - $xj);
        }
      }
      $sum += $ti[1] * $prod;
    }
    return $sum;
  }

  public static function lagrangePoly($table) {
    $n = count($table);
    $sum = array_fill(0, $n, 0);
    $prod = array_fill(0, $n, 0);
    $last = $n - 1;
    for ($i = 0; $i < $n; $i++) {
      $xi = ($table[$i][0] ?? 0) ?: ($table[$i]['x'] ?? 0) ?: 0;
      $yi = ($table[$i][1] ?? 0) ?: ($table[$i]['y'] ?? 0) ?: 0;
      $prod[$last] = 1;
      $den = 1.0;
      $m = $last;
      for ($j = 0; $j < $n; $j++) {
        if ($i != $j) {
          $xj = ($table[$j][0] ?? 0) ?: ($table[$j]['x'] ?? 0) ?: 0;
          $prod[$m - 1] = $prod[$m] * -$xj;
          for ($k = $m; $k < $last; $k++) {
            $prod[$k] -= $prod[$k + 1] * $xj;
          }
          $m--;
          $den *= ($xi - $xj);
        }
      }
      foreach ($prod as $j => $pj) {
        $sum[$j] += fdiv($yi * $pj, $den);
      }
    }
    return $sum;
  }

  public static function linear($x, $x1, $xN, $y) {
    $interval = ($xN - $x1) / (count($y) - 1);
    if ($interval == 0) {
      return null;
    }
    $nearestX = floor(($x - $x1) / $interval);
    if ($nearestX < 0) {
      $nearestX = 0;
    } elseif ($nearestX > count($y) - 2) {
      $nearestX = count($y) - 2;
    }
    $y2 = array_slice($y, (int) $nearestX, 2);
    $x01 = $x1 + $nearestX * $interval;
    return $y2[0] + ($y[1] - $y[0]) * ($x - $x01) / $interval;
  }
}

class AstroLen3 {
  public $x1;
  public $x3;
  public $y;
  public $a;
  public $b;
  public $c;
  public $abSum;
  public $xSum;
  public $xDiff;

  // js constructor throws on bad arguments: make() returns null instead
  public static function make($x1, $x3, $y) {
    if (count($y) != 3 || $x3 == $x1) {
      return null;
    }
    return new self($x1, $x3, $y);
  }

  public function __construct($x1, $x3, $y) {
    $this->x1 = $x1;
    $this->x3 = $x3;
    $this->y = $y;
    $this->a = $y[1] - $y[0];
    $this->b = $y[2] - $y[1];
    $this->c = $this->b - $this->a;
    $this->abSum = $this->a + $this->b;
    $this->xSum = $x3 + $x1;
    $this->xDiff = $x3 - $x1;
  }

  public function interpolateX($x) {
    $n = (2 * $x - $this->xSum) / $this->xDiff;
    return $this->interpolateN($n);
  }

  public function interpolateXStrict($x) {
    $n = (2 * $x - $this->xSum) / $this->xDiff;
    return $this->interpolateNStrict($n);
  }

  public function interpolateN($n) {
    return $this->y[1] + $n * 0.5 * ($this->abSum + $n * $this->c);
  }

  public function interpolateNStrict($n) {
    if ($n < -1 || $n > 1) {
      return null;
    }
    return $this->interpolateN($n);
  }

  public function extremum() {
    if ($this->c == 0) {
      return null;
    }
    $n = $this->abSum / (-2 * $this->c);
    if ($n < -1 || $n > 1) {
      return null;
    }
    $x = 0.5 * ($this->xSum + $this->xDiff * $n);
    $y = $this->y[1] - ($this->abSum * $this->abSum) / (8 * $this->c);
    return [$x, $y];
  }

  public function zero($strong = false) {
    if ($strong) {
      $f = function ($n0) {
        return $n0 - fdiv(2 * $this->y[1] + $n0 * ($this->abSum + $this->c * $n0),
          $this->abSum + 2 * $this->c * $n0);
      };
    } else {
      $f = function ($n0) {
        return fdiv(-2 * $this->y[1], $this->abSum + $this->c * $n0);
      };
    }
    [$n0, $ok] = AstroInterpolation::iterate(0, $f);
    if (!$ok) {
      return null;
    }
    if ($n0 > 1 || $n0 < -1) {
      return null;
    }
    return 0.5 * ($this->xSum + $this->xDiff * $n0);
  }
}

class AstroLen5 {
  public $x1;
  public $x5;
  public $y;
  public $y3;
  public $a;
  public $b;
  public $c;
  public $d;
  public $e;
  public $f;
  public $g;
  public $h;
  public $j;
  public $k;
  public $xSum;
  public $xDiff;
  public $interpCoeff;

  // js constructor throws on bad arguments: make() returns null instead
  public static function make($x1, $x5, $y) {
    if (count($y) != 5 || $x5 == $x1) {
      return null;
    }
    return new self($x1, $x5, $y);
  }

  public function __construct($x1, $x5, $y) {
    $this->x1 = $x1;
    $this->x5 = $x5;
    $this->y = $y;
    $this->y3 = $y[2];
    $this->a = $y[1] - $y[0];
    $this->b = $y[2] - $y[1];
    $this->c = $y[3] - $y[2];
    $this->d = $y[4] - $y[3];
    $this->e = $this->b - $this->a;
    $this->f = $this->c - $this->b;
    $this->g = $this->d - $this->c;
    $this->h = $this->f - $this->e;
    $this->j = $this->g - $this->f;
    $this->k = $this->j - $this->h;
    $this->xSum = $x5 + $x1;
    $this->xDiff = $x5 - $x1;
    $this->interpCoeff = [
      $this->y3,
      ($this->b + $this->c) / 2 - ($this->h + $this->j) / 12,
      $this->f / 2 - $this->k / 24,
      ($this->h + $this->j) / 12,
      $this->k / 24
    ];
  }

  public function interpolateX($x) {
    $n = (4 * $x - 2 * $this->xSum) / $this->xDiff;
    return $this->interpolateN($n);
  }

  public function interpolateXStrict($x) {
    $n = (4 * $x - 2 * $this->xSum) / $this->xDiff;
    return $this->interpolateNStrict($n);
  }

  public function interpolateN($n) {
    return AstroBase::horner($n, ...$this->interpCoeff);
  }

  public function interpolateNStrict($n) {
    if ($n < -1 || $n > 1) {
      return null;
    }
    return AstroBase::horner($n, ...$this->interpCoeff);
  }

  public function extremum() {
    $nCoeff = [
      6 * ($this->b + $this->c) - $this->h - $this->j,
      0,
      3 * ($this->h + $this->j),
      2 * $this->k
    ];
    $den = $this->k - 12 * $this->f;
    if ($den == 0) {
      return null;
    }
    [$n0, $ok] = AstroInterpolation::iterate(0, function ($n0) use ($nCoeff, $den) {
      return AstroBase::horner($n0, ...$nCoeff) / $den;
    });
    if (!$ok) {
      return null;
    }
    if ($n0 < -2 || $n0 > 2) {
      return null;
    }
    $x = 0.5 * $this->xSum + 0.25 * $this->xDiff * $n0;
    $y = AstroBase::horner($n0, ...$this->interpCoeff);
    return [$x, $y];
  }

  public function zero($strong = false) {
    if ($strong) {
      $M = $this->k / 24;
      $N = ($this->h + $this->j) / 12;
      $P = $this->f / 2 - $M;
      $Q = ($this->b + $this->c) / 2 - $N;
      $numCoeff = [$this->y3, $Q, $P, $N, $M];
      $denCoeff = [$Q, 2 * $P, 3 * $N, 4 * $M];
      $f = function ($n0) use ($numCoeff, $denCoeff) {
        return $n0 - fdiv(AstroBase::horner($n0, ...$numCoeff), AstroBase::horner($n0, ...$denCoeff));
      };
    } else {
      $numCoeff = [
        -24 * $this->y3,
        0,
        $this->k - 12 * $this->f,
        -2 * ($this->h + $this->j),
        -$this->k
      ];
      $den = 12 * ($this->b + $this->c) - 2 * ($this->h + $this->j);
      $f = function ($n0) use ($numCoeff, $den) {
        return fdiv(AstroBase::horner($n0, ...$numCoeff), $den);
      };
    }
    [$n0, $ok] = AstroInterpolation::iterate(0, $f);
    if (!$ok) {
      return null;
    }
    if ($n0 > 2 || $n0 < -2) {
      return null;
    }
    return 0.5 * $this->xSum + 0.25 * $this->xDiff * $n0;
  }
}

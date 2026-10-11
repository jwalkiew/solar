<?php
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/astro/AstroMoonPhase.php';

// principal Moon phases indexed by quarter number n (Meeus lunation k = n / 4, k = 0 at 2000-01-06)
final class MoonPhases {
  public const NAMES = ['new_moon', 'first_quarter', 'full_moon', 'last_quarter'];
  // Brown lunation number = Meeus lunation number + 953
  private const BROWN_OFFSET = 953;

  public static function jde(int $n): float {
    $q = (($n % 4) + 4) % 4;
    // a year that snaps exactly to lunation k inside AstroMoonPhase
    $y = 2000 + ($n / 4) / 12.3685;
    return match ($q) {
      0 => AstroMoonPhase::newMoon($y),
      1 => AstroMoonPhase::first($y),
      2 => AstroMoonPhase::full($y),
      default => AstroMoonPhase::last($y),
    };
  }

  public static function name(int $n): string {
    return self::NAMES[(($n % 4) + 4) % 4];
  }

  // last quarter number whose phase is at or before jde
  public static function lastBefore(float $jde): int {
    $n = (int)floor((Time::decimalYear($jde) - 2000) * 12.3685 * 4) + 2;
    while (self::jde($n) > $jde) {
      $n--;
    }
    while (self::jde($n + 1) <= $jde) {
      $n++;
    }
    return $n;
  }

  public static function previousNewMoon(float $jde): array {
    $n = self::lastBefore($jde);
    $n -= (($n % 4) + 4) % 4;
    return ['jde' => self::jde($n), 'lunation' => intdiv($n, 4) + self::BROWN_OFFSET];
  }
}

<?php
require_once __DIR__ . '/Context.php';
require_once __DIR__ . '/Time.php';
require_once __DIR__ . '/Ephemeris.php';
require_once __DIR__ . '/Observer.php';
require_once __DIR__ . '/MoonPhases.php';
require_once __DIR__ . '/Eclipses.php';
require_once __DIR__ . '/EventScan.php';
require_once __DIR__ . '/astro/AstroSolstice.php';
require_once __DIR__ . '/astro/AstroApsis.php';

// GET /api/events.php payload
final class EventsService {
  private const PHASES_AHEAD = 8;
  private const ECLIPSES = 6;
  private const APSIDES = 4;
  private const PLANET_WINDOW_DAYS = 365;
  private const MOON_CONJ_WINDOW_DAYS = 30;

  public function __construct(private Context $ctx) {
  }

  public function build(): array {
    $tz = $this->ctx->tz;
    $refJd = Time::unixToJd($this->ctx->unix);
    $refJde = Time::jdToJde($refJd);
    $obs = new Observer($this->ctx->lat, $this->ctx->lon, $this->ctx->elev);
    $scan = EventScan::forDay($this->ctx->unix);

    return [
      'location' => $this->ctx->location(),
      'timezone' => $this->ctx->timezone(),
      'reference' => Time::obj($this->ctx->unix, $tz),
      'moonPhases' => $this->moonPhases($refJde, $tz),
      'eclipses' => Eclipses::next($refJde, self::ECLIPSES, $obs, $tz),
      'seasons' => $this->seasons($tz),
      'moonApsides' => $this->moonApsides($refJde, $tz),
      'planetEvents' => $this->planetEvents($scan['planetEvents'], $refJde, $tz),
      'conjunctions' => $this->conjunctions($scan['conjunctions'], $refJde, $tz),
    ];
  }

  private function moonPhases(float $refJde, DateTimeZone $tz): array {
    $n = MoonPhases::lastBefore($refJde);
    $out = [];
    for ($i = 0; $i <= self::PHASES_AHEAD; $i++) {
      $item = ['phase' => MoonPhases::name($n + $i), 'time' => Time::objFromJde(MoonPhases::jde($n + $i), $tz)];
      if ($i === 0) {
        $item['past'] = true;
      }
      $out[] = $item;
    }
    return $out;
  }

  private function seasons(DateTimeZone $tz): array {
    $year = (int)(new DateTimeImmutable('@' . (int)floor($this->ctx->unix)))->setTimezone($tz)->format('Y');
    $earth = Ephemeris::vsop('earth');
    $out = [];
    foreach ([$year, $year + 1] as $y) {
      $out[] = ['type' => 'march_equinox', 'time' => Time::objFromJde(AstroSolstice::march2($y, $earth), $tz)];
      $out[] = ['type' => 'june_solstice', 'time' => Time::objFromJde(AstroSolstice::june2($y, $earth), $tz)];
      $out[] = ['type' => 'september_equinox', 'time' => Time::objFromJde(AstroSolstice::september2($y, $earth), $tz)];
      $out[] = ['type' => 'december_solstice', 'time' => Time::objFromJde(AstroSolstice::december2($y, $earth), $tz)];
    }
    return $out;
  }

  // perigee at integer k, apogee at k + 0.5 (Meeus ch. 50)
  private function moonApsides(float $refJde, DateTimeZone $tz): array {
    $k = floor((Time::decimalYear($refJde) - 1999.97) * 13.2555) - 1;
    $out = [];
    for ($guard = 0; count($out) < self::APSIDES && $guard < 20; $guard++, $k += 0.5) {
      $y = 1999.97 + $k / 13.2555;
      $perigee = fmod($k, 1.0) == 0.0;
      $jde = $perigee ? AstroApsis::perigee($y) : AstroApsis::apogee($y);
      if ($jde < $refJde) {
        continue;
      }
      $parallax = $perigee ? AstroApsis::perigeeParallax($y) : AstroApsis::apogeeParallax($y);
      $out[] = [
        'type' => $perigee ? 'perigee' : 'apogee',
        'time' => Time::objFromJde($jde, $tz),
        'distanceKm' => round(AstroApsis::distance($parallax)),
      ];
    }
    return $out;
  }

  private function planetEvents(array $events, float $refJde, DateTimeZone $tz): array {
    $out = [];
    foreach ($events as $e) {
      if ($e['jde'] < $refJde || $e['jde'] > $refJde + self::PLANET_WINDOW_DAYS) {
        continue;
      }
      $out[] = ['planet' => $e['planet'], 'type' => $e['type'], 'time' => Time::objFromJde($e['jde'], $tz), 'elongation' => $e['elongation']];
    }
    return $out;
  }

  private function conjunctions(array $items, float $refJde, DateTimeZone $tz): array {
    $out = [];
    foreach ($items as $c) {
      $window = $c['a'] === 'moon' ? self::MOON_CONJ_WINDOW_DAYS : self::PLANET_WINDOW_DAYS;
      if ($c['jde'] < $refJde || $c['jde'] > $refJde + $window) {
        continue;
      }
      $out[] = ['a' => $c['a'], 'b' => $c['b'], 'time' => Time::objFromJde($c['jde'], $tz), 'separation' => $c['separation']];
    }
    return $out;
  }
}

<?php
// port of astronomia julian.js, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

require_once __DIR__ . '/AstroBase.php';
require_once __DIR__ . '/AstroSexagesimal.php';
require_once __DIR__ . '/AstroDeltaT.php';

final class AstroJulian {
  const GREGORIAN0JD = 2299160.5;
  const DAYS_OF_YEAR = [0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  const SECS_OF_DAY = 86400;

  public static function CalendarToJD($y, $m, $d, $isJulian = false) {
    $b = 0;
    if ($m < 3) {
      $y--;
      $m += 12;
    }
    if (!$isJulian) {
      $a = AstroBase::floorDiv($y, 100);
      $b = 2 - $a + AstroBase::floorDiv($a, 4);
    }
    $t = $y + 4716;
    $jd = (AstroBase::floorDiv(36525 * ($t < 0 ? ceil($t) : floor($t)), 100)) +
      (AstroBase::floorDiv(306 * ($m + 1), 10) + $b) + $d - 1524.5;
    return $jd;
  }

  public static function CalendarGregorianToJD($y, $m, $d) {
    return self::CalendarToJD($y, $m, $d, false);
  }

  public static function CalendarJulianToJD($y, $m, $d) {
    return self::CalendarToJD($y, $m, $d, true);
  }

  public static function LeapYearJulian($y) {
    return fmod($y, 4) == 0;
  }

  public static function LeapYearGregorian($y) {
    return (fmod($y, 4) == 0 && fmod($y, 100) != 0) || fmod($y, 400) == 0;
  }

  public static function JDToCalendar($jd, $isJulian = false) {
    [$z, $f] = AstroBase::modf($jd + 0.5);
    $a = $z;
    if (!$isJulian) {
      $alpha = AstroBase::floorDiv($z * 100 - 186721625, 3652425);
      $a = $z + 1 + $alpha - AstroBase::floorDiv($alpha, 4);
    }
    $b = $a + 1524;
    $c = AstroBase::floorDiv($b * 100 - 12210, 36525);
    $d = AstroBase::floorDiv(36525 * $c, 100);
    $e = AstroBase::floorDiv(($b - $d) * 1e4, 306001);
    $e = $e < 0 ? ceil($e) : floor($e);
    $bd = $b - $d;
    $day = (($bd < 0 ? ceil($bd) : floor($bd)) - AstroBase::floorDiv(306001 * $e, 1e4)) + $f;
    if ($e == 14 || $e == 15) {
      $month = $e - 13;
    } else {
      $month = $e - 1;
    }
    $ci = $c < 0 ? ceil($c) : floor($c);
    if ($month < 3) {
      $year = $ci - 4715;
    } else {
      $year = $ci - 4716;
    }
    return ['year' => $year, 'month' => $month, 'day' => $day];
  }

  public static function JDToCalendarGregorian($jd) {
    return self::JDToCalendar($jd, false);
  }

  public static function JDToCalendarJulian($jd) {
    return self::JDToCalendar($jd, true);
  }

  public static function isJDCalendarGregorian($jd) {
    return ($jd >= self::GREGORIAN0JD);
  }

  public static function isCalendarGregorian($year, $month = 1, $day = 1) {
    return ($year > 1582 ||
      ($year == 1582 && $month > 10) ||
      ($year == 1582 && $month == 10 && $day >= 15)
    );
  }

  // js Date replaced by unix milliseconds (float)
  public static function JDToDate($jd) {
    return (new AstroCalendarGregorian())->fromJD($jd)->toDate();
  }

  public static function DateToJD($ms) {
    return (new AstroCalendarGregorian())->fromDate($ms)->toJD();
  }

  public static function JDEToDate($jde) {
    return (new AstroCalendarGregorian())->fromJDE($jde)->toDate();
  }

  public static function DateToJDE($ms) {
    return (new AstroCalendarGregorian())->fromDate($ms)->toJDE();
  }

  public static function MJDToJD($mjd) {
    return $mjd + AstroBase::JMod;
  }

  public static function JDToMJD($jd) {
    return $jd - AstroBase::JMod;
  }

  public static function DayOfWeek($jd) {
    $t = $jd + 1.5;
    return fmod($t < 0 ? ceil($t) : floor($t), 7);
  }

  public static function DayOfYearGregorian($y, $m, $d) {
    return self::DayOfYear($y, $m, $d < 0 ? ceil($d) : floor($d), self::LeapYearGregorian($y));
  }

  public static function DayOfYearJulian($y, $m, $d) {
    return self::DayOfYear($y, $m, $d < 0 ? ceil($d) : floor($d), self::LeapYearJulian($y));
  }

  public static function DayOfYear($y, $m, $d, $leap) {
    $k = 0;
    if ($leap && $m > 1) {
      $k = 1;
    }
    return $k + self::DAYS_OF_YEAR[$m] + ($d < 0 ? ceil($d) : floor($d));
  }

  public static function DayOfYearToCalendar($n, $leap) {
    $k = 0;
    if ($leap) {
      $k = 1;
    }
    for ($month = 1; $month <= 12; $month++) {
      if ($k + self::DAYS_OF_YEAR[$month] > $n) {
        $month = $month - 1;
        break;
      }
    }
    $day = $n - $k - (self::DAYS_OF_YEAR[$month] ?? NAN);
    return ['month' => $month, 'day' => $day];
  }

  public static function DayOfYearToCalendarGregorian($year, $n) {
    ['month' => $month, 'day' => $day] = self::DayOfYearToCalendar($n, self::LeapYearGregorian($year));
    return new AstroCalendarGregorian($year, $month, $day);
  }

  public static function DayOfYearToCalendarJulian($year, $n) {
    ['month' => $month, 'day' => $day] = self::DayOfYearToCalendar($n, self::LeapYearJulian($year));
    return new AstroCalendarJulian($year, $month, $day);
  }

  public static function pad($num, $len = 2) {
    $len = $len ?: 2;
    $neg = $num < 0 ? '-' : '';
    $num = abs($num);
    $padded = '0000' . $num;
    return $neg . substr($padded, strlen($padded) - $len, $len);
  }

  // helpers replacing js Date.UTC and the getUTC* accessors
  public static function _dateUTC($year, $month0, $day, $hour, $minute, $second, $ms) {
    $ym = $year + floor($month0 / 12);
    $mn = $month0 - 12 * floor($month0 / 12);
    $m = $mn + 1;
    $y = $m <= 2 ? $ym - 1 : $ym;
    $era = floor($y / 400);
    $yoe = $y - $era * 400;
    $mp = $m > 2 ? $m - 3 : $m + 9;
    $doy = floor((153 * $mp + 2) / 5);
    $doe = $yoe * 365 + floor($yoe / 4) - floor($yoe / 100) + $doy;
    $days = $era * 146097 + $doe - 719468 + $day - 1;
    return (float) ($days * 86400000 + $hour * 3600000 + $minute * 60000 + $second * 1000 + $ms);
  }

  public static function _utcParts($ms) {
    $sec = floor($ms / 1000);
    $msPart = $ms - $sec * 1000;
    [$y, $mo, $d, $h, $mi, $s] = array_map('intval', explode(' ', gmdate('Y n j G i s', (int) $sec)));
    return ['year' => $y, 'month' => $mo, 'day' => $d, 'hour' => $h, 'minute' => $mi, 'second' => $s,
      'ms' => $msPart];
  }
}

class AstroCalendar {
  public $year;
  public $month;
  public $day;

  public function __construct($year = null, $month = 1, $day = 1) {
    $this->year = $year;
    $this->month = $month;
    $this->day = $day;
  }

  public function getDate() {
    return [
      'year' => $this->year,
      'month' => $this->month,
      'day' => floor($this->day)
    ];
  }

  public function getTime() {
    $t = new AstroTime($this->day * AstroJulian::SECS_OF_DAY);
    [$neg, $h, $m, $_s] = $t->toHMS();
    [$s, $ms] = AstroBase::modf($_s);
    $ms = $ms * 1000;
    $ms = $ms < 0 ? ceil($ms) : floor($ms);
    return [
      'hour' => fmod($h, 24),
      'minute' => $m,
      'second' => $s,
      'millisecond' => $ms
    ];
  }

  public function toISOString() {
    ['year' => $year, 'month' => $month, 'day' => $day] = $this->getDate();
    ['hour' => $hour, 'minute' => $minute, 'second' => $second, 'millisecond' => $millisecond] = $this->getTime();
    return AstroJulian::pad($year, 4) . '-' . AstroJulian::pad($month) . '-' . AstroJulian::pad($day) . 'T' .
      AstroJulian::pad($hour) . ':' . AstroJulian::pad($minute) . ':' . AstroJulian::pad($second) . '.' .
      AstroJulian::pad($millisecond, 3) . 'Z';
  }

  public function isGregorian() {
    return AstroJulian::isCalendarGregorian($this->year, $this->month, $this->day);
  }

  // js Date replaced by unix milliseconds (float)
  public function fromDate($ms) {
    $p = AstroJulian::_utcParts($ms);
    $this->year = $p['year'];
    $this->month = $p['month'];
    $this->day = $p['day'] + ($p['hour'] + (($p['minute'] + (($p['second'] + $p['ms'] / 1000) / 60)) / 60)) / 24;
    return $this;
  }

  // returns unix milliseconds (float)
  public function toDate() {
    [$day, $fhour] = AstroBase::modf($this->day);
    [$hour, $fminute] = AstroBase::modf($fhour * 24);
    [$minute, $fsecond] = AstroBase::modf($fminute * 60);
    [$second, $fms] = AstroBase::modf($fsecond * 60);
    return AstroJulian::_dateUTC($this->year, $this->month - 1, $day, $hour, $minute, $second,
      floor($fms * 1000 + 0.5));
  }

  public function toYear() {
    [$d, $f] = AstroBase::modf($this->day);
    $n = $this->dayOfYear() - 1 + $f;
    $days = $this->isLeapYear() ? 366 : 365;
    $decYear = $this->year + ($n / $days);
    return $decYear;
  }

  public function fromYear($year) {
    [$y, $f] = AstroBase::modf($year);
    $this->year = $y;
    $days = $this->isLeapYear() ? 366 : 365;
    $dayOfYear = AstroBase::round($f * $days, 5);
    $m = 12;
    while ($m > 0 && AstroJulian::DAYS_OF_YEAR[$m] > $dayOfYear) {
      $m--;
    }
    $this->month = $m;
    $this->day = 1 + $dayOfYear - AstroJulian::DAYS_OF_YEAR[$this->month];
    return $this;
  }

  public function isLeapYear() {
    if ($this->isGregorian()) {
      return AstroJulian::LeapYearGregorian($this->year);
    } else {
      return AstroJulian::LeapYearJulian($this->year);
    }
  }

  public function toJD() {
    return AstroJulian::CalendarToJD($this->year, $this->month, $this->day, !$this->isGregorian());
  }

  public function fromJD($jd) {
    $isJulian = !AstroJulian::isJDCalendarGregorian($jd);
    ['year' => $year, 'month' => $month, 'day' => $day] = AstroJulian::JDToCalendar($jd, $isJulian);
    $this->year = $year;
    $this->month = $month;
    $this->day = $day;
    return $this;
  }

  public function fromJDE($jde) {
    $this->fromJD($jde);
    $dT = AstroDeltaT::deltaT($this->toYear());
    if ($dT === null) {
      return null;
    }
    $this->day -= $dT / 86400;
    return $this;
  }

  public function toJDE() {
    $dT = AstroDeltaT::deltaT($this->toYear());
    if ($dT === null) {
      return null;
    }
    $this->day += $dT / 86400;
    return $this->toJD();
  }

  public function midnight() {
    $this->day = floor($this->day);
    return $this;
  }

  public function noon() {
    $this->day = floor($this->day) + 0.5;
    return $this;
  }

  public function deltaT($td = false) {
    $dT = AstroDeltaT::deltaT($this->toYear());
    if ($dT === null) {
      return null;
    }
    if ($td) {
      $this->day -= $dT / 86400;
    } else {
      $this->day += $dT / 86400;
    }
    return $this;
  }

  public function dayOfWeek() {
    return AstroJulian::DayOfWeek($this->toJD());
  }

  public function dayOfYear() {
    if ($this->isGregorian()) {
      return AstroJulian::DayOfYearGregorian($this->year, $this->month, $this->day);
    } else {
      return AstroJulian::DayOfYearJulian($this->year, $this->month, $this->day);
    }
  }
}

class AstroCalendarJulian extends AstroCalendar {
  public function toJD() {
    return AstroJulian::CalendarJulianToJD($this->year, $this->month, $this->day);
  }

  public function fromJD($jd) {
    ['year' => $year, 'month' => $month, 'day' => $day] = AstroJulian::JDToCalendarJulian($jd);
    $this->year = $year;
    $this->month = $month;
    $this->day = $day;
    return $this;
  }

  public function isLeapYear() {
    return AstroJulian::LeapYearJulian($this->year);
  }

  public function dayOfYear() {
    return AstroJulian::DayOfYearJulian($this->year, $this->month, $this->day);
  }

  public function toGregorian() {
    $jd = $this->toJD();
    return (new AstroCalendarGregorian())->fromJD($jd);
  }
}

class AstroCalendarGregorian extends AstroCalendar {
  public function toJD() {
    return AstroJulian::CalendarGregorianToJD($this->year, $this->month, $this->day);
  }

  public function fromJD($jd) {
    ['year' => $year, 'month' => $month, 'day' => $day] = AstroJulian::JDToCalendarGregorian($jd);
    $this->year = $year;
    $this->month = $month;
    $this->day = $day;
    return $this;
  }

  public function isLeapYear() {
    return AstroJulian::LeapYearGregorian($this->year);
  }

  public function dayOfYear() {
    return AstroJulian::DayOfYearGregorian($this->year, $this->month, $this->day);
  }

  public function toJulian() {
    $jd = $this->toJD();
    return (new AstroCalendarJulian())->fromJD($jd);
  }
}

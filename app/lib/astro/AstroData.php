<?php
// port of astronomia data access, (c) 2013 Sonia Keys, (c) 2016 commenthol, MIT

final class AstroData {
  private static $cache = [];

  public static function get($name) {
    if (array_key_exists($name, self::$cache)) {
      return self::$cache[$name];
    }
    $dir = defined('ASTRO_DATA_DIR') ? ASTRO_DATA_DIR : __DIR__ . '/../../data';
    $file = $dir . '/' . $name . '.php';
    if (!is_file($file)) {
      error_log('astro data missing: ' . $name);
      return null;
    }
    self::$cache[$name] = require $file;
    return self::$cache[$name];
  }
}

# Solar — sky now

A small self-hosted web service showing the positions of the Sun, Moon and planets, Moon phases, eclipses and other
astronomical data for a chosen instant and place, with visualizations. Backend in plain PHP 8.4 (no framework, no
Composer), frontend in vanilla HTML/CSS/JS (no build step, nothing loaded from external URLs), packaged with Docker
Compose or as a plain Apache + PHP site.
The user interface is in Polish.

## Features

- Time: UTC, local time with offset and zone, unix timestamp, Julian Day, MJD, JDE, ΔT, Greenwich and local mean and
  apparent sidereal time, day of year, ISO week, Julian-calendar date, Julian epoch.
- Sun: position, altitude/azimuth, rise/transit/set, civil/nautical/astronomical twilight, day length, equation of
  time.
- Moon: position, phase drawing oriented as seen in the sky, illumination, age, phase name, Brown lunation number,
  distance, rise/transit/set, next phases, perigee/apogee.
- Planets Mercury–Neptune: RA/Dec, ecliptic and heliocentric coordinates, altitude/azimuth, distance and light time,
  magnitude, elongation, illuminated fraction, angular diameter, rise/transit/set.
- Events: next solar and lunar eclipses with local circumstances, equinoxes and solstices, oppositions, conjunctions
  with the Sun, greatest elongations, planet–planet and Moon–planet close approaches, pairwise angular separations.
- Visualizations: sky dome, top-down orrery, ecliptic strip with zodiac, altitude-over-the-day chart with twilight
  bands, world map with day/night terminator and subsolar/sublunar points.
- Reference time: now (live mode) or any instant; step buttons; state kept in the URL so views can be shared.
- Location: default from configuration, or typed in, taken from the browser's geolocation, or picked on the map.
  The time zone for local time is derived from the coordinates, and the nearest town (within 30 km, with the
  country outside Poland) is shown next to the coordinates.
- Algorithms page (`/algorytmy.html`, in Polish): every formula the service uses, rendered at build time with
  KaTeX; linked from the main page.

## Running

```sh
docker compose up -d --build
```

Open <http://localhost:8080>. To change the port or the default location, copy `.env.example` to `.env` and edit it.
The default location is the town of Garwolin, Poland.

| Variable             | Default             | Meaning                                               |
| -------------------- | ------------------- | ----------------------------------------------------- |
| `SOLAR_PORT`         | `8080`              | Host port of the web service (Docker only)            |
| `SOLAR_DEFAULT_LAT`  | `51.897202`         | Default latitude in degrees, north positive           |
| `SOLAR_DEFAULT_LON`  | `21.615002`         | Default longitude in degrees, east positive           |
| `SOLAR_DEFAULT_ELEV` | `100`               | Default elevation in metres above sea level           |
| `SOLAR_DEFAULT_NAME` | `Garwolin`          | Display name of the default location; empty = no name |
| `SOLAR_CACHE_DIR`    | `<tmp>/solar-cache` | Writable directory for the event cache                |

The image runs Apache as `www-data` on container port 8080. Code and generated data are baked into the image; there
are no bind mounts.

## Running without Docker (Apache + PHP hosting)

Requirements: Apache with PHP **8.4 or newer** (the code uses PHP 8.4 property hooks and does not parse on 8.3) as
mod_php or PHP-FPM. Only the core extensions `json` and `date` are needed; no database, no Composer. A request
needs about 12 MB of memory. OPcache is recommended, because the VSOP87 data files are large PHP arrays. Node.js
22 is needed once, on your own computer, to generate the data files.

1. Generate the data files locally:

   ```sh
   cd build
   npm ci
   node gen-astro-data.mjs ../app/data
   node gen-tz-data.mjs ../app/data
   node gen-places.mjs ../app/data
   node gen-land.mjs ../app/public/data/land.json
   node gen-algorithms.mjs ../docs/algorytmy.md ../app/public
   ```

2. Upload `app/` keeping its three parts side by side, with the contents of `app/public/` as the document root:

   ```text
   <account home>/
   ├── public_html/    <- contents of app/public (index.html, algorytmy.html, .htaccess, api/, css/, js/, data/,
   │                      vendor/)
   ├── lib/            <- app/lib
   └── data/           <- app/data (generated PHP data files)
   ```

   The document root directory may have any name as long as `lib/` and `data/` are its siblings.

   **Single-directory alternative**, for hosts that only allow files inside the document root (it may also be a
   subdirectory such as `/niebo/`):

   ```text
   public_html/        <- contents of app/public
   └── app/
       ├── .htaccess   <- app/.htaccess (Require all denied)
       ├── lib/        <- app/lib
       └── data/       <- app/data
   ```

   `api/bootstrap.php` uses `app/lib/` inside the document root when it exists, otherwise `../lib/` next to it.
   If the host ignores `.htaccess`, nothing leaks: the PHP files in `app/` run and return empty responses.

   File names are case-sensitive on the server (`Http.php` is not `http.php`). Some FTP clients convert names to
   lower case on upload, which makes `api/*.php` fail with HTTP 500. Disable that option, or upload a ZIP and
   extract it with the hosting panel's file manager. On Windows, create the ZIP with the built-in `tar`, which
   keeps forward slashes in paths (PowerShell 5.1 `Compress-Archive` writes backslashes, which Linux extracts as
   literal file names):

   ```powershell
   cd public_html
   tar -a -c -f ..\solar-public_html.zip .htaccess *
   ```

3. Optional configuration: `app/public/.htaccess` turns off directory listings and sends the security headers.
   It also has commented `SetEnv` lines for the variables in the table above. Without them, the built-in defaults
   (Garwolin) are used. `SetEnv` only takes effect when the host allows `.htaccess` overrides (`AllowOverride All`
   or `FileInfo`). Otherwise put the same lines in the virtual host configuration. If the system temporary
   directory is not writable or is outside `open_basedir`, set `SOLAR_CACHE_DIR` to a writable directory outside
   the document root. Without a cache the service still works, but each request for events takes a few seconds.
   Keep `display_errors = Off` (the production default): a displayed PHP warning would break the JSON responses.

Both layouts were tested on stock `php:8.4.26-apache`: in the document root and in a subdirectory, with
`.htaccess` honoured (`app/` answers 403) and ignored (`app/` PHP files return empty responses). The
single-directory layout also runs on a production shared host with PHP 8.4.22 (PHP-FPM).

### Troubleshooting

| Symptom                                        | Likely cause and fix                                                            |
| ---------------------------------------------- | ------------------------------------------------------------------------------- |
| HTTP 500 on `api/*.php`, `index.html` works    | PHP older than 8.4 (parse error in `AstroBase.php`): switch PHP to 8.4 or newer |
| HTTP 500, "Failed opening required … Http.php" | File names lower-cased on upload: re-upload preserving case (see step 2)        |
| HTTP 500 on every page                         | The host does not allow a directive in `.htaccess` (e.g. `Options`): remove it  |
| Event lists take seconds every time            | Cache directory not writable: set `SOLAR_CACHE_DIR`                             |
| Invalid JSON with a PHP warning at the start   | `display_errors` is on: turn it off                                             |

The exact error is in the PHP or Apache error log of the hosting panel.

## API

Both endpoints accept `GET` and return JSON; invalid input gives HTTP 400 with `{"error": "..."}`.

- `GET /api/sky.php`: positions and the events of the local calendar day.
- `GET /api/events.php`: upcoming events.

| Parameter    | Meaning                                                                            |
| ------------ | ---------------------------------------------------------------------------------- |
| `t`          | Reference instant: unix seconds, or ISO-8601 with `Z` or an offset                 |
| `local`      | Alternative to `t`: `YYYY-MM-DDTHH:MM[:SS]` wall-clock time in the location's zone |
| `lat`, `lon` | Location in degrees (N and E positive); both or neither                            |
| `elev`       | Elevation in metres; default: configured value for the default location, else 0    |
| `tz`         | Optional IANA zone overriding the one derived from the coordinates                 |

Without `t`/`local` the current time is used; without `lat`/`lon` the configured default location. Supported years:
1600–2400. Every instant in a response is an object with `unix`, `utc`, `local` (ISO-8601 with offset) and `jd`.
`location.place` is the nearest populated place, `{"name", "country", "distanceKm"}` with an ISO 3166-1 country
code, or `null` when none lies within 30 km.
Angles are degrees; azimuth is measured from north through east; altitude includes standard refraction.

## Layout

| Path             | Content                                                                                     |
| ---------------- | ------------------------------------------------------------------------------------------- |
| `app/public/`    | Document root: `index.html`, `css/`, `js/`, `api/*.php` (`api/bootstrap.php` finds `lib/`)  |
| `app/lib/`       | Services: request parsing, ephemeris, day events, eclipses, event search, nearest town      |
| `app/.htaccess`  | Access denial for the single-directory hosting layout (`public_html/app/`)                  |
| `app/lib/astro/` | PHP port of the astronomia library (Meeus algorithms, VSOP87)                               |
| `build/`         | Node generators run in the image build; pinned npm dependencies                             |
| `docker/`        | Apache and PHP configuration                                                                |
| `docs/`          | Formulas: `algorytmy.md` (Polish, source of the algorithms page), `ALGORITHMS.md` (English) |

`app/data/` (PHP data files), `app/public/data/land.json`, `app/public/algorytmy.html` and `app/public/vendor/`
(KaTeX stylesheet and fonts) are generated during `docker build` and listed in `.dockerignore` and `.gitignore`.
To regenerate them locally, run the commands from step 1 of the section above. The local `.env` is not tracked;
`.env.example` is the template.

## Methods and accuracy

The formulas are on the algorithms page of the service; its source is [docs/algorytmy.md](docs/algorytmy.md).
The same content in English is in [docs/ALGORITHMS.md](docs/ALGORITHMS.md); edit both together.

- Planets: full VSOP87B series with light time, aberration, FK5 correction and IAU 1980 nutation. Moon: Meeus ch. 47.
  Eclipses: Meeus ch. 54 with local circumstances of solar eclipses solved numerically from topocentric positions.
- Rise/set times use the standard altitudes (−0.833° for the Sun, −0.567° for planets, parallax-dependent for the
  Moon) and are searched within the local calendar day, so they are correct for any longitude and time zone.
- Oppositions, conjunctions, elongations and close approaches come from a daily scan refined numerically. The scan
  is location independent and cached per week in `/tmp/solar-cache`; the first request for a new week takes about
  4–7 s, later ones about 0.2 s.

Compared with JPL DE421 (via Skyfield) for seven location/time cases (Warsaw, Sydney, Svalbard in polar day and
polar night, Quito, Tromsø, Honolulu), years 2026–2027:

| Quantity                                        | Largest difference seen |
| ----------------------------------------------- | ----------------------- |
| Apparent RA/Dec of Sun, Moon, planets           | 4″                      |
| Altitude/azimuth above 2° altitude              | 5″                      |
| Rise, set, transit, twilight times              | 9 s                     |
| Moon phases, equinoxes and solstices            | 8 s                     |
| Oppositions, conjunctions, greatest elongations | 36 s                    |
| Lunar eclipse maximum / umbral magnitude        | 56 s / 0.007            |
| Local solar eclipse contacts (Warsaw)           | 5 s                     |

Solar eclipse types and magnitudes matched the NASA catalogue for 2027–2029 within 0.004, with greatest-eclipse
times within one minute.

Known limitations:

- Magnitudes use the 1984 Astronomical Almanac formulas; near inferior conjunction Mercury differs from modern
  models by up to 0.7 mag.
- Refraction assumes standard conditions and is faded out between −1° and −3° altitude to keep altitude curves
  continuous, so altitudes of bodies just below the horizon are not physical.
- Conjunctions and separations are geocentric; seen from the surface, Moon–planet distances can differ by up to 1°.
- The time zone comes from boundary data compiled into tz-lookup and may be inexact within a few kilometres of a
  border. Zones unknown to the PHP build fall back to the nautical zone of the longitude.

## Data and licences

| Component                                                                                               | Licence             | Use                                                                                  |
| ------------------------------------------------------------------------------------------------------- | ------------------- | ------------------------------------------------------------------------------------ |
| [astronomia](https://github.com/commenthol/astronomia) 4.2.0                                            | MIT                 | Algorithms ported to PHP, VSOP87 and ΔT data; see `app/lib/astro/LICENSE-astronomia` |
| [@photostructure/tz-lookup](https://github.com/photostructure/tz-lookup) 11.7.0                         | CC0-1.0             | Time zone from coordinates                                                           |
| [world-atlas](https://github.com/topojson/world-atlas) 2.0.2 (Natural Earth 1:110m)                     | ISC / public domain | World map land                                                                       |
| [topojson-client](https://github.com/topojson/topojson-client) 3.1.0                                    | ISC                 | TopoJSON conversion at build time                                                    |
| [GeoNames](https://www.geonames.org/) via [cities.json](https://github.com/lutangar/cities.json) 1.1.64 | CC BY 4.0           | Nearest town names; credited in the page footer                                      |
| [KaTeX](https://github.com/KaTeX/KaTeX) 0.16.22                                                         | MIT                 | Formula rendering and fonts of the algorithms page                                   |
| [marked](https://github.com/markedjs/marked) 15.0.12                                                    | MIT                 | Markdown conversion at build time                                                    |

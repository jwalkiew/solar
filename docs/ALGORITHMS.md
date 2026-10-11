# Astronomical algorithms

The formulas used by the service, written as they are implemented. Equation numbers "(Meeus n.m)" refer to
J. Meeus, *Astronomical Algorithms*, 2nd ed. (1998). Formulas marked "(astronomia)" are in the PHP port under
`app/lib/astro/`. The others are in the services in `app/lib/`. Long coefficient tables are not repeated here. Each
section names the file that holds them.

Notation: angles in radians unless a unit is given; $\mathrm{pmod}(x, 2\pi)$ reduces to $[0, 2\pi)$;
$\operatorname{atan2}(y, x)$ is the two-argument arctangent.

## 1. Time scales

**Julian Day from unix time** (`Time.php`). Unix time counts UTC seconds and ignores leap seconds:

$$
JD = \frac{t_\mathrm{unix}}{86400} + 2440587.5, \qquad MJD = JD - 2400000.5
$$

**Julian centuries and millennia from J2000.0** (astronomia, Meeus 12.1, 22.1):

$$
T = \frac{JDE - 2451545.0}{36525}, \qquad \tau = \frac{T}{10}
$$

**Dynamical time.** $JDE = JD + \Delta T / 86400$. ΔT in seconds depends on the decimal year $y$
(astronomia `AstroDeltaT`, data in `app/data/deltat.php`):

| Range of $y$            | ΔT                                                         |
| ----------------------- | ---------------------------------------------------------- |
| $y < -500$              | $-20 + 32u^2$, $u = (y - 1820)/100$                        |
| $-500 \le y < 500$      | polynomial of degree 6 in $y/100$ (Espenak–Meeus)          |
| $500 \le y < 1600$      | polynomial of degree 6 in $(y - 1000)/100$ (Espenak–Meeus) |
| $1600 \le y < 1657$     | $120 - 0.9808t - 0.01532t^2 + t^3/7129$, $t = y - 1600$    |
| $1657 \le y < 1973.1$   | interpolation in the historic yearly table                 |
| $1973.1 \le y < 2023.1$ | quadratic interpolation in monthly IERS values             |
| $2023.1 \le y < 2032$   | interpolation in the quarterly prediction table            |
| $2032 \le y < 2050$     | $62.92 + 32.217t + 55.89t^2$, $t = (y - 2000)/100$         |
| $2050 \le y < 2150$     | $-205.72 + 56.28u + 32u^2$, $u = (y - 1820)/100$           |
| $y \ge 2150$            | $-20 + 32u^2$                                              |

The service converts with $y = 2000 + (JD - 2451545)/365.25$, and back from JDE to JD with one step of the same
formula.

**Julian epoch:** $J = 2000 + (JDE - 2451545)/365.25$.

## 2. Sidereal time

Mean sidereal time at Greenwich, IAU 1982 (astronomia `AstroSidereal`, Meeus 12.2). Let $JD_0$ be the Julian Day
at the preceding 0h UT, $f$ the fraction of the day since then, and $T_0$ the value of $T$ at $JD_0$:

$$
\theta_0 = 24110.54841 + 8640184.812866\,T_0 + 0.093104\,T_0^2 - 0.0000062\,T_0^3
         + 1.00273790935 \cdot 86400\,f \quad [\mathrm{s}]
$$

Apparent sidereal time adds the equation of the equinoxes, $\Delta\psi\cos\varepsilon$ (in seconds of time:
divide the angle in arcseconds by 15). Local times: $\mathrm{LMST} = \mathrm{GMST} + \lambda/15^\circ$ hours and
$\mathrm{LAST} = \mathrm{GAST} + \lambda/15^\circ$, with longitude $\lambda$ positive east.

## 3. Nutation and obliquity

Fundamental arguments in degrees (astronomia `AstroNutation`, Meeus 22):

$$
\begin{aligned}
D &= 297.85036 + 445267.111480\,T - 0.0019142\,T^2 + T^3/189474 \\
M &= 357.52772 + 35999.050340\,T - 0.0001603\,T^2 - T^3/300000 \\
M' &= 134.96298 + 477198.867398\,T + 0.0086972\,T^2 + T^3/56250 \\
F &= 93.27191 + 483202.017538\,T - 0.0036825\,T^2 + T^3/327270 \\
\Omega &= 125.04452 - 1934.136261\,T + 0.0020708\,T^2 + T^3/450000
\end{aligned}
$$

The nutation in longitude and obliquity is a sum over the 63 rows of Meeus table 22.A, with coefficients in units of
0.0001″:

$$
\Delta\psi = \sum_i (S_i + S'_i T)\sin a_i, \qquad \Delta\varepsilon = \sum_i (C_i + C'_i T)\cos a_i, \qquad
a_i = d_i D + m_i M + n_i M' + f_i F + \omega_i \Omega
$$

Mean and true obliquity of the ecliptic (Meeus 22.2):

$$
\varepsilon_0 = 23^\circ 26' 21.448'' - 46.815''\,T - 0.00059''\,T^2 + 0.001813''\,T^3, \qquad
\varepsilon = \varepsilon_0 + \Delta\varepsilon
$$

## 4. Coordinate transformations

**Ecliptic to equatorial** (Meeus 13.3, 13.4):

$$
\alpha = \operatorname{atan2}(\sin\lambda\cos\varepsilon - \tan\beta\sin\varepsilon,\ \cos\lambda), \qquad
\delta = \arcsin(\sin\beta\cos\varepsilon + \cos\beta\sin\varepsilon\sin\lambda)
$$

**Parallax constants** of the observer at geodetic latitude $\varphi$ and height $h$ in metres
(`Observer.php`, Meeus 11):

$$
u = \arctan(0.99664719\tan\varphi), \quad
\rho\sin\varphi' = 0.99664719\sin u + \frac{h}{6378140}\sin\varphi, \quad
\rho\cos\varphi' = \cos u + \frac{h}{6378140}\cos\varphi
$$

**Topocentric correction** (diurnal parallax, Meeus 40.2, 40.3). Here $H = \theta_\mathrm{GAST} + \lambda - \alpha$
is the hour angle and $\Delta$ the distance in AU:

$$
\begin{aligned}
\sin\pi &= \frac{\sin 8.794''}{\Delta} \\
\Delta\alpha &= \operatorname{atan2}(-\rho\cos\varphi'\sin\pi\sin H,\ \cos\delta - \rho\cos\varphi'\sin\pi\cos H) \\
\delta' &= \operatorname{atan2}\big((\sin\delta - \rho\sin\varphi'\sin\pi)\cos\Delta\alpha,\
           \cos\delta - \rho\cos\varphi'\sin\pi\cos H\big), \qquad H' = H - \Delta\alpha
\end{aligned}
$$

**Horizontal coordinates**, with azimuth measured from north through east (Meeus 13.5, 13.6 shifted by 180°):

$$
h = \arcsin(\sin\varphi\sin\delta' + \cos\varphi\cos\delta'\cos H'), \qquad
A = \operatorname{atan2}(\sin H',\ \cos H'\sin\varphi - \tan\delta'\cos\varphi) + 180^\circ
$$

**Refraction** (`Observer.php`, Saemundsson, Meeus 16.4). The formula gives the refraction in arcminutes for a
geometric altitude $h$ in degrees:

$$
R = \frac{1.02}{\tan\left(h + \dfrac{10.3}{h + 5.11}\right)}
$$

The apparent altitude is $h + R$ for $h \ge -1^\circ$. Between −1° and −3°, $R(-1^\circ)$ is scaled linearly down
to zero so that altitude curves stay continuous. Below −3° no refraction is applied.

**Angular separation** (haversine form, accurate for small angles):

$$
d = 2\arcsin\sqrt{\sin^2\frac{\delta_2 - \delta_1}{2} + \cos\delta_1\cos\delta_2\sin^2\frac{\alpha_2 - \alpha_1}{2}}
$$

The same formula is used with ecliptic $(\lambda, \beta)$ and with horizontal $(A, h)$ pairs.

## 5. Planets and the Sun from VSOP87

**VSOP87B series** (astronomia `AstroPlanetPosition`, Meeus 32). Each of the heliocentric longitude $L$, latitude
$B$ (J2000 ecliptic) and radius vector $R$ (AU) is

$$
X = \sum_{n=0}^{5} \tau^n \sum_i A_{n,i}\cos(B_{n,i} + C_{n,i}\,\tau)
$$

The full series of Bretagnon and Francou are used; the coefficients are in `app/data/vsop87b-<planet>.php`.

**Precession to the ecliptic of date** (Meeus 21.5, 21.7), where $t$ is the number of Julian centuries from J2000
to the date:

$$
\begin{aligned}
\eta &= (47.0029'' - 0.03302''\,t + 0.000060''\,t^2)\,t \\
\Pi &= 174.876384^\circ - 869.8089''\,t + 0.03536''\,t^2 \\
p &= (5029.0966'' + 1.11113''\,t - 0.000006''\,t^2)\,t
\end{aligned}
$$

$$
\begin{aligned}
A &= \cos\eta\cos\beta\sin(\Pi - \lambda) - \sin\eta\sin\beta, \qquad B = \cos\beta\cos(\Pi - \lambda), \\
C &= \cos\eta\sin\beta + \sin\eta\cos\beta\sin(\Pi - \lambda), \qquad
\lambda' = p + \Pi - \operatorname{atan2}(A, B), \quad \beta' = \arcsin C
\end{aligned}
$$

**Geocentric position of a planet** (`Ephemeris.php`, following Meeus 33). The heliocentric position of the
planet is $(L, B, R)$ and that of the Earth $(L_0, B_0, R_0)$:

$$
\begin{aligned}
x &= R\cos B\cos L - R_0\cos B_0\cos L_0 \\
y &= R\cos B\sin L - R_0\cos B_0\sin L_0 \\
z &= R\sin B - R_0\sin B_0
\end{aligned}
\qquad
\Delta = \sqrt{x^2 + y^2 + z^2}
$$

The light time is $\tau_\mathrm{l} = 0.0057755183\,\Delta$ days (Meeus 33.3). The planet is recomputed at
$JDE - \tau_\mathrm{l}$, and then $\lambda = \operatorname{atan2}(y, x)$,
$\beta = \operatorname{atan2}(z, \sqrt{x^2 + y^2})$.

**Aberration** (astronomia `AstroApparent`, Meeus 23.2). Here $\kappa = 20.49552''$, $\odot$ is the Sun's true
longitude, $e$ the eccentricity of the Earth's orbit, and
$\varpi = 102.93735^\circ + 1.71946^\circ T + 0.00046^\circ T^2$ the longitude of its perihelion:

$$
\Delta\lambda = \kappa\,\frac{e\cos(\varpi - \lambda) - \cos(\odot - \lambda)}{\cos\beta}, \qquad
\Delta\beta = -\kappa\sin\beta\,\big(\sin(\odot - \lambda) - e\sin(\varpi - \lambda)\big)
$$

**Conversion to FK5** (Meeus 32.3), with $\lambda' = \lambda - 1.397^\circ T - 0.00031^\circ T^2$:

$$
\Delta\lambda_\mathrm{FK5} = -0.09033'' + 0.03916''(\cos\lambda' + \sin\lambda')\tan\beta, \qquad
\Delta\beta_\mathrm{FK5} = 0.03916''(\cos\lambda' - \sin\lambda')
$$

**Apparent position:** $\lambda_\mathrm{app} = \lambda + \Delta\lambda + \Delta\lambda_\mathrm{FK5} + \Delta\psi$
and $\beta_\mathrm{app} = \beta + \Delta\beta + \Delta\beta_\mathrm{FK5}$, converted to $(\alpha, \delta)$ with the
true obliquity $\varepsilon$.

**The Sun** (astronomia `AstroSolar`, Meeus 25.9, 25.10). The geocentric position is the Earth's heliocentric one
reversed, $\odot = L_0 + 180^\circ$, $\beta_\odot = -B_0$, with the FK5 correction above. The apparent longitude
adds nutation and aberration:

$$
\lambda_\mathrm{app} = \odot - 0.09033'' + \Delta\psi - \frac{20.4898''}{R_0}
$$

**Equation of time** (astronomia `AstroEqTime`, Meeus 28.1, 28.2):

$$
L_0 = 280.4664567^\circ + 360007.6982779^\circ\tau + 0.03032028^\circ\tau^2 + \frac{\tau^3}{49931}
      - \frac{\tau^4}{15300} - \frac{\tau^5}{2000000}
$$

$$
E = L_0 - 0.0057183^\circ - \alpha_\odot + \Delta\psi\cos\varepsilon
$$

$E$ is reduced to $(-180^\circ, 180^\circ]$ and reported in minutes ($1^\circ = 4$ min).

**Sub-solar and sub-lunar points:** latitude $= \delta$, longitude $= \alpha - \theta_\mathrm{GAST}$ reduced to
$(-180^\circ, 180^\circ]$.

## 6. Physical data of the planets

**Phase angle and illuminated fraction** (astronomia `AstroIllum`, Meeus 41.1, 41.2). Here $r$ is the
planet–Sun distance, $\Delta$ the planet–Earth distance and $R$ the Earth–Sun distance:

$$
\cos i = \frac{r^2 + \Delta^2 - R^2}{2 r \Delta}, \qquad k = \frac{1 + \cos i}{2}
$$

**Visual magnitude**, Astronomical Almanac 1984 (Meeus 41), with $i$ in degrees:

| Planet  | $V$                                                                                                 |
| ------- | --------------------------------------------------------------------------------------------------- |
| Mercury | $-0.42 + 5\log_{10}(r\Delta) + 0.0380\,i - 0.000273\,i^2 + 0.000002\,i^3$                           |
| Venus   | $-4.40 + 5\log_{10}(r\Delta) + 0.0009\,i + 0.000239\,i^2 - 0.00000065\,i^3$                         |
| Mars    | $-1.52 + 5\log_{10}(r\Delta) + 0.016\,i$                                                            |
| Jupiter | $-9.40 + 5\log_{10}(r\Delta) + 0.005\,i$                                                            |
| Saturn  | $-8.88 + 5\log_{10}(r\Delta) + 0.044\,\lvert\Delta U\rvert - 2.60\sin\lvert B\rvert + 1.25\sin^2 B$ |
| Uranus  | $-7.19 + 5\log_{10}(r\Delta)$                                                                       |
| Neptune | $-6.87 + 5\log_{10}(r\Delta)$                                                                       |

For Saturn, $B$ is the Saturnicentric latitude of the Earth referred to the ring plane and $\Delta U$ (degrees) the
difference between the Saturnicentric longitudes of the Sun and the Earth (Meeus 45, astronomia `AstroSaturnRing`).
The Sun is given $V = -26.74$.

**Angular diameter** (Meeus 55): semidiameter $s = s_0/\Delta$, with $s_0$ the semidiameter at 1 AU (Sun 959.63″,
Mercury 3.36″, Venus 8.41″, Mars 4.68″, Jupiter 98.44″, Saturn 82.73″, Uranus 35.02″, Neptune 33.50″). The
reported diameter is $2s$.

**Elongation:** $\psi$ is the angular separation between the planet and the Sun. It is signed positive (east,
evening sky) when $\mathrm{pmod}(\lambda - \odot, 2\pi) < \pi$.

## 7. The Moon

**Position** (astronomia `AstroMoonPosition`, Meeus 47; the 60 + 60 terms of tables 47.A and 47.B are in that
file). Arguments in degrees:

$$
\begin{aligned}
L' &= 218.3164477 + 481267.88123421\,T - 0.0015786\,T^2 + T^3/538841 - T^4/65194000 \\
D &= 297.8501921 + 445267.1114034\,T - 0.0018819\,T^2 + T^3/545868 - T^4/113065000 \\
M &= 357.5291092 + 35999.0502909\,T - 0.0001536\,T^2 + T^3/24490000 \\
M' &= 134.9633964 + 477198.8675055\,T + 0.0087414\,T^2 + T^3/69699 - T^4/14712000 \\
F &= 93.2720950 + 483202.0175233\,T - 0.0036539\,T^2 - T^3/3526000 + T^4/863310000 \\
A_1 &= 119.75 + 131.849\,T, \qquad A_2 = 53.09 + 479264.290\,T, \qquad A_3 = 313.45 + 481266.484\,T \\
E &= 1 - 0.002516\,T - 0.0000074\,T^2
\end{aligned}
$$

$$
\begin{aligned}
\Sigma_l &= \sum_i l_i E^{\lvert m_i\rvert}\sin(d_i D + m_i M + m'_i M' + f_i F)
            + 3958\sin A_1 + 1962\sin(L' - F) + 318\sin A_2 \\
\Sigma_r &= \sum_i r_i E^{\lvert m_i\rvert}\cos(d_i D + m_i M + m'_i M' + f_i F) \\
\Sigma_b &= \sum_i b_i E^{\lvert m_i\rvert}\sin(d_i D + m_i M + m'_i M' + f_i F) - 2235\sin L' + 382\sin A_3 \\
         &\quad + 175\sin(A_1 - F) + 175\sin(A_1 + F) + 127\sin(L' - M') - 115\sin(L' + M')
\end{aligned}
$$

$$
\lambda = L' + \Sigma_l \cdot 10^{-6\,\circ}, \qquad \beta = \Sigma_b \cdot 10^{-6\,\circ}, \qquad
\Delta = 385000.56 + \Sigma_r/1000\ \mathrm{km}
$$

The apparent longitude adds the nutation, $\lambda_\mathrm{app} = \lambda + \Delta\psi$ (`Ephemeris.php`).

**Phase angle and illuminated fraction** (`SkyService.php`, Meeus 48.2, 48.3, 48.1). Here $\psi$ is the
geocentric elongation of the Moon from the Sun, and $R$ and $\Delta$ are the Sun and Moon distances in km:

$$
i = \operatorname{atan2}(R\sin\psi,\ \Delta - R\cos\psi), \qquad k = \frac{1 + \cos i}{2}
$$

**Position angle of the bright limb** (Meeus 48.5), measured from north through east:

$$
\chi = \operatorname{atan2}\big(\cos\delta_\odot\sin(\alpha_\odot - \alpha),\
       \sin\delta_\odot\cos\delta - \cos\delta_\odot\sin\delta\cos(\alpha_\odot - \alpha)\big)
$$

The browser rotates the phase drawing by $\chi - q$, where $q$ is the parallactic angle (Meeus 14.1), so the
drawing has the zenith up:

$$
q = \operatorname{atan2}\big(\sin H,\ \tan\varphi\cos\delta - \sin\delta\cos H\big)
$$

**Other Moon values:**

- Magnitude: $V = -12.73 + 0.026\lvert i\rvert + 4\cdot 10^{-9}\, i^4$, with $i$ in degrees.
- Age: $JDE$ minus the $JDE$ of the previous new Moon (section 9).
- Brown lunation number: $\mathrm{BLN} = k + 953$, where $k$ is the Meeus lunation number of the previous new Moon.
- Phase name from $E = \mathrm{pmod}(\lambda_\mathrm{Moon} - \odot, 360^\circ)$:

  | Range of $E$                   | Name            |
  | ------------------------------ | --------------- |
  | $< 7^\circ$ or $\ge 353^\circ$ | new Moon        |
  | $7^\circ$–$83^\circ$           | waxing crescent |
  | $83^\circ$–$97^\circ$          | first quarter   |
  | $97^\circ$–$173^\circ$         | waxing gibbous  |
  | $173^\circ$–$187^\circ$        | full Moon       |
  | $187^\circ$–$263^\circ$        | waning gibbous  |
  | $263^\circ$–$277^\circ$        | last quarter    |
  | $277^\circ$–$353^\circ$        | waning crescent |

## 8. Rise, set, transit and twilight

The search runs over the local calendar day $[t_0, t_1)$ of the reference instant (23 or 25 hours on DST change
days) (`SkyService.php`).

1. Apparent geocentric $(\alpha, \delta, \Delta)$ of each body is computed at nodes every 3 hours, from 3 hours
   before $t_0$ to 3 hours after $t_1$. RA is unwrapped across 0/360°.
2. Between the nodes, the values are interpolated with the 4-point Lagrange formula, using the nodes
   $t_{j-1} \ldots t_{j+2}$ around $t$:

   $$
   f(t) = \sum_{j} f_j \prod_{m \ne j} \frac{t - t_m}{t_j - t_m}
   $$

3. Rising and setting are the zeros of $g(t) = h_\mathrm{geo}(t) - h_0$, where $h_\mathrm{geo}$ is the geometric
   geocentric altitude. The standard altitudes (Meeus 15) are:

   | Body or event         | $h_0$                                                                     |
   | --------------------- | ------------------------------------------------------------------------- |
   | Sun                   | $-0.8333^\circ$                                                           |
   | Planets               | $-0.5667^\circ$                                                           |
   | Moon                  | $0.7275\,\pi - 0.5667^\circ$, $\pi = \arcsin(6378.14/\Delta_\mathrm{km})$ |
   | Civil twilight        | $-6^\circ$                                                                |
   | Nautical twilight     | $-12^\circ$                                                               |
   | Astronomical twilight | $-18^\circ$                                                               |

   $g$ is sampled every 600 s. A change of sign is refined by 11 bisections, to about 0.3 s. Rise is the first
   upward crossing and set the first downward one. For twilight, dawn is the first upward crossing and dusk the
   last downward one.
4. Transit is the zero of the hour angle $H \in (-\pi, \pi]$ passing from negative to positive.
5. Day length is $t_\mathrm{set} - t_\mathrm{rise}$. If the Sun sets before it rises within the day,
   it is $(t_\mathrm{set} - t_0) + (t_1 - t_\mathrm{rise})$. In polar day it is the length of the local day, in
   polar night 0.

The altitude curves use the same interpolated positions, with the topocentric correction and refraction applied.

## 9. Moon phases

(astronomia `AstroMoonPhase`, Meeus 49.) The lunation number is $k$, with $k = 0$ at the new Moon of 2000-01-06.
The phase is new for an integer $k$, first quarter for $k + 0.25$, full for $k + 0.5$ and last quarter for
$k + 0.75$. Then $T = k/1236.85$ and

$$
JDE_\mathrm{mean} = 2451550.09766 + 29.530588861\,k + 0.00015437\,T^2 - 0.000000150\,T^3 + 0.00000000073\,T^4
$$

The true phase adds periodic terms in $M$, $M'$, $F$, $\Omega$ and $E$ (Meeus 49.4–49.9):

- 25 terms for the new and full Moon, with different coefficient sets, e.g. $-0.40720\sin M'$ for the new Moon.
- 25 terms for the quarters.
- 14 planetary arguments $A_1 \ldots A_{14}$.
- For the quarters only, $\pm W$, with $+$ for the first quarter and $-$ for the last:

$$
W = 0.00306 - 0.00038\,E\cos M + 0.00026\cos M' - 0.00002\cos(M' - M) + 0.00002\cos(M' + M) + 0.00002\cos 2F
$$

The service calls the functions with $y = 2000 + k/12.3685$, which selects exactly lunation $k$
(`MoonPhases.php`).

## 10. Equinoxes and solstices

(astronomia `AstroSolstice`, Meeus 27.) A starting estimate comes from the polynomial of Meeus table 27.B in
$Y = (y - 2000)/1000$. It is iterated until the correction falls below $5\cdot 10^{-6}$ day:

$$
JDE \leftarrow JDE + 58\sin(\lambda_0 - \lambda_\odot(JDE))
$$

Here $\lambda_0$ is 0°, 90°, 180° or 270°, and $\lambda_\odot$ is the apparent longitude of the Sun from VSOP87.

## 11. Lunar perigee and apogee

(astronomia `AstroApsis`, Meeus 50.) $k$ is an integer for perigee and $k + 0.5$ for apogee, $T = k/1325.55$, and

$$
JDE_\mathrm{mean} = 2451534.6698 + 27.55454989\,k - 0.0006691\,T^2 - 0.000001098\,T^3 + 0.0000000052\,T^4
$$

The time and the equatorial horizontal parallax $\pi$ get the periodic corrections of Meeus tables 50.A and 50.B.
The distance is $\Delta = 6378.137\ \mathrm{km}/\sin\pi$. The service uses $y = 1999.97 + k/13.2555$.

## 12. Eclipses

(astronomia `AstroEclipse`, Meeus 54.) For lunation $k$ (an integer for solar eclipses, $k + 0.5$ for lunar
ones), $T = k/1236.85$:

$$
F = 160.7108^\circ + 390.67050284^\circ k - 0.0016118^\circ T^2 - 0.00000227^\circ T^3 + 0.000000011^\circ T^4
$$

No eclipse is possible when $\lvert\sin F\rvert > 0.36$. Otherwise the time of maximum is the mean phase plus the
corrections of Meeus 54.1, with $F_1 = F - 0.02665^\circ\sin\Omega$, and

$$
\begin{aligned}
P &= 0.2070\,E\sin M + 0.0024\,E\sin 2M - 0.0392\sin M' + 0.0116\sin 2M' - 0.0073\,E\sin(M' + M) \\
  &\quad + 0.0067\,E\sin(M' - M) + 0.0118\sin 2F_1 \\
Q &= 5.2207 - 0.0048\,E\cos M + 0.0020\,E\cos 2M - 0.3299\cos M' - 0.0060\,E\cos(M' + M) + 0.0041\,E\cos(M' - M) \\
W &= \lvert\cos F_1\rvert, \qquad \gamma = (P\cos F_1 + Q\sin F_1)(1 - 0.0048\,W) \\
u &= 0.0059 + 0.0046\,E\cos M - 0.0182\cos M' + 0.0004\cos 2M' - 0.0005\cos(M + M')
\end{aligned}
$$

$\gamma$ is the least distance from the shadow axis to the Earth's centre and $u$ the radius of the umbral cone in
the fundamental plane, both in equatorial Earth radii.

**Solar eclipses.** There is no eclipse when $\lvert\gamma\rvert > 1.5433 + u$. Otherwise:

| Condition                                                             | Type                                                     |
| --------------------------------------------------------------------- | -------------------------------------------------------- |
| $\lvert\gamma\rvert > 0.9972$ and the umbra misses the Earth          | partial                                                  |
| $0.9972 < \lvert\gamma\rvert < \min(1.026,\ 0.9972 + \lvert u\rvert)$ | non-central total ($u < 0$) or annular ($u > 0$)         |
| $\lvert\gamma\rvert < 0.9972$, $u < 0$                                | total                                                    |
| $\lvert\gamma\rvert < 0.9972$, $u > 0.0047$                           | annular                                                  |
| $\lvert\gamma\rvert < 0.9972$, $0 \le u \le 0.0047$                   | hybrid if $u < 0.00464\sqrt{1 - \gamma^2}$, else annular |

The magnitude of a partial eclipse is (Meeus 54.2)

$$
\mathrm{mag} = \frac{1.5433 + u - \lvert\gamma\rvert}{0.5461 + 2u}
$$

For central and umbral eclipses, the service reports the ratio of the apparent diameters seen from the point of
the Earth nearest to the shadow axis (`Eclipses.php`):

$$
\mathrm{mag} = \frac{\arcsin\big(1737.4/(\Delta_\mathrm{Moon} - 6378.14\sqrt{1 - \gamma^2})\big)}
                    {\arcsin(696000/\Delta_\odot)} \qquad (\mathrm{km})
$$

**Local circumstances of a solar eclipse** (`Eclipses.php`) are solved numerically within ±4 h of the maximum.
The topocentric separation $d$ of the centres is computed from the horizontal coordinates of both bodies. The
semidiameters are

$$
s_\odot = \arcsin\frac{696000}{\Delta_\odot}, \qquad
s_\mathrm{M} = \arcsin\frac{1737.4}{\Delta_\mathrm{M} - 6378.14\sin h_\mathrm{M}}
$$

and the local magnitude is

$$
m(t) = \frac{s_\odot + s_\mathrm{M} - d}{2 s_\odot}
$$

- The maximum is found by golden-section search, and the start and end are the zeros of $m(t)$.
- The eclipse is visible if the Sun's geometric altitude exceeds −0.8333° at some moment between start and end.
- The obscured fraction of the solar disc comes from the area of the lens where the two discs overlap:

$$
\begin{aligned}
\mathrm{obsc} = \frac{1}{\pi s_\odot^2}\Big[\, &s_\odot^2\arccos\frac{d^2 + s_\odot^2 - s_\mathrm{M}^2}{2 d s_\odot}
  + s_\mathrm{M}^2\arccos\frac{d^2 + s_\mathrm{M}^2 - s_\odot^2}{2 d s_\mathrm{M}} \\
  &- \tfrac12\sqrt{(-d + s_\odot + s_\mathrm{M})(d + s_\odot - s_\mathrm{M})(d - s_\odot + s_\mathrm{M})
     (d + s_\odot + s_\mathrm{M})}\,\Big]
\end{aligned}
$$

  This holds when the discs overlap partly. When one disc lies inside the other, the fraction is 1 if the Moon is
  larger, otherwise $(s_\mathrm{M}/s_\odot)^2$.

**Lunar eclipses** (Meeus 54.3, 54.4). The penumbral radius is $\rho = 1.2848 + u$ and the umbral radius
$\sigma = 0.7403 - u$:

$$
\mathrm{mag}_\mathrm{umbral} = \frac{1.0128 - u - \lvert\gamma\rvert}{0.545}, \qquad
\mathrm{mag}_\mathrm{penumbral} = \frac{1.5573 + u - \lvert\gamma\rvert}{0.545}
$$

The type is total for an umbral magnitude above 1, partial for one above 0, penumbral for a non-negative penumbral
magnitude, and otherwise there is no eclipse. With $n = 0.5458 + 0.04\cos M'$ (Earth radii per hour), the
semidurations in days are

$$
s_\mathrm{pen} = \frac{\sqrt{(1.5573 + u)^2 - \gamma^2}}{24\,n}, \quad
s_\mathrm{par} = \frac{\sqrt{(1.0128 - u)^2 - \gamma^2}}{24\,n}, \quad
s_\mathrm{tot} = \frac{\sqrt{(0.4678 - u)^2 - \gamma^2}}{24\,n}
$$

The contacts are $P_1, P_4 = t_\mathrm{max} \mp s_\mathrm{pen}$, $U_1, U_4 = t_\mathrm{max} \mp s_\mathrm{par}$ and
$U_2, U_3 = t_\mathrm{max} \mp s_\mathrm{tot}$. Local visibility samples the refracted altitude of the Moon every
5 minutes over the umbral phase, or the penumbral phase for penumbral eclipses.

## 13. Planetary events and close approaches

(`EventScan.php`.) Geometric geocentric positions are computed daily over 375 days without light time or
aberration, using VSOP87. Candidates are then refined with the full apparent positions of section 5:

| Event                           | Condition                                                                                          |
| ------------------------------- | -------------------------------------------------------------------------------------------------- |
| Conjunction with the Sun        | $\lambda_\mathrm{p} - \odot = 0$                                                                   |
| Inferior / superior conjunction | conjunction of Mercury or Venus with $\Delta_\mathrm{p} < R_\odot$ / $\Delta_\mathrm{p} > R_\odot$ |
| Opposition                      | $\lambda_\mathrm{p} - \odot = 180^\circ$ (outer planets)                                           |
| Greatest elongation (E/W)       | local maximum of $\psi$ for Mercury and Venus; east if $\lambda_\mathrm{p} - \odot > 0$            |
| Planet–planet close approach    | local minimum of the separation $d$, $d \le 5^\circ$                                               |
| Moon–planet close approach      | local minimum of $d$ (Moon sampled every 2 h), $d \le 3^\circ$, next 30 days                       |

Zeros are refined by the Illinois variant of regula falsi:

$$
c = \frac{a f(b) - b f(a)}{f(b) - f(a)}
$$

When the same end point is kept twice in a row, its function value is halved. The iteration stops when the
interval is shorter than $10^{-5}$ day.

Extrema are refined by golden-section search around the sample $t$ ($\pm 1$ day; $\pm 2$ hours for the Moon),
with ratio $(\sqrt5 - 1)/2$ and a tolerance of $10^{-4}$ day. Differences of longitude are taken modulo $2\pi$
into $(-\pi, \pi]$.

## 14. Time zone from coordinates

(`TimeZoneLookup.php`, a port of tz-lookup.) The globe is split into a 48 × 24 grid of cells, each 7.5° wide and
7.5° high. The cell gives a 2-character code: either a zone index directly, or a node of a quadtree. Each node is
split into 2 × 2 children, and the position inside the node, doubled each step, chooses the child until a leaf
with a zone index is reached. The leaves hold IANA zone names derived from the timezone-boundary-builder data. At
sea they are the nautical zones `Etc/GMT±n`. The UTC offset, its abbreviation and DST status for the instant then
come from PHP's time zone database.

## 15. Nearest town

(`Places.php`, GeoNames data from the cities.json package.) About 171 thousand places are split into 10° × 10°
tiles. For a point $(\varphi, \lambda)$, the tiles covering $\pm 30\ \mathrm{km}/111\ \mathrm{km}$ of latitude and
$\pm 30/(111\cos\varphi)$ of longitude are loaded, and distances use the haversine formula on a sphere of radius
6371 km:

$$
s = 2R\arcsin\sqrt{\sin^2\frac{\varphi_2 - \varphi_1}{2}
    + \cos\varphi_1\cos\varphi_2\sin^2\frac{\lambda_2 - \lambda_1}{2}}
$$

The result is the nearest place within 30 km. The page adds the distance when it exceeds 3 km and, outside Poland,
the Polish country name from the browser's `Intl.DisplayNames`.

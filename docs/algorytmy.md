# Algorytmy i wzory

Wzory, których używa serwis, zapisane tak, jak są zaimplementowane. Numery „(Meeus n.m)” odnoszą się do książki
J. Meeus, *Astronomical Algorithms*, wyd. 2 (1998). Obliczenia oznaczone „(astronomia)” pochodzą z portu biblioteki
astronomia do PHP (`app/lib/astro/`). Pozostałe są w usługach serwisu (`app/lib/`). Długie tabele współczynników nie
są tu powtórzone. Przy każdym temacie podany jest plik, który je zawiera.

Oznaczenia: kąty w radianach, o ile nie podano jednostki; $\mathrm{pmod}(x, 2\pi)$ sprowadza kąt do przedziału
$[0, 2\pi)$; $\operatorname{atan2}(y, x)$ to arcus tangens dwóch argumentów.

## 1. Skale czasu

**Dzień juliański z czasu uniksowego** (`Time.php`). Czas uniksowy liczy sekundy UTC i pomija sekundy przestępne:

$$
JD = \frac{t_\mathrm{unix}}{86400} + 2440587.5, \qquad MJD = JD - 2400000.5
$$

**Stulecia i tysiąclecia juliańskie od epoki J2000.0** (astronomia, Meeus 12.1, 22.1):

$$
T = \frac{JDE - 2451545.0}{36525}, \qquad \tau = \frac{T}{10}
$$

**Czas dynamiczny.** $JDE = JD + \Delta T / 86400$. Poprawka ΔT w sekundach zależy od roku dziesiętnego $y$
(astronomia `AstroDeltaT`, dane w `app/data/deltat.php`):

| Zakres $y$              | ΔT                                                       |
| ----------------------- | -------------------------------------------------------- |
| $y < -500$              | $-20 + 32u^2$, $u = (y - 1820)/100$                      |
| $-500 \le y < 500$      | wielomian 6. stopnia od $y/100$ (Espenak–Meeus)          |
| $500 \le y < 1600$      | wielomian 6. stopnia od $(y - 1000)/100$ (Espenak–Meeus) |
| $1600 \le y < 1657$     | $120 - 0.9808t - 0.01532t^2 + t^3/7129$, $t = y - 1600$  |
| $1657 \le y < 1973.1$   | interpolacja w historycznej tabeli rocznej               |
| $1973.1 \le y < 2023.1$ | interpolacja kwadratowa w miesięcznych wartościach IERS  |
| $2023.1 \le y < 2032$   | interpolacja w kwartalnej tabeli prognoz                 |
| $2032 \le y < 2050$     | $62.92 + 32.217t + 55.89t^2$, $t = (y - 2000)/100$       |
| $2050 \le y < 2150$     | $-205.72 + 56.28u + 32u^2$, $u = (y - 1820)/100$         |
| $y \ge 2150$            | $-20 + 32u^2$                                            |

Serwis przyjmuje $y = 2000 + (JD - 2451545)/365.25$. Z JDE do JD wraca jednym krokiem tego samego wzoru.

**Epoka juliańska:** $J = 2000 + (JDE - 2451545)/365.25$.

## 2. Czas gwiazdowy

Średni czas gwiazdowy w Greenwich według IAU 1982 (astronomia `AstroSidereal`, Meeus 12.2). Niech $JD_0$ będzie
dniem juliańskim o poprzedzającej godzinie 0h UT, $f$ ułamkiem doby, który od niej upłynął, a $T_0$ wartością $T$
dla $JD_0$:

$$
\theta_0 = 24110.54841 + 8640184.812866\,T_0 + 0.093104\,T_0^2 - 0.0000062\,T_0^3
         + 1.00273790935 \cdot 86400\,f \quad [\mathrm{s}]
$$

Prawdziwy czas gwiazdowy dodaje równanie punktów równonocy $\Delta\psi\cos\varepsilon$ (w sekundach czasu: kąt w
sekundach łuku dzielony przez 15). Czasy lokalne: $\mathrm{LMST} = \mathrm{GMST} + \lambda/15^\circ$ godzin oraz
$\mathrm{LAST} = \mathrm{GAST} + \lambda/15^\circ$, gdzie długość geograficzna $\lambda$ jest dodatnia na wschód.

## 3. Nutacja i nachylenie ekliptyki

Argumenty podstawowe w stopniach (astronomia `AstroNutation`, Meeus 22):

$$
\begin{aligned}
D &= 297.85036 + 445267.111480\,T - 0.0019142\,T^2 + T^3/189474 \\
M &= 357.52772 + 35999.050340\,T - 0.0001603\,T^2 - T^3/300000 \\
M' &= 134.96298 + 477198.867398\,T + 0.0086972\,T^2 + T^3/56250 \\
F &= 93.27191 + 483202.017538\,T - 0.0036825\,T^2 + T^3/327270 \\
\Omega &= 125.04452 - 1934.136261\,T + 0.0020708\,T^2 + T^3/450000
\end{aligned}
$$

Nutacja w długości i w nachyleniu to suma po 63 wierszach tabeli 22.A Meeusa, ze współczynnikami w jednostkach
0.0001″:

$$
\Delta\psi = \sum_i (S_i + S'_i T)\sin a_i, \qquad \Delta\varepsilon = \sum_i (C_i + C'_i T)\cos a_i, \qquad
a_i = d_i D + m_i M + n_i M' + f_i F + \omega_i \Omega
$$

Średnie i prawdziwe nachylenie ekliptyki (Meeus 22.2):

$$
\varepsilon_0 = 23^\circ 26' 21.448'' - 46.815''\,T - 0.00059''\,T^2 + 0.001813''\,T^3, \qquad
\varepsilon = \varepsilon_0 + \Delta\varepsilon
$$

## 4. Przeliczenia współrzędnych

**Z ekliptycznych na równikowe** (Meeus 13.3, 13.4):

$$
\alpha = \operatorname{atan2}(\sin\lambda\cos\varepsilon - \tan\beta\sin\varepsilon,\ \cos\lambda), \qquad
\delta = \arcsin(\sin\beta\cos\varepsilon + \cos\beta\sin\varepsilon\sin\lambda)
$$

**Stałe paralaksy** obserwatora na szerokości geodezyjnej $\varphi$ i wysokości $h$ w metrach (`Observer.php`,
Meeus 11):

$$
u = \arctan(0.99664719\tan\varphi), \quad
\rho\sin\varphi' = 0.99664719\sin u + \frac{h}{6378140}\sin\varphi, \quad
\rho\cos\varphi' = \cos u + \frac{h}{6378140}\cos\varphi
$$

**Poprawka topocentryczna** (paralaksa dobowa, Meeus 40.2, 40.3). Tu $H = \theta_\mathrm{GAST} + \lambda - \alpha$
to kąt godzinny, a $\Delta$ odległość w AU:

$$
\begin{aligned}
\sin\pi &= \frac{\sin 8.794''}{\Delta} \\
\Delta\alpha &= \operatorname{atan2}(-\rho\cos\varphi'\sin\pi\sin H,\ \cos\delta - \rho\cos\varphi'\sin\pi\cos H) \\
\delta' &= \operatorname{atan2}\big((\sin\delta - \rho\sin\varphi'\sin\pi)\cos\Delta\alpha,\
           \cos\delta - \rho\cos\varphi'\sin\pi\cos H\big), \qquad H' = H - \Delta\alpha
\end{aligned}
$$

**Współrzędne horyzontalne**, z azymutem liczonym od północy przez wschód (Meeus 13.5, 13.6 przesunięte o 180°):

$$
h = \arcsin(\sin\varphi\sin\delta' + \cos\varphi\cos\delta'\cos H'), \qquad
A = \operatorname{atan2}(\sin H',\ \cos H'\sin\varphi - \tan\delta'\cos\varphi) + 180^\circ
$$

**Refrakcja** (`Observer.php`, wzór Saemundssona, Meeus 16.4). Wzór daje refrakcję w minutach łuku dla wysokości
geometrycznej $h$ w stopniach:

$$
R = \frac{1.02}{\tan\left(h + \dfrac{10.3}{h + 5.11}\right)}
$$

Wysokość pozorna to $h + R$ dla $h \ge -1^\circ$. Między −1° a −3° wartość $R(-1^\circ)$ maleje liniowo do zera,
żeby krzywe wysokości nie miały skoku. Poniżej −3° refrakcji się nie dodaje.

**Odległość kątowa** (postać haversine, dokładna także dla małych kątów):

$$
d = 2\arcsin\sqrt{\sin^2\frac{\delta_2 - \delta_1}{2} + \cos\delta_1\cos\delta_2\sin^2\frac{\alpha_2 - \alpha_1}{2}}
$$

Tego samego wzoru używa się dla par współrzędnych ekliptycznych $(\lambda, \beta)$ i horyzontalnych $(A, h)$.

## 5. Planety i Słońce z teorii VSOP87

**Szeregi VSOP87B** (astronomia `AstroPlanetPosition`, Meeus 32). Heliocentryczna długość $L$, szerokość $B$
(ekliptyka J2000) i promień wodzący $R$ (AU) mają postać

$$
X = \sum_{n=0}^{5} \tau^n \sum_i A_{n,i}\cos(B_{n,i} + C_{n,i}\,\tau)
$$

Serwis używa pełnych szeregów Bretagnona i Francou. Współczynniki są w plikach `app/data/vsop87b-<planeta>.php`.

**Precesja do ekliptyki daty** (Meeus 21.5, 21.7), gdzie $t$ to liczba stuleci juliańskich od J2000 do danej
chwili:

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

**Pozycja geocentryczna planety** (`Ephemeris.php`, według Meeus 33). Pozycja heliocentryczna planety to
$(L, B, R)$, a Ziemi $(L_0, B_0, R_0)$:

$$
\begin{aligned}
x &= R\cos B\cos L - R_0\cos B_0\cos L_0 \\
y &= R\cos B\sin L - R_0\cos B_0\sin L_0 \\
z &= R\sin B - R_0\sin B_0
\end{aligned}
\qquad
\Delta = \sqrt{x^2 + y^2 + z^2}
$$

Czas biegu światła to $\tau_\mathrm{l} = 0.0057755183\,\Delta$ doby (Meeus 33.3). Pozycję planety liczy się
ponownie dla chwili $JDE - \tau_\mathrm{l}$, po czym $\lambda = \operatorname{atan2}(y, x)$ oraz
$\beta = \operatorname{atan2}(z, \sqrt{x^2 + y^2})$.

**Aberracja** (astronomia `AstroApparent`, Meeus 23.2). Tu $\kappa = 20.49552''$, $\odot$ to prawdziwa długość
Słońca, $e$ mimośród orbity Ziemi, a
$\varpi = 102.93735^\circ + 1.71946^\circ T + 0.00046^\circ T^2$ długość jej peryhelium:

$$
\Delta\lambda = \kappa\,\frac{e\cos(\varpi - \lambda) - \cos(\odot - \lambda)}{\cos\beta}, \qquad
\Delta\beta = -\kappa\sin\beta\,\big(\sin(\odot - \lambda) - e\sin(\varpi - \lambda)\big)
$$

**Przejście do układu FK5** (Meeus 32.3), gdzie $\lambda' = \lambda - 1.397^\circ T - 0.00031^\circ T^2$:

$$
\Delta\lambda_\mathrm{FK5} = -0.09033'' + 0.03916''(\cos\lambda' + \sin\lambda')\tan\beta, \qquad
\Delta\beta_\mathrm{FK5} = 0.03916''(\cos\lambda' - \sin\lambda')
$$

**Pozycja pozorna:** $\lambda_\mathrm{app} = \lambda + \Delta\lambda + \Delta\lambda_\mathrm{FK5} + \Delta\psi$
oraz $\beta_\mathrm{app} = \beta + \Delta\beta + \Delta\beta_\mathrm{FK5}$, przeliczone na $(\alpha, \delta)$ przy
prawdziwym nachyleniu ekliptyki $\varepsilon$.

**Słońce** (astronomia `AstroSolar`, Meeus 25.9, 25.10). Pozycja geocentryczna to odwrócona pozycja heliocentryczna
Ziemi, $\odot = L_0 + 180^\circ$, $\beta_\odot = -B_0$, z powyższą poprawką FK5. Długość pozorna dodaje nutację i
aberrację:

$$
\lambda_\mathrm{app} = \odot - 0.09033'' + \Delta\psi - \frac{20.4898''}{R_0}
$$

**Równanie czasu** (astronomia `AstroEqTime`, Meeus 28.1, 28.2):

$$
L_0 = 280.4664567^\circ + 360007.6982779^\circ\tau + 0.03032028^\circ\tau^2 + \frac{\tau^3}{49931}
      - \frac{\tau^4}{15300} - \frac{\tau^5}{2000000}
$$

$$
E = L_0 - 0.0057183^\circ - \alpha_\odot + \Delta\psi\cos\varepsilon
$$

$E$ sprowadza się do przedziału $(-180^\circ, 180^\circ]$ i podaje w minutach ($1^\circ = 4$ min).

**Punkty podsłoneczny i podksiężycowy:** szerokość $= \delta$, długość $= \alpha - \theta_\mathrm{GAST}$
sprowadzona do $(-180^\circ, 180^\circ]$.

## 6. Dane fizyczne planet

**Kąt fazowy i oświetlona część tarczy** (astronomia `AstroIllum`, Meeus 41.1, 41.2). Tu $r$ to odległość
planety od Słońca, $\Delta$ od Ziemi, a $R$ odległość Ziemi od Słońca:

$$
\cos i = \frac{r^2 + \Delta^2 - R^2}{2 r \Delta}, \qquad k = \frac{1 + \cos i}{2}
$$

**Jasność wizualna** według Astronomical Almanac 1984 (Meeus 41), z $i$ w stopniach:

| Planeta | $V$                                                                                                 |
| ------- | --------------------------------------------------------------------------------------------------- |
| Merkury | $-0.42 + 5\log_{10}(r\Delta) + 0.0380\,i - 0.000273\,i^2 + 0.000002\,i^3$                           |
| Wenus   | $-4.40 + 5\log_{10}(r\Delta) + 0.0009\,i + 0.000239\,i^2 - 0.00000065\,i^3$                         |
| Mars    | $-1.52 + 5\log_{10}(r\Delta) + 0.016\,i$                                                            |
| Jowisz  | $-9.40 + 5\log_{10}(r\Delta) + 0.005\,i$                                                            |
| Saturn  | $-8.88 + 5\log_{10}(r\Delta) + 0.044\,\lvert\Delta U\rvert - 2.60\sin\lvert B\rvert + 1.25\sin^2 B$ |
| Uran    | $-7.19 + 5\log_{10}(r\Delta)$                                                                       |
| Neptun  | $-6.87 + 5\log_{10}(r\Delta)$                                                                       |

Dla Saturna $B$ to saturnocentryczna szerokość Ziemi względem płaszczyzny pierścieni, a $\Delta U$ (w stopniach)
różnica saturnocentrycznych długości Słońca i Ziemi (Meeus 45, astronomia `AstroSaturnRing`). Słońcu przypisano
$V = -26.74$.

**Średnica kątowa** (Meeus 55): półśrednica $s = s_0/\Delta$, gdzie $s_0$ to półśrednica w odległości 1 AU (Słońce
959.63″, Merkury 3.36″, Wenus 8.41″, Mars 4.68″, Jowisz 98.44″, Saturn 82.73″, Uran 35.02″, Neptun 33.50″).
Podawana średnica to $2s$.

**Elongacja:** $\psi$ to odległość kątowa planety od Słońca. Ma znak dodatni (na wschód od Słońca, widoczność
wieczorna), gdy $\mathrm{pmod}(\lambda - \odot, 2\pi) < \pi$.

## 7. Księżyc

**Pozycja** (astronomia `AstroMoonPosition`, Meeus 47; po 60 wyrazów z tabel 47.A i 47.B jest w tym pliku).
Argumenty w stopniach:

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

Długość pozorna dodaje nutację: $\lambda_\mathrm{app} = \lambda + \Delta\psi$ (`Ephemeris.php`).

**Kąt fazowy i oświetlona część tarczy** (`SkyService.php`, Meeus 48.2, 48.3, 48.1). Tu $\psi$ to geocentryczna
elongacja Księżyca od Słońca, a $R$ i $\Delta$ to odległości Słońca i Księżyca w km:

$$
i = \operatorname{atan2}(R\sin\psi,\ \Delta - R\cos\psi), \qquad k = \frac{1 + \cos i}{2}
$$

**Kąt pozycyjny jasnego brzegu** (Meeus 48.5), liczony od północy przez wschód:

$$
\chi = \operatorname{atan2}\big(\cos\delta_\odot\sin(\alpha_\odot - \alpha),\
       \sin\delta_\odot\cos\delta - \cos\delta_\odot\sin\delta\cos(\alpha_\odot - \alpha)\big)
$$

Przeglądarka obraca rysunek fazy o $\chi - q$, gdzie $q$ to kąt paralaktyczny (Meeus 14.1). Dzięki temu na rysunku
zenit jest u góry:

$$
q = \operatorname{atan2}\big(\sin H,\ \tan\varphi\cos\delta - \sin\delta\cos H\big)
$$

**Inne wielkości dla Księżyca:**

- Jasność: $V = -12.73 + 0.026\lvert i\rvert + 4\cdot 10^{-9}\, i^4$, z $i$ w stopniach.
- Wiek: $JDE$ minus $JDE$ poprzedniego nowiu (rozdział 9).
- Numer lunacji Browna: $\mathrm{BLN} = k + 953$, gdzie $k$ to numer lunacji Meeusa poprzedniego nowiu.
- Nazwa fazy wynika z $E = \mathrm{pmod}(\lambda_\mathrm{K} - \odot, 360^\circ)$:

  | Zakres $E$                      | Faza               |
  | ------------------------------- | ------------------ |
  | $< 7^\circ$ lub $\ge 353^\circ$ | nów                |
  | $7^\circ$–$83^\circ$            | przybywający sierp |
  | $83^\circ$–$97^\circ$           | pierwsza kwadra    |
  | $97^\circ$–$173^\circ$          | przybywający garb  |
  | $173^\circ$–$187^\circ$         | pełnia             |
  | $187^\circ$–$263^\circ$         | ubywający garb     |
  | $263^\circ$–$277^\circ$         | ostatnia kwadra    |
  | $277^\circ$–$353^\circ$         | ubywający sierp    |

## 8. Wschód, górowanie, zachód i zmierzch

Poszukiwanie obejmuje lokalną dobę kalendarzową $[t_0, t_1)$ chwili odniesienia (23 lub 25 godzin w dniu zmiany
czasu) (`SkyService.php`).

1. Pozorne geocentryczne $(\alpha, \delta, \Delta)$ każdego ciała liczy się w węzłach co 3 godziny, od 3 godzin
   przed $t_0$ do 3 godzin po $t_1$. Rektascensję „rozwija się” przez przejście 0/360°.
2. Między węzłami wartości interpoluje się 4-punktowym wzorem Lagrange'a z węzłów $t_{j-1} \ldots t_{j+2}$ wokół
   $t$:

   $$
   f(t) = \sum_{j} f_j \prod_{m \ne j} \frac{t - t_m}{t_j - t_m}
   $$

3. Wschód i zachód to miejsca zerowe funkcji $g(t) = h_\mathrm{geo}(t) - h_0$, gdzie $h_\mathrm{geo}$ to
   geometryczna wysokość geocentryczna. Wysokości standardowe (Meeus 15):

   | Ciało lub zjawisko     | $h_0$                                                                     |
   | ---------------------- | ------------------------------------------------------------------------- |
   | Słońce                 | $-0.8333^\circ$                                                           |
   | Planety                | $-0.5667^\circ$                                                           |
   | Księżyc                | $0.7275\,\pi - 0.5667^\circ$, $\pi = \arcsin(6378.14/\Delta_\mathrm{km})$ |
   | Zmierzch cywilny       | $-6^\circ$                                                                |
   | Zmierzch żeglarski     | $-12^\circ$                                                               |
   | Zmierzch astronomiczny | $-18^\circ$                                                               |

   Funkcję $g$ próbkuje się co 600 s. Zmianę znaku zawęża się 11 krokami bisekcji, do około 0.3 s. Wschód to
   pierwsze przejście w górę, a zachód pierwsze przejście w dół. Dla zmierzchów świt to pierwsze przejście w górę,
   a zmierzch ostatnie przejście w dół.
4. Górowanie to miejsce zerowe kąta godzinnego $H \in (-\pi, \pi]$ przechodzącego od wartości ujemnych do
   dodatnich.
5. Długość dnia to $t_\mathrm{z} - t_\mathrm{w}$. Jeśli w danej dobie Słońce zachodzi przed wschodem, jest to
   $(t_\mathrm{z} - t_0) + (t_1 - t_\mathrm{w})$. W dniu polarnym to długość całej lokalnej doby, a w nocy
   polarnej 0.

Krzywe wysokości korzystają z tych samych interpolowanych pozycji, z poprawką topocentryczną i refrakcją.

## 9. Fazy Księżyca

(astronomia `AstroMoonPhase`, Meeus 49.) Numer lunacji to $k$, przy czym $k = 0$ to nów z 6 stycznia 2000. Nów
odpowiada całkowitemu $k$, pierwsza kwadra $k + 0.25$, pełnia $k + 0.5$, a ostatnia kwadra $k + 0.75$. Wtedy
$T = k/1236.85$, a średni moment fazy to

$$
JDE_0 = 2451550.09766 + 29.530588861\,k + 0.00015437\,T^2 - 0.000000150\,T^3 + 0.00000000073\,T^4
$$

Prawdziwy moment fazy dodaje wyrazy okresowe zależne od $M$, $M'$, $F$, $\Omega$ i $E$ (Meeus 49.4–49.9):

- 25 wyrazów dla nowiu i pełni, z osobnymi zestawami współczynników, np. $-0.40720\sin M'$ dla nowiu;
- 25 wyrazów dla kwadr;
- 14 argumentów planetarnych $A_1 \ldots A_{14}$;
- tylko dla kwadr $\pm W$: $+$ dla pierwszej, $-$ dla ostatniej kwadry.

$$
W = 0.00306 - 0.00038\,E\cos M + 0.00026\cos M' - 0.00002\cos(M' - M) + 0.00002\cos(M' + M) + 0.00002\cos 2F
$$

Serwis wywołuje te funkcje z $y = 2000 + k/12.3685$, co wybiera dokładnie lunację $k$ (`MoonPhases.php`).

## 10. Równonoce i przesilenia

(astronomia `AstroSolstice`, Meeus 27.) Przybliżenie początkowe daje wielomian z tabeli 27.B Meeusa w zmiennej
$Y = (y - 2000)/1000$. Iteruje się je, dopóki poprawka nie spadnie poniżej $5\cdot 10^{-6}$ doby:

$$
JDE \leftarrow JDE + 58\sin(\lambda_0 - \lambda_\odot(JDE))
$$

Tu $\lambda_0$ wynosi 0°, 90°, 180° lub 270°, a $\lambda_\odot$ to pozorna długość Słońca z VSOP87.

## 11. Perygeum i apogeum Księżyca

(astronomia `AstroApsis`, Meeus 50.) Dla perygeum $k$ jest liczbą całkowitą, dla apogeum $k + 0.5$,
$T = k/1325.55$, a średni moment to

$$
JDE_0 = 2451534.6698 + 27.55454989\,k - 0.0006691\,T^2 - 0.000001098\,T^3 + 0.0000000052\,T^4
$$

Czas i równikowa paralaksa horyzontalna $\pi$ dostają poprawki okresowe z tabel 50.A i 50.B Meeusa. Odległość to
$\Delta = 6378.137\ \mathrm{km}/\sin\pi$. Serwis podaje $y = 1999.97 + k/13.2555$.

## 12. Zaćmienia

(astronomia `AstroEclipse`, Meeus 54.) Dla lunacji $k$ (całkowitej dla zaćmień Słońca, $k + 0.5$ dla zaćmień
Księżyca) mamy $T = k/1236.85$ oraz

$$
F = 160.7108^\circ + 390.67050284^\circ k - 0.0016118^\circ T^2 - 0.00000227^\circ T^3 + 0.000000011^\circ T^4
$$

Zaćmienie nie jest możliwe, gdy $\lvert\sin F\rvert > 0.36$. W przeciwnym razie moment maksimum to średnia faza z
poprawkami z równania 54.1 Meeusa, przy $F_1 = F - 0.02665^\circ\sin\Omega$, a dalej

$$
\begin{aligned}
P &= 0.2070\,E\sin M + 0.0024\,E\sin 2M - 0.0392\sin M' + 0.0116\sin 2M' - 0.0073\,E\sin(M' + M) \\
  &\quad + 0.0067\,E\sin(M' - M) + 0.0118\sin 2F_1 \\
Q &= 5.2207 - 0.0048\,E\cos M + 0.0020\,E\cos 2M - 0.3299\cos M' - 0.0060\,E\cos(M' + M) + 0.0041\,E\cos(M' - M) \\
W &= \lvert\cos F_1\rvert, \qquad \gamma = (P\cos F_1 + Q\sin F_1)(1 - 0.0048\,W) \\
u &= 0.0059 + 0.0046\,E\cos M - 0.0182\cos M' + 0.0004\cos 2M' - 0.0005\cos(M + M')
\end{aligned}
$$

$\gamma$ to najmniejsza odległość osi cienia od środka Ziemi, a $u$ promień stożka cienia w płaszczyźnie
podstawowej, oba w promieniach równikowych Ziemi.

**Zaćmienia Słońca.** Nie ma zaćmienia, gdy $\lvert\gamma\rvert > 1.5433 + u$. W przeciwnym razie:

| Warunek                                                               | Typ                                                                 |
| --------------------------------------------------------------------- | ------------------------------------------------------------------- |
| $\lvert\gamma\rvert > 0.9972$, a cień nie dosięga Ziemi               | częściowe                                                           |
| $0.9972 < \lvert\gamma\rvert < \min(1.026,\ 0.9972 + \lvert u\rvert)$ | niecentralne całkowite ($u < 0$) lub obrączkowe ($u > 0$)           |
| $\lvert\gamma\rvert < 0.9972$, $u < 0$                                | całkowite                                                           |
| $\lvert\gamma\rvert < 0.9972$, $u > 0.0047$                           | obrączkowe                                                          |
| $\lvert\gamma\rvert < 0.9972$, $0 \le u \le 0.0047$                   | hybrydowe, gdy $u < 0.00464\sqrt{1 - \gamma^2}$, inaczej obrączkowe |

Wielkość fazy zaćmienia częściowego (Meeus 54.2):

$$
\mathrm{mag} = \frac{1.5433 + u - \lvert\gamma\rvert}{0.5461 + 2u}
$$

Dla zaćmień centralnych i niecentralnych całkowitych lub obrączkowych serwis podaje stosunek średnic kątowych
Księżyca i Słońca widzianych z punktu Ziemi najbliższego osi cienia (`Eclipses.php`):

$$
\mathrm{mag} = \frac{\arcsin\big(1737.4/(\Delta_\mathrm{K} - 6378.14\sqrt{1 - \gamma^2})\big)}
                    {\arcsin(696000/\Delta_\odot)} \qquad (\mathrm{km})
$$

**Lokalny przebieg zaćmienia Słońca** (`Eclipses.php`) wyznacza się numerycznie w oknie ±4 h od maksimum.
Topocentryczną odległość środków tarcz $d$ liczy się ze współrzędnych horyzontalnych obu ciał. Półśrednice to

$$
s_\odot = \arcsin\frac{696000}{\Delta_\odot}, \qquad
s_\mathrm{K} = \arcsin\frac{1737.4}{\Delta_\mathrm{K} - 6378.14\sin h_\mathrm{K}}
$$

a lokalna wielkość fazy

$$
m(t) = \frac{s_\odot + s_\mathrm{K} - d}{2 s_\odot}
$$

- Maksimum znajduje się metodą złotego podziału, a początek i koniec to miejsca zerowe $m(t)$.
- Zaćmienie jest widoczne, jeśli geometryczna wysokość Słońca przekracza −0.8333° w jakiejkolwiek chwili między
  początkiem a końcem.
- Zakrytą część tarczy Słońca daje pole soczewki, w której nakładają się obie tarcze:

$$
\begin{aligned}
\mathrm{obsc} = \frac{1}{\pi s_\odot^2}\Big[\, &s_\odot^2\arccos\frac{d^2 + s_\odot^2 - s_\mathrm{K}^2}{2 d s_\odot}
  + s_\mathrm{K}^2\arccos\frac{d^2 + s_\mathrm{K}^2 - s_\odot^2}{2 d s_\mathrm{K}} \\
  &- \tfrac12\sqrt{(-d + s_\odot + s_\mathrm{K})(d + s_\odot - s_\mathrm{K})(d - s_\odot + s_\mathrm{K})
     (d + s_\odot + s_\mathrm{K})}\,\Big]
\end{aligned}
$$

Wzór dotyczy częściowego nałożenia tarcz. Gdy jedna tarcza leży w całości wewnątrz drugiej, zakryta część wynosi 1,
jeśli Księżyc jest większy, a w przeciwnym razie $(s_\mathrm{K}/s_\odot)^2$.

**Zaćmienia Księżyca** (Meeus 54.3, 54.4). Promień półcienia to $\rho = 1.2848 + u$, a promień cienia
$\sigma = 0.7403 - u$. Wielkości fazy w cieniu (U) i w półcieniu (P) to:

$$
\mathrm{mag}_\mathrm{U} = \frac{1.0128 - u - \lvert\gamma\rvert}{0.545}, \qquad
\mathrm{mag}_\mathrm{P} = \frac{1.5573 + u - \lvert\gamma\rvert}{0.545}
$$

Zaćmienie jest całkowite, gdy wielkość fazy w cieniu przekracza 1, częściowe, gdy przekracza 0, i półcieniowe,
gdy nieujemna jest wielkość fazy w półcieniu. W pozostałych przypadkach zaćmienia nie ma. Przy
$n = 0.5458 + 0.04\cos M'$ (promienie Ziemi na godzinę) połowy czasów trwania faz półcieniowej (P), cieniowej (U) i całkowitej (C)
w dobach to

$$
s_\mathrm{P} = \frac{\sqrt{(1.5573 + u)^2 - \gamma^2}}{24\,n}, \quad
s_\mathrm{U} = \frac{\sqrt{(1.0128 - u)^2 - \gamma^2}}{24\,n}, \quad
s_\mathrm{C} = \frac{\sqrt{(0.4678 - u)^2 - \gamma^2}}{24\,n}
$$

Kontakty to $P_1, P_4 = t_\mathrm{max} \mp s_\mathrm{P}$, $U_1, U_4 = t_\mathrm{max} \mp s_\mathrm{U}$ oraz
$U_2, U_3 = t_\mathrm{max} \mp s_\mathrm{C}$. Lokalną widoczność sprawdza się, próbkując co 5 minut wysokość
Księżyca z refrakcją w trakcie fazy cieniowej, a przy zaćmieniu półcieniowym w trakcie fazy półcieniowej.

## 13. Zjawiska planetarne i zbliżenia

(`EventScan.php`.) Geometryczne pozycje geocentryczne z VSOP87, bez czasu biegu światła i aberracji, liczy się
codziennie przez 375 dni. Kandydatów zawęża się potem pełnymi pozycjami pozornymi z rozdziału 5:

| Zjawisko                           | Warunek                                                                                           |
| ---------------------------------- | ------------------------------------------------------------------------------------------------- |
| Koniunkcja ze Słońcem              | $\lambda_\mathrm{p} - \odot = 0$                                                                  |
| Koniunkcja dolna / górna           | koniunkcja Merkurego lub Wenus przy $\Delta_\mathrm{p} < R_\odot$ / $\Delta_\mathrm{p} > R_\odot$ |
| Opozycja                           | $\lambda_\mathrm{p} - \odot = 180^\circ$ (planety zewnętrzne)                                     |
| Maksymalna elongacja (wsch./zach.) | lokalne maksimum $\psi$ dla Merkurego i Wenus; wschodnia, gdy $\lambda_\mathrm{p} - \odot > 0$    |
| Zbliżenie dwóch planet             | lokalne minimum odległości $d$, $d \le 5^\circ$                                                   |
| Zbliżenie Księżyca do planety      | lokalne minimum $d$ (Księżyc co 2 h), $d \le 3^\circ$, najbliższe 30 dni                          |

Miejsca zerowe zawęża się metodą regula falsi w wariancie Illinois:

$$
c = \frac{a f(b) - b f(a)}{f(b) - f(a)}
$$

Gdy ten sam koniec przedziału zostaje zachowany dwa razy z rzędu, jego wartość funkcji dzieli się na pół. Iteracja
kończy się, gdy przedział jest krótszy niż $10^{-5}$ doby.

Ekstrema zawęża się metodą złotego podziału wokół próbki $t$ ($\pm 1$ doba; $\pm 2$ godziny dla Księżyca), ze
współczynnikiem $(\sqrt5 - 1)/2$ i tolerancją $10^{-4}$ doby. Różnice długości bierze się modulo $2\pi$ w przedziale
$(-\pi, \pi]$.

## 14. Strefa czasowa ze współrzędnych

(`TimeZoneLookup.php`, port biblioteki tz-lookup.) Kula ziemska jest podzielona na siatkę 48 × 24 komórek, każda o
boku 7.5°. Komórka zawiera dwuznakowy kod: albo bezpośrednio numer strefy, albo węzeł drzewa czwórkowego. Każdy
węzeł dzieli się na 2 × 2 części, a położenie wewnątrz węzła, podwajane w każdym kroku, wybiera część, aż do liścia
z numerem strefy. Liście zawierają nazwy stref IANA wyznaczone z danych timezone-boundary-builder. Na morzu są to
strefy żeglarskie `Etc/GMT±n`. Przesunięcie względem UTC, skrót i informację o czasie letnim dla danej chwili podaje
potem baza stref czasowych PHP.

## 15. Najbliższa miejscowość

(`Places.php`, dane GeoNames z paczki cities.json.) Około 171 tysięcy miejscowości jest podzielonych na kafle
10° × 10°. Dla punktu $(\varphi, \lambda)$ wczytuje się kafle pokrywające prostokąt
$\pm 30\ \mathrm{km}/111\ \mathrm{km}$ w szerokości i $\pm 30/(111\cos\varphi)$ w długości, a odległość liczy się
wzorem haversine na kuli o promieniu 6371 km:

$$
s = 2R\arcsin\sqrt{\sin^2\frac{\varphi_2 - \varphi_1}{2}
    + \cos\varphi_1\cos\varphi_2\sin^2\frac{\lambda_2 - \lambda_1}{2}}
$$

Wynikiem jest najbliższa miejscowość w promieniu 30 km. Strona dopisuje odległość, gdy przekracza 3 km, a poza
Polską także polską nazwę kraju z przeglądarkowego `Intl.DisplayNames`.

export const LOCALE = 'pl-PL';

export const UI = {
  'app.title': 'Niebo teraz',
  'app.subtitle': 'Słońce, Księżyc i planety — pozycje, zjawiska i wizualizacje',
  'loading': 'Ładowanie…',
  'skip': 'Przejdź do treści',
  'live.status': 'Na żywo',
  'ctl.location': 'Lokalizacja',
  'ctl.lat': 'Szerokość geogr. (°N)',
  'ctl.lon': 'Długość geogr. (°E)',
  'ctl.elev': 'Wysokość (m n.p.m.)',
  'ctl.myLocation': 'Moja lokalizacja',
  'ctl.default': 'Domyślna',
  'ctl.time': 'Czas',
  'ctl.datetime': 'Data i godzina (czas lokalny miejsca)',
  'ctl.now': 'Teraz',
  'ctl.live': 'Na żywo',
  'ctl.steps': 'Przesunięcie czasu',
  'ctl.placeName': 'Miejsce',
  'ctl.defaultPlace': 'domyślne',
  'ctl.noPlace': 'brak miejscowości w pobliżu',
  'err.prefix': 'Błąd',
  'err.server': 'Błąd serwera',
  'err.network': 'Nie udało się połączyć z serwerem. Wyświetlane są poprzednie dane.',
  'err.geoDenied': 'Brak zgody na użycie lokalizacji.',
  'err.geoUnavailable': 'Lokalizacja jest niedostępna w tej przeglądarce lub urządzeniu.',
  'err.coords': 'Podaj szerokość (−90…90) i długość (−180…180) geograficzną.',
  'err.panel': 'Nie udało się wyświetlić tego panelu.',
  'err.close': 'Zamknij',
  'noData': 'Brak danych',
  'panel.time': 'Czas',
  'panel.sun': 'Słońce',
  'panel.moon': 'Księżyc',
  'panel.planets': 'Planety',
  'panel.dome': 'Kopuła nieba',
  'panel.orrery': 'Układ planetarny (widok z góry)',
  'panel.ecliptic': 'Ekliptyka i zodiak',
  'panel.chart': 'Wysokość nad horyzontem w ciągu dnia',
  'panel.map': 'Mapa świata',
  'panel.events': 'Zjawiska astronomiczne',
  'panel.separations': 'Odległości kątowe między ciałami',
  // time panel
  'time.clock': 'Zegar (czas lokalny)',
  'time.utc': 'UTC',
  'time.local': 'Czas lokalny',
  'time.zone': 'Strefa czasowa',
  'time.unix': 'Czas uniksowy',
  'time.jd': 'JD',
  'time.mjd': 'MJD',
  'time.jde': 'JDE',
  'time.deltaT': 'ΔT',
  'time.gmst': 'GMST',
  'time.gast': 'GAST',
  'time.lmst': 'LMST',
  'time.last': 'LAST',
  'time.doy': 'Dzień roku',
  'time.week': 'Tydzień ISO',
  'time.julianDate': 'Data juliańska (kalendarz)',
  'time.julianEpoch': 'Epoka juliańska',
  'time.dst': 'czas letni',
  'time.std': 'czas zimowy',
  // sun
  'sun.rise': 'Wschód',
  'sun.transit': 'Górowanie',
  'sun.set': 'Zachód',
  'sun.noon': 'Południe słoneczne',
  'sun.dayLength': 'Długość dnia',
  'sun.eot': 'Równanie czasu',
  'sun.altaz': 'Wysokość / azymut',
  'sun.radec': 'Rektascensja / deklinacja',
  'sun.distance': 'Odległość',
  'sun.twilight': 'Zmierzch i świt',
  'sun.dawn': 'Świt',
  'sun.dusk': 'Zmierzch',
  'sun.civil': 'Cywilny (−6°)',
  'sun.nautical': 'Żeglarski (−12°)',
  'sun.astronomical': 'Astronomiczny (−18°)',
  'sun.alwaysAbove': 'Słońce nie zachodzi tego dnia',
  'sun.alwaysBelow': 'Słońce nie wschodzi tego dnia',
  // moon
  'moon.illumination': 'Oświetlenie',
  'moon.phase': 'Faza',
  'moon.age': 'Wiek',
  'moon.distance': 'Odległość',
  'moon.nextPhases': 'Najbliższe fazy',
  'moon.diameter': 'Średnica kątowa',
  'moon.lunation': 'Lunacja (nr Browna)',
  'moon.past': 'ostatnia',
  'moon.drawing': 'Rysunek fazy Księżyca widzianej z miejsca obserwacji',
  'moon.waxing': 'przybywa',
  'moon.waning': 'ubywa',
  // planets
  'pl.body': 'Ciało',
  'pl.radec': 'RA / Dek',
  'pl.altaz': 'Wys. / Az.',
  'pl.dist': 'Odległość (AU)',
  'pl.light': 'Czas światła',
  'pl.mag': 'Jasność',
  'pl.elong': 'Elongacja',
  'pl.illum': 'Oświetlone',
  'pl.diam': 'Średnica',
  'pl.rise': 'Wschód',
  'pl.transit': 'Górowanie',
  'pl.set': 'Zachód',
  'pl.visible': 'widoczna teraz',
  'pl.east': 'E (wieczorem)',
  'pl.west': 'W (rano)',
  // dome
  'dome.showBelow': 'Pokaż ciała pod horyzontem',
  'dome.aria': 'Kopuła nieba: położenie ciał na niebie, zenit w środku, północ u góry, wschód po lewej',
  'dome.ecliptic': 'Pokaż ekliptykę',
  // orrery
  'orrery.lines': 'Linie Ziemia → planeta',
  'orrery.scale': 'Skala promieni',
  'orrery.sqrt': 'pierwiastkowa',
  'orrery.log': 'logarytmiczna',
  'orrery.aries': '0° Barana',
  'orrery.earth': 'Ziemia',
  'orrery.aria': 'Rzut z góry na Układ Słoneczny, współrzędne heliocentryczne',
  // ecliptic
  'ecl.aria': 'Pas ekliptyki 0–360° ze znakami zodiaku i położeniem ciał',
  'ecl.antisun': 'Punkt przeciwny Słońcu',
  // chart
  'chart.aria': 'Wykres wysokości ciał nad horyzontem w ciągu doby',
  'chart.now': 'Chwila odniesienia',
  'chart.horizon': 'Horyzont',
  'chart.time': 'Czas',
  'chart.day': 'Dzień',
  'chart.civil': 'Zmierzch cywilny',
  'chart.nautical': 'Zmierzch żeglarski',
  'chart.astronomical': 'Zmierzch astronomiczny',
  'chart.night': 'Noc',
  // map
  'map.aria': 'Mapa świata z linią dnia i nocy. Kliknij, aby ustawić lokalizację.',
  'map.hint': 'Kliknij na mapie, aby ustawić lokalizację.',
  'map.subsolar': 'Punkt podsłoneczny',
  'map.sublunar': 'Punkt podksiężycowy',
  'map.observer': 'Obserwator',
  // events
  'ev.loading': 'Ładowanie zjawisk…',
  'ev.hint': 'Kliknij datę zjawiska, aby ustawić czas odniesienia.',
  'ev.solar': 'Zaćmienia Słońca',
  'ev.lunar': 'Zaćmienia Księżyca',
  'ev.phases': 'Fazy Księżyca',
  'ev.seasons': 'Pory roku',
  'ev.apsides': 'Perygeum i apogeum Księżyca',
  'ev.planets': 'Zjawiska planetarne',
  'ev.conj': 'Koniunkcje i zbliżenia',
  'ev.date': 'Data i czas',
  'ev.type': 'Typ',
  'ev.gamma': 'Gamma',
  'ev.magnitude': 'Wielkość',
  'ev.penMag': 'Wielk. półcieniowa',
  'ev.local': 'Widoczność lokalna',
  'ev.obscuration': 'Przesłonięcie',
  'ev.start': 'Początek',
  'ev.max': 'Maksimum',
  'ev.end': 'Koniec',
  'ev.notVisible': 'niewidoczne z tego miejsca',
  'ev.belowHorizon': 'pod horyzontem',
  'ev.visible': 'widoczne',
  'ev.moonAlt': 'Wys. Księżyca w maks.',
  'ev.contacts': 'Kontakty',
  'ev.distance': 'Odległość',
  'ev.planet': 'Planeta',
  'ev.event': 'Zjawisko',
  'ev.elong': 'Elongacja',
  'ev.pair': 'Ciała',
  'ev.separation': 'Odstęp',
  'ev.central': 'centralne',
  // separations
  'sep.note': 'Wartości poniżej 5° są podświetlone.',
  'sep.aria': 'Macierz odległości kątowych w stopniach',
};

export function tr(key) {
  return UI[key] ?? key;
}

export const BODY_NAMES = {
  sun: 'Słońce',
  moon: 'Księżyc',
  mercury: 'Merkury',
  venus: 'Wenus',
  mars: 'Mars',
  jupiter: 'Jowisz',
  saturn: 'Saturn',
  uranus: 'Uran',
  neptune: 'Neptun',
};

export const BODY_COLORS = {
  sun: '#ffd23f',
  moon: '#d9dde8',
  mercury: '#b8a99a',
  venus: '#f2e0a0',
  mars: '#e5603f',
  jupiter: '#d9a066',
  saturn: '#e3cf8a',
  uranus: '#7fd6e0',
  neptune: '#5b7bff',
};

export const BODY_ORDER = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune'];

export const PHASE_NAMES = {
  new_moon: 'Nów',
  waxing_crescent: 'Sierp przybywającego Księżyca',
  first_quarter: 'Pierwsza kwadra',
  waxing_gibbous: 'Przybywający Księżyc (garb)',
  full_moon: 'Pełnia',
  waning_gibbous: 'Ubywający Księżyc (garb)',
  last_quarter: 'Ostatnia kwadra',
  waning_crescent: 'Sierp ubywającego Księżyca',
};

export const ZODIAC = [
  ['Baran', '♈'], ['Byk', '♉'], ['Bliźnięta', '♊'], ['Rak', '♋'], ['Lew', '♌'], ['Panna', '♍'],
  ['Waga', '♎'], ['Skorpion', '♏'], ['Strzelec', '♐'], ['Koziorożec', '♑'], ['Wodnik', '♒'], ['Ryby', '♓'],
];

export const SOLAR_TYPES = { total: 'całkowite', annular: 'obrączkowe', hybrid: 'hybrydowe', partial: 'częściowe' };
export const LUNAR_TYPES = { total: 'całkowite', partial: 'częściowe', penumbral: 'półcieniowe' };
export const LUNAR_VISIBILITY = { full: 'w pełni widoczne', partial: 'częściowo widoczne', none: 'niewidoczne' };
export const SEASONS = {
  march_equinox: 'Równonoc wiosenna',
  june_solstice: 'Przesilenie letnie',
  september_equinox: 'Równonoc jesienna',
  december_solstice: 'Przesilenie zimowe',
};
export const APSIDES = { perigee: 'Perygeum', apogee: 'Apogeum' };
export const PLANET_EVENTS = {
  opposition: 'Opozycja',
  conjunction: 'Koniunkcja ze Słońcem',
  inferior_conjunction: 'Koniunkcja dolna',
  superior_conjunction: 'Koniunkcja górna',
  greatest_elongation_east: 'Największa elongacja wschodnia',
  greatest_elongation_west: 'Największa elongacja zachodnia',
};
export const LUNAR_CONTACTS = {
  p1: 'P1 — początek zaćmienia półcieniowego',
  u1: 'U1 — początek zaćmienia częściowego',
  u2: 'U2 — początek całkowitości',
  u3: 'U3 — koniec całkowitości',
  u4: 'U4 — koniec zaćmienia częściowego',
  p4: 'P4 — koniec zaćmienia półcieniowego',
};
export const STEPS = [
  ['−1 d', -86400], ['−1 h', -3600], ['−10 min', -600], ['+10 min', 600], ['+1 h', 3600], ['+1 d', 86400],
];

export function applyStaticText(root = document) {
  for (const el of root.querySelectorAll('[data-i18n]')) el.textContent = tr(el.dataset.i18n);
  for (const el of root.querySelectorAll('[data-i18n-aria]')) el.setAttribute('aria-label', tr(el.dataset.i18nAria));
}

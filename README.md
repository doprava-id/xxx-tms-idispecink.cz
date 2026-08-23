# iDispečink.cz — prezentační web

Statický web spedice **iDispečink.cz s.r.o.** Čisté HTML, CSS a JavaScript,
žádný build ani závislosti. Soubory stačí nakopírovat na hosting — funguje
na obyčejném FTP i kdekoliv jinde.

## Struktura

```
index.html                   Úvodní stránka
sluzby.html                  Spedice a externí dispečink
pro-dopravce.html            Podmínky spolupráce + registrační formulář
o-nas.html                   O společnosti a identifikační údaje
kontakt.html                 Kontaktní údaje + poptávkový formulář
zasady-osobnich-udaju.html   Informace o zpracování osobních údajů (osnova)
404.html                     Chybová stránka
robots.txt                   Pravidla pro roboty
sitemap.xml                  Mapa webu
assets/css/                  firemni-styl.css — jediná definice barev a komponent
assets/js/                   main.js — mobilní menu a odesílání formulářů
assets/img/                  Logo a favicon
```

## Firemní styl

Barvy a pravidla odpovídají firemnímu stylu iDispečink.cz — žlutá `#F0B41E`,
antracit `#343F41`, krém `#F0EDE6`. Web používá tmavou technickou variantu.
Barvy jsou na jednom místě jako CSS proměnné v `assets/css/firemni-styl.css`;
měňte je jen tam, ne v jednotlivých stránkách.

## Formuláře

Statický web nemá backend. Formuláře na stránkách *Kontakt* a *Pro dopravce*
poskládají text zprávy a otevřou poštovního klienta návštěvníka (`mailto:`) —
odeslání provede sám návštěvník. Web tedy sám nesbírá a neukládá žádná data,
nepoužívá cookies ani měření návštěvnosti.

Příjemce se nastavuje atributem `data-prijemce` na elementu `<form>`.
Pokud budete chtít odesílání na pozadí, stačí formuláře přesměrovat na
službu typu Formspree nebo Web3Forms — obsluha je v `assets/js/main.js`.
Tím ale začnete data zpracovávat a bude potřeba upravit i zásady ochrany údajů.

## Co je potřeba doplnit před spuštěním

Nedodané údaje jsou v HTML označené žlutým rámečkem `.doplnit` nebo
slovem „doplnit“. Vyhledáte je takto:

```bash
grep -rn "doplnit\|PLACEHOLDER" *.html
```

Konkrétně jde o:

- telefon a provozní dobu (`kontakt.html`, `index.html`)
- DIČ, adresu sídla a spisovou značku v OR (`kontakt.html`, `o-nas.html`)
- obchodní podmínky pro dopravce — splatnost, doklady, sankce (`pro-dopravce.html`)
- doby uchování údajů, seznam zpracovatelů a datum účinnosti
  (`zasady-osobnich-udaju.html`)
- oficiální logo místo dočasného SVG (`assets/img/`)

`zasady-osobnich-udaju.html` je připravená osnova, ne hotové právní znění —
před zveřejněním ji nechte zkontrolovat.

## Lokální spuštění

```bash
python3 -m http.server 8000
```

Pak otevřete <http://localhost:8000>.

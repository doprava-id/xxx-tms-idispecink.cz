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

## Logo

`logo-idispecink.svg` je vodorovný lockup pro tmavé pozadí (hlavička, patička),
`logo-idispecink-tmavy.svg` totéž pro světlé pozadí, `znacka.svg` /
`znacka-tmava.svg` je samotný piktogram bez nápisu a `favicon.svg` ikona do
záložky. Všechno je vektor překreslený podle dodaného loga — pokud máte
originální PNG, nahraďte jím soubory v `assets/img/` a v HTML upravte
`width`/`height` u `<img>` podle nového poměru stran.

Nápis v lockupu je vysázený písmem Segoe UI s `textLength`, takže drží šířku
i na strojích, kde Segoe UI není. Pro naprostou věrnost by bylo lepší mít
nápis převedený do křivek — to lze udělat z originálního zdroje loga.

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

- provozní dobu (`kontakt.html`)
- obchodní podmínky pro dopravce — splatnost, doklady, sankce (`pro-dopravce.html`)
- doby uchování údajů, seznam zpracovatelů a datum účinnosti
  (`zasady-osobnich-udaju.html`)

## Firemní údaje na webu

Údaje jsou převzaté z veřejného rejstříku (stav srpen 2026):

| Údaj | Hodnota |
|---|---|
| Společnost | iDispečink.cz s.r.o. |
| Sídlo | Příčná 1892/4, 110 00 Praha 1 – Nové Město |
| IČO | 23359765 |
| DIČ | CZ23359765 |
| Spisová značka | C 425222, Městský soud v Praze |
| Datum vzniku | 5. 6. 2025 |
| Jednatel | Jakub Pěsta |
| Telefon | +420 734 580 243 |
| E-mail | doprava@idispecink.cz |

Objevují se na stránkách *Kontakt*, *O nás*, v zásadách zpracování údajů,
v patičce všech stránek a ve strukturovaných datech (JSON-LD) na úvodní stránce.
Při změně je potřeba je upravit na všech těchto místech.

`zasady-osobnich-udaju.html` je připravená osnova, ne hotové právní znění —
před zveřejněním ji nechte zkontrolovat.

## Lokální spuštění

```bash
python3 -m http.server 8000
```

Pak otevřete <http://localhost:8000>.

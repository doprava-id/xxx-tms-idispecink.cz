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
.htaccess                    Nastavení pro Apache (404, komprese, cache, hlavičky)
robots.txt                   Pravidla pro roboty
sitemap.xml                  Mapa webu
assets/css/                  firemni-styl.css — jediná definice barev a komponent
assets/js/                   main.js — mobilní menu a odesílání formulářů
assets/img/                  Logo, favicon a náhledový obrázek
```

## Logo

Logo je vektor překreslený podle dodané předlohy. Nápis je převedený do křivek,
takže vypadá stejně všude a nezávisí na tom, jaká písma má prohlížeč nebo
tiskárna k dispozici.

| Soubor | Použití |
|---|---|
| `logo-idispecink.svg` | vodorovné, na tmavé pozadí — hlavička a patička webu |
| `logo-idispecink-tmavy.svg` | vodorovné, na světlé pozadí — dokumenty, faktury |
| `logo-idispecink-ctverec.svg` | svislé, na světlé pozadí — profilovky, dlaždice |
| `logo-idispecink-ctverec-tmave-pozadi.svg` | svislé, na tmavé pozadí |
| `znacka.svg`, `znacka-tmava.svg` | samotný piktogram bez nápisu |
| `favicon.svg` | ikona v záložce prohlížeče |
| `apple-touch-icon.png` | ikona po přidání webu na plochu mobilu |
| `og-idispecink.png` | náhled při sdílení odkazu (1200×630) |
| `logo-idispecink*.png` | rastrové exporty pro e-mailové podpisy a dokumenty |

Poměr stran vodorovného loga je 6,825 : 1, svislého 2,120 : 1. Při výměně
souborů upravte i `width`/`height` u `<img>` v HTML, jinak se stránka
při načítání poskočí.

Tvary písmen vycházejí z písma **Poppins SemiBold** (SIL Open Font License),
které se z dodané předlohy jevilo jako nejbližší. Obrysy jsou v souborech
zapsané jako křivky, žádný soubor s písmem se nikam nenahrává.

Pokud existuje originální zdroj loga (AI, EPS nebo PDF s křivkami), je lepší
použít ten — tohle je poctivý překres, ne originál.

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

- obchodní podmínky pro dopravce — splatnost, doklady, sankce (`pro-dopravce.html`)
- doby uchování údajů a seznam zpracovatelů (`zasady-osobnich-udaju.html`)

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
| Provozní doba | nonstop 24/7 |

Objevují se na stránkách *Kontakt*, *O nás*, v zásadách zpracování údajů,
v patičce všech stránek a ve strukturovaných datech (JSON-LD) na úvodní stránce.
Při změně je potřeba je upravit na všech těchto místech.

`zasady-osobnich-udaju.html` je připravená osnova, ne hotové právní znění —
před zveřejněním ji nechte zkontrolovat.

## Chování bez JavaScriptu a při tisku

Web funguje i s vypnutým JavaScriptem. Skript obsluhuje jen rozbalovací menu
na úzkých displejích a odesílání formulářů; bez něj se menu zobrazí rovnou
rozbalené (`<noscript>` blok v hlavičce každé stránky) a formuláře zůstanou
vyplnitelné, jen se neodešlou — proto je u nich vždy uvedená i e-mailová
adresa.

Tiskový styl v `firemni-styl.css` převádí tmavý motiv na černobílý: skryje
navigaci, patičku a tlačítka, zesvětlí plochy a ztmaví text. Barvy z tmavého
motivu jsou na několika místech zapsané přímo v atributu `style`, proto je
tiskový blok přebíjí přes `!important` — při úpravách stránek na to pozor.

## Nasazení

Soubory nakopírujte do kořene webu — celý obsah repozitáře kromě `README.md`
a `LICENSE`. Web nic nekompiluje, takže funguje hned po nahrání.

`.htaccess` nastaví chybovou stránku, kompresi, cache a bezpečnostní
hlavičky. Na hostingu bez Apache se ignoruje a nic nerozbije; tytéž věci
si pak nastavte v administraci hostingu.

**Přesměrování na HTTPS a na doménu bez www je v `.htaccess` zakomentované.**
Odkomentujte ho, až budete mít na doméně funkční certifikát — dřív by web
znepřístupnilo.

Po nasazení stojí za kontrolu: náhled odkazu při sdílení (og obrázek),
funkčnost obou formulářů z běžného počítače a to, že `sitemap.xml`
a `robots.txt` odpovídají skutečné doméně.

## Lokální spuštění

```bash
python3 -m http.server 8000
```

Pak otevřete <http://localhost:8000>.

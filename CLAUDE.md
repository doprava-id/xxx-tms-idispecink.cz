# CLAUDE.md

Pokyny pro Claude Code a další AI asistenty pracující v tomto repozitáři.

## Co tento repozitář je

**Prezentační web spedice iDispečink.cz s.r.o.** — statické HTML, CSS a JavaScript.
Žádný build, žádné závislosti, žádný package manager. Soubory se nahrávají tak, jak
jsou, na běžný hosting.

Ověřená fakta o repozitáři:

| | |
|---|---|
| Repozitář | `doprava-id/xxx-tms-idispecink.cz` (GitHub, soukromý) |
| Výchozí větev | `main` |
| Licence | Apache License 2.0 |
| Runtime | žádný — statické soubory |
| Testy, CI | nejsou |
| Jazyk obsahu | čeština |

Předpona `xxx-` v názvu repozitáře působí jako zástupný text; skutečný název nebyl
stanoven.

## Struktura

```
index.html                   Úvodní stránka
sluzby.html                  Spedice a externí dispečink
pro-dopravce.html            Podmínky spolupráce + registrační formulář
o-nas.html                   O společnosti a identifikační údaje
kontakt.html                 Kontaktní údaje + poptávkový formulář
zasady-osobnich-udaju.html   Zpracování osobních údajů
404.html                     Chybová stránka
favicon.ico                  Ikona pro starší prohlížeče (musí zůstat v kořeni)
.htaccess                    Apache: 404, komprese, cache, bezpečnostní hlavičky
robots.txt, sitemap.xml      Pro vyhledávače
assets/css/firemni-styl.css  Jediná definice barev a komponent
assets/js/main.js            Mobilní menu a odesílání formulářů
assets/img/                  Logo, favicon, náhledový obrázek
README.md                    Podrobná dokumentace webu — čti ji taky
PREDANI-WEBU.md              Předávací soubor pro revizi — čti před obsahovými změnami
```

**Chystá se obsahová revize webu.** Závazná rozhodnutí, osm otevřených otázek
a důsledky, které se nesmí přehlédnout (přepis zásad zpracování údajů společně
s formulářem, CMR versus vnitrostátní rozsah, tvrzení o 24/7 na pěti místech),
jsou v `PREDANI-WEBU.md`. Než začneš měnit obsah, přečti si ho.

Hlavička, patička a `<head>` jsou v každé stránce zvlášť. **Když měníš navigaci,
patičku nebo meta značky, uprav všech sedm stránek.** Neexistuje šablonovací vrstva,
která by to udělala za tebe.

## Spuštění a ověření

```bash
python3 -m http.server 8000     # pak http://localhost:8000
```

Testy nejsou. Změny se ověřují vykreslením v prohlížeči — v prostředí je Chromium
a Playwright (`/opt/node22/lib/node_modules/playwright`, `PLAYWRIGHT_BROWSERS_PATH`
je nastavená, `playwright install` nespouštěj). Po každé netriviální změně projeď:

- všech sedm stránek: stav 200, žádné chyby v konzoli,
- šířky 1280, 768 a 390 px: nikde vodorovný scroll,
- oba formuláře: skládají korektní `mailto:` a povinná pole nejdou obejít,
- vypnutý JavaScript: menu na mobilu musí zůstat dostupné,
- tiskový režim (`emulateMedia({media:'print'})`): žádný světlý text na bílé.

Netvrď, že něco funguje, dokud jsi to nevykreslil a neviděl.

## Konvence

**Jazyk.** Obsah, názvy tříd, identifikátory v JavaScriptu, komentáře i commit
messages jsou česky (`.id-tabulka`, `.doplnit`, `sestav_mailto`, `prepinac`). Drž se
toho — nemíchej angličtinu do existujícího kódu.

**Barvy jsou na jednom místě.** Žlutá `#F0B41E`, antracit `#343F41`, krém `#F0EDE6`.
Jsou definované jako CSS proměnné v `assets/css/firemni-styl.css`. Jednotlivé stránky
si vlastní barvy nedefinují a nesmí začít. Firemní styl zakazuje gradienty, stíny
a dekorace: „Plocha, linka, text."

**Formuláře nemají backend.** Poskládají text a otevřou poštovního klienta
návštěvníka (`mailto:`). Web proto nesbírá žádná data, nepoužívá cookies ani měření
návštěvnosti — a zásady zpracování údajů to výslovně uvádějí. Pokud formuláře
přepojíš na službu typu Formspree, začne docházet ke zpracování údajů a je nutné
upravit i `zasady-osobnich-udaju.html`.

**Placeholdery.** V současném obsahu žádné nejsou — všechny firemní údaje jsou
doplněné. Když ale narazíš na údaj, který ti chybí, **nevymýšlej ho**: označ místo
třídou `.doplnit` a komentářem `PLACEHOLDER` a řekni to uživateli. Ceny, podmínky,
lhůty ani reference si nelze domyslet. Zbylé placeholdery najdeš příkazem:

```bash
grep -rn 'doplnit\|PLACEHOLDER' *.html
```

**Logo.** Soubory v `assets/img/` pocházejí z originálního vektoru, ne z překresu.
Dvě věci, které se snadno rozbijí:

1. Každá barva je **jedna cesta** s `fill-rule="evenodd"`. Díry — okna kabiny, náboje
   kol, vnitřek monitoru — drží právě tímto pravidlem. Rozdělíš-li podcesty do
   samostatných elementů `<path>`, díry se vyplní a kabina zčerná.
2. Na tmavém pozadí **nepřebarvuj antracitové tvary na krémovou.** V originálu je
   kabina antracitová a je to určující znak značky; překlopením vůz dostane bílou
   kabinu. Tmavé varianty místo toho drží původní barvy a od pozadí je odděluje
   krémová linka (`stroke` 6,5 jednotky, `paint-order="stroke fill"`).

Poměry stran jsou v README. Když vyměníš logo, uprav i `width`/`height` u `<img>`
ve všech stránkách, jinak stránka při načítání poskočí.

## Nasazení

Obsah repozitáře kromě `README.md`, `CLAUDE.md`, `PREDANI-WEBU.md` a `LICENSE`
se nahraje do kořene webu. `.htaccess` začíná tečkou — většina FTP klientů ho ve výchozím nastavení
nezobrazí ani nenahraje.

**Přesměrování na HTTPS a bez www je v `.htaccess` zakomentované.** Odkomentovat až
s funkčním certifikátem na doméně — dřív by web znepřístupnilo.

## Provoz firmy — kontext, který v repozitáři není

iDispečink.cz s.r.o. provozuje silniční dispečink nad Airtable (tabulka `Přepravy`),
Blue Yonder TMS (účty ESA a WELLPACK/Chep), denními Trello nástěnkami a exporty do
Excelu. **Nic z té automatizace v tomto repozitáři není** a nepatří sem. Běží
v samostatné lokální pipeline a v Claude skillech.

Do repozitáře nekopíruj identifikátory Airtable bází a tabulek, přihlašovací údaje
k Blue Yonderu, kódy Trello nástěnek ani cesty na pracovní stanici. Zmínit systém
jménem je v pořádku, kopírovat jeho konfiguraci ne.

## Práce s Gitem

- Vyvíjej na větvi, kterou dostaneš v zadání (`claude/<téma>-<přípona>`). Do `main`
  necommituj přímo a na jinou větev nepushuj bez svolení.
- Pull request zakládej, jen když o něj uživatel výslovně požádá.
- Pokud už byl PR tvé větve zmergovaný, restartuj větev z aktuálního `main`
  (`git fetch origin main && git checkout -B <větev> origin/main`) místo stohování
  commitů na zmergovanou historii.
- Do commit messages, PR ani komentářů v kódu nepiš označení modelu.

## Pravidla pro AI asistenty

- **Ověř, než tvrdíš.** Struktura je malá a přehledná — `git ls-files` a vlastní oči
  v prohlížeči jsou rychlejší než odhad.
- **Nescaffolduj nevyžádaně.** Web záměrně nemá build, framework ani CI. Nepřidávej
  je proto, že „by tam měly být".
- **Nevymýšlej firemní údaje.** Ceny, podmínky, lhůty, reference ani kontakty si
  nedomýšlej. Když chybí, řekni to a označ místo jako `.doplnit`.
- **Drž tento soubor pravdivý.** Jakmile změna udělá některé tvrzení tady nepravdivým,
  oprav ho ve stejném commitu. Zastaralý CLAUDE.md je horší než žádný.

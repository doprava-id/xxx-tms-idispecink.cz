# CLAUDE.md

Pokyny pro Claude Code a další AI asistenty pracující v tomto repozitáři.

## Co tento repozitář je

**Prezentační web spedice iDispečink.cz s.r.o.** — statické HTML, CSS a JavaScript.
Žádný build, žádné závislosti, žádný package manager. Soubory se nahrávají tak, jak
jsou, na běžný hosting.

Ověřená fakta o repozitáři:

| | |
|---|---|
| Repozitář | `doprava-id/xxx-tms-idispecink.cz` (GitHub) |
| Viditelnost | **veřejný** — cokoliv se sem commitne, je čitelné komukoliv |
| Výchozí větev | `main` |
| Licence | Apache License 2.0 |
| Runtime | žádný — statické soubory |
| Testy, CI | nejsou |
| Jazyk obsahu | čeština |

Předpona `xxx-` v názvu repozitáře působí jako zástupný text; skutečný název nebyl
stanoven.

## Struktura

```
index.html                   Úvod — přepínač oken pro zákazníky a dopravce
sluzby.html                  Spedice a externí dispečink
pro-dopravce.html            Dvě cesty: nabídka vozidel + externí dispečink
o-nas.html                   O společnosti a identifikační údaje
poptat-prepravu.html         Jediný poptávkový formulář
kontakt.html                 Kontaktní údaje — na formulář jen navádí
odeslani.php                 Server: odeslání obou formulářů e-mailem
odeslano.html                Potvrzení po odeslání (noindex)
zasady-osobnich-udaju.html   Zpracování osobních údajů
404.html                     Chybová stránka
favicon.ico                  Ikona pro starší prohlížeče (musí zůstat v kořeni)
.htaccess                    Apache: 404, komprese, cache, bezpečnostní hlavičky
robots.txt, sitemap.xml      Pro vyhledávače
assets/css/firemni-styl.css  Jediná definice barev a komponent
assets/js/main.js            Mobilní menu, schovávání hlavičky, stav formulářů
assets/js/mereni.js          Google Analytics + lišta souhlasu (ID je PLACEHOLDER)
assets/img/                  Logo, favicon, náhledový obrázek
README.md                    Podrobná dokumentace webu — čti ji taky
PREDANI-WEBU.md              Předávací soubor pro revizi — čti před obsahovými změnami
```

**Chystá se obsahová revize webu.** Závazná rozhodnutí, osm otevřených otázek
a důsledky, které se nesmí přehlédnout (přepis zásad zpracování údajů společně
s formulářem, CMR versus vnitrostátní rozsah, tvrzení o 24/7 na pěti místech),
jsou v `PREDANI-WEBU.md`. Než začneš měnit obsah, přečti si ho.

Hlavička, patička a `<head>` jsou v každé stránce zvlášť. **Když měníš navigaci,
patičku, kontaktní pruh nad hlavičkou nebo meta značky, uprav všech devět stránek.**
Neexistuje šablonovací vrstva, která by to udělala za tebe.

**Nabídka na úvodní stránce je rozdělená do dvou oken** — zákazník na první pohled
nevidí, co nabízíme dopravcům. Přepínají se dvěma `<input type="radio">` uvnitř
`fieldset.prepinac-oken`, takže to funguje i **bez JavaScriptu** a klávesnice v nich
jezdí šipkami. Pravidla jsou v CSS u `.prepinac-oken` a stojí na pořadí sourozenců
(`input:checked + input + .zalozky`) — **když do fieldsetu přidáš další prvek mezi
radia a `.zalozky`, přepínač se tiše rozbije.** Na tisku se záložky skryjí a vytisknou
se obě okna pod sebou.

## Spuštění a ověření

```bash
python3 -m http.server 8000     # pak http://localhost:8000
```

Testy nejsou. Změny se ověřují vykreslením v prohlížeči — v prostředí je Chromium
a Playwright (`/opt/node22/lib/node_modules/playwright`, `PLAYWRIGHT_BROWSERS_PATH`
je nastavená, `playwright install` nespouštěj). Po každé netriviální změně projeď:

- všech devět stránek: stav 200, žádné chyby v konzoli,
- šířky 1280, 768 a 390 px: nikde vodorovný scroll,
- oba formuláře: POST na `odeslani.php` projde na `odeslano.html` a povinná pole nejdou obejít (testuj přes `php -S`, ne `python3 -m http.server`),
- vypnutý JavaScript: menu na mobilu musí zůstat dostupné,
- tiskový režim (`emulateMedia({media:'print'})`): žádný světlý text na bílé.

Netvrď, že něco funguje, dokud jsi to nevykreslil a neviděl.

## Konvence

**Jazyk.** Obsah, názvy tříd, identifikátory v JavaScriptu, komentáře i commit
messages jsou česky (`.id-tabulka`, `.doplnit`, `prepinac`, `formulare`, `prijemce`,
`radky`, `spousteci`). Drž se toho — nemíchej angličtinu do existujícího kódu.

**Barvy jsou na jednom místě.** Žlutá `#F0B41E`, antracit `#343F41`, krém `#F0EDE6`.
Jsou definované jako CSS proměnné v `assets/css/firemni-styl.css`. Jednotlivé stránky
si vlastní barvy nedefinují a nesmí začít.

Tmavé plochy pod nimi jsou **kovové**: `--kov-pozadi`, `--kov-povrch` a `--kov-vyssi`
jsou jemné přechody, `--brouseny` je broušená textura a `--odlesk` světlá hrana nahoře.
Světlost putuje jen o pár procent — má to číst jako kov, ne jako lesklý plast. Plochy
se odvozují z firemních barev, samotné firemní barvy se nemění.

Jedinou výjimkou je `<meta name="theme-color" content="#14191B">` — meta značka na CSS
proměnnou dosáhnout nemůže, takže je barva natvrdo v `<head>` všech devíti stránek.
Odpovídá **hornímu pruhu a pozadí stránky** (`--pozadi`), ne hlavičce: nahoře stránky
je pruh a při odrolování se schovanou hlavičkou je pod lištou prohlížeče rovnou pozadí.
**Když se změní odstín horního pruhu, změň i těch devět meta značek**, jinak lišta
prohlížeče na mobilu zůstane ve staré barvě.

Pravidlo zní „plocha, linka, text": vržené stíny, záře ani barevné přechody přes
firemní barvy na web nepatří. Kovové odlesky tmavých ploch jsou jediná povolená výjimka
a jsou celé v proměnných výše — nepřidávej gradienty ad hoc do jednotlivých pravidel.

**Formuláře odesílá server.** Oba formuláře (poptávka na `poptat-prepravu.html`,
registrace na `pro-dopravce.html`) posílají běžný POST na `odeslani.php` — fungují
proto i bez JavaScriptu; skript jen po odeslání zablokuje tlačítko. Smlouva mezi
HTML a PHP:

| Co | Kde | Pravidlo |
|---|---|---|
| `name="formular"` | skryté pole | `poptavka` nebo `registrace` — vybírá sadu polí v PHP |
| `name` polí | HTML i PHP | popisky řádků e-mailu drží pole `$FORMULARE` v PHP; pole, které tam není, se do e-mailu nedostane |
| `name="www"` | skryté pole `.nevyplnovat` | honeypot — vyplněné znamená robota, zpráva se zahodí |
| povinná pole | HTML `required` i PHP `povinna` | musí se shodovat, jinak server odmítne, co prohlížeč pustil |

Po úspěchu server přesměruje na `odeslano.html` (POST–redirect–GET). Hlavička
`From` je `web@idispecink.cz` — aby pošta nekončila ve spamu, musí SPF záznam
domény zahrnovat servery VAS Hostingu (viz README).

**Měření návštěvnosti** řídí `assets/js/mereni.js`: Google Analytics se načte
až po souhlasu v liště, volba se drží v localStorage (`ga-souhlas`), tlačítko
„Nastavení cookies" v patičce ji kdykoliv změní. Konstanta `GA_MERENI` je
**prázdný PLACEHOLDER** — měřicí ID vydá administrace GA a nelze si ho
vymyslet; dokud je prázdné, nic se neměří a lišta se nezobrazuje. Formuláře
i měření jsou popsané v `zasady-osobnich-udaju.html` — každá změna jednoho
znamená úpravu druhého ve stejném commitu.

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

Obsah repozitáře kromě `README.md`, `CLAUDE.md`, `PREDANI-WEBU.md`, `LICENSE`
a `.gitignore` se nahraje do kořene webu; ty na hosting nepatří. `.htaccess`
začíná tečkou — většina FTP klientů ho ve výchozím nastavení
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

**Repozitář je veřejný.** Commit je publikace: obsah zůstane v historii i poté, co ho
další commit smaže, takže uniklý klíč se musí zneplatnit, ne jen odstranit. U webu
samotného to nevadí — je stejně veřejný —, ale provozní data a přístupy sem nesmí
ani na okamžik.

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

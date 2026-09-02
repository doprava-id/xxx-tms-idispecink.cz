# CLAUDE.md

Pokyny pro Claude Code a další AI asistenty pracující v tomto repozitáři.

## Co tento repozitář je

Repozitář drží **dvě věci**, které spolu sdílejí jen firemní styl a hosting:

1. **Prezentační web spedice iDispečink.cz s.r.o.** — statické HTML, CSS a
   JavaScript v kořeni. Formuláře odesílá `odeslani.php`.
2. **Provozní systém v `aplikace/`** — vnitřní dispečerská aplikace za
   přihlášením: přepravy, dispečerská tabule, firmy, objednávky přepravy,
   podklady k fakturaci.

**Žádný build, žádné závislosti, žádný package manager ani u jednoho.** Soubory se
nahrávají tak, jak jsou, na běžný hosting.

Ověřená fakta o repozitáři:

| | |
|---|---|
| Repozitář | `doprava-id/xxx-tms-idispecink.cz` (GitHub) |
| Viditelnost | **veřejný** — cokoliv se sem commitne, je čitelné komukoliv |
| Výchozí větev | `main` |
| Licence | Apache License 2.0 |
| Runtime | web: žádný. `aplikace/`: PHP 7.4+ s PDO (SQLite, volitelně MySQL) |
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

aplikace/                    PROVOZNÍ SYSTÉM — vnitřní aplikace za přihlášením
  index.php                  Jediný vstupní bod, směrovač (index.php?s=…)
  config.vzor.php            Vzor konfigurace; config.php je v .gitignore
  aplikace.css               Nadstavba firemního stylu, vlastní barvy nedefinuje
  aplikace.js                Nabídka, dvojí odeslání, náhled marže
  .htaccess                  DirectoryIndex, zákaz výpisu, noindex, zákaz stažení dat
  data/                      Databáze SQLite — v .gitignore, .htaccess vše zakazuje
  zdroj/                     Vkládané soubory; přímo se neotevírají
    zavaděč v index.php, databaze.php, pomocnici.php, ciselniky.php,
    autentizace.php, sablona.php, trasa.php (body jízdy, stálé linky,
    státní svátky), prilohy.php, ares.php, odkazy.php (veřejné odkazy,
    WhatsApp), posta.php (e-mail, objednávka jako zpráva), faktury.php
    (pohledávky, závazky), fakturoid.php (úhrady a založení faktury přes API),
    dispecink.php (externí dispečink: klienti, podmínka JEN_SPEDICE, podklad
    k fakturaci služby), ceniky.php (ceníky zákazníků, návrh ceny),
    dopravci.php (platnosti dokladů dopravce), nabidky.php (nabídky:
    číslování, převod na přepravu, zpráva zákazníkovi)
  zdroj/stranky/             Jedna stránka = jeden soubor
    instalace, prihlaseni, odhlaseni, prehled, prepravy, preprava,
    nabidky, nabidka (jen s právem na ceny),
    dispecink, vozy (plán vozů klientů), firmy, firma, mista, misto, linky, objednavka,
    fakturace, nastaveni, import, export, priloha,
    verejne (bez přihlášení: zákazník, dopravce, řidič)
```

**Zadání toho, co se má postavit dál, je v `ZADANI-APLIKACE.md`** — vzniklo
z pohovoru se zadavatelem a je zdrojem pravdy o záměru. Než sáhneš do
aplikace, přečti si ho: říká i to, co zadavatel z rozsahu vyřadil.

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
php -S 127.0.0.1:8000           # web i aplikace, pak http://localhost:8000
python3 -m http.server 8000     # jen statický web, PHP neběží
```

Aplikaci otevřeš na `http://localhost:8000/aplikace/`. Při prvním spuštění si
sama založí databázi v `aplikace/data/` a nabídne založení správce; smazáním
souboru `aplikace/data/idispecink.sqlite` se instalace vrátí na začátek.

Testy nejsou. Změny se ověřují vykreslením v prohlížeči — v prostředí je Chromium
a Playwright (`/opt/node22/lib/node_modules/playwright`, `PLAYWRIGHT_BROWSERS_PATH`
je nastavená, `playwright install` nespouštěj). Po každé netriviální změně projeď:

- všech devět stránek: stav 200, žádné chyby v konzoli,
- šířky 1280, 768 a 390 px: nikde vodorovný scroll,
- oba formuláře: POST na `odeslani.php` projde na `odeslano.html` a povinná pole nejdou obejít (testuj přes `php -S`, ne `python3 -m http.server`),
- u zásahu do aplikace i jejích **dvaadvacet stránek** (Fakturace má sedm pohledů: dopravci, zákazníci, externí dispečink, chybějící údaje, faktury, pohledávky, závazky; Fakturoid zkoušej proti napodobenině přes `fakturoid_adresa` v dočasném `config.php`): instalace, přihlášení, obojí CRUD, body trasy (přidat, posunout, splnit, smazat), místa, linky včetně generování týdne, přílohy, tabule, plán vozů včetně nové jízdy z prázdné buňky, nabídky (návrh ceny, tisk, odeslání, přijetí → přeprava, důvod neúspěchu), ceník a platnosti dokladů na kartě firmy, objednávka včetně odeslání e-mailem (spusť `php -S` s `-d sendmail_path=` na skript, který zprávu uloží), veřejné odkazy všech tří rolí z cizího prohlížeče, fakturace, import a export,
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

## Provozní systém (`aplikace/`)

Vnitřní nástroj pro dva dispečery. Stejná pravidla jako web — česky, bez buildu,
bez závislostí, „plocha, linka, text" —, ale pár věcí navíc.

**Jmenuje se „provozní systém", ne TMS.** Rozhodnutí 17 v `PREDANI-WEBU.md` říká
označovat vnitřní systém výhradně takhle. Název složky a větve zkratku TMS nese,
**text, který uvidí uživatel, ji nést nesmí.** Web o aplikaci nemluví vůbec a
nemá začít — je to nástroj, ne nabídka.

**Jeden vstupní bod.** Všechno jde přes `aplikace/index.php?s=<stránka>`; hosting
tedy nemusí umět `mod_rewrite`. Seznam stránek v `index.php` je bílá listina —
stránka, která v něm není, se nespustí, a u každé je zapsané, jestli vyžaduje
přihlášení.

**Věci, které se rozbijí tiše:**

| Co | Pravidlo |
|---|---|
| soubory v `zdroj/` | každý začíná kontrolou `if (!defined("APLIKACE"))`; bez ní jde obsah ven při přímém otevření |
| výstup | každý údaj z databáze i formuláře projde `chran()`; bez toho jde do stránky cizí kód |
| zápis | každý POST ověřuje `over_token()`; směrovač to dělá plošně, `prihlaseni` a `instalace` si to řeší samy |
| schéma | popisuje ho pole `$SCHEMA` v `databaze.php`, ne hotové SQL. **Nový sloupec přidej tam** — doplní se sám při dalším načtení, na SQLite i MySQL. Ručně psané `ALTER TABLE` do repozitáře nepatří |
| trasa | zdrojem pravdy jsou **body v tabulce `body`**. Pole `nakladka_*` a `vykladka_*` na přepravě jsou jen odvozený souhrn první nakládky a poslední vykládky — přepočítává je výhradně `prepocitej_trasu()`. **Nikdo je nesmí zapisovat přímo**; seznamy a tabule je smí jen číst. Stav „naloženo" a „vyloženo" se řídí splněnými body |
| šablony linek | přeprava se `sablona = 1` je šablona stálé linky. **Každý dotaz nad přepravami, který zobrazuje evidenci, musí mít `p.sablona = 0`** — jinak se šablona objeví jako zásilka v seznamu, na tabuli nebo v obratu |
| externí dispečink | jízda s `dispecink_klient_id` je vůz klienta, který řídíme; klientem je vždy dopravce jízdy. Odesílateli fakturuje klient sám, my účtujeme jen odměnu podle karty klienta. **Každý součet tržby, nákladů a marže spedice a každý podklad k fakturaci musí mít `JEN_SPEDICE`** (`dispecink.php`) — bez toho se obrat cizích vozů přičte k našemu. Způsob účtování a sazba jsou **PLACEHOLDER** na kartě klienta: dokud chybí, podklad odměnu nespočítá a nedomýšlí ji |
| ceníky | návrh ceny (`navrh_ceny()`) se **nikdy nezapisuje sám** — dispečer ho převezme tlačítkem „použít". Přednost: pevná cena za trasu → pásmo → sazba za km → historie trasy; návrh vždy říká, podle čeho vznikl. Pásma a sazba potřebují `km` u jízdy, zatím ručně |
| doklady dopravce | propadlé pojištění, oprávnění nebo smlouva **varují, ale nepustí** — objednávka jde vystavit, rozhodnutí je na dispečerovi. Varování jde jen na obrazovku, do tisku ne |
| přílohy | soubory leží v `data/prilohy/` pod náhodným jménem, ven jdou jen přes `priloha.php` po přihlášení. Typ se bere z tabulky `PRILOHY_TYPY`, ne z toho, co soubor tvrdí; SVG a HTML tam schválně nejsou |
| odchozí provoz | ven volají jen `ares.php` a `fakturoid.php` — s limitem a bez výjimky ven. Hosting nemusí odchozí spojení povolit a aplikace na tom nesmí stát. Pošta jde přes `posta.php` a PHP `mail()` jako u webu |
| Fakturoid | přístup (slug, client_id, client_secret) je **jen v `config.php`**, který je v `.gitignore`. Nic se nevolá samo: čtení úhrad i založení faktury jsou tlačítka. Faktury se na přepravy vážou **číslem** (`faktura_vydana`, `faktura_prijata`), ne cizím klíčem — jedna faktura kryje víc přeprav. Klíč `fakturoid_adresa` v konfiguraci je jen pro zkoušení proti napodobenině |
| veřejné odkazy | `verejne.php` je jediná stránka bez přihlášení. Kód v adrese vybírá přepravu i roli; **cena dopravce a marže se tam nesmí objevit nikdy, cena zákazníka jen zákazníkovi.** Stránka posílá `Referrer-Policy: no-referrer` a odkazy ven mají `rel="noreferrer"`, jinak by kód utekl do cizích logů. Každý POST má token jako všude jinde |
| ceny | cenu zákazníka a marži smí vidět jen `vidi_ceny()`. Kdo právo nemá, **nesmí ta pole ani přepsat** — jinak by je uložení formuláře smazalo (viz `preprava.php`) |
| `.tabulka-obal` | musí zůstat `position: relative`. Skryté popisky uvnitř široké tabulky jsou absolutně umístěné a bez toho roztáhnou stránku do vodorovného scrollu |
| číslování | tvar řady drží Nastavení, ne kód. `dalsi_cislo()` navíc přeskočí číslo, které už existuje |
| podmínky objednávky | **PLACEHOLDER.** Text dodá zadavatel v Nastavení. Objednávka bez něj vytiskne viditelné upozornění — nedoplňuj ho za něj |

**Do repozitáře nepatří** `aplikace/config.php` ani cokoliv z `aplikace/data/`.
Obojí je v `.gitignore`, `data/` má navíc vlastní `.htaccess`, který zakazuje
všechno. Repozitář je veřejný a evidence přeprav nese osobní údaje zákazníků,
dopravců i řidičů.

**Import z CSV je obecný**, ne konektor. Čte cizí soubor, hádá sloupce podle názvů
a mapování se nikam neukládá. Konfigurace Airtable, Blue Yonderu ani Trella sem
nepatří — platí kapitola „Provoz firmy" níže.

## Nasazení

Obsah repozitáře kromě `README.md`, `CLAUDE.md`, `PREDANI-WEBU.md`, `LICENSE`
a `.gitignore` se nahraje do kořene webu; ty na hosting nepatří. `.htaccess`
začíná tečkou — většina FTP klientů ho ve výchozím nastavení
nezobrazí ani nenahraje. **Adresář `aplikace/` má vlastní `.htaccess` a další
ještě v `aplikace/data/` a `aplikace/zdroj/`** — bez nich by šla databáze
stáhnout z webu.

Aplikace potřebuje navíc: PHP s `pdo_sqlite` (nebo `pdo_mysql`) a **právo zápisu
do `aplikace/data/`** (0770). Konfigurace je volitelná — bez `config.php` běží
na SQLite v `data/`. První otevření `aplikace/` nabídne založení správce; dokud
žádný uživatel neexistuje, jiná stránka se nespustí.

**Přesměrování na HTTPS a bez www je v `.htaccess` zakomentované.** Odkomentovat až
s funkčním certifikátem na doméně — dřív by web znepřístupnilo.

## Provoz firmy — kontext, který v repozitáři není

iDispečink.cz s.r.o. provozuje silniční dispečink nad Airtable (tabulka `Přepravy`),
Blue Yonder TMS (účty ESA a WELLPACK/Chep), denními Trello nástěnkami a exporty do
Excelu. **Nic z té automatizace v tomto repozitáři není** a nepatří sem. Běží
v samostatné lokální pipeline a v Claude skillech.

Provozní systém v `aplikace/` na ni **nenavazuje** a navazovat nemá: je to
samostatná evidence, do které se data zadávají ručně nebo se načtou obecným
importem z CSV. Kdyby se někdy měly obě věci propojit, propojení patří do
pipeline mimo tento repozitář, ne sem.

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

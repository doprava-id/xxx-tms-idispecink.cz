# Předávací soubor — web iDispečink.cz

**Datum:** 30. 8. 2026
**Verze webu:** větev `claude/vytvor-webovou-stranku-2h1skc`, poslední commit `48ce056`
**Stav:** web je hotový a funkční. Proběhla obsahová revize (27. 8. 2026), jejíž
závěry **zatím nejsou zapracované**. Tento soubor je zadání pro ně.

Soubor je samostatný — kdo ho čte, nemusí znát nic dalšího. Slouží pro předání
do ChatGPT, Claude Design nebo Claude Cowork.

---

## 0. Jak tento soubor použít

| Nástroj | Co má dělat | Které kapitoly číst |
|---|---|---|
| **ChatGPT** | jazyková a obchodní revize textů, návrhy formulací | 1, 2, 5, 6, 7, 9, 11 |
| **Claude Design** | vizuální návrh nové struktury úvodní stránky a stránky Pro dopravce | 1, 3, 4, 8, 9 |
| **Claude Cowork** | rozhodnutí o otevřených bodech, obchodní obsah, koordinace | 1, 2, 5, 6, 7, 10 |
| **Claude Code** (implementace) | zapracování do HTML/CSS/JS | vše |

**Nikdo z nich nesmí vymýšlet firemní údaje.** Ceny, podmínky, lhůty, reference,
jména ani kontakty se nedomýšlejí. Chybí-li údaj, označí se a předá zadavateli.
Viz kapitola 9.

---

## 1. Co je iDispečink.cz

Spediční společnost. Dvě rovnocenné služby:

1. **Spedice a organizace přeprav** — pro odesílatele. Zajištění dopravce,
   plánování, dohled nad zásilkou, doklady.
2. **Externí dispečink pro dopravce** — dopravní firma si dispečink nekupuje
   jako zaměstnance, ale jako službu. Vytěžování vozů, denní plánování,
   komunikace s řidiči, podklady k fakturaci.

Web je **prezentační**, ne produktový. Není to aplikace, není to zákaznický
portál. Cílem je důvěryhodně představit firmu a přivést tři skupiny:

- dopravce, kteří nabízejí volná vozidla,
- dopravce, kteří hledají externí dispečink,
- zákazníky poptávající přepravu.

První dvě skupiny jsou prioritní, třetí je rovněž důležitá.

---

## 2. Ověřená firemní fakta

Převzato z veřejného rejstříku (stav srpen 2026). **Tato tabulka je jediný
zdroj pravdy — nic mimo ni si nedomýšlej.**

| Údaj | Hodnota |
|---|---|
| Společnost | iDispečink.cz s.r.o. |
| Sídlo | Příčná 1892/4, 110 00 Praha 1 – Nové Město |
| IČO | 23359765 |
| DIČ | CZ23359765 |
| Spisová značka | C 425222, Městský soud v Praze |
| Datum vzniku | 5. 6. 2025 |
| Jednatel (zapsaný) | Jakub Pěsta |
| Telefon | +420 734 580 243 |
| E-mail | doprava@idispecink.cz |

Adresa je **pouze zapsané sídlo**, nikoli provozovna. Web nesmí vyzývat
k osobní návštěvě.

### Provozní skutečnosti potvrzené zadavatelem

- **Územní rozsah:** standardně **pouze vnitrostátní přeprava po ČR**.
- **Vozidla:** plachtový návěs 13,6 m, mega / lowdeck, jiné typy podle dohody.
- **Rozsah zásilek:** převážně **FTL (celovozové)**, menší zásilky po dohodě.
- **Dostupnost:** provozní **pohotovost 24/7 pro probíhající přepravy**
  (telefonická). Neznamená zaručené okamžité vyřešení každého požadavku.
- **Vedení provozu:** provoz vedou **dva lidé společně** (jméno druhého člověka
  chybí — viz kapitola 6).
- **Prověření nového dopravce:** firma a IČO v registrech, koncese nebo
  oprávnění k dopravě, pojištění odpovědnosti dopravce, reference a předchozí
  zkušenosti, doklady vozidla a řidiče.
- **Dohled během přepravy:** potvrzení nakládky a vykládky, řešení zpoždění
  a mimořádností, průběžná komunikace s řidičem, informování zákazníka.
  **GPS sledování pouze u vozidel v externím dispečinku**, ne u spedice.
- **Interní systém:** označovat výhradně jako **„vlastní provozní systém"**.
  Termín „vlastní TMS" nepoužívat — sliboval by hotový software se všemi
  běžnými funkcemi TMS.

### Podmínky pro dopravce (potvrzené)

- Týdenní fakturace po zajetí vozu, jehož délku dohodneme individuálně;
  za odjeté přepravy v následujícím pracovním týdnu.
- Doklady stačí předat elektronicky, po domluvě si je můžeme vyzvednout.
- Nepokutujeme, pokud to není nezbytné; komplikace řešíme dohodou.
- Spolupráce krátkodobá i dlouhodobá.
- V objednávce jsou předem adresy, časová okna, kontakty i sjednaná cena.
- Vytížení sháníme celoročně, i v logisticky slabších měsících.
- K zakázkám z velkých tendrů máme přístup **přes zprostředkovatele**
  (ne napřímo — tuhle formulaci neměnit).

---

## 3. Současný web — technický stav

Statické HTML, CSS a JavaScript. **Žádný build, žádné závislosti, žádný
package manager, žádné CI, žádné testy.** Soubory se nahrávají tak, jak jsou,
na běžný FTP hosting (VAS Hosting).

```
index.html                   Úvodní stránka
sluzby.html                  Spedice a externí dispečink
pro-dopravce.html            Podmínky spolupráce + registrační formulář
o-nas.html                   O společnosti a identifikační údaje
kontakt.html                 Kontaktní údaje + poptávkový formulář
zasady-osobnich-udaju.html   Zpracování osobních údajů
404.html                     Chybová stránka
favicon.ico                  Musí zůstat v kořeni
.htaccess                    Apache: 404, komprese, cache, bezpečnostní hlavičky
robots.txt, sitemap.xml
assets/css/firemni-styl.css  Jediná definice barev a komponent
assets/js/main.js            Mobilní menu a odesílání formulářů
assets/img/                  Logo, favicon, náhledový obrázek
```

**Hlavička, patička a `<head>` jsou v každé stránce zvlášť.** Neexistuje
šablonovací vrstva. Změna navigace nebo patičky = úprava všech stránek.

### Vlastnosti, které se nesmí ztratit

- **Web funguje bez JavaScriptu.** Skript obsluhuje jen rozbalovací menu
  a formuláře. Bez něj se menu zobrazí rovnou rozbalené (`<noscript>` blok
  v hlavičce každé stránky).
- **Tiskový styl** převádí tmavý motiv na černobílý. Barvy tmavého motivu
  jsou místy zapsané přímo v atributu `style`, proto je tiskový blok přebíjí
  přes `!important`.
- **Přístupnost:** odkaz „Přeskočit na obsah", jeden `<h1>` na stránku,
  nepřeskakované úrovně nadpisů, viditelný focus.
- **Bez vodorovného scrollu** na 1280, 768 a 390 px.

### Ověřování změn

```bash
python3 -m http.server 8000     # pak http://localhost:8000
```

Testy neexistují. Změny se ověřují vykreslením v prohlížeči — v prostředí je
Chromium a Playwright (`PLAYWRIGHT_BROWSERS_PATH` je nastavená,
`playwright install` nespouštět). Po netriviální změně projít: všech sedm
stránek (stav 200, žádné chyby v konzoli), tři šířky, oba formuláře, vypnutý
JavaScript, tiskový režim.

**Netvrdit, že něco funguje, dokud to nebylo vykresleno a viděno.**

---

## 4. Firemní styl

Definice je **na jediném místě** — `assets/css/firemni-styl.css`. Jednotlivé
stránky si vlastní barvy nedefinují a nesmí začít.

### Barvy

| Proměnná | Hodnota | Použití |
|---|---|---|
| `--zluta` | `#F0B41E` | akcent, tlačítka, odkazy, štítky |
| `--zluta-tmava` | `#C79212` | odkaz při najetí |
| `--zluta-svetla` | `#FFF3DA` | světlé zvýraznění |
| `--antracit` | `#343F41` | firemní tmavá |
| `--krem` | `#F0EDE6` | firemní světlá, barva textu |
| `--pozadi` | `#23292B` | pozadí stránky |
| `--povrch` | `#2A3133` | karty, mobilní menu |
| `--povrch-2` | `#343F41` | druhotné plochy |
| `--linka` | `#414D50` | ohraničení |
| `--linka-slaba` | `#333C3E` | jemné předěly sekcí |
| `--text` | `#F0EDE6` | text |
| `--text-tlum` | `#A5AEB0` | tlumený text |

Stavové barvy: OK `#7BD69A` / `#16301F`, pozor `#F0C55E` / `#33290F`,
chyba `#F0868F` / `#35171A`.

### Pravidla

> **Bez gradientů, stínů a dekorací. Plocha, linka, text.**

Toto je závazné. Žádné `box-shadow`, žádné `linear-gradient`, žádné dekorativní
tvary, žádné ikonové sady. Rozlišení prvků se dělá **plochou** (`--povrch`
proti `--pozadi`), **linkou** (1 px `--linka`) a **typografií**.

Písmo: `"Segoe UI", Inter, system-ui, -apple-system, "Helvetica Neue", Arial,
sans-serif`. Čísla: monospace s `tabular-nums`. Základ 17 px, řádkování 1,65.
Maximální šířka obsahu 1160 px, vnitřní okraj 24 px.

### Logo

Soubory v `assets/img/` pocházejí z **originálního vektoru**, ne z překresu.
Dvě věci, které se snadno rozbijí:

1. **Každá barva je jedna cesta s `fill-rule="evenodd"`.** Díry — okna kabiny,
   náboje kol, vnitřek monitoru — drží právě tímto pravidlem. Rozdělením
   podcest do samostatných `<path>` se díry vyplní a kabina zčerná.
2. **Na tmavém pozadí se antracitové tvary nepřebarvují na krémovou.**
   V originálu je kabina antracitová a je to určující znak značky. Tmavé
   varianty drží původní barvy a od pozadí je odděluje krémová linka
   (`stroke` 6,5 jednotky, `paint-order="stroke fill"`).

Poměry stran: vodorovné **6,305 : 1**, svislé **1,961 : 1**, piktogram
**1,975 : 1**. Při výměně souboru je nutné upravit i `width`/`height` u `<img>`
ve všech stránkách, jinak stránka při načítání poskočí.

---

## 5. Revize 27. 8. 2026 — závazná rozhodnutí

Následující rozhodnutí padla a **mají se zapracovat**. Číslování odpovídá
původnímu revizním zápisu.

### Technika a provoz

| # | Rozhodnutí |
|---|---|
| 1 | Formuláře **nesmí zůstat jen na `mailto:`**. Zpráva se má skutečně odeslat a návštěvníkovi se zobrazí jasné potvrzení. |
| 2 | Formuláře doplňují telefon a e-mail, nepřebíjejí je. Web je důvěryhodná prezentace, ne agresivní sběrač kontaktů. |
| 3 | Nastavit **základní měření návštěvnosti**: počet návštěv, zdroje, kliknutí, odeslané formuláře. Zásady ochrany údajů upravit podle zvoleného nástroje. |
| 4 | Dostupnost formulovat jako **„Provozní pohotovost 24/7 pro probíhající přepravy."** |
| 5 | Neuvádět jednoho člověka jako jediného vedoucího provozu — **provoz vedou dva společně**. |

### Struktura a navigace

| # | Rozhodnutí |
|---|---|
| 6 | Pro dopravce vytvořit **dvě jasně oddělené cesty**. Poptávka přepravy zůstává samostatnou výraznou akcí. |
| 7 | Na stránce „Pro dopravce" dvě oddělené části: **„Chci nabízet svá vozidla"** a **„Chci externí dispečink"**. |
| 8 | V horním menu ponechat obě hlavní akce: **„Pro dopravce"** a **„Poptat přepravu"**. |
| 9 | Obě tlačítka mají **stejnou vizuální váhu** — žádná služba nad druhou. |
| 10 | Horní menu se **skryje při rolování dolů a objeví při rolování nahoru**. |
| 11 | **Klikací telefon přímo v menu**, na mobilu jako snadno dostupná ikona nebo tlačítko. |
| 16 | Spedici a externí dispečink zobrazovat **stejně výrazně**, bez pořadí. |
| 18 | Poptávka přepravy na **samostatné stránce „Poptat přepravu"**, kontaktní stránka na ni navede. **Jeden společný formulář**, ne dva. |
| 19 | V patičce pod „Pro dopravce" dva odkazy: **„Pro dopravce"** a **„Externí dispečink"** — mohou vést na dvě části jedné stránky. |

### Obsah a formulace

| # | Rozhodnutí |
|---|---|
| 12 | Jasně uvádět **vnitrostátní přepravy po ČR**. |
| 13 | Uvádět **plachtový návěs 13,6 m, mega/lowdeck**, ostatní jako *„jiné typy podle dohody"*. |
| 14 | Nabídku postavit na **celovozových přepravách (FTL)**, menší zásilky jen jako možnost po dohodě. |
| 15 | **Nezveřejňovat seznam vyloučených přeprav.** Nestandardní poptávky posuzovat individuálně. |
| 17 | Používat výhradně označení **„vlastní provozní systém"**. |
| 24 | Adresu označit jako **„Sídlo společnosti"**, nevyzývat k osobní návštěvě. |
| 25 | V patičce ponechat **IČO 23359765 i DIČ CZ23359765**. |
| 29 | Označení „prověřený dopravce" **lze ponechat**, na podrobné stránce krátce vysvětlit rozsah kontroly. |
| 30 | Uvést **konkrétní provozní dohled** (potvrzení nakládky/vykládky, řešení mimořádností, komunikace s řidičem, informování zákazníka). |
| 31 | **GPS uvádět pouze u externího dispečinku**, ne jako vlastnost všech přeprav. |

### Kontakty

| # | Rozhodnutí |
|---|---|
| 20 | Uvádět **`doprava@idispecink.cz` i `info@idispecink.cz`** s krátkým vysvětlením. |
| 21 | Popisky: `doprava@` = **„Přepravy a provoz"**, `info@` = **„Obecné dotazy"**. |
| 22 | Přidat **veřejný klikací WhatsApp kontakt** pro zákazníky i dopravce. |
| 23 | **Nezveřejňovat obchodní podmínky ani vzory objednávek.** Zůstávají jen zásady ochrany osobních údajů. |

### Úvodní stránka

| # | Rozhodnutí |
|---|---|
| 26 | Hlavní nadpis a první obrazovku postavit na **dvou rovnocenných službách**, ne jen na přepravě. |
| 27 | Vpravo od nadpisu **dvě stejně velké karty** „Přeprava a spedice" a „Externí dispečink" — místo dosavadní karty „Rychlý kontakt". |
| 28 | Každá karta: **název, jedna stručná věta, jedno tlačítko.** Nic víc. |

---

## 6. Otevřené otázky — bez odpovědi nelze zapracovat

**Toto je blokující seznam.** Sedm rozhodnutí a jedna nezodpovězená otázka
z revize.

### 6.1 Čím odeslat formulář

Hosting je běžné FTP (VAS Hosting). Dvě cesty:

- **PHP skript přímo na hostingu** — data neopustí ČR, žádný další zpracovatel
  do zásad. Nutno potvrdit, že na hostingu běží PHP.
- **Externí služba** (Formspree, Web3Forms) — rychlejší nasazení, ale přibude
  do zásad jako další zpracovatel, u některých i přenos mimo EU.

### 6.2 Čím měřit návštěvnost

- **Google Analytics 4** → cookies + přenos do USA + **povinná lišta se
  souhlasem**.
- **Bezcookiová evropská varianta** (Plausible, Simple Analytics, Matomo bez
  cookies) → zvládne všechny čtyři požadované metriky (návštěvy, zdroje,
  kliknutí, odeslané formuláře) a **lištu nepotřebuje**. *Doporučeno.*

### 6.3 Druhý člověk ve vedení provozu

Chybí **jméno, příjmení a označení role** (společník? provozní ředitel?
prokurista?). V rejstříku je zapsaný jen Jakub Pěsta a ten na stránce „O nás"
zůstat musí. Bez doplnění by si stránka odporovala sama se sebou.

### 6.4 Platí nonstop i u placeného dispečinku?

Nová formulace „Provozní pohotovost 24/7 pro probíhající přepravy" opravuje
tvrzení směrem k poptávajícím. Ale u **placené služby externího dispečinku**
web dnes slibuje „komunikace s řidiči nonstop, 24 hodin denně" a „služba běží
i o víkendech, svátcích a v noci". To je jiný závazek — vůči platícímu
dopravci. Změkčit i tam, nebo to tak skutečně platí?

### 6.5 Mezinárodní přepravy — vůbec, nebo po dohodě?

Rozhodnutí #12 říká „pouze vnitrostátně". Pokud se mezinárodní přeprava občas
dělá a jen se nemá stavět do výlohy, formulace bude *„standardně vnitrostátně
po ČR, mezinárodní po dohodě"* a část o CMR zůstane u požadavků na dopravce.
Jinak CMR z webu zmizí (viz 7.2).

### 6.6 Funguje info@ a WhatsApp?

- Existuje schránka **`info@idispecink.cz`** a někdo ji čte? Publikovaná
  adresa, která se nedoručí, je horší než jedna adresa.
- Je číslo **+420 734 580 243 na WhatsAppu**, nebo jde o jiné číslo?

### 6.7 Kde bydlí externí dispečink

Rozhodnutí #7 vytváří na „Pro dopravce" plnohodnotnou část „Chci externí
dispečink". Ta bude **obsahově zdvojená se Službou 02** na stránce Služby. Dva
skoro shodné texty na jedné doméně si kazí pozici ve vyhledávání.

**Návrh:** externí dispečink celý na „Pro dopravce" (odkaz z patičky tam podle
#19 stejně míří), na Službách po něm zůstane krátký blok s odkazem. Alternativa
je opačná. Nutno rozhodnout.

### 6.8 Kdo vystavuje fakturu (revizní bod 32 — otevřený)

Spojení „kompletace dokladů a fakturace" je nejednoznačné: může znamenat
vlastní fakturaci i pouhou přípravu podkladů pro dopravce.

**Do rozhodnutí:** slovo „fakturace" z úvodní stránky vypustit, ponechat jen
kompletaci dokladů.

---

## 7. Důsledky, které se nesmí přehlédnout

Rozhodnutí z revize vypadají jako textové úpravy, ale některá mají následky
jinde na webu. Kdyby se zapracovala izolovaně, web by si začal odporovat.

### 7.1 Formuláře s backendem + měření → přepis zásad ve stejném nasazení

Dnes web neodesílá nic, neukládá nic, nemá cookies — a zásady zpracování to
**na třech místech výslovně tvrdí**:

- karta „Tento web sám o sobě žádné údaje nesbírá" (bod 2),
- bod 5 (seznam příjemců),
- bod 7 (cookies).

Jakmile formulář odesílá na server a měří se návštěvnost:

- firma se stává **správcem údajů návštěvníků webu**, ne jen obchodních
  partnerů,
- do bodu 5 přibývá provozovatel formuláře i měřicího nástroje,
- podle zvoleného nástroje může přibýt **lišta se souhlasem s cookies**,
- do bodu 5 patří i **Meta**, přibude-li WhatsApp.

**Zásady se nesmí upravit „později".** Musí jít ven ve stejném nasazení jako
formulář — jinak web měsíc lže o sobě samém.

### 7.2 Vnitrostátní rozsah → CMR na čtyřech místech

CMR je *mezinárodní* úmluva. U čistě vnitrostátní přepravy po ČR se odpovědnost
řídí občanským zákoníkem. Pokud platí „pouze vnitrostátně":

| Kde | Dnes | Po změně |
|---|---|---|
| Úvod, krok 04 | „Dodací listy, **CMR** a fakturu" | přepravní list / dodací list |
| Služby, rozsah spedice | „Doklady — **CMR**, dodací listy" | totéž |
| Pro dopravce, požadavky | „**CMR** pro mezinárodní přepravu, vnitrostátní pojištění pro ČR" | jen pojištění odpovědnosti pro vnitrostátní přepravu |
| Registrační formulář | nápověda „např. Morava, celá ČR, **ČR + SK + DE**" | bez zahraničí |

### 7.3 Tvrzení o 24/7 je na pěti místech ve třech významech

| Kde | Co tam dnes stojí |
|---|---|
| Úvod, panel kontaktu | Dostupnost — Nonstop 24/7 |
| Kontakt, tabulka | Provozní doba — Nonstop 24/7 |
| Kontakt, perex | Jsme k zastižení nonstop, 24 hodin denně |
| Služby, dispečink | Komunikace s řidiči nonstop, 24 hodin denně / služba běží i o víkendech, svátcích a v noci |
| `index.html`, JSON-LD | `hoursAvailable` 00:00–23:59, sedm dní v týdnu |

První tři opraví nová formulace. Čtvrtý je jiný závazek — viz otázka 6.4.
Pátý (strukturovaná data pro Google) se musí upravit tak jako tak.

### 7.4 Mizející menu musí fungovat bez JavaScriptu

Rozhodnutí #10 vyžaduje skript. Web dnes funguje i s vypnutými skripty.
Skrývání menu se proto smí implementovat **jen jako nadstavba**: bez
JavaScriptu zůstane menu normálně viditelné. Nikdy ne naopak.

### 7.5 Karta „Rychlý kontakt" mizí → kontakty musí zůstat dosažitelné

Rozhodnutí #27 nahrazuje kartu s telefonem, e-mailem a IČO dvěma kartami
služeb. Telefon se přesouvá do menu (#11), ale je nutné ověřit, že se
kontakt z první obrazovky nevytratí úplně — zejména na mobilu.

### 7.6 Nová stránka „Poptat přepravu"

Rozhodnutí #18 zakládá osmou stránku. S ní: záznam v `sitemap.xml`, odkaz
v navigaci i patičce **všech** stránek, vlastní `<head>` s canonical a OG,
`<noscript>` blok, a přesměrování starého kotvícího odkazu
`kontakt.html#poptavka`.

---

## 8. Cílová struktura po revizi

```
index.html            Úvod — dvě rovnocenné služby, dvě karty, dvě akce
sluzby.html           Spedice (plně) + externí dispečink (krátce, odkaz)   ← dle 6.7
pro-dopravce.html     Dvě cesty: „Nabízím vozidla" | „Chci externí dispečink"
poptat-prepravu.html  NOVÁ — jediný poptávkový formulář
o-nas.html            O společnosti, společné vedení provozu, sídlo
kontakt.html          Kontakty, oba e-maily, telefon, WhatsApp → navede na poptávku
zasady-osobnich-udaju.html   PŘEPSAT podle zvoleného formuláře a měření
404.html              Beze změny
```

**Horní menu:** Úvod · Služby · Pro dopravce · O nás · Kontakt
+ **telefon** (klikací) + dvě stejně výrazná tlačítka **Pro dopravce**
a **Poptat přepravu**.

**Patička, sloupec „Pro dopravce":** Pro dopravce · Externí dispečink

### První obrazovka úvodní stránky (rozhodnutí 26–28)

```
┌──────────────────────────────────────┬─────────────────────────┐
│ Štítek: Spedice · Externí dispečink  │ ┌─────────────────────┐ │
│                                      │ │ Přeprava a spedice  │ │
│ H1 — dvě rovnocenné služby           │ │ jedna věta          │ │
│                                      │ │ [tlačítko]          │ │
│ Perex, 2–3 řádky                     │ └─────────────────────┘ │
│                                      │ ┌─────────────────────┐ │
│ [Poptat přepravu] [Pro dopravce]     │ │ Externí dispečink   │ │
│  ↑ obě stejně výrazná                │ │ jedna věta          │ │
│                                      │ │ [tlačítko]          │ │
│                                      │ └─────────────────────┘ │
└──────────────────────────────────────┴─────────────────────────┘
```

Obě karty **stejně velké**, stejný styl, žádná zvýrazněná. Formát karty je
`.karta` z firemního stylu — plocha `--povrch`, linka `--linka`, žádný stín.

---

## 9. Pravidla pro toho, kdo přebírá

**Jazyk.** Obsah, názvy tříd, identifikátory v JavaScriptu, komentáře i commit
messages jsou **česky** (`.id-tabulka`, `.doplnit`, `sestav_mailto`,
`prepinac`). Nemíchat angličtinu do existujícího kódu.

**Nevymýšlet firemní údaje.** Ceny, podmínky, lhůty, reference, jména ani
kontakty se nedomýšlejí. Když údaj chybí:

1. místo se označí třídou `.doplnit` a komentářem `PLACEHOLDER`,
2. **řekne se to zadavateli**.

Kontrola zbylých placeholderů:

```bash
grep -rn 'doplnit\|PLACEHOLDER' *.html
```

Aktuálně web žádné neobsahuje.

**Nescaffoldovat nevyžádaně.** Web záměrně nemá build, framework ani CI.
Nepřidávat je proto, že „by tam měly být". Přidání backendu pro formulář je
výjimka daná rozhodnutím #1 — a i tam platí nejjednodušší funkční řešení.

**Ověřit, než tvrdíš.** Struktura je malá. `git ls-files` a vlastní oči
v prohlížeči jsou rychlejší než odhad.

**Držet dokumentaci pravdivou.** Jakmile změna udělá tvrzení v `CLAUDE.md`,
`README.md` nebo v tomto souboru nepravdivým, opravit ve stejném commitu.

**Git.** Vyvíjet na zadané větvi (`claude/<téma>-<přípona>`). Do `main`
necommitovat přímo. Pull request zakládat jen na výslovné vyžádání.

---

## 10. Co do repozitáře nepatří

iDispečink.cz provozuje silniční dispečink nad Airtable, Blue Yonder TMS,
denními Trello nástěnkami a exporty do Excelu. **Nic z té automatizace v tomto
repozitáři není** a nepatří sem — běží v samostatné lokální pipeline
a v Claude skillech.

Do repozitáře **nekopírovat**:

- identifikátory Airtable bází a tabulek,
- přihlašovací údaje k Blue Yonderu,
- kódy Trello nástěnek,
- cesty na pracovní stanici,
- provozní data s osobními údaji (`*.xlsx`, `*.csv`, `*.docx`).

Zmínit systém jménem je v pořádku, kopírovat jeho konfiguraci ne.

---

## 11. Konkrétní zadání pro jednotlivé nástroje

### ChatGPT — jazyková a obchodní revize

Kompletní text webu je v souboru `veskery-text-webu.md` (předává se zvlášť),
nebo se dá vytáhnout přímo z HTML.

Co posoudit:

1. **Formulace nových sdělení** podle rozhodnutí 4, 12, 13, 14, 17, 24, 26–31.
   Zejména hlavní nadpis první obrazovky, který má stavět na dvou rovnocenných
   službách, a jednu větu ke každé ze dvou karet.
2. **Tón.** Věcný, formální, bez superlativů a marketingových frází. Firma
   je malá a nová (vznik 6/2025) — text nesmí předstírat velikost.
3. **Nadbytečná tvrzení.** Cokoli, co web slibuje a nemá čím doložit.
4. **Konzistence** mezi stránkami — stejná věc řečená na dvou místech dvěma
   způsoby.

Co **nedělat**: nevymýšlet čísla, reference, počty vozů, roky zkušeností
ani jména. Chybí-li údaj, označit a zeptat se.

### Claude Design — vizuální návrh

Navrhnout **dvě obrazovky**:

1. **Úvodní stránka, první obrazovka** podle schématu v kapitole 8 —
   nadpis vlevo, dvě stejně velké karty služeb vpravo, dvě stejně výrazná
   tlačítka, horní menu s telefonem.
2. **Stránka „Pro dopravce", horní část** — dvě jasně oddělené cesty
   „Nabízím vozidla" a „Chci externí dispečink", vizuálně rovnocenné.

Závazné: barvy z kapitoly 4, pravidlo **„Bez gradientů, stínů a dekorací.
Plocha, linka, text."**, logo podle kapitoly 4. Šířky k ověření: 1280, 768
a 390 px.

### Claude Cowork — rozhodnutí a obsah

1. Zodpovědět **osm otevřených bodů z kapitoly 6** — bez nich se nedá
   pokračovat.
2. Rozhodnout o **hostingových předpokladech** (běží na VAS Hostingu PHP?)
   a o zřízení schránky `info@`.
3. Připravit podklad pro **přepis zásad zpracování osobních údajů** podle
   skutečně zvoleného formuláře a měřicího nástroje.
4. Zvážit **právní kontrolu zásad** — obsah odpovídá skutečnosti, ale znění
   nikdo s právním vzděláním neviděl.

---

## 12. Kde co leží

| Co | Kde |
|---|---|
| Repozitář | `doprava-id/xxx-tms-idispecink.cz` (GitHub, soukromý) |
| Výchozí větev | `main` |
| Pracovní větev | `claude/vytvor-webovou-stranku-2h1skc` |
| Podrobná dokumentace webu | `README.md` |
| Pokyny pro AI asistenty | `CLAUDE.md` |
| Firemní styl | `assets/css/firemni-styl.css` |
| Logo a ikony | `assets/img/` |
| Licence | Apache 2.0 (`LICENSE`) |

Na hosting se nahrává obsah repozitáře **kromě** `README.md`, `CLAUDE.md`,
`PREDANI-WEBU.md` a `LICENSE`. Soubor `.htaccess` začíná tečkou — většina FTP
klientů ho ve výchozím nastavení nezobrazí ani nenahraje.

**Přesměrování na HTTPS a bez www je v `.htaccess` zakomentované.** Odkomentovat
až s funkčním certifikátem na doméně — dřív by web znepřístupnilo.

---

## 13. Pořadí prací

1. Zodpovědět otevřené otázky (kapitola 6). **Blokující.**
2. Vybrat řešení formuláře a měření, ověřit hosting.
3. Vizuální návrh úvodní stránky a stránky Pro dopravce.
4. Zapracovat obsahová rozhodnutí (kapitola 5) do všech stránek najednou.
5. Přepsat zásady zpracování osobních údajů — **ve stejném nasazení** jako
   formulář a měření.
6. Ověřit v prohlížeči: sedm (osm) stránek, tři šířky, oba formuláře,
   bez JavaScriptu, tiskový režim.
7. Nasadit na FTP, zapnout HTTPS, teprve pak odkomentovat přesměrování.

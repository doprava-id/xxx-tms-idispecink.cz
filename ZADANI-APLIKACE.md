# Zadání — provozní systém iDispečink.cz

**Datum:** 1. 9. 2026
**Větev:** `claude/idispecink-tms-software-uosnh7`
**Stav:** jádro (19 obrazovek), ARES, přílohy, bod 1 pořadí prací (trasa
jako seznam bodů, místa, linky, historie trasy), bod 2 (odeslání objednávky,
veřejné odkazy, WhatsApp), bod 3 (faktury, pohledávky, závazky, Fakturoid),
bod 4 (externí dispečink), bod 5 (nabídky, ceníky, doklady dopravců)
a bod 6 (vyhodnocení, hlídání, ovládání, role, provoz). Čeká bod 7.

Vzniklo z pohovoru se zadavatelem. Je to **zdroj pravdy o tom, co se má
postavit** — ne popis toho, co existuje. Co existuje, popisuje `README.md`.

Soubor je samostatný: kdo ho čte, nemusí znát nic dalšího.

---

## 1. K čemu systém je

Vnitřní nástroj pro **dva dispečery**, kteří vedou provoz společně. Pokrývá
celý životní cyklus zakázky:

```
poptávka → nabídka → přeprava → doklady → fakturace → zaplaceno
```

a k tomu druhou polovinu podnikání — **externí dispečink**, tedy řízení
cizího vozového parku jako placenou službu.

Systém se **jmenuje „provozní systém"**, ne TMS. Rozhodnutí 17 v
`PREDANI-WEBU.md` platí dál: název složky a větve zkratku nese, text pro
uživatele ji nést nesmí. Web o aplikaci nemluví a nemá začít.

---

## 2. Co už je hotové

| Modul | Stav |
|---|---|
| Přihlášení, uživatelé, práva na ceny | hotové |
| Přehled | hotové |
| Přepravy — evidence, filtry, protokol | hotové |
| Dispečerská tabule po týdnech | hotové |
| Firmy — zákazníci, dopravci, vozidla, řidiči, prověření | hotové |
| Objednávka přepravy k tisku | hotové |
| Fakturace — obrat, marže, podklady po firmách | hotové |
| Nastavení, import z CSV, export do CSV | hotové |
| Načtení firmy z ARES podle IČO | hotové (živé volání neověřeno, viz 6) |
| Přílohy k přepravě | hotové — nahrání, stažení po přihlášení, mazání |
| Trasa jako seznam bodů (3.1) | hotové — body, splnění, převod starých dat |
| Adresář míst (3.2) | hotové |
| Stálé linky a generování týdne (3.2) | hotové |
| Návrh ceny podle historie trasy (3.2) | hotové |
| Kilometry a čas trasy (3.2) | čeká na mapovou službu, viz 6 |
| Veřejné odkazy pro zákazníka, dopravce a řidiče (3.7) | hotové |
| Odeslání objednávky e-mailem, WhatsApp odkaz (3.8) | hotové |
| SMS řidičům (3.8) | čeká na bránu, viz 6 |
| Pohledávky a závazky (3.4) | hotové |
| Fakturoid: úhrady přes API, založení faktury z podkladu (3.4, 3.13) | hotové, ověřeno proti napodobenině; živě až s přístupem v config.php |
| Historie komunikace u přepravy (3.8) | zatím jen protokol událostí; ruční poznámky s datem chybí |
| Externí dispečink (3.6) | hotové — klienti, plán vozů, podklad k fakturaci služby i faktura ve Fakturoidu; způsob účtování a sazby jsou PLACEHOLDER na kartě klienta, viz 6 |
| Nabídky a poptávky (3.3) | hotové — návrh ceny, tisk, e-mail, přijetí → přeprava, důvody neúspěchu, úspěšnost celkově i po zákaznících |
| Ceníky zákazníků (3.3) | hotové — pevná cena, pásma, sazba za km s předností; kilometry ručně, dokud není mapová služba (viz 6) |
| Smlouvy a pojistky dopravců (3.3) | hotové — platnosti s upozorněním měsíc předem; objednávka varuje a pustí |
| Vyhodnocení (3.5) | hotové — pohled Vyhodnocení ve Fakturaci: zákazníci, dopravci, řidiči, vozy |
| Hlídání (3.9) | hotové — ranní souhrn e-mailem přes cron s klíčem, ručně, nebo při prvním otevření dne (viz 6) |
| Ovládání (3.10) | hotové — světlý režim, rychlé hledání se zkratkami, hromadné akce, seznamy jako karty na mobilu |
| Role a přístupy (3.11) | hotové — čtyři role, brigádník bez jakékoli ceny, účetní bez zásahu do dispečinku; vlastník přepravy a zámek proti souběžné úpravě |
| Provoz (3.12) | hotové — denní zálohy a stažení zálohy, druhý faktor (TOTP), přehled změn |

---

## 3. Co se má postavit

### 3.1 Trasa jako seznam bodů — zasahuje do jádra

Dnešní model umí **jedna nakládka → jedna vykládka**. Rozvoz ze skladu na
pět míst ani sběr od tří dodavatelů zadat nejdou.

Trasa se stane **seznamem bodů**. Bod má: pořadí, druh (nakládka /
vykládka), místo, adresu, časové okno, kontakt a poznámku.

- **Zboží, hmotnost a palety jsou u bodu, ale nepovinně.** U celovozu se
  nechají prázdné a vyplní se souhrn u přepravy; u rozvozu se vyplní po
  bodech. Pozor: čísla pak mohou být na dvou místech a nemusí si odpovídat —
  systém na rozpor upozorní, ale nebude ho zakazovat.
- **Stav se sleduje u každého bodu zvlášť** — splněno a čas. Stav celé
  jízdy se z toho dopočítá. Na tabuli i na odkazu pro zákazníka je pak vidět
  „dvě ze tří vykládek hotové"; řidič si na svém odkazu odklikává zastávky,
  jak jede.

**Přizpůsobit se tomu musí všechno, co dnes čte `nakladka_*` a `vykladka_*`:**
seznam přeprav, dispečerská tabule, objednávka přepravy, pokyny řidiči,
podklady k fakturaci, import i export. Proto se to dělá **jako první** —
každý další modul postavený na starém modelu by se pak předělával.

Stávající data se převedou: z každé přepravy vzniknou dva body.

### 3.2 Pomoc při zadávání

- **Adresář míst** — **společný číselník**, u místa se dá (ale nemusí)
  uvést, čí je. Odpovídá tomu, že do jednoho skladu vozíte pro víc
  zákazníků a překladiště není nikoho. Místo nese adresu, bránu, kontakt,
  otevírací dobu a poznámku („ohlásit se na vrátnici").
- **Návrh ceny podle historie trasy** — ukáže, za kolik se stejná nebo
  podobná trasa vozila naposled a jaká na ní byla marže.
- **Šablony a stálé linky** — u linky se zaškrtnou dny v týdnu.
  **Generuje se na kliknutí, ne samo na pozadí:** v pátek kliknete
  a systém připraví příští týden, který si projdete. **Státní svátky
  přeskočí** a upozorní na to (svátky se počítají v kódu, žádná služba
  zvenku). Má-li linka stálého dopravce, vznikne přeprava rovnou
  objednaná — **odeslání objednávky ale zůstává na vás.**
- **Kilometry a čas trasy** — vzdálenost mezi body a cena za kilometr.
  **Vyžaduje mapovou službu zvenku** — viz otevřené otázky.

### 3.3 Okolí zakázky

- **Nabídky a poptávky** — stupeň před zakázkou; z nabídky se jedním
  kliknutím stane přeprava. Sleduje se **důvod, proč nabídka neprošla**
  (drahé, pozdě, neměli jsme vůz, zákazník zrušil) a **úspěšnost v číslech** —
  kolik nabídek jde ven, kolik prochází a v jaké hodnotě, celkově i po
  zákaznících. Nabídka jde **vytisknout a poslat zákazníkovi** ve firemním
  stylu, jako dnes objednávka pro dopravce.
  Platnost nabídky se nesleduje.
- **Ceníky zákazníků a stálé linky** — systém umí tři podoby a u zákazníka,
  se kterým se domlouvá po jedné, se nevyplní žádná:
  pevná cena za trasu · pásma podle vzdálenosti · sazba za kilometr.
  **Když platí víc pravidel, vyhrává nejkonkrétnější:** pevná cena → pásmo →
  sazba za km → cena z historie trasy. Systém vždycky napíše, podle čeho
  cenu spočítal.
- **Smlouvy a pojistky dopravců** — platnost pojištění odpovědnosti
  a oprávnění; upozornění na blížící se konec. **Při vystavení objednávky
  dopravci s propadlou pojistkou systém varuje, ale pustí dál** — rozhodnutí
  zůstává na dispečerovi.

**Reklamace, škody a pokuty se neřeší.** Zadavatel je z rozsahu vyřadil;
zůstávají u mailu a v hlavě.

### 3.4 Peníze

- **Pohledávky** — které vydané faktury jsou po splatnosti a jak dlouho.
  **Informace o zaplacení se tahá z Fakturoidu přes API**; přístup leží
  v `config.php` mimo git.
- **Závazky vůči dopravcům** — co komu dlužíme a kdy je to splatné,
  podle týdenní fakturace po zajetí vozu.
- **Export do Fakturoidu** — faktury se vystavují ve Fakturoidu, systém
  předá podklad ve tvaru, který se naimportuje.

**Marže zůstává hrubým rozdílem cen.** Příplatky a srážky za stání,
vícepráce, storno a pokuty se neevidují — zadavatel je z rozsahu vyřadil.

### 3.5 Vyhodnocení

Obraty a marže po zákaznících · vytíženost a spolehlivost dopravců (počet
jízd, zpoždění, rychlost vracení dokladů) · výkonnost řidičů · **obrat
jednotlivého vozu za období**.

**Prostoje vozů se nesledují** — zadavatel je z rozsahu vyřadil. Systém
tedy neřekne, kolik dní auto stálo naprázdno, jen kolik vydělalo.

### 3.6 Externí dispečink

Zadavatel nechal rozhodnutí na mně. **Volím: je to obyčejná přeprava.**
Zákazníkem je ten, kdo zboží posílá, dopravcem klient dispečinku; jízda
nese příznak „pod externím dispečinkem" a odkaz na klienta. Modul pak není
druhý systém vedle prvního, jen jiný pohled na tatáž data — a všechno, co
už umí přepravy (objednávka, doklady, tabule, veřejné odkazy), platí i tady.
Kdyby se ukázalo, že to nestačí, dá se to rozdělit později; opačně to jde hůř.

Modul obsahuje:

- **Klienti dispečinku** s vlastním vozovým parkem a řidiči.
- **Denní plán na každý vůz** — tabule řazená po vozidlech, ne po dnech.
- **Zakázky, které vozu sháníme** — přehled vytěžení: co, za kolik, pro koho.
- **Podklad k fakturaci služby** — **způsob účtování a sazba jsou údaj
  u každého klienta zvlášť** (paušál za vůz, procento z obratu, částka za
  jízdu — s každým jinak). Sazby dodá zadavatel, nedomýšlejí se.

**Rozhodnutí při stavbě (2. 9. 2026):** odesílateli fakturuje klient sám;
systém mu účtuje jen odměnu za dispečink. Jízdy pod dispečinkem se proto
nepočítají do tržby, nákladů ani marže spedice a nejsou v podkladech po
dopravcích a zákaznících — mají vlastní pohled ve Fakturaci. Kdyby některý
klient jel jako subdodavatel spedice (odesílateli fakturujete vy), nechá se
jízda bez příznaku a je to běžná přeprava s marží. Viz otázka 11.

### 3.7 Veřejné odkazy bez hesla

Dlouhý nehádatelný kód místo přihlášení. Kdo odkaz má, vidí **jen tu jednu
věc** — proto do něj nesmí přijít nic, co adresát nemá vědět.

| Komu | Co vidí | Co smí udělat |
|---|---|---|
| **Zákazník** | stav a termíny, místa a časová okna, cena přepravy | nic |
| **Dopravce** | svou objednávku | potvrdit ji, nahrát doklady, doplnit SPZ a řidiče, nahlásit zpoždění |
| **Řidič** | pokyny k jízdě | odklikat splněné body trasy |

**Cena dopravce a marže se ven nedostanou nikdy.** Cena zákazníka jde jen
zákazníkovi.

**Odkaz přestane fungovat měsíc po vykládce.** Dopravce stihne dohrát
doklady, zákazník si stihne dohledat, co potřebuje, a staré odkazy neleží
živé v cizích e-mailech navěky. Odvolat jde i dřív.

### 3.8 Doklady a komunikace

- **Přílohy k přepravě** — skeny a fotky dokladů (základ hotový, zapojit).
- **E-mail z aplikace.** Objednávka dopravci chodí tak, že **celá je v těle
  e-mailu a pod ní je odkaz** na potvrzení a nahrání dokladů. Aplikace neumí
  vyrobit PDF — nemá žádné knihovny — takže příloha by stejně nebyla.
- **WhatsApp jedním kliknutím** — tlačítko sestaví odkaz s předvyplněnou
  zprávou; odeslání potvrdíte v telefonu. Nestojí nic a nepotřebuje API.
- **SMS řidičům** — potřebuje placenou bránu, viz otevřené otázky.
- **Historie komunikace u přepravy** — kdo komu volal a co bylo dohodnuto.

### 3.9 Hlídání

Ranní **souhrn e-mailem** oběma dispečerům · blížící se nakládka **bez
dopravce** · doklady chybějící **déle než týden** · končící pojistka
dopravce. Spouští se z hostingu naplánovanou úlohou; ručně jde spustit
z Nastavení.

### 3.10 Ovládání

- **Světlý režim** vedle dnešního tmavého. Firemní barvy zůstávají, mění se
  plochy; přepínač si nastaví každý sám.
- **Rychlé hledání a klávesové zkratky** — jedno pole, do kterého se napíše
  číslo zásilky, místo nebo firma.
- **Hromadné akce v seznamu** — označit deset přeprav a najednou u nich
  zapnout přijaté doklady nebo je označit za vyfakturované.
- **Pořádné ovládání z mobilu** u klíčových obrazovek.

### 3.11 Role a přístupy

Dnešní dvě role nestačí. Systém má počítat se čtyřmi:

| Role | Vidí | Nesmí |
|---|---|---|
| **Správce** | všechno | — |
| **Dispečer** | přepravy, dopravce, ceny dopravce; ceny zákazníka podle práva | správu uživatelů a nastavení |
| **Účetní** | podklady, faktury, doklady, pohledávky | zasahovat do dispečinku |
| **Brigádník** | zásilky a doklady k přepisu | **jakoukoliv cenu, i cenu dopravce** |

Brigádnická role vyžaduje změnu: dnešní právo řeší jen ceny zákazníka
a marže, cena dopravce je vidět vždycky.

**Rozhodnutí při stavbě (2. 9. 2026):** druhý faktor je kód z aplikace
v telefonu (TOTP), protože nepotřebuje SMS bránu ani doručitelnou poštu;
tajemství se opisuje ručně, QR kód systém nekreslí. Účetní na kartě
přepravy mění jen doklady a čísla faktur.

Se třetím dispečerem přibude **vlastník přepravy** (kdo ji má na starosti)
a ochrana proti tomu, aby dva lidé upravovali tutéž zásilku proti sobě.

### 3.12 Provoz

- **Automatické zálohy** — denní kopie databáze, starší než měsíc se mažou,
  plus tlačítko „stáhnout zálohu teď".
- **Přihlášení druhým faktorem** — systém je na veřejné adrese a nese osobní
  údaje zákazníků i řidičů.
- **Přehled, kdo co změnil** — jeden společný protokol napříč systémem
  pro správce.

Automatické mazání starých osobních údajů zadavatel **nechtěl**. Doba
uchování zůstává otevřená (viz 6).

### 3.13 Napojení na Airtable, Blue Yonder a Fakturoid

Zadavatel rozhodl **napojit systém přímo přes API**, ne jen přes soubor.
Týká se to Airtable a Blue Yonderu i Fakturoidu, ze kterého se tahá,
které faktury jsou uhrazené.

> **Tohle mění dosavadní pravidlo.** `CLAUDE.md` dnes říká, že propojení
> patří do samostatné pipeline mimo tento repozitář. Až se napojení bude
> stavět, **musí se to pravidlo v `CLAUDE.md` přepsat ve stejném commitu**,
> jinak bude dokumentace lhát.
>
> Co platí dál bez výjimky: **přihlašovací údaje, identifikátory bází
> a kódy nástěnek se do repozitáře nedostanou.** Patří do `config.php`,
> který je v `.gitignore`. Repozitář je veřejný.

---

## 4. Co systém nahrazuje

Trello, týdenní KT report v Excelu i cenový Excel **nakonec nahradí** —
ale ne hned. Nejdřív poběží vedle sebe a vypnou se, až se systém osvědčí.
Nesmí nastat den, kdy není podle čeho odbavit.

---

## 5. Pořadí prací

1. **Trasa jako seznam bodů + adresář míst, šablony, návrh ceny** (3.1, 3.2)
   — sahá do jádra, proto první.
2. Doklady a komunikace ven — přílohy, odeslání objednávky, veřejné odkazy (3.7, 3.8)
3. Peníze — Fakturoid, pohledávky, závazky (3.4)
4. Externí dispečink (3.6)
5. Okolí zakázky — nabídky, ceníky, pojistky dopravců (3.3)
6. Vyhodnocení (3.5), hlídání (3.9), ovládání (3.10), role (3.11), provoz (3.12)
7. Napojení přes API (3.13) — až nakonec, po přepsání pravidla v CLAUDE.md

---

## 6. Otevřené otázky — bez odpovědi nelze zapracovat

Nic z toho si nelze domyslet.

1. **Podmínky objednávky přepravy** — text pro dopravce. Dokud chybí,
   objednávka tiskne viditelné upozornění.
2. **Číselná řada** — tvar a poslední použité číslo, aby řada navázala.
3. **Sazby externího dispečinku** — u každého klienta způsob účtování
   a částka. Pole jsou na kartě klienta připravená, zatím prázdná.
4. **Mapová služba** — který poskytovatel, kdo platí, pustí ho hosting ven?
   Bez ní se kilometry zadávají u jízdy ručně; pásma i sazba za kilometr
   v ceníku s ručními kilometry fungují.
5. **SMS brána** — která a s jakými přístupy. Mají řidiči WhatsApp?
6. **Fakturoid** — přístup k API (client_id, client_secret) do `config.php`
   na hostingu. Napojení je postavené a ověřené proti napodobenině; živě
   ho ověříte prvním kliknutím na „Načíst úhrady".
7. **Naplánovaná úloha na hostingu** — má tarif u VAS Hostingu cron?
   Bez něj se ranní souhrn spustí až při prvním otevření systému toho dne.
   Spouštěč je hotový: adresa `index.php?s=hlidani&klic=…`, klíč do
   `config.php`.
8. **Doba uchování osobních údajů** — jak dlouho držet jména a telefony
   řidičů u uzavřených přeprav.
9. **Airtable a Blue Yonder** — které báze, tabulky a účty, a jaká data
   mají téct kterým směrem.
10. **Živé volání ARES** — prostředí, ve kterém aplikace vznikala, nepustí
    ven. Po nasazení zkusit jedno IČO a ověřit.
11. **Kdo fakturuje odesílateli u externího dispečinku** — systém počítá
    s tím, že klient sám a vy účtujete jen odměnu. Pokud u některého klienta
    fakturujete odesílateli vy a k tomu odměnu, je potřeba říct, jak se to
    má počítat do marže.

---

## 7. Co se nesmí ztratit

Platí pro každou další změnu:

- **Žádný build, žádné závislosti.** Soubory se nahrávají tak, jak jsou.
- **Česky** — obsah, třídy, identifikátory, komentáře i commit messages.
- **Plocha, linka, text.** Barvy jen z proměnných firemního stylu.
- **`config.php` a `data/` nikdy do repozitáře.** Repozitář je veřejný
  a evidence nese osobní údaje zákazníků, dopravců i řidičů.
- **Ceny zákazníka a marže vidí jen ten, kdo na ně má právo** — a kdo ho
  nemá, nesmí ta pole ani přepsat.
- Ověřuje se vykreslením v prohlížeči, ne odhadem.

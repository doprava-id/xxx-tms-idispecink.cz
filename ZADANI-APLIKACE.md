# Zadání — provozní systém iDispečink.cz

**Datum:** 1. 9. 2026
**Větev:** `claude/idispecink-tms-software-uosnh7`
**Stav:** postavené jádro (14 obrazovek) + načtení z ARES. Zbytek níže čeká.

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
| Přílohy k přepravě | základ napsaný, **nezapojený** |

---

## 3. Co se má postavit

### 3.1 Trasa jako seznam bodů — zasahuje do jádra

Dnešní model umí **jedna nakládka → jedna vykládka**. Rozvoz ze skladu na
pět míst ani sběr od tří dodavatelů zadat nejdou.

Trasa se stane **seznamem bodů**. Každý bod má: pořadí, druh (nakládka /
vykládka), místo, adresu, časové okno, zboží, hmotnost, palety, kontakt
a poznámku.

**Přizpůsobit se tomu musí všechno, co dnes čte `nakladka_*` a `vykladka_*`:**
seznam přeprav, dispečerská tabule, objednávka přepravy, pokyny řidiči,
podklady k fakturaci, import i export. Proto se to dělá **jako první** —
každý další modul postavený na starém modelu by se pak předělával.

Stávající data se převedou: z každé přepravy vzniknou dva body.

### 3.2 Pomoc při zadávání

- **Adresář míst** — místo jako číselník: adresa, brána, kontakt, otevírací
  doba, poznámka („ohlásit se na vrátnici"). Vyberete místo, zbytek se doplní.
- **Návrh ceny podle historie trasy** — při zakládání ukáže, za kolik se
  stejná nebo podobná trasa vozila naposled a jaká na ní byla marže.
  Žádný automatický ceník, jen paměť.
- **Šablony opakovaných přeprav** — stálá linka, ze které se založí celý
  týden dopředu.
- **Kilometry a čas trasy** — vzdálenost mezi body a cena za kilometr.
  **Vyžaduje mapovou službu zvenku** — viz otevřené otázky.

### 3.3 Okolí zakázky

- **Nabídky a poptávky** — stupeň před zakázkou. Poptávka přijde, nacení se,
  čeká na odpověď. Vidět, kolik nabídek visí a kolik jich prochází.
  Z nabídky se jedním kliknutím stane přeprava.
- **Ceníky zákazníků a stálé linky** — dohodnuté ceny na opakované trasy,
  nabídnou se samy.
- **Smlouvy a pojistky dopravců** — platnost pojištění odpovědnosti
  a oprávnění u každého dopravce. Systém upozorní na blížící se konec
  a varuje před vystavením objednávky dopravci s prošlou pojistkou.
- **Reklamace, škody a pokuty** — navedené na konkrétní přepravu a dopravce.

### 3.4 Peníze

- **Pohledávky** — které vydané faktury jsou po splatnosti a jak dlouho.
- **Závazky vůči dopravcům** — co komu dlužíme a kdy je to splatné,
  podle týdenní fakturace po zajetí vozu.
- **Ziskovost zakázky do detailu** — marže po odečtení stání, víceprací,
  storna a pokut, ne jen hrubý rozdíl cen.
- **Export do Fakturoidu** — faktury se vystavují ve Fakturoidu, systém
  předá podklad ve tvaru, který se naimportuje.

### 3.5 Vyhodnocení

Obraty a marže po zákaznících · vytíženost a spolehlivost dopravců (počet
jízd, zpoždění, rychlost vracení dokladů) · výkonnost řidičů · **ekonomika
jednotlivého vozu** (co vydělá za období, kolik dní stál naprázdno) — ta je
jádrem toho, co se ukazuje klientovi externího dispečinku.

### 3.6 Externí dispečink — plný modul

- **Klienti dispečinku** s vlastním vozovým parkem a řidiči.
- **Denní plán na každý vůz** — tabule řazená po vozidlech, ne po dnech.
- **Zakázky, které vozu sháníme** — evidence vytěžení: co, za kolik, pro koho.
- **Podklad k fakturaci služby** — **způsob účtování a sazba jsou údaj
  u každého klienta zvlášť** (paušál za vůz, procento z obratu, částka za
  jízdu — s každým jinak). Sazby dodá zadavatel, nedomýšlejí se.

### 3.7 Veřejné odkazy bez hesla

Dlouhý nehádatelný kód místo přihlášení. Kdo odkaz má, vidí **jen tu jednu
věc** — proto do něj nesmí přijít nic, co adresát nemá vědět.

| Komu | Co vidí | Co smí udělat |
|---|---|---|
| **Zákazník** | stav a termíny, místa a časová okna, cena přepravy | nic |
| **Dopravce** | svou objednávku | potvrdit ji, nahrát doklady, doplnit SPZ a řidiče, nahlásit zpoždění |
| **Řidič** | pokyny k jízdě | — |

**Cena dopravce a marže se ven nedostanou nikdy.** Cena zákazníka jde jen
zákazníkovi.

### 3.8 Doklady a komunikace

- **Přílohy k přepravě** — skeny a fotky dokladů (základ hotový, zapojit).
- **E-mail z aplikace** — objednávky, odkazy, souhrny.
- **WhatsApp jedním kliknutím** — tlačítko sestaví odkaz s předvyplněnou
  zprávou; odeslání potvrdíte v telefonu. Nestojí nic a nepotřebuje API.
- **SMS řidičům** — potřebuje placenou bránu, viz otevřené otázky.
- **Historie komunikace u přepravy** — kdo komu volal a co bylo dohodnuto,
  aby to druhý dispečer nemusel lovit z hlavy.

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

### 3.11 Provoz

- **Automatické zálohy** — denní kopie databáze, starší než měsíc se mažou,
  plus tlačítko „stáhnout zálohu teď".
- **Přihlášení druhým faktorem** — systém je na veřejné adrese a nese osobní
  údaje zákazníků i řidičů.
- **Přehled, kdo co změnil** — jeden společný protokol napříč systémem
  pro správce.

Automatické mazání starých osobních údajů zadavatel **nechtěl**. Doba
uchování zůstává otevřená (viz 6).

### 3.12 Napojení na Airtable a Blue Yonder

Zadavatel rozhodl **napojit systém přímo přes API**, ne jen přes soubor.

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
3. Peníze — Fakturoid, pohledávky, závazky, ziskovost (3.4)
4. Externí dispečink (3.6)
5. Okolí zakázky — nabídky, ceníky, smlouvy, reklamace (3.3)
6. Vyhodnocení (3.5), hlídání (3.9), ovládání (3.10), provoz (3.11)
7. Napojení přes API (3.12) — až úplně nakonec, po přepsání pravidla

---

## 6. Otevřené otázky — bez odpovědi nelze zapracovat

Nic z toho si nelze domyslet.

1. **Podmínky objednávky přepravy** — text pro dopravce. Dokud chybí,
   objednávka tiskne viditelné upozornění.
2. **Číselná řada** — tvar a poslední použité číslo, aby řada navázala.
3. **Sazby externího dispečinku** — u každého klienta způsob účtování
   a částka.
4. **Mapová služba** — který poskytovatel, kdo platí, pustí ho hosting ven?
5. **SMS brána** — která a s jakými přístupy. Mají řidiči WhatsApp?
6. **Fakturoid** — účet a zda stačí soubor k importu, nebo se má volat API.
7. **Naplánovaná úloha na hostingu** — má tarif u VAS Hostingu cron?
   Bez něj se ranní souhrn spustí až při prvním otevření systému toho dne.
8. **Doba uchování osobních údajů** — jak dlouho držet jména a telefony
   řidičů u uzavřených přeprav.
9. **Airtable a Blue Yonder** — které báze, tabulky a účty, a jaká data
   mají téct kterým směrem.
10. **Živé volání ARES** — prostředí, ve kterém aplikace vznikala, nepustí
    ven. Po nasazení zkusit jedno IČO a ověřit.

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

# iDispečink.cz — web a provozní systém

Repozitář spedice **iDispečink.cz s.r.o.** Jsou v něm dvě věci:

- **prezentační web** v kořeni — statické HTML, CSS a JavaScript,
- **provozní systém** v `aplikace/` — vnitřní dispečerská aplikace za
  přihlášením (PHP, bez frameworku).

Čisté HTML, CSS, JavaScript a PHP, **žádný build ani závislosti**. Soubory stačí
nakopírovat na hosting — funguje na obyčejném FTP i kdekoliv jinde. Aplikace
navíc potřebuje PHP s `pdo_sqlite` a právo zápisu do `aplikace/data/`;
podrobnosti jsou v kapitole [Provozní systém](#provozní-systém-aplikace).

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
zasady-osobnich-udaju.html   Informace o zpracování osobních údajů
404.html                     Chybová stránka
favicon.ico                  Ikona pro starší prohlížeče (musí zůstat v kořeni)
.htaccess                    Nastavení pro Apache (404, komprese, cache, hlavičky)
robots.txt                   Pravidla pro roboty
sitemap.xml                  Mapa webu
assets/css/                  firemni-styl.css — jediná definice barev a komponent
assets/js/                   main.js — menu a hlavička; mereni.js — analytika
assets/img/                  Logo, favicon a náhledový obrázek
PREDANI-WEBU.md              Předávací soubor — stav, rozhodnutí revize, otevřené otázky

aplikace/                    Provozní systém — vnitřní aplikace za přihlášením
  index.php                  Jediný vstupní bod a směrovač
  config.vzor.php            Vzor konfigurace (config.php do repozitáře nepatří)
  aplikace.css, aplikace.js  Nadstavba firemního stylu a obsluha rozhraní
  data/                      Databáze — mimo repozitář, .htaccess vše zakazuje
  zdroj/                     Databáze, přihlášení, šablona, pomocné funkce
  zdroj/stranky/             Jedna obrazovka = jeden soubor
```

Chystá se obsahová revize webu. Její závazná rozhodnutí, otevřené otázky
a důsledky, které se nesmí přehlédnout, jsou v `PREDANI-WEBU.md`.

## Logo

Všechny soubory pocházejí z **originálního vektoru** dodaného v PDF
(`idispecink-logo-vektor-transparent.pdf`) — nejde o překres. Křivky jsou
převzaté beze změny, mění se jen výřez, uspořádání a u tmavých variant barva
antracitových tvarů na krémovou.

| Soubor | Použití |
|---|---|
| `logo-idispecink.svg` | vodorovné, na tmavé pozadí — hlavička a patička webu |
| `logo-idispecink-tmavy.svg` | vodorovné, na světlé pozadí — dokumenty, faktury |
| `logo-idispecink-ctverec.svg` | svislé (původní sazba), na světlé pozadí |
| `logo-idispecink-ctverec-tmave-pozadi.svg` | svislé, na tmavé pozadí |
| `znacka.svg` | samotný piktogram bez nápisu, na světlé pozadí |
| `znacka-tmava.svg` | samotný piktogram bez nápisu, na tmavé pozadí |
| `favicon.svg` | ikona v záložce — čtvercový výřez s vozem |
| `favicon.ico` | tatáž ikona pro starší prohlížeče; leží v kořeni, protože prohlížeče i některé nástroje si ji vyžádají z `/favicon.ico` bez ohledu na odkazy v HTML |
| `apple-touch-icon.png` | ikona po přidání webu na plochu mobilu |
| `og-idispecink.png` | náhled při sdílení odkazu (1200×630) |
| `logo-idispecink*.png` | rastrové exporty pro e-mailové podpisy a dokumenty |

Poměry stran: vodorovné **6,305 : 1**, svislé **1,961 : 1**, piktogram
**1,975 : 1**. Při výměně souborů upravte i `width`/`height` u `<img>`
v HTML, jinak stránka při načítání poskočí.

### Tmavé pozadí

V originálu je korba vozu žlutá, ale **kabina antracitová**. Překlopení všech
antracitových tvarů na krémovou proto nefunguje — vůz dostane bílou kabinu
a značka čte jako jiné logo.

Tmavé varianty místo toho drží původní barvy a od pozadí je odděluje krémová
linka: antracitová cesta má `stroke="#F0EDE6"` o tloušťce **6,5** jednotky
souřadnic původního PDF, s `paint-order="stroke fill"`, takže linka vede vně
tvaru a nesnídá výplň. Nápis je na tmavém pozadí plnou krémovou — obrysové
písmo se špatně čte.

Poznat variantu pro tmavé pozadí jde spolehlivě jen podle obsahu, ne podle názvu:
krémovou linku (`stroke="#F0EDE6"` s `paint-order`) mají právě `logo-idispecink.svg`,
`logo-idispecink-ctverec-tmave-pozadi.svg` a `znacka-tmava.svg`. **Přípony `-tmavy`
a `-tmava` přitom znamenají opak:** `logo-idispecink-tmavy.svg` je tmavě zbarvené logo
na světlé pozadí, kdežto `znacka-tmava.svg` je varianta pro tmavé pozadí. Než soubor
někam vložíte, ověřte si `stroke`.

Tloušťka je kompromis ověřený vykreslením: pod 3 jednotky oddělení mizí
ve velikosti hlavičky (38 px) i ve faviconu, nad 8 začne linka požírat okna
a mřížku kabiny.

Všechny varianty — světlé i tmavé — mají v `viewBox` odsazení 4 jednotky,
aby měly shodný poměr stran a linka se neořízla.

### Na co si dát pozor při úpravách

Obě barvy jsou v každém souboru vždy **jedna cesta** s `fill-rule="evenodd"`.
Díry — okna kabiny, náboje kol, vnitřek monitoru — drží právě tímto pravidlem.
Když podcesty rozdělíte do samostatných elementů `<path>`, díry se vyplní
a z kabiny se stane tmavá plocha.

Piktogram a nápis nejdou v originálu oddělit podle barev — obrys monitoru
a tmavé části vozu jsou jedna souvislá kontura. Rozdělení na značku a nápis
je proto vedené vodorovným předělem: podcesty se středem nad y = 337
(v souřadnicích původního PDF) tvoří piktogram, ostatní nápis.

Vodorovná varianta má nápis zvětšený na 130 % vůči původní sazbě — vedle
piktogramu by v původním poměru působil drobně.

## Formuláře

Formuláře na stránkách *Poptat přepravu* a *Pro dopravce* odesílá server:
běžný POST na `odeslani.php`, který zprávu pošle e-mailem na
`doprava@idispecink.cz` a přesměruje na potvrzení `odeslano.html`. Funguje
to i s vypnutým JavaScriptem. Roboty odchytává skryté pole (honeypot) —
vyplněná past znamená zahození zprávy. Web obsah formulářů nikam neukládá,
jen ho předá poštou.

**Hosting proto musí umět PHP a funkci `mail()`** — na VAS Hostingu běžně
k dispozici. A pozor na doručitelnost: skript posílá z adresy
`web@idispecink.cz` přes servery VAS Hostingu, zatímco pošta domény běží na
Microsoft 365. Aby zprávy nepadaly do spamu, **přidejte do SPF záznamu domény
servery VAS Hostingu** (v DNS administraci; přesný include sdělí podpora
hostingu).

## Měření návštěvnosti

Web je připravený na Google Analytics 4 se souhlasovou lištou: skript
`assets/js/mereni.js` načte měření **až po souhlasu návštěvníka**, volbu si
pamatuje v prohlížeči a tlačítko „Nastavení cookies" v patičce ji kdykoliv
změní. Bez souhlasu se skript Googlu vůbec nenačte.

Před nasazením **vyplňte měřicí ID** (konstanta `GA_MERENI` v `mereni.js`,
tvar `G-XXXXXXXXXX` z administrace GA). Dokud je prázdné, web nic neměří
a lišta se nezobrazuje — zásady zpracování údajů ale měření už popisují,
takže bez ID slibují víc, než web dělá. V administraci GA zároveň nastavte
dobu uchování dat na **14 měsíců**, aby odpovídala zásadám.

## Obsah je kompletní

Web už neobsahuje žádné zástupné texty. Kdyby v budoucnu nějaký vznikl,
označuje se třídou `.doplnit` a komentářem `PLACEHOLDER` a najdete ho takto:

```bash
grep -rn "doplnit\|PLACEHOLDER" *.html
```

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
| Provozní pohotovost | 24/7 pro probíhající přepravy |

Objevují se na stránkách *Kontakt*, *O nás*, v zásadách zpracování údajů,
v patičce všech stránek a ve strukturovaných datech (JSON-LD) na úvodní stránce.
Při změně je potřeba je upravit na všech těchto místech.

**Zásady zpracování osobních údajů nebyly právně zkontrolované.** Obsah
odpovídá skutečnosti — zpracovatelé, doby uchování i účely jsou dodané
zadavatelem — ale znění nikdo s právním vzděláním neviděl. Před delším
provozem stojí za to ho nechat projít.

## Dvě okna na úvodní stránce

Nabídka pro zákazníky a pro dopravce je na úvodní stránce ve dvou oddělených
oknech — zákazník nemusí na první pohled číst, co nabízíme dopravcům.
Přepínají se dvěma skrytými radio přepínači, takže to **funguje i s vypnutým
JavaScriptem** a klávesnicí se mezi okny přechází šipkami jako v běžné
skupině voleb.

Obsah obou oken je v HTML vždy přítomný (jen skrytý přes `display`), takže ho
vyhledávače najdou. Na papíře se záložky netisknou a vytisknou se obě okna
pod sebou.

## Chování bez JavaScriptu a při tisku

Web funguje i s vypnutým JavaScriptem. Formuláře jsou obyčejný POST, takže
se odešlou i bez skriptů; menu se zobrazí rozbalené (`<noscript>` blok
v hlavičce každé stránky), hlavička zůstává přilepená nahoře a měření
návštěvnosti se bez JavaScriptu vůbec nespustí.

Tiskový styl v `firemni-styl.css` převádí tmavý motiv na černobílý: skryje
navigaci, patičku a tlačítka, zesvětlí plochy a ztmaví text. Barvy z tmavého
motivu jsou na několika místech zapsané přímo v atributu `style`, proto je
tiskový blok přebíjí přes `!important` — při úpravách stránek na to pozor.

## Nasazení

Soubory nakopírujte do kořene webu — celý obsah repozitáře kromě `README.md`,
`CLAUDE.md`, `PREDANI-WEBU.md`, `LICENSE` a `.gitignore`; ty na hosting
nepatří. Web nic nekompiluje, takže funguje hned po nahrání.

`.htaccess` nastaví chybovou stránku, kompresi, cache a bezpečnostní
hlavičky. Na hostingu bez Apache se ignoruje a nic nerozbije; tytéž věci
si pak nastavte v administraci hostingu.

Chování je ověřené na skutečném Apachi: chybová stránka vrací stav 404,
komprese ušetří 58–74 % objemu HTML, CSS i JavaScriptu, statická aktiva mají
roční cache a HTML žádnou, bezpečnostní hlavičky se posílají a `.htaccess`
sám je zvenčí nedostupný (403).

Seznamy typů obsahu uvádějí u skriptů `text/javascript` i
`application/javascript` a u ikony `image/vnd.microsoft.icon` i `image/x-icon`
— novější a starší Apache je hlásí odlišně a při uvedení jen jednoho z nich
se komprese i cache tiše minou účinkem.

**Přesměrování na HTTPS a na doménu bez www je v `.htaccess` zakomentované.**
Odkomentujte ho, až budete mít na doméně funkční certifikát — dřív by web
znepřístupnilo.

Po nasazení stojí za kontrolu: náhled odkazu při sdílení (og obrázek),
funkčnost obou formulářů z běžného počítače a to, že `sitemap.xml`
a `robots.txt` odpovídají skutečné doméně.

## Provozní systém (`aplikace/`)

Vnitřní dispečerská aplikace za přihlášením. S prezentačním webem sdílí jen
firemní styl a hosting — na veřejných stránkách o ní není ani zmínka a odkaz
na ni nikde nevede.

### Co umí

| Modul | Co dělá |
|---|---|
| **Přehled** | co se dnes a zítra nakládá, co nemá dopravce, kde chybí doklady, marže za měsíc |
| **Přepravy** | evidence zásilek — trasa jako seznam bodů (kolik nakládek a vykládek je potřeba), náklad, dopravce, ceny, doklady, fakturace, přílohy; filtry, stránkování, protokol změn. U bodu se odškrtává splnění, stav jízdy se z toho dopočítá |
| **Místa** | společný adresář skladů a ramp — adresa, brána, kontakt, otevírací doba; u bodu trasy se vybere a zbytek se doplní |
| **Stálé linky** | přeprava označená jako šablona s dny v týdnu; na kliknutí se z ní založí celý týden, státní svátky se přeskočí |
| **Dispečink** | týdenní tabule po dnech podle data nakládky; zásilky bez dopravce mají červenou hranu |
| **Firmy** | zákazníci i dopravci v jednom adresáři, vozidla, řidiči a prověření dopravce (registry, oprávnění, pojištění, doklady, reference); načtení názvu, adresy a DIČ z ARES podle IČO |
| **Objednávka přepravy** | tisková objednávka pro dopravce, číslo přepravy je zároveň číslem objednávky; odeslání e-mailem celá v těle zprávy s odkazem na potvrzení |
| **Odkazy ven** | odkazy bez hesla: zákazník vidí stav, termíny a cenu; dopravce potvrdí objednávku, doplní vůz a řidiče, nahlásí zpoždění a nahraje doklady; řidič vidí pokyny a odklikává zastávky. Tlačítko WhatsApp odkaz rovnou předvyplní do zprávy |
| **Fakturace** | obrat, marže a podklady k fakturaci po dopravcích i zákaznících za období; přehled toho, co fakturaci brání |
| **Nastavení** | údaje firmy, číselná řada, podmínky objednávky, uživatelé a jejich práva |
| **Import / export** | obecné načtení přeprav z CSV s ručním přiřazením sloupců; export do CSV pro Excel |

### Co je potřeba na hostingu

- **PHP 7.4 a novější** s rozšířením `pdo_sqlite` (nebo `pdo_mysql`).
- **Právo zápisu do `aplikace/data/`** — práva 0770. Tam si aplikace založí
  databázi.
- Nahrát je nutné i skryté soubory `.htaccess` v `aplikace/`, `aplikace/data/`
  a `aplikace/zdroj/`. **Bez nich by šla databáze i přílohy stáhnout přímo z webu.**
- Načtení firmy z ARES potřebuje, aby hosting pustil odchozí HTTPS. Když
  nepustí, tlačítko to řekne a údaje se vyplní ručně — nic dalšího nestojí.
  Většina FTP klientů soubory začínající tečkou ve výchozím nastavení
  nezobrazí — zapněte si zobrazení skrytých souborů.

### První spuštění

1. Otevřete `https://idispecink.cz/aplikace/`.
2. Aplikace zjistí, že nemá žádného uživatele, a nabídne založení správce.
   Heslo musí mít aspoň 10 znaků.
3. V **Nastavení** doplňte:
   - **číselnou řadu** — tvar a číslo, kterým se má pokračovat, aby řada
     navázala na to, co vystavujete dnes,
   - **podmínky objednávky přepravy** — text, který se tiskne dopravci.
     Dokud je pole prázdné, objednávka to na sobě viditelně přizná.
4. Další uživatele přidáte tamtéž. U každého se zvlášť přepíná právo
   **vidět ceny zákazníka a marže**; cenu dopravce potřebuje ke své práci
   každý dispečer, obchodní stranu ne. Správce vidí všechno.

### Konfigurace

Bez souboru `config.php` běží aplikace na SQLite v `aplikace/data/` a nic se
nenastavuje. Chcete-li MySQL, delší nebo kratší odhlašování nebo vynucené
HTTPS, zkopírujte vzor:

```bash
cp aplikace/config.vzor.php aplikace/config.php
```

`config.php` **není v repozitáři** (je v `.gitignore`) — repozitář je veřejný.
Vynucené HTTPS zapněte až s funkčním certifikátem, stejně jako přesměrování
zakomentované v kořenovém `.htaccess`.

### Pošta a odkazy ven

Objednávka odchází z adresy nastavené v Nastavení (výchozí
`web@idispecink.cz`, stejná jako u formulářů webu), odpověď přijde tomu, kdo
ji poslal. Aby zprávy nekončily ve spamu, musí SPF záznam domény zahrnovat
servery hostingu, stejně jako u formulářů. Do každé odeslané objednávky se
dá poslat skrytá kopie na vlastní adresu.

Odkazy bez hesla platí měsíc po vykládce a jdou kdykoliv zrušit. Kdo odkaz
má, vidí jen tu jednu přepravu; nepřeposílejte ho dál, než je potřeba.
Adresa, kterou odkazy nesou, se odvozuje z požadavku; za proxy ji nastavte
v Nastavení ručně.

### Zálohování

Celá databáze je jediný soubor `aplikace/data/idispecink.sqlite`; přílohy
k přepravám leží vedle v `aplikace/data/prilohy/`. Stáhněte si obojí po FTP;
kopie je plnohodnotná záloha. Pro archiv mimo systém slouží
navíc export přeprav a firem do CSV v Nastavení.

### Bezpečnost a osobní údaje

Hesla se ukládají jen jako otisk (`password_hash`) a přečíst se nedají —
zapomenuté heslo nastaví správce nové. Každý zápis je chráněný jednorázovým
tokenem, po pěti neúspěšných přihlášeních se adresa na čtvrt hodiny zablokuje
a sezení se po osmi hodinách nečinnosti samo ukončí. Aplikace posílá
`X-Robots-Tag: noindex` a je vyloučená v `robots.txt`.

Evidence obsahuje osobní údaje kontaktních osob a řidičů. **Nikdy ji
necommitujte** a nenahrávejte exporty do repozitáře — `*.csv` a
`aplikace/data/` jsou proto v `.gitignore`.

Zásady zpracování osobních údajů na webu se tím **nemění**: popisují, co se děje
s údaji návštěvníků webu, a ten do žádné databáze nic neukládá dál. Aplikace
zpracovává údaje obchodních partnerů, ne návštěvníků.

## Lokální spuštění

```bash
php -S 127.0.0.1:8000        # web i aplikace
python3 -m http.server 8000  # jen statický web, PHP neběží
```

Pak otevřete <http://localhost:8000> — web —, nebo
<http://localhost:8000/aplikace/> — provozní systém. Ten si při prvním otevření
založí databázi a nabídne účet správce; smazáním souboru
`aplikace/data/idispecink.sqlite` se instalace vrátí na začátek.

Testy nejsou. Změny se ověřují vykreslením v prohlížeči — všechny stránky webu
i aplikace, šířky 1280, 768 a 390 px, oba formuláře, vypnutý JavaScript
a tiskový režim.

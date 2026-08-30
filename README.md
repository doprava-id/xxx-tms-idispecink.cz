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
zasady-osobnich-udaju.html   Informace o zpracování osobních údajů
404.html                     Chybová stránka
favicon.ico                  Ikona pro starší prohlížeče (musí zůstat v kořeni)
.htaccess                    Nastavení pro Apache (404, komprese, cache, hlavičky)
robots.txt                   Pravidla pro roboty
sitemap.xml                  Mapa webu
assets/css/                  firemni-styl.css — jediná definice barev a komponent
assets/js/                   main.js — mobilní menu a odesílání formulářů
assets/img/                  Logo, favicon a náhledový obrázek
PREDANI-WEBU.md              Předávací soubor — stav, rozhodnutí revize, otevřené otázky
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

Statický web nemá backend. Formuláře na stránkách *Kontakt* a *Pro dopravce*
poskládají text zprávy a otevřou poštovního klienta návštěvníka (`mailto:`) —
odeslání provede sám návštěvník. Web tedy sám nesbírá a neukládá žádná data,
nepoužívá cookies ani měření návštěvnosti.

Příjemce se nastavuje atributem `data-prijemce` na elementu `<form>`.
Pokud budete chtít odesílání na pozadí, stačí formuláře přesměrovat na
službu typu Formspree nebo Web3Forms — obsluha je v `assets/js/main.js`.
Tím ale začnete data zpracovávat a bude potřeba upravit i zásady ochrany údajů.

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
| Provozní doba | nonstop 24/7 |

Objevují se na stránkách *Kontakt*, *O nás*, v zásadách zpracování údajů,
v patičce všech stránek a ve strukturovaných datech (JSON-LD) na úvodní stránce.
Při změně je potřeba je upravit na všech těchto místech.

**Zásady zpracování osobních údajů nebyly právně zkontrolované.** Obsah
odpovídá skutečnosti — zpracovatelé, doby uchování i účely jsou dodané
zadavatelem — ale znění nikdo s právním vzděláním neviděl. Před delším
provozem stojí za to ho nechat projít.

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

## Lokální spuštění

```bash
python3 -m http.server 8000
```

Pak otevřete <http://localhost:8000>.

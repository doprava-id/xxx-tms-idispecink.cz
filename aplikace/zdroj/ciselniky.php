<?php
/* =========================================================================
   Číselníky — pevné seznamy hodnot, na kterých stojí formuláře i přehledy

   Rozsah služby: vnitrostátní celovozové přepravy po ČR. Typy vozidel
   odpovídají tomu, co firma skutečně vozí; „jiný typ" pokrývá dohodu.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Stavy přepravy v pořadí, v jakém jimi zásilka prochází.
   „zruseno" stojí mimo řadu. */
const STAVY = [
  "nova"        => "Nová",
  "objednana"   => "Objednaná",
  "nalozeno"    => "Naloženo",
  "vylozeno"    => "Vyloženo",
  "doklady"     => "Doklady kompletní",
  "fakturovano" => "Fakturováno",
  "zruseno"     => "Zrušeno",
];

/* Barevný stav pro štítek — drží se stavových barev firemního stylu. */
const STAVY_BARVA = [
  "nova"        => "ceka",
  "objednana"   => "bezi",
  "nalozeno"    => "bezi",
  "vylozeno"    => "bezi",
  "doklady"     => "hotovo",
  "fakturovano" => "hotovo",
  "zruseno"     => "zrus",
];

const TYPY_FIREM = [
  "zakaznik" => "Zákazník",
  "dopravce" => "Dopravce",
  "oboji"    => "Zákazník i dopravce",
];

const TYPY_VOZIDEL = [
  "plachta"  => "Plachtový návěs 13,6 m",
  "mega"     => "Mega",
  "lowdeck"  => "Lowdeck",
  "jiny"     => "Jiný typ podle dohody",
];

const DOKLADY = [
  "ceka"    => "Čekáme",
  "prijato" => "Přijaty",
  "chybi"   => "Chybí",
];

/* Způsob účtování externího dispečinku — s každým klientem jinak. */
const DISPECINK_UCTOVANI = [
  "pausal_vuz" => "Paušál za vůz a měsíc",
  "procento"   => "Procento z obratu vozů",
  "za_jizdu"   => "Částka za jízdu",
];

/* Čtyři role. Co která smí, hlídají funkce v autentizace.php. */
const ROLE = [
  "spravce"   => "Správce",
  "dispecer"  => "Dispečer",
  "ucetni"    => "Účetní",
  "brigadnik" => "Brigádník",
];
const ROLE_POPIS = [
  "spravce"   => "všechno včetně nastavení a uživatelů",
  "dispecer"  => "přepravy, dopravci a cena dopravce; cena zákazníka a marže podle práva",
  "ucetni"    => "podklady, faktury, doklady a pohledávky; do dispečinku nezasahuje",
  "brigadnik" => "zásilky a doklady k přepisu; žádnou cenu, ani cenu dopravce",
];

function nazev_stavu(?string $stav): string {
  return STAVY[$stav] ?? "—";
}

function nazev_typu_vozidla(?string $typ): string {
  return TYPY_VOZIDEL[$typ] ?? ($typ ? (string)$typ : "—");
}

/* Stavy, které se počítají do obratu — zrušené přepravy se nikam nesčítají. */
function stavy_zive(): array {
  return ["nova", "objednana", "nalozeno", "vylozeno", "doklady", "fakturovano"];
}

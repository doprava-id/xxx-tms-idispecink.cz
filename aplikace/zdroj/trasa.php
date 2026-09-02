<?php
/* =========================================================================
   Trasa — body jízdy a odvozený souhrn na přepravě

   Zdrojem pravdy je tabulka `body`. Pole nakladka_* a vykladka_* na
   přepravě jsou jen souhrn první nakládky a poslední vykládky, aby se
   seznamy, tabule a podklady nemusely přepisovat a zůstaly rychlé.
   Přepočítává je výhradně prepocitej_trasu() — nikdo jiný je nesmí
   zapisovat.

   Stav jízdy mezi „objednaná" a „vyloženo" se řídí splněnými body:
   splněná nakládka = naloženo, všechno splněné = vyloženo. Stavy
   „doklady", „fakturováno" a „zrušeno" se nastavují ručně a body
   na ně nesahají.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DRUHY_BODU = [
  "nakladka" => "Nakládka",
  "vykladka" => "Vykládka",
];

function nazev_druhu(?string $druh): string {
  return DRUHY_BODU[$druh] ?? "—";
}

function body_prepravy(int $preprava_id): array {
  return radky("SELECT b.*, m.nazev AS misto_nazev FROM body b
                 LEFT JOIN mista m ON m.id = b.misto_id
                WHERE b.preprava_id = ? ORDER BY b.poradi, b.id", [$preprava_id]);
}

/* „Pardubice → Zlín → Brno" */
function popis_trasy(array $body): string {
  $mista = [];
  foreach ($body as $b) $mista[] = trim((string)$b["misto"]) !== "" ? (string)$b["misto"] : "?";
  return implode(" → ", $mista);
}

/* Stav odvozený z bodů. Ruční stavy mimo řadu zůstávají, jak jsou. */
function stav_z_bodu(array $body, string $stav): string {
  if (in_array($stav, ["doklady", "fakturovano", "zruseno"], true) || !$body) return $stav;

  $vse = true; $nakladka_hotova = false;
  foreach ($body as $b) {
    if ((int)$b["splneno"] === 1) {
      if ($b["druh"] === "nakladka") $nakladka_hotova = true;
    } else {
      $vse = false;
    }
  }
  if ($vse) return "vylozeno";
  if ($nakladka_hotova) return "nalozeno";
  /* Nic splněné: naloženo/vyloženo bez bodů nedává smysl, vrať se k objednané. */
  if (in_array($stav, ["nalozeno", "vylozeno"], true)) return "objednana";
  return $stav;
}

/* Přepočítá souhrn první nakládky a poslední vykládky a stav jízdy. */
function prepocitej_trasu(int $preprava_id): void {
  $preprava = radek("SELECT id, stav FROM prepravy WHERE id = ?", [$preprava_id]);
  if (!$preprava) return;
  $body = body_prepravy($preprava_id);

  $prvni = null; $posledni = null;
  foreach ($body as $b) {
    if ($b["druh"] === "nakladka" && $prvni === null) $prvni = $b;
    if ($b["druh"] === "vykladka") $posledni = $b;
  }
  /* Jízda bez nakládky nebo bez vykládky: vezme se první a poslední bod,
     ať souhrn není prázdný. */
  if ($prvni === null && $body) $prvni = $body[0];
  if ($posledni === null && $body) $posledni = $body[count($body) - 1];

  $vem = function (?array $b, string $klic) { return $b ? ($b[$klic] ?? null) : null; };

  uprav("prepravy", $preprava_id, [
    "nakladka_misto"  => $vem($prvni, "misto"),
    "nakladka_adresa" => $vem($prvni, "adresa"),
    "nakladka_datum"  => $vem($prvni, "datum"),
    "nakladka_od"     => $vem($prvni, "od"),
    "nakladka_do"     => $vem($prvni, "do"),
    "vykladka_misto"  => $vem($posledni, "misto"),
    "vykladka_adresa" => $vem($posledni, "adresa"),
    "vykladka_datum"  => $vem($posledni, "datum"),
    "vykladka_od"     => $vem($posledni, "od"),
    "vykladka_do"     => $vem($posledni, "do"),
    "stav"            => stav_z_bodu($body, (string)$preprava["stav"]),
    "upraveno"        => date("Y-m-d H:i:s"),
  ]);
}

/* Očísluje body 1…n podle současného pořadí. */
function preusporadej_body(int $preprava_id): void {
  $i = 1;
  foreach (radky("SELECT id FROM body WHERE preprava_id = ? ORDER BY poradi, id", [$preprava_id]) as $b) {
    dotaz("UPDATE body SET poradi = ? WHERE id = ?", [$i++, (int)$b["id"]]);
  }
}

/* Když je vybrané místo z adresáře a textová pole jsou prázdná, opíše se
   z něj obec, adresa i kontakt. Opis zůstává u bodu — objednávka drží to,
   co bylo dohodnuté, i když se karta místa později změní. */
function dopln_z_mista(array $data): array {
  $misto_id = (int)($data["misto_id"] ?? 0);
  if (!$misto_id) return $data;
  $m = radek("SELECT * FROM mista WHERE id = ?", [$misto_id]);
  if (!$m) { $data["misto_id"] = null; return $data; }

  if (trim((string)($data["misto"] ?? "")) === "") $data["misto"] = (string)$m["mesto"];
  if (trim((string)($data["adresa"] ?? "")) === "") {
    $casti = array_filter([(string)$m["nazev"], (string)$m["ulice"], trim((string)$m["psc"] . " " . (string)$m["mesto"])]);
    $data["adresa"] = implode(", ", $casti);
  }
  if (trim((string)($data["kontakt"] ?? "")) === "") {
    $data["kontakt"] = trim((string)$m["kontakt_jmeno"] . " " . (string)$m["kontakt_telefon"]);
  }
  if (trim((string)($data["poznamka"] ?? "")) === "" && trim((string)$m["oteviraci_doba"]) !== "") {
    $data["poznamka"] = "Otevřeno " . (string)$m["oteviraci_doba"];
  }
  return $data;
}

function pridej_bod(int $preprava_id, array $data): int {
  $data = dopln_z_mista($data);
  $dalsi = (int)hodnota("SELECT COALESCE(MAX(poradi), 0) + 1 FROM body WHERE preprava_id = ?", [$preprava_id]);
  $id = vloz("body", array_merge([
    "preprava_id" => $preprava_id,
    "poradi"      => $dalsi,
    "druh"        => "nakladka",
    "splneno"     => 0,
  ], $data));
  prepocitej_trasu($preprava_id);
  return $id;
}

function uprav_bod(int $bod_id, array $data): void {
  $bod = radek("SELECT * FROM body WHERE id = ?", [$bod_id]);
  if (!$bod) return;
  $data = dopln_z_mista($data);
  uprav("body", $bod_id, $data);
  prepocitej_trasu((int)$bod["preprava_id"]);
}

function smaz_bod(int $bod_id): void {
  $bod = radek("SELECT * FROM body WHERE id = ?", [$bod_id]);
  if (!$bod) return;
  dotaz("DELETE FROM body WHERE id = ?", [$bod_id]);
  preusporadej_body((int)$bod["preprava_id"]);
  prepocitej_trasu((int)$bod["preprava_id"]);
}

/* $smer -1 = výš, +1 = níž. Prohodí pořadí se sousedem. */
function posun_bod(int $bod_id, int $smer): void {
  $bod = radek("SELECT * FROM body WHERE id = ?", [$bod_id]);
  if (!$bod) return;
  preusporadej_body((int)$bod["preprava_id"]);
  $bod = radek("SELECT * FROM body WHERE id = ?", [$bod_id]);
  $soused = radek("SELECT * FROM body WHERE preprava_id = ? AND poradi = ?",
    [(int)$bod["preprava_id"], (int)$bod["poradi"] + ($smer < 0 ? -1 : 1)]);
  if (!$soused) return;
  dotaz("UPDATE body SET poradi = ? WHERE id = ?", [(int)$soused["poradi"], $bod_id]);
  dotaz("UPDATE body SET poradi = ? WHERE id = ?", [(int)$bod["poradi"], (int)$soused["id"]]);
  prepocitej_trasu((int)$bod["preprava_id"]);
}

/* Splnění bodu — z detailu i z odkazu pro řidiče. */
function splnit_bod(int $bod_id, bool $splneno): void {
  $bod = radek("SELECT * FROM body WHERE id = ?", [$bod_id]);
  if (!$bod) return;
  uprav("body", $bod_id, [
    "splneno"     => $splneno ? 1 : 0,
    "splneno_kdy" => $splneno ? date("Y-m-d H:i:s") : null,
  ]);
  prepocitej_trasu((int)$bod["preprava_id"]);
}

/* Ruční změna stavu na formuláři se promítne do bodů, aby si obě strany
   odpovídaly: naloženo splní nakládky, vyloženo všechno, návrat na nižší
   stav splnění odebere. */
function srovnej_body_se_stavem(int $preprava_id, string $stav): void {
  $ted = date("Y-m-d H:i:s");
  if ($stav === "vylozeno") {
    dotaz("UPDATE body SET splneno = 1, splneno_kdy = COALESCE(splneno_kdy, ?) WHERE preprava_id = ?", [$ted, $preprava_id]);
  } elseif ($stav === "nalozeno") {
    dotaz("UPDATE body SET splneno = 1, splneno_kdy = COALESCE(splneno_kdy, ?) WHERE preprava_id = ? AND druh = 'nakladka'", [$ted, $preprava_id]);
    dotaz("UPDATE body SET splneno = 0, splneno_kdy = NULL WHERE preprava_id = ? AND druh = 'vykladka'", [$preprava_id]);
  } elseif (in_array($stav, ["nova", "objednana"], true)) {
    dotaz("UPDATE body SET splneno = 0, splneno_kdy = NULL WHERE preprava_id = ?", [$preprava_id]);
  }
}

/* Dva body z polí nakládka/vykládka — pro nový formulář, import
   a jednorázový převod starých přeprav. */
function zaloz_body_z_poli(int $preprava_id, array $p): void {
  $bez_datumu = function ($h) { return $h === "" ? null : $h; };
  vloz("body", [
    "preprava_id" => $preprava_id, "poradi" => 1, "druh" => "nakladka",
    "misto"  => (string)($p["nakladka_misto"] ?? ""),
    "adresa" => (string)($p["nakladka_adresa"] ?? ""),
    "datum"  => $bez_datumu($p["nakladka_datum"] ?? null),
    "od"     => (string)($p["nakladka_od"] ?? ""),
    "do"     => (string)($p["nakladka_do"] ?? ""),
    "splneno" => in_array($p["stav"] ?? "", ["nalozeno", "vylozeno", "doklady", "fakturovano"], true) ? 1 : 0,
  ]);
  vloz("body", [
    "preprava_id" => $preprava_id, "poradi" => 2, "druh" => "vykladka",
    "misto"  => (string)($p["vykladka_misto"] ?? ""),
    "adresa" => (string)($p["vykladka_adresa"] ?? ""),
    "datum"  => $bez_datumu($p["vykladka_datum"] ?? null),
    "od"     => (string)($p["vykladka_od"] ?? ""),
    "do"     => (string)($p["vykladka_do"] ?? ""),
    "splneno" => in_array($p["stav"] ?? "", ["vylozeno", "doklady", "fakturovano"], true) ? 1 : 0,
  ]);
}

/* Jednorázový převod: přepravy bez bodů dostanou dva body z polí.
   Běží při každém načtení, ale po prvním průchodu už nic nenajde. */
function preved_prepravy_na_body(): void {
  if (nastaveni("prevod_body") === "hotovo") return;
  $bez = radky("SELECT p.* FROM prepravy p WHERE NOT EXISTS (SELECT 1 FROM body b WHERE b.preprava_id = p.id)");
  foreach ($bez as $p) {
    zaloz_body_z_poli((int)$p["id"], $p);
  }
  uloz_nastaveni("prevod_body", "hotovo");
}

/* Kopie bodů z jedné přepravy do druhé — pro kopii i pro stálé linky.
   $posun_dnu posune data bodů (generování linky na jiný den). */
function zkopiruj_body(int $z_prepravy, int $do_prepravy, ?int $posun_dnu = null, bool $nesplnene = true): void {
  foreach (body_prepravy($z_prepravy) as $b) {
    $datum = $b["datum"];
    if ($datum && $posun_dnu !== null) {
      $datum = date("Y-m-d", strtotime((string)$datum . " " . ($posun_dnu >= 0 ? "+" : "") . $posun_dnu . " days"));
    }
    vloz("body", [
      "preprava_id" => $do_prepravy, "poradi" => (int)$b["poradi"], "druh" => $b["druh"],
      "misto_id" => $b["misto_id"], "misto" => $b["misto"], "adresa" => $b["adresa"],
      "datum" => $datum, "od" => $b["od"], "do" => $b["do"],
      "kontakt" => $b["kontakt"], "poznamka" => $b["poznamka"],
      "zbozi" => $b["zbozi"], "hmotnost" => $b["hmotnost"], "palet" => $b["palet"],
      "splneno" => $nesplnene ? 0 : (int)$b["splneno"],
      "splneno_kdy" => $nesplnene ? null : $b["splneno_kdy"],
    ]);
  }
  prepocitej_trasu($do_prepravy);
}

/* --- Státní svátky ------------------------------------------------------ */

/* Velikonoční neděle podle gregoriánského kalendáře (Meeus–Jones–Butcher). */
function velikonocni_nedele(int $rok): string {
  $a = $rok % 19; $b = intdiv($rok, 100); $c = $rok % 100;
  $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25);
  $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
  $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
  $m = intdiv($a + 11 * $h + 22 * $l, 451);
  $mesic = intdiv($h + $l - 7 * $m + 114, 31);
  $den = (($h + $l - 7 * $m + 114) % 31) + 1;
  return sprintf("%04d-%02d-%02d", $rok, $mesic, $den);
}

/* Název svátku, nebo null. Počítá se v kódu, žádná služba zvenku. */
function statni_svatek(string $datum): ?string {
  $d = date_create($datum);
  if (!$d) return null;
  $rok = (int)$d->format("Y");
  $pevne = [
    "01-01" => "Nový rok", "05-01" => "Svátek práce", "05-08" => "Den vítězství",
    "07-05" => "Cyril a Metoděj", "07-06" => "Jan Hus", "09-28" => "Den české státnosti",
    "10-28" => "Vznik samostatného státu", "11-17" => "Den boje za svobodu",
    "12-24" => "Štědrý den", "12-25" => "1. svátek vánoční", "12-26" => "2. svátek vánoční",
  ];
  $md = $d->format("m-d");
  if (isset($pevne[$md])) return $pevne[$md];
  $nedele = date_create(velikonocni_nedele($rok));
  if ($d->format("Y-m-d") === (clone $nedele)->modify("-2 days")->format("Y-m-d")) return "Velký pátek";
  if ($d->format("Y-m-d") === (clone $nedele)->modify("+1 day")->format("Y-m-d")) return "Velikonoční pondělí";
  return null;
}

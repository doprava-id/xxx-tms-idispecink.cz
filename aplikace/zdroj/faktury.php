<?php
/* =========================================================================
   Faktury — vydané zákazníkům, přijaté od dopravců

   Faktura se na přepravy váže číslem (prepravy.faktura_vydana,
   prepravy.faktura_prijata), protože jedna faktura kryje víc přeprav.
   Vydané se dají tahat z Fakturoidu, přijaté se zapisují ručně.

   Pohledávka = vydaná faktura bez úhrady po splatnosti.
   Závazek    = přijatá faktura bez úhrady, řazená podle splatnosti.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DRUHY_FAKTUR = [
  "vydana"  => "Vydaná",
  "prijata" => "Přijatá",
];

/* Stav z Fakturoidu → česky. Ručně zapsané faktury stav nemají. */
const STAVY_FAKTUR = [
  "open"          => "Otevřená",
  "sent"          => "Odeslaná",
  "overdue"       => "Po splatnosti",
  "paid"          => "Zaplacená",
  "cancelled"     => "Stornovaná",
  "uncollectible" => "Nedobytná",
];

function faktura_podle_cisla(string $druh, string $cislo): ?array {
  $cislo = trim($cislo);
  if ($cislo === "") return null;
  return radek("SELECT * FROM faktury WHERE druh = ? AND cislo = ?", [$druh, $cislo]);
}

/* Založí nebo doplní fakturu podle čísla. Vrací id. */
function faktura_uloz(string $druh, string $cislo, array $data): int {
  $cislo = trim($cislo);
  $stara = faktura_podle_cisla($druh, $cislo);
  $data["upraveno"] = date("Y-m-d H:i:s");
  if ($stara) {
    uprav("faktury", (int)$stara["id"], $data);
    return (int)$stara["id"];
  }
  return vloz("faktury", array_merge([
    "druh" => $druh, "cislo" => $cislo, "vytvoreno" => date("Y-m-d H:i:s"),
  ], $data));
}

/* Přepravy, které faktura kryje. */
function prepravy_faktury(array $f): array {
  $sloupec = $f["druh"] === "prijata" ? "faktura_prijata" : "faktura_vydana";
  return radky("SELECT p.* FROM prepravy p WHERE p.sablona = 0 AND p." . $sloupec . " = ? ORDER BY p.nakladka_datum, p.id", [(string)$f["cislo"]]);
}

/* Součet cen přeprav, které faktura kryje — kontrola proti částce na faktuře. */
function soucet_prepravy_faktury(array $f): float {
  $sloupec = $f["druh"] === "prijata" ? "faktura_prijata" : "faktura_vydana";
  $cena    = $f["druh"] === "prijata" ? "cena_dopravce" : "cena_zakaznik";
  return (float)hodnota("SELECT COALESCE(SUM(" . $cena . "), 0) FROM prepravy WHERE sablona = 0 AND " . $sloupec . " = ?", [(string)$f["cislo"]]);
}

/* Pohledávky: vydané, nezaplacené, po splatnosti (nebo všechny nezaplacené). */
/* Dny mezi dneškem a splatností — v PHP, aby to sedělo na SQLite i MySQL. */
function dnu_od_splatnosti(?string $splatnost): ?int {
  if (!$splatnost) return null;
  return (int)round((strtotime(date("Y-m-d")) - strtotime($splatnost)) / 86400);
}

function pohledavky(bool $jen_po_splatnosti = true): array {
  $dnes = date("Y-m-d");
  $seznam = radky(
    "SELECT f.*, z.nazev AS firma_nazev
       FROM faktury f LEFT JOIN firmy z ON z.id = f.firma_id
      WHERE f.druh = 'vydana' AND f.uhrazeno IS NULL
        AND COALESCE(f.stav, '') NOT IN ('cancelled', 'uncollectible')"
      . ($jen_po_splatnosti ? " AND f.splatnost IS NOT NULL AND f.splatnost < ?" : "") . "
      ORDER BY COALESCE(f.splatnost, '9999-12-31'), f.id",
    $jen_po_splatnosti ? [$dnes] : []);
  foreach ($seznam as &$f) $f["dnu_po"] = dnu_od_splatnosti($f["splatnost"]);
  return $seznam;
}

/* Závazky: přijaté, nezaplacené, podle splatnosti. */
function zavazky(): array {
  $seznam = radky(
    "SELECT f.*, d.nazev AS firma_nazev
       FROM faktury f LEFT JOIN firmy d ON d.id = f.firma_id
      WHERE f.druh = 'prijata' AND f.uhrazeno IS NULL
      ORDER BY COALESCE(f.splatnost, '9999-12-31'), f.id");
  foreach ($seznam as &$f) { $d = dnu_od_splatnosti($f["splatnost"]); $f["dnu_do"] = $d === null ? null : -$d; }
  return $seznam;
}

/* Čísla faktur zapsaná u přeprav, ke kterým ještě není záznam faktury —
   kandidáti na synchronizaci nebo ruční doplnění. */
function cisla_bez_zaznamu(string $druh): array {
  $sloupec = $druh === "prijata" ? "faktura_prijata" : "faktura_vydana";
  return radky(
    "SELECT p." . $sloupec . " AS cislo, COUNT(*) AS prepravy, MIN(p." . ($druh === "prijata" ? "dopravce_id" : "zakaznik_id") . ") AS firma_id,
            SUM(p." . ($druh === "prijata" ? "cena_dopravce" : "cena_zakaznik") . ") AS soucet
       FROM prepravy p
      WHERE p.sablona = 0 AND p." . $sloupec . " IS NOT NULL AND TRIM(p." . $sloupec . ") <> ''
        AND NOT EXISTS (SELECT 1 FROM faktury f WHERE f.druh = ? AND f.cislo = p." . $sloupec . ")
      GROUP BY p." . $sloupec . " ORDER BY p." . $sloupec, [$druh]);
}

<?php
/* Export do CSV. Oddělovač je středník a soubor začíná značkou BOM —
   český Excel takový soubor otevře rovnou ve sloupcích, zatímco u čárky
   a bez BOM by z něj udělal jeden sloupec s rozsypanou diakritikou. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();
$co = vstup("co", "prepravy");

function posli_csv(string $nazev, array $hlavicka, array $radky): void {
  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"" . $nazev . "\"");
  header("Cache-Control: no-store");
  $ven = fopen("php://output", "w");
  fwrite($ven, "\xEF\xBB\xBF");
  /* Prázdný únikový znak: Excel zpětné lomítko jako únik nezná a obsah
     s ním by se rozpadl. */
  fputcsv($ven, $hlavicka, ";", '"', "");
  foreach ($radky as $r) fputcsv($ven, $r, ";", '"', "");
  fclose($ven);
  exit;
}

function csv_datum($h): string { return $h ? date("d.m.Y", strtotime((string)$h)) : ""; }
function csv_castka($h): string { return $h === null || $h === "" ? "" : number_format((float)$h, 2, ",", ""); }

/* --- Firmy -------------------------------------------------------------- */

if ($co === "firmy") {
  $data = radky("SELECT * FROM firmy ORDER BY LOWER(nazev)");
  $vystup = [];
  foreach ($data as $f) {
    $vystup[] = [
      $f["nazev"], TYPY_FIREM[$f["typ"]] ?? $f["typ"], $f["ico"], $f["dic"],
      $f["ulice"], $f["psc"], $f["mesto"], $f["stat"],
      $f["kontakt_jmeno"], $f["kontakt_telefon"], $f["kontakt_email"],
      $f["splatnost"], csv_datum($f["prov_datum"]),
      (int)$f["prov_registry"] + (int)$f["prov_opravneni"] + (int)$f["prov_pojisteni"]
        + (int)$f["prov_doklady"] + (int)$f["prov_reference"],
      (int)$f["aktivni"] === 1 ? "ano" : "ne",
      $f["poznamka"],
    ];
  }
  posli_csv("firmy-" . date("Y-m-d") . ".csv", [
    "Název", "Typ", "IČO", "DIČ", "Ulice", "PSČ", "Město", "Stát",
    "Kontaktní osoba", "Telefon", "E-mail", "Splatnost dnů",
    "Prověřeno dne", "Prověření z 5", "Aktivní", "Poznámka",
  ], $vystup);
}

/* --- Řádky faktury pro zákazníka za období ------------------------------ */

if ($co === "radky_faktury") {
  vyzaduj_ceny();
  $od = vstup_datum("od") ?: date("Y-m-01");
  $do = vstup_datum("do") ?: date("Y-m-t");
  $firma = radek("SELECT * FROM firmy WHERE id = ?", [vstup_cislo("firma")]);
  if (!$firma) { vzkaz("chyba", "Zákazník nenalezen."); presmeruj(odkaz("fakturace")); }
  $polozky = radky(
    "SELECT p.* FROM prepravy p
      WHERE COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum) BETWEEN ? AND ?
        AND p.stav <> 'zruseno' AND p.sablona = 0 AND p.zakaznik_id = ?
      ORDER BY COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum), p.id", [$od, $do, (int)$firma["id"]]);
  $vystup = [];
  foreach ($polozky as $p) {
    $vystup[] = [
      $firma["nazev"], $firma["ico"], $firma["dic"],
      "Přeprava " . $p["cislo"] . " " . ($p["nakladka_misto"] ?: "?") . " – " . ($p["vykladka_misto"] ?: "?"),
      csv_datum($p["nakladka_datum"]), csv_datum($p["vykladka_datum"]), $p["ref_zakaznika"],
      1, "ks", csv_castka($p["cena_zakaznik"]), $p["faktura_vydana"],
    ];
  }
  posli_csv("faktura-radky-" . preg_replace('/[^A-Za-z0-9]+/', "-", (string)@iconv("UTF-8", "ASCII//TRANSLIT", (string)$firma["nazev"])) . "-" . $od . "-" . $do . ".csv", [
    "Odběratel", "IČO", "DIČ", "Položka", "Nakládka", "Vykládka", "Reference", "Množství", "Jednotka", "Cena bez DPH", "Faktura",
  ], $vystup);
}

/* --- Řádky faktury za externí dispečink --------------------------------- */

if ($co === "dispecink_radky") {
  vyzaduj_ceny();
  $od = vstup_datum("od") ?: date("Y-m-01");
  $do = vstup_datum("do") ?: date("Y-m-t");
  $klient = radek("SELECT * FROM firmy WHERE id = ?", [vstup_cislo("firma")]);
  if (!$klient) { vzkaz("chyba", "Klient nenalezen."); presmeruj(odkaz("fakturace", ["pohled" => "dispecink"])); }
  $pk = dispecink_podklad($klient, $od, $do);
  $vystup = [];
  foreach ($pk["radky"] as [$nazev, $mnozstvi, $jednotka, $cena]) {
    $vystup[] = [
      $klient["nazev"], $klient["ico"], $klient["dic"], $nazev, $mnozstvi, $jednotka,
      csv_castka($cena), csv_castka((float)$cena * (float)$mnozstvi),
    ];
  }
  posli_csv("dispecink-radky-" . preg_replace('/[^A-Za-z0-9]+/', "-", (string)@iconv("UTF-8", "ASCII//TRANSLIT", (string)$klient["nazev"])) . "-" . $od . "-" . $do . ".csv", [
    "Odběratel", "IČO", "DIČ", "Položka", "Množství", "Jednotka", "Cena za jednotku bez DPH", "Celkem bez DPH",
  ], $vystup);
}

/* --- Přepravy ----------------------------------------------------------- */

$kde = []; $parametry = [];

if ($co === "fakturace") {
  $od = vstup_datum("od") ?: date("Y-m-01");
  $do = vstup_datum("do") ?: date("Y-m-t");
  $kde[] = "COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum) BETWEEN ? AND ?";
  $kde[] = "p.stav <> 'zruseno'";
  array_push($parametry, $od, $do);
  $soubor = "fakturace-" . $od . "-" . $do . ".csv";

} elseif ($co === "zaloha") {
  $soubor = "prepravy-vse-" . date("Y-m-d") . ".csv";

} else {
  /* Stejné filtry jako v seznamu přeprav — export vrací to, co je vidět. */
  $hledat = vstup("hledat"); $stav = vstup("stav");
  $dopravce = vstup_cislo("dopravce"); $zakaznik = vstup_cislo("zakaznik");
  $od = vstup_datum("od"); $do = vstup_datum("do"); $jen = vstup("jen");

  if ($hledat !== "") {
    $kde[] = "(p.cislo LIKE ? OR p.nakladka_misto LIKE ? OR p.vykladka_misto LIKE ?"
           . " OR p.zbozi LIKE ? OR p.ref_zakaznika LIKE ? OR p.spz LIKE ?)";
    for ($i = 0; $i < 6; $i++) $parametry[] = "%" . $hledat . "%";
  }
  if (isset(STAVY[$stav])) { $kde[] = "p.stav = ?"; $parametry[] = $stav; }
  if ($dopravce) { $kde[] = "p.dopravce_id = ?"; $parametry[] = $dopravce; }
  if ($zakaznik) { $kde[] = "p.zakaznik_id = ?"; $parametry[] = $zakaznik; }
  if ($od) { $kde[] = "p.nakladka_datum >= ?"; $parametry[] = $od; }
  if ($do) { $kde[] = "p.nakladka_datum <= ?"; $parametry[] = $do; }
  if ($jen === "bez_dopravce")       $kde[] = "(p.dopravce_id IS NULL OR p.dopravce_id = 0) AND p.stav <> 'zruseno'";
  elseif ($jen === "doklady")        $kde[] = "p.doklady <> 'prijato' AND p.stav IN ('vylozeno','doklady','fakturovano')";
  elseif ($jen === "nefakturovano")  $kde[] = "(p.faktura_vydana IS NULL OR p.faktura_vydana = '') AND p.stav <> 'zruseno' AND " . JEN_SPEDICE;
  elseif ($jen === "dispecink")      $kde[] = JEN_DISPECINK;
  elseif ($jen === "spedice")        $kde[] = JEN_SPEDICE;

  $soubor = "prepravy-" . date("Y-m-d") . ".csv";
}

$kde[] = "p.sablona = 0";
$podminka = " WHERE " . implode(" AND ", $kde);

$data = radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev, k.nazev AS klient_nazev,
          (SELECT COUNT(*) FROM body b WHERE b.preprava_id = p.id) AS bodu
     FROM prepravy p
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy k ON k.id = p.dispecink_klient_id" . $podminka . "
    ORDER BY COALESCE(p.nakladka_datum, '9999-12-31'), p.id", $parametry);

$hlavicka = [
  "Číslo", "Stav", "Zákazník", "Reference zákazníka", "Bodů trasy",
  "Nakládka místo", "Nakládka adresa", "Nakládka datum", "Nakládka od", "Nakládka do",
  "Vykládka místo", "Vykládka adresa", "Vykládka datum", "Vykládka od", "Vykládka do",
  "Zboží", "Hmotnost kg", "Palet", "LDM", "Vozidlo", "Požadavky",
  "Dopravce", "SPZ", "Řidič", "Telefon řidiče", "Externí dispečink",
];
/* Pořadí musí sedět s pořadím hodnot v řádku níže. */
if ($ceny) $hlavicka[] = "Cena zákazníka";
$hlavicka[] = "Cena dopravce";
$hlavicka[] = "Přijatá faktura";
$hlavicka[] = "Doklady";
$hlavicka[] = "Poznámka k dokladům";
if ($ceny) { $hlavicka[] = "Vydaná faktura"; $hlavicka[] = "Marže"; }

$vystup = [];
foreach ($data as $p) {
  $radek = [
    $p["cislo"], nazev_stavu($p["stav"]), $p["zakaznik_nazev"], $p["ref_zakaznika"], (int)$p["bodu"],
    $p["nakladka_misto"], $p["nakladka_adresa"], csv_datum($p["nakladka_datum"]), $p["nakladka_od"], $p["nakladka_do"],
    $p["vykladka_misto"], $p["vykladka_adresa"], csv_datum($p["vykladka_datum"]), $p["vykladka_od"], $p["vykladka_do"],
    $p["zbozi"], $p["hmotnost"], $p["palet"], $p["ldm"], nazev_typu_vozidla($p["typ_vozidla"]), $p["pozadavky"],
    $p["dopravce_nazev"], $p["spz"], $p["ridic_jmeno"], $p["ridic_telefon"], $p["klient_nazev"],
  ];
  if ($ceny) $radek[] = csv_castka($p["cena_zakaznik"]);
  $radek[] = csv_castka($p["cena_dopravce"]);
  $radek[] = $p["faktura_prijata"];
  $radek[] = DOKLADY[$p["doklady"]] ?? "";
  $radek[] = $p["doklady_poznamka"];
  if ($ceny) {
    $radek[] = $p["faktura_vydana"];
    $radek[] = ($p["cena_zakaznik"] === null && $p["cena_dopravce"] === null)
      ? "" : csv_castka((float)$p["cena_zakaznik"] - (float)$p["cena_dopravce"]);
  }
  $vystup[] = $radek;
}

posli_csv($soubor, $hlavicka, $vystup);

<?php
/* =========================================================================
   Ceníky zákazníků a návrh ceny

   Tři podoby ceníku: pevná cena za trasu, pásmo podle vzdálenosti a sazba
   za kilometr. U zákazníka, se kterým se domlouvá po jedné, není žádná.
   Když platí víc pravidel, vyhrává nejkonkrétnější: pevná cena → pásmo →
   sazba za km → cena z historie trasy. Návrh vždycky řekne, podle čeho
   vznikl — nikdy se nezapisuje sám, dispečer ho jen převezme.

   Kilometry se zatím zadávají ručně: mapová služba je otevřená otázka.
   Bez nich pásma a sazba za kilometr mlčí a přijde na řadu historie.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DRUHY_CENIKU = [
  "trasa" => "Pevná cena za trasu",
  "pasmo" => "Pásmo podle vzdálenosti",
  "km"    => "Sazba za kilometr",
];

/* Pravidla zákazníka v pořadí, v jakém se zkoušejí: druh podle přednosti,
   uvnitř druhu napřed pravidla s konkrétním vozidlem. */
function cenik_zakaznika(int $firma_id): array {
  return radky(
    "SELECT * FROM ceniky WHERE firma_id = ? AND aktivni = 1
      ORDER BY CASE druh WHEN 'trasa' THEN 1 WHEN 'pasmo' THEN 2 ELSE 3 END,
               CASE WHEN COALESCE(typ_vozidla, '') = '' THEN 1 ELSE 0 END, km_od, id", [$firma_id]);
}

/* Popis pravidla do seznamu na kartě firmy. */
function popis_pravidla(array $c): string {
  if ($c["druh"] === "trasa") return ($c["nakladka_misto"] ?: "?") . " → " . ($c["vykladka_misto"] ?: "?") . ": " . castka($c["cena"]);
  if ($c["druh"] === "pasmo") return (int)$c["km_od"] . "–" . ($c["km_do"] !== null ? (int)$c["km_do"] : "∞") . " km: " . castka($c["cena"]);
  return cislo($c["cena"], 2) . " Kč/km";
}

/* Návrh ceny zákazníka. Vrací [cena, podle, popis] nebo null, když není
   podle čeho. $mimo_preprava_id vynechá z historie právě otevřenou jízdu. */
function navrh_ceny(?int $zakaznik_id, string $nakladka, string $vykladka, ?int $km, string $typ_vozidla, ?int $mimo_preprava_id = null): ?array {
  $n = mb_strtolower(trim($nakladka));
  $v = mb_strtolower(trim($vykladka));

  if ($zakaznik_id) {
    foreach (cenik_zakaznika($zakaznik_id) as $c) {
      $vuz = (string)$c["typ_vozidla"];
      if ($vuz !== "" && $typ_vozidla !== "" && $vuz !== $typ_vozidla) continue;
      $vuz_popis = $vuz !== "" ? ", " . nazev_typu_vozidla($vuz) : "";
      if ($c["druh"] === "trasa") {
        if ($n === "" || $v === "") continue;
        if (mb_strtolower(trim((string)$c["nakladka_misto"])) !== $n || mb_strtolower(trim((string)$c["vykladka_misto"])) !== $v) continue;
        return ["cena" => (float)$c["cena"], "podle" => "cenik_trasa",
                "popis" => "pevná cena z ceníku " . $c["nakladka_misto"] . " → " . $c["vykladka_misto"] . $vuz_popis];
      }
      if ($km === null || $km <= 0) continue;
      if ($c["druh"] === "pasmo") {
        if ($km < (int)$c["km_od"] || ($c["km_do"] !== null && $km > (int)$c["km_do"])) continue;
        return ["cena" => (float)$c["cena"], "podle" => "cenik_pasmo",
                "popis" => "pásmo " . (int)$c["km_od"] . "–" . ($c["km_do"] !== null ? (int)$c["km_do"] : "∞") . " km z ceníku" . $vuz_popis];
      }
      return ["cena" => round((float)$c["cena"] * $km, 2), "podle" => "cenik_km",
              "popis" => cislo($c["cena"], 2) . " Kč/km × " . $km . " km z ceníku" . $vuz_popis];
    }
  }

  /* Historie: naposledy na stejné trase, napřed u téhož zákazníka. Jízdy
     pod externím dispečinkem cenu zákazníka nemají, proto jen spedice. */
  if ($n !== "" && $v !== "") {
    foreach ($zakaznik_id ? [$zakaznik_id, null] : [null] as $jen_zakaznik) {
      $parametry = [trim($nakladka), trim($vykladka)];
      $sql = "SELECT p.cislo, p.cena_zakaznik, p.nakladka_datum, z.nazev AS zakaznik_nazev
                FROM prepravy p LEFT JOIN firmy z ON z.id = p.zakaznik_id
               WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND p.cena_zakaznik IS NOT NULL AND " . JEN_SPEDICE . "
                 AND LOWER(p.nakladka_misto) = LOWER(?) AND LOWER(p.vykladka_misto) = LOWER(?)";
      if ($jen_zakaznik) { $sql .= " AND p.zakaznik_id = ?"; $parametry[] = $jen_zakaznik; }
      if ($mimo_preprava_id) { $sql .= " AND p.id <> ?"; $parametry[] = $mimo_preprava_id; }
      $h = radek($sql . " ORDER BY COALESCE(p.nakladka_datum, '') DESC, p.id DESC LIMIT 1", $parametry);
      if ($h) {
        return ["cena" => (float)$h["cena_zakaznik"], "podle" => "historie",
                "popis" => "naposledy na této trase " . datum($h["nakladka_datum"]) . ", přeprava " . $h["cislo"]
                         . ($jen_zakaznik ? "" : " pro " . ($h["zakaznik_nazev"] ?: "jiného zákazníka"))];
      }
    }
  }
  return null;
}

/* Řádek s návrhem pod polem ceny: text a tlačítko, které cenu doplní.
   Bez JavaScriptu zůstane jen text — cena se opíše ručně. */
function navrh_ceny_html(?array $navrh, string $pole): string {
  if (!$navrh) return '<p class="app-perex navrh-ceny">Návrh ceny: není podle čeho — zákazník nemá ceník na tuhle trasu a trasa se ještě nevozila.</p>';
  return '<p class="app-perex navrh-ceny">Návrh ceny: <b class="cislo">' . chran(castka($navrh["cena"])) . '</b> — ' . chran($navrh["popis"])
    . ' <button type="button" class="odkaz-tlacitko" data-doplnit="' . chran($pole) . '" data-hodnota="' . chran((string)round((float)$navrh["cena"], 2)) . '" data-podle="' . chran($navrh["popis"]) . '">použít</button></p>';
}

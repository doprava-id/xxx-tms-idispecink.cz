<?php
/* =========================================================================
   Externí dispečink — vozy klientů, které řídíme jako službu

   Jízda pod externím dispečinkem je obyčejná přeprava: zákazníkem je ten,
   kdo zboží posílá, dopravcem klient dispečinku. Od spedice ji odlišuje
   jen dispecink_klient_id — a to, co se s ní děje v penězích: odesílateli
   fakturuje klient sám, my mu účtujeme odměnu za dispečink podle způsobu
   a sazby na jeho kartě. Do tržby, nákladů a marže spedice se tyhle jízdy
   nepočítají; sčítají se zvlášť jako obrat vozů klienta.

   Každý dotaz, který sčítá tržbu, náklady nebo marži spedice nebo staví
   podklad k fakturaci, musí mít podmínku JEN_SPEDICE. Bez ní se obrat
   cizích vozů přičte k našemu.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Podmínky do WHERE — přepravy mají v dotazu alias p. */
const JEN_SPEDICE   = "COALESCE(p.dispecink_klient_id, 0) = 0";
const JEN_DISPECINK = "COALESCE(p.dispecink_klient_id, 0) <> 0";

function klienti_dispecinku(bool $jen_aktivni = true): array {
  return radky("SELECT * FROM firmy WHERE dispecink = 1" . ($jen_aktivni ? " AND aktivni = 1" : "") . " ORDER BY LOWER(nazev)");
}

function je_klient_dispecinku(?int $firma_id): bool {
  if (!$firma_id) return false;
  return (int)hodnota("SELECT dispecink FROM firmy WHERE id = ?", [$firma_id]) === 1;
}

/* Aktivní vozy klienta. */
function vozy_klienta(int $firma_id): array {
  return radky("SELECT * FROM vozidla WHERE firma_id = ? AND aktivni = 1 ORDER BY spz", [$firma_id]);
}

/* „1. 9. 2026 – 30. 9. 2026" do řádku faktury a poznámky. */
function popis_obdobi(string $od, string $do): string {
  return datum($od) . " – " . datum($do);
}

/* Skloňování počtu: 1 jízda, 2–4 jízdy, 5 jízd. */
function sklonuj(int $pocet, string $jedna, string $dve, string $pet): string {
  if ($pocet === 1) return $jedna;
  if ($pocet >= 2 && $pocet <= 4) return $dve;
  return $pet;
}

/* Podklad k fakturaci služby za období. Období se počítá jako ve
   fakturaci: podle data vykládky, bez ní podle nakládky. Odměna se počítá
   jen z jízd, které ještě nemají číslo vydané faktury — vyúčtované se
   ukážou, ale podruhé se neúčtují. Sazby nedomýšlí: bez způsobu nebo
   sazby na kartě klienta odměnu nespočítá a řekne to. */
function dispecink_podklad(array $klient, string $od, string $do): array {
  $jizdy = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev, v.spz AS vuz_spz
       FROM prepravy p
       LEFT JOIN firmy z ON z.id = p.zakaznik_id
       LEFT JOIN vozidla v ON v.id = p.vozidlo_id
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND p.dispecink_klient_id = ?
        AND COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum) BETWEEN ? AND ?
      ORDER BY COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum), p.id",
    [(int)$klient["id"], $od, $do]);
  $vozy = vozy_klienta((int)$klient["id"]);

  $obrat = 0.0; $obrat_otevrene = 0.0; $otevrene = 0; $vyuctovane = 0;
  $bez_ceny = 0; $bez_vozu = 0; $uzite = [];
  foreach ($jizdy as $j) {
    $vyuctovana = trim((string)$j["faktura_vydana"]) !== "";
    if ($vyuctovana) $vyuctovane++; else $otevrene++;
    if ($j["cena_dopravce"] === null) {
      $bez_ceny++;
    } else {
      $obrat += (float)$j["cena_dopravce"];
      if (!$vyuctovana) $obrat_otevrene += (float)$j["cena_dopravce"];
    }
    if (empty($j["vozidlo_id"])) $bez_vozu++; else $uzite[(int)$j["vozidlo_id"]] = true;
  }

  $zpusob = (string)($klient["dispecink_uctovani"] ?? "");
  $sazba  = ($klient["dispecink_sazba"] === null || $klient["dispecink_sazba"] === "") ? null : (float)$klient["dispecink_sazba"];
  $obdobi = popis_obdobi($od, $do);
  $odmena = null; $vypocet = ""; $radky = []; $upozorneni = [];

  if (!isset(DISPECINK_UCTOVANI[$zpusob])) {
    $upozorneni[] = "Způsob účtování není na kartě klienta zadaný — odměnu nelze spočítat.";
  } elseif ($sazba === null) {
    $upozorneni[] = "Sazba není na kartě klienta zadaná — odměnu nelze spočítat.";
  } elseif ($zpusob === "pausal_vuz") {
    $odmena  = round($sazba * count($vozy), 2);
    $vypocet = count($vozy) . " " . sklonuj(count($vozy), "vůz", "vozy", "vozů") . " × " . castka($sazba) . " měsíčně; paušál na délce období nezávisí";
    if ($vozy) $radky[] = ["Externí dispečink " . $obdobi . " — paušál za vůz", count($vozy), "vůz", $sazba];
  } elseif ($zpusob === "procento") {
    $odmena  = round($obrat_otevrene * $sazba / 100, 2);
    $vypocet = cislo($sazba, 1) . " % z obratu " . castka($obrat_otevrene) . " (" . $otevrene . " " . sklonuj($otevrene, "jízda", "jízdy", "jízd") . ")";
    if ($otevrene) $radky[] = ["Externí dispečink " . $obdobi . " — " . cislo($sazba, 1) . " % z obratu jízd (" . $otevrene . " jízd, " . castka($obrat_otevrene) . " bez DPH)", 1, "ks", $odmena];
  } else {
    $odmena  = round($sazba * $otevrene, 2);
    $vypocet = $otevrene . " " . sklonuj($otevrene, "jízda", "jízdy", "jízd") . " × " . castka($sazba);
    if ($otevrene) $radky[] = ["Externí dispečink " . $obdobi . " — jízdy vozů", $otevrene, "jízda", $sazba];
  }
  if ($bez_ceny) $upozorneni[] = $bez_ceny . " " . sklonuj($bez_ceny, "jízda je", "jízdy jsou", "jízd je") . " bez ceny — obrat vozů je neúplný.";
  if ($bez_vozu) $upozorneni[] = $bez_vozu . " " . sklonuj($bez_vozu, "jízda nemá", "jízdy nemají", "jízd nemá") . " přiřazený vůz.";

  return [
    "jizdy" => $jizdy, "vozy" => $vozy, "pocet" => count($jizdy),
    "otevrene" => $otevrene, "vyuctovane" => $vyuctovane,
    "obrat" => $obrat, "obrat_otevrene" => $obrat_otevrene, "vozu_v_provozu" => count($uzite),
    "bez_ceny" => $bez_ceny, "bez_vozu" => $bez_vozu,
    "zpusob" => $zpusob, "sazba" => $sazba, "odmena" => $odmena, "vypocet" => $vypocet,
    "radky" => $radky, "upozorneni" => $upozorneni, "obdobi" => $obdobi,
  ];
}

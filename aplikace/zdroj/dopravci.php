<?php
/* =========================================================================
   Doklady dopravce — pojištění odpovědnosti, oprávnění, smlouva

   Každý má platnost do. Systém na blížící se konec upozorní na kartě
   firmy, v seznamu, u přepravy i na objednávce — ale objednávku pustí.
   Rozhodnutí zůstává na dispečerovi, tak to zadavatel chtěl.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DOKLADY_DOPRAVCE = [
  "pojisteni_do" => "Pojištění odpovědnosti",
  "opravneni_do" => "Oprávnění k dopravě",
  "smlouva_do"   => "Smlouva",
];
const DOKLADY_UPOZORNIT_DNU = 30;

/* Upozornění k dokladům firmy: [vazne => bool, text]. Prázdné = v pořádku
   nebo bez data — chybějící datum se nevyčítá, ne každý dopravce má smlouvu. */
function upozorneni_dopravce(array $firma): array {
  $ven = [];
  foreach (DOKLADY_DOPRAVCE as $klic => $popis) {
    $do = (string)($firma[$klic] ?? "");
    if ($do === "") continue;
    $dnu = (int)round((strtotime($do) - strtotime(date("Y-m-d"))) / 86400);
    if ($dnu < 0) {
      $ven[] = ["vazne" => true, "text" => $popis . ": platnost skončila " . datum($do) . " (před " . (-$dnu) . " " . sklonuj(-$dnu, "dnem", "dny", "dny") . ")"];
    } elseif ($dnu <= DOKLADY_UPOZORNIT_DNU) {
      $ven[] = ["vazne" => false, "text" => $popis . ": platnost končí " . datum($do) . ($dnu === 0 ? " (dnes)" : " (za " . $dnu . " " . sklonuj($dnu, "den", "dny", "dní") . ")")];
    }
  }
  return $ven;
}

/* Aktivní dopravci, kterým nějaký doklad propadl nebo do měsíce propadne. */
function dopravci_s_upozornenim(): array {
  $hranice = date("Y-m-d", strtotime("+" . DOKLADY_UPOZORNIT_DNU . " days"));
  $seznam = radky(
    "SELECT * FROM firmy
      WHERE aktivni = 1 AND typ IN ('dopravce', 'oboji')
        AND ((pojisteni_do IS NOT NULL AND pojisteni_do <> '' AND pojisteni_do <= ?)
          OR (opravneni_do IS NOT NULL AND opravneni_do <> '' AND opravneni_do <= ?)
          OR (smlouva_do IS NOT NULL AND smlouva_do <> '' AND smlouva_do <= ?))
      ORDER BY LOWER(nazev)", [$hranice, $hranice, $hranice]);
  foreach ($seznam as &$f) $f["upozorneni"] = upozorneni_dopravce($f);
  return $seznam;
}

/* Vzkazy k dokladům dopravce — propadlé červeně, končící žlutě. */
function upozorneni_dopravce_html(?array $firma, string $dovetek = ""): string {
  if (!$firma) return "";
  $ven = "";
  foreach (upozorneni_dopravce($firma) as $u) {
    $ven .= '<p class="vzkaz ' . ($u["vazne"] ? "vzkaz-chyba" : "vzkaz-pozor") . '">' . chran($u["text"]) . ($dovetek !== "" ? " " . chran($dovetek) : "") . '</p>';
  }
  return $ven;
}

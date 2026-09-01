<?php
/* =========================================================================
   ARES — načtení firmy z veřejného rejstříku podle IČO

   Dotaz jde na veřejné rozhraní Ministerstva financí. Je to jediné místo,
   odkud aplikace volá ven, a proto se chová opatrně: krátký časový limit,
   žádná výjimka ven, a když se spojení nepovede, řekne to a údaje se
   vyplní ručně. Hosting nemusí odchozí provoz vůbec povolit — aplikace
   na tom nesmí stát.

   Vrací pole s klíči nazev, ico, dic, ulice, mesto, psc, nebo null.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const ARES_ADRESA = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/";
const ARES_LIMIT  = 6;   /* vteřin */

/* IČO má osm číslic; kratší se doplní nulami zleva, jak je v rejstříku zvykem. */
function ares_uprav_ico(string $ico): string {
  $ico = preg_replace('/\D+/', "", $ico) ?? "";
  if ($ico === "" || strlen($ico) > 8) return "";
  return str_pad($ico, 8, "0", STR_PAD_LEFT);
}

/* Kontrolní číslice IČO — chybu v přepisu pozná dřív, než se někam volá. */
function ares_ico_sedi(string $ico): bool {
  if (!preg_match('/^\d{8}$/', $ico)) return false;
  $soucet = 0;
  for ($i = 0; $i < 7; $i++) $soucet += (int)$ico[$i] * (8 - $i);
  $zbytek = $soucet % 11;
  $kontrolni = $zbytek === 0 ? 1 : ($zbytek === 1 ? 0 : 11 - $zbytek);
  return $kontrolni === (int)$ico[7];
}

/* Stáhne tělo odpovědi, nebo null. Kód 404 znamená „subjekt není",
   cokoliv jiného „nedovoláno se" — volající to rozliší podle $stav. */
function ares_stahni(string $adresa, ?int &$stav = null): ?string {
  $stav = null;

  if (function_exists("curl_init")) {
    $spojeni = curl_init($adresa);
    curl_setopt_array($spojeni, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => ARES_LIMIT,
      CURLOPT_CONNECTTIMEOUT => ARES_LIMIT,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_USERAGENT      => "iDispecink provozni system",
      CURLOPT_HTTPHEADER     => ["Accept: application/json"],
    ]);
    $telo = curl_exec($spojeni);
    $stav = (int)curl_getinfo($spojeni, CURLINFO_RESPONSE_CODE) ?: null;
    curl_close($spojeni);
    return is_string($telo) && $telo !== "" ? $telo : null;
  }

  if (!ini_get("allow_url_fopen")) return null;

  $kontext = stream_context_create(["http" => [
    "method"        => "GET",
    "timeout"       => ARES_LIMIT,
    "ignore_errors" => true,
    "header"        => "Accept: application/json\r\nUser-Agent: iDispecink provozni system\r\n",
  ]]);
  $telo = @file_get_contents($adresa, false, $kontext);
  foreach ($http_response_header ?? [] as $radek) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $radek, $shoda)) $stav = (int)$shoda[1];
  }
  return is_string($telo) && $telo !== "" ? $telo : null;
}

/* Adresa v ARESu přichází po částech i jako jeden řádek. Skládá se
   z částí, protože textová adresa nese i obec a PSČ dohromady. */
function ares_ulice(array $sidlo): string {
  $ulice = trim((string)($sidlo["nazevUlice"] ?? $sidlo["nazevCastiObce"] ?? ""));
  $cislo = trim((string)($sidlo["cisloDomovni"] ?? ""));
  $orient = trim((string)($sidlo["cisloOrientacni"] ?? ""));
  if ($orient !== "" && ($sidlo["cisloOrientacniPismeno"] ?? "") !== "") {
    $orient .= (string)$sidlo["cisloOrientacniPismeno"];
  }
  if ($cislo !== "" && $orient !== "") $cislo .= "/" . $orient;
  return trim($ulice . " " . $cislo);
}

/* Hlavní vstup. $chyba nese větu pro uživatele, když se nepovede. */
function ares_najdi(string $ico, ?string &$chyba = null): ?array {
  $chyba = null;

  $ico = ares_uprav_ico($ico);
  if ($ico === "") { $chyba = "IČO musí být číslo o nejvýše osmi číslicích."; return null; }
  if (!ares_ico_sedi($ico)) { $chyba = "IČO " . $ico . " neprošlo kontrolou číslic — překlep?"; return null; }

  $telo = ares_stahni(ARES_ADRESA . $ico, $stav);

  if ($stav === 404) { $chyba = "IČO " . $ico . " rejstřík nezná."; return null; }
  if ($telo === null || $stav === null || $stav >= 400) {
    $chyba = "ARES se nepodařilo zeptat. Vyplňte prosím údaje ručně; hosting nemusí mít povolené spojení ven.";
    return null;
  }

  $data = json_decode($telo, true);
  if (!is_array($data) || !isset($data["obchodniJmeno"])) {
    $chyba = "ARES odpověděl něčím, čemu nerozumím. Vyplňte prosím údaje ručně.";
    return null;
  }

  $sidlo = is_array($data["sidlo"] ?? null) ? $data["sidlo"] : [];
  $psc = (string)($sidlo["psc"] ?? "");
  if (preg_match('/^\d{5}$/', $psc)) $psc = substr($psc, 0, 3) . " " . substr($psc, 3);

  return [
    "nazev" => trim((string)$data["obchodniJmeno"]),
    "ico"   => $ico,
    "dic"   => trim((string)($data["dic"] ?? "")),
    "ulice" => ares_ulice($sidlo),
    "mesto" => trim((string)($sidlo["nazevObce"] ?? "")),
    "psc"   => $psc,
    "stat"  => trim((string)($sidlo["nazevStatu"] ?? "Česká republika")),
  ];
}

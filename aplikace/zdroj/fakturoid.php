<?php
/* =========================================================================
   Fakturoid — čtení úhrad a založení faktury z podkladu

   Druhé místo (vedle ARESu), odkud aplikace volá ven. Přístup leží
   v config.php mimo git: slug účtu, client_id a client_secret pro OAuth
   „client credentials", a kontaktní e-mail, který Fakturoid vyžaduje
   v hlavičce User-Agent. Bez nastavení modul spí a stránky to řeknou.

   Nic se nevolá samo od sebe. Synchronizace úhrad i založení faktury
   jsou tlačítka; žádná faktura nevznikne bez kliknutí.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const FAKTUROID_ADRESA = "https://app.fakturoid.cz/api/v3/";
const FAKTUROID_LIMIT  = 12;   /* vteřin */

function fakturoid_nastaven(): bool {
  global $config;
  return trim((string)($config["fakturoid_slug"] ?? "")) !== ""
    && trim((string)($config["fakturoid_client_id"] ?? "")) !== ""
    && trim((string)($config["fakturoid_client_secret"] ?? "")) !== "";
}

function fakturoid_agent(): string {
  global $config;
  $email = trim((string)($config["fakturoid_email"] ?? nastaveni("firma_email", "doprava@idispecink.cz")));
  return "iDispecink provozni system (" . $email . ")";
}

/* Přístupový token se drží v sezení, dokud neexpiruje. */
function fakturoid_token(?string &$chyba = null): ?string {
  global $config;
  $chyba = null;
  if (!fakturoid_nastaven()) { $chyba = "Fakturoid není nastavený — doplňte přístup do config.php."; return null; }

  $t = $_SESSION["fakturoid_token"] ?? null;
  if (is_array($t) && ($t["do"] ?? 0) > time() + 30) return (string)$t["token"];

  $odpoved = fakturoid_pozadavek("POST", "oauth/token", ["grant_type" => "client_credentials"], $stav, [
    "Authorization: Basic " . base64_encode($config["fakturoid_client_id"] . ":" . $config["fakturoid_client_secret"]),
  ], true);
  if (!is_array($odpoved) || empty($odpoved["access_token"])) {
    $chyba = "Fakturoid nevydal přístupový token (" . ($stav ?: "bez odpovědi") . "). Zkontrolujte client_id a client_secret.";
    return null;
  }
  $_SESSION["fakturoid_token"] = [
    "token" => (string)$odpoved["access_token"],
    "do"    => time() + (int)($odpoved["expires_in"] ?? 7200),
  ];
  return (string)$odpoved["access_token"];
}

/* Jeden požadavek. Bez tokenu ($bez_tokenu) jen pro získání tokenu. */
function fakturoid_pozadavek(string $metoda, string $cesta, ?array $data, ?int &$stav = null, array $hlavicky_navic = [], bool $bez_tokenu = false) {
  global $config;
  $stav = null;
  if (!function_exists("curl_init")) return null;

  /* Adresa rozhraní jde přepsat v config.php — jen pro zkoušení proti
     napodobenině; v provozu se nechává výchozí. */
  $zaklad = rtrim((string)($config["fakturoid_adresa"] ?? FAKTUROID_ADRESA), "/") . "/";
  $adresa = $zaklad . ($bez_tokenu ? $cesta : "accounts/" . rawurlencode((string)$config["fakturoid_slug"]) . "/" . $cesta);
  $hlavicky = array_merge(["User-Agent: " . fakturoid_agent(), "Accept: application/json"], $hlavicky_navic);

  if (!$bez_tokenu) {
    $token = fakturoid_token($chyba);
    if ($token === null) return null;
    $hlavicky[] = "Authorization: Bearer " . $token;
  }

  $spojeni = curl_init();
  $volby = [
    CURLOPT_URL            => $adresa,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => FAKTUROID_LIMIT,
    CURLOPT_CONNECTTIMEOUT => FAKTUROID_LIMIT,
    CURLOPT_CUSTOMREQUEST  => $metoda,
  ];
  if ($metoda === "GET" && $data) {
    $volby[CURLOPT_URL] = $adresa . "?" . http_build_query($data);
  } elseif ($data !== null) {
    $hlavicky[] = "Content-Type: application/json";
    $volby[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
  }
  $volby[CURLOPT_HTTPHEADER] = $hlavicky;
  curl_setopt_array($spojeni, $volby);
  $telo = curl_exec($spojeni);
  $stav = (int)curl_getinfo($spojeni, CURLINFO_RESPONSE_CODE) ?: null;
  curl_close($spojeni);

  if (!is_string($telo) || $telo === "") return null;
  $json = json_decode($telo, true);
  return is_array($json) ? $json : null;
}

/* --- Úhrady ------------------------------------------------------------- */

/* Stáhne vydanou fakturu podle čísla. Vrací pole s klíči pro tabulku faktury,
   nebo null. */
function fakturoid_faktura_podle_cisla(string $cislo, ?string &$chyba = null): ?array {
  $chyba = null;
  $seznam = fakturoid_pozadavek("GET", "invoices.json", ["number" => $cislo], $stav);
  if ($seznam === null) {
    $chyba = "Fakturoid neodpověděl (" . ($stav ?: "spojení") . ").";
    return null;
  }
  foreach ($seznam as $f) {
    if (!is_array($f) || (string)($f["number"] ?? "") !== $cislo) continue;
    return fakturoid_preved_fakturu($f);
  }
  return null;
}

function fakturoid_preved_fakturu(array $f): array {
  $vystaveno = (string)($f["issued_on"] ?? "") ?: null;
  /* Splatnost chodí jako datum due_on; když chybí, dopočítá se z počtu dní. */
  $splatnost = (string)($f["due_on"] ?? "") ?: null;
  if ($splatnost === null && $vystaveno && isset($f["due"])) {
    $splatnost = date("Y-m-d", strtotime($vystaveno . " +" . (int)$f["due"] . " days"));
  }
  return [
    "fakturoid_id" => (int)($f["id"] ?? 0),
    "stav"         => (string)($f["status"] ?? ""),
    "vystaveno"    => $vystaveno,
    "splatnost"    => $splatnost,
    "uhrazeno"     => (string)($f["paid_on"] ?? "") ?: null,
    "castka"       => isset($f["native_subtotal"]) ? (float)$f["native_subtotal"] : (isset($f["subtotal"]) ? (float)$f["subtotal"] : null),
    "castka_s_dph" => isset($f["native_total"]) ? (float)$f["native_total"] : (isset($f["total"]) ? (float)$f["total"] : null),
  ];
}

/* Projde vydané faktury bez úhrady i čísla bez záznamu a doplní je
   z Fakturoidu. Vrací souhrn pro hlášku. */
function fakturoid_synchronizuj_uhrady(?string &$chyba = null): array {
  $chyba = null;
  $aktualizovano = 0; $zalozeno = 0; $nenalezeno = [];

  $cisla = [];
  foreach (radky("SELECT cislo FROM faktury WHERE druh = 'vydana' AND uhrazeno IS NULL") as $r) $cisla[(string)$r["cislo"]] = "existuje";
  foreach (cisla_bez_zaznamu("vydana") as $r) $cisla[(string)$r["cislo"]] = ["firma_id" => $r["firma_id"]];

  foreach ($cisla as $cislo => $info) {
    $z = fakturoid_faktura_podle_cisla((string)$cislo, $chyba_dilci);
    if ($chyba_dilci !== null) { $chyba = $chyba_dilci; break; }
    if ($z === null) { $nenalezeno[] = (string)$cislo; continue; }
    if ($info === "existuje") {
      faktura_uloz("vydana", (string)$cislo, $z);
      $aktualizovano++;
    } else {
      faktura_uloz("vydana", (string)$cislo, array_merge($z, ["firma_id" => $info["firma_id"] ?: null]));
      $zalozeno++;
    }
  }
  return compact("aktualizovano", "zalozeno", "nenalezeno");
}

/* --- Založení faktury z podkladu ---------------------------------------- */

/* Najde odběratele podle IČO, jinak založí. Vrací subject_id. */
function fakturoid_odberatel(array $firma, ?string &$chyba = null): ?int {
  $chyba = null;
  $ico = preg_replace('/\D+/', "", (string)$firma["ico"]);
  if ($ico !== "") {
    $nalezeni = fakturoid_pozadavek("GET", "subjects/search.json", ["query" => $ico], $stav);
    foreach ((array)$nalezeni as $s) {
      if (is_array($s) && preg_replace('/\D+/', "", (string)($s["registration_no"] ?? "")) === $ico) return (int)$s["id"];
    }
  }
  $novy = fakturoid_pozadavek("POST", "subjects.json", [
    "name"            => (string)$firma["nazev"],
    "registration_no" => $ico,
    "vat_no"          => (string)$firma["dic"],
    "street"          => (string)$firma["ulice"],
    "city"            => (string)$firma["mesto"],
    "zip"             => preg_replace('/\s+/', "", (string)$firma["psc"]),
    "country"         => "CZ",
    "email"           => (string)$firma["kontakt_email"],
    "phone"           => (string)$firma["kontakt_telefon"],
  ], $stav);
  if (!is_array($novy) || empty($novy["id"])) {
    $chyba = "Odběratele se nepodařilo ve Fakturoidu založit (" . ($stav ?: "spojení") . ").";
    return null;
  }
  return (int)$novy["id"];
}

/* Založí ve Fakturoidu fakturu s danými řádky — každý je [název, množství,
   jednotka, cena za jednotku bez DPH]. Vrací [cislo, id, data] nebo null.
   Volá se jen z tlačítka po potvrzení. */
function fakturoid_zaloz_fakturu_radky(array $firma, array $radky, int $splatnost_dnu, float $dph, ?string &$chyba = null): ?array {
  $chyba = null;
  if (!$radky) { $chyba = "Faktura nemá žádný řádek."; return null; }
  $subject_id = fakturoid_odberatel($firma, $chyba);
  if ($subject_id === null) return null;

  $lines = [];
  foreach ($radky as [$nazev, $mnozstvi, $jednotka, $cena]) {
    $lines[] = [
      "name"       => (string)$nazev,
      "quantity"   => $mnozstvi,
      "unit_name"  => (string)$jednotka,
      "unit_price" => round((float)$cena, 2),
      "vat_rate"   => $dph,
    ];
  }
  $odpoved = fakturoid_pozadavek("POST", "invoices.json", [
    "subject_id"     => $subject_id,
    "due"            => max(1, $splatnost_dnu),
    "lines"          => $lines,
    "vat_price_mode" => "without_vat",
  ], $stav);
  if (!is_array($odpoved) || empty($odpoved["number"])) {
    $chyba = "Fakturu se nepodařilo založit (" . ($stav ?: "spojení") . ").";
    return null;
  }
  return ["cislo" => (string)$odpoved["number"], "id" => (int)($odpoved["id"] ?? 0), "data" => fakturoid_preved_fakturu($odpoved)];
}

/* Faktura zákazníkovi s řádkem za každou přepravu. */
function fakturoid_zaloz_fakturu(array $firma, array $prepravy, int $splatnost_dnu, float $dph, ?string &$chyba = null): ?array {
  $radky = [];
  foreach ($prepravy as $p) {
    $radky[] = [
      "Přeprava " . $p["cislo"] . " " . ($p["nakladka_misto"] ?: "?") . " – " . ($p["vykladka_misto"] ?: "?")
        . ($p["nakladka_datum"] ? " " . datum($p["nakladka_datum"]) : "")
        . ($p["ref_zakaznika"] ? " (" . $p["ref_zakaznika"] . ")" : ""),
      1, "ks", (float)$p["cena_zakaznik"],
    ];
  }
  return fakturoid_zaloz_fakturu_radky($firma, $radky, $splatnost_dnu, $dph, $chyba);
}

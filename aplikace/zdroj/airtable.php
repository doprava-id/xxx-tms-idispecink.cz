<?php
/* =========================================================================
   Airtable — načtení přeprav z provozní evidence firmy

   Třetí místo (vedle ARESu a Fakturoidu), odkud aplikace volá ven. Přístup
   leží v config.php mimo git: token a identifikátor báze. Repozitář je
   veřejný, takže v něm není ani token, ani báze, ani tabulka, ani názvy
   polí — tabulku si vyberete v Nastavení a mapování polí se ukládá do
   databáze, která do repozitáře taky nepatří.

   Směr je zatím jen dovnitř: z Airtable do provozního systému. Zpět se
   nezapisuje nic, takže stačí token s právem číst. Nic se nevolá samo od
   sebe — načtení je tlačítko.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const AIRTABLE_ADRESA   = "https://api.airtable.com/v0/";
const AIRTABLE_LIMIT    = 15;    /* vteřin na jeden požadavek */
const AIRTABLE_STRANKA  = 100;   /* záznamů na stránku, víc Airtable nedá */
const AIRTABLE_PRODLEVA = 220000; /* mikrosekund mezi stránkami — limit je 5 dotazů za vteřinu */
const AIRTABLE_STROP    = 2000;  /* nejvíc záznamů na jedno načtení */

/* Pole přepravy, která umí napojení naplnit. Klíče jsou naše, ne jejich —
   co je čemu v Airtable, řekne mapování v Nastavení. */
const AIRTABLE_POLE = [
  "cislo"           => "Číslo přepravy — párovací klíč",
  "stav"            => "Stav",
  "zakaznik"        => "Zákazník (název firmy)",
  "ref_zakaznika"   => "Reference zákazníka",
  "dopravce"        => "Dopravce (název firmy)",
  "nakladka_misto"  => "Nakládka — místo",
  "nakladka_adresa" => "Nakládka — adresa",
  "nakladka_datum"  => "Nakládka — datum",
  "nakladka_od"     => "Nakládka — okno od",
  "nakladka_do"     => "Nakládka — okno do",
  "vykladka_misto"  => "Vykládka — místo",
  "vykladka_adresa" => "Vykládka — adresa",
  "vykladka_datum"  => "Vykládka — datum",
  "vykladka_od"     => "Vykládka — okno od",
  "vykladka_do"     => "Vykládka — okno do",
  "zbozi"           => "Zboží",
  "hmotnost"        => "Hmotnost (kg)",
  "palet"           => "Počet palet",
  "ldm"             => "LDM",
  "km"              => "Kilometry",
  "pozadavky"       => "Zvláštní požadavky",
  "spz"             => "SPZ",
  "ridic_jmeno"     => "Řidič",
  "ridic_telefon"   => "Telefon na řidiče",
  "cena_zakaznik"   => "Cena zákazníka",
  "cena_dopravce"   => "Cena dopravce",
  "faktura_vydana"  => "Číslo vydané faktury",
  "poznamka"        => "Poznámka",
];

/* Slova, podle kterých se pole odhadují při prvním otevření. Je to jen
   nápověda do formuláře — co se opravdu použije, potvrzuje uživatel. */
const AIRTABLE_NAPOVEDA = [
  "cislo"           => ["shipment", "číslo přepravy", "cislo prepravy", "zásilka", "reference id"],
  "stav"            => ["stav", "status"],
  "zakaznik"        => ["zákazník", "zakaznik", "objednatel", "customer"],
  "ref_zakaznika"   => ["č. obj", "c. obj", "objednávka zákazníka", "reference", "doplňková čísla"],
  "dopravce"        => ["dopravce", "carrier", "přepravce"],
  "nakladka_misto"  => ["n: místo", "nakládka", "nakladka", "origin", "odkud"],
  "nakladka_adresa" => ["n: název", "adresa nakládky", "origin location"],
  "nakladka_datum"  => ["datum", "den jízdy", "date"],
  "nakladka_od"     => ["čas n od", "cas n od", "okno n od"],
  "nakladka_do"     => ["čas n do", "cas n do", "okno n do"],
  "vykladka_misto"  => ["v: místo", "vykládka", "vykladka", "destination", "kam"],
  "vykladka_adresa" => ["v: název", "adresa vykládky", "destination location"],
  "vykladka_datum"  => ["datum výkládky", "datum vykladky", "datum vykládky"],
  "vykladka_od"     => ["čas v od", "cas v od", "okno v od"],
  "vykladka_do"     => ["čas v do", "cas v do", "okno v do"],
  "zbozi"           => ["zboží", "zbozi", "komodita", "typ přepravy"],
  "hmotnost"        => ["hmotnost", "váha", "vaha", "weight"],
  "palet"           => ["palet", "kusů", "kusu", "pallets"],
  "ldm"             => ["ldm", "laden length"],
  "km"              => ["km", "kilometr"],
  "pozadavky"       => ["požadavky", "pozadavky"],
  "spz"             => ["spz", "rz"],
  "ridic_jmeno"     => ["řidič", "ridic", "driver"],
  "ridic_telefon"   => ["telefon"],
  "cena_zakaznik"   => ["cena zákazník", "cena zakaznik", "výnos", "tržba", "prodej"],
  "cena_dopravce"   => ["cena dopravce", "nákup", "naklad", "náklad"],
  "faktura_vydana"  => ["č. faktury", "c. faktury", "číslo faktury", "faktura"],
  "poznamka"        => ["poznámka dispečera", "poznámka", "poznamka", "pozn"],
];

function airtable_nastaven(): bool {
  global $config;
  return trim((string)($config["airtable_token"] ?? "")) !== ""
    && trim((string)($config["airtable_baze"] ?? "")) !== "";
}

/* --- Volání rozhraní ---------------------------------------------------- */

/* Jeden požadavek. $cesta je za základní adresou, $dotaz jde do query.
   Vrací pole z odpovědi, nebo null; podrobnost jde do $chyba. */
function airtable_pozadavek(string $cesta, array $dotaz = [], ?string &$chyba = null, ?int &$stav = null): ?array {
  global $config;
  $chyba = null; $stav = null;
  if (!airtable_nastaven()) { $chyba = "Airtable není nastavený — doplňte token a bázi do config.php."; return null; }
  if (!function_exists("curl_init")) { $chyba = "PHP na tomto serveru nemá rozšíření curl, ven se volat nedá."; return null; }

  /* Adresa rozhraní jde přepsat v config.php — jen pro zkoušení proti
     napodobenině; v provozu se nechává výchozí. */
  $zaklad = rtrim((string)($config["airtable_adresa"] ?? AIRTABLE_ADRESA), "/") . "/";
  $adresa = $zaklad . $cesta . ($dotaz ? "?" . airtable_dotaz($dotaz) : "");

  $spojeni = curl_init();
  curl_setopt_array($spojeni, [
    CURLOPT_URL            => $adresa,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => AIRTABLE_LIMIT,
    CURLOPT_CONNECTTIMEOUT => AIRTABLE_LIMIT,
    CURLOPT_HTTPHEADER     => [
      "Authorization: Bearer " . trim((string)$config["airtable_token"]),
      "Accept: application/json",
    ],
  ]);
  $telo = curl_exec($spojeni);
  $stav = (int)curl_getinfo($spojeni, CURLINFO_RESPONSE_CODE) ?: null;
  $potiz = curl_error($spojeni);
  curl_close($spojeni);

  if (!is_string($telo) || $telo === "") {
    $chyba = "Airtable neodpověděl" . ($potiz !== "" ? " (" . $potiz . ")" : "") . ". Hosting musí pustit odchozí HTTPS.";
    return null;
  }
  $json = json_decode($telo, true);
  if (!is_array($json)) { $chyba = "Airtable vrátil odpověď, které nerozumím (" . ($stav ?: "?") . ")."; return null; }

  if ($stav !== null && $stav >= 400) {
    $popis = (string)($json["error"]["message"] ?? ($json["error"]["type"] ?? ""));
    if ($stav === 401 || $stav === 403) {
      $chyba = "Airtable odmítl přístup (" . $stav . "). Zkontrolujte token a jeho práva k bázi." . ($popis !== "" ? " " . $popis : "");
    } elseif ($stav === 404) {
      $chyba = "Airtable takovou bázi nebo tabulku nezná (404). Zkontrolujte identifikátor báze v config.php.";
    } elseif ($stav === 422) {
      $chyba = "Airtable dotaz odmítl (422)." . ($popis !== "" ? " " . $popis : "");
    } else {
      $chyba = "Airtable odpověděl chybou " . $stav . ($popis !== "" ? ": " . $popis : "") . ".";
    }
    return null;
  }
  return $json;
}

/* Airtable čeká seznam hodnot jako fields[]=A&fields[]=B. Bez hranatých
   závorek by z toho serveru zůstala jen poslední hodnota a rozhraní by
   vrátilo jediné pole; http_build_query zase přidává indexy (fields[0]),
   které Airtable nebere. */
function airtable_dotaz(array $dotaz): string {
  $casti = [];
  foreach ($dotaz as $klic => $hodnota) {
    if (is_array($hodnota)) {
      foreach ($hodnota as $jedna) $casti[] = rawurlencode($klic) . "%5B%5D=" . rawurlencode((string)$jedna);
    } else {
      $casti[] = rawurlencode($klic) . "=" . rawurlencode((string)$hodnota);
    }
  }
  return implode("&", $casti);
}

/* Popis tabulek báze: název, identifikátor, pole a volby výběrů. */
function airtable_tabulky(?string &$chyba = null): ?array {
  global $config;
  $odpoved = airtable_pozadavek("meta/bases/" . rawurlencode(trim((string)$config["airtable_baze"])) . "/tables", [], $chyba);
  if ($odpoved === null) return null;
  $ven = [];
  foreach ((array)($odpoved["tables"] ?? []) as $t) {
    $pole = [];
    foreach ((array)($t["fields"] ?? []) as $f) {
      $volby = [];
      foreach ((array)($f["options"]["choices"] ?? []) as $v) {
        if (isset($v["name"])) $volby[] = (string)$v["name"];
      }
      $pole[] = [
        "id"      => (string)($f["id"] ?? ""),
        "nazev"   => (string)($f["name"] ?? ""),
        "typ"     => (string)($f["type"] ?? ""),
        "volby"   => $volby,
        "odkaz"   => (string)($f["options"]["linkedTableId"] ?? ""),
      ];
    }
    $ven[] = [
      "id"       => (string)($t["id"] ?? ""),
      "nazev"    => (string)($t["name"] ?? ""),
      "primarni" => (string)($t["primaryFieldId"] ?? ""),
      "pole"     => $pole,
    ];
  }
  return $ven;
}

/* --- Mapování v Nastavení ----------------------------------------------- */

function airtable_tabulka(): string { return nastaveni("airtable_tabulka"); }

function airtable_mapovani(): array {
  $ulozene = json_decode(nastaveni("airtable_mapovani", "{}"), true);
  return is_array($ulozene) ? $ulozene : [];
}

function airtable_uloz_mapovani(array $mapovani): void {
  uloz_nastaveni("airtable_mapovani", (string)json_encode($mapovani, JSON_UNESCAPED_UNICODE));
}

function airtable_stavy(): array {
  $ulozene = json_decode(nastaveni("airtable_stavy", "{}"), true);
  return is_array($ulozene) ? $ulozene : [];
}

function airtable_uloz_stavy(array $stavy): void {
  uloz_nastaveni("airtable_stavy", (string)json_encode($stavy, JSON_UNESCAPED_UNICODE));
}

/* Odhad pole podle názvu — jen předvyplnění formuláře. */
function airtable_hadej_pole(array $pole, string $nase): string {
  $bez = function (string $t): string {
    $t = mb_strtolower(trim($t));
    return function_exists("iconv") ? (string)@iconv("UTF-8", "ASCII//TRANSLIT", $t) : $t;
  };
  foreach ([true, false] as $presne) {
    foreach (AIRTABLE_NAPOVEDA[$nase] ?? [] as $slovo) {
      $s = $bez($slovo);
      foreach ($pole as $p) {
        $n = $bez((string)$p["nazev"]);
        if ($n === "" || $s === "") continue;
        if ($presne ? $n === $s : mb_strpos($n, $s) !== false) return (string)$p["nazev"];
      }
    }
  }
  return "";
}

/* --- Převod hodnot ------------------------------------------------------ */

/* Z Airtable chodí i pole hodnot (odkazy, lookupy) a objekty (přílohy).
   Do jednoho textu se to složí takhle; odkazy na záznamy řeší volající. */
function airtable_text($hodnota): string {
  if ($hodnota === null || $hodnota === false) return "";
  if (is_bool($hodnota)) return $hodnota ? "1" : "";
  if (is_scalar($hodnota)) return trim((string)$hodnota);
  if (is_array($hodnota)) {
    $casti = [];
    foreach ($hodnota as $jedna) {
      if (is_array($jedna)) {
        $casti[] = (string)($jedna["name"] ?? ($jedna["filename"] ?? ""));
      } else {
        $casti[] = trim((string)$jedna);
      }
    }
    return trim(implode(", ", array_filter($casti, "strlen")));
  }
  return "";
}

/* Vypadá to jako identifikátor záznamu? Odkazová pole chodí jako pole
   takových identifikátorů, ne jako názvy. */
function airtable_je_odkaz(string $hodnota): bool {
  return (bool)preg_match('/^rec[A-Za-z0-9]{14}$/', $hodnota);
}

/* Názvy záznamů odkazované tabulky. Načte se jedním průchodem celá
   tabulka (primární pole) a drží se v paměti do konce běhu — jednotlivé
   dotazy na každý odkaz by narazily na limit rozhraní. */
function airtable_nazvy_tabulky(string $tabulka_id, ?string &$chyba = null): array {
  global $config;
  static $pamet = [];
  if (isset($pamet[$tabulka_id])) return $pamet[$tabulka_id];

  $nazvy = []; $posun = null; $stran = 0;
  do {
    $dotaz = ["pageSize" => AIRTABLE_STRANKA];
    if ($posun !== null) $dotaz["offset"] = $posun;
    $odpoved = airtable_pozadavek(rawurlencode(trim((string)$config["airtable_baze"])) . "/" . rawurlencode($tabulka_id), $dotaz, $chyba);
    if ($odpoved === null) break;
    foreach ((array)($odpoved["records"] ?? []) as $z) {
      $pole = (array)($z["fields"] ?? []);
      $prvni = "";
      foreach ($pole as $h) { $prvni = airtable_text($h); if ($prvni !== "") break; }
      $nazvy[(string)$z["id"]] = $prvni;
    }
    $posun = isset($odpoved["offset"]) ? (string)$odpoved["offset"] : null;
    $stran++;
    if ($posun !== null) usleep(AIRTABLE_PRODLEVA);
  } while ($posun !== null && $stran < 20);

  $pamet[$tabulka_id] = $nazvy;
  return $nazvy;
}

/* --- Načtení přeprav ---------------------------------------------------- */

/* Volby: od, do (období podle namapovaného data nakládky), rezim
   (nove | doplnit | prepsat), strop, nanecisto (jen ukázat, nezapisovat),
   zakladat_firmy. Vrací souhrn a u nanečisto i ukázku řádků. */
function airtable_nacti(array $volby, ?string &$chyba = null): ?array {
  global $config;
  $chyba = null;
  $mapovani = airtable_mapovani();
  $tabulka  = airtable_tabulka();
  if ($tabulka === "") { $chyba = "Není vybraná tabulka."; return null; }
  if (trim((string)($mapovani["cislo"] ?? "")) === "") { $chyba = "Není namapované číslo přepravy — bez něj by se záznamy nedaly párovat."; return null; }

  $rezim     = in_array($volby["rezim"] ?? "", ["nove", "doplnit", "prepsat"], true) ? $volby["rezim"] : "doplnit";
  $nanecisto = !empty($volby["nanecisto"]);
  $zakladat  = !empty($volby["zakladat_firmy"]);
  $strop     = max(1, min(AIRTABLE_STROP, (int)($volby["strop"] ?? 500)));

  /* Ven se ptáme jen na namapovaná pole — méně dat i méně starostí. */
  $chtena = [];
  foreach ($mapovani as $hodnota) {
    $hodnota = trim((string)$hodnota);
    if ($hodnota !== "" && !in_array($hodnota, $chtena, true)) $chtena[] = $hodnota;
  }

  $dotaz = ["pageSize" => AIRTABLE_STRANKA, "fields" => $chtena];
  /* Období umí odfiltrovat samo rozhraní; u názvu pole se složenými
     závorkami by se vzorec rozpadl, tak se v takovém případě filtruje až
     tady u nás. */
  $pole_data = trim((string)($mapovani["nakladka_datum"] ?? ""));
  $od = (string)($volby["od"] ?? ""); $do = (string)($volby["do"] ?? "");
  $filtrovat_doma = false;
  if ($pole_data !== "" && ($od !== "" || $do !== "")) {
    if (strpbrk($pole_data, "{}") !== false) {
      $filtrovat_doma = true;
    } else {
      $podminky = [];
      if ($od !== "") $podminky[] = "IS_AFTER({" . $pole_data . "}, DATETIME_PARSE('" . date("Y-m-d", strtotime($od . " -1 day")) . "', 'YYYY-MM-DD'))";
      if ($do !== "") $podminky[] = "IS_BEFORE({" . $pole_data . "}, DATETIME_PARSE('" . date("Y-m-d", strtotime($do . " +1 day")) . "', 'YYYY-MM-DD'))";
      $dotaz["filterByFormula"] = count($podminky) > 1 ? "AND(" . implode(", ", $podminky) . ")" : $podminky[0];
    }
  }

  $zaznamy = []; $posun = null;
  do {
    if ($posun !== null) $dotaz["offset"] = $posun;
    $odpoved = airtable_pozadavek(rawurlencode(trim((string)$config["airtable_baze"])) . "/" . rawurlencode($tabulka), $dotaz, $chyba);
    if ($odpoved === null) return null;
    foreach ((array)($odpoved["records"] ?? []) as $z) $zaznamy[] = (array)($z["fields"] ?? []);
    $posun = isset($odpoved["offset"]) ? (string)$odpoved["offset"] : null;
    if ($posun !== null && count($zaznamy) < $strop) usleep(AIRTABLE_PRODLEVA);
  } while ($posun !== null && count($zaznamy) < $strop);
  $zaznamy = array_slice($zaznamy, 0, $strop);

  /* Odkazová pole vrací identifikátory záznamů; názvy se doplní z tabulky,
     na kterou odkazují. */
  $odkazy = [];
  $tabulky = airtable_tabulky($chyba_meta);
  if (is_array($tabulky)) {
    foreach ($tabulky as $t) {
      if ((string)$t["nazev"] !== $tabulka && (string)$t["id"] !== $tabulka) continue;
      foreach ($t["pole"] as $p) {
        if ($p["odkaz"] !== "" && in_array($p["nazev"], $chtena, true)) $odkazy[$p["nazev"]] = $p["odkaz"];
      }
    }
  }

  $stavy_mapa = airtable_stavy();
  $ted = date("Y-m-d H:i:s");
  $uzivatel_id = (int)((uzivatel() ?? [])["id"] ?? 0);
  $zalozeno = 0; $doplneno = 0; $beze_zmeny = 0; $preskoceno = 0; $ukazka = []; $potize = [];

  foreach ($zaznamy as $poradi => $z) {
    /* Jedna hodnota z Airtable jako text, s rozřešenými odkazy. */
    $ber = function (string $nase) use ($z, $mapovani, $odkazy, &$chyba): string {
      $pole = trim((string)($mapovani[$nase] ?? ""));
      if ($pole === "" || !array_key_exists($pole, $z)) return "";
      $text = airtable_text($z[$pole]);
      if ($text === "" || !isset($odkazy[$pole])) return $text;
      $nazvy = airtable_nazvy_tabulky($odkazy[$pole], $chyba);
      $casti = [];
      foreach (explode(", ", $text) as $kus) {
        $casti[] = airtable_je_odkaz($kus) ? (string)($nazvy[$kus] ?? "") : $kus;
      }
      return trim(implode(", ", array_filter($casti, "strlen")));
    };

    $cislo = $ber("cislo");
    if ($cislo === "") { $preskoceno++; continue; }

    if ($filtrovat_doma) {
      $den = import_datum($ber("nakladka_datum"));
      if ($den === null) { $preskoceno++; continue; }
      if ($od !== "" && $den < $od) { $preskoceno++; continue; }
      if ($do !== "" && $den > $do) { $preskoceno++; continue; }
    }

    $stav_zvenku = $ber("stav");
    $stav = (string)($stavy_mapa[$stav_zvenku] ?? "");
    if (!isset(STAVY[$stav])) $stav = "nova";

    $trasa = [
      "nakladka_misto"  => $ber("nakladka_misto"),
      "nakladka_adresa" => $ber("nakladka_adresa"),
      "nakladka_datum"  => import_datum($ber("nakladka_datum")),
      "nakladka_od"     => import_cas($ber("nakladka_od")),
      "nakladka_do"     => import_cas($ber("nakladka_do")),
      "vykladka_misto"  => $ber("vykladka_misto"),
      "vykladka_adresa" => $ber("vykladka_adresa"),
      "vykladka_datum"  => import_datum($ber("vykladka_datum")),
      "vykladka_od"     => import_cas($ber("vykladka_od")),
      "vykladka_do"     => import_cas($ber("vykladka_do")),
      "stav"            => $stav,
    ];

    $udaje = [
      "stav"            => $stav,
      "ref_zakaznika"   => $ber("ref_zakaznika"),
      "zbozi"           => $ber("zbozi"),
      "hmotnost"        => ($h = import_cislo($ber("hmotnost"))) === null ? null : (int)$h,
      "palet"           => ($h = import_cislo($ber("palet"))) === null ? null : (int)$h,
      "ldm"             => import_cislo($ber("ldm")),
      "km"              => ($h = import_cislo($ber("km"))) === null ? null : (int)$h,
      "pozadavky"       => $ber("pozadavky"),
      "spz"             => $ber("spz"),
      "ridic_jmeno"     => $ber("ridic_jmeno"),
      "ridic_telefon"   => $ber("ridic_telefon"),
      "cena_zakaznik"   => import_cislo($ber("cena_zakaznik")),
      "cena_dopravce"   => import_cislo($ber("cena_dopravce")),
      "faktura_vydana"  => $ber("faktura_vydana"),
      "poznamka"        => $ber("poznamka"),
    ] + $trasa;
    unset($udaje["stav"]);
    $udaje["stav"] = $stav;

    $nazev_zakaznika = $ber("zakaznik");
    $nazev_dopravce  = $ber("dopravce");

    $stara = radek("SELECT * FROM prepravy WHERE cislo = ? AND sablona = 0", [$cislo]);

    if ($nanecisto) {
      if (count($ukazka) < 15) {
        $ukazka[] = [
          "cislo" => $cislo, "stav" => $stav, "stav_zvenku" => $stav_zvenku,
          "zakaznik" => $nazev_zakaznika, "dopravce" => $nazev_dopravce,
          "trasa" => ($trasa["nakladka_misto"] ?: "?") . " → " . ($trasa["vykladka_misto"] ?: "?"),
          "datum" => $trasa["nakladka_datum"], "cena_dopravce" => $udaje["cena_dopravce"],
          "co" => $stara ? ($rezim === "nove" ? "přeskočí se" : "doplní se") : "založí se",
        ];
      }
      if ($stara) { $rezim === "nove" ? $preskoceno++ : $doplneno++; } else { $zalozeno++; }
      continue;
    }

    if ($stara && $rezim === "nove") { $preskoceno++; continue; }

    if (!$stara) {
      $data = $udaje;
      $data["cislo"]       = $cislo;
      $data["sablona"]     = 0;
      $data["doklady"]     = "ceka";
      $data["typ_vozidla"] = "plachta";
      $data["zakaznik_id"] = import_firma($nazev_zakaznika, "zakaznik", $zakladat);
      $data["dopravce_id"] = import_firma($nazev_dopravce, "dopravce", $zakladat);
      $data["dispecink_klient_id"] = je_klient_dispecinku($data["dopravce_id"]) ? $data["dopravce_id"] : null;
      $data["vytvoreno"]   = $ted;
      $data["upraveno"]    = $ted;
      $data["vytvoril"]    = $uzivatel_id;
      $data["upravil"]     = $uzivatel_id;
      $id = vloz("prepravy", $data);
      zaloz_body_z_poli($id, $trasa);
      prepocitej_trasu($id);
      zapis_udalost($id, "Přeprava " . $cislo . " načtena z Airtable");
      $zalozeno++;
      continue;
    }

    /* Existující: v režimu „doplnit" se sahá jen na prázdná pole, aby se
       nepřepsalo, co dispečer zadal ručně. Trasa se nepřepisuje nikdy —
       body jsou zdrojem pravdy a mohly se mezitím rozrůst. */
    $zmeny = [];
    foreach ($udaje as $klic => $hodnota) {
      if (array_key_exists($klic, $trasa)) continue;
      if ($hodnota === null || $hodnota === "") continue;
      $stare = $stara[$klic] ?? null;
      $prazdne = ($stare === null || $stare === "" || ($klic === "stav" && $stare === "nova"));
      if ($rezim === "doplnit" && !$prazdne) continue;
      if ((string)$stare === (string)$hodnota) continue;
      $zmeny[$klic] = $hodnota;
    }
    foreach (["zakaznik_id" => [$nazev_zakaznika, "zakaznik"], "dopravce_id" => [$nazev_dopravce, "dopravce"]] as $klic => $co) {
      if ($co[0] === "") continue;
      if ($rezim === "doplnit" && !empty($stara[$klic])) continue;
      $firma = import_firma($co[0], $co[1], $zakladat);
      if ($firma && (int)$stara[$klic] !== $firma) $zmeny[$klic] = $firma;
    }
    if (!$zmeny) { $beze_zmeny++; continue; }
    $zmeny["upraveno"] = $ted;
    $zmeny["upravil"]  = $uzivatel_id;
    uprav("prepravy", (int)$stara["id"], $zmeny);
    zapis_udalost((int)$stara["id"], "Z Airtable doplněno: " . implode(", ", array_keys(array_diff_key($zmeny, ["upraveno" => 1, "upravil" => 1]))));
    $doplneno++;
  }

  $souhrn = [
    "zaznamu" => count($zaznamy), "zalozeno" => $zalozeno, "doplneno" => $doplneno,
    "beze_zmeny" => $beze_zmeny, "preskoceno" => $preskoceno, "ukazka" => $ukazka,
    "nanecisto" => $nanecisto, "strop" => $strop, "potize" => $potize,
  ];
  if (!$nanecisto) {
    uloz_nastaveni("airtable_naposledy", date("j. n. Y H:i") . " · " . $zalozeno . " nových, " . $doplneno . " doplněných, " . $beze_zmeny . " beze změny");
    zapis_udalost(null, "Načtení z Airtable: " . $zalozeno . " nových, " . $doplneno . " doplněných, " . $beze_zmeny . " beze změny");
  }
  return $souhrn;
}

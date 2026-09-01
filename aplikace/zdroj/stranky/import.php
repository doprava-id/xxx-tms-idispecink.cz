<?php
/* Import přeprav z CSV — obecný čtečka, ne konektor na konkrétní systém.

   Nahraný soubor se uloží do data/ (odtud ho web nevydá), přečte se
   hlavička, uživatel přiřadí sloupce k polím přepravy a teprve pak se
   řádky zakládají. Soubor se po importu maže.

   Do repozitáře nepatří nastavení žádné konkrétní pipeline — proto se
   mapování sloupců nikam neukládá a dělá se pokaždé znovu, jen s tím,
   že se systém pokusí sloupce odhadnout podle názvů. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_spravce();
uklid_stare_importy();

const IMPORT_POLE = [
  "cislo"           => "Číslo přepravy",
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
  "pozadavky"       => "Zvláštní požadavky",
  "spz"             => "SPZ",
  "ridic_jmeno"     => "Řidič",
  "ridic_telefon"   => "Telefon na řidiče",
  "cena_zakaznik"   => "Cena zákazníka",
  "cena_dopravce"   => "Cena dopravce",
  "poznamka"        => "Poznámka",
];

function cesta_importu(string $znacka): string {
  return APLIKACE_CESTA . "/data/import-" . $znacka . ".csv";
}

/* Nahrané soubory, u kterých se import nedokončil, by se v data/ jinak
   vršily. Po hodině jdou pryč. */
function uklid_stare_importy(): void {
  foreach ((array)glob(APLIKACE_CESTA . "/data/import-*.csv") as $soubor) {
    if (is_file($soubor) && filemtime($soubor) < time() - 3600) @unlink($soubor);
  }
}

/* Oddělovač se pozná z hlavičky — středník, čárka nebo tabulátor. */
function odhad_oddelovace(string $radek): string {
  $nejlepsi = ";"; $nejvic = -1;
  foreach ([";", ",", "\t"] as $znak) {
    $pocet = substr_count($radek, $znak);
    if ($pocet > $nejvic) { $nejvic = $pocet; $nejlepsi = $znak; }
  }
  return $nejlepsi;
}

/* Český Excel exportuje často ve Windows-1250, ne v UTF-8. Převod dělá
   iconv; mbstring CP1250 v řadě sestavení PHP nezná, takže je až druhý
   v pořadí a s nejbližší kódovou stránkou, kterou umí. */
function na_utf8(string $obsah): string {
  if (substr($obsah, 0, 3) === "\xEF\xBB\xBF") $obsah = substr($obsah, 3);
  if (mb_check_encoding($obsah, "UTF-8")) return $obsah;

  if (function_exists("iconv")) {
    $prevedeno = @iconv("CP1250", "UTF-8//IGNORE", $obsah);
    if ($prevedeno !== false) return $prevedeno;
  }
  $znama = array_map("strtolower", mb_list_encodings());
  foreach (["cp1250", "windows-1250", "iso-8859-2"] as $kodovani) {
    if (in_array($kodovani, $znama, true)) {
      return (string)mb_convert_encoding($obsah, "UTF-8", $kodovani);
    }
  }
  return $obsah;
}

function nacti_csv(string $cesta): array {
  $obsah = (string)file_get_contents($cesta);
  $obsah = na_utf8($obsah);
  $obsah = str_replace(["\r\n", "\r"], "\n", $obsah);
  $prvni = strtok($obsah, "\n");
  $oddelovac = odhad_oddelovace((string)$prvni);

  $radky = [];
  $ukazatel = fopen("php://memory", "r+");
  fwrite($ukazatel, $obsah);
  rewind($ukazatel);
  while (($r = fgetcsv($ukazatel, 0, $oddelovac, '"', "")) !== false) {
    if ($r === [null] || $r === false) continue;
    $radky[] = $r;
  }
  fclose($ukazatel);
  return $radky;
}

/* Datum z běžných českých i strojových tvarů. */
function import_datum(string $h): ?string {
  $h = trim($h);
  if ($h === "") return null;
  foreach (["d.m.Y", "j.n.Y", "d. m. Y", "j. n. Y", "Y-m-d", "d/m/Y", "d.m.y"] as $tvar) {
    $d = DateTime::createFromFormat($tvar, $h);
    if ($d && $d->format($tvar) === $h) return $d->format("Y-m-d");
  }
  $d = date_create($h);
  return $d ? $d->format("Y-m-d") : null;
}

function import_cas(string $h): string {
  $h = trim($h);
  if ($h === "") return "";
  if (preg_match('/(\d{1,2})[:.](\d{2})/', $h, $shoda)) {
    return str_pad($shoda[1], 2, "0", STR_PAD_LEFT) . ":" . $shoda[2];
  }
  return "";
}

function import_cislo(string $h): ?float {
  $h = str_replace([" ", "\u{00A0}", "Kč", "kg"], "", trim($h));
  $h = str_replace(",", ".", $h);
  if ($h === "" || !is_numeric($h)) return null;
  return (float)$h;
}

/* Firma podle názvu — najde, nebo (smí-li) založí. */
function import_firma(string $nazev, string $typ, bool $zakladat): ?int {
  $nazev = trim($nazev);
  if ($nazev === "") return null;
  $id = hodnota("SELECT id FROM firmy WHERE LOWER(nazev) = ?", [mb_strtolower($nazev)]);
  if ($id) return (int)$id;
  if (!$zakladat) return null;
  return vloz("firmy", [
    "typ" => $typ, "nazev" => $nazev, "stat" => "Česká republika",
    "aktivni" => 1, "vytvoreno" => date("Y-m-d H:i:s"),
  ]);
}

/* --- Nahrání souboru ---------------------------------------------------- */

$znacka = (string)vstup("soubor");
if (!preg_match('/^[0-9a-f]{32}$/', $znacka)) $znacka = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "nahrat") {
  $soubor = $_FILES["csv"] ?? null;
  if (!$soubor || ($soubor["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    vzkaz("chyba", "Soubor se nepodařilo nahrát. Zkontrolujte, že není větší, než hosting povoluje.");
    presmeruj(odkaz("import"));
  }
  if ((int)$soubor["size"] > 8 * 1024 * 1024) {
    vzkaz("chyba", "Soubor je větší než 8 MB. Rozdělte ho na menší části.");
    presmeruj(odkaz("import"));
  }
  $nova_znacka = bin2hex(random_bytes(16));
  if (!move_uploaded_file($soubor["tmp_name"], cesta_importu($nova_znacka))) {
    vzkaz("chyba", "Soubor nešlo uložit — zkontrolujte práva adresáře data/.");
    presmeruj(odkaz("import"));
  }
  presmeruj(odkaz("import", ["soubor" => $nova_znacka]));
}

/* --- Vlastní import ----------------------------------------------------- */

$vysledek = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "importovat" && $znacka !== "") {
  $cesta = cesta_importu($znacka);
  if (!is_file($cesta)) {
    vzkaz("chyba", "Nahraný soubor už není k dispozici. Nahrajte ho prosím znovu.");
    presmeruj(odkaz("import"));
  }

  $mapovani = (array)($_POST["mapovani"] ?? []);
  $zakladat = vstup_ano_ne("zakladat_firmy") === 1;
  $data = nacti_csv($cesta);
  array_shift($data);   /* hlavička */

  $stavy_zpet = [];
  foreach (STAVY as $klic => $popis) $stavy_zpet[mb_strtolower($popis)] = $klic;

  $pridano = 0; $preskoceno = 0; $chyby = [];

  foreach ($data as $cislo_radku => $r) {
    $ber = function (string $pole) use ($mapovani, $r): string {
      $sloupec = $mapovani[$pole] ?? "";
      if ($sloupec === "" || !isset($r[(int)$sloupec])) return "";
      return trim((string)$r[(int)$sloupec]);
    };

    $nakladka = $ber("nakladka_misto");
    $vykladka = $ber("vykladka_misto");
    if ($nakladka === "" && $vykladka === "") { $preskoceno++; continue; }

    $cislo = $ber("cislo");
    if ($cislo !== "" && hodnota("SELECT COUNT(*) FROM prepravy WHERE cislo = ?", [$cislo]) > 0) {
      $preskoceno++;
      continue;
    }

    $stav_text = mb_strtolower($ber("stav"));
    $stav = STAVY[$stav_text] ?? ($stavy_zpet[$stav_text] ?? "nova");

    try {
      $id = vloz("prepravy", [
        "cislo"           => $cislo !== "" ? $cislo : dalsi_cislo(),
        "stav"            => $stav,
        "zakaznik_id"     => import_firma($ber("zakaznik"), "zakaznik", $zakladat),
        "ref_zakaznika"   => $ber("ref_zakaznika"),
        "dopravce_id"     => import_firma($ber("dopravce"), "dopravce", $zakladat),
        "nakladka_misto"  => $nakladka,
        "nakladka_adresa" => $ber("nakladka_adresa"),
        "nakladka_datum"  => import_datum($ber("nakladka_datum")),
        "nakladka_od"     => import_cas($ber("nakladka_od")),
        "nakladka_do"     => import_cas($ber("nakladka_do")),
        "vykladka_misto"  => $vykladka,
        "vykladka_adresa" => $ber("vykladka_adresa"),
        "vykladka_datum"  => import_datum($ber("vykladka_datum")),
        "vykladka_od"     => import_cas($ber("vykladka_od")),
        "vykladka_do"     => import_cas($ber("vykladka_do")),
        "zbozi"           => $ber("zbozi"),
        "hmotnost"        => ($h = import_cislo($ber("hmotnost"))) === null ? null : (int)$h,
        "palet"           => ($h = import_cislo($ber("palet"))) === null ? null : (int)$h,
        "ldm"             => import_cislo($ber("ldm")),
        "typ_vozidla"     => "plachta",
        "pozadavky"       => $ber("pozadavky"),
        "spz"             => $ber("spz"),
        "ridic_jmeno"     => $ber("ridic_jmeno"),
        "ridic_telefon"   => $ber("ridic_telefon"),
        "cena_zakaznik"   => import_cislo($ber("cena_zakaznik")),
        "cena_dopravce"   => import_cislo($ber("cena_dopravce")),
        "doklady"         => "ceka",
        "poznamka"        => $ber("poznamka"),
        "vytvoreno"       => date("Y-m-d H:i:s"),
        "upraveno"        => date("Y-m-d H:i:s"),
        "vytvoril"        => (int)uzivatel()["id"],
      ]);
      $pridano++;
      if ($pridano <= 3) zapis_udalost($id, "Založeno importem z CSV");
    } catch (PDOException $e) {
      error_log("iDispecink TMS import: " . $e->getMessage());
      if (count($chyby) < 10) $chyby[] = "Řádek " . ($cislo_radku + 2) . " se nepodařilo uložit.";
    }
  }

  @unlink($cesta);
  zapis_udalost(null, "Import z CSV: " . $pridano . " přeprav");
  $vysledek = ["pridano" => $pridano, "preskoceno" => $preskoceno, "chyby" => $chyby];
  $znacka = "";
}

/* --- Výpis -------------------------------------------------------------- */

$hlavicka = []; $ukazka = [];
if ($znacka !== "" && is_file(cesta_importu($znacka))) {
  $data = nacti_csv(cesta_importu($znacka));
  $hlavicka = $data ? array_map("strval", $data[0]) : [];
  $ukazka = array_slice($data, 1, 5);
}

/* Odhad mapování podle názvu sloupce. Diakritika se z obou stran odstraní —
   exporty z cizích systémů bývají „Cena zakaznika" i „Cena zákazníka". */
function bez_diakritiky(string $text): string {
  $text = mb_strtolower(trim($text));
  $z = ["á","č","ď","é","ě","í","ň","ó","ř","š","ť","ú","ů","ý","ž"];
  $na = ["a","c","d","e","e","i","n","o","r","s","t","u","u","y","z"];
  return str_replace($z, $na, $text);
}

$odhad = function (string $pole) use ($hlavicka): string {
  $klicova = [
    "cislo" => ["číslo", "cislo", "shipment", "id zásilky", "ref."],
    "stav" => ["stav", "status"],
    "zakaznik" => ["zákazník", "zakaznik", "odesílatel", "objednatel", "customer"],
    "ref_zakaznika" => ["reference", "ref zákazníka", "objednávka zákazníka"],
    "dopravce" => ["dopravce", "carrier", "přepravce"],
    "nakladka_misto" => ["nakládka", "nakladka", "odkud", "loading", "místo nakládky"],
    "nakladka_adresa" => ["adresa nakládky", "nakládka adresa"],
    "nakladka_datum" => ["datum nakládky", "nakládka datum", "d_datum", "datum jízdy", "datum jizdy", "datum"],
    "nakladka_od" => ["nakládka od", "okno nakládky od"],
    "nakladka_do" => ["nakládka do", "okno nakládky do"],
    "vykladka_misto" => ["vykládka", "vykladka", "kam", "unloading", "místo vykládky"],
    "vykladka_adresa" => ["adresa vykládky", "vykládka adresa"],
    "vykladka_datum" => ["datum vykládky", "vykládka datum"],
    "vykladka_od" => ["vykládka od"],
    "vykladka_do" => ["vykládka do"],
    "zbozi" => ["zboží", "zbozi", "náklad", "komodita"],
    "hmotnost" => ["hmotnost", "váha", "vaha", "kg", "weight", "tonáž"],
    "palet" => ["palet", "pallets", "ks"],
    "ldm" => ["ldm"],
    "pozadavky" => ["požadavky", "poznámka pro dopravce"],
    "spz" => ["spz", "rz", "vozidlo"],
    "ridic_jmeno" => ["řidič", "ridic", "driver"],
    "ridic_telefon" => ["telefon"],
    "cena_zakaznik" => ["cena zákazníka", "cena zakaznika", "výnos", "tržba", "prodej", "cena pro zákazníka"],
    "cena_dopravce" => ["cena dopravce", "nákup", "cena", "náklad"],
    "poznamka" => ["poznámka", "poznamka", "note"],
  ];
  foreach ($hlavicka as $i => $nazev) {
    $n = bez_diakritiky($nazev);
    if ($n === "") continue;
    foreach ($klicova[$pole] ?? [] as $slovo) {
      $s = bez_diakritiky($slovo);
      if ($n === $s || mb_strpos($n, $s) !== false) return (string)$i;
    }
  }
  return "";
};

hlava("Import z CSV", "nastaveni");
?>
<a class="app-zpet" href="<?= chran(odkaz("nastaveni")) ?>">← Zpět na nastavení</a>
<?php hlava_stranky("Data", "Import přeprav z CSV"); ?>

<?php if ($vysledek): ?>
  <p class="vzkaz vzkaz-ok">Založeno <?= (int)$vysledek["pridano"] ?> přeprav.
    Přeskočeno <?= (int)$vysledek["preskoceno"] ?> řádků (prázdné nebo už existující číslo).</p>
  <?php foreach ($vysledek["chyby"] as $ch): ?>
    <p class="vzkaz vzkaz-chyba"><?= chran($ch) ?></p>
  <?php endforeach; ?>
  <div class="tlacitka">
    <a class="tlacitko" href="<?= chran(odkaz("prepravy")) ?>">Zobrazit přepravy</a>
    <a class="tlacitko obrys" href="<?= chran(odkaz("import")) ?>">Importovat další soubor</a>
  </div>
<?php endif; ?>

<?php if ($znacka === ""): ?>
  <form method="post" action="<?= chran(odkaz("import")) ?>" class="formular" enctype="multipart/form-data" style="max-width:680px">
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="nahrat">
    <div class="skupina" style="margin-bottom:0">
      <h2>Nahrát soubor</h2>
      <p class="app-perex">Soubor CSV s hlavičkou v prvním řádku. Oddělovač
        (středník, čárka nebo tabulátor) i kódování (UTF-8 nebo Windows-1250)
        se poznají samy. Po nahrání přiřadíte sloupce k polím přepravy.</p>
      <div class="pole">
        <label for="csv">Soubor CSV</label>
        <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
      </div>
      <button type="submit" class="tlacitko">Nahrát a přiřadit sloupce</button>
      <p class="formular-poznamka">Soubor se uloží do adresáře <span class="cislo">data/</span>,
        odkud ho web nevydá, a po importu se smaže.</p>
    </div>
  </form>

<?php elseif (!$hlavicka): ?>
  <p class="vzkaz vzkaz-chyba">Soubor se nepodařilo přečíst nebo je prázdný.</p>
  <a class="tlacitko" href="<?= chran(odkaz("import")) ?>">Zkusit znovu</a>

<?php else: ?>
  <p class="app-perex">Soubor má <?= count($hlavicka) ?> sloupců.
    Nepotřebné nechte na „— nepoužít —". Bez místa nakládky i vykládky se řádek přeskočí.</p>

  <div class="tabulka-obal" style="margin-bottom:22px">
    <table class="id-tabulka">
      <thead><tr><?php foreach ($hlavicka as $n): ?><th><?= chran($n) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($ukazka as $r): ?>
          <tr><?php foreach ($hlavicka as $i => $n): ?><td><?= chran($r[$i] ?? "") ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" action="<?= chran(odkaz("import", ["soubor" => $znacka])) ?>" class="formular" data-jednou>
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="importovat">
    <div class="skupina">
      <h2>Přiřazení sloupců</h2>
      <div class="pole-radek tri">
        <?php
        $volby_sloupcu = ["" => "— nepoužít —"];
        foreach ($hlavicka as $i => $n) $volby_sloupcu[(string)$i] = ($n !== "" ? $n : "sloupec " . ($i + 1));
        foreach (IMPORT_POLE as $klic => $popis): ?>
          <div class="pole">
            <label for="map-<?= $klic ?>"><?= chran($popis) ?></label>
            <select id="map-<?= $klic ?>" name="mapovani[<?= $klic ?>]"><?= volby($volby_sloupcu, $odhad($klic)) ?></select>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="skupina">
      <h2>Firmy</h2>
      <div class="pole-zaskrtnuti">
        <input type="checkbox" id="zakladat_firmy" name="zakladat_firmy" value="1" checked>
        <label for="zakladat_firmy">Zakládat chybějící firmy podle názvu
          <span class="napoveda">— bez zaškrtnutí zůstane přeprava bez zákazníka i dopravce, když se název nenajde</span></label>
      </div>
    </div>
    <button type="submit" class="tlacitko">Importovat</button>
    <a class="tlacitko obrys" href="<?= chran(odkaz("import")) ?>">Zrušit</a>
  </form>
<?php endif; ?>
<?php
pata();

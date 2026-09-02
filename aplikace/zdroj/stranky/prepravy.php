<?php
/* Seznam přeprav s filtry. Ceny zákazníka a marže vidí jen ten, kdo na
   ně má právo — cenu dopravce potřebuje ke své práci každý dispečer. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();
$ceny_dopravce = vidi_ceny_dopravce();

/* Hromadné akce nad označenými řádky. Co kdo smí, rozhodují stejné funkce
   jako na kartě přepravy; každá změna se zapíše do protokolu zvlášť. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "hromadne") {
  $navrat = (string)($_SERVER["REQUEST_URI"] ?? odkaz("prepravy"));
  $ids = array_values(array_unique(array_filter(array_map("intval", (array)($_POST["id"] ?? [])))));
  $co = vstup("co"); $cislo = vstup("cislo");
  $smi = ["doklady" => true, "faktura_prijata" => smi_fakturaci(), "faktura_vydana" => vidi_ceny(), "vlastnik" => smi_dispecink()];
  if (!$ids) { vzkaz("chyba", "Nejdřív označte přepravy v prvním sloupci."); presmeruj($navrat); }
  if (!isset($smi[$co])) { vzkaz("chyba", "Neznámá hromadná akce."); presmeruj($navrat); }
  if (!$smi[$co]) { vzkaz("chyba", "Na tuhle hromadnou akci nemáte právo."); presmeruj($navrat); }
  if (in_array($co, ["faktura_prijata", "faktura_vydana"], true) && $cislo === "") { vzkaz("chyba", "Zadejte číslo faktury."); presmeruj($navrat); }
  $pocet = 0; $ted = date("Y-m-d H:i:s"); $ja = uzivatel();
  foreach ($ids as $pid) {
    $p = radek("SELECT * FROM prepravy WHERE id = ? AND sablona = 0", [$pid]);
    if (!$p) continue;
    $zmeny = ["upraveno" => $ted, "upravil" => (int)$ja["id"]];
    if ($co === "doklady") {
      if ($p["doklady"] === "prijato") continue;
      $zmeny["doklady"] = "prijato"; $zmeny["doklady_kdy"] = $ted;
      zapis_udalost($pid, "Doklady přijaty (hromadně)");
    } elseif ($co === "faktura_prijata") {
      $zmeny["faktura_prijata"] = $cislo;
      zapis_udalost($pid, "Přijatá faktura " . $cislo . " (hromadně)");
    } elseif ($co === "faktura_vydana") {
      $zmeny["faktura_vydana"] = $cislo;
      if (in_array($p["stav"], ["vylozeno", "doklady"], true)) $zmeny["stav"] = "fakturovano";
      zapis_udalost($pid, "Vydaná faktura " . $cislo . " (hromadně)");
    } else {
      $zmeny["vlastnik_id"] = (int)$ja["id"];
      zapis_udalost($pid, "Má na starosti " . $ja["jmeno"] . " (hromadně)");
    }
    uprav("prepravy", $pid, $zmeny);
    $pocet++;
  }
  vzkaz("ok", "Hromadně upraveno: " . $pocet . " " . sklonuj($pocet, "přeprava", "přepravy", "přeprav") . ".");
  presmeruj($navrat);
}

$hledat   = vstup("hledat");
$stav     = vstup("stav");
$dopravce = vstup_cislo("dopravce");
$zakaznik = vstup_cislo("zakaznik");
$od       = vstup_datum("od");
$do       = vstup_datum("do");
$jen      = vstup("jen");            /* bez_dopravce | doklady | nefakturovano | dispecink | spedice */
$strana   = max(1, (int)vstup("strana", "1"));
$na_stranu = 50;

$kde = []; $parametry = [];

if ($hledat !== "") {
  $kde[] = "(p.cislo LIKE ? OR p.nakladka_misto LIKE ? OR p.vykladka_misto LIKE ?"
         . " OR p.zbozi LIKE ? OR p.ref_zakaznika LIKE ? OR p.spz LIKE ?)";
  $vzor = "%" . $hledat . "%";
  for ($i = 0; $i < 6; $i++) $parametry[] = $vzor;
}
if (isset(STAVY[$stav])) { $kde[] = "p.stav = ?"; $parametry[] = $stav; }
if ($dopravce) { $kde[] = "p.dopravce_id = ?"; $parametry[] = $dopravce; }
if ($zakaznik) { $kde[] = "p.zakaznik_id = ?"; $parametry[] = $zakaznik; }
if ($od) { $kde[] = "p.nakladka_datum >= ?"; $parametry[] = $od; }
if ($do) { $kde[] = "p.nakladka_datum <= ?"; $parametry[] = $do; }

if ($jen === "bez_dopravce") {
  $kde[] = "(p.dopravce_id IS NULL OR p.dopravce_id = 0) AND p.stav <> 'zruseno'";
} elseif ($jen === "doklady") {
  $kde[] = "p.doklady <> 'prijato' AND p.stav IN ('vylozeno','doklady','fakturovano')";
} elseif ($jen === "nefakturovano") {
  $kde[] = "(p.faktura_vydana IS NULL OR p.faktura_vydana = '') AND p.stav <> 'zruseno' AND " . JEN_SPEDICE;
} elseif ($jen === "dispecink") {
  $kde[] = JEN_DISPECINK;
} elseif ($jen === "spedice") {
  $kde[] = JEN_SPEDICE;
} elseif ($jen === "moje") {
  $kde[] = "p.vlastnik_id = ?"; $parametry[] = (int)uzivatel()["id"];
}

/* Šablony stálých linek se v evidenci neukazují — jen se z nich generuje. */
$kde[] = "p.sablona = 0";
$podminka = " WHERE " . implode(" AND ", $kde);

$celkem = (int)hodnota("SELECT COUNT(*) FROM prepravy p" . $podminka, $parametry);
$stran  = max(1, (int)ceil($celkem / $na_stranu));
if ($strana > $stran) $strana = $stran;

/* Tržba, náklad a marže jsou jen spedice: jízdy pod externím dispečinkem
   fakturuje odesílateli klient, u nás se sčítají zvlášť jako obrat vozů. */
$souhrn = radek(
  "SELECT COUNT(*) AS pocet,
          SUM(CASE WHEN " . JEN_DISPECINK . " THEN 1 ELSE 0 END) AS dispecink,
          COALESCE(SUM(CASE WHEN p.stav <> 'zruseno' AND " . JEN_SPEDICE . " THEN p.cena_zakaznik END), 0) AS trzba,
          COALESCE(SUM(CASE WHEN p.stav <> 'zruseno' AND " . JEN_SPEDICE . " THEN p.cena_dopravce END), 0) AS naklad,
          COALESCE(SUM(CASE WHEN p.stav <> 'zruseno' AND " . JEN_DISPECINK . " THEN p.cena_dopravce END), 0) AS obrat_vozu
     FROM prepravy p" . $podminka, $parametry);

$prepravy = radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev,
          (SELECT COUNT(*) FROM body b WHERE b.preprava_id = p.id) AS bodu
     FROM prepravy p
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
     LEFT JOIN firmy d ON d.id = p.dopravce_id" . $podminka . "
    ORDER BY COALESCE(p.nakladka_datum, '9999-12-31') DESC, p.id DESC
    LIMIT " . $na_stranu . " OFFSET " . (($strana - 1) * $na_stranu),
  $parametry);

$firmy_zakaznici = radky("SELECT id, nazev FROM firmy WHERE aktivni = 1 AND typ IN ('zakaznik','oboji') ORDER BY LOWER(nazev)");
$firmy_dopravci  = radky("SELECT id, nazev FROM firmy WHERE aktivni = 1 AND typ IN ('dopravce','oboji') ORDER BY LOWER(nazev)");

$seznam_firem = function (array $firmy): array {
  $ven = [];
  foreach ($firmy as $f) $ven[(string)$f["id"]] = (string)$f["nazev"];
  return $ven;
};

$adresa_seznamu = odkaz("prepravy", array_filter([
  "hledat" => $hledat, "stav" => $stav, "dopravce" => $dopravce, "zakaznik" => $zakaznik,
  "od" => $od, "do" => $do, "jen" => $jen, "strana" => $strana > 1 ? $strana : "",
]));

hlava("Přepravy", "prepravy");
hlava_stranky("Evidence", "Přepravy",
  '<a class="tlacitko" href="' . chran(odkaz("preprava", ["id" => "nova"])) . '">Nová přeprava</a>'
  . '<a class="tlacitko obrys" href="' . chran(odkaz("linky")) . '">Stálé linky</a>'
  . '<a class="tlacitko obrys" href="' . chran(odkaz("export", array_filter([
      "co" => "prepravy", "hledat" => $hledat, "stav" => $stav,
      "dopravce" => $dopravce, "zakaznik" => $zakaznik, "od" => $od, "do" => $do, "jen" => $jen,
    ]))) . '">Export CSV</a>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="prepravy">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="hledat">Hledat</label>
      <input type="search" id="hledat" name="hledat" value="<?= chran($hledat) ?>" placeholder="číslo, místo, zboží, SPZ, reference">
    </div>
    <div class="pole">
      <label for="stav">Stav</label>
      <select id="stav" name="stav"><?= volby(STAVY, $stav, "Všechny") ?></select>
    </div>
    <div class="pole">
      <label for="zakaznik">Zákazník</label>
      <select id="zakaznik" name="zakaznik"><?= volby($seznam_firem($firmy_zakaznici), (string)$zakaznik, "Všichni") ?></select>
    </div>
    <div class="pole">
      <label for="dopravce">Dopravce</label>
      <select id="dopravce" name="dopravce"><?= volby($seznam_firem($firmy_dopravci), (string)$dopravce, "Všichni") ?></select>
    </div>
  </div>
  <div class="filtr-radek" style="margin-top:12px">
    <div class="pole">
      <label for="od">Nakládka od</label>
      <input type="date" id="od" name="od" value="<?= chran($od) ?>">
    </div>
    <div class="pole">
      <label for="do">Nakládka do</label>
      <input type="date" id="do" name="do" value="<?= chran($do) ?>">
    </div>
    <div class="pole">
      <label for="jen">Jen</label>
      <select id="jen" name="jen"><?= volby([
        "bez_dopravce"  => "Bez dopravce",
        "doklady"       => "Chybějící doklady",
        "nefakturovano" => "Nevyfakturované",
        "dispecink"     => "Pod externím dispečinkem",
        "spedice"       => "Jen spedice",
        "moje"          => "Moje přepravy",
      ], $jen, "Vše") ?></select>
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Filtrovat</button>
      <a class="tlacitko obrys" href="<?= chran(odkaz("prepravy")) ?>">Zrušit</a>
    </div>
  </div>
</form>

<div class="dlazdice">
  <div class="dlazdice-polozka">
    <span class="popis">Přeprav ve výběru</span>
    <span class="hodnota"><?= (int)$souhrn["pocet"] ?></span>
    <?php if ((int)$souhrn["dispecink"]): ?>
      <span class="doplnek">z toho <?= (int)$souhrn["dispecink"] ?> pod dispečinkem · obrat vozů <?= chran(castka($souhrn["obrat_vozu"])) ?></span>
    <?php endif; ?>
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Tržba</span>
      <span class="hodnota"><?= chran(castka($souhrn["trzba"])) ?></span>
      <span class="doplnek">bez DPH · spedice, mimo zrušené</span>
    </div>
    <div class="dlazdice-polozka">
      <span class="popis">Náklad dopravců</span>
      <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
      <span class="doplnek">bez DPH · spedice</span>
    </div>
    <div class="dlazdice-polozka">
      <span class="popis">Marže</span>
      <span class="hodnota"><?= chran(castka((float)$souhrn["trzba"] - (float)$souhrn["naklad"])) ?></span>
      <span class="doplnek"><?= (float)$souhrn["trzba"] > 0
        ? chran(cislo(((float)$souhrn["trzba"] - (float)$souhrn["naklad"]) / (float)$souhrn["trzba"] * 100, 1)) . " %"
        : "—" ?></span>
    </div>
  <?php elseif ($ceny_dopravce): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Náklad dopravců</span>
      <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
      <span class="doplnek">bez DPH · spedice</span>
    </div>
  <?php endif; ?>
</div>

<?php if (!$prepravy): ?>
  <p class="prazdno">Žádná přeprava neodpovídá filtru.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka karty">
      <thead>
        <tr>
          <th class="netisknout"><input type="checkbox" data-vse aria-label="Označit všechny na stránce" style="width:16px;height:16px;accent-color:var(--zluta)"></th>
          <th>Číslo</th>
          <th>Nakládka</th>
          <th>Vykládka</th>
          <th>Zákazník</th>
          <th>Dopravce</th>
          <?php if ($ceny): ?><th class="vpravo">Cena zák.</th><?php endif; ?>
          <?php if ($ceny_dopravce): ?><th class="vpravo">Cena dopr.</th><?php endif; ?>
          <?php if ($ceny): ?><th class="vpravo">Marže</th><?php endif; ?>
          <th>Doklady</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($prepravy as $p): ?>
        <?php $marze = (float)$p["cena_zakaznik"] - (float)$p["cena_dopravce"]; ?>
        <tr<?= $p["stav"] === "zruseno" ? ' class="zrusena"' : "" ?>>
          <td class="netisknout" data-popis="Označit"><input type="checkbox" name="id[]" value="<?= (int)$p["id"] ?>" form="hromadne" aria-label="Označit <?= chran($p["cislo"]) ?>" style="width:16px;height:16px;accent-color:var(--zluta)"></td>
          <td data-popis="Číslo">
            <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
            <span class="druhotny"><?= stitek_stavu($p["stav"]) ?></span>
          </td>
          <td data-popis="Nakládka">
            <?= chran($p["nakladka_misto"] ?: "—") ?>
            <?php if ((int)$p["bodu"] > 2): ?><span class="trasa-pocet">+<?= (int)$p["bodu"] - 2 ?></span><?php endif; ?>
            <span class="druhotny"><?= chran(datum($p["nakladka_datum"])) ?> <?= chran(okno($p["nakladka_od"], $p["nakladka_do"])) ?></span>
          </td>
          <td data-popis="Vykládka">
            <?= chran($p["vykladka_misto"] ?: "—") ?>
            <span class="druhotny"><?= chran(datum($p["vykladka_datum"])) ?> <?= chran(okno($p["vykladka_od"], $p["vykladka_do"])) ?></span>
          </td>
          <td data-popis="Zákazník">
            <?= chran($p["zakaznik_nazev"] ?: "—") ?>
            <?php if ($p["ref_zakaznika"]): ?><span class="druhotny">ref. <?= chran($p["ref_zakaznika"]) ?></span><?php endif; ?>
          </td>
          <td data-popis="Dopravce">
            <?php if ($p["dopravce_nazev"]): ?>
              <?= chran($p["dopravce_nazev"]) ?>
              <?php if ($p["spz"]): ?><span class="druhotny cislo"><?= chran($p["spz"]) ?></span><?php endif; ?>
              <?php if (!empty($p["dispecink_klient_id"])): ?><span class="druhotny">externí dispečink</span><?php endif; ?>
            <?php else: ?>
              <span class="stitek stitek-zrus">nezajištěno</span>
            <?php endif; ?>
          </td>
          <?php if ($ceny): ?><td class="cislo vpravo" data-popis="Cena zák."><?= chran(castka($p["cena_zakaznik"])) ?></td><?php endif; ?>
          <?php if ($ceny_dopravce): ?><td class="cislo vpravo" data-popis="Cena dopr."><?= chran(castka($p["cena_dopravce"])) ?></td><?php endif; ?>
          <?php if ($ceny): ?>
            <td class="cislo vpravo" data-popis="Marže"><?= (!empty($p["dispecink_klient_id"]) || ($p["cena_zakaznik"] === null && $p["cena_dopravce"] === null)) ? "—" : chran(castka($marze)) ?></td>
          <?php endif; ?>
          <td data-popis="Doklady"><?= chran(DOKLADY[$p["doklady"]] ?? "Čekáme") ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form id="hromadne" method="post" action="<?= chran($adresa_seznamu) ?>" class="filtr netisknout" style="margin-top:16px"
        data-potvrdit="Provést hromadnou akci s označenými přepravami?">
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="hromadne">
    <div class="filtr-radek">
      <div class="pole">
        <label for="co">S označenými</label>
        <select id="co" name="co"><?= volby(array_filter([
          "doklady"         => "Doklady přijaty",
          "faktura_vydana"  => $ceny ? "Zapsat vydanou fakturu" : null,
          "faktura_prijata" => smi_fakturaci() ? "Zapsat přijatou fakturu dopravce" : null,
          "vlastnik"        => smi_dispecink() ? "Mám na starosti já" : null,
        ]), "doklady") ?></select>
      </div>
      <div class="pole">
        <label for="cislo">Číslo faktury <span class="napoveda">— u faktur</span></label>
        <input type="text" id="cislo" name="cislo">
      </div>
      <div class="filtr-akce"><button type="submit" class="tlacitko">Provést</button></div>
    </div>
    <p class="app-perex" style="margin:12px 0 0">Označte přepravy v prvním sloupci; zaškrtávátko v hlavičce označí všechny na stránce. Vydaná faktura přepne vyložené přepravy na fakturované.</p>
  </form>

  <?php if ($stran > 1): ?>
    <nav class="strankovani" aria-label="Stránkování">
      <?php
        $adresa = function (int $c) use ($hledat, $stav, $dopravce, $zakaznik, $od, $do, $jen) {
          return odkaz("prepravy", array_filter([
            "hledat" => $hledat, "stav" => $stav, "dopravce" => $dopravce,
            "zakaznik" => $zakaznik, "od" => $od, "do" => $do, "jen" => $jen, "strana" => $c,
          ]));
        };
      ?>
      <?php if ($strana > 1): ?><a href="<?= chran($adresa($strana - 1)) ?>">← Předchozí</a><?php endif; ?>
      <span>Strana <?= $strana ?> z <?= $stran ?> · <?= $celkem ?> přeprav</span>
      <?php if ($strana < $stran): ?><a href="<?= chran($adresa($strana + 1)) ?>">Další →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php
pata();

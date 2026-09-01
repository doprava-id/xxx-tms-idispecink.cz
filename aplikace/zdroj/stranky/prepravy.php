<?php
/* Seznam přeprav s filtry. Ceny zákazníka a marže vidí jen ten, kdo na
   ně má právo — cenu dopravce potřebuje ke své práci každý dispečer. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$hledat   = vstup("hledat");
$stav     = vstup("stav");
$dopravce = vstup_cislo("dopravce");
$zakaznik = vstup_cislo("zakaznik");
$od       = vstup_datum("od");
$do       = vstup_datum("do");
$jen      = vstup("jen");            /* bez_dopravce | doklady | nefakturovano */
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
  $kde[] = "(p.faktura_vydana IS NULL OR p.faktura_vydana = '') AND p.stav <> 'zruseno'";
}

$podminka = $kde ? " WHERE " . implode(" AND ", $kde) : "";

$celkem = (int)hodnota("SELECT COUNT(*) FROM prepravy p" . $podminka, $parametry);
$stran  = max(1, (int)ceil($celkem / $na_stranu));
if ($strana > $stran) $strana = $stran;

$souhrn = radek(
  "SELECT COUNT(*) AS pocet,
          COALESCE(SUM(CASE WHEN p.stav <> 'zruseno' THEN p.cena_zakaznik END), 0) AS trzba,
          COALESCE(SUM(CASE WHEN p.stav <> 'zruseno' THEN p.cena_dopravce END), 0) AS naklad
     FROM prepravy p" . $podminka, $parametry);

$prepravy = radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev
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

hlava("Přepravy", "prepravy");
hlava_stranky("Evidence", "Přepravy",
  '<a class="tlacitko" href="' . chran(odkaz("preprava", ["id" => "nova"])) . '">Nová přeprava</a>'
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
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Tržba</span>
      <span class="hodnota"><?= chran(castka($souhrn["trzba"])) ?></span>
      <span class="doplnek">bez DPH, mimo zrušené</span>
    </div>
    <div class="dlazdice-polozka">
      <span class="popis">Náklad dopravců</span>
      <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
      <span class="doplnek">bez DPH</span>
    </div>
    <div class="dlazdice-polozka">
      <span class="popis">Marže</span>
      <span class="hodnota"><?= chran(castka((float)$souhrn["trzba"] - (float)$souhrn["naklad"])) ?></span>
      <span class="doplnek"><?= (float)$souhrn["trzba"] > 0
        ? chran(cislo(((float)$souhrn["trzba"] - (float)$souhrn["naklad"]) / (float)$souhrn["trzba"] * 100, 1)) . " %"
        : "—" ?></span>
    </div>
  <?php else: ?>
    <div class="dlazdice-polozka">
      <span class="popis">Náklad dopravců</span>
      <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
      <span class="doplnek">bez DPH</span>
    </div>
  <?php endif; ?>
</div>

<?php if (!$prepravy): ?>
  <p class="prazdno">Žádná přeprava neodpovídá filtru.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <thead>
        <tr>
          <th>Číslo</th>
          <th>Nakládka</th>
          <th>Vykládka</th>
          <th>Zákazník</th>
          <th>Dopravce</th>
          <?php if ($ceny): ?><th class="vpravo">Zákazník</th><?php endif; ?>
          <th class="vpravo">Dopravce</th>
          <?php if ($ceny): ?><th class="vpravo">Marže</th><?php endif; ?>
          <th>Doklady</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($prepravy as $p): ?>
        <?php $marze = (float)$p["cena_zakaznik"] - (float)$p["cena_dopravce"]; ?>
        <tr<?= $p["stav"] === "zruseno" ? ' class="zrusena"' : "" ?>>
          <td>
            <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
            <span class="druhotny"><?= stitek_stavu($p["stav"]) ?></span>
          </td>
          <td>
            <?= chran($p["nakladka_misto"] ?: "—") ?>
            <span class="druhotny"><?= chran(datum($p["nakladka_datum"])) ?> <?= chran(okno($p["nakladka_od"], $p["nakladka_do"])) ?></span>
          </td>
          <td>
            <?= chran($p["vykladka_misto"] ?: "—") ?>
            <span class="druhotny"><?= chran(datum($p["vykladka_datum"])) ?> <?= chran(okno($p["vykladka_od"], $p["vykladka_do"])) ?></span>
          </td>
          <td>
            <?= chran($p["zakaznik_nazev"] ?: "—") ?>
            <?php if ($p["ref_zakaznika"]): ?><span class="druhotny">ref. <?= chran($p["ref_zakaznika"]) ?></span><?php endif; ?>
          </td>
          <td>
            <?php if ($p["dopravce_nazev"]): ?>
              <?= chran($p["dopravce_nazev"]) ?>
              <?php if ($p["spz"]): ?><span class="druhotny cislo"><?= chran($p["spz"]) ?></span><?php endif; ?>
            <?php else: ?>
              <span class="stitek stitek-zrus">nezajištěno</span>
            <?php endif; ?>
          </td>
          <?php if ($ceny): ?><td class="cislo vpravo"><?= chran(castka($p["cena_zakaznik"])) ?></td><?php endif; ?>
          <td class="cislo vpravo"><?= chran(castka($p["cena_dopravce"])) ?></td>
          <?php if ($ceny): ?>
            <td class="cislo vpravo"><?= ($p["cena_zakaznik"] === null && $p["cena_dopravce"] === null) ? "—" : chran(castka($marze)) ?></td>
          <?php endif; ?>
          <td><?= chran(DOKLADY[$p["doklady"]] ?? "Čekáme") ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

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

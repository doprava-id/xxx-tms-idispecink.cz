<?php
/* Fakturace a přehledy — obrat, marže a podklady k fakturaci za období.

   Podklad po dopravcích potřebuje každý dispečer (týdenní fakturace za
   odjeté přepravy). Obchodní strana — tržba, marže a podklad po
   zákaznících — je jen pro toho, kdo na ceny zákazníka má právo. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$pohled = vstup("pohled", "dopravci");
if (!in_array($pohled, ["dopravci", "zakaznici", "chybi"], true)) $pohled = "dopravci";
if ($pohled === "zakaznici" && !$ceny) $pohled = "dopravci";

$od = vstup_datum("od") ?: date("Y-m-01");
$do = vstup_datum("do") ?: date("Y-m-t");

/* Období se počítá podle data vykládky — fakturuje se za odjeté přepravy.
   Bez data vykládky rozhoduje datum nakládky. */
const OBDOBI_SLOUPEC = "COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum)";

$kde = OBDOBI_SLOUPEC . " BETWEEN ? AND ? AND p.stav <> 'zruseno' AND p.sablona = 0";
$parametry = [$od, $do];

$souhrn = radek(
  "SELECT COUNT(*) AS pocet,
          COALESCE(SUM(p.cena_zakaznik), 0) AS trzba,
          COALESCE(SUM(p.cena_dopravce), 0) AS naklad
     FROM prepravy p WHERE " . $kde, $parametry);

$marze = (float)$souhrn["trzba"] - (float)$souhrn["naklad"];

$rychle = [
  "Tento týden"  => [date("Y-m-d", strtotime("monday this week")), date("Y-m-d", strtotime("sunday this week"))],
  "Minulý týden" => [date("Y-m-d", strtotime("monday last week")), date("Y-m-d", strtotime("sunday last week"))],
  "Tento měsíc"  => [date("Y-m-01"), date("Y-m-t")],
  "Minulý měsíc" => [date("Y-m-01", strtotime("first day of last month")), date("Y-m-t", strtotime("last day of last month"))],
];

hlava("Fakturace", "fakturace");
hlava_stranky("Podklady", "Fakturace a přehledy",
  '<a class="tlacitko obrys" href="' . chran(odkaz("export", ["co" => "fakturace", "od" => $od, "do" => $do])) . '">Export CSV</a>'
  . '<button type="button" class="tlacitko obrys" onclick="window.print()">Vytisknout</button>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="fakturace">
  <input type="hidden" name="pohled" value="<?= chran($pohled) ?>">
  <div class="filtr-radek">
    <div class="pole">
      <label for="od">Období od</label>
      <input type="date" id="od" name="od" value="<?= chran($od) ?>">
    </div>
    <div class="pole">
      <label for="do">Období do</label>
      <input type="date" id="do" name="do" value="<?= chran($do) ?>">
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Zobrazit</button>
    </div>
    <div class="filtr-akce" style="flex-wrap:wrap">
      <?php foreach ($rychle as $popis => $rozsah): ?>
        <a class="tlacitko obrys" href="<?= chran(odkaz("fakturace", ["pohled" => $pohled, "od" => $rozsah[0], "do" => $rozsah[1]])) ?>"><?= chran($popis) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="app-perex" style="margin:12px 0 0">Období se počítá podle data vykládky; u přeprav bez vykládky podle nakládky. Zrušené se nepočítají.</p>
</form>

<div class="dlazdice">
  <div class="dlazdice-polozka">
    <span class="popis">Přeprav</span>
    <span class="hodnota"><?= (int)$souhrn["pocet"] ?></span>
    <span class="doplnek"><?= chran(datum($od)) ?> – <?= chran(datum($do)) ?></span>
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Tržba</span>
      <span class="hodnota"><?= chran(castka($souhrn["trzba"])) ?></span>
      <span class="doplnek">bez DPH</span>
    </div>
  <?php endif; ?>
  <div class="dlazdice-polozka">
    <span class="popis">Náklad dopravců</span>
    <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
    <span class="doplnek">bez DPH</span>
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Marže</span>
      <span class="hodnota"><?= chran(castka($marze)) ?></span>
      <span class="doplnek"><?= (float)$souhrn["trzba"] > 0 ? chran(cislo($marze / (float)$souhrn["trzba"] * 100, 1)) . " %" : "—" ?></span>
    </div>
  <?php endif; ?>
</div>

<nav class="tlacitka netisknout" style="margin-top:0;margin-bottom:20px">
  <a class="tlacitko<?= $pohled === "dopravci" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "dopravci", "od" => $od, "do" => $do])) ?>">Podle dopravců</a>
  <?php if ($ceny): ?>
    <a class="tlacitko<?= $pohled === "zakaznici" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "zakaznici", "od" => $od, "do" => $do])) ?>">Podle zákazníků</a>
  <?php endif; ?>
  <a class="tlacitko<?= $pohled === "chybi" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "chybi", "od" => $od, "do" => $do])) ?>">Chybějící údaje</a>
</nav>

<?php if ($pohled === "chybi"):
  $chybne = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev
       FROM prepravy p
       LEFT JOIN firmy z ON z.id = p.zakaznik_id
       LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE " . $kde . "
        AND (p.cena_dopravce IS NULL OR (p.dopravce_id IS NULL OR p.dopravce_id = 0)"
        . ($ceny ? " OR p.cena_zakaznik IS NULL" : "") . "
            OR p.doklady <> 'prijato')
      ORDER BY " . OBDOBI_SLOUPEC . ", p.id", $parametry);
?>
  <h2>Chybějící údaje</h2>
  <p class="app-perex">Přepravy z období, u kterých něco brání fakturaci.</p>
  <?php if (!$chybne): ?>
    <p class="prazdno">V období nechybí nic.</p>
  <?php else: ?>
    <div class="tabulka-obal">
      <table class="id-tabulka">
        <thead><tr><th>Číslo</th><th>Trasa</th><th>Dopravce</th><th>Zákazník</th><th>Co chybí</th></tr></thead>
        <tbody>
        <?php foreach ($chybne as $p):
          $chybi = [];
          if (empty($p["dopravce_id"]))       $chybi[] = "dopravce";
          if ($p["cena_dopravce"] === null)   $chybi[] = "cena dopravce";
          if ($ceny && $p["cena_zakaznik"] === null) $chybi[] = "cena zákazníka";
          if ($p["doklady"] !== "prijato")    $chybi[] = "doklady";
        ?>
          <tr>
            <td><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a></td>
            <td><?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?>
              <span class="druhotny"><?= chran(datum($p["vykladka_datum"] ?: $p["nakladka_datum"])) ?></span></td>
            <td><?= chran($p["dopravce_nazev"] ?: "—") ?></td>
            <td><?= chran($p["zakaznik_nazev"] ?: "—") ?></td>
            <td><?= chran(implode(", ", $chybi)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php else:
  $po_firmach = $pohled === "dopravci"
    ? radky("SELECT f.id, f.nazev, COUNT(*) AS pocet, COALESCE(SUM(p.cena_dopravce), 0) AS soucet
               FROM prepravy p JOIN firmy f ON f.id = p.dopravce_id
              WHERE " . $kde . " GROUP BY f.id, f.nazev ORDER BY soucet DESC", $parametry)
    : radky("SELECT f.id, f.nazev, COUNT(*) AS pocet, COALESCE(SUM(p.cena_zakaznik), 0) AS soucet
               FROM prepravy p JOIN firmy f ON f.id = p.zakaznik_id
              WHERE " . $kde . " GROUP BY f.id, f.nazev ORDER BY soucet DESC", $parametry);
?>
  <h2><?= $pohled === "dopravci" ? "Podklad k fakturaci dopravců" : "Podklad k fakturaci zákazníkům" ?></h2>
  <?php if (!$po_firmach): ?>
    <p class="prazdno">V období není žádná přeprava s přiřazenou firmou.</p>
  <?php else: ?>
    <?php foreach ($po_firmach as $f):
      $polozky = $pohled === "dopravci"
        ? radky("SELECT p.* FROM prepravy p WHERE " . $kde . " AND p.dopravce_id = ?
                  ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [$f["id"]]))
        : radky("SELECT p.* FROM prepravy p WHERE " . $kde . " AND p.zakaznik_id = ?
                  ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [$f["id"]]));
    ?>
      <section style="margin-bottom:28px">
        <div class="app-hlava" style="margin-bottom:10px">
          <div>
            <h3 style="margin:0"><a href="<?= chran(odkaz("firma", ["id" => $f["id"]])) ?>"><?= chran($f["nazev"]) ?></a></h3>
            <p class="app-perex" style="margin:2px 0 0"><?= (int)$f["pocet"] ?> přeprav · <b class="cislo"><?= chran(castka($f["soucet"])) ?></b> bez DPH</p>
          </div>
        </div>
        <div class="tabulka-obal">
          <table class="id-tabulka">
            <thead>
              <tr>
                <th>Číslo</th><th>Nakládka</th><th>Vykládka</th><th>Zboží</th>
                <th>Doklady</th>
                <th class="vpravo"><?= $pohled === "dopravci" ? "Cena dopravce" : "Cena zákazníka" ?></th>
                <th><?= $pohled === "dopravci" ? "Přijatá faktura" : "Vydaná faktura" ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($polozky as $p): ?>
              <tr>
                <td><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a></td>
                <td><?= chran($p["nakladka_misto"] ?: "—") ?><span class="druhotny"><?= chran(datum($p["nakladka_datum"])) ?></span></td>
                <td><?= chran($p["vykladka_misto"] ?: "—") ?><span class="druhotny"><?= chran(datum($p["vykladka_datum"])) ?></span></td>
                <td><?= chran($p["zbozi"] ?: "—") ?></td>
                <td><?= chran(DOKLADY[$p["doklady"]] ?? "—") ?></td>
                <td class="cislo vpravo"><?= chran(castka($pohled === "dopravci" ? $p["cena_dopravce"] : $p["cena_zakaznik"])) ?></td>
                <td class="cislo"><?= chran(($pohled === "dopravci" ? $p["faktura_prijata"] : $p["faktura_vydana"]) ?: "—") ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
<?php
pata();

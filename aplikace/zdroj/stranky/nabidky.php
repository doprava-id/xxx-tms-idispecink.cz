<?php
/* Nabídky — seznam, úspěšnost v číslech a důvody, proč neprošly.
   Období se počítá podle data vzniku nabídky. Dlaždice a přehledy po
   zákaznících ignorují filtr stavu, aby bylo vidět celé okno. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_ceny();

$stav     = vstup("stav");
$zakaznik = vstup_cislo("zakaznik");
$hledat   = vstup("hledat");
$od = vstup_datum("od") ?: date("Y-m-01", strtotime("first day of -2 months"));
$do = vstup_datum("do") ?: date("Y-m-d");

$kde = ["DATE(n.vytvoreno) BETWEEN ? AND ?"];
$parametry = [$od, $do];
if ($zakaznik) { $kde[] = "n.zakaznik_id = ?"; $parametry[] = $zakaznik; }
if ($hledat !== "") {
  $kde[] = "(n.cislo LIKE ? OR n.nakladka_misto LIKE ? OR n.vykladka_misto LIKE ? OR n.zbozi LIKE ? OR n.ref_zakaznika LIKE ?)";
  for ($i = 0; $i < 5; $i++) $parametry[] = "%" . $hledat . "%";
}
$podminka_bez_stavu = " WHERE " . implode(" AND ", $kde);
$kde_seznam = $kde; $parametry_seznam = $parametry;
if (isset(STAVY_NABIDKY[$stav])) { $kde_seznam[] = "n.stav = ?"; $parametry_seznam[] = $stav; }
$podminka = " WHERE " . implode(" AND ", $kde_seznam);

$souhrn = radek(
  "SELECT COUNT(*) AS pocet,
          SUM(CASE WHEN n.stav = 'prijata' THEN 1 ELSE 0 END) AS prijato,
          SUM(CASE WHEN n.stav = 'neprosla' THEN 1 ELSE 0 END) AS neproslo,
          SUM(CASE WHEN n.stav = 'otevrena' THEN 1 ELSE 0 END) AS otevreno,
          COALESCE(SUM(n.cena), 0) AS hodnota,
          COALESCE(SUM(CASE WHEN n.stav = 'prijata' THEN n.cena END), 0) AS hodnota_prijata
     FROM nabidky n" . $podminka_bez_stavu, $parametry);
$rozhodnuto = (int)$souhrn["prijato"] + (int)$souhrn["neproslo"];
$uspesnost = $rozhodnuto ? (int)$souhrn["prijato"] / $rozhodnuto * 100 : null;

$nabidky = radky(
  "SELECT n.*, z.nazev AS zakaznik_nazev, p.cislo AS preprava_cislo
     FROM nabidky n
     LEFT JOIN firmy z ON z.id = n.zakaznik_id
     LEFT JOIN prepravy p ON p.id = n.preprava_id" . $podminka . "
    ORDER BY n.id DESC LIMIT 300", $parametry_seznam);

$po_zakaznicich = radky(
  "SELECT z.id, z.nazev, COUNT(*) AS pocet,
          SUM(CASE WHEN n.stav = 'prijata' THEN 1 ELSE 0 END) AS prijato,
          SUM(CASE WHEN n.stav = 'neprosla' THEN 1 ELSE 0 END) AS neproslo,
          COALESCE(SUM(CASE WHEN n.stav = 'prijata' THEN n.cena END), 0) AS hodnota
     FROM nabidky n LEFT JOIN firmy z ON z.id = n.zakaznik_id" . $podminka_bez_stavu . "
    GROUP BY z.id, z.nazev ORDER BY pocet DESC, hodnota DESC", $parametry);

$duvody = radky(
  "SELECT n.duvod, COUNT(*) AS pocet FROM nabidky n" . $podminka_bez_stavu . " AND n.stav = 'neprosla'
    GROUP BY n.duvod ORDER BY pocet DESC", $parametry);

$zakaznici = radky("SELECT id, nazev FROM firmy WHERE typ IN ('zakaznik','oboji') AND aktivni = 1 ORDER BY LOWER(nazev)");
$volby_zakazniku = []; foreach ($zakaznici as $z) $volby_zakazniku[(string)$z["id"]] = (string)$z["nazev"];

hlava("Nabídky", "nabidky");
hlava_stranky("Obchod", "Nabídky",
  '<a class="tlacitko" href="' . chran(odkaz("nabidka", ["id" => "nova"])) . '">Nová nabídka</a>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="nabidky">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="hledat">Hledat</label>
      <input type="search" id="hledat" name="hledat" value="<?= chran($hledat) ?>" placeholder="číslo, místo, zboží, reference">
    </div>
    <div class="pole">
      <label for="stav">Stav</label>
      <select id="stav" name="stav"><?= volby(STAVY_NABIDKY, $stav, "Všechny") ?></select>
    </div>
    <div class="pole">
      <label for="zakaznik">Zákazník</label>
      <select id="zakaznik" name="zakaznik"><?= volby($volby_zakazniku, (string)$zakaznik, "Všichni") ?></select>
    </div>
    <div class="pole">
      <label for="od">Vznik od</label>
      <input type="date" id="od" name="od" value="<?= chran($od) ?>">
    </div>
    <div class="pole">
      <label for="do">Vznik do</label>
      <input type="date" id="do" name="do" value="<?= chran($do) ?>">
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Filtrovat</button>
      <a class="tlacitko obrys" href="<?= chran(odkaz("nabidky")) ?>">Zrušit</a>
    </div>
  </div>
</form>

<div class="dlazdice">
  <div class="dlazdice-polozka">
    <span class="popis">Nabídek v období</span>
    <span class="hodnota"><?= (int)$souhrn["pocet"] ?></span>
    <span class="doplnek"><?= (int)$souhrn["otevreno"] ?> <?= sklonuj((int)$souhrn["otevreno"], "otevřená", "otevřené", "otevřených") ?> · hodnota <?= chran(castka($souhrn["hodnota"])) ?></span>
  </div>
  <div class="dlazdice-polozka">
    <span class="popis">Přijato</span>
    <span class="hodnota"><?= (int)$souhrn["prijato"] ?></span>
    <span class="doplnek">v hodnotě <?= chran(castka($souhrn["hodnota_prijata"])) ?> bez DPH</span>
  </div>
  <div class="dlazdice-polozka">
    <span class="popis">Neprošlo</span>
    <span class="hodnota"><?= (int)$souhrn["neproslo"] ?></span>
    <span class="doplnek"><?= $duvody ? "nejčastěji: " . chran(mb_strtolower(DUVODY_NABIDKY[$duvody[0]["duvod"]] ?? "bez důvodu")) : "—" ?></span>
  </div>
  <div class="dlazdice-polozka">
    <span class="popis">Úspěšnost</span>
    <span class="hodnota"><?= $uspesnost === null ? "—" : chran(cislo($uspesnost, 0)) . " %" ?></span>
    <span class="doplnek">z <?= $rozhodnuto ?> rozhodnutých</span>
  </div>
</div>

<?php if (!$nabidky): ?>
  <p class="prazdno">Žádná nabídka neodpovídá filtru. Poptávku zapište tlačítkem Nová nabídka.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <thead>
        <tr><th>Číslo</th><th>Zákazník</th><th>Trasa</th><th>Termín</th><th>Zboží</th><th class="vpravo">Cena</th><th>Stav</th><th>Vznik</th></tr>
      </thead>
      <tbody>
      <?php foreach ($nabidky as $n): ?>
        <tr>
          <td><a href="<?= chran(odkaz("nabidka", ["id" => $n["id"]])) ?>" class="cislo"><?= chran($n["cislo"]) ?></a>
            <?php if ($n["ref_zakaznika"]): ?><span class="druhotny">ref. <?= chran($n["ref_zakaznika"]) ?></span><?php endif; ?></td>
          <td><?= chran($n["zakaznik_nazev"] ?: "—") ?><?php if ($n["kontakt_jmeno"]): ?><span class="druhotny"><?= chran($n["kontakt_jmeno"]) ?></span><?php endif; ?></td>
          <td><?= chran($n["nakladka_misto"] ?: "?") ?> → <?= chran($n["vykladka_misto"] ?: "?") ?><?php if ($n["km"]): ?><span class="druhotny"><?= (int)$n["km"] ?> km</span><?php endif; ?></td>
          <td><?= chran(datum($n["nakladka_datum"])) ?><span class="druhotny"><?= chran(okno($n["nakladka_od"], $n["nakladka_do"])) ?></span></td>
          <td><?= chran($n["zbozi"] ?: "—") ?></td>
          <td class="cislo vpravo"><?= chran(castka($n["cena"])) ?></td>
          <td><?= stitek_nabidky($n["stav"]) ?>
            <?php if ($n["stav"] === "neprosla"): ?><span class="druhotny"><?= chran(DUVODY_NABIDKY[$n["duvod"]] ?? "") ?></span><?php endif; ?>
            <?php if ($n["preprava_cislo"]): ?><span class="druhotny"><a href="<?= chran(odkaz("preprava", ["id" => $n["preprava_id"]])) ?>">přeprava <?= chran($n["preprava_cislo"]) ?></a></span><?php endif; ?></td>
          <td><?= chran(datum($n["vytvoreno"])) ?><?php if ($n["odeslana"]): ?><span class="druhotny">odeslána <?= chran(datum($n["odeslana"])) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($po_zakaznicich): ?>
  <div class="app-sloupce stejne" style="margin-top:36px">
    <div>
      <h2>Po zákaznících</h2>
      <div class="tabulka-obal">
        <table class="id-tabulka" style="min-width:520px">
          <thead><tr><th>Zákazník</th><th class="vpravo">Nabídek</th><th class="vpravo">Přijato</th><th class="vpravo">Úspěšnost</th><th class="vpravo">Hodnota přijatých</th></tr></thead>
          <tbody>
          <?php foreach ($po_zakaznicich as $z): $r = (int)$z["prijato"] + (int)$z["neproslo"]; ?>
            <tr>
              <td><?= $z["id"] ? '<a href="' . chran(odkaz("firma", ["id" => $z["id"]])) . '">' . chran($z["nazev"]) . '</a>' : "bez zákazníka" ?></td>
              <td class="cislo vpravo"><?= (int)$z["pocet"] ?></td>
              <td class="cislo vpravo"><?= (int)$z["prijato"] ?></td>
              <td class="cislo vpravo"><?= $r ? chran(cislo((int)$z["prijato"] / $r * 100, 0)) . " %" : "—" ?></td>
              <td class="cislo vpravo"><?= chran(castka($z["hodnota"])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div>
      <h2>Proč neprošly</h2>
      <?php if (!$duvody): ?>
        <p class="prazdno">V období žádná nabídka neprošla — nebo se důvod nezapsal.</p>
      <?php else: ?>
        <ul class="protokol">
          <?php foreach ($duvody as $d): ?>
            <li><b><?= chran(DUVODY_NABIDKY[$d["duvod"]] ?? "Bez důvodu") ?></b> <time><?= (int)$d["pocet"] ?>× · <?= chran(cislo((int)$d["pocet"] / max(1, (int)$souhrn["neproslo"]) * 100, 0)) ?> % neúspěšných</time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php
pata();

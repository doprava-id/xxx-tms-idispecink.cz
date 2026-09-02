<?php
/* Rychlé hledání — jedno pole v hlavičce. Číslo zásilky, místo, firma,
   SPZ, reference, zboží, řidič. Přesná shoda čísla přepravy vede rovnou
   na kartu; jinak se ukáže, co všechno sedí. Nabídky vidí jen ten, kdo
   vidí ceny. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$q = trim(vstup("q"));
$vzor = "%" . $q . "%";

$prepravy = []; $firmy = []; $mista = []; $nabidky = [];
if ($q !== "") {
  $presna = radek("SELECT id FROM prepravy WHERE sablona = 0 AND cislo = ?", [$q]);
  if ($presna) presmeruj(odkaz("preprava", ["id" => $presna["id"]]));

  $prepravy = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev
       FROM prepravy p
       LEFT JOIN firmy z ON z.id = p.zakaznik_id
       LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE p.sablona = 0 AND (p.cislo LIKE ? OR p.nakladka_misto LIKE ? OR p.vykladka_misto LIKE ?
         OR p.ref_zakaznika LIKE ? OR p.spz LIKE ? OR p.zbozi LIKE ? OR p.ridic_jmeno LIKE ?
         OR z.nazev LIKE ? OR d.nazev LIKE ?)
      ORDER BY COALESCE(p.nakladka_datum, '') DESC, p.id DESC LIMIT 50",
    array_fill(0, 9, $vzor));
  $firmy = radky("SELECT * FROM firmy WHERE nazev LIKE ? OR ico LIKE ? OR mesto LIKE ? OR kontakt_jmeno LIKE ? ORDER BY aktivni DESC, LOWER(nazev) LIMIT 30", array_fill(0, 4, $vzor));
  $mista = radky("SELECT * FROM mista WHERE aktivni = 1 AND (nazev LIKE ? OR mesto LIKE ? OR ulice LIKE ?) ORDER BY LOWER(nazev) LIMIT 30", array_fill(0, 3, $vzor));
  if (vidi_ceny()) {
    $nabidky = radky(
      "SELECT n.*, z.nazev AS zakaznik_nazev FROM nabidky n LEFT JOIN firmy z ON z.id = n.zakaznik_id
        WHERE n.cislo LIKE ? OR n.nakladka_misto LIKE ? OR n.vykladka_misto LIKE ? OR n.ref_zakaznika LIKE ? OR z.nazev LIKE ?
        ORDER BY n.id DESC LIMIT 30", array_fill(0, 5, $vzor));
  }
}
$celkem = count($prepravy) + count($firmy) + count($mista) + count($nabidky);

hlava("Hledání", "");
hlava_stranky("Hledání", $q === "" ? "Rychlé hledání" : "„" . $q . "“");
?>
<form method="get" action="index.php" class="filtr" role="search">
  <input type="hidden" name="s" value="hledat">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="q">Co hledáte</label>
      <input type="search" id="q" name="q" value="<?= chran($q) ?>" placeholder="číslo zásilky, místo, firma, SPZ, reference, zboží, řidič" autofocus>
    </div>
    <div class="filtr-akce"><button type="submit" class="tlacitko">Hledat</button></div>
  </div>
  <p class="app-perex" style="margin:12px 0 0">Přesné číslo přepravy otevře rovnou její kartu. Zkratka <span class="cislo">/</span> skočí do hledání v hlavičce, <span class="cislo">Alt+N</span> založí novou přepravu.</p>
</form>

<?php if ($q === ""): ?>
  <p class="prazdno">Napište, co hledáte.</p>
<?php elseif ($celkem === 0): ?>
  <p class="prazdno">Nic neodpovídá „<?= chran($q) ?>“.</p>
<?php else: ?>
  <?php if ($prepravy): ?>
    <h2>Přepravy <span class="napoveda" style="text-transform:none;letter-spacing:0"><?= count($prepravy) ?><?= count($prepravy) === 50 ? "+, zpřesněte hledání" : "" ?></span></h2>
    <div class="tabulka-obal" style="margin-bottom:28px">
      <table class="id-tabulka karty">
        <thead><tr><th>Číslo</th><th>Trasa</th><th>Nakládka</th><th>Zákazník</th><th>Dopravce</th><th>Stav</th></tr></thead>
        <tbody>
        <?php foreach ($prepravy as $p): ?>
          <tr<?= $p["stav"] === "zruseno" ? ' class="zrusena"' : "" ?>>
            <td data-popis="Číslo"><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a><?php if ($p["ref_zakaznika"]): ?><span class="druhotny">ref. <?= chran($p["ref_zakaznika"]) ?></span><?php endif; ?></td>
            <td data-popis="Trasa"><?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?><?php if ($p["zbozi"]): ?><span class="druhotny"><?= chran($p["zbozi"]) ?></span><?php endif; ?></td>
            <td data-popis="Nakládka"><?= chran(datum($p["nakladka_datum"])) ?></td>
            <td data-popis="Zákazník"><?= chran($p["zakaznik_nazev"] ?: "—") ?></td>
            <td data-popis="Dopravce"><?= chran($p["dopravce_nazev"] ?: "—") ?><?php if ($p["spz"]): ?><span class="druhotny cislo"><?= chran($p["spz"]) ?></span><?php endif; ?></td>
            <td data-popis="Stav"><?= stitek_stavu($p["stav"]) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="app-sloupce stejne">
    <div>
      <?php if ($firmy): ?>
        <h2>Firmy <span class="napoveda" style="text-transform:none;letter-spacing:0"><?= count($firmy) ?></span></h2>
        <ul class="protokol" style="margin-bottom:28px">
          <?php foreach ($firmy as $f): ?>
            <li><a href="<?= chran(odkaz("firma", ["id" => $f["id"]])) ?>"><?= chran($f["nazev"]) ?></a> — <?= chran(TYPY_FIREM[$f["typ"]] ?? "") ?><?= (int)$f["aktivni"] === 1 ? "" : " · vyřazená" ?>
              <time><?= chran($f["mesto"] ?: "") ?><?= $f["ico"] ? " · IČO " . chran($f["ico"]) : "" ?><?= $f["kontakt_jmeno"] ? " · " . chran($f["kontakt_jmeno"]) : "" ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($nabidky): ?>
        <h2>Nabídky <span class="napoveda" style="text-transform:none;letter-spacing:0"><?= count($nabidky) ?></span></h2>
        <ul class="protokol">
          <?php foreach ($nabidky as $n): ?>
            <li><a href="<?= chran(odkaz("nabidka", ["id" => $n["id"]])) ?>" class="cislo"><?= chran($n["cislo"]) ?></a> — <?= chran($n["nakladka_misto"] ?: "?") ?> → <?= chran($n["vykladka_misto"] ?: "?") ?> <?= stitek_nabidky($n["stav"]) ?>
              <time><?= chran($n["zakaznik_nazev"] ?: "bez zákazníka") ?> · <?= chran(datum($n["vytvoreno"])) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($mista): ?>
        <h2>Místa <span class="napoveda" style="text-transform:none;letter-spacing:0"><?= count($mista) ?></span></h2>
        <ul class="protokol">
          <?php foreach ($mista as $m): ?>
            <li><a href="<?= chran(odkaz("misto", ["id" => $m["id"]])) ?>"><?= chran($m["nazev"]) ?></a>
              <time><?= chran(trim((string)$m["ulice"] . ", " . (string)$m["mesto"], ", ")) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php
pata();

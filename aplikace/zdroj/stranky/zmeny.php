<?php
/* Přehled změn — jeden společný protokol napříč systémem, jen pro správce.
   Události zapisují všechny stránky přes zapis_udalost(); tady se dají
   projít podle člověka, období a textu. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_spravce();

$uzivatel = vstup_cislo("uzivatel");
$hledat   = vstup("hledat");
$od = vstup_datum("od") ?: date("Y-m-d", strtotime("-14 days"));
$do = vstup_datum("do") ?: date("Y-m-d");
$strana = max(1, (int)vstup("strana", "1"));
$na_stranu = 100;

$kde = ["DATE(u.kdy) BETWEEN ? AND ?"]; $parametry = [$od, $do];
if ($uzivatel) { $kde[] = "u.uzivatel_id = ?"; $parametry[] = $uzivatel; }
if ($hledat !== "") { $kde[] = "u.text LIKE ?"; $parametry[] = "%" . $hledat . "%"; }
$podminka = " WHERE " . implode(" AND ", $kde);

$celkem = (int)hodnota("SELECT COUNT(*) FROM udalosti u" . $podminka, $parametry);
$stran = max(1, (int)ceil($celkem / $na_stranu));
if ($strana > $stran) $strana = $stran;

$zmeny = radky(
  "SELECT u.*, z.jmeno AS kdo, p.cislo AS preprava_cislo
     FROM udalosti u
     LEFT JOIN uzivatele z ON z.id = u.uzivatel_id
     LEFT JOIN prepravy p ON p.id = u.preprava_id" . $podminka . "
    ORDER BY u.id DESC LIMIT " . $na_stranu . " OFFSET " . (($strana - 1) * $na_stranu), $parametry);

$uzivatele = radky("SELECT id, jmeno FROM uzivatele ORDER BY LOWER(jmeno)");
$volby_uzivatelu = []; foreach ($uzivatele as $x) $volby_uzivatelu[(string)$x["id"]] = (string)$x["jmeno"];
$adresa = function (int $c) use ($uzivatel, $hledat, $od, $do) {
  return odkaz("zmeny", array_filter(["uzivatel" => $uzivatel, "hledat" => $hledat, "od" => $od, "do" => $do, "strana" => $c]));
};

hlava("Přehled změn", "nastaveni");
?>
<a class="app-zpet" href="<?= chran(odkaz("nastaveni")) ?>">← Zpět na nastavení</a>
<?php hlava_stranky("Protokol", "Přehled změn"); ?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="zmeny">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="hledat">Hledat v textu</label>
      <input type="search" id="hledat" name="hledat" value="<?= chran($hledat) ?>" placeholder="číslo přepravy, firma, co se stalo">
    </div>
    <div class="pole">
      <label for="uzivatel">Kdo</label>
      <select id="uzivatel" name="uzivatel"><?= volby($volby_uzivatelu, (string)$uzivatel, "Kdokoli") ?></select>
    </div>
    <div class="pole"><label for="od">Od</label><input type="date" id="od" name="od" value="<?= chran($od) ?>"></div>
    <div class="pole"><label for="do">Do</label><input type="date" id="do" name="do" value="<?= chran($do) ?>"></div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Filtrovat</button>
      <a class="tlacitko obrys" href="<?= chran(odkaz("zmeny")) ?>">Zrušit</a>
    </div>
  </div>
</form>

<?php if (!$zmeny): ?>
  <p class="prazdno">V období nic neodpovídá.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <thead><tr><th>Kdy</th><th>Kdo</th><th>Co se stalo</th><th>Přeprava</th></tr></thead>
      <tbody>
      <?php foreach ($zmeny as $z): ?>
        <tr>
          <td><span class="cislo"><?= chran(datum_cas($z["kdy"])) ?></span></td>
          <td><?= chran($z["kdo"] ?: "systém") ?></td>
          <td><?= chran($z["text"]) ?></td>
          <td><?php if ($z["preprava_id"]): ?><a href="<?= chran(odkaz("preprava", ["id" => $z["preprava_id"]])) ?>" class="cislo"><?= chran($z["preprava_cislo"] ?: "#" . (int)$z["preprava_id"]) ?></a><?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <nav class="strankovani" aria-label="Stránkování">
    <?php if ($strana > 1): ?><a href="<?= chran($adresa($strana - 1)) ?>">← Novější</a><?php endif; ?>
    <span>Strana <?= $strana ?> z <?= $stran ?> · <?= $celkem ?> záznamů</span>
    <?php if ($strana < $stran): ?><a href="<?= chran($adresa($strana + 1)) ?>">Starší →</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php
pata();

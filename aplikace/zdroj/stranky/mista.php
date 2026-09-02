<?php
/* Adresář míst — společný číselník nakládek a vykládek. Firma je u místa
   nepovinná: do jednoho skladu se vozí pro víc zákazníků a překladiště
   není nikoho. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$hledat = vstup("hledat");
$neaktivni = vstup("neaktivni") !== "";

$kde = []; $parametry = [];
if ($hledat !== "") {
  $kde[] = "(m.nazev LIKE ? OR m.mesto LIKE ? OR m.ulice LIKE ? OR m.kontakt_jmeno LIKE ? OR f.nazev LIKE ?)";
  for ($i = 0; $i < 5; $i++) $parametry[] = "%" . $hledat . "%";
}
if (!$neaktivni) $kde[] = "m.aktivni = 1";
$podminka = $kde ? " WHERE " . implode(" AND ", $kde) : "";

$mista = radky(
  "SELECT m.*, f.nazev AS firma_nazev,
          (SELECT COUNT(*) FROM body b WHERE b.misto_id = m.id) AS pouziti
     FROM mista m LEFT JOIN firmy f ON f.id = m.firma_id" . $podminka . "
    ORDER BY LOWER(m.nazev)", $parametry);

hlava("Místa", "mista");
hlava_stranky("Adresář", "Místa nakládky a vykládky",
  '<a class="tlacitko" href="' . chran(odkaz("misto", ["id" => "nove"])) . '">Nové místo</a>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="mista">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="hledat">Hledat</label>
      <input type="search" id="hledat" name="hledat" value="<?= chran($hledat) ?>" placeholder="název, obec, ulice, kontakt, firma">
    </div>
    <div class="pole">
      <label for="neaktivni">Zobrazit</label>
      <select id="neaktivni" name="neaktivni">
        <option value="">Jen aktivní</option>
        <option value="1"<?= $neaktivni ? " selected" : "" ?>>Včetně vyřazených</option>
      </select>
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Filtrovat</button>
      <a class="tlacitko obrys" href="<?= chran(odkaz("mista")) ?>">Zrušit</a>
    </div>
  </div>
</form>

<?php if (!$mista): ?>
  <p class="prazdno">Zatím žádné místo. Založte sklady a rampy, na které jezdíte opakovaně — u bodu trasy je pak vyberete jedním kliknutím.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <thead><tr><th>Místo</th><th>Adresa</th><th>Firma</th><th>Kontakt</th><th>Otevřeno</th><th class="vpravo">Použito</th></tr></thead>
      <tbody>
      <?php foreach ($mista as $m): ?>
        <tr<?= (int)$m["aktivni"] === 1 ? "" : ' class="zrusena"' ?>>
          <td>
            <a href="<?= chran(odkaz("misto", ["id" => $m["id"]])) ?>"><?= chran($m["nazev"]) ?></a>
            <?php if ($m["poznamka"]): ?><span class="druhotny"><?= chran(mb_substr((string)$m["poznamka"], 0, 80)) ?></span><?php endif; ?>
          </td>
          <td><?= chran($m["mesto"] ?: "—") ?><?php if ($m["ulice"]): ?><span class="druhotny"><?= chran($m["ulice"]) ?><?= $m["psc"] ? ", " . chran($m["psc"]) : "" ?></span><?php endif; ?></td>
          <td><?= chran($m["firma_nazev"] ?: "—") ?></td>
          <td><?= chran($m["kontakt_jmeno"] ?: "—") ?><?php if ($m["kontakt_telefon"]): ?><span class="druhotny"><?= chran($m["kontakt_telefon"]) ?></span><?php endif; ?></td>
          <td><?= chran($m["oteviraci_doba"] ?: "—") ?></td>
          <td class="cislo vpravo"><?= (int)$m["pouziti"] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="app-perex" style="margin-top:12px">Celkem <?= count($mista) ?> míst.</p>
<?php endif; ?>
<?php
pata();

<?php
/* Adresář firem — zákazníci i dopravci v jednom seznamu.
   Jedna firma může být obojí, proto je typ jen příznak, ne dva seznamy. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$hledat  = vstup("hledat");
$typ     = vstup("typ");
$neaktivni = vstup("neaktivni") !== "";
$jen_doklady = vstup("neaktivni") === "doklady";   /* dopravci s končícími doklady */

$kde = [];
$parametry = [];

if ($hledat !== "") {
  $kde[] = "(f.nazev LIKE ? OR f.ico LIKE ? OR f.mesto LIKE ? OR f.kontakt_jmeno LIKE ?)";
  $vzor = "%" . $hledat . "%";
  array_push($parametry, $vzor, $vzor, $vzor, $vzor);
}
if ($typ === "dispecink") {
  $kde[] = "f.dispecink = 1";
} elseif (isset(TYPY_FIREM[$typ])) {
  /* „oboji" musí vyjít i při hledání zákazníků a při hledání dopravců. */
  $kde[] = "(f.typ = ? OR f.typ = 'oboji')";
  $parametry[] = $typ;
}
if (!$neaktivni) $kde[] = "f.aktivni = 1";

$podminka = $kde ? " WHERE " . implode(" AND ", $kde) : "";

$firmy = radky(
  "SELECT f.*,
          (SELECT COUNT(*) FROM prepravy p WHERE p.dopravce_id = f.id OR p.zakaznik_id = f.id) AS prepravy_pocet
     FROM firmy f" . $podminka . "
    ORDER BY LOWER(f.nazev)",
  $parametry
);
if ($jen_doklady) $firmy = array_values(array_filter($firmy, fn($f) => (int)$f["aktivni"] === 1 && upozorneni_dopravce($f)));

hlava("Firmy", "firmy");
hlava_stranky("Adresář", "Firmy",
  '<a class="tlacitko" href="' . chran(odkaz("firma", ["id" => "nova"])) . '">Nová firma</a>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="firmy">
  <div class="filtr-radek">
    <div class="pole siroke">
      <label for="hledat">Hledat</label>
      <input type="search" id="hledat" name="hledat" value="<?= chran($hledat) ?>" placeholder="název, IČO, město, kontakt">
    </div>
    <div class="pole">
      <label for="typ">Typ</label>
      <select id="typ" name="typ"><?= volby(TYPY_FIREM + ["dispecink" => "Klienti dispečinku"], $typ, "Všechny") ?></select>
    </div>
    <div class="pole">
      <label for="neaktivni">Zobrazit</label>
      <select id="neaktivni" name="neaktivni">
        <option value="">Jen aktivní</option>
        <option value="1"<?= $neaktivni && !$jen_doklady ? " selected" : "" ?>>Včetně vyřazených</option>
        <option value="doklady"<?= $jen_doklady ? " selected" : "" ?>>Dopravci s končícími doklady</option>
      </select>
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Filtrovat</button>
      <a class="tlacitko obrys" href="<?= chran(odkaz("firmy")) ?>">Zrušit</a>
    </div>
  </div>
</form>

<?php if (!$firmy): ?>
  <p class="prazdno">Žádná firma neodpovídá filtru.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka karty">
      <thead>
        <tr>
          <th>Firma</th>
          <th>Typ</th>
          <th>IČO / DIČ</th>
          <th>Kontakt</th>
          <th>Prověření</th>
          <th class="vpravo">Přeprav</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($firmy as $f): ?>
        <tr<?= (int)$f["aktivni"] === 1 ? "" : ' class="zrusena"' ?>>
          <td data-popis="Firma">
            <a href="<?= chran(odkaz("firma", ["id" => $f["id"]])) ?>"><?= chran($f["nazev"]) ?></a>
            <?php if ($f["mesto"]): ?><span class="druhotny"><?= chran($f["mesto"]) ?></span><?php endif; ?>
            <?php if ((int)$f["aktivni"] !== 1): ?><span class="druhotny">vyřazená</span><?php endif; ?>
          </td>
          <td data-popis="Typ">
            <?= chran(TYPY_FIREM[$f["typ"]] ?? "—") ?>
            <?php if ((int)$f["dispecink"] === 1): ?><span class="druhotny">klient dispečinku</span><?php endif; ?>
          </td>
          <td data-popis="IČO / DIČ">
            <span class="cislo"><?= chran($f["ico"] ?: "—") ?></span>
            <?php if ($f["dic"]): ?><span class="druhotny cislo"><?= chran($f["dic"]) ?></span><?php endif; ?>
          </td>
          <td data-popis="Kontakt">
            <?= chran($f["kontakt_jmeno"] ?: "—") ?>
            <?php if ($f["kontakt_telefon"]): ?>
              <span class="druhotny"><a href="tel:<?= chran(preg_replace('/\s+/', '', (string)$f["kontakt_telefon"])) ?>"><?= chran($f["kontakt_telefon"]) ?></a></span>
            <?php endif; ?>
          </td>
          <td data-popis="Prověření">
            <?php
              if ($f["typ"] === "zakaznik") {
                echo "—";
              } else {
                $splneno = (int)$f["prov_registry"] + (int)$f["prov_opravneni"] + (int)$f["prov_pojisteni"]
                         + (int)$f["prov_doklady"] + (int)$f["prov_reference"];
                $trida = $splneno === 5 ? "hotovo" : ($splneno === 0 ? "zrus" : "bezi");
                echo '<span class="stitek stitek-' . $trida . '">' . $splneno . ' z 5</span>';
                foreach (upozorneni_dopravce($f) as $u) {
                  echo '<span class="druhotny" style="color:var(--' . ($u["vazne"] ? "chyba" : "pozor") . '-text)">' . chran($u["text"]) . '</span>';
                }
              }
            ?>
          </td>
          <td class="cislo vpravo" data-popis="Přeprav"><?= (int)$f["prepravy_pocet"] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="app-perex" style="margin-top:12px">Celkem <?= count($firmy) ?> firem.</p>
<?php endif; ?>
<?php
pata();

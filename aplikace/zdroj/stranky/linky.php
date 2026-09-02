<?php
/* Stálé linky — šablony přeprav a generování týdne dopředu.

   Šablona je obyčejná přeprava s příznakem sablona = 1; v seznamech ani
   na tabuli se neukazuje. Generování je na kliknutí, ne na pozadí:
   v pátek se připraví příští týden a projde se. Státní svátky se
   přeskočí a řekne se to. Má-li linka stálého dopravce, vznikne přeprava
   rovnou objednaná — odeslání objednávky ale zůstává na dispečerovi. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DNY_ZKRATKY = [1 => "Po", 2 => "Út", 3 => "St", 4 => "Čt", 5 => "Pá", 6 => "So", 7 => "Ne"];

$sablony = radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev
     FROM prepravy p
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
     LEFT JOIN firmy d ON d.id = p.dopravce_id
    WHERE p.sablona = 1 ORDER BY LOWER(COALESCE(p.linka_nazev, '')), p.id");

/* Příští pondělí jako výchozí týden. */
$vychozi_pondeli = date("Y-m-d", strtotime("monday next week"));

/* --- Generování --------------------------------------------------------- */

$vysledek = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "generovat") {
  $pondeli = date_create(vstup_datum("tyden") ?: $vychozi_pondeli);
  $pondeli->modify("monday this week");

  $vybrane = array_map("intval", (array)($_POST["sablona"] ?? []));
  $zalozeno = 0; $preskoceno_svatek = []; $preskoceno_existuje = 0; $bez_dnu = 0;

  foreach ($sablony as $s) {
    if ($vybrane && !in_array((int)$s["id"], $vybrane, true)) continue;
    $dny = array_filter(array_map("intval", explode(",", (string)$s["linka_dny"])));
    if (!$dny) { $bez_dnu++; continue; }

    $body_sablony = body_prepravy((int)$s["id"]);
    $puvodni_datum = $s["nakladka_datum"] ?: null;

    foreach ($dny as $den) {
      $datum = (clone $pondeli)->modify("+" . ($den - 1) . " days")->format("Y-m-d");

      $svatek = statni_svatek($datum);
      if ($svatek) { $preskoceno_svatek[] = datum($datum) . " (" . $svatek . ")"; continue; }

      $uz = hodnota("SELECT COUNT(*) FROM prepravy WHERE zdroj_sablony_id = ? AND nakladka_datum = ? AND sablona = 0",
        [(int)$s["id"], $datum]);
      if ($uz) { $preskoceno_existuje++; continue; }

      /* Čistý řádek přepravy bez sloupců z JOINu — ty by INSERT shodily. */
      $nova = radek("SELECT * FROM prepravy WHERE id = ?", [(int)$s["id"]]);
      unset($nova["id"]);
      $nova["cislo"]     = dalsi_cislo();
      $nova["sablona"]   = 0;
      $nova["linka_nazev"] = "";
      $nova["linka_dny"]   = "";
      $nova["zdroj_sablony_id"] = (int)$s["id"];
      $nova["stav"]      = $s["dopravce_id"] ? "objednana" : "nova";
      $nova["doklady"]   = "ceka";
      $nova["objednavka_datum"] = null;
      $nova["faktura_vydana"]   = "";
      $nova["faktura_prijata"]  = "";
      $nova["vytvoreno"] = date("Y-m-d H:i:s");
      $nova["upraveno"]  = date("Y-m-d H:i:s");
      $nova["vytvoril"]  = (int)uzivatel()["id"];
      $id = vloz("prepravy", $nova);

      /* Posun dat bodů: má-li šablona datum, zachová se odstup mezi body
         (vícedenní jízda). Bez data dostanou všechny body cílový den. */
      if ($puvodni_datum) {
        $posun = (int)round((strtotime($datum) - strtotime($puvodni_datum)) / 86400);
        zkopiruj_body((int)$s["id"], $id, $posun);
      } else {
        zkopiruj_body((int)$s["id"], $id);
        dotaz("UPDATE body SET datum = ? WHERE preprava_id = ?", [$datum, $id]);
        prepocitej_trasu($id);
      }
      zapis_udalost($id, "Založeno z linky " . ($s["linka_nazev"] ?: $s["cislo"]) . " na " . datum($datum));
      $zalozeno++;
    }
  }

  $vysledek = compact("zalozeno", "preskoceno_svatek", "preskoceno_existuje", "bez_dnu");
  $vysledek["tyden"] = $pondeli->format("Y-m-d");
}

hlava("Stálé linky", "prepravy");
hlava_stranky("Přepravy", "Stálé linky");
?>

<?php if ($vysledek): ?>
  <p class="vzkaz vzkaz-ok">Založeno <?= (int)$vysledek["zalozeno"] ?> přeprav na týden od <?= chran(datum($vysledek["tyden"])) ?>.
    <?php if ($vysledek["preskoceno_existuje"]): ?> <?= (int)$vysledek["preskoceno_existuje"] ?> jízd už existovalo a nezakládaly se znovu.<?php endif; ?>
    <?php if ($vysledek["bez_dnu"]): ?> <?= (int)$vysledek["bez_dnu"] ?> linek nemá zaškrtnuté dny.<?php endif; ?>
    <a href="<?= chran(odkaz("dispecink", ["tyden" => $vysledek["tyden"]])) ?>">Zobrazit na tabuli</a>.</p>
  <?php if ($vysledek["preskoceno_svatek"]): ?>
    <p class="vzkaz vzkaz-pozor">Přeskočené státní svátky: <?= chran(implode(", ", array_unique($vysledek["preskoceno_svatek"]))) ?>.
      Pokud se ten den jezdí, založte přepravu ručně.</p>
  <?php endif; ?>
<?php endif; ?>

<p class="app-perex" style="max-width:72ch">Linka je obyčejná přeprava označená jako šablona — otevřete ji, nastavte trasu, dopravce a ceny a zaškrtněte dny v týdnu. Tady se z ní pak jedním kliknutím připraví celý týden.</p>

<?php if (!$sablony): ?>
  <p class="prazdno">Zatím žádná linka. Otevřete existující přepravu, která se opakuje, a v části „Stálá linka" ji označte jako šablonu.</p>
<?php else: ?>
  <form method="post" action="<?= chran(odkaz("linky")) ?>" data-potvrdit="Založit přepravy z označených linek? Už existující jízdy se přeskočí.">
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="generovat">
    <div class="tabulka-obal">
      <table class="id-tabulka">
        <thead><tr><th></th><th>Linka</th><th>Trasa</th><th>Dny</th><th>Zákazník</th><th>Dopravce</th><th class="vpravo">Cena dopravce</th></tr></thead>
        <tbody>
        <?php foreach ($sablony as $s):
          $dny = array_filter(array_map("intval", explode(",", (string)$s["linka_dny"])));
          $body = body_prepravy((int)$s["id"]);
        ?>
          <tr>
            <td><input type="checkbox" name="sablona[]" value="<?= (int)$s["id"] ?>" checked style="width:18px;height:18px;accent-color:var(--zluta)" aria-label="Generovat <?= chran($s["linka_nazev"] ?: $s["cislo"]) ?>"></td>
            <td><a href="<?= chran(odkaz("preprava", ["id" => $s["id"]])) ?>"><?= chran($s["linka_nazev"] ?: "bez názvu") ?></a><span class="druhotny cislo"><?= chran($s["cislo"]) ?></span></td>
            <td><?= chran(popis_trasy($body)) ?><span class="druhotny"><?= count($body) ?> <?= count($body) === 1 ? "bod" : (count($body) < 5 ? "body" : "bodů") ?></span></td>
            <td><?= $dny ? chran(implode(" ", array_map(fn($d) => DNY_ZKRATKY[$d] ?? "", $dny))) : '<span class="stitek stitek-zrus">bez dnů</span>' ?></td>
            <td><?= chran($s["zakaznik_nazev"] ?: "—") ?></td>
            <td><?= chran($s["dopravce_nazev"] ?: "—") ?></td>
            <td class="cislo vpravo"><?= chran(castka($s["cena_dopravce"])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="filtr" style="margin-top:20px">
      <div class="filtr-radek">
        <div class="pole">
          <label for="tyden">Týden od pondělí</label>
          <input type="date" id="tyden" name="tyden" value="<?= chran($vychozi_pondeli) ?>">
        </div>
        <div class="filtr-akce">
          <button type="submit" class="tlacitko">Založit přepravy na týden</button>
        </div>
      </div>
      <p class="app-perex" style="margin:12px 0 0">Zakládá se jen to, co ještě neexistuje — kliknout podruhé nevadí. Státní svátky se přeskočí a napíše se to.</p>
    </div>
  </form>
<?php endif; ?>
<?php
pata();

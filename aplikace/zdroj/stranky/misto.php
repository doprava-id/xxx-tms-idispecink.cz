<?php
/* Detail místa v adresáři. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$id = vstup("id");
$nove = ($id === "nove" || $id === "");
$misto = null;

if (!$nove) {
  $misto = radek("SELECT * FROM mista WHERE id = ?", [(int)$id]);
  if (!$misto) {
    vzkaz("chyba", "Místo nenalezeno.");
    presmeruj(odkaz("mista"));
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "ulozit") {
  $data = [
    "nazev"           => vstup("nazev"),
    "firma_id"        => vstup_cislo("firma_id") ?: null,
    "ulice"           => vstup("ulice"),
    "mesto"           => vstup("mesto"),
    "psc"             => vstup("psc"),
    "kontakt_jmeno"   => vstup("kontakt_jmeno"),
    "kontakt_telefon" => vstup("kontakt_telefon"),
    "oteviraci_doba"  => vstup("oteviraci_doba"),
    "poznamka"        => vstup("poznamka"),
    "aktivni"         => vstup_ano_ne("aktivni"),
  ];
  if ($data["nazev"] === "" || $data["mesto"] === "") {
    vzkaz("chyba", "Vyplňte název místa a obec.");
  } elseif ($nove) {
    $data["vytvoreno"] = date("Y-m-d H:i:s");
    $novy = vloz("mista", $data);
    zapis_udalost(null, "Založeno místo " . $data["nazev"]);
    vzkaz("ok", "Místo založeno.");
    presmeruj(odkaz("misto", ["id" => $novy]));
  } else {
    uprav("mista", (int)$misto["id"], $data);
    vzkaz("ok", "Změny uloženy.");
    presmeruj(odkaz("misto", ["id" => $misto["id"]]));
  }
}

$h = function (string $klic, string $vychozi = "") use ($misto) {
  $v = $misto[$klic] ?? null;
  return ($v === null || $v === "") ? $vychozi : (string)$v;
};

$firmy = radky("SELECT id, nazev FROM firmy WHERE aktivni = 1 OR id = ? ORDER BY LOWER(nazev)", [(int)($misto["firma_id"] ?? 0)]);
$volby_firem = [];
foreach ($firmy as $f) $volby_firem[(string)$f["id"]] = (string)$f["nazev"];

$posledni = $misto ? radky(
  "SELECT p.id, p.cislo, p.stav, b.druh, b.datum FROM body b JOIN prepravy p ON p.id = b.preprava_id
    WHERE b.misto_id = ? AND p.sablona = 0 ORDER BY COALESCE(b.datum, '') DESC, b.id DESC LIMIT 10",
  [(int)$misto["id"]]) : [];

hlava($nove ? "Nové místo" : $h("nazev"), "mista");
?>
<a class="app-zpet" href="<?= chran(odkaz("mista")) ?>">← Zpět na adresář míst</a>
<?php hlava_stranky("Místo", $nove ? "Nové místo" : $h("nazev")); ?>

<div class="app-sloupce">
  <div>
    <form method="post" action="<?= chran(odkaz("misto", ["id" => $nove ? "nove" : $misto["id"]])) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="ulozit">
      <div class="skupina">
        <h2>Místo</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="nazev">Název <span class="napoveda">— jak mu říkáte, např. „Sklad Hrušov"</span></label>
            <input type="text" id="nazev" name="nazev" value="<?= chran($h("nazev")) ?>" required>
          </div>
          <div class="pole">
            <label for="firma_id">Firma <span class="napoveda">— nepovinné</span></label>
            <select id="firma_id" name="firma_id"><?= volby($volby_firem, $h("firma_id"), "— nikoho, společné —") ?></select>
          </div>
        </div>
        <div class="pole">
          <label for="ulice">Ulice a číslo, brána, rampa</label>
          <input type="text" id="ulice" name="ulice" value="<?= chran($h("ulice")) ?>">
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="psc">PSČ</label>
            <input type="text" id="psc" name="psc" value="<?= chran($h("psc")) ?>">
          </div>
          <div class="pole">
            <label for="mesto">Obec</label>
            <input type="text" id="mesto" name="mesto" value="<?= chran($h("mesto")) ?>" required>
          </div>
        </div>
      </div>
      <div class="skupina">
        <h2>Na místě</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="kontakt_jmeno">Kontaktní osoba</label>
            <input type="text" id="kontakt_jmeno" name="kontakt_jmeno" value="<?= chran($h("kontakt_jmeno")) ?>">
          </div>
          <div class="pole">
            <label for="kontakt_telefon">Telefon</label>
            <input type="tel" id="kontakt_telefon" name="kontakt_telefon" value="<?= chran($h("kontakt_telefon")) ?>">
          </div>
          <div class="pole">
            <label for="oteviraci_doba">Otevírací doba</label>
            <input type="text" id="oteviraci_doba" name="oteviraci_doba" value="<?= chran($h("oteviraci_doba")) ?>" placeholder="Po–Pá 6–14">
          </div>
        </div>
        <div class="pole">
          <label for="poznamka">Poznámka <span class="napoveda">— ohlásit se na vrátnici, rampa jen pro plachty…</span></label>
          <textarea id="poznamka" name="poznamka" style="min-height:80px"><?= chran($h("poznamka")) ?></textarea>
        </div>
        <div class="pole-zaskrtnuti">
          <input type="checkbox" id="aktivni" name="aktivni" value="1"<?= ($nove || (int)$h("aktivni") === 1) ? " checked" : "" ?>>
          <label for="aktivni">Místo je aktivní <span class="napoveda">— vyřazené se nenabízí u bodů trasy</span></label>
        </div>
      </div>
      <button type="submit" class="tlacitko"><?= $nove ? "Založit místo" : "Uložit změny" ?></button>
    </form>
  </div>
  <div>
    <?php if ($posledni): ?>
      <div class="formular">
        <div class="skupina" style="margin-bottom:0">
          <h2>Poslední jízdy přes toto místo</h2>
          <ul class="protokol">
            <?php foreach ($posledni as $p): ?>
              <li>
                <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
                — <?= chran(nazev_druhu($p["druh"])) ?>
                <time><?= chran(datum($p["datum"])) ?> · <?= chran(nazev_stavu($p["stav"])) ?></time>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
pata();

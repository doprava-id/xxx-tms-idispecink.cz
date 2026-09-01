<?php
/* Detail přepravy — jediné místo, kde se zásilka zakládá a mění.

   Vozidlo a řidič se vybírají z číselníku dopravce, ale zároveň se
   opisují do textových polí. Objednávka pak drží to, co bylo v okamžiku
   vystavení dohodnuté, i když se karta vozidla později změní. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$id = vstup("id");
$nova = ($id === "nova" || $id === "");
$preprava = null;

if (!$nova) {
  $preprava = radek("SELECT * FROM prepravy WHERE id = ?", [(int)$id]);
  if (!$preprava) {
    vzkaz("chyba", "Přeprava nenalezena.");
    presmeruj(odkaz("prepravy"));
  }
}

/* --- Zápis -------------------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($akce === "ulozit") {
    $dopravce_id = vstup_cislo("dopravce_id") ?: null;
    $vozidlo_id  = vstup_cislo("vozidlo_id") ?: null;
    $ridic_id    = vstup_cislo("ridic_id") ?: null;

    $spz     = vstup("spz");
    $r_jmeno = vstup("ridic_jmeno");
    $r_tel   = vstup("ridic_telefon");

    /* Opis z číselníku, když se pole nechalo prázdné. */
    if ($vozidlo_id && $spz === "") {
      $v = radek("SELECT spz FROM vozidla WHERE id = ?", [$vozidlo_id]);
      if ($v) $spz = (string)$v["spz"];
    }
    if ($ridic_id && $r_jmeno === "") {
      $r = radek("SELECT jmeno, telefon FROM ridici WHERE id = ?", [$ridic_id]);
      if ($r) {
        $r_jmeno = (string)$r["jmeno"];
        if ($r_tel === "") $r_tel = (string)$r["telefon"];
      }
    }

    $data = [
      "stav"          => isset(STAVY[vstup("stav")]) ? vstup("stav") : "nova",
      "zakaznik_id"   => vstup_cislo("zakaznik_id") ?: null,
      "ref_zakaznika" => vstup("ref_zakaznika"),

      "dopravce_id"   => $dopravce_id,
      "vozidlo_id"    => $vozidlo_id,
      "ridic_id"      => $ridic_id,
      "spz"           => $spz,
      "ridic_jmeno"   => $r_jmeno,
      "ridic_telefon" => $r_tel,

      "nakladka_misto"  => vstup("nakladka_misto"),
      "nakladka_adresa" => vstup("nakladka_adresa"),
      "nakladka_datum"  => vstup_datum("nakladka_datum"),
      "nakladka_od"     => vstup("nakladka_od"),
      "nakladka_do"     => vstup("nakladka_do"),

      "vykladka_misto"  => vstup("vykladka_misto"),
      "vykladka_adresa" => vstup("vykladka_adresa"),
      "vykladka_datum"  => vstup_datum("vykladka_datum"),
      "vykladka_od"     => vstup("vykladka_od"),
      "vykladka_do"     => vstup("vykladka_do"),

      "zbozi"       => vstup("zbozi"),
      "hmotnost"    => vstup_cislo("hmotnost"),
      "palet"       => vstup_cislo("palet"),
      "ldm"         => vstup_castka("ldm"),
      "typ_vozidla" => isset(TYPY_VOZIDEL[vstup("typ_vozidla")]) ? vstup("typ_vozidla") : "plachta",
      "pozadavky"   => vstup("pozadavky"),

      "cena_dopravce" => vstup_castka("cena_dopravce"),

      "doklady"          => isset(DOKLADY[vstup("doklady")]) ? vstup("doklady") : "ceka",
      "doklady_poznamka" => vstup("doklady_poznamka"),
      "faktura_prijata"  => vstup("faktura_prijata"),

      "poznamka"          => vstup("poznamka"),
      "poznamka_dopravci" => vstup("poznamka_dopravci"),

      "upraveno" => date("Y-m-d H:i:s"),
    ];

    /* Obchodní stranu zapisuje jen ten, kdo na ni má právo — jinak by
       uložení formuláře cenu zákazníka smazalo. */
    if ($ceny) {
      $data["cena_zakaznik"]  = vstup_castka("cena_zakaznik");
      $data["faktura_vydana"] = vstup("faktura_vydana");
    }

    if ($data["nakladka_misto"] === "" && $data["vykladka_misto"] === "") {
      vzkaz("chyba", "Vyplňte aspoň místo nakládky nebo vykládky.");
    } elseif ($nova) {
      $data["cislo"]     = dalsi_cislo();
      $data["vytvoreno"] = date("Y-m-d H:i:s");
      $data["vytvoril"]  = (int)uzivatel()["id"];
      $novy = vloz("prepravy", $data);
      zapis_udalost($novy, "Přeprava " . $data["cislo"] . " založena");
      vzkaz("ok", "Přeprava " . $data["cislo"] . " založena.");
      presmeruj(odkaz("preprava", ["id" => $novy]));
    } else {
      if ($preprava["stav"] !== $data["stav"]) {
        zapis_udalost((int)$preprava["id"],
          "Stav změněn: " . nazev_stavu($preprava["stav"]) . " → " . nazev_stavu($data["stav"]));
      }
      if ((int)$preprava["dopravce_id"] !== (int)$dopravce_id) {
        $nazev = $dopravce_id ? (string)hodnota("SELECT nazev FROM firmy WHERE id = ?", [$dopravce_id]) : "nikdo";
        zapis_udalost((int)$preprava["id"], "Dopravce: " . $nazev);
      }
      uprav("prepravy", (int)$preprava["id"], $data);
      vzkaz("ok", "Změny uloženy.");
      presmeruj(odkaz("preprava", ["id" => $preprava["id"]]));
    }

  } elseif ($akce === "zrusit" && $preprava) {
    uprav("prepravy", (int)$preprava["id"], ["stav" => "zruseno", "upraveno" => date("Y-m-d H:i:s")]);
    zapis_udalost((int)$preprava["id"], "Přeprava zrušena");
    vzkaz("pozor", "Přeprava zrušena.");
    presmeruj(odkaz("preprava", ["id" => $preprava["id"]]));

  } elseif ($akce === "kopie" && $preprava) {
    $kopie = $preprava;
    unset($kopie["id"]);
    $kopie["cislo"]     = dalsi_cislo();
    $kopie["stav"]      = "nova";
    $kopie["doklady"]   = "ceka";
    $kopie["faktura_vydana"]   = "";
    $kopie["faktura_prijata"]  = "";
    $kopie["objednavka_datum"] = null;
    $kopie["vytvoreno"] = date("Y-m-d H:i:s");
    $kopie["upraveno"]  = date("Y-m-d H:i:s");
    $kopie["vytvoril"]  = (int)uzivatel()["id"];
    $novy = vloz("prepravy", $kopie);
    zapis_udalost($novy, "Založeno jako kopie přepravy " . $preprava["cislo"]);
    vzkaz("ok", "Vytvořena kopie " . $kopie["cislo"] . ". Zkontrolujte termíny.");
    presmeruj(odkaz("preprava", ["id" => $novy]));

  } elseif ($akce === "smazat" && $preprava) {
    if (!je_spravce()) {
      vzkaz("chyba", "Mazat přepravy může jen správce.");
      presmeruj(odkaz("preprava", ["id" => $preprava["id"]]));
    }
    dotaz("DELETE FROM udalosti WHERE preprava_id = ?", [(int)$preprava["id"]]);
    dotaz("DELETE FROM prepravy WHERE id = ?", [(int)$preprava["id"]]);
    vzkaz("ok", "Přeprava " . $preprava["cislo"] . " smazána.");
    presmeruj(odkaz("prepravy"));
  }
}

/* --- Výpis -------------------------------------------------------------- */

$h = function (string $klic, string $vychozi = "") use ($preprava) {
  $hodnota = $preprava[$klic] ?? null;
  return ($hodnota === null || $hodnota === "") ? $vychozi : (string)$hodnota;
};

$zakaznici = radky("SELECT id, nazev FROM firmy WHERE typ IN ('zakaznik','oboji') AND (aktivni = 1 OR id = ?) ORDER BY LOWER(nazev)",
  [(int)($preprava["zakaznik_id"] ?? 0)]);
$dopravci  = radky("SELECT id, nazev FROM firmy WHERE typ IN ('dopravce','oboji') AND (aktivni = 1 OR id = ?) ORDER BY LOWER(nazev)",
  [(int)($preprava["dopravce_id"] ?? 0)]);

$dopravce_id = (int)($preprava["dopravce_id"] ?? 0);
$vozidla = $dopravce_id ? radky("SELECT id, spz, typ FROM vozidla WHERE firma_id = ? AND aktivni = 1 ORDER BY spz", [$dopravce_id]) : [];
$ridici  = $dopravce_id ? radky("SELECT id, jmeno FROM ridici WHERE firma_id = ? AND aktivni = 1 ORDER BY LOWER(jmeno)", [$dopravce_id]) : [];

$udalosti = $preprava ? radky(
  "SELECT u.*, z.jmeno AS kdo FROM udalosti u
     LEFT JOIN uzivatele z ON z.id = u.uzivatel_id
    WHERE u.preprava_id = ? ORDER BY u.id DESC LIMIT 30", [(int)$preprava["id"]]) : [];

$do_voleb = function (array $zaznamy, string $sloupec): array {
  $ven = [];
  foreach ($zaznamy as $z) $ven[(string)$z["id"]] = (string)$z[$sloupec];
  return $ven;
};

$akce_hlavy = "";
if (!$nova) {
  if ($dopravce_id) {
    $akce_hlavy .= '<a class="tlacitko" href="' . chran(odkaz("objednavka", ["id" => $preprava["id"]]))
                . '" target="_blank" rel="noopener">Objednávka přepravy</a>';
  }
  $akce_hlavy .= '<form method="post" action="' . chran(odkaz("preprava", ["id" => $preprava["id"]])) . '" style="display:inline">'
    . pole_token() . '<input type="hidden" name="akce" value="kopie">'
    . '<button type="submit" class="tlacitko obrys">Vytvořit kopii</button></form>';
}

hlava($nova ? "Nová přeprava" : "Přeprava " . $h("cislo"), "prepravy");
?>
<a class="app-zpet" href="<?= chran(odkaz("prepravy")) ?>">← Zpět na seznam přeprav</a>
<?php
hlava_stranky($nova ? "Evidence" : "Přeprava " . $h("cislo"),
  $nova ? "Nová přeprava" : (($h("nakladka_misto", "?")) . " → " . ($h("vykladka_misto", "?"))),
  $akce_hlavy);
?>

<div class="app-sloupce">
  <div>
    <form method="post" action="<?= chran(odkaz("preprava", ["id" => $nova ? "nova" : $preprava["id"]])) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="ulozit">

      <div class="skupina">
        <h2>Zakázka</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="stav">Stav</label>
            <select id="stav" name="stav"><?= volby(STAVY, $h("stav", "nova")) ?></select>
          </div>
          <div class="pole">
            <label for="zakaznik_id">Zákazník</label>
            <select id="zakaznik_id" name="zakaznik_id"><?= volby($do_voleb($zakaznici, "nazev"), $h("zakaznik_id"), "— nevybrán —") ?></select>
          </div>
          <div class="pole">
            <label for="ref_zakaznika">Reference zákazníka</label>
            <input type="text" id="ref_zakaznika" name="ref_zakaznika" value="<?= chran($h("ref_zakaznika")) ?>"
                   placeholder="číslo objednávky u zákazníka">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Nakládka</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="nakladka_misto">Místo <span class="napoveda">— obec</span></label>
            <input type="text" id="nakladka_misto" name="nakladka_misto" value="<?= chran($h("nakladka_misto")) ?>">
          </div>
          <div class="pole">
            <label for="nakladka_adresa">Adresa a kontakt</label>
            <input type="text" id="nakladka_adresa" name="nakladka_adresa" value="<?= chran($h("nakladka_adresa")) ?>">
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="nakladka_datum">Datum</label>
            <input type="date" id="nakladka_datum" name="nakladka_datum" value="<?= chran($h("nakladka_datum")) ?>">
          </div>
          <div class="pole">
            <label for="nakladka_od">Okno od</label>
            <input type="time" id="nakladka_od" name="nakladka_od" value="<?= chran($h("nakladka_od")) ?>">
          </div>
          <div class="pole">
            <label for="nakladka_do">Okno do</label>
            <input type="time" id="nakladka_do" name="nakladka_do" value="<?= chran($h("nakladka_do")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Vykládka</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="vykladka_misto">Místo <span class="napoveda">— obec</span></label>
            <input type="text" id="vykladka_misto" name="vykladka_misto" value="<?= chran($h("vykladka_misto")) ?>">
          </div>
          <div class="pole">
            <label for="vykladka_adresa">Adresa a kontakt</label>
            <input type="text" id="vykladka_adresa" name="vykladka_adresa" value="<?= chran($h("vykladka_adresa")) ?>">
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="vykladka_datum">Datum</label>
            <input type="date" id="vykladka_datum" name="vykladka_datum" value="<?= chran($h("vykladka_datum")) ?>">
          </div>
          <div class="pole">
            <label for="vykladka_od">Okno od</label>
            <input type="time" id="vykladka_od" name="vykladka_od" value="<?= chran($h("vykladka_od")) ?>">
          </div>
          <div class="pole">
            <label for="vykladka_do">Okno do</label>
            <input type="time" id="vykladka_do" name="vykladka_do" value="<?= chran($h("vykladka_do")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Náklad</h2>
        <div class="pole">
          <label for="zbozi">Zboží</label>
          <input type="text" id="zbozi" name="zbozi" value="<?= chran($h("zbozi")) ?>">
        </div>
        <div class="pole-radek ctyri">
          <div class="pole">
            <label for="hmotnost">Hmotnost <span class="napoveda">kg</span></label>
            <input type="number" id="hmotnost" name="hmotnost" value="<?= chran($h("hmotnost")) ?>" min="0" step="1">
          </div>
          <div class="pole">
            <label for="palet">Palet</label>
            <input type="number" id="palet" name="palet" value="<?= chran($h("palet")) ?>" min="0" step="1">
          </div>
          <div class="pole">
            <label for="ldm">LDM</label>
            <input type="text" id="ldm" name="ldm" value="<?= chran($h("ldm")) ?>" inputmode="decimal">
          </div>
          <div class="pole">
            <label for="typ_vozidla">Požadované vozidlo</label>
            <select id="typ_vozidla" name="typ_vozidla"><?= volby(TYPY_VOZIDEL, $h("typ_vozidla", "plachta")) ?></select>
          </div>
        </div>
        <div class="pole">
          <label for="pozadavky">Zvláštní požadavky <span class="napoveda">— hydraulické čelo, teplota, ADR…</span></label>
          <input type="text" id="pozadavky" name="pozadavky" value="<?= chran($h("pozadavky")) ?>">
        </div>
      </div>

      <div class="skupina">
        <h2>Dopravce</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="dopravce_id">Dopravce</label>
            <select id="dopravce_id" name="dopravce_id"><?= volby($do_voleb($dopravci, "nazev"), $h("dopravce_id"), "— nezajištěno —") ?></select>
          </div>
          <div class="pole">
            <label for="cena_dopravce">Cena dopravce <span class="napoveda">Kč bez DPH</span></label>
            <input type="text" id="cena_dopravce" name="cena_dopravce" value="<?= chran($h("cena_dopravce")) ?>" inputmode="decimal">
          </div>
        </div>
        <?php if ($dopravce_id): ?>
          <div class="pole-radek">
            <div class="pole">
              <label for="vozidlo_id">Vozidlo z karty dopravce</label>
              <select id="vozidlo_id" name="vozidlo_id"><?= volby($do_voleb($vozidla, "spz"), $h("vozidlo_id"), "— nevybráno —") ?></select>
            </div>
            <div class="pole">
              <label for="ridic_id">Řidič z karty dopravce</label>
              <select id="ridic_id" name="ridic_id"><?= volby($do_voleb($ridici, "jmeno"), $h("ridic_id"), "— nevybrán —") ?></select>
            </div>
          </div>
        <?php else: ?>
          <p class="app-perex">Vozidla a řidiče z karty dopravce nabídneme, jakmile dopravce vyberete a přepravu uložíte.</p>
        <?php endif; ?>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="spz">SPZ do objednávky</label>
            <input type="text" id="spz" name="spz" value="<?= chran($h("spz")) ?>">
          </div>
          <div class="pole">
            <label for="ridic_jmeno">Řidič</label>
            <input type="text" id="ridic_jmeno" name="ridic_jmeno" value="<?= chran($h("ridic_jmeno")) ?>">
          </div>
          <div class="pole">
            <label for="ridic_telefon">Telefon na řidiče</label>
            <input type="tel" id="ridic_telefon" name="ridic_telefon" value="<?= chran($h("ridic_telefon")) ?>">
          </div>
        </div>
        <div class="pole">
          <label for="poznamka_dopravci">Pokyny pro dopravce <span class="napoveda">— tisknou se v objednávce</span></label>
          <textarea id="poznamka_dopravci" name="poznamka_dopravci" style="min-height:80px"><?= chran($h("poznamka_dopravci")) ?></textarea>
        </div>
      </div>

      <?php if ($ceny): ?>
        <div class="skupina">
          <h2>Obchod</h2>
          <div class="pole-radek tri">
            <div class="pole">
              <label for="cena_zakaznik">Cena zákazníka <span class="napoveda">Kč bez DPH</span></label>
              <input type="text" id="cena_zakaznik" name="cena_zakaznik" value="<?= chran($h("cena_zakaznik")) ?>" inputmode="decimal">
            </div>
            <div class="pole">
              <label for="faktura_vydana">Vydaná faktura</label>
              <input type="text" id="faktura_vydana" name="faktura_vydana" value="<?= chran($h("faktura_vydana")) ?>">
            </div>
            <div class="pole">
              <label>Marže</label>
              <p class="cislo" id="marze-nahled" style="padding:11px 0;margin:0;font-weight:700">—</p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="skupina">
        <h2>Doklady</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="doklady">Stav dokladů</label>
            <select id="doklady" name="doklady"><?= volby(DOKLADY, $h("doklady", "ceka")) ?></select>
          </div>
          <div class="pole">
            <label for="doklady_poznamka">Poznámka k dokladům</label>
            <input type="text" id="doklady_poznamka" name="doklady_poznamka" value="<?= chran($h("doklady_poznamka")) ?>">
          </div>
          <div class="pole">
            <label for="faktura_prijata">Přijatá faktura dopravce</label>
            <input type="text" id="faktura_prijata" name="faktura_prijata" value="<?= chran($h("faktura_prijata")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Interní poznámka</h2>
        <div class="pole">
          <label for="poznamka" class="jen-pro-ctecky">Interní poznámka</label>
          <textarea id="poznamka" name="poznamka" placeholder="Netiskne se v objednávce."><?= chran($h("poznamka")) ?></textarea>
        </div>
      </div>

      <button type="submit" class="tlacitko"><?= $nova ? "Založit přepravu" : "Uložit změny" ?></button>
    </form>
  </div>

  <div>
    <?php if (!$nova): ?>
      <div class="formular">
        <div class="skupina">
          <h2>Shrnutí</h2>
          <ul class="udaje">
            <li><span class="klic">Číslo</span><span class="hodnota cislo"><?= chran($h("cislo")) ?></span></li>
            <li><span class="klic">Stav</span><span class="hodnota"><?= stitek_stavu($h("stav")) ?></span></li>
            <li><span class="klic">Objednávka</span><span class="hodnota"><?= $h("objednavka_datum") ? chran(datum_cas($h("objednavka_datum"))) : "nevystavena" ?></span></li>
            <?php if ($ceny): ?>
              <li><span class="klic">Marže</span><span class="hodnota cislo"><?=
                ($preprava["cena_zakaznik"] === null && $preprava["cena_dopravce"] === null) ? "—"
                : chran(castka((float)$preprava["cena_zakaznik"] - (float)$preprava["cena_dopravce"])) ?></span></li>
            <?php endif; ?>
            <li><span class="klic">Založeno</span><span class="hodnota"><?= chran(datum_cas($h("vytvoreno"))) ?></span></li>
          </ul>
        </div>

        <div class="skupina" style="margin-bottom:0">
          <h2>Ukončení</h2>
          <?php if ($h("stav") !== "zruseno"): ?>
            <form method="post" action="<?= chran(odkaz("preprava", ["id" => $preprava["id"]])) ?>"
                  data-potvrdit="Opravdu přepravu zrušit? Zůstane v evidenci, ale nebude se počítat do obratu.">
              <?= pole_token() ?>
              <input type="hidden" name="akce" value="zrusit">
              <button type="submit" class="tlacitko obrys">Zrušit přepravu</button>
            </form>
          <?php else: ?>
            <p class="app-perex">Přeprava je zrušená. Vrátit ji zpět jde změnou stavu ve formuláři.</p>
          <?php endif; ?>
          <?php if (je_spravce()): ?>
            <form method="post" action="<?= chran(odkaz("preprava", ["id" => $preprava["id"]])) ?>" style="margin-top:10px"
                  data-potvrdit="Smazat přepravu nadobro? Tohle se vrátit nedá — zrušení je obvykle to, co chcete.">
              <?= pole_token() ?>
              <input type="hidden" name="akce" value="smazat">
              <button type="submit" class="odkaz-tlacitko" style="margin:0">Smazat nadobro</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($udalosti): ?>
        <div class="formular" style="margin-top:20px">
          <div class="skupina" style="margin-bottom:0">
            <h2>Protokol</h2>
            <ul class="protokol">
              <?php foreach ($udalosti as $u): ?>
                <li>
                  <?= chran($u["text"]) ?>
                  <time><?= chran(datum_cas($u["kdy"])) ?><?= $u["kdo"] ? " · " . chran($u["kdo"]) : "" ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php
pata();

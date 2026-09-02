<?php
/* Nabídka — zápis poptávky, ocenění, tisk a odeslání zákazníkovi. Z přijaté
   vznikne jedním kliknutím přeprava; u neúspěšné se zapíše důvod. Cena je
   jádro nabídky, proto stránku vidí jen ten, kdo na ceny má právo. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_ceny();

$id = vstup("id");
$nova = ($id === "nova" || $id === "");
$n = null;
if (!$nova) {
  $n = radek("SELECT n.*, z.nazev AS zakaznik_nazev FROM nabidky n LEFT JOIN firmy z ON z.id = n.zakaznik_id WHERE n.id = ?", [(int)$id]);
  if (!$n) {
    vzkaz("chyba", "Nabídka nenalezena.");
    presmeruj(odkaz("nabidky"));
  }
}
$zpet = function () use ($n) { return odkaz("nabidka", ["id" => $n["id"]]); };

/* --- Zápis -------------------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($akce === "ulozit") {
    $zakaznik_id = vstup_cislo("zakaznik_id") ?: null;
    $kontakt_jmeno = vstup("kontakt_jmeno");
    $kontakt_email = vstup("kontakt_email");
    /* Prázdný kontakt se doplní z karty zákazníka. */
    if ($zakaznik_id && ($kontakt_jmeno === "" || $kontakt_email === "")) {
      $z = radek("SELECT kontakt_jmeno, kontakt_email FROM firmy WHERE id = ?", [$zakaznik_id]);
      if ($z) {
        if ($kontakt_jmeno === "") $kontakt_jmeno = (string)$z["kontakt_jmeno"];
        if ($kontakt_email === "") $kontakt_email = (string)$z["kontakt_email"];
      }
    }
    $data = [
      "zakaznik_id"   => $zakaznik_id,
      "kontakt_jmeno" => $kontakt_jmeno,
      "kontakt_email" => $kontakt_email,
      "ref_zakaznika" => vstup("ref_zakaznika"),
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
      "km"          => vstup_cislo("km"),
      "typ_vozidla" => isset(TYPY_VOZIDEL[vstup("typ_vozidla")]) ? vstup("typ_vozidla") : "plachta",
      "pozadavky"   => vstup("pozadavky"),
      "cena"          => vstup_castka("cena"),
      "cena_dopravce" => vstup_castka("cena_dopravce"),
      "cena_podle"    => vstup("cena_podle"),
      "text_pro_zakaznika" => (string)($_POST["text_pro_zakaznika"] ?? ""),
      "poznamka"    => (string)($_POST["poznamka"] ?? ""),
      "upraveno"    => date("Y-m-d H:i:s"),
    ];
    if ($data["nakladka_misto"] === "" && $data["vykladka_misto"] === "") {
      vzkaz("chyba", "Vyplňte aspoň obec nakládky nebo vykládky.");
      presmeruj($nova ? odkaz("nabidka", ["id" => "nova"]) : $zpet());
    }
    if ($nova) {
      $data["cislo"]     = dalsi_cislo_nabidky();
      $data["stav"]      = "otevrena";
      $data["vytvoreno"] = date("Y-m-d H:i:s");
      $data["vytvoril"]  = (int)uzivatel()["id"];
      $novy = vloz("nabidky", $data);
      zapis_udalost(null, "Nabídka " . $data["cislo"] . " založena");
      vzkaz("ok", "Nabídka " . $data["cislo"] . " založena. Doplňte cenu a pošlete ji zákazníkovi.");
      presmeruj(odkaz("nabidka", ["id" => $novy]));
    }
    uprav("nabidky", (int)$n["id"], $data);
    vzkaz("ok", "Změny uloženy.");
    presmeruj($zpet());

  } elseif ($akce === "prijmout" && $n) {
    if ($n["stav"] !== "otevrena") { vzkaz("chyba", "Přijmout jde jen otevřenou nabídku."); presmeruj($zpet()); }
    $pid = zaloz_prepravu_z_nabidky($n);
    uprav("nabidky", (int)$n["id"], ["stav" => "prijata", "duvod" => null, "duvod_poznamka" => "", "rozhodnuto" => date("Y-m-d H:i:s"), "preprava_id" => $pid, "upraveno" => date("Y-m-d H:i:s")]);
    $cislo_prepravy = (string)hodnota("SELECT cislo FROM prepravy WHERE id = ?", [$pid]);
    zapis_udalost($pid, "Přeprava " . $cislo_prepravy . " založena z nabídky " . $n["cislo"]);
    zapis_udalost(null, "Nabídka " . $n["cislo"] . " přijata, vznikla přeprava " . $cislo_prepravy);
    vzkaz("ok", "Nabídka přijata. Vznikla přeprava " . $cislo_prepravy . " — doplňte dopravce a termíny bodů.");
    presmeruj(odkaz("preprava", ["id" => $pid]));

  } elseif ($akce === "neprosla" && $n) {
    if ($n["stav"] === "prijata") { vzkaz("chyba", "Přijatá nabídka už má přepravu; zrušte případně tu."); presmeruj($zpet()); }
    $duvod = isset(DUVODY_NABIDKY[vstup("duvod")]) ? vstup("duvod") : "jiny";
    uprav("nabidky", (int)$n["id"], ["stav" => "neprosla", "duvod" => $duvod, "duvod_poznamka" => vstup("duvod_poznamka"), "rozhodnuto" => date("Y-m-d H:i:s"), "upraveno" => date("Y-m-d H:i:s")]);
    zapis_udalost(null, "Nabídka " . $n["cislo"] . " neprošla: " . DUVODY_NABIDKY[$duvod]);
    vzkaz("pozor", "Nabídka označena jako neúspěšná (" . mb_strtolower(DUVODY_NABIDKY[$duvod]) . ").");
    presmeruj($zpet());

  } elseif ($akce === "otevrit" && $n) {
    if ($n["stav"] !== "neprosla") { vzkaz("chyba", "Znovu otevřít jde jen nabídku, která neprošla."); presmeruj($zpet()); }
    uprav("nabidky", (int)$n["id"], ["stav" => "otevrena", "duvod" => null, "duvod_poznamka" => "", "rozhodnuto" => null, "upraveno" => date("Y-m-d H:i:s")]);
    zapis_udalost(null, "Nabídka " . $n["cislo"] . " znovu otevřena");
    vzkaz("ok", "Nabídka je znovu otevřená.");
    presmeruj($zpet());

  } elseif ($akce === "smazat" && $n) {
    if (!je_spravce()) { vzkaz("chyba", "Mazat nabídky může jen správce."); presmeruj($zpet()); }
    if ($n["stav"] === "prijata") { vzkaz("chyba", "Přijatou nabídku nelze smazat — váže se na ni přeprava."); presmeruj($zpet()); }
    dotaz("DELETE FROM nabidky WHERE id = ?", [(int)$n["id"]]);
    zapis_udalost(null, "Nabídka " . $n["cislo"] . " smazána");
    vzkaz("ok", "Nabídka " . $n["cislo"] . " smazána.");
    presmeruj(odkaz("nabidky"));

  } elseif ($akce === "poslat" && $n) {
    $komu = vstup("komu");
    if (!platny_email($komu)) { vzkaz("chyba", "Zadejte platnou e-mailovou adresu zákazníka."); presmeruj(odkaz("nabidka", ["id" => $n["id"], "tisk" => 1])); }
    $u = nabidka_udaje($n);
    $uvod = trim((string)($_POST["uvod"] ?? ""));
    $predmet = vstup("predmet") ?: "Cenová nabídka " . $n["cislo"] . " – " . $u["trasa"];
    $ja = uzivatel();
    $poslano = posli_email($komu, $predmet, nabidka_text($u, $uvod), nabidka_html($u, $uvod), (string)($ja["email"] ?? ""), (string)nastaveni("email_kopie"));
    if ($poslano) {
      uprav("nabidky", (int)$n["id"], ["odeslana" => date("Y-m-d H:i:s"), "kontakt_email" => $komu, "upraveno" => date("Y-m-d H:i:s")]);
      zapis_udalost(null, "Nabídka " . $n["cislo"] . " odeslána e-mailem na " . $komu);
      vzkaz("ok", "Nabídka odeslána na " . $komu . ".");
    } else {
      vzkaz("chyba", "Poštovní server zprávu nepřijal. Nabídku vytiskněte nebo uložte jako PDF a pošlete ručně; hosting musí mít povolené odesílání pošty.");
    }
    presmeruj(odkaz("nabidka", ["id" => $n["id"], "tisk" => 1]));
  }
}

/* --- Tisk a odeslání ---------------------------------------------------- */

if ($n && vstup("tisk") !== "") {
  $u = nabidka_udaje($n);
  $zak = $n["zakaznik_id"] ? radek("SELECT * FROM firmy WHERE id = ?", [(int)$n["zakaznik_id"]]) : null;
  $adresa_nas = $u["firma_adresa"];
  $adresa_zak = $zak ? trim(((string)$zak["ulice"]) . ", " . ((string)$zak["psc"]) . " " . ((string)$zak["mesto"]), ", ") : "";
  hlava("Nabídka " . $n["cislo"], "", ["bez_navigace" => true]);
  ?>
  <div class="netisknout" style="max-width:900px;margin:0 auto 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a class="tlacitko obrys" href="<?= chran($zpet()) ?>">← Zpět na nabídku</a>
    <button type="button" class="tlacitko" onclick="window.print()">Vytisknout / uložit PDF</button>
    <?php if ($n["odeslana"]): ?><span class="app-perex" style="margin:0">Odeslána <?= chran(datum_cas($n["odeslana"])) ?>.</span><?php endif; ?>
  </div>

  <form method="post" action="<?= chran($zpet()) ?>" class="formular netisknout" style="max-width:900px;margin:0 auto 20px" data-jednou>
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="poslat">
    <div class="skupina" style="margin-bottom:0">
      <h2>Poslat zákazníkovi</h2>
      <div class="pole-radek">
        <div class="pole">
          <label for="komu">Komu</label>
          <input type="email" id="komu" name="komu" value="<?= chran($n["kontakt_email"] ?: (string)($zak["kontakt_email"] ?? "")) ?>" required>
        </div>
        <div class="pole">
          <label for="predmet">Předmět</label>
          <input type="text" id="predmet" name="predmet" value="<?= chran("Cenová nabídka " . $n["cislo"] . " – " . $u["trasa"]) ?>">
        </div>
      </div>
      <div class="pole">
        <label for="uvod">Úvodní text <span class="napoveda">— nabídka celá následuje pod ním</span></label>
        <textarea id="uvod" name="uvod" style="min-height:70px">Dobrý den,
děkujeme za poptávku, posíláme cenovou nabídku. Přijetí prosím potvrďte odpovědí na tento e-mail.</textarea>
      </div>
      <button type="submit" class="tlacitko">Odeslat e-mailem</button>
      <p class="formular-poznamka">Odejde z adresy <?= chran(nastaveni("email_odesilatel", "web@idispecink.cz")) ?>, odpověď přijde vám<?= platny_email((string)nastaveni("email_kopie")) ? ", kopie na " . chran(nastaveni("email_kopie")) : "" ?>.</p>
    </div>
  </form>

  <article class="objednavka">
    <header class="objednavka-hlava">
      <div>
        <img src="../assets/img/logo-idispecink.svg" alt="iDispečink.cz" width="240" height="38">
        <p style="margin-top:10px;font-size:.9rem"><b><?= chran($u["firma"]) ?></b><br>
          <?= chran($adresa_nas) ?><br>
          IČO <?= chran($u["firma_ico"]) ?><?= $u["firma_dic"] ? ", DIČ " . chran($u["firma_dic"]) : "" ?><br>
          <?= chran($u["firma_telefon"]) ?> · <?= chran($u["firma_email"]) ?></p>
      </div>
      <div class="cislo">
        <span class="nadpis-stitek">Cenová nabídka</span>
        <b class="cislo"><?= chran($n["cislo"]) ?></b>
        <p style="font-size:.88rem;margin-top:6px">Vystaveno <?= chran(datum($n["odeslana"] ?: date("Y-m-d"))) ?></p>
      </div>
    </header>

    <div class="strany">
      <div>
        <h2>Dodavatel</h2>
        <p><b><?= chran($u["firma"]) ?></b><br><?= chran($adresa_nas) ?><br>IČO <?= chran($u["firma_ico"]) ?><br><?= chran(nastaveni("firma_zapis")) ?></p>
      </div>
      <div>
        <h2>Zákazník</h2>
        <p><b><?= chran($u["zakaznik"] ?: "—") ?></b><br>
          <?= chran($adresa_zak ?: "") ?><?= $adresa_zak ? "<br>" : "" ?>
          <?= $zak && $zak["ico"] ? "IČO " . chran($zak["ico"]) . ($zak["dic"] ? ", DIČ " . chran($zak["dic"]) : "") . "<br>" : "" ?>
          <?= chran($u["kontakt"]) ?><?= $n["kontakt_email"] ? " · " . chran($n["kontakt_email"]) : "" ?>
          <?= $u["reference"] !== "" ? "<br>Vaše reference: " . chran($u["reference"]) : "" ?></p>
      </div>
    </div>

    <h2>Přeprava</h2>
    <div class="tabulka-obal">
      <table class="id-tabulka">
        <tbody>
          <tr><td>Nakládka</td><td><?= chran($u["nakladka"]) ?></td></tr>
          <tr><td>Vykládka</td><td><?= chran($u["vykladka"]) ?></td></tr>
          <?php if ($u["naklad"] !== ""): ?><tr><td>Zboží</td><td><?= chran($u["naklad"]) ?></td></tr><?php endif; ?>
          <tr><td>Vozidlo</td><td><?= chran($u["vozidlo"]) ?></td></tr>
          <?php if ($u["km"] !== ""): ?><tr><td>Vzdálenost</td><td><?= chran($u["km"]) ?></td></tr><?php endif; ?>
          <tr><td>Cena</td><td><b class="cislo"><?= chran($u["cena"]) ?></b><?= $u["cena_s_dph"] !== "" ? "<br>" . chran($u["cena_s_dph"]) : "" ?></td></tr>
        </tbody>
      </table>
    </div>

    <?php if ($u["text"] !== ""): ?>
      <h2>K nabídce</h2>
      <p class="podminky"><?= chran($u["text"]) ?></p>
    <?php endif; ?>

    <div class="podpisy">
      <div>Za dodavatele — <?= chran($u["firma"]) ?></div>
      <div>Přijetí nabídky potvrďte odpovědí na e-mail nebo telefonicky.</div>
    </div>
  </article>
  <?php
  pata();
  exit;
}

/* --- Formulář ----------------------------------------------------------- */

$h = function (string $klic, string $vychozi = "") use ($n) {
  $hodnota = $n[$klic] ?? null;
  return ($hodnota === null || $hodnota === "") ? $vychozi : (string)$hodnota;
};
$zakaznici = radky("SELECT id, nazev FROM firmy WHERE typ IN ('zakaznik','oboji') AND (aktivni = 1 OR id = ?) ORDER BY LOWER(nazev)", [(int)($n["zakaznik_id"] ?? 0)]);
$volby_zakazniku = []; foreach ($zakaznici as $z) $volby_zakazniku[(string)$z["id"]] = (string)$z["nazev"];
$mista = radky("SELECT nazev, mesto FROM mista WHERE aktivni = 1 ORDER BY LOWER(nazev)");
$predvoleny_zakaznik = $nova ? (string)(vstup_cislo("zakaznik") ?: "") : "";

$navrh = $n ? navrh_ceny($n["zakaznik_id"] ? (int)$n["zakaznik_id"] : null, (string)$n["nakladka_misto"], (string)$n["vykladka_misto"],
                          $n["km"] !== null ? (int)$n["km"] : null, (string)$n["typ_vozidla"]) : null;
$preprava = ($n && $n["preprava_id"]) ? radek("SELECT id, cislo, stav FROM prepravy WHERE id = ?", [(int)$n["preprava_id"]]) : null;

$akce_hlavy = "";
if ($n) {
  $akce_hlavy .= '<a class="tlacitko" href="' . chran(odkaz("nabidka", ["id" => $n["id"], "tisk" => 1])) . '" target="_blank" rel="noopener">Tisk a odeslání</a>';
}
hlava($nova ? "Nová nabídka" : "Nabídka " . $h("cislo"), "nabidky");
?>
<a class="app-zpet" href="<?= chran(odkaz("nabidky")) ?>">← Zpět na nabídky</a>
<?php
hlava_stranky($nova ? "Obchod" : "Nabídka " . $h("cislo"),
  $nova ? "Nová nabídka" : (($h("nakladka_misto") ?: "?") . " → " . ($h("vykladka_misto") ?: "?")), $akce_hlavy);
?>

<datalist id="seznam-obci">
  <?php foreach ($mista as $m): ?><option value="<?= chran($m["mesto"]) ?>"><?= chran($m["nazev"]) ?></option><?php endforeach; ?>
</datalist>

<div class="app-sloupce">
  <div>
    <form method="post" action="<?= chran(odkaz("nabidka", ["id" => $nova ? "nova" : $n["id"]])) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="ulozit">

      <div class="skupina">
        <h2>Poptávka</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="zakaznik_id">Zákazník</label>
            <select id="zakaznik_id" name="zakaznik_id"><?= volby($volby_zakazniku, $h("zakaznik_id", $predvoleny_zakaznik), "— nevybrán —") ?></select>
          </div>
          <div class="pole">
            <label for="ref_zakaznika">Reference zákazníka</label>
            <input type="text" id="ref_zakaznika" name="ref_zakaznika" value="<?= chran($h("ref_zakaznika")) ?>" placeholder="číslo poptávky u zákazníka">
          </div>
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="kontakt_jmeno">Kontaktní osoba <span class="napoveda">— prázdné se doplní z karty</span></label>
            <input type="text" id="kontakt_jmeno" name="kontakt_jmeno" value="<?= chran($h("kontakt_jmeno")) ?>">
          </div>
          <div class="pole">
            <label for="kontakt_email">E-mail pro nabídku</label>
            <input type="email" id="kontakt_email" name="kontakt_email" value="<?= chran($h("kontakt_email")) ?>">
          </div>
        </div>
      </div>

      <?php foreach ([["nakladka", "Nakládka"], ["vykladka", "Vykládka"]] as [$k, $popis]): ?>
        <div class="skupina">
          <h2><?= $popis ?></h2>
          <div class="pole-radek">
            <div class="pole">
              <label for="<?= $k ?>_misto">Obec</label>
              <input type="text" id="<?= $k ?>_misto" name="<?= $k ?>_misto" value="<?= chran($h($k . "_misto")) ?>" list="seznam-obci">
            </div>
            <div class="pole">
              <label for="<?= $k ?>_adresa">Adresa</label>
              <input type="text" id="<?= $k ?>_adresa" name="<?= $k ?>_adresa" value="<?= chran($h($k . "_adresa")) ?>">
            </div>
          </div>
          <div class="pole-radek tri">
            <div class="pole"><label for="<?= $k ?>_datum">Datum</label><input type="date" id="<?= $k ?>_datum" name="<?= $k ?>_datum" value="<?= chran($h($k . "_datum")) ?>"></div>
            <div class="pole"><label for="<?= $k ?>_od">Okno od</label><input type="time" id="<?= $k ?>_od" name="<?= $k ?>_od" value="<?= chran($h($k . "_od")) ?>"></div>
            <div class="pole"><label for="<?= $k ?>_do">Okno do</label><input type="time" id="<?= $k ?>_do" name="<?= $k ?>_do" value="<?= chran($h($k . "_do")) ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="skupina">
        <h2>Náklad</h2>
        <div class="pole">
          <label for="zbozi">Zboží</label>
          <input type="text" id="zbozi" name="zbozi" value="<?= chran($h("zbozi")) ?>">
        </div>
        <div class="pole-radek ctyri">
          <div class="pole"><label for="hmotnost">Hmotnost <span class="napoveda">kg</span></label><input type="number" id="hmotnost" name="hmotnost" value="<?= chran($h("hmotnost")) ?>" min="0" step="1"></div>
          <div class="pole"><label for="palet">Palet</label><input type="number" id="palet" name="palet" value="<?= chran($h("palet")) ?>" min="0" step="1"></div>
          <div class="pole"><label for="ldm">LDM</label><input type="text" id="ldm" name="ldm" value="<?= chran($h("ldm")) ?>" inputmode="decimal"></div>
          <div class="pole"><label for="km">Vzdálenost <span class="napoveda">km</span></label><input type="number" id="km" name="km" value="<?= chran($h("km")) ?>" min="0" step="1" placeholder="zatím ručně"></div>
        </div>
        <div class="pole-radek">
          <div class="pole"><label for="typ_vozidla">Požadované vozidlo</label><select id="typ_vozidla" name="typ_vozidla"><?= volby(TYPY_VOZIDEL, $h("typ_vozidla", "plachta")) ?></select></div>
          <div class="pole"><label for="pozadavky">Zvláštní požadavky</label><input type="text" id="pozadavky" name="pozadavky" value="<?= chran($h("pozadavky")) ?>"></div>
        </div>
      </div>

      <div class="skupina">
        <h2>Cena</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="cena_zakaznik">Cena pro zákazníka <span class="napoveda">Kč bez DPH</span></label>
            <input type="text" id="cena_zakaznik" name="cena" value="<?= chran($h("cena")) ?>" inputmode="decimal">
          </div>
          <div class="pole">
            <label for="cena_dopravce">Odhad nákladu <span class="napoveda">Kč bez DPH, jen pro marži</span></label>
            <input type="text" id="cena_dopravce" name="cena_dopravce" value="<?= chran($h("cena_dopravce")) ?>" inputmode="decimal">
          </div>
          <div class="pole">
            <label>Očekávaná marže</label>
            <p class="cislo" id="marze-nahled" style="padding:11px 0;margin:0;font-weight:700">—</p>
          </div>
        </div>
        <?php if ($n): ?>
          <?= navrh_ceny_html($navrh, "cena_zakaznik") ?>
        <?php else: ?>
          <p class="app-perex navrh-ceny">Návrh ceny podle ceníku a historie trasy se ukáže po založení nabídky.</p>
        <?php endif; ?>
        <div class="pole">
          <label for="cena_podle">Podle čeho je cena <span class="napoveda">— doplní se z návrhu, nebo napište</span></label>
          <input type="text" id="cena_podle" name="cena_podle" value="<?= chran($h("cena_podle")) ?>">
        </div>
        <div class="pole">
          <label for="text_pro_zakaznika">Text pro zákazníka <span class="napoveda">— tiskne se v nabídce, např. co je v ceně a co ne</span></label>
          <textarea id="text_pro_zakaznika" name="text_pro_zakaznika" style="min-height:90px"><?= chran($h("text_pro_zakaznika")) ?></textarea>
        </div>
      </div>

      <div class="skupina">
        <h2>Interní poznámka</h2>
        <div class="pole">
          <label for="poznamka" class="jen-pro-ctecky">Interní poznámka</label>
          <textarea id="poznamka" name="poznamka" placeholder="Netiskne se v nabídce."><?= chran($h("poznamka")) ?></textarea>
        </div>
      </div>

      <button type="submit" class="tlacitko"><?= $nova ? "Založit nabídku" : "Uložit změny" ?></button>
    </form>
  </div>

  <div>
    <?php if ($n): ?>
      <div class="formular">
        <div class="skupina">
          <h2>Shrnutí</h2>
          <ul class="udaje">
            <li><span class="klic">Číslo</span><span class="hodnota cislo"><?= chran($h("cislo")) ?></span></li>
            <li><span class="klic">Stav</span><span class="hodnota"><?= stitek_nabidky($h("stav")) ?><?php
              if ($h("stav") === "neprosla") echo "<br><span class=\"druhotny\">" . chran(DUVODY_NABIDKY[$h("duvod")] ?? "") . ($h("duvod_poznamka") !== "" ? " — " . chran($h("duvod_poznamka")) : "") . "</span>";
            ?></span></li>
            <li><span class="klic">Odeslána</span><span class="hodnota"><?= $h("odeslana") ? chran(datum_cas($h("odeslana"))) . ($h("kontakt_email") ? "<br><span class=\"druhotny\">" . chran($h("kontakt_email")) . "</span>" : "") : "zatím ne" ?></span></li>
            <?php if ($preprava): ?>
              <li><span class="klic">Přeprava</span><span class="hodnota"><a href="<?= chran(odkaz("preprava", ["id" => $preprava["id"]])) ?>" class="cislo"><?= chran($preprava["cislo"]) ?></a> <?= stitek_stavu($preprava["stav"]) ?></span></li>
            <?php endif; ?>
            <li><span class="klic">Vznik</span><span class="hodnota"><?= chran(datum_cas($h("vytvoreno"))) ?></span></li>
          </ul>
        </div>

        <div class="skupina" style="margin-bottom:0">
          <h2>Rozhodnutí</h2>
          <?php if ($h("stav") === "otevrena"): ?>
            <form method="post" action="<?= chran($zpet()) ?>" data-potvrdit="Přijmout nabídku <?= chran($h("cislo")) ?> a založit z ní přepravu s cenou <?= chran(castka($n["cena"])) ?>?">
              <?= pole_token() ?><input type="hidden" name="akce" value="prijmout">
              <button type="submit" class="tlacitko" style="width:100%"<?= $n["cena"] === null ? " disabled title=\"Nejdřív doplňte cenu\"" : "" ?>>Přijata → založit přepravu</button>
            </form>
            <?php if ($n["cena"] === null): ?><p class="app-perex" style="margin-top:6px">Přijmout jde až s cenou.</p><?php endif; ?>
            <form method="post" action="<?= chran($zpet()) ?>" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--linka)">
              <?= pole_token() ?><input type="hidden" name="akce" value="neprosla">
              <div class="pole">
                <label for="duvod">Neprošla — proč</label>
                <select id="duvod" name="duvod"><?= volby(DUVODY_NABIDKY, "drahe") ?></select>
              </div>
              <div class="pole">
                <label for="duvod_poznamka">Poznámka k důvodu</label>
                <input type="text" id="duvod_poznamka" name="duvod_poznamka" placeholder="např. konkurence o 2 000 Kč levněji">
              </div>
              <button type="submit" class="tlacitko obrys">Označit jako neúspěšnou</button>
            </form>
          <?php elseif ($h("stav") === "neprosla"): ?>
            <form method="post" action="<?= chran($zpet()) ?>">
              <?= pole_token() ?><input type="hidden" name="akce" value="otevrit">
              <button type="submit" class="tlacitko obrys">Znovu otevřít</button>
            </form>
          <?php else: ?>
            <p class="app-perex">Nabídka je přijatá, práce pokračuje na přepravě.</p>
          <?php endif; ?>
          <?php if (je_spravce() && $h("stav") !== "prijata"): ?>
            <form method="post" action="<?= chran($zpet()) ?>" style="margin-top:14px" data-potvrdit="Smazat nabídku <?= chran($h("cislo")) ?> nadobro?">
              <?= pole_token() ?><input type="hidden" name="akce" value="smazat">
              <button type="submit" class="odkaz-tlacitko" style="margin:0">Smazat nadobro</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="formular">
        <div class="skupina" style="margin-bottom:0">
          <h2>Jak to funguje</h2>
          <p class="app-perex">Zapište, co zákazník poptává, a založte nabídku. Systém navrhne cenu podle ceníku zákazníka nebo podle toho, za kolik se trasa vozila naposled; nabídku pak vytisknete nebo pošlete e-mailem. Z přijaté vznikne přeprava jedním kliknutím, u neúspěšné si zapíšete důvod.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
pata();

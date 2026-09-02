<?php
/* Detail firmy — údaje, vozidla, řidiči, prověření dopravce a poslední přepravy.

   Rozsah prověření odpovídá tomu, co firma u nového dopravce skutečně
   kontroluje: firma a IČO v registrech, koncese nebo oprávnění k dopravě,
   pojištění odpovědnosti dopravce, doklady vozidla a řidiče, reference. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

require_once APLIKACE_CESTA . "/zdroj/ares.php";

$id = vstup("id");
$nova = ($id === "nova" || $id === "");
$firma = null;

if (!$nova) {
  $firma = radek("SELECT * FROM firmy WHERE id = ?", [(int)$id]);
  if (!$firma) {
    vzkaz("chyba", "Firma nenalezena.");
    presmeruj(odkaz("firmy"));
  }
}

/* --- Zápis -------------------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");
  /* Tlačítko „Načíst z ARES" stojí uvnitř formuláře pro uložení — má
     vlastní jméno, aby se s akcí ukládání nepralo. */
  if (isset($_POST["ares"])) $akce = "ares";

  if ($akce === "ulozit") {
    $data = [
      "typ"             => isset(TYPY_FIREM[vstup("typ")]) ? vstup("typ") : "dopravce",
      "nazev"           => vstup("nazev"),
      "ico"             => vstup("ico"),
      "dic"             => vstup("dic"),
      "ulice"           => vstup("ulice"),
      "mesto"           => vstup("mesto"),
      "psc"             => vstup("psc"),
      "stat"            => vstup("stat", "Česká republika"),
      "kontakt_jmeno"   => vstup("kontakt_jmeno"),
      "kontakt_telefon" => vstup("kontakt_telefon"),
      "kontakt_email"   => vstup("kontakt_email"),
      "splatnost"       => vstup_cislo("splatnost"),
      "poznamka"        => vstup("poznamka"),
      "prov_registry"   => vstup_ano_ne("prov_registry"),
      "prov_opravneni"  => vstup_ano_ne("prov_opravneni"),
      "prov_pojisteni"  => vstup_ano_ne("prov_pojisteni"),
      "prov_doklady"    => vstup_ano_ne("prov_doklady"),
      "prov_reference"  => vstup_ano_ne("prov_reference"),
      "prov_datum"      => vstup_datum("prov_datum"),
      "prov_poznamka"   => vstup("prov_poznamka"),
      "dispecink"          => vstup_ano_ne("dispecink"),
      "dispecink_uctovani" => isset(DISPECINK_UCTOVANI[vstup("dispecink_uctovani")]) ? vstup("dispecink_uctovani") : "",
      "dispecink_sazba"    => vstup_castka("dispecink_sazba"),
      "dispecink_poznamka" => vstup("dispecink_poznamka"),
      "aktivni"         => vstup_ano_ne("aktivni"),
      "upraveno"        => date("Y-m-d H:i:s"),
    ];

    /* Klient dispečinku vystupuje u jízd jako dopravce — čistý zákazník
       by se u přepravy nedal vybrat. */
    if ($data["dispecink"] === 1 && $data["typ"] === "zakaznik") {
      $data["typ"] = "oboji";
      vzkaz("pozor", "Klient dispečinku vystupuje u jízd jako dopravce, typ firmy je proto „Zákazník i dopravce\".");
    }

    if ($data["nazev"] === "") {
      vzkaz("chyba", "Název firmy je povinný.");
    } elseif ($nova) {
      $data["vytvoreno"] = date("Y-m-d H:i:s");
      $novy = vloz("firmy", $data);
      zapis_udalost(null, "Založena firma " . $data["nazev"]);
      vzkaz("ok", "Firma založena.");
      presmeruj(odkaz("firma", ["id" => $novy]));
    } else {
      uprav("firmy", (int)$firma["id"], $data);
      vzkaz("ok", "Změny uloženy.");
      presmeruj(odkaz("firma", ["id" => $firma["id"]]));
    }

  } elseif ($akce === "ares") {
    /* Rozepsaný formulář se nesmí ztratit — schová se celý a údaje
       z rejstříku ho jen přebijí tam, kde je rejstřík zná. */
    $rozepsane = [];
    foreach ($_POST as $klic => $hodnota) {
      if (is_string($hodnota) && !in_array($klic, ["token", "akce", "ares"], true)) {
        $rozepsane[$klic] = trim($hodnota);
      }
    }

    $nalezeno = ares_najdi(vstup("ico"), $chyba_ares);
    if ($nalezeno) {
      /* Údaje se jen předvyplní, neuloží — ať je vidíte dřív, než je
         potvrdíte. Držíme je v sezení, aby obnovení stránky formulář
         neodeslalo podruhé. */
      $_SESSION["ares"] = array_merge($rozepsane, array_filter($nalezeno, "strlen"));
      vzkaz("ok", "Načteno z ARES: " . $nalezeno["nazev"] . " — zkontrolujte a uložte.");
    } else {
      $_SESSION["ares"] = $rozepsane;
      vzkaz("pozor", (string)$chyba_ares);
    }
    presmeruj(odkaz("firma", ["id" => $nova ? "nova" : $firma["id"]]));

  } elseif ($akce === "vozidlo" && $firma) {
    $spz = vstup("spz");
    if ($spz !== "") {
      vloz("vozidla", [
        "firma_id" => (int)$firma["id"],
        "spz"      => $spz,
        "typ"      => isset(TYPY_VOZIDEL[vstup("vozidlo_typ")]) ? vstup("vozidlo_typ") : "jiny",
        "poznamka" => vstup("vozidlo_poznamka"),
        "aktivni"  => 1,
      ]);
      vzkaz("ok", "Vozidlo přidáno.");
    }
    presmeruj(odkaz("firma", ["id" => $firma["id"]]));

  } elseif ($akce === "vozidlo_pryc" && $firma) {
    dotaz("UPDATE vozidla SET aktivni = 0 WHERE id = ? AND firma_id = ?",
      [vstup_cislo("vozidlo_id"), (int)$firma["id"]]);
    vzkaz("ok", "Vozidlo vyřazeno.");
    presmeruj(odkaz("firma", ["id" => $firma["id"]]));

  } elseif ($akce === "ridic" && $firma) {
    $jmeno = vstup("ridic_jmeno");
    if ($jmeno !== "") {
      vloz("ridici", [
        "firma_id" => (int)$firma["id"],
        "jmeno"    => $jmeno,
        "telefon"  => vstup("ridic_telefon"),
        "poznamka" => vstup("ridic_poznamka"),
        "aktivni"  => 1,
      ]);
      vzkaz("ok", "Řidič přidán.");
    }
    presmeruj(odkaz("firma", ["id" => $firma["id"]]));

  } elseif ($akce === "ridic_pryc" && $firma) {
    dotaz("UPDATE ridici SET aktivni = 0 WHERE id = ? AND firma_id = ?",
      [vstup_cislo("ridic_id"), (int)$firma["id"]]);
    vzkaz("ok", "Řidič vyřazen.");
    presmeruj(odkaz("firma", ["id" => $firma["id"]]));
  }
}

/* --- Výpis -------------------------------------------------------------- */

/* Co přišlo z ARESu, přebíjí uložený stav — dokud se formulář neuloží. */
$ares = $_SESSION["ares"] ?? [];
unset($_SESSION["ares"]);

$h = function (string $klic, string $vychozi = "") use ($firma, $ares) {
  if (isset($ares[$klic]) && $ares[$klic] !== "") return (string)$ares[$klic];
  return (string)($firma[$klic] ?? $vychozi);
};

$vozidla = $firma ? radky("SELECT * FROM vozidla WHERE firma_id = ? AND aktivni = 1 ORDER BY spz", [(int)$firma["id"]]) : [];
$ridici  = $firma ? radky("SELECT * FROM ridici  WHERE firma_id = ? AND aktivni = 1 ORDER BY LOWER(jmeno)", [(int)$firma["id"]]) : [];
$posledni = $firma ? radky(
  "SELECT * FROM prepravy WHERE sablona = 0 AND (dopravce_id = ? OR zakaznik_id = ?)
    ORDER BY COALESCE(nakladka_datum, '') DESC, id DESC LIMIT 12",
  [(int)$firma["id"], (int)$firma["id"]]) : [];

hlava($nova ? "Nová firma" : $h("nazev"), "firmy");
?>
<a class="app-zpet" href="<?= chran(odkaz("firmy")) ?>">← Zpět na seznam firem</a>
<?php
$akce_firmy = "";
if (!$nova && (int)$h("dispecink") === 1) {
  $akce_firmy .= '<a class="tlacitko" href="' . chran(odkaz("vozy", ["klient" => $firma["id"]])) . '">Plán vozů</a>';
  if (vidi_ceny()) $akce_firmy .= '<a class="tlacitko obrys" href="' . chran(odkaz("fakturace", ["pohled" => "dispecink"])) . '">Podklad k fakturaci služby</a>';
}
hlava_stranky($nova ? "Adresář" : (TYPY_FIREM[$h("typ")] ?? "Firma") . ((int)$h("dispecink") === 1 ? " · klient dispečinku" : ""),
  $nova ? "Nová firma" : $h("nazev"), $akce_firmy);
?>

<div class="app-sloupce">
  <div>
    <form method="post" action="<?= chran(odkaz("firma", ["id" => $nova ? "nova" : $firma["id"]])) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="ulozit">

      <div class="skupina">
        <h2>Základní údaje</h2>
        <div class="pole-radek">
          <div class="pole">
            <label for="nazev">Název firmy</label>
            <input type="text" id="nazev" name="nazev" value="<?= chran($h("nazev")) ?>" required>
          </div>
          <div class="pole">
            <label for="typ">Typ</label>
            <select id="typ" name="typ"><?= volby(TYPY_FIREM, $h("typ", "dopravce")) ?></select>
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="ico">IČO</label>
            <div class="pole-s-tlacitkem">
              <input type="text" id="ico" name="ico" value="<?= chran($h("ico")) ?>" inputmode="numeric" maxlength="8">
              <button type="submit" name="ares" value="1" class="tlacitko obrys"
                      title="Doplní název, adresu a DIČ z veřejného rejstříku">Z ARES</button>
            </div>
          </div>
          <div class="pole">
            <label for="dic">DIČ</label>
            <input type="text" id="dic" name="dic" value="<?= chran($h("dic")) ?>">
          </div>
          <div class="pole">
            <label for="splatnost">Splatnost <span class="napoveda">dnů</span></label>
            <input type="number" id="splatnost" name="splatnost" value="<?= chran($h("splatnost")) ?>" min="0" max="180">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Sídlo</h2>
        <div class="pole">
          <label for="ulice">Ulice a číslo</label>
          <input type="text" id="ulice" name="ulice" value="<?= chran($h("ulice")) ?>">
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="psc">PSČ</label>
            <input type="text" id="psc" name="psc" value="<?= chran($h("psc")) ?>">
          </div>
          <div class="pole">
            <label for="mesto">Město</label>
            <input type="text" id="mesto" name="mesto" value="<?= chran($h("mesto")) ?>">
          </div>
          <div class="pole">
            <label for="stat">Stát</label>
            <input type="text" id="stat" name="stat" value="<?= chran($h("stat", "Česká republika")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Kontakt</h2>
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
            <label for="kontakt_email">E-mail</label>
            <input type="email" id="kontakt_email" name="kontakt_email" value="<?= chran($h("kontakt_email")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Prověření dopravce</h2>
        <p class="app-perex">U zákazníka se nevyplňuje. Datum si zapište, ať je vidět, kdy se prověření dělalo naposled.</p>
        <?php
        $body = [
          "prov_registry"  => "Firma a IČO ověřené v registrech",
          "prov_opravneni" => "Koncese nebo oprávnění k silniční dopravě",
          "prov_pojisteni" => "Pojištění odpovědnosti dopravce pro vnitrostátní přepravu po ČR",
          "prov_doklady"   => "Doklady vozidla a řidiče",
          "prov_reference" => "Reference a předchozí zkušenosti",
        ];
        foreach ($body as $klic => $popis): ?>
          <div class="pole-zaskrtnuti">
            <input type="checkbox" id="<?= $klic ?>" name="<?= $klic ?>" value="1"<?= (int)$h($klic) === 1 ? " checked" : "" ?>>
            <label for="<?= $klic ?>"><?= chran($popis) ?></label>
          </div>
        <?php endforeach; ?>
        <div class="pole-radek">
          <div class="pole">
            <label for="prov_datum">Datum prověření</label>
            <input type="date" id="prov_datum" name="prov_datum" value="<?= chran($h("prov_datum")) ?>">
          </div>
          <div class="pole">
            <label for="prov_poznamka">Poznámka k prověření</label>
            <input type="text" id="prov_poznamka" name="prov_poznamka" value="<?= chran($h("prov_poznamka")) ?>">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Externí dispečink</h2>
        <div class="pole-zaskrtnuti">
          <input type="checkbox" id="dispecink" name="dispecink" value="1"<?= (int)$h("dispecink") === 1 ? " checked" : "" ?>>
          <label for="dispecink">Klient externího dispečinku <span class="napoveda">— jeho vozy řídíme my. Jízdy jeho vozů se vedou pod dispečinkem, mají vlastní plán a nepočítají se do tržby ani marže spedice.</span></label>
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="dispecink_uctovani">Způsob účtování služby</label>
            <select id="dispecink_uctovani" name="dispecink_uctovani"><?= volby(DISPECINK_UCTOVANI, $h("dispecink_uctovani"), "— nezadáno —") ?></select>
          </div>
          <div class="pole">
            <label for="dispecink_sazba">Sazba <span class="napoveda">Kč bez DPH; u procenta číslo v %</span></label>
            <input type="text" id="dispecink_sazba" name="dispecink_sazba" value="<?= chran($h("dispecink_sazba")) ?>" inputmode="decimal">
          </div>
        </div>
        <div class="pole">
          <label for="dispecink_poznamka">Dohoda s klientem <span class="napoveda">— co je v ceně, fakturační období, kontakt na účtárnu</span></label>
          <input type="text" id="dispecink_poznamka" name="dispecink_poznamka" value="<?= chran($h("dispecink_poznamka")) ?>">
        </div>
        <p class="app-perex">Sazbu a způsob účtování dodá vedení firmy — dokud chybí, podklad k fakturaci služby odměnu nespočítá a řekne to.</p>
      </div>

      <div class="skupina">
        <h2>Poznámka</h2>
        <div class="pole">
          <label for="poznamka" class="jen-pro-ctecky">Poznámka</label>
          <textarea id="poznamka" name="poznamka"><?= chran($h("poznamka")) ?></textarea>
        </div>
        <div class="pole-zaskrtnuti">
          <input type="checkbox" id="aktivni" name="aktivni" value="1"<?= ($nova || (int)$h("aktivni") === 1) ? " checked" : "" ?>>
          <label for="aktivni">Firma je aktivní <span class="napoveda">— vyřazená se nenabízí u nových přeprav</span></label>
        </div>
      </div>

      <button type="submit" class="tlacitko"><?= $nova ? "Založit firmu" : "Uložit změny" ?></button>
    </form>
  </div>

  <div>
    <?php if (!$nova): ?>
      <div class="formular">
        <div class="skupina">
          <h2>Vozidla</h2>
          <?php if ($vozidla): ?>
            <ul class="protokol">
              <?php foreach ($vozidla as $v): ?>
                <li>
                  <b class="cislo"><?= chran($v["spz"]) ?></b> — <?= chran(nazev_typu_vozidla($v["typ"])) ?>
                  <?php if ($v["poznamka"]): ?><span class="druhotny"><?= chran($v["poznamka"]) ?></span><?php endif; ?>
                  <form method="post" action="<?= chran(odkaz("firma", ["id" => $firma["id"]])) ?>" style="display:inline"
                        data-potvrdit="Opravdu vozidlo <?= chran($v["spz"]) ?> vyřadit?">
                    <?= pole_token() ?>
                    <input type="hidden" name="akce" value="vozidlo_pryc">
                    <input type="hidden" name="vozidlo_id" value="<?= (int)$v["id"] ?>">
                    <button type="submit" class="odkaz-tlacitko">vyřadit</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="app-perex">Zatím žádné vozidlo.</p>
          <?php endif; ?>

          <form method="post" action="<?= chran(odkaz("firma", ["id" => $firma["id"]])) ?>" style="margin-top:14px">
            <?= pole_token() ?>
            <input type="hidden" name="akce" value="vozidlo">
            <div class="pole">
              <label for="spz">SPZ</label>
              <input type="text" id="spz" name="spz" required>
            </div>
            <div class="pole">
              <label for="vozidlo_typ">Typ</label>
              <select id="vozidlo_typ" name="vozidlo_typ"><?= volby(TYPY_VOZIDEL, "plachta") ?></select>
            </div>
            <div class="pole">
              <label for="vozidlo_poznamka">Poznámka</label>
              <input type="text" id="vozidlo_poznamka" name="vozidlo_poznamka">
            </div>
            <button type="submit" class="tlacitko obrys">Přidat vozidlo</button>
          </form>
        </div>

        <div class="skupina" style="margin-bottom:0">
          <h2>Řidiči</h2>
          <?php if ($ridici): ?>
            <ul class="protokol">
              <?php foreach ($ridici as $r): ?>
                <li>
                  <b><?= chran($r["jmeno"]) ?></b>
                  <?php if ($r["telefon"]): ?> — <a href="tel:<?= chran(preg_replace('/\s+/', '', (string)$r["telefon"])) ?>"><?= chran($r["telefon"]) ?></a><?php endif; ?>
                  <form method="post" action="<?= chran(odkaz("firma", ["id" => $firma["id"]])) ?>" style="display:inline"
                        data-potvrdit="Opravdu řidiče <?= chran($r["jmeno"]) ?> vyřadit?">
                    <?= pole_token() ?>
                    <input type="hidden" name="akce" value="ridic_pryc">
                    <input type="hidden" name="ridic_id" value="<?= (int)$r["id"] ?>">
                    <button type="submit" class="odkaz-tlacitko">vyřadit</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="app-perex">Zatím žádný řidič.</p>
          <?php endif; ?>

          <form method="post" action="<?= chran(odkaz("firma", ["id" => $firma["id"]])) ?>" style="margin-top:14px">
            <?= pole_token() ?>
            <input type="hidden" name="akce" value="ridic">
            <div class="pole">
              <label for="ridic_jmeno">Jméno</label>
              <input type="text" id="ridic_jmeno" name="ridic_jmeno" required>
            </div>
            <div class="pole">
              <label for="ridic_telefon">Telefon</label>
              <input type="tel" id="ridic_telefon" name="ridic_telefon">
            </div>
            <button type="submit" class="tlacitko obrys">Přidat řidiče</button>
          </form>
        </div>
      </div>

      <?php if ($posledni): ?>
        <div class="formular" style="margin-top:20px">
          <div class="skupina" style="margin-bottom:0">
            <h2>Poslední přepravy</h2>
            <ul class="protokol">
              <?php foreach ($posledni as $p): ?>
                <li>
                  <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
                  — <?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?>
                  <time><?= chran(datum($p["nakladka_datum"])) ?> · <?= chran(nazev_stavu($p["stav"])) ?></time>
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

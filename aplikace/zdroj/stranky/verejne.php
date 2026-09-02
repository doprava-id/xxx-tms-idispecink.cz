<?php
/* Veřejná stránka bez hesla — zákazník, dopravce nebo řidič.

   Kód v adrese vybírá přepravu i roli. Každá role vidí jen své:
   zákazník stav, termíny, místa a svou cenu; dopravce svou objednávku
   a může ji potvrdit, doplnit vůz a řidiče, nahlásit zpoždění a nahrát
   doklady; řidič pokyny a odklikává splněné body. Cena dopravce
   a marže se tu neukazují nikdy, cena zákazníka jen zákazníkovi.

   Kód je v adrese, proto stránka neposílá referrer a odkazy ven ho
   nenesou — jinak by se dal vyčíst z logů cizích serverů. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Chybová stránka bez jakéhokoliv údaje o přepravě. */
function verejne_chyba(string $nadpis, string $text, int $stav = 404): void {
  http_response_code($stav);
  hlava($nadpis, "", ["bez_navigace" => true, "referrer" => "no-referrer"]);
  ?>
  <div class="verejne">
    <header class="verejne-hlava">
      <img src="../assets/img/logo-idispecink.svg" alt="iDispečink.cz" width="180" height="29">
    </header>
    <h1><?= chran($nadpis) ?></h1>
    <p class="app-perex"><?= chran($text) ?></p>
    <p class="app-perex">Provoz: <a href="tel:<?= chran(preg_replace('/\s+/', '', nastaveni("firma_telefon"))) ?>" rel="noreferrer"><?= chran(nastaveni("firma_telefon")) ?></a></p>
  </div>
  <?php
  pata();
  exit;
}

if (verejne_pokusy_vycerpany()) {
  verejne_chyba("Příliš mnoho pokusů", "Z této adresy přišlo příliš mnoho neplatných odkazů. Zkuste to za čtvrt hodiny.", 429);
}

$odkaz = odkaz_podle_kodu(vstup("k"));
$p = $odkaz ? radek(
  "SELECT p.*, d.nazev AS d_nazev, d.kontakt_telefon AS d_telefon, z.nazev AS z_nazev
     FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.id = ?", [(int)$odkaz["preprava_id"]]) : null;

if (!$odkaz || !$p || (int)$p["sablona"] === 1 || !odkaz_plati($odkaz, $p)) {
  verejne_zapis_pokus();
  verejne_chyba("Odkaz neplatí", "Odkaz je neplatný, byl zrušený, nebo už vypršel — platí měsíc po vykládce. Ozvěte se prosím dispečinku.");
}

$druh = (string)$odkaz["druh"];
$id   = (int)$p["id"];
$body = body_prepravy($id);
$zpet = verejna_adresa((string)$odkaz["kod"]);

/* --- Akce dopravce a řidiče ------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($druh === "dopravce" && $akce === "potvrdit") {
    $zmeny = ["potvrzeno_kdy" => date("Y-m-d H:i:s"), "upraveno" => date("Y-m-d H:i:s")];
    if ($p["stav"] === "nova") $zmeny["stav"] = "objednana";
    uprav("prepravy", $id, $zmeny);
    zapis_udalost($id, "Dopravce potvrdil objednávku přes odkaz");
    vzkaz("ok", "Děkujeme, objednávka je potvrzená.");

  } elseif ($druh === "dopravce" && $akce === "vozidlo") {
    $spz = mb_substr(vstup("spz"), 0, 20);
    $jmeno = mb_substr(vstup("ridic_jmeno"), 0, 80);
    $tel = mb_substr(vstup("ridic_telefon"), 0, 30);
    uprav("prepravy", $id, ["spz" => $spz, "ridic_jmeno" => $jmeno, "ridic_telefon" => $tel, "upraveno" => date("Y-m-d H:i:s")]);
    zapis_udalost($id, "Dopravce doplnil vůz a řidiče: " . trim($spz . " " . $jmeno));
    vzkaz("ok", "Vůz a řidič uloženi.");

  } elseif ($druh === "dopravce" && $akce === "zpozdeni") {
    $text = mb_substr(vstup("hlaseni"), 0, 250);
    if ($text === "") {
      vzkaz("chyba", "Napište prosím, co se děje.");
    } else {
      uprav("prepravy", $id, ["hlaseni" => $text, "hlaseni_kdy" => date("Y-m-d H:i:s")]);
      zapis_udalost($id, "Dopravce hlásí: " . $text);
      vzkaz("ok", "Hlášení předáno dispečinku.");
    }

  } elseif ($druh === "dopravce" && $akce === "doklady") {
    $id_prilohy = priloha_uloz((array)($_FILES["soubor"] ?? []), $id, $chyba);
    if ($id_prilohy) {
      zapis_udalost($id, "Dopravce nahrál doklad " . (string)($_FILES["soubor"]["name"] ?? ""));
      if ($p["doklady"] === "chybi") uprav("prepravy", $id, ["doklady" => "ceka"]);
      vzkaz("ok", "Doklad nahrán. Děkujeme.");
    } else {
      vzkaz("chyba", (string)$chyba);
    }

  } elseif ($druh === "ridic" && $akce === "splnit") {
    $bod = radek("SELECT * FROM body WHERE id = ? AND preprava_id = ?", [vstup_cislo("bod_id"), $id]);
    if ($bod && (int)$bod["splneno"] === 0) {
      splnit_bod((int)$bod["id"], true);
      zapis_udalost($id, "Řidič splnil: " . nazev_druhu($bod["druh"]) . " " . $bod["misto"]);
      vzkaz("ok", nazev_druhu($bod["druh"]) . " " . $bod["misto"] . " — hotovo.");
    }
  }
  presmeruj($zpet);
}

/* --- Výpis -------------------------------------------------------------- */

$telefon_firmy = (string)nastaveni("firma_telefon");
$tel_odkaz = fn(string $t) => "tel:" . preg_replace('/[^\d+]/', "", $t);

hlava(DRUHY_ODKAZU[$druh] . " · " . $p["cislo"], "", ["bez_navigace" => true, "referrer" => "no-referrer"]);
?>
<div class="verejne">
  <header class="verejne-hlava">
    <img src="../assets/img/logo-idispecink.svg" alt="iDispečink.cz" width="180" height="29">
    <span class="app-znacka" style="margin:0"><?= chran(nastaveni("firma_nazev")) ?></span>
  </header>

  <?php if ($druh === "zakaznik"): ?>
    <span class="nadpis-stitek">Vaše zásilka</span>
    <h1 class="cislo"><?= chran($p["cislo"]) ?></h1>
    <p class="app-perex"><?= chran(popis_trasy($body)) ?><?= $p["ref_zakaznika"] ? " · vaše reference " . chran($p["ref_zakaznika"]) : "" ?></p>

    <div class="dlazdice" style="grid-template-columns:repeat(2,1fr)">
      <div class="dlazdice-polozka"><span class="popis">Stav</span><span class="hodnota" style="font-family:inherit;font-size:1.2rem"><?= stitek_stavu($p["stav"]) ?></span></div>
      <div class="dlazdice-polozka"><span class="popis">Cena přepravy</span><span class="hodnota"><?= chran(castka($p["cena_zakaznik"])) ?></span><span class="doplnek">bez DPH</span></div>
    </div>

    <h2>Průběh</h2>
    <ol class="body-verejne">
      <?php foreach ($body as $b): ?>
        <li class="<?= (int)$b["splneno"] === 1 ? "hotovo" : "" ?>">
          <b><?= chran(nazev_druhu($b["druh"])) ?> · <?= chran($b["misto"] ?: "—") ?></b>
          <?php if ($b["adresa"]): ?><span><?= chran($b["adresa"]) ?></span><?php endif; ?>
          <span><?= chran(datum($b["datum"])) ?> <?= chran(okno($b["od"], $b["do"])) ?></span>
          <?php if ((int)$b["splneno"] === 1): ?><span class="stitek stitek-hotovo">splněno <?= chran(datum_cas($b["splneno_kdy"])) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php $naklad = array_filter([$p["zbozi"] ?: null, $p["hmotnost"] ? cislo($p["hmotnost"]) . " kg" : null, $p["palet"] ? (int)$p["palet"] . " palet" : null]); ?>
    <?php if ($naklad): ?><p class="app-perex">Zboží: <?= chran(implode(" · ", $naklad)) ?></p><?php endif; ?>

  <?php elseif ($druh === "dopravce"): ?>
    <?php $u = objednavka_udaje($p); ?>
    <span class="nadpis-stitek">Objednávka přepravy</span>
    <h1 class="cislo"><?= chran($p["cislo"]) ?></h1>
    <p class="app-perex"><?= chran($u["trasa"]) ?></p>

    <?php if ($p["potvrzeno_kdy"]): ?>
      <p class="vzkaz vzkaz-ok">Potvrzeno <?= chran(datum_cas($p["potvrzeno_kdy"])) ?>. Děkujeme.</p>
    <?php else: ?>
      <form method="post" action="<?= chran($zpet) ?>" class="formular" style="margin-bottom:20px">
        <?= pole_token() ?><input type="hidden" name="akce" value="potvrdit">
        <p class="app-perex" style="margin:0 0 12px">Přijímáte objednávku za sjednaných podmínek?</p>
        <button type="submit" class="tlacitko">Potvrzuji objednávku</button>
      </form>
    <?php endif; ?>

    <div class="tabulka-obal">
      <table class="id-tabulka"><tbody>
        <tr><td>Objednatel</td><td><b><?= chran($u["firma"]) ?></b><br><?= chran($u["firma_adresa"]) ?><br>IČO <?= chran($u["firma_ico"]) ?><?= $u["firma_dic"] ? ", DIČ " . chran($u["firma_dic"]) : "" ?></td></tr>
        <tr><td>Dopravce</td><td><?= chran($u["dopravce"]) ?></td></tr>
        <?php foreach ($body as $b): ?>
          <tr><td><?= count($body) > 2 ? (int)$b["poradi"] . ". " : "" ?><?= chran(nazev_druhu($b["druh"])) ?></td><td>
            <b><?= chran($b["misto"] ?: "—") ?></b><?= $b["adresa"] ? "<br>" . chran($b["adresa"]) : "" ?><?= $b["kontakt"] ? "<br>" . chran($b["kontakt"]) : "" ?>
            <br><?= chran(datum($b["datum"])) ?> <?= chran(okno($b["od"], $b["do"])) ?><?= $b["poznamka"] ? "<br><i>" . chran($b["poznamka"]) . "</i>" : "" ?>
          </td></tr>
        <?php endforeach; ?>
        <?php if ($u["naklad"] !== ""): ?><tr><td>Zboží</td><td><?= chran($u["naklad"]) ?></td></tr><?php endif; ?>
        <tr><td>Vozidlo</td><td><?= chran($u["vozidlo"]) ?></td></tr>
        <tr><td>Sjednaná cena</td><td><b class="cislo"><?= chran($u["cena"]) ?></b></td></tr>
      </tbody></table>
    </div>
    <?php if ($u["pokyny"] !== ""): ?><h2>Pokyny</h2><p class="podminky" style="white-space:pre-line"><?= chran($u["pokyny"]) ?></p><?php endif; ?>
    <?php if ($u["podminky"] !== ""): ?><h2>Podmínky</h2><p class="podminky" style="white-space:pre-line;color:var(--text-tlum);font-size:.9rem"><?= chran($u["podminky"]) ?></p><?php endif; ?>

    <h2>Vůz a řidič</h2>
    <form method="post" action="<?= chran($zpet) ?>" class="formular">
      <?= pole_token() ?><input type="hidden" name="akce" value="vozidlo">
      <div class="pole-radek tri">
        <div class="pole"><label for="spz">SPZ</label><input type="text" id="spz" name="spz" value="<?= chran($p["spz"]) ?>" maxlength="20"></div>
        <div class="pole"><label for="ridic_jmeno">Řidič</label><input type="text" id="ridic_jmeno" name="ridic_jmeno" value="<?= chran($p["ridic_jmeno"]) ?>" maxlength="80"></div>
        <div class="pole"><label for="ridic_telefon">Telefon na řidiče</label><input type="tel" id="ridic_telefon" name="ridic_telefon" value="<?= chran($p["ridic_telefon"]) ?>" maxlength="30"></div>
      </div>
      <button type="submit" class="tlacitko obrys">Uložit vůz a řidiče</button>
    </form>

    <h2>Nahlásit zpoždění nebo změnu</h2>
    <form method="post" action="<?= chran($zpet) ?>" class="formular">
      <?= pole_token() ?><input type="hidden" name="akce" value="zpozdeni">
      <?php if ($p["hlaseni"]): ?><p class="app-perex">Poslední hlášení <?= chran(datum_cas($p["hlaseni_kdy"])) ?>: „<?= chran($p["hlaseni"]) ?>"</p><?php endif; ?>
      <div class="pole"><label for="hlaseni">Zpráva pro dispečink</label><input type="text" id="hlaseni" name="hlaseni" maxlength="250" placeholder="Nestihneme okno, budeme v 15:30." required></div>
      <button type="submit" class="tlacitko obrys">Odeslat hlášení</button>
    </form>

    <h2>Doklady</h2>
    <?php $prilohy = radky("SELECT nazev, kdy FROM prilohy WHERE preprava_id = ? ORDER BY id", [$id]); ?>
    <?php if ($prilohy): ?>
      <ul class="protokol"><?php foreach ($prilohy as $pr): ?><li><?= chran($pr["nazev"]) ?><time><?= chran(datum_cas($pr["kdy"])) ?></time></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" action="<?= chran($zpet) ?>" class="formular" enctype="multipart/form-data">
      <?= pole_token() ?><input type="hidden" name="akce" value="doklady">
      <div class="pole"><label for="soubor">Dodací list, přepravní list nebo fotka</label><input type="file" id="soubor" name="soubor" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" required></div>
      <button type="submit" class="tlacitko obrys">Nahrát doklad</button>
      <p class="formular-poznamka">PDF nebo fotka do 8 MB. Po nahrání ho dispečink uvidí u přepravy.</p>
    </form>

  <?php else: /* řidič */ ?>
    <span class="nadpis-stitek">Pokyny k jízdě</span>
    <h1 class="cislo"><?= chran($p["cislo"]) ?></h1>
    <p class="app-perex"><?= chran(popis_trasy($body)) ?><?= $p["spz"] ? " · " . chran($p["spz"]) : "" ?><?= $p["ref_zakaznika"] ? " · ref. " . chran($p["ref_zakaznika"]) : "" ?></p>

    <ol class="body-verejne ridic">
      <?php foreach ($body as $b): ?>
        <li class="<?= (int)$b["splneno"] === 1 ? "hotovo" : "" ?>">
          <b><?= (int)$b["poradi"] ?>. <?= chran(nazev_druhu($b["druh"])) ?> · <?= chran($b["misto"] ?: "—") ?></b>
          <?php if ($b["adresa"]): ?><span><?= chran($b["adresa"]) ?></span><?php endif; ?>
          <span><?= chran(datum($b["datum"])) ?> <?= chran(okno($b["od"], $b["do"])) ?></span>
          <?php if ($b["kontakt"]): ?><span>Kontakt: <?= chran($b["kontakt"]) ?></span><?php endif; ?>
          <?php $z = array_filter([$b["zbozi"] ?: null, $b["hmotnost"] ? cislo($b["hmotnost"]) . " kg" : null, $b["palet"] ? (int)$b["palet"] . " pal." : null]); ?>
          <?php if ($z): ?><span><?= chran(implode(" · ", $z)) ?></span><?php endif; ?>
          <?php if ($b["poznamka"]): ?><span><i><?= chran($b["poznamka"]) ?></i></span><?php endif; ?>
          <?php if ((int)$b["splneno"] === 1): ?>
            <span class="stitek stitek-hotovo">splněno <?= chran(datum_cas($b["splneno_kdy"])) ?></span>
          <?php else: ?>
            <form method="post" action="<?= chran($zpet) ?>" data-potvrdit="Označit <?= chran(mb_strtolower(nazev_druhu($b["druh"]))) ?> <?= chran($b["misto"]) ?> jako splněnou?">
              <?= pole_token() ?><input type="hidden" name="akce" value="splnit"><input type="hidden" name="bod_id" value="<?= (int)$b["id"] ?>">
              <button type="submit" class="tlacitko">Splněno</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php $naklad = array_filter([$p["zbozi"] ?: null, $p["hmotnost"] ? cislo($p["hmotnost"]) . " kg" : null, $p["palet"] ? (int)$p["palet"] . " palet" : null, $p["pozadavky"] ?: null]); ?>
    <?php if ($naklad): ?><p class="app-perex">Náklad: <?= chran(implode(" · ", $naklad)) ?></p><?php endif; ?>
    <?php if (trim((string)$p["poznamka_dopravci"]) !== ""): ?><h2>Pokyny</h2><p style="white-space:pre-line"><?= chran($p["poznamka_dopravci"]) ?></p><?php endif; ?>
  <?php endif; ?>

  <p class="verejne-pata">Dispečink <?= chran(nastaveni("firma_nazev")) ?>:
    <a href="<?= chran($tel_odkaz($telefon_firmy)) ?>" rel="noreferrer"><?= chran($telefon_firmy) ?></a>
    · odkaz platí do <?= chran(datum(odkaz_platnost_do($odkaz, $p))) ?></p>
</div>
<?php
pata();

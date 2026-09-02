<?php
/* Objednávka přepravy pro dopravce — stránka k tisku a k uložení do PDF
   přes tisk prohlížeče. Číslo objednávky je číslo přepravy: jedna
   přeprava, jedna objednávka, žádné druhé číslování.

   Podmínky pro dopravce se berou z Nastavení. Nejsou-li vyplněné,
   objednávka to viditelně přizná — vymyslet se nedají. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$id = vstup_cislo("id");
$p = $id ? radek(
  "SELECT p.*, d.nazev AS d_nazev, d.ico AS d_ico, d.dic AS d_dic,
          d.ulice AS d_ulice, d.mesto AS d_mesto, d.psc AS d_psc,
          d.kontakt_jmeno AS d_osoba, d.kontakt_telefon AS d_telefon, d.kontakt_email AS d_email,
          z.nazev AS z_nazev
     FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.id = ?", [$id]) : null;

if (!$p) {
  vzkaz("chyba", "Přeprava nenalezena.");
  presmeruj(odkaz("prepravy"));
}
if (empty($p["dopravce_id"])) {
  vzkaz("chyba", "Objednávku nelze vystavit, dokud přeprava nemá dopravce.");
  presmeruj(odkaz("preprava", ["id" => $p["id"]]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && vstup("akce") === "vystavena") {
  $zmeny = ["objednavka_datum" => date("Y-m-d H:i:s"), "upraveno" => date("Y-m-d H:i:s")];
  if ($p["stav"] === "nova") $zmeny["stav"] = "objednana";
  uprav("prepravy", (int)$p["id"], $zmeny);
  zapis_udalost((int)$p["id"], "Objednávka vystavena dopravci " . (string)$p["d_nazev"]);
  vzkaz("ok", "Objednávka označena jako vystavená.");
  presmeruj(odkaz("objednavka", ["id" => $p["id"]]));
}

$podminky = trim(nastaveni("podminky"));
$adresa_nas = trim(nastaveni("firma_ulice") . ", " . nastaveni("firma_psc") . " " . nastaveni("firma_mesto"), ", ");
$adresa_dopravce = trim(((string)$p["d_ulice"]) . ", " . ((string)$p["d_psc"]) . " " . ((string)$p["d_mesto"]), ", ");

hlava("Objednávka " . $p["cislo"], "", ["bez_navigace" => true]);
?>
<div class="netisknout" style="max-width:900px;margin:0 auto 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <a class="tlacitko obrys" href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>">← Zpět na přepravu</a>
  <button type="button" class="tlacitko" onclick="window.print()">Vytisknout / uložit PDF</button>
  <?php if (!$p["objednavka_datum"]): ?>
    <form method="post" action="<?= chran(odkaz("objednavka", ["id" => $p["id"]])) ?>" style="margin:0">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="vystavena">
      <button type="submit" class="tlacitko obrys">Označit jako vystavenou</button>
    </form>
  <?php else: ?>
    <span class="app-perex" style="margin:0">Vystavena <?= chran(datum_cas($p["objednavka_datum"])) ?>.</span>
  <?php endif; ?>
</div>

<article class="objednavka">
  <header class="objednavka-hlava">
    <div>
      <img src="../assets/img/logo-idispecink.svg" alt="iDispečink.cz" width="240" height="38">
      <p style="margin-top:10px;font-size:.9rem"><b><?= chran(nastaveni("firma_nazev")) ?></b><br>
        <?= chran($adresa_nas) ?><br>
        IČO <?= chran(nastaveni("firma_ico")) ?><?= nastaveni("firma_dic") ? ", DIČ " . chran(nastaveni("firma_dic")) : "" ?><br>
        <?= chran(nastaveni("firma_telefon")) ?> · <?= chran(nastaveni("firma_email")) ?></p>
    </div>
    <div class="cislo">
      <span class="nadpis-stitek">Objednávka přepravy</span>
      <b class="cislo"><?= chran($p["cislo"]) ?></b>
      <p style="font-size:.88rem;margin-top:6px">Vystaveno <?= chran(datum($p["objednavka_datum"] ?: date("Y-m-d"))) ?></p>
    </div>
  </header>

  <div class="strany">
    <div>
      <h2>Objednatel</h2>
      <p><b><?= chran(nastaveni("firma_nazev")) ?></b><br>
        <?= chran($adresa_nas) ?><br>
        IČO <?= chran(nastaveni("firma_ico")) ?><br>
        <?= chran(nastaveni("firma_zapis")) ?></p>
    </div>
    <div>
      <h2>Dopravce</h2>
      <p><b><?= chran($p["d_nazev"]) ?></b><br>
        <?= chran($adresa_dopravce ?: "—") ?><br>
        IČO <?= chran($p["d_ico"] ?: "—") ?><?= $p["d_dic"] ? ", DIČ " . chran($p["d_dic"]) : "" ?><br>
        <?= chran($p["d_osoba"] ?: "") ?><?= $p["d_telefon"] ? " · " . chran($p["d_telefon"]) : "" ?></p>
    </div>
  </div>

  <h2>Přeprava</h2>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <tbody>
        <?php
        /* Trasa bod po bodu. U jízdy s víc než dvěma body je pořadí
           závazné — dopravce má vědět, kudy jede. */
        $body = body_prepravy((int)$p["id"]);
        $vic_bodu = count($body) > 2;
        foreach ($body as $b): ?>
          <tr><td><?= $vic_bodu ? (int)$b["poradi"] . ". " : "" ?><?= chran(nazev_druhu($b["druh"])) ?></td><td>
            <b><?= chran($b["misto"] ?: "—") ?></b>
            <?= $b["adresa"] ? "<br>" . chran($b["adresa"]) : "" ?>
            <?= $b["kontakt"] ? "<br>" . chran($b["kontakt"]) : "" ?>
            <br><?= chran(datum($b["datum"])) ?> <?= chran(okno($b["od"], $b["do"])) ?>
            <?php
              $d = [];
              if ($b["zbozi"])    $d[] = (string)$b["zbozi"];
              if ($b["hmotnost"]) $d[] = cislo($b["hmotnost"]) . " kg";
              if ($b["palet"])    $d[] = (int)$b["palet"] . " palet";
              if ($d) echo "<br>" . chran(implode(" · ", $d));
              if ($b["poznamka"]) echo "<br><i>" . chran($b["poznamka"]) . "</i>";
            ?>
          </td></tr>
        <?php endforeach; ?>
        <tr><td>Zboží</td><td><?= chran($p["zbozi"] ?: "—") ?><?php
          $doplnky = [];
          if ($p["hmotnost"]) $doplnky[] = cislo($p["hmotnost"]) . " kg";
          if ($p["palet"])    $doplnky[] = (int)$p["palet"] . " palet";
          if ($p["ldm"])      $doplnky[] = cislo($p["ldm"], 1) . " LDM";
          if ($doplnky) echo "<br>" . chran(implode(" · ", $doplnky));
        ?></td></tr>
        <tr><td>Vozidlo</td><td><?= chran(nazev_typu_vozidla($p["typ_vozidla"])) ?><?php
          if ($p["spz"]) echo "<br>SPZ <span class=\"cislo\">" . chran($p["spz"]) . "</span>";
          if ($p["pozadavky"]) echo "<br>" . chran($p["pozadavky"]);
        ?></td></tr>
        <tr><td>Řidič</td><td><?= chran($p["ridic_jmeno"] ?: "—") ?><?= $p["ridic_telefon"] ? " · " . chran($p["ridic_telefon"]) : "" ?></td></tr>
        <tr><td>Sjednaná cena</td><td><b class="cislo"><?= chran(castka($p["cena_dopravce"])) ?></b> bez DPH</td></tr>
      </tbody>
    </table>
  </div>

  <?php if (trim((string)$p["poznamka_dopravci"]) !== ""): ?>
    <h2>Pokyny</h2>
    <p class="podminky"><?= chran($p["poznamka_dopravci"]) ?></p>
  <?php endif; ?>

  <h2>Podmínky</h2>
  <?php if ($podminky !== ""): ?>
    <p class="podminky"><?= chran($podminky) ?></p>
  <?php else: ?>
    <!-- PLACEHOLDER: text podmínek objednávky dodá zadavatel, viz Nastavení. -->
    <div class="doplnit">
      <b>Podmínky nejsou vyplněné.</b> Text podmínek pro dopravce se vkládá
      v Nastavení provozního systému. Dokud tam není, objednávka žádné
      podmínky neuvádí — a vymyslet se nedají.
    </div>
  <?php endif; ?>

  <div class="podpisy">
    <div>Za objednatele — <?= chran(nastaveni("firma_nazev")) ?></div>
    <div>Za dopravce — <?= chran($p["d_nazev"]) ?></div>
  </div>
</article>
<?php
pata();

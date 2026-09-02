<?php
/* Fakturace a přehledy — obrat, marže a podklady k fakturaci za období.

   Podklad po dopravcích potřebuje každý dispečer (týdenní fakturace za
   odjeté přepravy). Obchodní strana — tržba, marže a podklad po
   zákaznících — je jen pro toho, kdo na ceny zákazníka má právo. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$pohled = vstup("pohled", "dopravci");
if (!in_array($pohled, ["dopravci", "zakaznici", "chybi", "faktury", "pohledavky", "zavazky"], true)) $pohled = "dopravci";
if (in_array($pohled, ["zakaznici", "pohledavky"], true) && !$ceny) $pohled = "dopravci";

$od = vstup_datum("od") ?: date("Y-m-01");
$do = vstup_datum("do") ?: date("Y-m-t");

/* Období se počítá podle data vykládky — fakturuje se za odjeté přepravy.
   Bez data vykládky rozhoduje datum nakládky. */
const OBDOBI_SLOUPEC = "COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum)";

$kde = OBDOBI_SLOUPEC . " BETWEEN ? AND ? AND p.stav <> 'zruseno' AND p.sablona = 0";
$parametry = [$od, $do];

$souhrn = radek(
  "SELECT COUNT(*) AS pocet,
          COALESCE(SUM(p.cena_zakaznik), 0) AS trzba,
          COALESCE(SUM(p.cena_dopravce), 0) AS naklad
     FROM prepravy p WHERE " . $kde, $parametry);

$marze = (float)$souhrn["trzba"] - (float)$souhrn["naklad"];

/* --- Zápis: faktury, úhrady, Fakturoid ---------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");
  $navrat = odkaz("fakturace", ["pohled" => vstup("pohled", "faktury"), "od" => $od, "do" => $do]);

  if ($akce === "faktura_ulozit") {
    $druh = vstup("druh") === "prijata" ? "prijata" : "vydana";
    if ($druh === "vydana" && !$ceny) { vzkaz("chyba", "Vydané faktury smí zapisovat jen ten, kdo vidí ceny zákazníka."); presmeruj($navrat); }
    $cislo = vstup("cislo");
    if ($cislo === "") { vzkaz("chyba", "Číslo faktury je povinné."); presmeruj($navrat); }
    faktura_uloz($druh, $cislo, [
      "firma_id"     => vstup_cislo("firma_id") ?: null,
      "castka"       => vstup_castka("castka"),
      "castka_s_dph" => vstup_castka("castka_s_dph"),
      "vystaveno"    => vstup_datum("vystaveno"),
      "splatnost"    => vstup_datum("splatnost"),
      "uhrazeno"     => vstup_datum("uhrazeno"),
      "poznamka"     => vstup("poznamka"),
    ]);
    zapis_udalost(null, DRUHY_FAKTUR[$druh] . " faktura " . $cislo . " zapsána");
    vzkaz("ok", "Faktura " . $cislo . " uložena.");
    presmeruj($navrat);

  } elseif ($akce === "faktura_zaplaceno") {
    $f = radek("SELECT * FROM faktury WHERE id = ?", [vstup_cislo("faktura_id")]);
    if ($f && ($f["druh"] === "prijata" || $ceny)) {
      uprav("faktury", (int)$f["id"], ["uhrazeno" => vstup_datum("uhrazeno") ?: date("Y-m-d"), "stav" => $f["stav"] ? "paid" : null, "upraveno" => date("Y-m-d H:i:s")]);
      zapis_udalost(null, "Faktura " . $f["cislo"] . " označena jako zaplacená");
      vzkaz("ok", "Faktura " . $f["cislo"] . " je zaplacená.");
    }
    presmeruj($navrat);

  } elseif ($akce === "faktura_smazat") {
    $f = radek("SELECT * FROM faktury WHERE id = ?", [vstup_cislo("faktura_id")]);
    if ($f && je_spravce()) {
      dotaz("DELETE FROM faktury WHERE id = ?", [(int)$f["id"]]);
      zapis_udalost(null, "Záznam faktury " . $f["cislo"] . " smazán");
      vzkaz("ok", "Záznam smazán. Čísla u přeprav zůstala.");
    }
    presmeruj($navrat);

  } elseif ($akce === "fakturoid_sync") {
    vyzaduj_ceny();
    $v = fakturoid_synchronizuj_uhrady($chyba);
    if ($chyba) vzkaz("chyba", $chyba);
    vzkaz($chyba ? "pozor" : "ok", "Fakturoid: aktualizováno " . $v["aktualizovano"] . ", nově zapsáno " . $v["zalozeno"]
      . ($v["nenalezeno"] ? ", ve Fakturoidu nenalezeno: " . implode(", ", $v["nenalezeno"]) : "") . ".");
    presmeruj($navrat);

  } elseif ($akce === "fakturoid_zalozit") {
    vyzaduj_ceny();
    $firma = radek("SELECT * FROM firmy WHERE id = ?", [vstup_cislo("firma_id")]);
    if (!$firma) { vzkaz("chyba", "Zákazník nenalezen."); presmeruj($navrat); }
    $polozky = radky(
      "SELECT p.* FROM prepravy p WHERE " . $kde . " AND p.zakaznik_id = ?
        AND (p.faktura_vydana IS NULL OR TRIM(p.faktura_vydana) = '') AND p.cena_zakaznik IS NOT NULL
        ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [(int)$firma["id"]]));
    if (!$polozky) { vzkaz("chyba", "Zákazník nemá v období žádnou nevyfakturovanou přepravu s cenou."); presmeruj($navrat); }

    $splatnost = (int)($firma["splatnost"] ?: nastaveni("splatnost_dnu", "14"));
    $dph = (float)str_replace(",", ".", nastaveni("dph_sazba", "21"));
    $vysledek = fakturoid_zaloz_fakturu($firma, $polozky, $splatnost, $dph, $chyba);
    if (!$vysledek) { vzkaz("chyba", (string)$chyba); presmeruj($navrat); }

    foreach ($polozky as $p) {
      uprav("prepravy", (int)$p["id"], ["faktura_vydana" => $vysledek["cislo"], "upraveno" => date("Y-m-d H:i:s")]);
      if (in_array($p["stav"], ["vylozeno", "doklady"], true)) uprav("prepravy", (int)$p["id"], ["stav" => "fakturovano"]);
      zapis_udalost((int)$p["id"], "Vyfakturováno ve Fakturoidu, faktura " . $vysledek["cislo"]);
    }
    faktura_uloz("vydana", $vysledek["cislo"], array_merge($vysledek["data"], ["firma_id" => (int)$firma["id"]]));
    zapis_udalost(null, "Ve Fakturoidu založena faktura " . $vysledek["cislo"] . " pro " . $firma["nazev"] . " (" . count($polozky) . " přeprav)");
    vzkaz("ok", "Ve Fakturoidu vznikla faktura " . $vysledek["cislo"] . " za " . count($polozky) . " přeprav. Číslo je zapsané u přeprav.");
    presmeruj(odkaz("fakturace", ["pohled" => "zakaznici", "od" => $od, "do" => $do]));
  }
}

$rychle = [
  "Tento týden"  => [date("Y-m-d", strtotime("monday this week")), date("Y-m-d", strtotime("sunday this week"))],
  "Minulý týden" => [date("Y-m-d", strtotime("monday last week")), date("Y-m-d", strtotime("sunday last week"))],
  "Tento měsíc"  => [date("Y-m-01"), date("Y-m-t")],
  "Minulý měsíc" => [date("Y-m-01", strtotime("first day of last month")), date("Y-m-t", strtotime("last day of last month"))],
];

hlava("Fakturace", "fakturace");
hlava_stranky("Podklady", "Fakturace a přehledy",
  '<a class="tlacitko obrys" href="' . chran(odkaz("export", ["co" => "fakturace", "od" => $od, "do" => $do])) . '">Export CSV</a>'
  . '<button type="button" class="tlacitko obrys" onclick="window.print()">Vytisknout</button>');
?>

<form method="get" action="index.php" class="filtr">
  <input type="hidden" name="s" value="fakturace">
  <input type="hidden" name="pohled" value="<?= chran($pohled) ?>">
  <div class="filtr-radek">
    <div class="pole">
      <label for="od">Období od</label>
      <input type="date" id="od" name="od" value="<?= chran($od) ?>">
    </div>
    <div class="pole">
      <label for="do">Období do</label>
      <input type="date" id="do" name="do" value="<?= chran($do) ?>">
    </div>
    <div class="filtr-akce">
      <button type="submit" class="tlacitko">Zobrazit</button>
    </div>
    <div class="filtr-akce" style="flex-wrap:wrap">
      <?php foreach ($rychle as $popis => $rozsah): ?>
        <a class="tlacitko obrys" href="<?= chran(odkaz("fakturace", ["pohled" => $pohled, "od" => $rozsah[0], "do" => $rozsah[1]])) ?>"><?= chran($popis) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="app-perex" style="margin:12px 0 0">Období se počítá podle data vykládky; u přeprav bez vykládky podle nakládky. Zrušené se nepočítají.</p>
</form>

<div class="dlazdice">
  <div class="dlazdice-polozka">
    <span class="popis">Přeprav</span>
    <span class="hodnota"><?= (int)$souhrn["pocet"] ?></span>
    <span class="doplnek"><?= chran(datum($od)) ?> – <?= chran(datum($do)) ?></span>
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Tržba</span>
      <span class="hodnota"><?= chran(castka($souhrn["trzba"])) ?></span>
      <span class="doplnek">bez DPH</span>
    </div>
  <?php endif; ?>
  <div class="dlazdice-polozka">
    <span class="popis">Náklad dopravců</span>
    <span class="hodnota"><?= chran(castka($souhrn["naklad"])) ?></span>
    <span class="doplnek">bez DPH</span>
  </div>
  <?php if ($ceny): ?>
    <div class="dlazdice-polozka">
      <span class="popis">Marže</span>
      <span class="hodnota"><?= chran(castka($marze)) ?></span>
      <span class="doplnek"><?= (float)$souhrn["trzba"] > 0 ? chran(cislo($marze / (float)$souhrn["trzba"] * 100, 1)) . " %" : "—" ?></span>
    </div>
  <?php endif; ?>
</div>

<nav class="tlacitka netisknout" style="margin-top:0;margin-bottom:20px">
  <a class="tlacitko<?= $pohled === "dopravci" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "dopravci", "od" => $od, "do" => $do])) ?>">Podle dopravců</a>
  <?php if ($ceny): ?>
    <a class="tlacitko<?= $pohled === "zakaznici" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "zakaznici", "od" => $od, "do" => $do])) ?>">Podle zákazníků</a>
  <?php endif; ?>
  <a class="tlacitko<?= $pohled === "chybi" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "chybi", "od" => $od, "do" => $do])) ?>">Chybějící údaje</a>
  <a class="tlacitko<?= $pohled === "faktury" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "faktury", "od" => $od, "do" => $do])) ?>">Faktury</a>
  <?php if ($ceny): ?><a class="tlacitko<?= $pohled === "pohledavky" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "pohledavky", "od" => $od, "do" => $do])) ?>">Pohledávky</a><?php endif; ?>
  <a class="tlacitko<?= $pohled === "zavazky" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "zavazky", "od" => $od, "do" => $do])) ?>">Závazky</a>
</nav>

<?php
/* Řádek faktury v přehledech pohledávek, závazků a faktur. */
function radek_faktury(array $f, string $pohled, string $od, string $do, bool $ceny): void {
  $navrat = odkaz("fakturace", ["pohled" => $pohled, "od" => $od, "do" => $do]);
  ?>
  <tr>
    <td class="cislo"><?= chran($f["cislo"]) ?><?php if ($f["fakturoid_id"]): ?><span class="druhotny">Fakturoid</span><?php endif; ?></td>
    <td><?= chran($f["firma_nazev"] ?: "—") ?></td>
    <td><?= chran(datum($f["vystaveno"])) ?></td>
    <td><?= chran(datum($f["splatnost"])) ?><?php
      if (isset($f["dnu_po"]) && $f["dnu_po"] !== null && $f["dnu_po"] > 0) echo '<span class="druhotny" style="color:var(--chyba-text)">' . (int)$f["dnu_po"] . ' dní po splatnosti</span>';
      if (isset($f["dnu_do"]) && $f["dnu_do"] !== null) echo '<span class="druhotny"' . ($f["dnu_do"] < 0 ? ' style="color:var(--chyba-text)"' : '') . '>' . ($f["dnu_do"] < 0 ? (-(int)$f["dnu_do"]) . ' dní po splatnosti' : 'za ' . (int)$f["dnu_do"] . ' dní') . '</span>';
    ?></td>
    <td class="cislo vpravo"><?= chran(castka($f["castka"])) ?><?php if ($f["castka_s_dph"]): ?><span class="druhotny"><?= chran(castka($f["castka_s_dph"])) ?> s DPH</span><?php endif; ?></td>
    <td><?= $f["uhrazeno"] ? '<span class="stitek stitek-hotovo">zaplaceno ' . chran(datum($f["uhrazeno"])) . '</span>' : chran(STAVY_FAKTUR[$f["stav"]] ?? "nezaplaceno") ?></td>
    <td class="netisknout" style="white-space:nowrap">
      <?php if (!$f["uhrazeno"] && ($f["druh"] === "prijata" || $ceny)): ?>
        <form method="post" action="<?= chran($navrat) ?>" style="display:inline">
          <?= pole_token() ?><input type="hidden" name="akce" value="faktura_zaplaceno"><input type="hidden" name="faktura_id" value="<?= (int)$f["id"] ?>"><input type="hidden" name="pohled" value="<?= chran($pohled) ?>">
          <button type="submit" class="tlacitko obrys" style="padding:4px 10px;font-size:.8rem">Zaplaceno dnes</button>
        </form>
      <?php endif; ?>
      <?php if (je_spravce()): ?>
        <form method="post" action="<?= chran($navrat) ?>" style="display:inline" data-potvrdit="Smazat záznam faktury <?= chran($f["cislo"]) ?>? Čísla u přeprav zůstanou.">
          <?= pole_token() ?><input type="hidden" name="akce" value="faktura_smazat"><input type="hidden" name="faktura_id" value="<?= (int)$f["id"] ?>"><input type="hidden" name="pohled" value="<?= chran($pohled) ?>">
          <button type="submit" class="odkaz-tlacitko">smazat</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php
}
?>

<?php if ($pohled === "faktury"):
  $vydane  = $ceny ? radky("SELECT f.*, z.nazev AS firma_nazev FROM faktury f LEFT JOIN firmy z ON z.id = f.firma_id WHERE f.druh = 'vydana' ORDER BY COALESCE(f.vystaveno, ''), f.id DESC LIMIT 200") : [];
  $prijate = radky("SELECT f.*, d.nazev AS firma_nazev FROM faktury f LEFT JOIN firmy d ON d.id = f.firma_id WHERE f.druh = 'prijata' ORDER BY COALESCE(f.vystaveno, ''), f.id DESC LIMIT 200");
  $bez_vydane  = $ceny ? cisla_bez_zaznamu("vydana") : [];
  $bez_prijate = cisla_bez_zaznamu("prijata");
  $firmy_vse = radky("SELECT id, nazev FROM firmy WHERE aktivni = 1 ORDER BY LOWER(nazev)");
  $volby_firem = []; foreach ($firmy_vse as $f) $volby_firem[(string)$f["id"]] = (string)$f["nazev"];
  $navrat = odkaz("fakturace", ["pohled" => "faktury", "od" => $od, "do" => $do]);
?>
  <div class="app-sloupce">
    <div>
      <?php if ($ceny): ?>
        <div class="app-hlava" style="margin-bottom:10px">
          <div><h2 style="margin:0">Vydané faktury</h2></div>
          <div class="app-hlava-akce">
            <?php if (fakturoid_nastaven()): ?>
              <form method="post" action="<?= chran($navrat) ?>"><?= pole_token() ?><input type="hidden" name="akce" value="fakturoid_sync"><input type="hidden" name="pohled" value="faktury">
                <button type="submit" class="tlacitko obrys">Načíst úhrady z Fakturoidu</button></form>
            <?php else: ?>
              <span class="app-perex">Fakturoid není napojený — přístup se doplňuje do config.php.</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!$vydane): ?><p class="prazdno">Zatím žádná vydaná faktura.</p><?php else: ?>
          <div class="tabulka-obal"><table class="id-tabulka"><thead><tr><th>Číslo</th><th>Odběratel</th><th>Vystaveno</th><th>Splatnost</th><th class="vpravo">Částka</th><th>Stav</th><th></th></tr></thead><tbody>
            <?php foreach ($vydane as $f) radek_faktury($f, "faktury", $od, $do, $ceny); ?>
          </tbody></table></div>
        <?php endif; ?>
        <?php if ($bez_vydane): ?>
          <p class="app-perex" style="margin-top:10px">U přeprav jsou zapsaná čísla vydaných faktur bez záznamu:
            <?php foreach ($bez_vydane as $b): ?><a href="<?= chran(odkaz("fakturace", ["pohled" => "faktury", "od" => $od, "do" => $do, "cislo" => $b["cislo"], "druh" => "vydana", "firma_id" => $b["firma_id"], "castka" => $b["soucet"]])) ?>" class="cislo"><?= chran($b["cislo"]) ?></a> (<?= (int)$b["prepravy"] ?>) <?php endforeach; ?>
            — kliknutím se předvyplní formulář<?= fakturoid_nastaven() ? ", nebo je načtěte z Fakturoidu" : "" ?>.</p>
        <?php endif; ?>
      <?php endif; ?>

      <h2 style="margin-top:28px">Přijaté faktury od dopravců</h2>
      <?php if (!$prijate): ?><p class="prazdno">Zatím žádná přijatá faktura.</p><?php else: ?>
        <div class="tabulka-obal"><table class="id-tabulka"><thead><tr><th>Číslo</th><th>Dopravce</th><th>Vystaveno</th><th>Splatnost</th><th class="vpravo">Částka</th><th>Stav</th><th></th></tr></thead><tbody>
          <?php foreach ($prijate as $f) radek_faktury($f, "faktury", $od, $do, $ceny); ?>
        </tbody></table></div>
      <?php endif; ?>
      <?php if ($bez_prijate): ?>
        <p class="app-perex" style="margin-top:10px">U přeprav jsou zapsaná čísla přijatých faktur bez záznamu:
          <?php foreach ($bez_prijate as $b): ?><a href="<?= chran(odkaz("fakturace", ["pohled" => "faktury", "od" => $od, "do" => $do, "cislo" => $b["cislo"], "druh" => "prijata", "firma_id" => $b["firma_id"], "castka" => $b["soucet"]])) ?>" class="cislo"><?= chran($b["cislo"]) ?></a> (<?= (int)$b["prepravy"] ?>) <?php endforeach; ?>
          — kliknutím se předvyplní formulář.</p>
      <?php endif; ?>
    </div>

    <div>
      <form method="post" action="<?= chran($navrat) ?>" class="formular" data-jednou>
        <?= pole_token() ?><input type="hidden" name="akce" value="faktura_ulozit"><input type="hidden" name="pohled" value="faktury">
        <div class="skupina" style="margin-bottom:0">
          <h2>Zapsat fakturu</h2>
          <p class="app-perex">Číslo musí být stejné, jaké je zapsané u přeprav — tím se faktura s přepravami spojí.</p>
          <div class="pole-radek">
            <div class="pole"><label for="druh">Druh</label><select id="druh" name="druh"><?= volby($ceny ? DRUHY_FAKTUR : ["prijata" => "Přijatá"], vstup("druh", "prijata")) ?></select></div>
            <div class="pole"><label for="cislo">Číslo faktury</label><input type="text" id="cislo" name="cislo" value="<?= chran(vstup("cislo")) ?>" required></div>
          </div>
          <div class="pole"><label for="firma_id">Firma</label><select id="firma_id" name="firma_id"><?= volby($volby_firem, vstup("firma_id"), "— nevybrána —") ?></select></div>
          <div class="pole-radek">
            <div class="pole"><label for="castka">Částka bez DPH</label><input type="text" id="castka" name="castka" value="<?= chran(vstup("castka")) ?>" inputmode="decimal"></div>
            <div class="pole"><label for="castka_s_dph">Částka s DPH</label><input type="text" id="castka_s_dph" name="castka_s_dph" inputmode="decimal"></div>
          </div>
          <div class="pole-radek tri">
            <div class="pole"><label for="vystaveno">Vystaveno</label><input type="date" id="vystaveno" name="vystaveno" value="<?= date("Y-m-d") ?>"></div>
            <div class="pole"><label for="splatnost">Splatnost</label><input type="date" id="splatnost" name="splatnost"></div>
            <div class="pole"><label for="uhrazeno">Zaplaceno</label><input type="date" id="uhrazeno" name="uhrazeno"></div>
          </div>
          <div class="pole"><label for="poznamka">Poznámka</label><input type="text" id="poznamka" name="poznamka"></div>
          <button type="submit" class="tlacitko">Uložit fakturu</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($pohled === "pohledavky"):
  $vse = vstup("vse") !== "";
  $seznam = pohledavky(!$vse);
  $soucet = 0; foreach ($seznam as $f) $soucet += (float)$f["castka_s_dph"] ?: (float)$f["castka"];
?>
  <div class="app-hlava" style="margin-bottom:10px">
    <div><h2 style="margin:0">Pohledávky<?= $vse ? " — všechny nezaplacené" : " po splatnosti" ?></h2>
      <p class="app-perex" style="margin:2px 0 0"><?= count($seznam) ?> faktur · <b class="cislo"><?= chran(castka($soucet)) ?></b><?= $vse ? "" : " po splatnosti" ?></p></div>
    <div class="app-hlava-akce">
      <a class="tlacitko obrys" href="<?= chran(odkaz("fakturace", array_filter(["pohled" => "pohledavky", "od" => $od, "do" => $do, "vse" => $vse ? "" : "1"]))) ?>"><?= $vse ? "Jen po splatnosti" : "Všechny nezaplacené" ?></a>
      <?php if (fakturoid_nastaven()): ?>
        <form method="post" action="<?= chran(odkaz("fakturace", ["pohled" => "pohledavky", "od" => $od, "do" => $do])) ?>"><?= pole_token() ?><input type="hidden" name="akce" value="fakturoid_sync"><input type="hidden" name="pohled" value="pohledavky">
          <button type="submit" class="tlacitko obrys">Načíst úhrady z Fakturoidu</button></form>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$seznam): ?><p class="prazdno">Nikdo nedluží<?= $vse ? "" : " po splatnosti" ?>.</p><?php else: ?>
    <div class="tabulka-obal"><table class="id-tabulka"><thead><tr><th>Číslo</th><th>Odběratel</th><th>Vystaveno</th><th>Splatnost</th><th class="vpravo">Částka</th><th>Stav</th><th></th></tr></thead><tbody>
      <?php foreach ($seznam as $f) radek_faktury($f, "pohledavky", $od, $do, $ceny); ?>
    </tbody></table></div>
  <?php endif; ?>

<?php elseif ($pohled === "zavazky"):
  $seznam = zavazky();
  $soucet = 0; foreach ($seznam as $f) $soucet += (float)$f["castka_s_dph"] ?: (float)$f["castka"];
?>
  <div class="app-hlava" style="margin-bottom:10px">
    <div><h2 style="margin:0">Závazky vůči dopravcům</h2>
      <p class="app-perex" style="margin:2px 0 0"><?= count($seznam) ?> nezaplacených faktur · <b class="cislo"><?= chran(castka($soucet)) ?></b></p></div>
  </div>
  <?php if (!$seznam): ?><p class="prazdno">Žádná nezaplacená přijatá faktura. Přijaté faktury zapisujte v pohledu Faktury.</p><?php else: ?>
    <div class="tabulka-obal"><table class="id-tabulka"><thead><tr><th>Číslo</th><th>Dopravce</th><th>Vystaveno</th><th>Splatnost</th><th class="vpravo">Částka</th><th>Stav</th><th></th></tr></thead><tbody>
      <?php foreach ($seznam as $f) radek_faktury($f, "zavazky", $od, $do, $ceny); ?>
    </tbody></table></div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($pohled === "chybi"):
  $chybne = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev
       FROM prepravy p
       LEFT JOIN firmy z ON z.id = p.zakaznik_id
       LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE " . $kde . "
        AND (p.cena_dopravce IS NULL OR (p.dopravce_id IS NULL OR p.dopravce_id = 0)"
        . ($ceny ? " OR p.cena_zakaznik IS NULL" : "") . "
            OR p.doklady <> 'prijato')
      ORDER BY " . OBDOBI_SLOUPEC . ", p.id", $parametry);
?>
  <h2>Chybějící údaje</h2>
  <p class="app-perex">Přepravy z období, u kterých něco brání fakturaci.</p>
  <?php if (!$chybne): ?>
    <p class="prazdno">V období nechybí nic.</p>
  <?php else: ?>
    <div class="tabulka-obal">
      <table class="id-tabulka">
        <thead><tr><th>Číslo</th><th>Trasa</th><th>Dopravce</th><th>Zákazník</th><th>Co chybí</th></tr></thead>
        <tbody>
        <?php foreach ($chybne as $p):
          $chybi = [];
          if (empty($p["dopravce_id"]))       $chybi[] = "dopravce";
          if ($p["cena_dopravce"] === null)   $chybi[] = "cena dopravce";
          if ($ceny && $p["cena_zakaznik"] === null) $chybi[] = "cena zákazníka";
          if ($p["doklady"] !== "prijato")    $chybi[] = "doklady";
        ?>
          <tr>
            <td><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a></td>
            <td><?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?>
              <span class="druhotny"><?= chran(datum($p["vykladka_datum"] ?: $p["nakladka_datum"])) ?></span></td>
            <td><?= chran($p["dopravce_nazev"] ?: "—") ?></td>
            <td><?= chran($p["zakaznik_nazev"] ?: "—") ?></td>
            <td><?= chran(implode(", ", $chybi)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php else:
  $po_firmach = $pohled === "dopravci"
    ? radky("SELECT f.id, f.nazev, COUNT(*) AS pocet, COALESCE(SUM(p.cena_dopravce), 0) AS soucet
               FROM prepravy p JOIN firmy f ON f.id = p.dopravce_id
              WHERE " . $kde . " GROUP BY f.id, f.nazev ORDER BY soucet DESC", $parametry)
    : radky("SELECT f.id, f.nazev, COUNT(*) AS pocet, COALESCE(SUM(p.cena_zakaznik), 0) AS soucet
               FROM prepravy p JOIN firmy f ON f.id = p.zakaznik_id
              WHERE " . $kde . " GROUP BY f.id, f.nazev ORDER BY soucet DESC", $parametry);
?>
  <h2><?= $pohled === "dopravci" ? "Podklad k fakturaci dopravců" : "Podklad k fakturaci zákazníkům" ?></h2>
  <?php if (!$po_firmach): ?>
    <p class="prazdno">V období není žádná přeprava s přiřazenou firmou.</p>
  <?php else: ?>
    <?php foreach ($po_firmach as $f):
      $polozky = $pohled === "dopravci"
        ? radky("SELECT p.* FROM prepravy p WHERE " . $kde . " AND p.dopravce_id = ?
                  ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [$f["id"]]))
        : radky("SELECT p.* FROM prepravy p WHERE " . $kde . " AND p.zakaznik_id = ?
                  ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [$f["id"]]));
    ?>
      <section style="margin-bottom:28px">
        <div class="app-hlava" style="margin-bottom:10px">
          <div>
            <h3 style="margin:0"><a href="<?= chran(odkaz("firma", ["id" => $f["id"]])) ?>"><?= chran($f["nazev"]) ?></a></h3>
            <p class="app-perex" style="margin:2px 0 0"><?= (int)$f["pocet"] ?> přeprav · <b class="cislo"><?= chran(castka($f["soucet"])) ?></b> bez DPH</p>
          </div>
          <?php if ($pohled === "zakaznici"):
            $nevyfakt = 0; $nevyfakt_soucet = 0.0;
            foreach ($polozky as $px) if (trim((string)$px["faktura_vydana"]) === "" && $px["cena_zakaznik"] !== null) { $nevyfakt++; $nevyfakt_soucet += (float)$px["cena_zakaznik"]; }
          ?>
            <div class="app-hlava-akce netisknout">
              <a class="tlacitko obrys" href="<?= chran(odkaz("export", ["co" => "radky_faktury", "od" => $od, "do" => $do, "firma" => $f["id"]])) ?>">Řádky faktury CSV</a>
              <?php if ($nevyfakt && fakturoid_nastaven()): ?>
                <form method="post" action="<?= chran(odkaz("fakturace", ["pohled" => "zakaznici", "od" => $od, "do" => $do])) ?>"
                      data-potvrdit="Založit ve Fakturoidu fakturu pro <?= chran($f["nazev"]) ?> za <?= $nevyfakt ?> přeprav, <?= chran(castka($nevyfakt_soucet)) ?> bez DPH, s DPH <?= chran(nastaveni("dph_sazba", "21")) ?> %? Faktura vznikne v účetnictví doopravdy.">
                  <?= pole_token() ?><input type="hidden" name="akce" value="fakturoid_zalozit"><input type="hidden" name="firma_id" value="<?= (int)$f["id"] ?>"><input type="hidden" name="pohled" value="zakaznici">
                  <button type="submit" class="tlacitko">Založit fakturu ve Fakturoidu (<?= $nevyfakt ?>)</button>
                </form>
              <?php elseif ($nevyfakt): ?>
                <span class="app-perex"><?= $nevyfakt ?> nevyfakturovaných · Fakturoid není napojený</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="tabulka-obal">
          <table class="id-tabulka">
            <thead>
              <tr>
                <th>Číslo</th><th>Nakládka</th><th>Vykládka</th><th>Zboží</th>
                <th>Doklady</th>
                <th class="vpravo"><?= $pohled === "dopravci" ? "Cena dopravce" : "Cena zákazníka" ?></th>
                <th><?= $pohled === "dopravci" ? "Přijatá faktura" : "Vydaná faktura" ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($polozky as $p): ?>
              <tr>
                <td><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a></td>
                <td><?= chran($p["nakladka_misto"] ?: "—") ?><span class="druhotny"><?= chran(datum($p["nakladka_datum"])) ?></span></td>
                <td><?= chran($p["vykladka_misto"] ?: "—") ?><span class="druhotny"><?= chran(datum($p["vykladka_datum"])) ?></span></td>
                <td><?= chran($p["zbozi"] ?: "—") ?></td>
                <td><?= chran(DOKLADY[$p["doklady"]] ?? "—") ?></td>
                <td class="cislo vpravo"><?= chran(castka($pohled === "dopravci" ? $p["cena_dopravce"] : $p["cena_zakaznik"])) ?></td>
                <td class="cislo"><?= chran(($pohled === "dopravci" ? $p["faktura_prijata"] : $p["faktura_vydana"]) ?: "—") ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
<?php
pata();

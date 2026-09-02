<?php
/* Fakturace a přehledy — obrat, marže a podklady k fakturaci za období.

   Podklad po dopravcích potřebuje každý dispečer (týdenní fakturace za
   odjeté přepravy). Obchodní strana — tržba, marže a podklad po
   zákaznících — je jen pro toho, kdo na ceny zákazníka má právo. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$pohled = vstup("pohled", "dopravci");
if (!in_array($pohled, ["dopravci", "zakaznici", "chybi", "faktury", "pohledavky", "zavazky", "dispecink", "vyhodnoceni"], true)) $pohled = "dopravci";
if (in_array($pohled, ["zakaznici", "pohledavky", "dispecink"], true) && !$ceny) $pohled = "dopravci";

$od = vstup_datum("od") ?: date("Y-m-01");
$do = vstup_datum("do") ?: date("Y-m-t");

/* Období se počítá podle data vykládky — fakturuje se za odjeté přepravy.
   Bez data vykládky rozhoduje datum nakládky. */
const OBDOBI_SLOUPEC = "COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum)";

/* Jízdy pod externím dispečinkem fakturuje odesílateli klient sám; sem
   patří jen spedice, dispečink má vlastní pohled a podklad. */
$kde = OBDOBI_SLOUPEC . " BETWEEN ? AND ? AND p.stav <> 'zruseno' AND p.sablona = 0 AND " . JEN_SPEDICE;
$parametry = [$od, $do];
$dispecink_pocet = (int)hodnota("SELECT COUNT(*) FROM prepravy p WHERE " . OBDOBI_SLOUPEC . " BETWEEN ? AND ? AND p.stav <> 'zruseno' AND p.sablona = 0 AND " . JEN_DISPECINK, $parametry);

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
        AND p.stav IN ('vylozeno', 'doklady')
        AND (p.faktura_vydana IS NULL OR TRIM(p.faktura_vydana) = '') AND p.cena_zakaznik IS NOT NULL
        ORDER BY " . OBDOBI_SLOUPEC . ", p.id", array_merge($parametry, [(int)$firma["id"]]));
    /* Fakturuje se jen to, co je odjeté: objednaná nebo teprve naložená
       zásilka do faktury nepatří, i kdyby jí datum spadlo do období. */
    if (!$polozky) { vzkaz("chyba", "Zákazník nemá v období žádnou vyloženou, nevyfakturovanou přepravu s cenou."); presmeruj($navrat); }

    $splatnost = (int)($firma["splatnost"] ?: nastaveni("splatnost_dnu", "14"));
    $dph = (float)str_replace(",", ".", nastaveni("dph_sazba", "21"));
    $vysledek = fakturoid_zaloz_fakturu($firma, $polozky, $splatnost, $dph, $chyba);
    if (!$vysledek) { vzkaz("chyba", (string)$chyba); presmeruj($navrat); }

    foreach ($polozky as $p) {
      uprav("prepravy", (int)$p["id"], ["faktura_vydana" => $vysledek["cislo"], "stav" => "fakturovano", "upraveno" => date("Y-m-d H:i:s")]);
      zapis_udalost((int)$p["id"], "Vyfakturováno ve Fakturoidu, faktura " . $vysledek["cislo"]);
    }
    faktura_uloz("vydana", $vysledek["cislo"], array_merge($vysledek["data"], ["firma_id" => (int)$firma["id"]]));
    zapis_udalost(null, "Ve Fakturoidu založena faktura " . $vysledek["cislo"] . " pro " . $firma["nazev"] . " (" . count($polozky) . " přeprav)");
    vzkaz("ok", "Ve Fakturoidu vznikla faktura " . $vysledek["cislo"] . " za " . count($polozky) . " přeprav. Číslo je zapsané u přeprav.");
    presmeruj(odkaz("fakturace", ["pohled" => "zakaznici", "od" => $od, "do" => $do]));

  } elseif ($akce === "fakturoid_dispecink") {
    vyzaduj_ceny();
    $navrat = odkaz("fakturace", ["pohled" => "dispecink", "od" => $od, "do" => $do]);
    $klient = radek("SELECT * FROM firmy WHERE id = ?", [vstup_cislo("firma_id")]);
    if (!$klient) { vzkaz("chyba", "Klient nenalezen."); presmeruj($navrat); }
    $pk = dispecink_podklad($klient, $od, $do);
    if ($pk["odmena"] === null) {
      vzkaz("chyba", "Odměnu nelze spočítat. " . ($pk["upozorneni"][0] ?? ""));
      presmeruj($navrat);
    }
    if (!$pk["radky"] || !($pk["odmena"] > 0)) {
      vzkaz("chyba", "Není co fakturovat: " . ($pk["zpusob"] === "pausal_vuz" ? "klient nemá žádný aktivní vůz." : "v období nejsou nevyúčtované jízdy."));
      presmeruj($navrat);
    }
    /* Stejné období podruhé nefakturovat — paušál se na jízdy neváže,
       tak ho hlídá poznámka faktury. */
    $dvojite = radek("SELECT cislo FROM faktury WHERE druh = 'vydana' AND firma_id = ? AND poznamka = ?",
      [(int)$klient["id"], "Externí dispečink " . $pk["obdobi"]]);
    if ($dvojite) { vzkaz("chyba", "Za období " . $pk["obdobi"] . " už je klientovi vystavená faktura " . $dvojite["cislo"] . "."); presmeruj($navrat); }

    $splatnost = (int)($klient["splatnost"] ?: nastaveni("splatnost_dnu", "14"));
    $dph = (float)str_replace(",", ".", nastaveni("dph_sazba", "21"));
    $vysledek = fakturoid_zaloz_fakturu_radky($klient, $pk["radky"], $splatnost, $dph, $chyba);
    if (!$vysledek) { vzkaz("chyba", (string)$chyba); presmeruj($navrat); }

    foreach ($pk["jizdy"] as $j) {
      if (trim((string)$j["faktura_vydana"]) !== "") continue;
      uprav("prepravy", (int)$j["id"], ["faktura_vydana" => $vysledek["cislo"], "upraveno" => date("Y-m-d H:i:s")]);
      zapis_udalost((int)$j["id"], "Dispečink vyúčtován ve Fakturoidu, faktura " . $vysledek["cislo"]);
    }
    faktura_uloz("vydana", $vysledek["cislo"], array_merge($vysledek["data"],
      ["firma_id" => (int)$klient["id"], "poznamka" => "Externí dispečink " . $pk["obdobi"]]));
    zapis_udalost(null, "Ve Fakturoidu založena faktura " . $vysledek["cislo"] . " za externí dispečink pro " . $klient["nazev"]);
    vzkaz("ok", "Ve Fakturoidu vznikla faktura " . $vysledek["cislo"] . " za externí dispečink " . $pk["obdobi"] . ", " . castka($pk["odmena"]) . " bez DPH.");
    presmeruj($navrat);
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
  <p class="app-perex" style="margin:12px 0 0">Období se počítá podle data vykládky; u přeprav bez vykládky podle nakládky. Zrušené se nepočítají, jízdy pod externím dispečinkem mají vlastní pohled.</p>
</form>

<div class="dlazdice">
  <div class="dlazdice-polozka">
    <span class="popis">Přeprav</span>
    <span class="hodnota"><?= (int)$souhrn["pocet"] ?></span>
    <span class="doplnek"><?= chran(datum($od)) ?> – <?= chran(datum($do)) ?><?= $dispecink_pocet ? " · a " . $dispecink_pocet . " pod dispečinkem" : "" ?></span>
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
    <a class="tlacitko<?= $pohled === "dispecink" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "dispecink", "od" => $od, "do" => $do])) ?>">Externí dispečink</a>
  <?php endif; ?>
  <a class="tlacitko<?= $pohled === "chybi" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "chybi", "od" => $od, "do" => $do])) ?>">Chybějící údaje</a>
  <a class="tlacitko<?= $pohled === "faktury" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "faktury", "od" => $od, "do" => $do])) ?>">Faktury</a>
  <?php if ($ceny): ?><a class="tlacitko<?= $pohled === "pohledavky" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "pohledavky", "od" => $od, "do" => $do])) ?>">Pohledávky</a><?php endif; ?>
  <a class="tlacitko<?= $pohled === "zavazky" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "zavazky", "od" => $od, "do" => $do])) ?>">Závazky</a>
  <a class="tlacitko<?= $pohled === "vyhodnoceni" ? "" : " obrys" ?>" href="<?= chran(odkaz("fakturace", ["pohled" => "vyhodnoceni", "od" => $od, "do" => $do])) ?>">Vyhodnocení</a>
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

<?php elseif ($pohled === "vyhodnoceni"):
  /* Vyhodnocení za období: zákazníci (jen s právem na ceny), dopravci,
     řidiči a vozy. Sčítá se v PHP z jednoho dotazu — dat je málo a čtyři
     seskupení v SQL by se hůř četla. Obrat vozu a jízdy dopravců berou
     i jízdy pod dispečinkem, tržba a marže jen spedici. */
  $jizdy = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev, d.nazev AS dopravce_nazev, v.spz AS vuz_spz, vf.nazev AS vuz_firma
       FROM prepravy p
       LEFT JOIN firmy z ON z.id = p.zakaznik_id
       LEFT JOIN firmy d ON d.id = p.dopravce_id
       LEFT JOIN vozidla v ON v.id = p.vozidlo_id
       LEFT JOIN firmy vf ON vf.id = v.firma_id
      WHERE " . OBDOBI_SLOUPEC . " BETWEEN ? AND ? AND p.stav <> 'zruseno' AND p.sablona = 0
      ORDER BY " . OBDOBI_SLOUPEC . ", p.id", [$od, $do]);
  $zakaznici = []; $dopravci = []; $ridici = []; $vozy = [];
  foreach ($jizdy as $j) {
    $spedice = empty($j["dispecink_klient_id"]);
    if ($spedice && $j["zakaznik_id"]) {
      $k = (int)$j["zakaznik_id"];
      $z = &$zakaznici[$k]; $z["nazev"] = $j["zakaznik_nazev"]; $z["id"] = $k;
      $z["pocet"] = ($z["pocet"] ?? 0) + 1;
      $z["trzba"] = ($z["trzba"] ?? 0) + (float)$j["cena_zakaznik"];
      $z["naklad"] = ($z["naklad"] ?? 0) + (float)$j["cena_dopravce"];
      unset($z);
    }
    if ($j["dopravce_id"]) {
      $k = (int)$j["dopravce_id"];
      $d = &$dopravci[$k]; $d["nazev"] = $j["dopravce_nazev"]; $d["id"] = $k;
      $d["pocet"] = ($d["pocet"] ?? 0) + 1;
      $d["naklad"] = ($d["naklad"] ?? 0) + (float)$j["cena_dopravce"];
      if (trim((string)$j["hlaseni"]) !== "") $d["zpozdeni"] = ($d["zpozdeni"] ?? 0) + 1;
      if (in_array($j["stav"], ["vylozeno", "doklady", "fakturovano"], true)) {
        $d["vylozeno"] = ($d["vylozeno"] ?? 0) + 1;
        if ($j["doklady"] === "prijato") $d["doklady"] = ($d["doklady"] ?? 0) + 1;
        if ($j["doklady"] === "prijato" && $j["doklady_kdy"] && $j["vykladka_datum"]) {
          $d["dnu_dokladu"][] = max(0, (strtotime(substr((string)$j["doklady_kdy"], 0, 10)) - strtotime((string)$j["vykladka_datum"])) / 86400);
        }
      }
      unset($d);
    }
    if (trim((string)$j["ridic_jmeno"]) !== "") {
      $k = mb_strtolower(trim((string)$j["ridic_jmeno"])) . "|" . (int)$j["dopravce_id"];
      $r = &$ridici[$k]; $r["jmeno"] = trim((string)$j["ridic_jmeno"]); $r["dopravce"] = $j["dopravce_nazev"];
      $r["pocet"] = ($r["pocet"] ?? 0) + 1;
      $r["obrat"] = ($r["obrat"] ?? 0) + (float)$j["cena_dopravce"];
      if (trim((string)$j["hlaseni"]) !== "") $r["zpozdeni"] = ($r["zpozdeni"] ?? 0) + 1;
      unset($r);
    }
    if ($j["vozidlo_id"] && $j["vuz_spz"]) {
      $k = (int)$j["vozidlo_id"];
      $v = &$vozy[$k]; $v["spz"] = $j["vuz_spz"]; $v["firma"] = $j["vuz_firma"];
      $v["pocet"] = ($v["pocet"] ?? 0) + 1;
      $v["obrat"] = ($v["obrat"] ?? 0) + (float)$j["cena_dopravce"];
      $v["dispecink"] = ($v["dispecink"] ?? 0) + ($spedice ? 0 : 1);
      unset($v);
    }
  }
  usort($zakaznici, fn($a, $b) => ($b["trzba"] - $b["naklad"]) <=> ($a["trzba"] - $a["naklad"]));
  usort($dopravci, fn($a, $b) => $b["pocet"] <=> $a["pocet"]);
  usort($ridici, fn($a, $b) => $b["pocet"] <=> $a["pocet"]);
  usort($vozy, fn($a, $b) => $b["obrat"] <=> $a["obrat"]);
  $procento = fn(float $cast, float $celek) => $celek > 0 ? cislo($cast / $celek * 100, 0) . " %" : "—";
?>
  <h2>Vyhodnocení období</h2>
  <p class="app-perex">Obraty a marže po zákaznících, vytíženost a spolehlivost dopravců, výkonnost řidičů a obrat každého vozu. Zpoždění je počet jízd s hlášením od dopravce; rychlost vracení dokladů se počítá ode dne vykládky do dne, kdy se doklady označily jako přijaté. Prostoje vozů se nesledují.</p>
  <?php if (!$jizdy): ?>
    <p class="prazdno">V období není žádná přeprava.</p>
  <?php else: ?>
    <?php if ($ceny): ?>
      <h3>Zákazníci — tržba a marže <span class="napoveda" style="text-transform:none;letter-spacing:0">spedice, bez jízd pod dispečinkem</span></h3>
      <div class="tabulka-obal" style="margin-bottom:28px">
        <table class="id-tabulka">
          <thead><tr><th>Zákazník</th><th class="vpravo">Přeprav</th><th class="vpravo">Tržba</th><th class="vpravo">Náklad</th><th class="vpravo">Marže</th><th class="vpravo">Marže %</th><th class="vpravo">Na přepravu</th></tr></thead>
          <tbody>
          <?php foreach ($zakaznici as $z): $m = $z["trzba"] - $z["naklad"]; ?>
            <tr>
              <td><a href="<?= chran(odkaz("firma", ["id" => $z["id"]])) ?>"><?= chran($z["nazev"] ?: "—") ?></a></td>
              <td class="cislo vpravo"><?= (int)$z["pocet"] ?></td>
              <td class="cislo vpravo"><?= chran(castka($z["trzba"])) ?></td>
              <td class="cislo vpravo"><?= chran(castka($z["naklad"])) ?></td>
              <td class="cislo vpravo"><?= chran(castka($m)) ?></td>
              <td class="cislo vpravo"><?= chran($procento($m, (float)$z["trzba"])) ?></td>
              <td class="cislo vpravo"><?= chran(castka($m / max(1, (int)$z["pocet"]))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$zakaznici): ?><tr><td colspan="7">V období není žádná spediční přeprava se zákazníkem.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h3>Dopravci — vytíženost a spolehlivost</h3>
    <div class="tabulka-obal" style="margin-bottom:28px">
      <table class="id-tabulka">
        <thead><tr><th>Dopravce</th><th class="vpravo">Jízd</th><th class="vpravo">Náklad / obrat vozů</th><th class="vpravo">Hlášení zpoždění</th><th class="vpravo">Doklady vráceny</th><th class="vpravo">Průměr dní na doklady</th></tr></thead>
        <tbody>
        <?php foreach ($dopravci as $d): $dnu = $d["dnu_dokladu"] ?? []; ?>
          <tr>
            <td><a href="<?= chran(odkaz("firma", ["id" => $d["id"]])) ?>"><?= chran($d["nazev"] ?: "—") ?></a></td>
            <td class="cislo vpravo"><?= (int)$d["pocet"] ?></td>
            <td class="cislo vpravo"><?= chran(castka($d["naklad"])) ?></td>
            <td class="cislo vpravo"><?= (int)($d["zpozdeni"] ?? 0) ?><span class="druhotny"><?= chran($procento((float)($d["zpozdeni"] ?? 0), (float)$d["pocet"])) ?> jízd</span></td>
            <td class="cislo vpravo"><?= (int)($d["doklady"] ?? 0) ?> z <?= (int)($d["vylozeno"] ?? 0) ?><span class="druhotny"><?= chran($procento((float)($d["doklady"] ?? 0), (float)($d["vylozeno"] ?? 0))) ?> vyložených</span></td>
            <td class="cislo vpravo"><?= $dnu ? chran(cislo(array_sum($dnu) / count($dnu), 1)) : "—" ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$dopravci): ?><tr><td colspan="6">Žádná přeprava v období nemá dopravce.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="app-sloupce stejne">
      <div>
        <h3>Řidiči</h3>
        <div class="tabulka-obal">
          <table class="id-tabulka" style="min-width:480px">
            <thead><tr><th>Řidič</th><th class="vpravo">Jízd</th><th class="vpravo">Obrat</th><th class="vpravo">Zpoždění</th></tr></thead>
            <tbody>
            <?php foreach ($ridici as $r): ?>
              <tr>
                <td><?= chran($r["jmeno"]) ?><span class="druhotny"><?= chran($r["dopravce"] ?: "—") ?></span></td>
                <td class="cislo vpravo"><?= (int)$r["pocet"] ?></td>
                <td class="cislo vpravo"><?= chran(castka($r["obrat"])) ?></td>
                <td class="cislo vpravo"><?= (int)($r["zpozdeni"] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$ridici): ?><tr><td colspan="4">U přeprav v období není zapsaný řidič.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div>
        <h3>Vozy — obrat za období</h3>
        <div class="tabulka-obal">
          <table class="id-tabulka" style="min-width:480px">
            <thead><tr><th>Vůz</th><th class="vpravo">Jízd</th><th class="vpravo">Obrat vozu</th></tr></thead>
            <tbody>
            <?php foreach ($vozy as $v): ?>
              <tr>
                <td><span class="cislo"><?= chran($v["spz"]) ?></span><span class="druhotny"><?= chran($v["firma"] ?: "—") ?><?= $v["dispecink"] ? " · " . (int)$v["dispecink"] . " pod dispečinkem" : "" ?></span></td>
                <td class="cislo vpravo"><?= (int)$v["pocet"] ?></td>
                <td class="cislo vpravo"><?= chran(castka($v["obrat"])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$vozy): ?><tr><td colspan="3">U přeprav v období není vybraný vůz z karty dopravce.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($pohled === "dispecink"):
  /* Klienti s příznakem plus ti, kdo mají v období jízdy (i vyřazení). */
  $klienti = radky(
    "SELECT f.* FROM firmy f
      WHERE (f.dispecink = 1 AND f.aktivni = 1)
         OR f.id IN (SELECT p.dispecink_klient_id FROM prepravy p
                      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND " . JEN_DISPECINK . "
                        AND " . OBDOBI_SLOUPEC . " BETWEEN ? AND ?)
      ORDER BY LOWER(f.nazev)", [$od, $do]);
  $navrat = odkaz("fakturace", ["pohled" => "dispecink", "od" => $od, "do" => $do]);
?>
  <h2>Externí dispečink — podklad k fakturaci služby</h2>
  <p class="app-perex">Jízdy vozů klientů za období, obrat vozů a odměna podle způsobu účtování na kartě klienta. Odesílateli fakturuje klient sám, do tržby a marže spedice se tyhle jízdy nepočítají. Odměna se počítá z jízd, které ještě nemají číslo faktury.</p>
  <?php if (!$klienti): ?>
    <p class="prazdno">Žádný klient dispečinku. Na kartě firmy zaškrtněte „Klient externího dispečinku".</p>
  <?php else: foreach ($klienti as $klient): $pk = dispecink_podklad($klient, $od, $do); ?>
    <section style="margin-bottom:32px">
      <div class="app-hlava" style="margin-bottom:12px">
        <div>
          <h3 style="margin:0"><a href="<?= chran(odkaz("firma", ["id" => $klient["id"]])) ?>"><?= chran($klient["nazev"]) ?></a></h3>
          <p class="app-perex" style="margin:2px 0 0"><?php
            if (isset(DISPECINK_UCTOVANI[$pk["zpusob"]])) {
              echo chran(DISPECINK_UCTOVANI[$pk["zpusob"]]);
              if ($pk["sazba"] !== null) echo " · " . ($pk["zpusob"] === "procento" ? chran(cislo($pk["sazba"], 1)) . " %" : chran(castka($pk["sazba"])));
            } else {
              echo "způsob účtování a sazba nejsou zadané";
            }
            if ($klient["dispecink_poznamka"]) echo " · " . chran($klient["dispecink_poznamka"]);
          ?></p>
        </div>
        <div class="app-hlava-akce netisknout">
          <?php if ($pk["radky"]): ?>
            <a class="tlacitko obrys" href="<?= chran(odkaz("export", ["co" => "dispecink_radky", "od" => $od, "do" => $do, "firma" => $klient["id"]])) ?>">Řádky faktury CSV</a>
            <?php if (fakturoid_nastaven() && $pk["odmena"] > 0): ?>
              <form method="post" action="<?= chran($navrat) ?>"
                    data-potvrdit="Založit ve Fakturoidu fakturu pro <?= chran($klient["nazev"]) ?> za externí dispečink <?= chran($pk["obdobi"]) ?>: <?= chran(castka($pk["odmena"])) ?> bez DPH, s DPH <?= chran(nastaveni("dph_sazba", "21")) ?> %? Faktura vznikne v účetnictví doopravdy.">
                <?= pole_token() ?><input type="hidden" name="akce" value="fakturoid_dispecink"><input type="hidden" name="firma_id" value="<?= (int)$klient["id"] ?>"><input type="hidden" name="pohled" value="dispecink">
                <button type="submit" class="tlacitko">Založit fakturu ve Fakturoidu</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="dlazdice">
        <div class="dlazdice-polozka">
          <span class="popis">Jízd v období</span>
          <span class="hodnota"><?= (int)$pk["pocet"] ?></span>
          <span class="doplnek"><?= $pk["vyuctovane"] ? (int)$pk["vyuctovane"] . " už vyúčtovaných · " : "" ?><?= (int)$pk["otevrene"] ?> k vyúčtování</span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis">Obrat vozů</span>
          <span class="hodnota"><?= chran(castka($pk["obrat"])) ?></span>
          <span class="doplnek">ceny jízd bez DPH<?= $pk["bez_ceny"] ? " · " . (int)$pk["bez_ceny"] . " " . sklonuj((int)$pk["bez_ceny"], "jízda", "jízdy", "jízd") . " bez ceny" : "" ?></span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis">Vozů v provozu</span>
          <span class="hodnota"><?= (int)$pk["vozu_v_provozu"] ?> z <?= count($pk["vozy"]) ?></span>
          <span class="doplnek">aktivních vozů na kartě<?= $pk["bez_vozu"] ? " · " . (int)$pk["bez_vozu"] . " " . sklonuj((int)$pk["bez_vozu"], "jízda", "jízdy", "jízd") . " bez vozu" : "" ?></span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis">Odměna za dispečink</span>
          <span class="hodnota"><?= $pk["odmena"] === null ? "—" : chran(castka($pk["odmena"])) ?></span>
          <span class="doplnek"><?= $pk["odmena"] === null ? "nelze spočítat" : chran($pk["vypocet"]) ?></span>
        </div>
      </div>
      <?php foreach ($pk["upozorneni"] as $u): ?><p class="vzkaz vzkaz-pozor"><?= chran($u) ?></p><?php endforeach; ?>
      <?php if (!$pk["jizdy"]): ?>
        <p class="prazdno">V období klient žádnou jízdu pod dispečinkem nemá.</p>
      <?php else: ?>
        <div class="tabulka-obal">
          <table class="id-tabulka">
            <thead><tr><th>Číslo</th><th>Trasa</th><th>Vůz</th><th>Zákazník</th><th>Zboží</th><th class="vpravo">Cena jízdy</th><th>Doklady</th><th>Vyúčtováno</th></tr></thead>
            <tbody>
            <?php foreach ($pk["jizdy"] as $j): ?>
              <tr>
                <td><a href="<?= chran(odkaz("preprava", ["id" => $j["id"]])) ?>" class="cislo"><?= chran($j["cislo"]) ?></a><span class="druhotny"><?= stitek_stavu($j["stav"]) ?></span></td>
                <td><?= chran($j["nakladka_misto"] ?: "?") ?> → <?= chran($j["vykladka_misto"] ?: "?") ?>
                  <span class="druhotny"><?= chran(datum($j["nakladka_datum"])) ?><?= $j["vykladka_datum"] && $j["vykladka_datum"] !== $j["nakladka_datum"] ? " – " . chran(datum($j["vykladka_datum"])) : "" ?></span></td>
                <td><span class="cislo"><?= chran($j["vuz_spz"] ?: ($j["spz"] ?: "—")) ?></span><?php if ($j["ridic_jmeno"]): ?><span class="druhotny"><?= chran($j["ridic_jmeno"]) ?></span><?php endif; ?></td>
                <td><?= chran($j["zakaznik_nazev"] ?: "—") ?></td>
                <td><?= chran($j["zbozi"] ?: "—") ?></td>
                <td class="cislo vpravo"><?= chran(castka($j["cena_dopravce"])) ?></td>
                <td><?= chran(DOKLADY[$j["doklady"]] ?? "—") ?></td>
                <td class="cislo"><?= chran(trim((string)$j["faktura_vydana"]) !== "" ? $j["faktura_vydana"] : "—") ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; endif; ?>
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

<?php elseif (in_array($pohled, ["dopravci", "zakaznici"], true)):
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
            foreach ($polozky as $px) if (in_array($px["stav"], ["vylozeno", "doklady"], true) && trim((string)$px["faktura_vydana"]) === "" && $px["cena_zakaznik"] !== null) { $nevyfakt++; $nevyfakt_soucet += (float)$px["cena_zakaznik"]; }
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
                <span class="app-perex"><?= $nevyfakt ?> vyložených k fakturaci · Fakturoid není napojený</span>
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

<?php
/* Napojení na Airtable — výběr tabulky, mapování polí a načtení přeprav.

   Stránka je jen pro správce: nese ceny a zakládá zásilky. Přístup je
   v config.php, mapování v databázi; v repozitáři není ani token, ani
   báze, ani jediný název pole. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_spravce();

$vysledek = null;
$tabulky = null; $chyba_spojeni = null;

if (airtable_nastaven()) {
  $tabulky = airtable_tabulky($chyba_spojeni);
}

/* Pole vybrané tabulky — pro rozbalovací seznamy v mapování. */
$vybrana = null;
foreach ((array)$tabulky as $t) {
  if ((string)$t["nazev"] === airtable_tabulka() || (string)$t["id"] === airtable_tabulka()) { $vybrana = $t; break; }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($akce === "tabulka") {
    $nova = vstup("tabulka");
    $najita = null;
    foreach ((array)$tabulky as $t) if ((string)$t["nazev"] === $nova) { $najita = $t; break; }
    if (!$najita) { vzkaz("chyba", "Takovou tabulku báze nemá."); presmeruj(odkaz("airtable")); }
    uloz_nastaveni("airtable_tabulka", $nova);
    /* Mapování se předvyplní odhadem, ať se nezačíná od prázdného. */
    if (airtable_mapovani() === [] || vstup_ano_ne("znovu_odhadnout") === 1) {
      $odhad = [];
      foreach (AIRTABLE_POLE as $klic => $popis) $odhad[$klic] = airtable_hadej_pole($najita["pole"], $klic);
      airtable_uloz_mapovani($odhad);
    }
    zapis_udalost(null, "Airtable: vybrána tabulka " . $nova);
    vzkaz("ok", "Tabulka vybraná. Zkontrolujte mapování polí — odhad podle názvů bývá blízko, ale ne vždy trefa.");
    presmeruj(odkaz("airtable"));

  } elseif ($akce === "mapovani") {
    $nove = [];
    $nazvy = [];
    foreach ((array)($vybrana["pole"] ?? []) as $p) $nazvy[] = (string)$p["nazev"];
    foreach (AIRTABLE_POLE as $klic => $popis) {
      $pole = (string)(($_POST["mapovani"][$klic] ?? ""));
      $nove[$klic] = in_array($pole, $nazvy, true) ? $pole : "";
    }
    airtable_uloz_mapovani($nove);

    $stavy = [];
    foreach ((array)($_POST["stav"] ?? []) as $zvenku => $nas) {
      $zvenku = trim((string)$zvenku);
      if ($zvenku !== "" && isset(STAVY[(string)$nas])) $stavy[$zvenku] = (string)$nas;
    }
    airtable_uloz_stavy($stavy);
    vzkaz("ok", "Mapování uloženo.");
    presmeruj(odkaz("airtable"));

  } elseif ($akce === "nacist" || $akce === "nanecisto") {
    $volby = [
      "od"        => vstup_datum("od") ?: "",
      "do"        => vstup_datum("do") ?: "",
      "rezim"     => vstup("rezim"),
      "strop"     => vstup_cislo("strop") ?: 500,
      "nanecisto" => $akce === "nanecisto",
      "zakladat_firmy" => vstup_ano_ne("zakladat_firmy") === 1,
    ];
    $vysledek = airtable_nacti($volby, $chyba_nacteni);
    if ($vysledek === null) {
      vzkaz("chyba", (string)$chyba_nacteni);
      presmeruj(odkaz("airtable"));
    }
    if (!$vysledek["nanecisto"]) {
      vzkaz("ok", "Načteno z Airtable: " . $vysledek["zalozeno"] . " nových, " . $vysledek["doplneno"]
        . " doplněných, " . $vysledek["beze_zmeny"] . " beze změny, " . $vysledek["preskoceno"] . " přeskočených.");
    }
  }
}

$mapovani = airtable_mapovani();
$stavy_mapa = airtable_stavy();
$pole_tabulky = [];
foreach ((array)($vybrana["pole"] ?? []) as $p) $pole_tabulky[(string)$p["nazev"]] = (string)$p["nazev"] . " · " . $p["typ"];

/* Volby stavu, na které se ptáme v mapování stavů. */
$stav_volby = [];
$pole_stavu = trim((string)($mapovani["stav"] ?? ""));
foreach ((array)($vybrana["pole"] ?? []) as $p) {
  if ((string)$p["nazev"] === $pole_stavu) $stav_volby = (array)$p["volby"];
}
foreach (array_keys($stavy_mapa) as $ulozena) {
  if (!in_array($ulozena, $stav_volby, true)) $stav_volby[] = $ulozena;
}

hlava("Airtable", "nastaveni");
?>
<a class="app-zpet" href="<?= chran(odkaz("nastaveni")) ?>">← Zpět na nastavení</a>
<?php hlava_stranky("Data", "Napojení na Airtable"); ?>

<?php if (!airtable_nastaven()): ?>
  <div class="doplnit" style="max-width:80ch">
    <b>Napojení není nastavené.</b> Do <span class="cislo">aplikace/config.php</span> na hostingu doplňte
    <span class="cislo">airtable_token</span> a <span class="cislo">airtable_baze</span>. Token vydá Airtable
    v Developer hub → Personal access tokens; stačí mu právo číst záznamy a schéma
    (<span class="cislo">data.records:read</span> a <span class="cislo">schema.bases:read</span>) a přístup k jediné bázi.
    Identifikátor báze je v adrese, když ji máte otevřenou — začíná <span class="cislo">app</span>.
    Do repozitáře nepatří ani jedno: je veřejný.
  </div>
<?php else: ?>

  <?php if ($chyba_spojeni !== null): ?>
    <p class="vzkaz vzkaz-chyba"><?= chran($chyba_spojeni) ?></p>
  <?php elseif ($tabulky !== null): ?>
    <p class="vzkaz vzkaz-ok">Spojení funguje — báze má <?= count($tabulky) ?> <?= sklonuj(count($tabulky), "tabulku", "tabulky", "tabulek") ?>.<?php
      if (nastaveni("airtable_naposledy") !== "") echo " Naposledy načteno " . chran(nastaveni("airtable_naposledy")) . ".";
    ?></p>
  <?php endif; ?>

  <div class="app-sloupce stejne">
    <div>
      <form method="post" action="<?= chran(odkaz("airtable")) ?>" class="formular">
        <?= pole_token() ?>
        <input type="hidden" name="akce" value="tabulka">
        <div class="skupina" style="margin-bottom:0">
          <h2>Tabulka s přepravami</h2>
          <?php if (!$tabulky): ?>
            <p class="app-perex">Seznam tabulek se nepodařilo načíst, takže vybírat není z čeho. Zkuste to znovu, až bude spojení v pořádku.</p>
          <?php else: ?>
            <div class="pole">
              <label for="tabulka">Ze které tabulky se čtou přepravy</label>
              <?php $volby_tabulek = []; foreach ($tabulky as $t) $volby_tabulek[(string)$t["nazev"]] = (string)$t["nazev"] . " (" . count($t["pole"]) . " " . sklonuj(count($t["pole"]), "pole", "pole", "polí") . ")"; ?>
              <select id="tabulka" name="tabulka"><?= volby($volby_tabulek, airtable_tabulka(), "— vyberte —") ?></select>
            </div>
            <div class="pole-zaskrtnuti">
              <input type="checkbox" id="znovu_odhadnout" name="znovu_odhadnout" value="1">
              <label for="znovu_odhadnout">Odhadnout mapování polí znovu <span class="napoveda">— přepíše to současné</span></label>
            </div>
            <button type="submit" class="tlacitko">Uložit tabulku</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div>
      <div class="formular">
        <div class="skupina" style="margin-bottom:0">
          <h2>Jak to funguje</h2>
          <p class="app-perex">Data tečou jen jedním směrem: z Airtable sem. Zpět se nezapisuje nic, takže token stačí s právem číst. Přepravy se párují podle čísla — co už tady je, se podle zvoleného režimu doplní nebo nechá být.</p>
          <p class="app-perex"><b>Trasa se nikdy nepřepisuje.</b> Body jízdy jsou tady zdrojem pravdy a mohly mezitím přibýt; z Airtable se zakládají jen u nových přeprav.</p>
          <p class="app-perex">Nic se nevolá samo od sebe. Načtení je tlačítko a před ním si výsledek můžete prohlédnout nanečisto.</p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($vybrana): ?>
    <form method="post" action="<?= chran(odkaz("airtable")) ?>" class="formular" style="margin-top:20px">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="mapovani">
      <div class="skupina">
        <h2>Mapování polí</h2>
        <p class="app-perex">Vlevo je pole přepravy, vpravo pole z tabulky <b><?= chran((string)$vybrana["nazev"]) ?></b>. Co necháte prázdné, se nenačítá. Odkazová pole (zákazník, dopravce) systém dohledá v tabulce, na kterou odkazují, a použije její první sloupec.</p>
        <div class="tabulka-obal">
          <table class="id-tabulka karty">
            <thead><tr><th>Pole přepravy</th><th>Pole v Airtable</th></tr></thead>
            <tbody>
            <?php foreach (AIRTABLE_POLE as $klic => $popis): ?>
              <tr>
                <td data-popis="Pole přepravy"><label for="m-<?= chran($klic) ?>"><?= chran($popis) ?></label></td>
                <td data-popis="Pole v Airtable">
                  <select id="m-<?= chran($klic) ?>" name="mapovani[<?= chran($klic) ?>]" style="padding:6px 8px;font-size:.88rem;max-width:100%">
                    <?= volby($pole_tabulky, (string)($mapovani[$klic] ?? ""), "— nenačítat —") ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="skupina" style="margin-bottom:0">
        <h2>Mapování stavů</h2>
        <?php if (!$stav_volby): ?>
          <p class="app-perex">Namapujte nejdřív pole se stavem a uložte; pak se sem doplní jeho hodnoty. Dokud stavy nesedí, zakládají se přepravy jako nové.</p>
        <?php else: ?>
          <p class="app-perex">Stav z Airtable vlevo, náš stav vpravo. Co nenamapujete, se založí jako <b>Nová</b>.</p>
          <div class="pole-radek tri">
            <?php foreach ($stav_volby as $hodnota): ?>
              <div class="pole">
                <label for="s-<?= chran(md5($hodnota)) ?>"><?= chran($hodnota) ?></label>
                <select id="s-<?= chran(md5($hodnota)) ?>" name="stav[<?= chran($hodnota) ?>]"><?= volby(STAVY, (string)($stavy_mapa[$hodnota] ?? ""), "— jako nová —") ?></select>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <button type="submit" class="tlacitko">Uložit mapování</button>
      </div>
    </form>

    <form method="post" action="<?= chran(odkaz("airtable")) ?>" class="filtr" style="margin-top:20px">
      <?= pole_token() ?>
      <div class="filtr-radek">
        <div class="pole"><label for="od">Nakládka od</label><input type="date" id="od" name="od" value="<?= chran(vstup_datum("od") ?: date("Y-m-01")) ?>"></div>
        <div class="pole"><label for="do">Nakládka do</label><input type="date" id="do" name="do" value="<?= chran(vstup_datum("do") ?: date("Y-m-t")) ?>"></div>
        <div class="pole">
          <label for="rezim">Co s tím, co už tady je</label>
          <select id="rezim" name="rezim"><?= volby([
            "doplnit" => "Doplnit jen prázdná pole",
            "nove"    => "Zakládat jen nové",
            "prepsat" => "Přepsat údaji z Airtable",
          ], vstup("rezim", "doplnit")) ?></select>
        </div>
        <div class="pole"><label for="strop">Nejvíc záznamů</label><input type="number" id="strop" name="strop" value="<?= chran(vstup("strop", "500")) ?>" min="1" max="<?= AIRTABLE_STROP ?>"></div>
        <div class="filtr-akce">
          <button type="submit" class="tlacitko obrys" name="akce" value="nanecisto">Ukázat nanečisto</button>
          <button type="submit" class="tlacitko" name="akce" value="nacist"
                  data-potvrdit="Načíst přepravy z Airtable do evidence? Prohlédli jste si to nanečisto?">Načíst doopravdy</button>
        </div>
      </div>
      <div class="pole-zaskrtnuti" style="margin-top:12px">
        <input type="checkbox" id="zakladat_firmy" name="zakladat_firmy" value="1" checked>
        <label for="zakladat_firmy">Zakládat firmy, které v adresáři nejsou <span class="napoveda">— jinak zůstane zásilka bez zákazníka a dopravce</span></label>
      </div>
      <p class="app-perex" style="margin:12px 0 0">Období se filtruje podle namapovaného data nakládky. Bez něj se načte, co rozhraní vrátí jako první, až do stropu.</p>
    </form>

    <?php if ($vysledek): ?>
      <h2 style="margin-top:28px"><?= $vysledek["nanecisto"] ? "Nanečisto — nic se nezapsalo" : "Výsledek načtení" ?></h2>
      <div class="dlazdice">
        <div class="dlazdice-polozka">
          <span class="popis">Ze zdroje</span>
          <span class="hodnota"><?= (int)$vysledek["zaznamu"] ?></span>
          <span class="doplnek">záznamů<?= (int)$vysledek["zaznamu"] >= (int)$vysledek["strop"] ? " · strop " . (int)$vysledek["strop"] . " vyčerpán" : "" ?></span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis"><?= $vysledek["nanecisto"] ? "Založilo by se" : "Založeno" ?></span>
          <span class="hodnota"><?= (int)$vysledek["zalozeno"] ?></span>
          <span class="doplnek">nových přeprav</span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis"><?= $vysledek["nanecisto"] ? "Doplnilo by se" : "Doplněno" ?></span>
          <span class="hodnota"><?= (int)$vysledek["doplneno"] ?></span>
          <span class="doplnek">existujících</span>
        </div>
        <div class="dlazdice-polozka">
          <span class="popis">Beze změny</span>
          <span class="hodnota"><?= (int)$vysledek["beze_zmeny"] + (int)$vysledek["preskoceno"] ?></span>
          <span class="doplnek"><?= (int)$vysledek["preskoceno"] ?> přeskočeno</span>
        </div>
      </div>
      <?php if ($vysledek["ukazka"]): ?>
        <div class="tabulka-obal">
          <table class="id-tabulka karty">
            <thead><tr><th>Číslo</th><th>Trasa</th><th>Datum</th><th>Zákazník</th><th>Dopravce</th><th>Stav</th><th class="vpravo">Cena dopravce</th><th>Co se stane</th></tr></thead>
            <tbody>
            <?php foreach ($vysledek["ukazka"] as $u): ?>
              <tr>
                <td class="cislo" data-popis="Číslo"><?= chran($u["cislo"]) ?></td>
                <td data-popis="Trasa"><?= chran($u["trasa"]) ?></td>
                <td data-popis="Datum"><?= chran(datum($u["datum"])) ?></td>
                <td data-popis="Zákazník"><?= chran($u["zakaznik"] ?: "—") ?></td>
                <td data-popis="Dopravce"><?= chran($u["dopravce"] ?: "—") ?></td>
                <td data-popis="Stav"><?= stitek_stavu($u["stav"]) ?><?php if ($u["stav_zvenku"] !== ""): ?><span class="druhotny"><?= chran($u["stav_zvenku"]) ?></span><?php endif; ?></td>
                <td class="cislo vpravo" data-popis="Cena dopravce"><?= chran(castka($u["cena_dopravce"])) ?></td>
                <td data-popis="Co se stane"><?= chran($u["co"]) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="app-perex" style="margin-top:12px">Ukázka prvních <?= count($vysledek["ukazka"]) ?> záznamů. Zkontrolujte hlavně místa, datum a firmy — když sedí, načtěte doopravdy.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>
<?php
pata();

<?php
/* Přehled — první obrazovka po přihlášení. Odpovídá na to, co dispečer
   řeší ráno: co se dnes a zítra nakládá, co ještě nemá dopravce, kde
   chybí doklady a co není vyfakturované. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();

$dnes  = date("Y-m-d");
$zitra = date("Y-m-d", strtotime("+1 day"));
$tyden_do = date("Y-m-d", strtotime("+7 days"));
$mesic_od = date("Y-m-01");
$mesic_do = date("Y-m-t");

$pocet_dnes  = (int)hodnota("SELECT COUNT(*) FROM prepravy WHERE sablona = 0 AND nakladka_datum = ? AND stav <> 'zruseno'", [$dnes]);
$pocet_tyden = (int)hodnota("SELECT COUNT(*) FROM prepravy WHERE sablona = 0 AND nakladka_datum BETWEEN ? AND ? AND stav <> 'zruseno'", [$dnes, $tyden_do]);

$bez_dopravce = radky(
  "SELECT p.* FROM prepravy p
    WHERE p.sablona = 0 AND (p.dopravce_id IS NULL OR p.dopravce_id = 0) AND p.stav <> 'zruseno'
      AND (p.nakladka_datum IS NULL OR p.nakladka_datum <= ?)
    ORDER BY COALESCE(p.nakladka_datum, '9999-12-31'), p.id LIMIT 20", [$tyden_do]);

$chybi_doklady = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
    WHERE p.sablona = 0 AND p.doklady <> 'prijato' AND p.stav IN ('vylozeno','doklady','fakturovano')
    ORDER BY COALESCE(p.vykladka_datum, p.nakladka_datum), p.id LIMIT 20");

$nevyfakturovano = $ceny ? radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev FROM prepravy p
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.sablona = 0 AND (p.faktura_vydana IS NULL OR p.faktura_vydana = '')
      AND p.stav IN ('vylozeno','doklady') AND " . JEN_SPEDICE . "
    ORDER BY COALESCE(p.vykladka_datum, p.nakladka_datum), p.id LIMIT 20") : [];

$blizke = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev, z.nazev AS zakaznik_nazev FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.sablona = 0 AND p.nakladka_datum IN (?, ?) AND p.stav <> 'zruseno'
    ORDER BY p.nakladka_datum, COALESCE(p.nakladka_od, '99:99'), p.id", [$dnes, $zitra]);

$hlaseni = radky(
  "SELECT p.id, p.cislo, p.hlaseni, p.hlaseni_kdy, p.nakladka_misto, p.vykladka_misto, d.nazev AS dopravce_nazev
     FROM prepravy p LEFT JOIN firmy d ON d.id = p.dopravce_id
    WHERE p.sablona = 0 AND p.hlaseni IS NOT NULL AND p.hlaseni <> ''
      AND p.hlaseni_kdy >= ? AND p.stav NOT IN ('zruseno','fakturovano')
    ORDER BY p.hlaseni_kdy DESC LIMIT 10", [date("Y-m-d H:i:s", strtotime("-7 days"))]);

/* Marže měsíce je spedice — jízdy pod externím dispečinkem fakturuje
   odesílateli klient a sčítají se zvlášť. */
$mesic = radek(
  "SELECT COUNT(*) AS pocet,
          COALESCE(SUM(cena_zakaznik), 0) AS trzba,
          COALESCE(SUM(cena_dopravce), 0) AS naklad
     FROM prepravy
    WHERE sablona = 0 AND stav <> 'zruseno' AND COALESCE(dispecink_klient_id, 0) = 0
      AND nakladka_datum BETWEEN ? AND ?", [$mesic_od, $mesic_do]);

/* Externí dispečink: kolik vozů klientů má dnes jízdu a co nemá vůz. */
$klienti_pocet = (int)hodnota("SELECT COUNT(*) FROM firmy WHERE dispecink = 1 AND aktivni = 1");
$vozy_dispecinku = 0; $vozy_dnes = 0; $bez_vozu_tyden = 0;
if ($klienti_pocet) {
  $vozy_dispecinku = (int)hodnota("SELECT COUNT(*) FROM vozidla v JOIN firmy f ON f.id = v.firma_id WHERE v.aktivni = 1 AND f.dispecink = 1 AND f.aktivni = 1");
  $vozy_dnes = (int)hodnota(
    "SELECT COUNT(DISTINCT p.vozidlo_id) FROM prepravy p
       JOIN vozidla v ON v.id = p.vozidlo_id JOIN firmy f ON f.id = v.firma_id
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND f.dispecink = 1 AND f.aktivni = 1 AND v.aktivni = 1
        AND p.nakladka_datum <= ? AND COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum) >= ?", [$dnes, $dnes]);
  $bez_vozu_tyden = (int)hodnota(
    "SELECT COUNT(*) FROM prepravy p
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND " . JEN_DISPECINK . "
        AND (p.vozidlo_id IS NULL OR p.vozidlo_id = 0) AND p.nakladka_datum BETWEEN ? AND ?", [$dnes, $tyden_do]);
}

$po_splatnosti = $ceny ? pohledavky(true) : [];
$po_splatnosti_soucet = 0; foreach ($po_splatnosti as $f) $po_splatnosti_soucet += (float)$f["castka_s_dph"] ?: (float)$f["castka"];
$zavazky_brzy = array_filter(zavazky(), fn($f) => $f["dnu_do"] !== null && $f["dnu_do"] <= 7);
$zavazky_soucet = 0; foreach ($zavazky_brzy as $f) $zavazky_soucet += (float)$f["castka_s_dph"] ?: (float)$f["castka"];

/* Doklady dopravců s koncem do měsíce a otevřené nabídky. */
$doklady_dopravcu = dopravci_s_upozornenim();
$nabidky_otevrene = $ceny ? radek("SELECT COUNT(*) AS pocet, COALESCE(SUM(cena), 0) AS hodnota FROM nabidky WHERE stav = 'otevrena'") : ["pocet" => 0, "hodnota" => 0];

hlava("Přehled", "prehled");
hlava_stranky("Provozní systém", "Přehled",
  '<a class="tlacitko" href="' . chran(odkaz("preprava", ["id" => "nova"])) . '">Nová přeprava</a>'
  . '<a class="tlacitko obrys" href="' . chran(odkaz("dispecink")) . '">Dispečerská tabule</a>');

/* Nedodělané nastavení, které se pozná až v provozu. */
if (je_spravce() && nastaveni("cislovani_potvrzeno") === "") : ?>
  <div class="doplnit" style="margin-bottom:20px">
    <b>Doplňte číselnou řadu.</b> Přepravy se zatím číslují výchozím tvarem
    <span class="cislo"><?= chran(slozene_cislo(nastaveni("cislovani_predpona", "{RR}-"), (int)nastaveni("cislovani_dalsi", "1"), (int)nastaveni("cislovani_mist", "4"))) ?></span>.
    Aby řada navázala na to, co vystavujete dnes, nastavte tvar a další číslo
    v <a href="<?= chran(odkaz("nastaveni")) ?>">Nastavení</a>.
  </div>
<?php endif;

if (je_spravce() && trim(nastaveni("podminky")) === "") : ?>
  <div class="doplnit" style="margin-bottom:20px">
    <b>Chybí podmínky objednávky přepravy.</b> Dokud je pole prázdné, tiskne se
    objednávka bez podmínek pro dopravce. Text si vložte
    v <a href="<?= chran(odkaz("nastaveni")) ?>">Nastavení</a> — vymyslet se nedá.
  </div>
<?php endif; ?>

<div class="dlazdice">
  <a class="dlazdice-polozka" href="<?= chran(odkaz("dispecink")) ?>">
    <span class="popis">Nakládky dnes</span>
    <span class="hodnota"><?= $pocet_dnes ?></span>
    <span class="doplnek">nejbližších 7 dní: <?= $pocet_tyden ?></span>
  </a>
  <a class="dlazdice-polozka" href="<?= chran(odkaz("prepravy", ["jen" => "bez_dopravce"])) ?>">
    <span class="popis">Bez dopravce</span>
    <span class="hodnota"><?= count($bez_dopravce) ?></span>
    <span class="doplnek">do sedmi dnů nebo bez data</span>
  </a>
  <a class="dlazdice-polozka" href="<?= chran(odkaz("prepravy", ["jen" => "doklady"])) ?>">
    <span class="popis">Chybí doklady</span>
    <span class="hodnota"><?= count($chybi_doklady) ?></span>
    <span class="doplnek">po vykládce</span>
  </a>
  <?php if ($ceny): ?>
    <a class="dlazdice-polozka" href="<?= chran(odkaz("fakturace")) ?>">
      <span class="popis">Marže tento měsíc</span>
      <span class="hodnota"><?= chran(castka((float)$mesic["trzba"] - (float)$mesic["naklad"])) ?></span>
      <span class="doplnek"><?= (int)$mesic["pocet"] ?> přeprav · tržba <?= chran(castka($mesic["trzba"])) ?></span>
    </a>
  <?php else: ?>
    <a class="dlazdice-polozka" href="<?= chran(odkaz("prepravy")) ?>">
      <span class="popis">Přeprav tento měsíc</span>
      <span class="hodnota"><?= (int)$mesic["pocet"] ?></span>
      <span class="doplnek"><?= chran(datum($mesic_od)) ?> – <?= chran(datum($mesic_do)) ?></span>
    </a>
  <?php endif; ?>
</div>

<?php if ($hlaseni): ?>
  <h2>Hlášení od dopravců</h2>
  <ul class="protokol" style="margin-bottom:28px">
    <?php foreach ($hlaseni as $hl): ?>
      <li>
        <a href="<?= chran(odkaz("preprava", ["id" => $hl["id"]])) ?>" class="cislo"><?= chran($hl["cislo"]) ?></a>
        <?= chran($hl["nakladka_misto"] ?: "?") ?> → <?= chran($hl["vykladka_misto"] ?: "?") ?>
        · <b><?= chran($hl["dopravce_nazev"] ?: "dopravce") ?>:</b> „<?= chran($hl["hlaseni"]) ?>"
        <time><?= chran(datum_cas($hl["hlaseni_kdy"])) ?></time>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($po_splatnosti || $zavazky_brzy || $klienti_pocet || $doklady_dopravcu || (int)$nabidky_otevrene["pocet"]): ?>
  <div class="dlazdice" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
    <?php if ($klienti_pocet): ?>
      <a class="dlazdice-polozka" href="<?= chran(odkaz("vozy")) ?>">
        <span class="popis">Vozy klientů dnes</span>
        <span class="hodnota"><?= $vozy_dnes ?> z <?= $vozy_dispecinku ?></span>
        <span class="doplnek">s jízdou · <?= $bez_vozu_tyden ? $bez_vozu_tyden . " " . sklonuj($bez_vozu_tyden, "jízda", "jízdy", "jízd") . " bez vozu do týdne" : "jízdy do týdne mají vůz" ?></span>
      </a>
    <?php endif; ?>
    <?php if ((int)$nabidky_otevrene["pocet"]): ?>
      <a class="dlazdice-polozka" href="<?= chran(odkaz("nabidky", ["stav" => "otevrena"])) ?>">
        <span class="popis">Otevřené nabídky</span>
        <span class="hodnota"><?= (int)$nabidky_otevrene["pocet"] ?></span>
        <span class="doplnek">v hodnotě <?= chran(castka($nabidky_otevrene["hodnota"])) ?> bez DPH</span>
      </a>
    <?php endif; ?>
    <?php if ($doklady_dopravcu): ?>
      <a class="dlazdice-polozka" href="<?= chran(odkaz("firmy", ["neaktivni" => "doklady"])) ?>">
        <span class="popis">Doklady dopravců</span>
        <span class="hodnota" style="color:var(--pozor-text)"><?= count($doklady_dopravcu) ?></span>
        <span class="doplnek"><?= chran(sklonuj(count($doklady_dopravcu), "dopravce má", "dopravci mají", "dopravců má")) ?> pojistku, oprávnění nebo smlouvu propadlou nebo končící do měsíce</span>
      </a>
    <?php endif; ?>
    <?php if ($po_splatnosti): ?>
      <a class="dlazdice-polozka" href="<?= chran(odkaz("fakturace", ["pohled" => "pohledavky"])) ?>">
        <span class="popis">Pohledávky po splatnosti</span>
        <span class="hodnota" style="color:var(--chyba-text)"><?= chran(castka($po_splatnosti_soucet)) ?></span>
        <span class="doplnek"><?= count($po_splatnosti) ?> faktur, nejstarší <?= (int)max(array_column($po_splatnosti, "dnu_po")) ?> dní</span>
      </a>
    <?php endif; ?>
    <?php if ($zavazky_brzy): ?>
      <a class="dlazdice-polozka" href="<?= chran(odkaz("fakturace", ["pohled" => "zavazky"])) ?>">
        <span class="popis">Dopravcům zaplatit do týdne</span>
        <span class="hodnota"><?= chran(castka($zavazky_soucet)) ?></span>
        <span class="doplnek"><?= count($zavazky_brzy) ?> faktur</span>
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<h2>Dnes a zítra</h2>
<?php if (!$blizke): ?>
  <p class="prazdno">Na dnešek ani zítřek není naplánovaná žádná nakládka.</p>
<?php else: ?>
  <div class="tabulka-obal">
    <table class="id-tabulka">
      <thead>
        <tr><th>Číslo</th><th>Den</th><th>Nakládka</th><th>Vykládka</th><th>Zákazník</th><th>Dopravce</th><th>Stav</th></tr>
      </thead>
      <tbody>
      <?php foreach ($blizke as $p): ?>
        <tr>
          <td><a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a></td>
          <td><?= $p["nakladka_datum"] === $dnes ? "dnes" : "zítra" ?>
            <span class="druhotny"><?= chran(okno($p["nakladka_od"], $p["nakladka_do"])) ?></span></td>
          <td><?= chran($p["nakladka_misto"] ?: "—") ?></td>
          <td><?= chran($p["vykladka_misto"] ?: "—") ?></td>
          <td><?= chran($p["zakaznik_nazev"] ?: "—") ?></td>
          <td><?= $p["dopravce_nazev"] ? chran($p["dopravce_nazev"]) : '<span class="stitek stitek-zrus">nezajištěno</span>' ?></td>
          <td><?= stitek_stavu($p["stav"]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="app-sloupce stejne" style="margin-top:36px">
  <div>
    <h2>Čeká na dopravce</h2>
    <?php if (!$bez_dopravce): ?>
      <p class="prazdno">Všechno má dopravce.</p>
    <?php else: ?>
      <ul class="protokol">
        <?php foreach ($bez_dopravce as $p): ?>
          <li>
            <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
            — <?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?>
            <time><?= $p["nakladka_datum"] ? chran(datum($p["nakladka_datum"])) : "bez data nakládky" ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div>
    <h2><?= $ceny ? "Vyloženo, nevyfakturováno" : "Chybějící doklady" ?></h2>
    <?php $seznam = $ceny ? $nevyfakturovano : $chybi_doklady; ?>
    <?php if (!$seznam): ?>
      <p class="prazdno">Nic nečeká.</p>
    <?php else: ?>
      <ul class="protokol">
        <?php foreach ($seznam as $p): ?>
          <li>
            <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
            — <?= chran($p["zakaznik_nazev"] ?? $p["dopravce_nazev"] ?? "—") ?>
            <time><?= chran(datum($p["vykladka_datum"] ?: $p["nakladka_datum"])) ?>
              <?= $ceny ? "" : " · " . chran(DOKLADY[$p["doklady"]] ?? "") ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php
pata();

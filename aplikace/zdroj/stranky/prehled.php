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

$pocet_dnes  = (int)hodnota("SELECT COUNT(*) FROM prepravy WHERE nakladka_datum = ? AND stav <> 'zruseno'", [$dnes]);
$pocet_tyden = (int)hodnota("SELECT COUNT(*) FROM prepravy WHERE nakladka_datum BETWEEN ? AND ? AND stav <> 'zruseno'", [$dnes, $tyden_do]);

$bez_dopravce = radky(
  "SELECT p.* FROM prepravy p
    WHERE (p.dopravce_id IS NULL OR p.dopravce_id = 0) AND p.stav <> 'zruseno'
      AND (p.nakladka_datum IS NULL OR p.nakladka_datum <= ?)
    ORDER BY COALESCE(p.nakladka_datum, '9999-12-31'), p.id LIMIT 20", [$tyden_do]);

$chybi_doklady = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
    WHERE p.doklady <> 'prijato' AND p.stav IN ('vylozeno','doklady','fakturovano')
    ORDER BY COALESCE(p.vykladka_datum, p.nakladka_datum), p.id LIMIT 20");

$nevyfakturovano = $ceny ? radky(
  "SELECT p.*, z.nazev AS zakaznik_nazev FROM prepravy p
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE (p.faktura_vydana IS NULL OR p.faktura_vydana = '')
      AND p.stav IN ('vylozeno','doklady')
    ORDER BY COALESCE(p.vykladka_datum, p.nakladka_datum), p.id LIMIT 20") : [];

$blizke = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev, z.nazev AS zakaznik_nazev FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.nakladka_datum IN (?, ?) AND p.stav <> 'zruseno'
    ORDER BY p.nakladka_datum, COALESCE(p.nakladka_od, '99:99'), p.id", [$dnes, $zitra]);

$mesic = radek(
  "SELECT COUNT(*) AS pocet,
          COALESCE(SUM(cena_zakaznik), 0) AS trzba,
          COALESCE(SUM(cena_dopravce), 0) AS naklad
     FROM prepravy
    WHERE stav <> 'zruseno' AND nakladka_datum BETWEEN ? AND ?", [$mesic_od, $mesic_do]);

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

<div class="app-sloupce" style="margin-top:36px;grid-template-columns:1fr 1fr">
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

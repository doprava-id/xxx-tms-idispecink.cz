<?php
/* Dispečerská tabule — týden po dnech podle data nakládky.

   Tabule odpovídá na jedinou otázku: co se kdy nakládá a kdo to veze.
   Zásilky bez dopravce mají červenou hranu, aby je nešlo přehlédnout. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Pondělí zvoleného týdne. */
$zvoleny = vstup_datum("tyden") ?: date("Y-m-d");
$pondeli = date_create($zvoleny);
$pondeli->modify("monday this week");
$nedele = (clone $pondeli)->modify("+6 days");

$predchozi = (clone $pondeli)->modify("-7 days")->format("Y-m-d");
$dalsi     = (clone $pondeli)->modify("+7 days")->format("Y-m-d");

$prepravy = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev, z.nazev AS zakaznik_nazev,
          (SELECT COUNT(*) FROM body b WHERE b.preprava_id = p.id) AS bodu
     FROM prepravy p
     LEFT JOIN firmy d ON d.id = p.dopravce_id
     LEFT JOIN firmy z ON z.id = p.zakaznik_id
    WHERE p.sablona = 0 AND p.nakladka_datum >= ? AND p.nakladka_datum <= ?
    ORDER BY p.nakladka_datum, COALESCE(p.nakladka_od, '99:99'), p.id",
  [$pondeli->format("Y-m-d"), $nedele->format("Y-m-d")]);

$po_dnech = [];
foreach ($prepravy as $p) $po_dnech[$p["nakladka_datum"]][] = $p;

$nezarazene = radky(
  "SELECT p.*, d.nazev AS dopravce_nazev,
          (SELECT COUNT(*) FROM body b WHERE b.preprava_id = p.id) AS bodu
     FROM prepravy p LEFT JOIN firmy d ON d.id = p.dopravce_id
    WHERE p.sablona = 0 AND (p.nakladka_datum IS NULL OR p.nakladka_datum = '') AND p.stav <> 'zruseno'
    ORDER BY p.id DESC LIMIT 30");

/* Jedna karta jízdy. */
function karta_jizdy(array $p): void {
  $trida = "jizda";
  if ($p["stav"] === "zruseno") $trida .= " zrusena";
  elseif (in_array($p["stav"], ["doklady", "fakturovano"], true)) $trida .= " hotova";
  elseif (empty($p["dopravce_id"])) $trida .= " bez-dopravce";
  elseif (in_array($p["stav"], ["nalozeno", "vylozeno"], true)) $trida .= " rozjeta";
  if (!empty($p["dispecink_klient_id"])) $trida .= " dispecink";
  ?>
  <a class="<?= $trida ?>" href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>">
    <b><?= chran($p["cislo"]) ?><?= okno($p["nakladka_od"], $p["nakladka_do"]) !== "" ? " · " . chran(okno($p["nakladka_od"], $p["nakladka_do"])) : "" ?></b>
    <span class="trasa"><?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?><?php
      if ((int)($p["bodu"] ?? 2) > 2) echo ' <span class="trasa-pocet">' . (int)$p["bodu"] . ' bodů</span>'; ?></span>
    <span class="radek"><?= chran($p["dopravce_nazev"] ?: "bez dopravce") ?><?= $p["spz"] ? " · " . chran($p["spz"]) : "" ?><?= !empty($p["dispecink_klient_id"]) ? " · dispečink" : "" ?></span>
    <?php if (!empty($p["zbozi"])): ?><span class="radek"><?= chran($p["zbozi"]) ?></span><?php endif; ?>
  </a>
  <?php
}

hlava("Dispečink", "dispecink");
hlava_stranky("Tabule", "Dispečink",
  '<a class="tlacitko" href="' . chran(odkaz("preprava", ["id" => "nova"])) . '">Nová přeprava</a>'
  . '<a class="tlacitko obrys" href="' . chran(odkaz("vozy", ["tyden" => $pondeli->format("Y-m-d")])) . '">Plán vozů</a>');
?>

<div class="tydny">
  <div class="tlacitka" style="margin:0">
    <a class="tlacitko obrys" href="<?= chran(odkaz("dispecink", ["tyden" => $predchozi])) ?>">← Předchozí týden</a>
    <a class="tlacitko obrys" href="<?= chran(odkaz("dispecink")) ?>">Tento týden</a>
    <a class="tlacitko obrys" href="<?= chran(odkaz("dispecink", ["tyden" => $dalsi])) ?>">Další týden →</a>
  </div>
  <b><?= chran(datum($pondeli->format("Y-m-d"))) ?> – <?= chran(datum($nedele->format("Y-m-d"))) ?>
     <span class="cislo" style="color:var(--text-tlum);font-weight:400">· <?= $pondeli->format("W") ?>. týden</span></b>
  <form method="get" action="index.php" class="tlacitka" style="margin:0;gap:8px">
    <input type="hidden" name="s" value="dispecink">
    <label for="tyden" class="jen-pro-ctecky">Přejít na týden</label>
    <input type="date" id="tyden" name="tyden" value="<?= chran($pondeli->format("Y-m-d")) ?>"
           style="background:var(--pozadi);border:1px solid var(--linka-pole);color:var(--text);font:inherit;font-size:.9rem;padding:9px 11px">
    <button type="submit" class="tlacitko obrys">Zobrazit</button>
  </form>
</div>

<div class="tabule-obal">
  <div class="tabule">
    <?php for ($i = 0; $i < 7; $i++):
      $den = (clone $pondeli)->modify("+" . $i . " days");
      $klic = $den->format("Y-m-d");
      $dnes = $klic === date("Y-m-d");
      $jizdy = $po_dnech[$klic] ?? [];
    ?>
      <div class="tabule-den<?= $dnes ? " dnes" : "" ?>">
        <div class="tabule-hlava">
          <b><?= chran(den_zkratka($klic)) ?> <?= chran(datum_kratce($klic)) ?></b>
          <span><?= count($jizdy) ?></span>
        </div>
        <div class="tabule-telo">
          <?php if (!$jizdy): ?>
            <p class="tabule-prazdno">Volno</p>
          <?php else: foreach ($jizdy as $p) karta_jizdy($p); endif; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php if ($nezarazene): ?>
  <h2 style="margin-top:36px">Bez data nakládky</h2>
  <p class="app-perex">Zásilky, které na tabuli nejsou vidět, dokud nedostanou datum.</p>
  <div class="tabule-obal">
    <div class="tabule" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php foreach ($nezarazene as $p): ?>
        <div class="tabule-den"><div class="tabule-telo"><?php karta_jizdy($p); ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php
pata();

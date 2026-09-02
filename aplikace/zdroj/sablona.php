<?php
/* =========================================================================
   Rozvržení stránky — hlavička s navigací, vzkazy, patička

   Aplikace stojí na firemním stylu webu (../assets/css/firemni-styl.css).
   Vlastní CSS v aplikace.css jen doplňuje hustší pracovní rozvržení;
   barvy si nedefinuje a definovat nesmí.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const NABIDKA = [
  "prehled"   => "Přehled",
  "prepravy"  => "Přepravy",
  "nabidky"   => "Nabídky",
  "dispecink" => "Dispečink",
  "vozy"      => "Vozy",
  "firmy"     => "Firmy",
  "mista"     => "Místa",
  "fakturace" => "Fakturace",
];

/* Stránka bez navigace (tisk objednávky) — hlavička i patička odpadají. */
$BEZ_NAVIGACE = false;

function hlava(string $nadpis, string $aktivni = "", array $volby = []): void {
  global $BEZ_NAVIGACE;
  $BEZ_NAVIGACE = !empty($volby["bez_navigace"]);
  $bez_navigace = $BEZ_NAVIGACE;
  header("Content-Type: text/html; charset=utf-8");
  header("X-Robots-Tag: noindex, nofollow");
  header("Cache-Control: no-store, no-cache, must-revalidate");
  header("Referrer-Policy: " . (string)($volby["referrer"] ?? "same-origin"));
  header("X-Content-Type-Options: nosniff");
  header("X-Frame-Options: DENY");
  ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#14191B">
<meta name="referrer" content="<?= chran((string)($volby["referrer"] ?? "same-origin")) ?>">
<title><?= chran($nadpis) ?> — provozní systém iDispečink.cz</title>
<link rel="icon" href="../favicon.ico" sizes="32x32">
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/css/firemni-styl.css">
<link rel="stylesheet" href="aplikace.css">
<noscript>
  <!-- Bez JavaScriptu tlačítko Menu nefunguje. Nabídku proto na úzkých
       displejích zobrazíme rovnou a tlačítko schováme. -->
  <style>
    @media (max-width: 1240px) {
      .app-hlavicka .menu-prepinac { display: none; }
      #app-menu { display: flex; }
    }
  </style>
</noscript>
</head>
<body class="aplikace<?= $bez_navigace ? " bez-navigace" : "" ?>">
<a class="preskocit" href="#obsah">Přeskočit na obsah</a>
<?php if (!$bez_navigace): ?>
<header class="hlavicka app-hlavicka">
  <div class="obal">
    <a class="logo" href="<?= chran(odkaz("prehled")) ?>">
      <img src="../assets/img/logo-idispecink.svg" alt="iDispečink.cz" width="214" height="34">
    </a>
    <span class="app-znacka">Provozní systém</span>
    <?php if (prihlasen()): ?>
      <button class="menu-prepinac" type="button" aria-expanded="false" aria-controls="app-menu">Menu</button>
      <nav class="menu app-menu" id="app-menu" aria-label="Hlavní nabídka">
        <?php foreach (NABIDKA as $klic => $popis): ?>
          <?php if ($klic === "nabidky" && !vidi_ceny()) continue; ?>
          <?php if ($klic === "fakturace" && !smi_fakturaci()) continue; ?>
          <a href="<?= chran(odkaz($klic)) ?>"<?= $aktivni === $klic ? " aria-current=\"page\"" : "" ?>><?= chran($popis) ?></a>
        <?php endforeach; ?>
        <?php if (je_spravce()): ?>
          <a href="<?= chran(odkaz("nastaveni")) ?>"<?= $aktivni === "nastaveni" ? " aria-current=\"page\"" : "" ?>>Nastavení</a>
        <?php endif; ?>
        <a class="app-uzivatel" href="<?= chran(odkaz("ucet")) ?>" title="Můj účet: heslo a druhý faktor"><?= chran(uzivatel()["jmeno"] ?? "") ?></a>
        <form method="post" action="<?= chran(odkaz("odhlaseni")) ?>" class="app-odhlasit">
          <?= pole_token() ?>
          <button type="submit" class="tlacitko obrys">Odhlásit</button>
        </form>
      </nav>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>
<main id="obsah" class="app-obsah">
  <div class="obal">
    <?php foreach (vyzvedni_vzkazy() as $v): ?>
      <p class="vzkaz vzkaz-<?= chran($v["druh"]) ?>" role="status"><?= chran($v["text"]) ?></p>
    <?php endforeach; ?>
<?php
}

function pata(): void {
  global $BEZ_NAVIGACE;
  ?>
  </div>
</main>
<?php if (prihlasen() && !$BEZ_NAVIGACE): ?>
<footer class="app-pata">
  <div class="obal">
    <span>iDispečink.cz s.r.o. — provozní systém</span>
    <span><a href="../index.html">Zpět na web</a></span>
  </div>
</footer>
<?php endif; ?>
<script src="aplikace.js"></script>
</body>
</html>
<?php
}

/* Hlavička pracovní stránky: nadpis vlevo, akce vpravo. */
function hlava_stranky(string $stitek, string $nadpis, string $akce = ""): void {
  ?>
  <div class="app-hlava">
    <div>
      <span class="nadpis-stitek"><?= chran($stitek) ?></span>
      <h1><?= chran($nadpis) ?></h1>
    </div>
    <?php if ($akce !== ""): ?><div class="app-hlava-akce"><?= $akce ?></div><?php endif; ?>
  </div>
  <?php
}

/* Štítek stavu přepravy. */
function stitek_stavu(?string $stav): string {
  $barva = STAVY_BARVA[$stav] ?? "ceka";
  return "<span class=\"stitek stitek-" . chran($barva) . "\">" . chran(nazev_stavu($stav)) . "</span>";
}

/* <option> seznam z pole klíč => popis. */
function volby(array $seznam, $vybrano, string $prazdne = ""): string {
  $ven = "";
  if ($prazdne !== "") {
    $ven .= "<option value=\"\">" . chran($prazdne) . "</option>";
  }
  foreach ($seznam as $klic => $popis) {
    $vyb = ((string)$klic === (string)$vybrano) ? " selected" : "";
    $ven .= "<option value=\"" . chran($klic) . "\"" . $vyb . ">" . chran($popis) . "</option>";
  }
  return $ven;
}

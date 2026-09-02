<?php
/* Plán vozů — externí dispečink: týden po vozidlech klientů, ne po dnech.

   Řádek je vůz, sloupce jsou dny; jízda leží ve dnech od nakládky po
   vykládku. Prázdná buňka je volný vůz, kterému sháníme zakázku — kliknutím
   se založí přeprava s předvyplněným dopravcem, vozem a dnem nakládky.
   Vpravo je vytěžení za týden: kolik jízd a za kolik. Cena jízdy je cena
   dopravce, tedy to, co vůz klienta vydělá — vidí ji každý dispečer. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$klienti = klienti_dispecinku();
$podle_id = [];
foreach ($klienti as $k) $podle_id[(int)$k["id"]] = $k;

$klient_id = vstup_cislo("klient") ?: 0;
if ($klient_id && !isset($podle_id[$klient_id])) $klient_id = 0;
$vybrani = $klient_id ? [$podle_id[$klient_id]] : $klienti;

/* Pondělí zvoleného týdne. */
$zvoleny = vstup_datum("tyden") ?: date("Y-m-d");
$pondeli = date_create($zvoleny);
$pondeli->modify("monday this week");
$nedele = (clone $pondeli)->modify("+6 days");
$predchozi = (clone $pondeli)->modify("-7 days")->format("Y-m-d");
$dalsi     = (clone $pondeli)->modify("+7 days")->format("Y-m-d");
$zacatek = $pondeli->format("Y-m-d");
$konec   = $nedele->format("Y-m-d");
$dny = [];
for ($i = 0; $i < 7; $i++) $dny[] = (clone $pondeli)->modify("+" . $i . " days")->format("Y-m-d");

/* Vozy vybraných klientů a jízdy, které se týdne dotýkají. Do plánu patří
   jízda pod dispečinkem klienta i jízda, která jeho vůz obsadila jako
   běžná subdodávka — vůz je ten den obsazený tak jako tak. */
$vozy = []; $vozy_podle_klienta = [];
foreach ($vybrani as $k) {
  foreach (vozy_klienta((int)$k["id"]) as $v) {
    $vozy[(int)$v["id"]] = $v;
    $vozy_podle_klienta[(int)$k["id"]][] = $v;
  }
}
$jizdy = []; $bez_data = [];
if ($vybrani) {
  $id_klientu = implode(",", array_map(fn($k) => (int)$k["id"], $vybrani));
  $id_vozu    = $vozy ? implode(",", array_map("intval", array_keys($vozy))) : "0";
  $patri = "(p.dispecink_klient_id IN (" . $id_klientu . ") OR p.vozidlo_id IN (" . $id_vozu . "))";
  $jizdy = radky(
    "SELECT p.*, z.nazev AS zakaznik_nazev
       FROM prepravy p LEFT JOIN firmy z ON z.id = p.zakaznik_id
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND " . $patri . "
        AND COALESCE(p.nakladka_datum, '') <> '' AND p.nakladka_datum <= ?
        AND COALESCE(NULLIF(p.vykladka_datum, ''), p.nakladka_datum) >= ?
      ORDER BY p.nakladka_datum, COALESCE(p.nakladka_od, '99:99'), p.id", [$konec, $zacatek]);
  $bez_data = radky(
    "SELECT p.*, d.nazev AS dopravce_nazev
       FROM prepravy p LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND " . $patri . " AND COALESCE(p.nakladka_datum, '') = ''
      ORDER BY p.id DESC LIMIT 30");
}

/* Rozdělení jízd: podle vozu, jinak pod klienta bez vozu. Vytěžení se
   počítá z jízd s nakládkou v týdnu, ať se jízda přes neděli nepočítá dvakrát. */
$podle_vozu = []; $bez_vozu = []; $souhrn_vozu = []; $souhrn_klienta = [];
foreach ($jizdy as $j) {
  $vid = (int)$j["vozidlo_id"];
  if ($vid && isset($vozy[$vid])) {
    $podle_vozu[$vid][] = $j;
    $kid = (int)$vozy[$vid]["firma_id"];
  } else {
    $vid = 0;
    $kid = (int)$j["dispecink_klient_id"];
    $bez_vozu[$kid][] = $j;
  }
  if ($j["nakladka_datum"] >= $zacatek) {
    $souhrn_vozu[$vid]["jizd"]     = ($souhrn_vozu[$vid]["jizd"] ?? 0) + 1;
    $souhrn_vozu[$vid]["obrat"]    = ($souhrn_vozu[$vid]["obrat"] ?? 0) + (float)$j["cena_dopravce"];
    $souhrn_klienta[$kid]["jizd"]  = ($souhrn_klienta[$kid]["jizd"] ?? 0) + 1;
    $souhrn_klienta[$kid]["obrat"] = ($souhrn_klienta[$kid]["obrat"] ?? 0) + (float)$j["cena_dopravce"];
  }
}

/* Karta jízdy v buňce dne. První den nese číslo, trasu, zákazníka, zboží
   a cenu (co, za kolik, pro koho); další dny jen to, že vůz pokračuje. */
function karta_planu(array $j, string $den, string $zacatek): void {
  $od = (string)$j["nakladka_datum"];
  $do = (string)($j["vykladka_datum"] ?: $j["nakladka_datum"]);
  if ($do < $od) $do = $od;
  if ($den < $od || $den > $do) return;
  $prvni = $den === $od;
  $trida = "jizda";
  if (!$prvni) $trida .= " pokracovani";
  elseif (in_array($j["stav"], ["doklady", "fakturovano"], true)) $trida .= " hotova";
  elseif (in_array($j["stav"], ["nalozeno", "vylozeno"], true)) $trida .= " rozjeta";
  if (empty($j["dispecink_klient_id"])) $trida .= " subdodavka";
  ?>
  <a class="<?= $trida ?>" href="<?= chran(odkaz("preprava", ["id" => $j["id"]])) ?>">
    <?php if ($prvni): ?>
      <b><?= chran($j["cislo"]) ?><?= okno($j["nakladka_od"], $j["nakladka_do"]) !== "" ? " · " . chran(okno($j["nakladka_od"], $j["nakladka_do"])) : "" ?></b>
      <span class="trasa"><?= chran($j["nakladka_misto"] ?: "?") ?> → <?= chran($j["vykladka_misto"] ?: "?") ?><?= $do !== $od ? " <span class=\"trasa-pocet\">do " . chran(den_zkratka($do)) . "</span>" : "" ?></span>
      <span class="radek"><?= chran($j["zakaznik_nazev"] ?: "bez zákazníka") ?></span>
      <span class="radek"><?= chran($j["zbozi"] ?: "zboží nezadáno") ?> · <?= chran(castka($j["cena_dopravce"])) ?></span>
      <?php if (empty($j["dispecink_klient_id"])): ?><span class="radek">subdodávka, ne dispečink</span><?php endif; ?>
    <?php else: ?>
      <span class="trasa">↳ <?= chran($j["cislo"]) ?> pokračuje</span>
      <span class="radek"><?= $den === $do ? "vykládka " . chran($j["vykladka_misto"] ?: "?") . ($j["vykladka_od"] || $j["vykladka_do"] ? " " . chran(okno($j["vykladka_od"], $j["vykladka_do"])) : "") : "na cestě" ?></span>
    <?php endif; ?>
  </a>
  <?php
}

$dnes = date("Y-m-d");
$akce = '<a class="tlacitko" href="' . chran(odkaz("preprava", ["id" => "nova", "dispecink" => 1])) . '">Nová jízda</a>'
      . '<a class="tlacitko obrys" href="' . chran(odkaz("dispecink", ["tyden" => $zacatek])) . '">Tabule po dnech</a>';
if (vidi_ceny()) $akce .= '<a class="tlacitko obrys" href="' . chran(odkaz("fakturace", ["pohled" => "dispecink"])) . '">Podklad k fakturaci služby</a>';

hlava("Plán vozů", "vozy");
hlava_stranky("Externí dispečink", "Plán vozů", $akce);
?>

<?php if (!$klienti): ?>
  <p class="prazdno">Zatím žádný klient dispečinku. Na kartě firmy zaškrtněte „Klient externího dispečinku" a přidejte jeho vozy — objeví se tady, každý na vlastním řádku.
    <a href="<?= chran(odkaz("firmy")) ?>">Otevřít firmy</a></p>
<?php else: ?>

<div class="tydny">
  <div class="tlacitka" style="margin:0">
    <a class="tlacitko obrys" href="<?= chran(odkaz("vozy", array_filter(["tyden" => $predchozi, "klient" => $klient_id]))) ?>">← Předchozí týden</a>
    <a class="tlacitko obrys" href="<?= chran(odkaz("vozy", array_filter(["klient" => $klient_id]))) ?>">Tento týden</a>
    <a class="tlacitko obrys" href="<?= chran(odkaz("vozy", array_filter(["tyden" => $dalsi, "klient" => $klient_id]))) ?>">Další týden →</a>
  </div>
  <b><?= chran(datum($zacatek)) ?> – <?= chran(datum($konec)) ?>
     <span class="cislo" style="color:var(--text-tlum);font-weight:400">· <?= $pondeli->format("W") ?>. týden</span></b>
  <form method="get" action="index.php" class="tlacitka" style="margin:0;gap:8px">
    <input type="hidden" name="s" value="vozy">
    <label for="klient" class="jen-pro-ctecky">Klient</label>
    <select id="klient" name="klient" style="background:var(--pozadi);border:1px solid var(--linka-pole);color:var(--text);font:inherit;font-size:.9rem;padding:9px 11px">
      <?php $volby_klientu = []; foreach ($klienti as $k) $volby_klientu[(string)$k["id"]] = (string)$k["nazev"]; ?>
      <?= volby($volby_klientu, (string)$klient_id, "Všichni klienti") ?>
    </select>
    <label for="tyden" class="jen-pro-ctecky">Přejít na týden</label>
    <input type="date" id="tyden" name="tyden" value="<?= chran($zacatek) ?>"
           style="background:var(--pozadi);border:1px solid var(--linka-pole);color:var(--text);font:inherit;font-size:.9rem;padding:9px 11px">
    <button type="submit" class="tlacitko obrys">Zobrazit</button>
  </form>
</div>

<?php foreach ($vybrani as $k):
  $kid = (int)$k["id"];
  $vozy_k = $vozy_podle_klienta[$kid] ?? [];
  $sk = $souhrn_klienta[$kid] ?? ["jizd" => 0, "obrat" => 0.0];
?>
  <section style="margin-bottom:32px">
    <div class="app-hlava" style="margin-bottom:10px">
      <div>
        <h2 style="margin:0"><a href="<?= chran(odkaz("firma", ["id" => $kid])) ?>"><?= chran($k["nazev"]) ?></a></h2>
        <p class="app-perex" style="margin:2px 0 0">
          <?= count($vozy_k) ?> <?= sklonuj(count($vozy_k), "vůz", "vozy", "vozů") ?>
          · tento týden <?= (int)$sk["jizd"] ?> <?= sklonuj((int)$sk["jizd"], "jízda", "jízdy", "jízd") ?>
          · obrat vozů <b class="cislo"><?= chran(castka($sk["obrat"])) ?></b>
          · <?= isset(DISPECINK_UCTOVANI[(string)$k["dispecink_uctovani"]]) ? chran(mb_strtolower(DISPECINK_UCTOVANI[(string)$k["dispecink_uctovani"]])) : "způsob účtování nezadán" ?>
        </p>
      </div>
      <div class="app-hlava-akce netisknout">
        <a class="tlacitko obrys" href="<?= chran(odkaz("preprava", ["id" => "nova", "dispecink" => 1, "dopravce" => $kid, "den" => $zacatek])) ?>">Nová jízda pro klienta</a>
      </div>
    </div>

    <?php if (!$vozy_k && empty($bez_vozu[$kid])): ?>
      <p class="prazdno">Klient nemá žádný vůz. Přidejte je na <a href="<?= chran(odkaz("firma", ["id" => $kid])) ?>">kartě firmy</a>.</p>
    <?php else: ?>
      <div class="tabulka-obal">
        <table class="id-tabulka plan-vozu">
          <colgroup><col class="sl-vuz"><?php for ($i = 0; $i < 7; $i++) echo "<col>"; ?><col class="sl-tyden"></colgroup>
          <thead>
            <tr>
              <th>Vůz</th>
              <?php foreach ($dny as $den): ?>
                <th<?= $den === $dnes ? ' class="dnes"' : "" ?>><?= chran(den_zkratka($den)) ?> <?= chran(datum_kratce($den)) ?></th>
              <?php endforeach; ?>
              <th class="vpravo">Týden</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($vozy_k as $v): $vid = (int)$v["id"]; $sv = $souhrn_vozu[$vid] ?? ["jizd" => 0, "obrat" => 0.0]; ?>
            <tr>
              <td class="vuz">
                <b class="cislo"><?= chran($v["spz"]) ?></b>
                <span class="druhotny"><?= chran(nazev_typu_vozidla($v["typ"])) ?></span>
                <?php if ($v["poznamka"]): ?><span class="druhotny"><?= chran($v["poznamka"]) ?></span><?php endif; ?>
              </td>
              <?php foreach ($dny as $den): ?>
                <td<?= $den === $dnes ? ' class="dnes"' : "" ?>>
                  <?php foreach ($podle_vozu[$vid] ?? [] as $j) karta_planu($j, $den, $zacatek); ?>
                  <a class="plan-pridat netisknout" href="<?= chran(odkaz("preprava", ["id" => "nova", "dispecink" => 1, "dopravce" => $kid, "vozidlo" => $vid, "den" => $den])) ?>"
                     title="Založit jízdu pro vůz <?= chran($v["spz"]) ?>, nakládka <?= chran(datum($den)) ?>">+ jízda</a>
                </td>
              <?php endforeach; ?>
              <td class="vpravo">
                <b class="cislo"><?= (int)$sv["jizd"] ?> <?= sklonuj((int)$sv["jizd"], "jízda", "jízdy", "jízd") ?></b>
                <span class="druhotny cislo"><?= chran(castka($sv["obrat"])) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!empty($bez_vozu[$kid])): $sv = $souhrn_vozu[0] ?? ["jizd" => 0, "obrat" => 0.0]; ?>
            <tr class="bez-vozu">
              <td class="vuz">
                <b>Bez vozu</b>
                <span class="druhotny">přiřaďte vůz na kartě jízdy</span>
              </td>
              <?php foreach ($dny as $den): ?>
                <td<?= $den === $dnes ? ' class="dnes"' : "" ?>>
                  <?php foreach ($bez_vozu[$kid] as $j) karta_planu($j, $den, $zacatek); ?>
                </td>
              <?php endforeach; ?>
              <td class="vpravo"><b class="cislo"><?= count($bez_vozu[$kid]) ?></b></td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if ($bez_data): ?>
  <h2 style="margin-top:8px">Bez data nakládky</h2>
  <p class="app-perex">Jízdy klientů, které v plánu nejsou vidět, dokud nedostanou datum.</p>
  <ul class="protokol">
    <?php foreach ($bez_data as $p): ?>
      <li>
        <a href="<?= chran(odkaz("preprava", ["id" => $p["id"]])) ?>" class="cislo"><?= chran($p["cislo"]) ?></a>
        — <?= chran($p["nakladka_misto"] ?: "?") ?> → <?= chran($p["vykladka_misto"] ?: "?") ?>
        <time><?= chran($p["dopravce_nazev"] ?: "bez dopravce") ?><?= $p["spz"] ? " · " . chran($p["spz"]) : "" ?></time>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php endif; ?>
<?php
pata();

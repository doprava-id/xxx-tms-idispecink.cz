<?php
/* =========================================================================
   Hlídání — ranní souhrn e-mailem

   Co ráno hlídá: nakládky bez dopravce v nejbližších dnech, doklady
   chybějící déle než týden po vykládce a končící doklady dopravců.
   Souhrn jde všem aktivním uživatelům s e-mailem — oběma dispečerům.

   Spouští se třemi cestami: naplánovanou úlohou hostingu (adresa
   index.php?s=hlidani&klic=… s klíčem z config.php), ručně z Nastavení,
   a jako záloha při prvním otevření systému toho dne, kdyby hosting cron
   neměl. Ceny se v souhrnu neobjevují — chodí i tomu, kdo je nevidí.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const HLIDANI_DNU_NAKLADKA = 3;   /* nakládka bez dopravce do tolika dnů */
const HLIDANI_DNU_DOKLADY  = 7;   /* doklady chybějící déle než týden po vykládce */

function hlidani_zapnuto(): bool {
  return nastaveni("hlidani_zapnuto", "1") === "1";
}

/* Co je potřeba hlídat — stejné seznamy, jaké ukazuje Přehled. */
function hlidani_souhrn(): array {
  $dnes = date("Y-m-d");
  $bez_dopravce = radky(
    "SELECT p.cislo, p.nakladka_misto, p.vykladka_misto, p.nakladka_datum, p.nakladka_od, p.nakladka_do, z.nazev AS zakaznik_nazev
       FROM prepravy p LEFT JOIN firmy z ON z.id = p.zakaznik_id
      WHERE p.sablona = 0 AND p.stav <> 'zruseno' AND (p.dopravce_id IS NULL OR p.dopravce_id = 0)
        AND p.nakladka_datum IS NOT NULL AND p.nakladka_datum <> '' AND p.nakladka_datum <= ?
      ORDER BY p.nakladka_datum, COALESCE(p.nakladka_od, '99:99'), p.id",
    [date("Y-m-d", strtotime("+" . HLIDANI_DNU_NAKLADKA . " days"))]);
  $doklady = radky(
    "SELECT p.cislo, p.nakladka_misto, p.vykladka_misto, p.vykladka_datum, p.doklady, d.nazev AS dopravce_nazev
       FROM prepravy p LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE p.sablona = 0 AND p.doklady <> 'prijato' AND p.stav IN ('vylozeno', 'doklady', 'fakturovano')
        AND p.vykladka_datum IS NOT NULL AND p.vykladka_datum <> '' AND p.vykladka_datum <= ?
      ORDER BY p.vykladka_datum, p.id",
    [date("Y-m-d", strtotime("-" . HLIDANI_DNU_DOKLADY . " days"))]);
  foreach ($doklady as &$d) $d["dnu"] = (int)round((strtotime($dnes) - strtotime((string)$d["vykladka_datum"])) / 86400);
  unset($d);
  $nakladek_dnes = (int)hodnota("SELECT COUNT(*) FROM prepravy WHERE sablona = 0 AND stav <> 'zruseno' AND nakladka_datum = ?", [$dnes]);

  return [
    "datum"            => $dnes,
    "nakladek_dnes"    => $nakladek_dnes,
    "bez_dopravce"     => $bez_dopravce,
    "doklady"          => $doklady,
    "doklady_dopravcu" => dopravci_s_upozornenim(),
  ];
}

function hlidani_ma_co_hlasit(array $s): bool {
  return $s["bez_dopravce"] || $s["doklady"] || $s["doklady_dopravcu"];
}

function hlidani_text(array $s): string {
  $r = ["RANNÍ SOUHRN " . datum($s["datum"]) . " — provozní systém " . nastaveni("firma_nazev"), ""];
  $r[] = "Nakládky dnes: " . $s["nakladek_dnes"];
  if (!hlidani_ma_co_hlasit($s)) { $r[] = ""; $r[] = "Nic nehoří: všechny blízké nakládky mají dopravce, doklady nechybí, doklady dopravců platí."; }
  if ($s["bez_dopravce"]) {
    $r[] = ""; $r[] = "BEZ DOPRAVCE — nakládka do " . HLIDANI_DNU_NAKLADKA . " dnů (" . count($s["bez_dopravce"]) . ")";
    foreach ($s["bez_dopravce"] as $p) $r[] = "  " . $p["cislo"] . " " . datum($p["nakladka_datum"]) . " " . okno($p["nakladka_od"], $p["nakladka_do"]) . " · " . ($p["nakladka_misto"] ?: "?") . " → " . ($p["vykladka_misto"] ?: "?") . ($p["zakaznik_nazev"] ? " · " . $p["zakaznik_nazev"] : "");
  }
  if ($s["doklady"]) {
    $r[] = ""; $r[] = "DOKLADY CHYBÍ DÉLE NEŽ TÝDEN PO VYKLÁDCE (" . count($s["doklady"]) . ")";
    foreach ($s["doklady"] as $p) $r[] = "  " . $p["cislo"] . " · " . ($p["dopravce_nazev"] ?: "bez dopravce") . " · vykládka " . datum($p["vykladka_datum"]) . " (" . $p["dnu"] . " dní)";
  }
  if ($s["doklady_dopravcu"]) {
    $r[] = ""; $r[] = "DOKLADY DOPRAVCŮ (" . count($s["doklady_dopravcu"]) . ")";
    foreach ($s["doklady_dopravcu"] as $f) foreach ($f["upozorneni"] as $u) $r[] = "  " . $f["nazev"] . ": " . $u["text"];
  }
  $r[] = ""; $r[] = "Otevřít systém: " . zakladni_adresa();
  return implode("\n", $r);
}

function hlidani_html(array $s): string {
  $odst = fn(string $nadpis) => '<p style="margin:18px 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>' . chran($nadpis) . '</b></p>';
  $seznam = function (array $radky): string {
    $h = '<ul style="margin:0;padding-left:18px">';
    foreach ($radky as $t) $h .= '<li style="margin:0 0 4px">' . chran($t) . '</li>';
    return $h . '</ul>';
  };
  $h = '<!doctype html><html lang="cs"><body style="margin:0;padding:20px;background:#f3f1ec;font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#111">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #ddd;border-top:4px solid #F0B41E;padding:24px">'
    . '<p style="margin:0;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>Ranní souhrn</b></p>'
    . '<p style="margin:2px 0 14px;font-size:22px;font-weight:700">' . chran(datum($s["datum"])) . '</p>'
    . '<p style="margin:0">Nakládky dnes: <b>' . (int)$s["nakladek_dnes"] . '</b></p>';
  if (!hlidani_ma_co_hlasit($s)) $h .= '<p style="margin:14px 0 0;color:#1f6b3a"><b>Nic nehoří.</b> Všechny blízké nakládky mají dopravce, doklady nechybí, doklady dopravců platí.</p>';
  if ($s["bez_dopravce"]) {
    $r = [];
    foreach ($s["bez_dopravce"] as $p) $r[] = $p["cislo"] . " · " . datum($p["nakladka_datum"]) . " " . okno($p["nakladka_od"], $p["nakladka_do"]) . " · " . ($p["nakladka_misto"] ?: "?") . " → " . ($p["vykladka_misto"] ?: "?") . ($p["zakaznik_nazev"] ? " · " . $p["zakaznik_nazev"] : "");
    $h .= $odst("Bez dopravce — nakládka do " . HLIDANI_DNU_NAKLADKA . " dnů (" . count($r) . ")") . $seznam($r);
  }
  if ($s["doklady"]) {
    $r = [];
    foreach ($s["doklady"] as $p) $r[] = $p["cislo"] . " · " . ($p["dopravce_nazev"] ?: "bez dopravce") . " · vykládka " . datum($p["vykladka_datum"]) . " (" . $p["dnu"] . " dní)";
    $h .= $odst("Doklady chybí déle než týden po vykládce (" . count($r) . ")") . $seznam($r);
  }
  if ($s["doklady_dopravcu"]) {
    $r = [];
    foreach ($s["doklady_dopravcu"] as $f) foreach ($f["upozorneni"] as $u) $r[] = $f["nazev"] . ": " . $u["text"];
    $h .= $odst("Doklady dopravců (" . count($s["doklady_dopravcu"]) . ")") . $seznam($r);
  }
  $h .= '<p style="margin:22px 0 0"><a href="' . chran(zakladni_adresa()) . '" style="display:inline-block;background:#F0B41E;color:#343F41;font-weight:700;padding:12px 20px;text-decoration:none">Otevřít provozní systém</a></p>'
    . '</div></body></html>';
  return $h;
}

/* Příjemci: všichni aktivní uživatelé s platným e-mailem. */
function hlidani_prijemci(): array {
  $ven = [];
  foreach (radky("SELECT email FROM uzivatele WHERE aktivni = 1 ORDER BY id") as $u) {
    if (platny_email((string)$u["email"])) $ven[] = (string)$u["email"];
  }
  return $ven;
}

/* Pošle souhrn a zapíše, kdy a jak to dopadlo. Vrací [poslano, prijemcu, chyba]. */
function hlidani_odesli(string $spoustec): array {
  $s = hlidani_souhrn();
  $prijemci = hlidani_prijemci();
  $predmet = "Ranní souhrn " . datum($s["datum"]) . (hlidani_ma_co_hlasit($s) ? "" : " — nic nehoří");
  $text = hlidani_text($s); $html = hlidani_html($s);
  $poslano = 0;
  foreach ($prijemci as $komu) {
    if (posli_email($komu, $predmet, $text, $html)) $poslano++;
  }
  $chyba = null;
  if (!$prijemci) $chyba = "Žádný uživatel nemá platný e-mail.";
  elseif ($poslano === 0) $chyba = "Poštovní server zprávu nepřijal — hosting musí mít povolené odesílání pošty.";
  uloz_nastaveni("hlidani_naposledy", $s["datum"]);
  uloz_nastaveni("hlidani_vysledek", date("j. n. Y H:i") . " · " . $spoustec . " · " . ($chyba ?: "odesláno " . $poslano . " z " . count($prijemci)));
  zapis_udalost(null, "Ranní souhrn (" . $spoustec . "): " . ($chyba ?: "odesláno " . $poslano . " příjemcům"));
  return ["poslano" => $poslano, "prijemcu" => count($prijemci), "chyba" => $chyba];
}

/* Záloha bez cronu: první otevření systému toho dne souhrn pošle samo.
   Zapíše datum ještě před odesláním, aby dva souběžné požadavky
   neposlaly souhrn dvakrát. */
function hlidani_denni_kontrola(): void {
  if (!hlidani_zapnuto()) return;
  if (nastaveni("hlidani_naposledy") === date("Y-m-d")) return;
  uloz_nastaveni("hlidani_naposledy", date("Y-m-d"));
  hlidani_odesli("první otevření dne");
}

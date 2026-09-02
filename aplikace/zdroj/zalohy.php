<?php
/* =========================================================================
   Zálohy databáze

   Jednou denně, při prvním otevření systému, vznikne kopie databáze
   v data/zalohy/; kopie starší než měsíc se mažou. Správce si zálohu
   stáhne tlačítkem v Nastavení — vznikne čerstvá a rovnou se pošle.
   Adresář data/ web nevydá (.htaccess), zálohy tedy jdou jen přes
   přihlášení nebo přes FTP.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const ZALOHY_DNU = 31;

function zalohy_adresar(): string {
  return APLIKACE_CESTA . "/data/zalohy";
}

/* Cesta k souboru SQLite — stejně, jak ji skládá připojení. */
function sqlite_soubor(array $config): string {
  $cesta = (string)($config["soubor"] ?? "data/idispecink.sqlite");
  if ($cesta === "" || $cesta[0] !== "/") $cesta = APLIKACE_CESTA . "/" . $cesta;
  return $cesta;
}

/* Vytvoří zálohu a vrátí cestu k ní, nebo null s popisem chyby. */
function zaloha_vytvor(?string &$chyba = null): ?string {
  global $pdo, $config, $SCHEMA;
  $chyba = null;
  $adresar = zalohy_adresar();
  if (!is_dir($adresar) && !@mkdir($adresar, 0770, true)) { $chyba = "Adresář " . $adresar . " nejde založit."; return null; }
  if (!is_writable($adresar)) { $chyba = "Do adresáře " . $adresar . " server nesmí zapisovat."; return null; }
  $ovladac = (string)($config["ovladac"] ?? "sqlite");

  if ($ovladac === "mysql") {
    /* Prostý výpis: CREATE TABLE a INSERT pro každou tabulku ze schématu. */
    $cesta = $adresar . "/idispecink-" . date("Y-m-d") . ".sql";
    $ven = "-- iDispecink provozni system, zaloha " . date("Y-m-d H:i:s") . "\nSET NAMES utf8mb4;\n";
    foreach (array_keys($SCHEMA) as $tabulka) {
      $vytvoreni = radek("SHOW CREATE TABLE `" . $tabulka . "`");
      $ven .= "\nDROP TABLE IF EXISTS `" . $tabulka . "`;\n" . (string)($vytvoreni["Create Table"] ?? "") . ";\n";
      foreach (radky("SELECT * FROM `" . $tabulka . "`") as $r) {
        $hodnoty = [];
        foreach ($r as $h) $hodnoty[] = $h === null ? "NULL" : $pdo->quote((string)$h);
        $ven .= "INSERT INTO `" . $tabulka . "` (`" . implode("`, `", array_keys($r)) . "`) VALUES (" . implode(", ", $hodnoty) . ");\n";
      }
    }
    if (file_put_contents($cesta, $ven) === false) { $chyba = "Zálohu se nepodařilo zapsat."; return null; }
  } else {
    $cesta = $adresar . "/idispecink-" . date("Y-m-d") . ".sqlite";
    @unlink($cesta);
    try {
      /* VACUUM INTO dá konzistentní kopii i za běhu; starší SQLite ho nezná. */
      $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $cesta) . "'");
    } catch (PDOException $e) {
      if (!@copy(sqlite_soubor($config), $cesta)) { $chyba = "Databázi se nepodařilo zkopírovat."; return null; }
    }
    if (!is_file($cesta)) { $chyba = "Záloha nevznikla."; return null; }
  }
  @chmod($cesta, 0660);
  zalohy_promaz();
  return $cesta;
}

/* Smaže zálohy starší než měsíc — podle data v názvu souboru. */
function zalohy_promaz(): void {
  $hranice = date("Y-m-d", strtotime("-" . ZALOHY_DNU . " days"));
  foreach (zalohy_seznam() as $z) {
    if ($z["datum"] < $hranice) @unlink($z["cesta"]);
  }
}

/* Existující zálohy, nejnovější první. */
function zalohy_seznam(): array {
  $ven = [];
  foreach (glob(zalohy_adresar() . "/idispecink-*.{sqlite,sql}", GLOB_BRACE) ?: [] as $cesta) {
    if (!preg_match('/idispecink-(\d{4}-\d{2}-\d{2})\.(sqlite|sql)$/', $cesta, $m)) continue;
    $ven[] = ["cesta" => $cesta, "nazev" => basename($cesta), "datum" => $m[1], "velikost" => (int)filesize($cesta)];
  }
  usort($ven, fn($a, $b) => strcmp($b["datum"], $a["datum"]));
  return $ven;
}

/* Denní záloha při prvním otevření dne. Datum se zapíše napřed, ať
   souběžné požadavky nezálohují dvakrát; chyba jde do logu serveru. */
function zaloha_denni(): void {
  if (nastaveni("zaloha_naposledy") === date("Y-m-d")) return;
  uloz_nastaveni("zaloha_naposledy", date("Y-m-d"));
  if (zaloha_vytvor($chyba) === null) error_log("iDispecink: denní záloha selhala — " . $chyba);
}

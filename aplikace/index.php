<?php
/* =========================================================================
   iDispečink.cz — provozní systém (TMS)

   Jediný vstupní bod. Všechny stránky se otevírají jako index.php?s=…,
   takže hosting nemusí umět přepisování adres (mod_rewrite) a soubory
   se dají nahrát běžným FTP klientem tak, jak jsou.

   Postavené na PHP a PDO, bez frameworku a bez závislostí — stejně
   jako zbytek webu, který se nahrává tak, jak je.
   ========================================================================= */

declare(strict_types=1);
mb_internal_encoding("UTF-8");
date_default_timezone_set("Europe/Prague");

define("APLIKACE", true);
define("APLIKACE_CESTA", __DIR__);

require __DIR__ . "/zdroj/pomocnici.php";

/* --- Konfigurace -------------------------------------------------------- */

$vychozi = require __DIR__ . "/config.vzor.php";
$config  = is_file(__DIR__ . "/config.php")
  ? array_merge($vychozi, (array)require __DIR__ . "/config.php")
  : $vychozi;

if (!empty($config["vyzadovat_https"])) {
  $https = (($_SERVER["HTTPS"] ?? "") !== "" && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
  if (!$https) {
    presmeruj("https://" . ($_SERVER["HTTP_HOST"] ?? "idispecink.cz") . ($_SERVER["REQUEST_URI"] ?? "/"));
  }
  header("Strict-Transport-Security: max-age=31536000");
}

/* --- Databáze ----------------------------------------------------------- */

require __DIR__ . "/zdroj/databaze.php";

try {
  $pdo = pripoj_databazi($config);
  priprav_schema($pdo, $config["ovladac"] ?? "sqlite");
} catch (PDOException $e) {
  /* Podrobnost chyby patří do logu serveru, ne na obrazovku. */
  error_log("iDispecink TMS: " . $e->getMessage());
  selhani("Databáze neodpovídá",
    "Aplikace se nedokázala připojit k databázi. Zkontrolujte config.php a práva adresáře data/. Podrobnost je v chybovém logu serveru.");
}

require __DIR__ . "/zdroj/ciselniky.php";
require __DIR__ . "/zdroj/autentizace.php";

zahaj_sezeni($config);

require __DIR__ . "/zdroj/sablona.php";

/* --- Směrování ---------------------------------------------------------- */

/* Stránka => vyžaduje přihlášení */
$STRANKY = [
  "instalace"  => false,
  "prihlaseni" => false,
  "odhlaseni"  => true,
  "prehled"    => true,
  "prepravy"   => true,
  "preprava"   => true,
  "dispecink"  => true,
  "firmy"      => true,
  "firma"      => true,
  "objednavka" => true,
  "fakturace"  => true,
  "nastaveni"  => true,
  "import"     => true,
  "export"     => true,
];

/* Stránka se bere výhradně z adresy — o tom, co se spustí, nemá
   rozhodovat tělo požadavku. */
$stranka = isset($_GET["s"]) && is_string($_GET["s"]) ? trim($_GET["s"]) : "prehled";
if (!isset($STRANKY[$stranka])) $stranka = "prehled";

/* Dokud není žádný uživatel, pustí se jen instalace. */
$bez_uzivatelu = (int)hodnota("SELECT COUNT(*) FROM uzivatele WHERE aktivni = 1") === 0;
if ($bez_uzivatelu && $stranka !== "instalace") {
  presmeruj(odkaz("instalace"));
}
if (!$bez_uzivatelu && $stranka === "instalace") {
  presmeruj(odkaz("prihlaseni"));
}

if ($STRANKY[$stranka]) {
  vyzaduj_prihlaseni();
}

/* Každý zápis musí nést jednorázový token. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $stranka !== "instalace" && $stranka !== "prihlaseni") {
  over_token();
}

require __DIR__ . "/zdroj/stranky/" . $stranka . ".php";

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
require __DIR__ . "/zdroj/trasa.php";
require __DIR__ . "/zdroj/prilohy.php";
require __DIR__ . "/zdroj/odkazy.php";
require __DIR__ . "/zdroj/posta.php";
require __DIR__ . "/zdroj/faktury.php";
require __DIR__ . "/zdroj/fakturoid.php";
require __DIR__ . "/zdroj/dispecink.php";
require __DIR__ . "/zdroj/ceniky.php";
require __DIR__ . "/zdroj/dopravci.php";
require __DIR__ . "/zdroj/nabidky.php";
require __DIR__ . "/zdroj/hlidani.php";
require __DIR__ . "/zdroj/totp.php";
require __DIR__ . "/zdroj/zalohy.php";

/* Přepravy z doby před body trasy dostanou dva body z polí. Po prvním
   průchodu se už nic nenajde a volání je zadarmo. */
preved_prepravy_na_body();

/* --- Směrování ---------------------------------------------------------- */

/* Stránka => vyžaduje přihlášení */
$STRANKY = [
  "instalace"  => false,
  "prihlaseni" => false,
  "verejne"    => false,     /* odkazy bez hesla pro zákazníka, dopravce a řidiče */
  "hlidani"    => false,     /* spouštěč ranního souhrnu, místo přihlášení klíč z config.php */
  "odhlaseni"  => true,
  "prehled"    => true,
  "prepravy"   => true,
  "preprava"   => true,
  "nabidky"    => true,      /* jen s právem na ceny — stránka si to hlídá sama */
  "nabidka"    => true,
  "dispecink"  => true,
  "vozy"       => true,      /* plán vozů klientů externího dispečinku */
  "firmy"      => true,
  "firma"      => true,
  "objednavka" => true,
  "fakturace"  => true,
  "nastaveni"  => true,
  "ucet"       => true,      /* vlastní heslo a druhý faktor */
  "zmeny"      => true,      /* protokol změn, jen správce */
  "zaloha"     => true,      /* stažení zálohy, jen správce */
  "import"     => true,
  "mista"      => true,
  "misto"      => true,
  "linky"      => true,
  "priloha"    => true,
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
  /* Ranní souhrn bez cronu: první otevření dne ho pošle samo. */
  if ($_SERVER["REQUEST_METHOD"] === "GET") { hlidani_denni_kontrola(); zaloha_denni(); }
}

/* Každý zápis musí nést jednorázový token. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $stranka !== "instalace" && $stranka !== "prihlaseni") {
  over_token();
}

require __DIR__ . "/zdroj/stranky/" . $stranka . ".php";

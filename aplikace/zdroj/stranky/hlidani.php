<?php
/* Spouštěč ranního souhrnu pro naplánovanou úlohu hostingu. Bez přihlášení,
   místo něj klíč z config.php v adrese:

     index.php?s=hlidani&klic=…

   Bez klíče v konfiguraci je stránka mrtvá. Odpovídá prostým textem, ať
   se v logu úlohy pozná, jak to dopadlo. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

header("Content-Type: text/plain; charset=utf-8");
header("Cache-Control: no-store");
header("X-Robots-Tag: noindex, nofollow");

$klic = trim((string)($config["hlidani_klic"] ?? ""));
$poslany = vstup("klic");
if ($klic === "" || strlen($klic) < 16 || $poslany === "" || !hash_equals($klic, $poslany)) {
  http_response_code(404);
  exit("Není k dispozici.");
}
if (!hlidani_zapnuto()) exit("Hlídání je v Nastavení vypnuté, souhrn se neposílá.");

$v = hlidani_odesli("naplánovaná úloha");
echo $v["chyba"] ? "CHYBA: " . $v["chyba"] : "Odesláno " . $v["poslano"] . " z " . $v["prijemcu"] . " příjemců.";
echo "\n";

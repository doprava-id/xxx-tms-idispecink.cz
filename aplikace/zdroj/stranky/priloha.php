<?php
/* Vydání přílohy. Soubory leží mimo dosah webu, ven je pouští jen tahle
   stránka po přihlášení — směrovač ho vyžaduje.

   Typ se bere z naší tabulky, ne z toho, co soubor tvrdí, a prohlížeč
   dostane zákaz hádat. Obrázky a PDF se ukážou rovnou, cokoliv jiného by
   se stahovalo — jenže jiného sem nic nepustíme. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$priloha = radek("SELECT * FROM prilohy WHERE id = ?", [vstup_cislo("id")]);
$cesta = $priloha ? priloha_cesta((string)$priloha["soubor"]) : "";

if (!$priloha || !is_file($cesta)) {
  vzkaz("chyba", "Příloha nenalezena.");
  presmeruj(odkaz("prepravy"));
}

$pripona = strtolower((string)pathinfo((string)$priloha["soubor"], PATHINFO_EXTENSION));
$typ = PRILOHY_TYPY[$pripona] ?? "application/octet-stream";
$inline = ($typ === "application/pdf" || strpos($typ, "image/") === 0);

/* Jméno souboru pro prohlížeč: bez uvozovek a zalomení, s náhradou pro
   starší prohlížeče, které neumí UTF-8 v hlavičce. */
$nazev = preg_replace('/[\r\n"]+/', "", (string)$priloha["nazev"]) ?: ("priloha." . $pripona);
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', "_", @iconv("UTF-8", "ASCII//TRANSLIT", $nazev) ?: "priloha." . $pripona);

header("Content-Type: " . $typ);
header("Content-Length: " . (string)filesize($cesta));
header("Content-Disposition: " . ($inline ? "inline" : "attachment")
  . "; filename=\"" . $ascii . "\"; filename*=UTF-8''" . rawurlencode($nazev));
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header("Cache-Control: private, no-store");
header("X-Robots-Tag: noindex");

readfile($cesta);
exit;

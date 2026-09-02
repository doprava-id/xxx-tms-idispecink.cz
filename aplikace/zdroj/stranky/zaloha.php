<?php
/* Stažení zálohy — vznikne čerstvá kopie databáze a rovnou se pošle.
   Jen správce; soubor nese osobní údaje, zacházejte s ním podle toho. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_spravce();

$cesta = zaloha_vytvor($chyba);
if ($cesta === null) {
  vzkaz("chyba", "Zálohu se nepodařilo vytvořit: " . $chyba);
  presmeruj(odkaz("nastaveni"));
}
zapis_udalost(null, "Stažena záloha databáze " . basename($cesta));

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . basename($cesta) . "\"");
header("Content-Length: " . (string)filesize($cesta));
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
readfile($cesta);
exit;

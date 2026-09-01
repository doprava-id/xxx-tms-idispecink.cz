<?php
/* Odhlášení. Jen přes POST s tokenem — odkaz v e-mailu nebo cizí obrázek
   by jinak dokázaly uživatele odhlásit bez jeho vědomí. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

if ($_SERVER["REQUEST_METHOD"] !== "POST") presmeruj(odkaz("prehled"));

odhlas();
vzkaz("ok", "Odhlášeno.");
presmeruj(odkaz("prihlaseni"));

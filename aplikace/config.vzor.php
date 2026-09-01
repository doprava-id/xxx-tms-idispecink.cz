<?php
/* =========================================================================
   iDispečink.cz — provozní systém: konfigurace

   VZOR. Zkopírujte jako config.php a vyplňte. Soubor config.php je
   v .gitignore a do repozitáře se nedostane — repozitář je veřejný
   a přihlašovací údaje v něm nemají co dělat.

       cp config.vzor.php config.php

   Bez config.php aplikace poběží s výchozími hodnotami níže: databáze
   SQLite v adresáři data/. Pro běžný provoz dvou dispečerů to stačí.
   ========================================================================= */

return [

  /* --- Databáze --------------------------------------------------------
     "sqlite" — soubor v adresáři data/, nic se nenastavuje. Doporučeno.
     "mysql"  — když hosting SQLite nemá nebo chcete zálohovat přes phpMyAdmin.
  */
  "ovladac" => "sqlite",

  /* Jen pro sqlite: cesta k souboru databáze. Relativní k adresáři aplikace. */
  "soubor" => "data/idispecink.sqlite",

  /* Jen pro mysql: */
  "server"  => "localhost",
  "databaze" => "",
  "uzivatel" => "",
  "heslo"    => "",

  /* --- Provoz ----------------------------------------------------------- */

  /* Vyžadovat HTTPS. Zapněte, jakmile má doména funkční certifikát —
     bez něj by se aplikace stala nedostupnou. Souvisí s přesměrováním
     zakomentovaným v kořenovém .htaccess. */
  "vyzadovat_https" => false,

  /* Odhlášení po nečinnosti (minuty). */
  "odhlasit_po" => 480,

  /* Kolik neúspěšných přihlášení povolit, než se adresa na čtvrt hodiny
     zablokuje. */
  "pokusu_prihlaseni" => 5,
];

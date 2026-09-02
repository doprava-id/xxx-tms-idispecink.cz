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

  /* --- Hlídání — ranní souhrn e-mailem ----------------------------------
     Naplánovaná úloha hostingu (cron) volá jednou ráno adresu

         https://idispecink.cz/aplikace/index.php?s=hlidani&klic=KLÍČ

     Klíč si vymyslete dlouhý a náhodný (aspoň 16 znaků); bez něj je
     adresa mrtvá. Bez cronu se souhrn pošle při prvním otevření systému
     toho dne. */
  "hlidani_klic" => "",

  /* --- Fakturoid --------------------------------------------------------
     Čtení úhrad vydaných faktur a založení faktury z podkladu. Přístup
     vydá Fakturoid v Nastavení → Uživatelský účet → API (OAuth 2.0,
     client credentials). Bez vyplnění modul spí.

     Tohle jsou přístupy k účetnictví. Patří JEN sem, do config.php,
     který je v .gitignore. Repozitář je veřejný. */
  "fakturoid_slug"          => "",
  "fakturoid_client_id"     => "",
  "fakturoid_client_secret" => "",
  "fakturoid_email"         => "",   /* kontaktní e-mail do hlavičky User-Agent */
];

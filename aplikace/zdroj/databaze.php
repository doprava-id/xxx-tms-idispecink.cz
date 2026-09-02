<?php
/* =========================================================================
   Databáze — připojení a schéma

   Schéma je popsané polem $SCHEMA, ne hotovým SQL. Typy sloupců se
   překládají podle ovladače, takže stejné schéma sedne na SQLite
   i na MySQL a nemusí se udržovat dvakrát.

   Tabulky se zakládají samy při prvním spuštění a při každém načtení
   se doplní chybějící sloupce (jednoduchá migrace vpřed). Data se
   nikdy nemažou.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Typy: id, cele, ano_ne, text, dlouhy_text, castka, datum, cas, cas_zapisu */
$SCHEMA = [

  "uzivatele" => [
    "id"                 => "id",
    "jmeno"              => "text",
    "email"              => "text",
    "heslo"              => "text",
    "role"               => "text",        /* spravce | dispecer */
    /* Právo vidět cenu zákazníka a marži. Cenu dopravce potřebuje
       ke své práci každý dispečer, obchodní stranu ne. */
    "vidi_ceny"          => "ano_ne",
    "aktivni"            => "ano_ne",
    "vytvoreno"          => "cas_zapisu",
    "posledni_prihlaseni" => "cas_zapisu",
  ],

  "firmy" => [
    "id"              => "id",
    "typ"             => "text",           /* zakaznik | dopravce | oboji */
    "nazev"           => "text",
    "ico"             => "text",
    "dic"             => "text",
    "ulice"           => "text",
    "mesto"           => "text",
    "psc"             => "text",
    "stat"            => "text",
    "kontakt_jmeno"   => "text",
    "kontakt_telefon" => "text",
    "kontakt_email"   => "text",
    "splatnost"       => "cele",
    "poznamka"        => "dlouhy_text",
    /* Prověření dopravce — rozsah podle interního postupu. */
    "prov_registry"   => "ano_ne",
    "prov_opravneni"  => "ano_ne",
    "prov_pojisteni"  => "ano_ne",
    "prov_doklady"    => "ano_ne",
    "prov_reference"  => "ano_ne",
    "prov_datum"      => "datum",
    "prov_poznamka"   => "dlouhy_text",
    /* Externí dispečink — klient, jehož vozy řídíme jako službu. Způsob
       účtování a sazba jsou s každým klientem jinak; dokud chybí, podklad
       odměnu nespočítá a řekne to. Sazby dodá zadavatel, nedomýšlejí se. */
    "dispecink"          => "ano_ne",
    "dispecink_uctovani" => "text",        /* pausal_vuz | procento | za_jizdu */
    "dispecink_sazba"    => "castka",      /* Kč; u procenta číslo v % */
    "dispecink_poznamka" => "dlouhy_text",
    /* Doklady dopravce s platností do. Systém na konec upozorní, ale
       objednávku pustí — rozhodnutí je na dispečerovi. */
    "pojisteni_do"       => "datum",
    "pojisteni_poznamka" => "text",        /* pojišťovna, číslo smlouvy, limit */
    "opravneni_do"       => "datum",
    "smlouva_do"         => "datum",
    "smlouva_poznamka"   => "text",
    "aktivni"         => "ano_ne",
    "vytvoreno"       => "cas_zapisu",
    "upraveno"        => "cas_zapisu",
  ],

  "vozidla" => [
    "id"       => "id",
    "firma_id" => "cele",
    "spz"      => "text",
    "typ"      => "text",
    "poznamka" => "text",
    "aktivni"  => "ano_ne",
  ],

  "ridici" => [
    "id"       => "id",
    "firma_id" => "cele",
    "jmeno"    => "text",
    "telefon"  => "text",
    "poznamka" => "text",
    "aktivni"  => "ano_ne",
  ],

  "prepravy" => [
    "id"    => "id",
    "cislo" => "text",
    "stav"  => "text",

    "zakaznik_id"   => "cele",
    "ref_zakaznika" => "text",

    "dopravce_id" => "cele",
    "vozidlo_id"  => "cele",
    "ridic_id"    => "cele",
    "spz"         => "text",       /* opis pro objednávku, nezávislý na číselníku */
    "ridic_jmeno" => "text",
    "ridic_telefon" => "text",

    /* Jízda pod externím dispečinkem: vůz klienta, který řídíme my. Klientem
       je dopravce jízdy; NULL = běžná spedice. Odesílateli fakturuje klient
       sám, proto se tyhle jízdy nepočítají do tržby ani marže (JEN_SPEDICE). */
    "dispecink_klient_id" => "cele",

    /* ODVOZENÝ SOUHRN TRASY — první nakládka a poslední vykládka.
       Zdrojem pravdy jsou body v tabulce `body`; tahle pole přepočítává
       prepocitej_trasu() při každé změně bodů. Seznamy, tabule a podklady
       je čtou, protože je to rychlé, ale nikdo je nesmí zapisovat přímo. */
    "nakladka_misto"  => "text",
    "nakladka_adresa" => "text",
    "nakladka_datum"  => "datum",
    "nakladka_od"     => "cas",
    "nakladka_do"     => "cas",

    "vykladka_misto"  => "text",
    "vykladka_adresa" => "text",
    "vykladka_datum"  => "datum",
    "vykladka_od"     => "cas",
    "vykladka_do"     => "cas",

    /* Stálé linky: šablona se v seznamech neukazuje, jen se z ní generuje. */
    "sablona"          => "ano_ne",
    "linka_nazev"      => "text",
    "linka_dny"        => "text",     /* „1,3,5" = pondělí, středa, pátek */
    "zdroj_sablony_id" => "cele",     /* z které šablony přeprava vznikla */
    "nabidka_id"       => "cele",     /* z které nabídky přeprava vznikla */

    "zbozi"        => "text",
    "hmotnost"     => "cele",      /* kg */
    "palet"        => "cele",
    "ldm"          => "castka",
    "typ_vozidla"  => "text",
    "pozadavky"    => "text",
    "km"           => "cele",      /* vzdálenost; zatím ručně, mapová služba chybí */

    "cena_zakaznik" => "castka",
    "cena_dopravce" => "castka",

    "objednavka_datum" => "cas_zapisu",
    "objednavka_odeslana" => "cas_zapisu",   /* kdy odešla e-mailem */
    "potvrzeno_kdy"    => "cas_zapisu",      /* dopravce potvrdil přes odkaz */
    "hlaseni"          => "text",            /* poslední zpráva od dopravce (zpoždění) */
    "hlaseni_kdy"      => "cas_zapisu",
    "doklady"          => "text",   /* ceka | prijato | chybi */
    "doklady_poznamka" => "text",

    "faktura_vydana"   => "text",
    "faktura_prijata"  => "text",

    "poznamka"          => "dlouhy_text",
    "poznamka_dopravci" => "dlouhy_text",

    "vytvoreno" => "cas_zapisu",
    "upraveno"  => "cas_zapisu",
    "vytvoril"  => "cele",
  ],

  /* Body trasy — zdroj pravdy o tom, kudy jízda vede. Zboží, hmotnost
     a palety jsou u bodu nepovinně: u celovozu zůstanou prázdné a platí
     souhrn u přepravy, u rozvozu se vyplní po bodech. */
  "body" => [
    "id"          => "id",
    "preprava_id" => "cele",
    "poradi"      => "cele",
    "druh"        => "text",         /* nakladka | vykladka */
    "misto_id"    => "cele",         /* odkaz do adresáře; text níže je opis */
    "misto"       => "text",         /* obec */
    "adresa"      => "text",
    "datum"       => "datum",
    "od"          => "cas",
    "do"          => "cas",
    "kontakt"     => "text",
    "poznamka"    => "text",
    "zbozi"       => "text",
    "hmotnost"    => "cele",
    "palet"       => "cele",
    "splneno"     => "ano_ne",
    "splneno_kdy" => "cas_zapisu",
  ],

  /* Adresář míst — společný číselník, firma nepovinně. */
  "mista" => [
    "id"              => "id",
    "nazev"           => "text",
    "firma_id"        => "cele",
    "ulice"           => "text",
    "mesto"           => "text",
    "psc"             => "text",
    "kontakt_jmeno"   => "text",
    "kontakt_telefon" => "text",
    "oteviraci_doba"  => "text",
    "poznamka"        => "dlouhy_text",
    "aktivni"         => "ano_ne",
    "vytvoreno"       => "cas_zapisu",
  ],

  /* Ceníky zákazníků — tři podoby pravidla. Přednost: trasa → pásmo → km,
     pak historie trasy. Návrh ceny se nikdy nezapisuje sám. */
  "ceniky" => [
    "id"             => "id",
    "firma_id"       => "cele",
    "druh"           => "text",       /* trasa | pasmo | km */
    "nakladka_misto" => "text",
    "vykladka_misto" => "text",
    "km_od"          => "cele",
    "km_do"          => "cele",       /* NULL = bez horní hranice */
    "cena"           => "castka",     /* Kč za trasu či pásmo; u km Kč/km */
    "typ_vozidla"    => "text",       /* prázdné = jakékoli */
    "poznamka"       => "text",
    "aktivni"        => "ano_ne",
    "vytvoreno"      => "cas_zapisu",
  ],

  /* Nabídky — stupeň před zakázkou. Z přijaté vznikne přeprava
     (preprava_id), u neúspěšné se zapíše důvod. Platnost se nesleduje. */
  "nabidky" => [
    "id"            => "id",
    "cislo"         => "text",
    "stav"          => "text",        /* otevrena | prijata | neprosla */
    "duvod"         => "text",        /* drahe | pozde | bez_vozu | zrusil | jiny */
    "duvod_poznamka" => "text",
    "zakaznik_id"   => "cele",
    "kontakt_jmeno" => "text",
    "kontakt_email" => "text",
    "ref_zakaznika" => "text",
    "nakladka_misto"  => "text",
    "nakladka_adresa" => "text",
    "nakladka_datum"  => "datum",
    "nakladka_od"     => "cas",
    "nakladka_do"     => "cas",
    "vykladka_misto"  => "text",
    "vykladka_adresa" => "text",
    "vykladka_datum"  => "datum",
    "vykladka_od"     => "cas",
    "vykladka_do"     => "cas",
    "zbozi"         => "text",
    "hmotnost"      => "cele",
    "palet"         => "cele",
    "ldm"           => "castka",
    "km"            => "cele",
    "typ_vozidla"   => "text",
    "pozadavky"     => "text",
    "cena"          => "castka",      /* nabídnutá cena zákazníkovi bez DPH */
    "cena_dopravce" => "castka",      /* odhad nákladu, jen pro marži */
    "cena_podle"    => "text",        /* podle čeho cena vznikla */
    "text_pro_zakaznika" => "dlouhy_text",
    "poznamka"      => "dlouhy_text",
    "odeslana"      => "cas_zapisu",
    "rozhodnuto"    => "cas_zapisu",
    "preprava_id"   => "cele",
    "vytvoreno"     => "cas_zapisu",
    "upraveno"      => "cas_zapisu",
    "vytvoril"      => "cele",
  ],

  /* Faktury — vydané zákazníkům i přijaté od dopravců. Na přepravu se
     vážou číslem (faktura_vydana / faktura_prijata), protože jedna faktura
     kryje víc přeprav. Vydané se dají tahat z Fakturoidu, přijaté se
     zapisují ručně. */
  "faktury" => [
    "id"           => "id",
    "druh"         => "text",         /* vydana | prijata */
    "cislo"        => "text",
    "firma_id"     => "cele",
    "castka"       => "castka",       /* bez DPH */
    "castka_s_dph" => "castka",
    "vystaveno"    => "datum",
    "splatnost"    => "datum",
    "uhrazeno"     => "datum",        /* NULL = nezaplaceno */
    "stav"         => "text",         /* z Fakturoidu: open | paid | overdue | cancelled | uncollectible */
    "fakturoid_id" => "cele",
    "poznamka"     => "text",
    "vytvoreno"    => "cas_zapisu",
    "upraveno"     => "cas_zapisu",
  ],

  /* Veřejné odkazy bez hesla. Kód je 160 bitů náhody — hádat ho nejde.
     Platnost se nepamatuje, počítá se z data vykládky (měsíc po ní). */
  "odkazy" => [
    "id"          => "id",
    "preprava_id" => "cele",
    "druh"        => "text",         /* zakaznik | dopravce | ridic */
    "kod"         => "text",
    "vytvoreno"   => "cas_zapisu",
    "vytvoril"    => "cele",
    "naposledy"   => "cas_zapisu",
    "otevreni"    => "cele",
    "zruseno"     => "ano_ne",
  ],

  "prilohy" => [
    "id"          => "id",
    "preprava_id" => "cele",
    "nazev"       => "text",       /* jak se soubor jmenoval u odesílatele */
    "soubor"      => "text",       /* jak se jmenuje v data/prilohy */
    "typ"         => "text",
    "velikost"    => "cele",
    "uzivatel_id" => "cele",
    "kdy"         => "cas_zapisu",
  ],

  "udalosti" => [
    "id"          => "id",
    "preprava_id" => "cele",
    "uzivatel_id" => "cele",
    "kdy"         => "cas_zapisu",
    "text"        => "text",
  ],

  "nastaveni" => [
    "klic"    => "text",
    "hodnota" => "dlouhy_text",
  ],

  "pokusy" => [
    "id"      => "id",
    "adresa"  => "text",
    "kdy"     => "cas_zapisu",
  ],
];

/* Indexy — jméno => [tabulka, sloupce] */
$INDEXY = [
  "idx_uzivatele_email"    => ["uzivatele", "email"],
  "idx_prepravy_cislo"     => ["prepravy", "cislo"],
  "idx_prepravy_nakladka"  => ["prepravy", "nakladka_datum"],
  "idx_prepravy_stav"      => ["prepravy", "stav"],
  "idx_prepravy_dopravce"  => ["prepravy", "dopravce_id"],
  "idx_prepravy_zakaznik"  => ["prepravy", "zakaznik_id"],
  "idx_prepravy_vozidlo"   => ["prepravy", "vozidlo_id"],
  "idx_prepravy_dispecink" => ["prepravy", "dispecink_klient_id"],
  "idx_ceniky_firma"       => ["ceniky", "firma_id"],
  "idx_nabidky_cislo"      => ["nabidky", "cislo"],
  "idx_nabidky_zakaznik"   => ["nabidky", "zakaznik_id"],
  "idx_nabidky_stav"       => ["nabidky", "stav"],
  "idx_udalosti_preprava"  => ["udalosti", "preprava_id"],
  "idx_prilohy_preprava"   => ["prilohy", "preprava_id"],
  "idx_body_preprava"      => ["body", "preprava_id"],
  "idx_odkazy_kod"         => ["odkazy", "kod"],
  "idx_faktury_cislo"      => ["faktury", "cislo"],
  "idx_faktury_firma"      => ["faktury", "firma_id"],
  "idx_odkazy_preprava"    => ["odkazy", "preprava_id"],
  "idx_mista_nazev"        => ["mista", "nazev"],
  "idx_prepravy_sablona"   => ["prepravy", "sablona"],
  "idx_vozidla_firma"      => ["vozidla", "firma_id"],
  "idx_ridici_firma"       => ["ridici", "firma_id"],
  "idx_nastaveni_klic"     => ["nastaveni", "klic"],
  "idx_pokusy_kdy"         => ["pokusy", "kdy"],
];

function sql_typ(string $typ, string $ovladac): string {
  $mysql = [
    "id"          => "INT AUTO_INCREMENT PRIMARY KEY",
    "cele"        => "INT NULL",
    "ano_ne"      => "TINYINT NOT NULL DEFAULT 0",
    "text"        => "VARCHAR(255) NULL",
    "dlouhy_text" => "TEXT NULL",
    "castka"      => "DECIMAL(12,2) NULL",
    "datum"       => "DATE NULL",
    "cas"         => "VARCHAR(5) NULL",
    "cas_zapisu"  => "DATETIME NULL",
  ];
  $sqlite = [
    "id"          => "INTEGER PRIMARY KEY AUTOINCREMENT",
    "cele"        => "INTEGER",
    "ano_ne"      => "INTEGER NOT NULL DEFAULT 0",
    "text"        => "TEXT",
    "dlouhy_text" => "TEXT",
    "castka"      => "REAL",
    "datum"       => "TEXT",
    "cas"         => "TEXT",
    "cas_zapisu"  => "TEXT",
  ];
  $mapa = $ovladac === "mysql" ? $mysql : $sqlite;
  return $mapa[$typ] ?? $mapa["text"];
}

function pripoj_databazi(array $config): PDO {
  $ovladac = $config["ovladac"] ?? "sqlite";

  if ($ovladac === "mysql") {
    if (!in_array("mysql", PDO::getAvailableDrivers(), true)) {
      selhani("Databáze není dostupná",
        "PHP na tomto serveru nemá rozšíření pdo_mysql. Zapněte ho v administraci hostingu, nebo v config.php přepněte ovladač na \"sqlite\".");
    }
    $dsn = "mysql:host=" . $config["server"] . ";dbname=" . $config["databaze"] . ";charset=utf8mb4";
    $pdo = new PDO($dsn, (string)$config["uzivatel"], (string)$config["heslo"]);
  } else {
    if (!in_array("sqlite", PDO::getAvailableDrivers(), true)) {
      selhani("Databáze není dostupná",
        "PHP na tomto serveru nemá rozšíření pdo_sqlite. Zapněte ho v administraci hostingu, nebo v config.php přepněte ovladač na \"mysql\" a vyplňte přístup k databázi.");
    }
    $cesta = $config["soubor"] ?? "data/idispecink.sqlite";
    if ($cesta[0] !== "/") $cesta = APLIKACE_CESTA . "/" . $cesta;
    $adresar = dirname($cesta);
    if (!is_dir($adresar)) @mkdir($adresar, 0770, true);
    if (!is_dir($adresar) || !is_writable($adresar)) {
      selhani("Databázi nelze založit",
        "Adresář " . $adresar . " neexistuje nebo do něj server nesmí zapisovat. Nastavte mu práva 770.");
    }
    $pdo = new PDO("sqlite:" . $cesta);
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA journal_mode = WAL");
  }

  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  return $pdo;
}

/* Založí chybějící tabulky, doplní chybějící sloupce a indexy. */
function priprav_schema(PDO $pdo, string $ovladac): void {
  global $SCHEMA, $INDEXY;

  foreach ($SCHEMA as $tabulka => $sloupce) {
    $stavajici = existujici_sloupce($pdo, $ovladac, $tabulka);

    if ($stavajici === null) {
      $casti = [];
      foreach ($sloupce as $nazev => $typ) {
        $casti[] = "`" . $nazev . "` " . sql_typ($typ, $ovladac);
      }
      $sql = "CREATE TABLE `" . $tabulka . "` (" . implode(", ", $casti) . ")";
      if ($ovladac === "mysql") $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
      $pdo->exec($sql);
      continue;
    }

    foreach ($sloupce as $nazev => $typ) {
      if ($typ === "id" || in_array($nazev, $stavajici, true)) continue;
      $pdo->exec("ALTER TABLE `" . $tabulka . "` ADD COLUMN `" . $nazev . "` " . sql_typ($typ, $ovladac));
    }
  }

  foreach ($INDEXY as $jmeno => $kam) {
    [$tabulka, $sloupec] = $kam;
    try {
      $pdo->exec("CREATE INDEX " . ($ovladac === "mysql" ? "" : "IF NOT EXISTS ")
        . "`" . $jmeno . "` ON `" . $tabulka . "` (`" . $sloupec . "`)");
    } catch (PDOException $e) {
      /* MySQL neumí IF NOT EXISTS u indexu — druhé založení prostě selže. */
    }
  }
}

/* Vrátí seznam sloupců tabulky, nebo null když tabulka neexistuje. */
function existujici_sloupce(PDO $pdo, string $ovladac, string $tabulka): ?array {
  try {
    $sql = $ovladac === "mysql"
      ? "SHOW COLUMNS FROM `" . $tabulka . "`"
      : "PRAGMA table_info(`" . $tabulka . "`)";
    $radky = $pdo->query($sql)->fetchAll();
  } catch (PDOException $e) {
    return null;
  }
  if (!$radky) return null;
  $sloupce = [];
  foreach ($radky as $r) {
    $sloupce[] = $r["Field"] ?? $r["name"];
  }
  return $sloupce;
}

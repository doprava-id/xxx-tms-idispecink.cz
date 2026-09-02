<?php
/* Detail přepravy — jediné místo, kde se zásilka zakládá a mění.

   Nová přeprava se zakládá s jednou nakládkou a jednou vykládkou, protože
   tak vypadá většina celovozových jízd. Z nich vzniknou dva body trasy;
   na detailu se pak dají přidávat další, přehazovat a odškrtávat.

   Vozidlo, řidič i místo se vybírají z číselníku, ale zároveň se opisují
   do textových polí. Objednávka pak drží to, co bylo v okamžiku vystavení
   dohodnuté, i když se karta později změní. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ceny = vidi_ceny();
$ceny_dopravce = vidi_ceny_dopravce();
$smi_dispecink = smi_dispecink();

$id = vstup("id");
$nova = ($id === "nova" || $id === "");
$preprava = null;

if (!$nova) {
  $preprava = radek("SELECT * FROM prepravy WHERE id = ?", [(int)$id]);
  if (!$preprava) {
    vzkaz("chyba", "Přeprava nenalezena.");
    presmeruj(odkaz("prepravy"));
  }
}
$zpet = function () use ($preprava) { return odkaz("preprava", ["id" => $preprava["id"]]); };

/* Nová jízda z plánu vozů přichází s předvyplněným dopravcem, vozem a dnem
   nakládky v adrese. */
$predvoleny_dopravce = $nova ? (vstup_cislo("dopravce") ?: 0) : 0;

/* Údaje bodu z formuláře — společné pro přidání i úpravu. */
function bod_z_formulare(): array {
  return [
    "druh"     => isset(DRUHY_BODU[vstup("druh")]) ? vstup("druh") : "nakladka",
    "misto_id" => vstup_cislo("misto_id") ?: null,
    "misto"    => vstup("misto"),
    "adresa"   => vstup("adresa"),
    "datum"    => vstup_datum("datum"),
    "od"       => vstup("od"),
    "do"       => vstup("do"),
    "kontakt"  => vstup("kontakt"),
    "poznamka" => vstup("bod_poznamka"),
    "zbozi"    => vstup("bod_zbozi"),
    "hmotnost" => vstup_cislo("bod_hmotnost"),
    "palet"    => vstup_cislo("bod_palet"),
  ];
}

/* --- Zápis -------------------------------------------------------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  /* Účetní do dispečinku nezasahuje: smí jen doklady, faktury a přílohy. */
  if (!$smi_dispecink && !in_array($akce, ["ulozit", "priloha_pridat", "priloha_smazat"], true)) {
    vzkaz("chyba", "Do dispečinku účetní nezasahuje — trasu, dopravce a odkazy mění dispečer.");
    presmeruj($preprava ? $zpet() : odkaz("prepravy"));
  }

  if ($akce === "ulozit" && !$smi_dispecink) {
    /* Účetní: jen doklady a čísla faktur, ostatní pole se nepřepíšou. */
    if ($nova) { vzkaz("chyba", "Přepravy zakládá dispečink."); presmeruj(odkaz("prepravy")); }
    $data = [
      "doklady"          => isset(DOKLADY[vstup("doklady")]) ? vstup("doklady") : "ceka",
      "doklady_poznamka" => vstup("doklady_poznamka"),
      "faktura_prijata"  => vstup("faktura_prijata"),
      "upraveno"         => date("Y-m-d H:i:s"),
      "upravil"          => (int)uzivatel()["id"],
    ];
    if ($ceny) $data["faktura_vydana"] = vstup("faktura_vydana");
    if ($data["doklady"] === "prijato" && $preprava["doklady"] !== "prijato") {
      $data["doklady_kdy"] = date("Y-m-d H:i:s");
      zapis_udalost((int)$preprava["id"], "Doklady přijaty");
    }
    uprav("prepravy", (int)$preprava["id"], $data);
    vzkaz("ok", "Doklady a faktury uloženy.");
    presmeruj($zpet());

  } elseif ($akce === "ulozit") {
    $dopravce_id = vstup_cislo("dopravce_id") ?: null;
    $vozidlo_id  = vstup_cislo("vozidlo_id") ?: null;
    $ridic_id    = vstup_cislo("ridic_id") ?: null;

    $spz     = vstup("spz");
    $r_jmeno = vstup("ridic_jmeno");
    $r_tel   = vstup("ridic_telefon");

    /* Vůz a řidič z karty musí patřit vybranému dopravci — po změně
       dopravce by jinak zůstal v jízdě cizí vůz a rozbil plán vozů. */
    if ($vozidlo_id && (int)hodnota("SELECT firma_id FROM vozidla WHERE id = ?", [$vozidlo_id]) !== (int)$dopravce_id) $vozidlo_id = null;
    if ($ridic_id && (int)hodnota("SELECT firma_id FROM ridici WHERE id = ?", [$ridic_id]) !== (int)$dopravce_id) $ridic_id = null;

    /* Externí dispečink: „podle karty dopravce" nechá rozhodnout příznak
       na firmě, ano/ne ho přebije. Klientem je vždy dopravce jízdy. */
    $dopravce_je_klient = je_klient_dispecinku($dopravce_id);
    $volba_dispecinku = vstup("dispecink");
    if ($volba_dispecinku === "1" && !$dopravce_je_klient) {
      vzkaz("pozor", $dopravce_id
        ? "Dopravce není klient dispečinku — zaškrtněte to nejdřív na jeho kartě. Jízda je uložená jako běžná spedice."
        : "Jízda pod dispečinkem potřebuje dopravce, který je klientem dispečinku. Uložená je jako běžná spedice.");
      $volba_dispecinku = "0";
    }
    $dispecink_klient_id = ($volba_dispecinku === "1" || ($volba_dispecinku === "" && $dopravce_je_klient)) ? $dopravce_id : null;

    if ($vozidlo_id && $spz === "") {
      $v = radek("SELECT spz FROM vozidla WHERE id = ?", [$vozidlo_id]);
      if ($v) $spz = (string)$v["spz"];
    }
    if ($ridic_id && $r_jmeno === "") {
      $r = radek("SELECT jmeno, telefon FROM ridici WHERE id = ?", [$ridic_id]);
      if ($r) {
        $r_jmeno = (string)$r["jmeno"];
        if ($r_tel === "") $r_tel = (string)$r["telefon"];
      }
    }

    $data = [
      "stav"          => isset(STAVY[vstup("stav")]) ? vstup("stav") : "nova",
      "zakaznik_id"   => vstup_cislo("zakaznik_id") ?: null,
      "ref_zakaznika" => vstup("ref_zakaznika"),

      "dopravce_id"   => $dopravce_id,
      "vozidlo_id"    => $vozidlo_id,
      "ridic_id"      => $ridic_id,
      "spz"           => $spz,
      "ridic_jmeno"   => $r_jmeno,
      "ridic_telefon" => $r_tel,
      "dispecink_klient_id" => $dispecink_klient_id,

      "zbozi"       => vstup("zbozi"),
      "hmotnost"    => vstup_cislo("hmotnost"),
      "palet"       => vstup_cislo("palet"),
      "ldm"         => vstup_castka("ldm"),
      "typ_vozidla" => isset(TYPY_VOZIDEL[vstup("typ_vozidla")]) ? vstup("typ_vozidla") : "plachta",
      "pozadavky"   => vstup("pozadavky"),
      "km"          => vstup_cislo("km"),

      "doklady"          => isset(DOKLADY[vstup("doklady")]) ? vstup("doklady") : "ceka",
      "doklady_poznamka" => vstup("doklady_poznamka"),
      "faktura_prijata"  => vstup("faktura_prijata"),

      "poznamka"          => vstup("poznamka"),
      "poznamka_dopravci" => vstup("poznamka_dopravci"),

      "upraveno" => date("Y-m-d H:i:s"),
    ];

    /* Obchodní stranu zapisuje jen ten, kdo na ni má právo — jinak by
       uložení formuláře cenu zákazníka smazalo. */
    if ($ceny) {
      $data["cena_zakaznik"]  = vstup_castka("cena_zakaznik");
      $data["faktura_vydana"] = vstup("faktura_vydana");
    }
    /* Cenu dopravce nevidí brigádník — a nesmí ji uložením smazat. */
    if ($ceny_dopravce) $data["cena_dopravce"] = vstup_castka("cena_dopravce");

    if ($nova) {
      $trasa = [
        "nakladka_misto"  => vstup("nakladka_misto"),
        "nakladka_adresa" => vstup("nakladka_adresa"),
        "nakladka_datum"  => vstup_datum("nakladka_datum"),
        "nakladka_od"     => vstup("nakladka_od"),
        "nakladka_do"     => vstup("nakladka_do"),
        "vykladka_misto"  => vstup("vykladka_misto"),
        "vykladka_adresa" => vstup("vykladka_adresa"),
        "vykladka_datum"  => vstup_datum("vykladka_datum"),
        "vykladka_od"     => vstup("vykladka_od"),
        "vykladka_do"     => vstup("vykladka_do"),
        "stav"            => $data["stav"],
      ];
      if ($trasa["nakladka_misto"] === "" && $trasa["vykladka_misto"] === "") {
        vzkaz("chyba", "Vyplňte aspoň místo nakládky nebo vykládky.");
      } else {
        $data["cislo"]     = dalsi_cislo();
        $data["vytvoreno"] = date("Y-m-d H:i:s");
        $data["vytvoril"]  = (int)uzivatel()["id"];
        $data["sablona"]   = 0;
        $data["vlastnik_id"] = vstup_cislo("vlastnik_id") ?: (int)uzivatel()["id"];
        $data["upravil"]   = (int)uzivatel()["id"];
        $novy = vloz("prepravy", $data);
        zaloz_body_z_poli($novy, $trasa);
        prepocitej_trasu($novy);
        zapis_udalost($novy, "Přeprava " . $data["cislo"] . " založena" . ($dispecink_klient_id ? " pod externím dispečinkem" : ""));
        vzkaz("ok", "Přeprava " . $data["cislo"] . " založena. Další body trasy přidáte níže.");
        presmeruj(odkaz("preprava", ["id" => $novy]));
      }
    } else {
      /* Dva lidé nad jednou zásilkou: kdo ukládá starší podobu, neprojde
         a musí si kartu načíst znovu. */
      $upraveno_pred = vstup("upraveno_pred");
      if ($upraveno_pred !== "" && $upraveno_pred !== (string)$preprava["upraveno"]) {
        $kdo = $preprava["upravil"] ? (string)hodnota("SELECT jmeno FROM uzivatele WHERE id = ?", [(int)$preprava["upravil"]]) : "";
        vzkaz("chyba", "Přepravu mezitím upravil " . ($kdo !== "" ? $kdo : "někdo jiný") . " (" . datum_cas($preprava["upraveno"]) . "). Vaše změny se neuložily — kartu načtěte znovu a zadejte je ještě jednou.");
        presmeruj($zpet());
      }
      $data["vlastnik_id"] = vstup_cislo("vlastnik_id") ?: null;
      $data["upravil"]     = (int)uzivatel()["id"];

      /* Stálá linka — jen u existující přepravy. */
      $data["sablona"]     = vstup_ano_ne("sablona");
      $data["linka_nazev"] = vstup("linka_nazev");
      $dny = [];
      for ($d = 1; $d <= 7; $d++) if (vstup("den_" . $d) !== "") $dny[] = $d;
      $data["linka_dny"] = implode(",", $dny);

      if ($preprava["stav"] !== $data["stav"]) {
        zapis_udalost((int)$preprava["id"],
          "Stav změněn: " . nazev_stavu($preprava["stav"]) . " → " . nazev_stavu($data["stav"]));
        srovnej_body_se_stavem((int)$preprava["id"], $data["stav"]);
      }
      /* Kdy doklady přišly — pro rychlost vracení ve vyhodnocení. */
      if ($data["doklady"] === "prijato" && $preprava["doklady"] !== "prijato") {
        $data["doklady_kdy"] = date("Y-m-d H:i:s");
        zapis_udalost((int)$preprava["id"], "Doklady přijaty");
      }
      if ((int)$preprava["dopravce_id"] !== (int)$dopravce_id) {
        $nazev = $dopravce_id ? (string)hodnota("SELECT nazev FROM firmy WHERE id = ?", [$dopravce_id]) : "nikdo";
        zapis_udalost((int)$preprava["id"], "Dopravce: " . $nazev);
      }
      if ((int)($preprava["dispecink_klient_id"] ?? 0) !== (int)$dispecink_klient_id) {
        zapis_udalost((int)$preprava["id"], $dispecink_klient_id ? "Jízda vedená pod externím dispečinkem" : "Jízda vyřazená z externího dispečinku");
      }
      uprav("prepravy", (int)$preprava["id"], $data);
      prepocitej_trasu((int)$preprava["id"]);
      vzkaz("ok", "Změny uloženy.");
      presmeruj($zpet());
    }

  /* --- Body trasy --- */

  } elseif ($akce === "bod_pridat" && $preprava) {
    $b = bod_z_formulare();
    if ($b["misto"] === "" && !$b["misto_id"]) {
      vzkaz("chyba", "Bod trasy potřebuje aspoň obec nebo místo z adresáře.");
    } else {
      pridej_bod((int)$preprava["id"], $b);
      zapis_udalost((int)$preprava["id"], "Přidán bod trasy: " . nazev_druhu($b["druh"]) . " " . ($b["misto"] ?: "z adresáře"));
      vzkaz("ok", "Bod trasy přidán.");
    }
    presmeruj($zpet());

  } elseif ($akce === "bod_ulozit" && $preprava) {
    $bod_id = vstup_cislo("bod_id");
    $bod = $bod_id ? radek("SELECT * FROM body WHERE id = ? AND preprava_id = ?", [$bod_id, (int)$preprava["id"]]) : null;
    if ($bod) {
      uprav_bod((int)$bod["id"], bod_z_formulare());
      vzkaz("ok", "Bod trasy upraven.");
    }
    presmeruj($zpet());

  } elseif (in_array($akce, ["bod_smazat", "bod_vys", "bod_niz", "bod_splnit", "bod_odsplnit"], true) && $preprava) {
    $bod_id = vstup_cislo("bod_id");
    $bod = $bod_id ? radek("SELECT * FROM body WHERE id = ? AND preprava_id = ?", [$bod_id, (int)$preprava["id"]]) : null;
    if ($bod) {
      if ($akce === "bod_smazat") {
        if ((int)hodnota("SELECT COUNT(*) FROM body WHERE preprava_id = ?", [(int)$preprava["id"]]) <= 1) {
          vzkaz("chyba", "Poslední bod trasy smazat nejde — jízda musí někam vést.");
        } else {
          smaz_bod((int)$bod["id"]);
          zapis_udalost((int)$preprava["id"], "Smazán bod trasy: " . nazev_druhu($bod["druh"]) . " " . $bod["misto"]);
          vzkaz("ok", "Bod trasy smazán.");
        }
      } elseif ($akce === "bod_vys") {
        posun_bod((int)$bod["id"], -1);
      } elseif ($akce === "bod_niz") {
        posun_bod((int)$bod["id"], 1);
      } elseif ($akce === "bod_splnit") {
        splnit_bod((int)$bod["id"], true);
        zapis_udalost((int)$preprava["id"], "Splněno: " . nazev_druhu($bod["druh"]) . " " . $bod["misto"]);
      } else {
        splnit_bod((int)$bod["id"], false);
      }
    }
    presmeruj($zpet());

  /* --- Přílohy --- */

  } elseif ($akce === "priloha_pridat" && $preprava) {
    $id_prilohy = priloha_uloz((array)($_FILES["soubor"] ?? []), (int)$preprava["id"], $chyba_prilohy);
    if ($id_prilohy) {
      zapis_udalost((int)$preprava["id"], "Nahrána příloha " . (string)($_FILES["soubor"]["name"] ?? ""));
      vzkaz("ok", "Příloha nahrána.");
    } else {
      vzkaz("chyba", (string)$chyba_prilohy);
    }
    presmeruj($zpet());

  } elseif ($akce === "priloha_smazat" && $preprava) {
    $pr = radek("SELECT * FROM prilohy WHERE id = ? AND preprava_id = ?", [vstup_cislo("priloha_id"), (int)$preprava["id"]]);
    if ($pr) {
      priloha_smaz($pr);
      zapis_udalost((int)$preprava["id"], "Smazána příloha " . $pr["nazev"]);
      vzkaz("ok", "Příloha smazána.");
    }
    presmeruj($zpet());

  /* --- Odkazy ven --- */

  } elseif (in_array($akce, ["odkaz_vytvorit", "odkaz_zrusit"], true) && $preprava) {
    $druh_odkazu = vstup("druh");
    if (!isset(DRUHY_ODKAZU[$druh_odkazu])) {
      vzkaz("chyba", "Neznámý druh odkazu.");
    } elseif ($akce === "odkaz_vytvorit") {
      if ($druh_odkazu !== "zakaznik" && empty($preprava["dopravce_id"])) {
        vzkaz("chyba", "Nejdřív přiřaďte dopravce.");
      } else {
        odkaz_verejny_zajisti((int)$preprava["id"], $druh_odkazu);
        zapis_udalost((int)$preprava["id"], "Vytvořen odkaz pro " . mb_strtolower(DRUHY_ODKAZU[$druh_odkazu]));
        vzkaz("ok", "Odkaz vytvořen. Zkopírujte ho a pošlete.");
      }
    } else {
      odkaz_verejny_zrus((int)$preprava["id"], $druh_odkazu);
      zapis_udalost((int)$preprava["id"], "Zrušen odkaz pro " . mb_strtolower(DRUHY_ODKAZU[$druh_odkazu]));
      vzkaz("ok", "Odkaz zrušen, přestal platit.");
    }
    presmeruj($zpet());

  /* --- Celá přeprava --- */

  } elseif ($akce === "zrusit" && $preprava) {
    uprav("prepravy", (int)$preprava["id"], ["stav" => "zruseno", "upraveno" => date("Y-m-d H:i:s")]);
    zapis_udalost((int)$preprava["id"], "Přeprava zrušena");
    vzkaz("pozor", "Přeprava zrušena.");
    presmeruj($zpet());

  } elseif ($akce === "kopie" && $preprava) {
    $kopie = $preprava;
    unset($kopie["id"]);
    $kopie["cislo"]     = dalsi_cislo();
    $kopie["stav"]      = "nova";
    $kopie["doklady"]   = "ceka";
    $kopie["sablona"]   = 0;
    $kopie["linka_nazev"] = "";
    $kopie["linka_dny"]   = "";
    $kopie["zdroj_sablony_id"] = null;
    $kopie["faktura_vydana"]   = "";
    $kopie["faktura_prijata"]  = "";
    $kopie["objednavka_datum"] = null;
    $kopie["vytvoreno"] = date("Y-m-d H:i:s");
    $kopie["upraveno"]  = date("Y-m-d H:i:s");
    $kopie["vytvoril"]  = (int)uzivatel()["id"];
    $novy = vloz("prepravy", $kopie);
    zkopiruj_body((int)$preprava["id"], $novy);
    zapis_udalost($novy, "Založeno jako kopie přepravy " . $preprava["cislo"]);
    vzkaz("ok", "Vytvořena kopie " . $kopie["cislo"] . ". Zkontrolujte termíny u bodů trasy.");
    presmeruj(odkaz("preprava", ["id" => $novy]));

  } elseif ($akce === "smazat" && $preprava) {
    if (!je_spravce()) {
      vzkaz("chyba", "Mazat přepravy může jen správce.");
      presmeruj($zpet());
    }
    prilohy_smaz_u_prepravy((int)$preprava["id"]);
    dotaz("DELETE FROM body WHERE preprava_id = ?", [(int)$preprava["id"]]);
    dotaz("DELETE FROM udalosti WHERE preprava_id = ?", [(int)$preprava["id"]]);
    dotaz("DELETE FROM prepravy WHERE id = ?", [(int)$preprava["id"]]);
    vzkaz("ok", "Přeprava " . $preprava["cislo"] . " smazána.");
    presmeruj(odkaz("prepravy"));
  }
}

/* --- Výpis -------------------------------------------------------------- */

$h = function (string $klic, string $vychozi = "") use ($preprava) {
  $hodnota = $preprava[$klic] ?? null;
  return ($hodnota === null || $hodnota === "") ? $vychozi : (string)$hodnota;
};

$zakaznici = radky("SELECT id, nazev FROM firmy WHERE typ IN ('zakaznik','oboji') AND (aktivni = 1 OR id = ?) ORDER BY LOWER(nazev)",
  [(int)($preprava["zakaznik_id"] ?? 0)]);
$dopravci  = radky("SELECT id, nazev FROM firmy WHERE typ IN ('dopravce','oboji') AND (aktivni = 1 OR id = ?) ORDER BY LOWER(nazev)",
  [(int)($preprava["dopravce_id"] ?? 0)]);
$mista     = radky("SELECT id, nazev, mesto FROM mista WHERE aktivni = 1 ORDER BY LOWER(nazev)");
$uzivatele = radky("SELECT id, jmeno FROM uzivatele WHERE aktivni = 1 OR id = ? ORDER BY LOWER(jmeno)", [(int)($preprava["vlastnik_id"] ?? 0)]);
$vlastnik_jmeno = !empty($preprava["vlastnik_id"]) ? (string)hodnota("SELECT jmeno FROM uzivatele WHERE id = ?", [(int)$preprava["vlastnik_id"]]) : "";
$upravil_jmeno  = !empty($preprava["upravil"]) ? (string)hodnota("SELECT jmeno FROM uzivatele WHERE id = ?", [(int)$preprava["upravil"]]) : "";

$dopravce_id = (int)($preprava["dopravce_id"] ?? $predvoleny_dopravce);
/* Volba externího dispečinku ve formuláři: uložená jízda ukáže ano/ne,
   nová nechá rozhodnout kartu dopravce (nebo přijde z plánu vozů s ano). */
$dopravce_je_klient = je_klient_dispecinku($dopravce_id ?: null);
if ($nova) {
  $volba_dispecinku = vstup("dispecink") === "1" ? "1" : "";
} else {
  $volba_dispecinku = !empty($preprava["dispecink_klient_id"]) ? "1" : ($dopravce_je_klient ? "0" : "");
}
$klient_dispecinku = !empty($preprava["dispecink_klient_id"]) ? radek("SELECT id, nazev FROM firmy WHERE id = ?", [(int)$preprava["dispecink_klient_id"]]) : null;
/* Návrh ceny zákazníka podle ceníku a historie — jen spedice, jízda pod
   dispečinkem cenu zákazníka nemá. */
$navrh = (!$nova && $ceny && !$klient_dispecinku)
  ? navrh_ceny($preprava["zakaznik_id"] ? (int)$preprava["zakaznik_id"] : null, (string)$preprava["nakladka_misto"], (string)$preprava["vykladka_misto"],
               $preprava["km"] !== null ? (int)$preprava["km"] : null, (string)$preprava["typ_vozidla"], (int)$preprava["id"])
  : null;
$nabidka_puvod = ($preprava && !empty($preprava["nabidka_id"])) ? radek("SELECT id, cislo FROM nabidky WHERE id = ?", [(int)$preprava["nabidka_id"]]) : null;
$vozidla = $dopravce_id ? radky("SELECT id, spz, typ FROM vozidla WHERE firma_id = ? AND aktivni = 1 ORDER BY spz", [$dopravce_id]) : [];
$ridici  = $dopravce_id ? radky("SELECT id, jmeno FROM ridici WHERE firma_id = ? AND aktivni = 1 ORDER BY LOWER(jmeno)", [$dopravce_id]) : [];

$body     = $preprava ? body_prepravy((int)$preprava["id"]) : [];
$prilohy  = $preprava ? radky("SELECT p.*, u.jmeno AS kdo FROM prilohy p LEFT JOIN uzivatele u ON u.id = p.uzivatel_id WHERE p.preprava_id = ? ORDER BY p.id", [(int)$preprava["id"]]) : [];
$dopravce_firma = $dopravce_id ? radek("SELECT * FROM firmy WHERE id = ?", [$dopravce_id]) : null;
$odkazy_ven = [];
if ($preprava) {
  foreach (DRUHY_ODKAZU as $d => $popis) $odkazy_ven[$d] = odkaz_verejny((int)$preprava["id"], $d);
}

$udalosti = $preprava ? radky(
  "SELECT u.*, z.jmeno AS kdo FROM udalosti u
     LEFT JOIN uzivatele z ON z.id = u.uzivatel_id
    WHERE u.preprava_id = ? ORDER BY u.id DESC LIMIT 30", [(int)$preprava["id"]]) : [];

/* Bod, který se právě upravuje (otevřený inline formulář). */
$upravovany = null;
if ($preprava && vstup_cislo("bod")) {
  foreach ($body as $b) if ((int)$b["id"] === vstup_cislo("bod")) $upravovany = $b;
}

/* Historie stejné trasy — poslední tři jízdy odjinud než z téhle. */
$historie = [];
if ($preprava && $h("nakladka_misto") !== "" && $h("vykladka_misto") !== "") {
  $historie = radky(
    "SELECT p.id, p.cislo, p.nakladka_datum, p.cena_zakaznik, p.cena_dopravce, p.dispecink_klient_id, d.nazev AS dopravce_nazev
       FROM prepravy p LEFT JOIN firmy d ON d.id = p.dopravce_id
      WHERE p.id <> ? AND p.sablona = 0 AND p.stav <> 'zruseno'
        AND LOWER(p.nakladka_misto) = LOWER(?) AND LOWER(p.vykladka_misto) = LOWER(?)
      ORDER BY COALESCE(p.nakladka_datum, '') DESC, p.id DESC LIMIT 3",
    [(int)$preprava["id"], $h("nakladka_misto"), $h("vykladka_misto")]);
}

$do_voleb = function (array $zaznamy, string $sloupec): array {
  $ven = [];
  foreach ($zaznamy as $z) $ven[(string)$z["id"]] = (string)$z[$sloupec];
  return $ven;
};
$volby_mist = [];
foreach ($mista as $m) $volby_mist[(string)$m["id"]] = (string)$m["nazev"] . ($m["mesto"] ? " — " . $m["mesto"] : "");

$akce_hlavy = "";
if (!$nova) {
  /* Objednávka nese cenu dopravce — brigádník ji neotevře. */
  if ($dopravce_id && $ceny_dopravce) {
    $akce_hlavy .= '<a class="tlacitko" href="' . chran(odkaz("objednavka", ["id" => $preprava["id"]]))
                . '" target="_blank" rel="noopener">Objednávka přepravy</a>';
  }
  if ($smi_dispecink) {
    $akce_hlavy .= '<form method="post" action="' . chran($zpet()) . '" style="display:inline">'
      . pole_token() . '<input type="hidden" name="akce" value="kopie">'
      . '<button type="submit" class="tlacitko obrys">Vytvořit kopii</button></form>';
  }
}

$nadpis = $nova ? "Nová přeprava" : ($body ? popis_trasy($body) : "Přeprava " . $h("cislo"));
hlava($nova ? "Nová přeprava" : "Přeprava " . $h("cislo"), "prepravy");
?>
<a class="app-zpet" href="<?= chran(odkaz("prepravy")) ?>">← Zpět na seznam přeprav</a>
<?php
hlava_stranky($nova ? "Evidence" : "Přeprava " . $h("cislo") . ((int)$h("sablona") === 1 ? " · šablona linky" : "") . ($klient_dispecinku ? " · externí dispečink" : ""), $nadpis, $akce_hlavy);

/* Formulář bodu — sdílený pro přidání i úpravu. */
function formular_bodu(array $b, array $volby_mist, bool $uprava): void {
  $v = function (string $k) use ($b) { return chran((string)($b[$k] ?? "")); };
  ?>
  <div class="pole-radek ctyri">
    <div class="pole">
      <label for="druh">Druh</label>
      <select id="druh" name="druh"><?= volby(DRUHY_BODU, $b["druh"] ?? "nakladka") ?></select>
    </div>
    <div class="pole">
      <label for="misto_id">Místo z adresáře</label>
      <select id="misto_id" name="misto_id"><?= volby($volby_mist, (string)($b["misto_id"] ?? ""), "— ručně —") ?></select>
    </div>
    <div class="pole">
      <label for="misto">Obec</label>
      <input type="text" id="misto" name="misto" value="<?= $v("misto") ?>" list="seznam-obci">
    </div>
    <div class="pole">
      <label for="datum">Datum</label>
      <input type="date" id="datum" name="datum" value="<?= $v("datum") ?>">
    </div>
  </div>
  <div class="pole-radek ctyri">
    <div class="pole">
      <label for="od">Okno od</label>
      <input type="time" id="od" name="od" value="<?= $v("od") ?>">
    </div>
    <div class="pole">
      <label for="do">Okno do</label>
      <input type="time" id="do" name="do" value="<?= $v("do") ?>">
    </div>
    <div class="pole sirsi">
      <label for="adresa">Adresa <span class="napoveda">— doplní se z adresáře, když zůstane prázdná</span></label>
      <input type="text" id="adresa" name="adresa" value="<?= $v("adresa") ?>">
    </div>
  </div>
  <div class="pole-radek ctyri">
    <div class="pole sirsi">
      <label for="kontakt">Kontakt na místě</label>
      <input type="text" id="kontakt" name="kontakt" value="<?= $v("kontakt") ?>">
    </div>
    <div class="pole sirsi">
      <label for="bod_poznamka">Poznámka k bodu</label>
      <input type="text" id="bod_poznamka" name="bod_poznamka" value="<?= $v("poznamka") ?>">
    </div>
  </div>
  <details<?= ($b["zbozi"] ?? "") !== "" || ($b["hmotnost"] ?? null) !== null ? " open" : "" ?>>
    <summary class="app-perex" style="cursor:pointer">Zboží u tohoto bodu <span class="napoveda">— nepovinné, u celovozu stačí souhrn u přepravy</span></summary>
    <div class="pole-radek tri" style="margin-top:10px">
      <div class="pole">
        <label for="bod_zbozi">Zboží</label>
        <input type="text" id="bod_zbozi" name="bod_zbozi" value="<?= $v("zbozi") ?>">
      </div>
      <div class="pole">
        <label for="bod_hmotnost">Hmotnost <span class="napoveda">kg</span></label>
        <input type="number" id="bod_hmotnost" name="bod_hmotnost" value="<?= $v("hmotnost") ?>" min="0">
      </div>
      <div class="pole">
        <label for="bod_palet">Palet</label>
        <input type="number" id="bod_palet" name="bod_palet" value="<?= $v("palet") ?>" min="0">
      </div>
    </div>
  </details>
  <div class="tlacitka" style="margin-top:14px">
    <button type="submit" class="tlacitko"><?= $uprava ? "Uložit bod" : "Přidat bod" ?></button>
    <?php if ($uprava): ?><a class="tlacitko obrys" href="<?= chran(odkaz("preprava", ["id" => $b["preprava_id"]])) ?>">Zrušit úpravu</a><?php endif; ?>
  </div>
  <?php
}
?>

<datalist id="seznam-obci">
  <?php foreach ($mista as $m): ?><option value="<?= chran($m["mesto"]) ?>"><?= chran($m["nazev"]) ?></option><?php endforeach; ?>
</datalist>

<div class="app-sloupce">
  <div>

    <?php if (!$nova): ?>
      <!-- ================= Trasa ================= -->
      <div class="formular" style="margin-bottom:20px">
        <div class="skupina" style="margin-bottom:0">
          <h2>Trasa <span class="napoveda" style="text-transform:none;letter-spacing:0"><?= count($body) ?> <?= count($body) === 1 ? "bod" : (count($body) < 5 ? "body" : "bodů") ?></span></h2>

          <?php if (!$body): ?>
            <p class="prazdno">Jízda zatím nemá žádný bod. Přidejte aspoň nakládku a vykládku.</p>
          <?php else: ?>
            <div class="tabulka-obal">
              <table class="id-tabulka trasa">
                <thead><tr><th>#</th><th>Bod</th><th>Místo</th><th>Termín</th><th>Zboží</th><th>Splněno</th><th class="netisknout"></th></tr></thead>
                <tbody>
                <?php foreach ($body as $i => $b): $bid = (int)$b["id"]; ?>
                  <tr class="<?= (int)$b["splneno"] === 1 ? "bod-splneny" : "" ?>">
                    <td class="cislo"><?= (int)$b["poradi"] ?></td>
                    <td><?= chran(nazev_druhu($b["druh"])) ?></td>
                    <td>
                      <b><?= chran($b["misto"] ?: "—") ?></b>
                      <?php if ($b["adresa"]): ?><span class="druhotny"><?= chran($b["adresa"]) ?></span><?php endif; ?>
                      <?php if ($b["kontakt"]): ?><span class="druhotny"><?= chran($b["kontakt"]) ?></span><?php endif; ?>
                      <?php if ($b["poznamka"]): ?><span class="druhotny"><?= chran($b["poznamka"]) ?></span><?php endif; ?>
                    </td>
                    <td><?= chran(datum($b["datum"])) ?><span class="druhotny"><?= chran(okno($b["od"], $b["do"])) ?></span></td>
                    <td>
                      <?= chran($b["zbozi"] ?: "") ?>
                      <?php $d = []; if ($b["hmotnost"]) $d[] = cislo($b["hmotnost"]) . " kg"; if ($b["palet"]) $d[] = (int)$b["palet"] . " pal."; ?>
                      <?php if ($d): ?><span class="druhotny"><?= chran(implode(" · ", $d)) ?></span><?php endif; ?>
                      <?php if (!$b["zbozi"] && !$d): ?><span class="druhotny">—</span><?php endif; ?>
                    </td>
                    <td>
                      <?php if (!$smi_dispecink): ?>
                        <?= (int)$b["splneno"] === 1 ? '<span class="stitek stitek-hotovo">✓ hotovo</span>' : '<span class="druhotny">čeká</span>' ?>
                      <?php else: ?>
                      <form method="post" action="<?= chran($zpet()) ?>" style="margin:0">
                        <?= pole_token() ?>
                        <input type="hidden" name="bod_id" value="<?= $bid ?>">
                        <?php if ((int)$b["splneno"] === 1): ?>
                          <input type="hidden" name="akce" value="bod_odsplnit">
                          <button type="submit" class="stitek stitek-hotovo" style="cursor:pointer" title="Kliknutím vrátíte na nesplněno">✓ <?= chran($b["splneno_kdy"] ? date("j. n. H:i", strtotime($b["splneno_kdy"])) : "hotovo") ?></button>
                        <?php else: ?>
                          <input type="hidden" name="akce" value="bod_splnit">
                          <button type="submit" class="tlacitko obrys" style="padding:4px 10px;font-size:.8rem">Splněno</button>
                        <?php endif; ?>
                      </form>
                      <?php endif; ?>
                    </td>
                    <td class="netisknout" style="white-space:nowrap">
                      <?php if ($smi_dispecink): ?>
                      <a href="<?= chran(odkaz("preprava", ["id" => $preprava["id"], "bod" => $bid])) ?>#bod-<?= $bid ?>" class="odkaz-tlacitko" style="margin:0">upravit</a>
                      <?php foreach ([["bod_vys", "↑", $i > 0], ["bod_niz", "↓", $i < count($body) - 1]] as [$a, $z, $lze]): if (!$lze) continue; ?>
                        <form method="post" action="<?= chran($zpet()) ?>" style="display:inline">
                          <?= pole_token() ?><input type="hidden" name="akce" value="<?= $a ?>"><input type="hidden" name="bod_id" value="<?= $bid ?>">
                          <button type="submit" class="odkaz-tlacitko" title="Posunout"><?= $z ?></button>
                        </form>
                      <?php endforeach; ?>
                      <form method="post" action="<?= chran($zpet()) ?>" style="display:inline" data-potvrdit="Smazat bod <?= chran($b["misto"]) ?>?">
                        <?= pole_token() ?><input type="hidden" name="akce" value="bod_smazat"><input type="hidden" name="bod_id" value="<?= $bid ?>">
                        <button type="submit" class="odkaz-tlacitko">smazat</button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <?php if (!$smi_dispecink): ?>
          <?php elseif ($upravovany): ?>
            <form method="post" action="<?= chran($zpet()) ?>" id="bod-<?= (int)$upravovany["id"] ?>" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--linka)">
              <?= pole_token() ?>
              <input type="hidden" name="akce" value="bod_ulozit">
              <input type="hidden" name="bod_id" value="<?= (int)$upravovany["id"] ?>">
              <p class="app-perex"><b>Úprava bodu <?= (int)$upravovany["poradi"] ?></b></p>
              <?php formular_bodu($upravovany, $volby_mist, true); ?>
            </form>
          <?php else: ?>
            <details style="margin-top:14px">
              <summary class="tlacitko obrys" style="display:inline-block;cursor:pointer;padding:9px 16px;font-size:.9rem">Přidat bod trasy</summary>
              <form method="post" action="<?= chran($zpet()) ?>" style="margin-top:14px">
                <?= pole_token() ?>
                <input type="hidden" name="akce" value="bod_pridat">
                <?php formular_bodu(["druh" => "vykladka", "preprava_id" => $preprava["id"]], $volby_mist, false); ?>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ================= Hlavní formulář ================= -->
    <form method="post" action="<?= chran(odkaz("preprava", ["id" => $nova ? "nova" : $preprava["id"]])) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="ulozit">
      <?php if (!$nova): ?><input type="hidden" name="upraveno_pred" value="<?= chran($h("upraveno")) ?>"><?php endif; ?>
      <?php if (!$smi_dispecink): ?><p class="vzkaz vzkaz-pozor">Účetní mění jen doklady a čísla faktur; ostatní pole jsou jen ke čtení.</p><?php endif; ?>
      <fieldset class="cast"<?= $smi_dispecink ? "" : " disabled" ?>>

      <div class="skupina">
        <h2>Zakázka</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="stav">Stav <span class="napoveda">— naloženo a vyloženo se řídí splněnými body</span></label>
            <select id="stav" name="stav"><?= volby(STAVY, $h("stav", "nova")) ?></select>
          </div>
          <div class="pole">
            <label for="zakaznik_id">Zákazník</label>
            <select id="zakaznik_id" name="zakaznik_id"><?= volby($do_voleb($zakaznici, "nazev"), $h("zakaznik_id"), "— nevybrán —") ?></select>
          </div>
          <div class="pole">
            <label for="ref_zakaznika">Reference zákazníka</label>
            <input type="text" id="ref_zakaznika" name="ref_zakaznika" value="<?= chran($h("ref_zakaznika")) ?>"
                   placeholder="číslo objednávky u zákazníka">
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="vlastnik_id">Má na starosti</label>
            <select id="vlastnik_id" name="vlastnik_id"><?= volby($do_voleb($uzivatele, "jmeno"), $h("vlastnik_id", $nova ? (string)uzivatel()["id"] : ""), "— nikdo —") ?></select>
          </div>
        </div>
      </div>

      <?php if ($nova): ?>
        <div class="skupina">
          <h2>Nakládka</h2>
          <div class="pole-radek">
            <div class="pole">
              <label for="nakladka_misto">Obec</label>
              <input type="text" id="nakladka_misto" name="nakladka_misto" list="seznam-obci">
            </div>
            <div class="pole">
              <label for="nakladka_adresa">Adresa a kontakt</label>
              <input type="text" id="nakladka_adresa" name="nakladka_adresa">
            </div>
          </div>
          <div class="pole-radek tri">
            <div class="pole"><label for="nakladka_datum">Datum</label><input type="date" id="nakladka_datum" name="nakladka_datum" value="<?= chran((string)(vstup_datum("den") ?? "")) ?>"></div>
            <div class="pole"><label for="nakladka_od">Okno od</label><input type="time" id="nakladka_od" name="nakladka_od"></div>
            <div class="pole"><label for="nakladka_do">Okno do</label><input type="time" id="nakladka_do" name="nakladka_do"></div>
          </div>
        </div>
        <div class="skupina">
          <h2>Vykládka</h2>
          <div class="pole-radek">
            <div class="pole">
              <label for="vykladka_misto">Obec</label>
              <input type="text" id="vykladka_misto" name="vykladka_misto" list="seznam-obci">
            </div>
            <div class="pole">
              <label for="vykladka_adresa">Adresa a kontakt</label>
              <input type="text" id="vykladka_adresa" name="vykladka_adresa">
            </div>
          </div>
          <div class="pole-radek tri">
            <div class="pole"><label for="vykladka_datum">Datum</label><input type="date" id="vykladka_datum" name="vykladka_datum"></div>
            <div class="pole"><label for="vykladka_od">Okno od</label><input type="time" id="vykladka_od" name="vykladka_od"></div>
            <div class="pole"><label for="vykladka_do">Okno do</label><input type="time" id="vykladka_do" name="vykladka_do"></div>
          </div>
          <p class="app-perex">Další zastávky přidáte po založení — trasa může mít bodů, kolik je potřeba.</p>
        </div>
      <?php endif; ?>

      <div class="skupina">
        <h2>Náklad <span class="napoveda" style="text-transform:none;letter-spacing:0">— souhrn za celou jízdu</span></h2>
        <div class="pole">
          <label for="zbozi">Zboží</label>
          <input type="text" id="zbozi" name="zbozi" value="<?= chran($h("zbozi")) ?>">
        </div>
        <div class="pole-radek ctyri">
          <div class="pole">
            <label for="hmotnost">Hmotnost <span class="napoveda">kg</span></label>
            <input type="number" id="hmotnost" name="hmotnost" value="<?= chran($h("hmotnost")) ?>" min="0" step="1">
          </div>
          <div class="pole">
            <label for="palet">Palet</label>
            <input type="number" id="palet" name="palet" value="<?= chran($h("palet")) ?>" min="0" step="1">
          </div>
          <div class="pole">
            <label for="ldm">LDM</label>
            <input type="text" id="ldm" name="ldm" value="<?= chran($h("ldm")) ?>" inputmode="decimal">
          </div>
          <div class="pole">
            <label for="typ_vozidla">Požadované vozidlo</label>
            <select id="typ_vozidla" name="typ_vozidla"><?= volby(TYPY_VOZIDEL, $h("typ_vozidla", "plachta")) ?></select>
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole sirsi">
            <label for="pozadavky">Zvláštní požadavky <span class="napoveda">— hydraulické čelo, teplota, ADR…</span></label>
            <input type="text" id="pozadavky" name="pozadavky" value="<?= chran($h("pozadavky")) ?>">
          </div>
          <div class="pole">
            <label for="km">Vzdálenost <span class="napoveda">km</span></label>
            <input type="number" id="km" name="km" value="<?= chran($h("km")) ?>" min="0" step="1" placeholder="zatím ručně">
          </div>
        </div>
      </div>

      <div class="skupina">
        <h2>Dopravce</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="dopravce_id">Dopravce</label>
            <select id="dopravce_id" name="dopravce_id"><?= volby($do_voleb($dopravci, "nazev"), $h("dopravce_id", $predvoleny_dopravce ? (string)$predvoleny_dopravce : ""), "— nezajištěno —") ?></select>
          </div>
          <?php if ($ceny_dopravce): ?>
          <div class="pole">
            <label for="cena_dopravce">Cena dopravce <span class="napoveda">Kč bez DPH<?= $klient_dispecinku ? "; u dispečinku obrat vozu klienta" : "" ?></span></label>
            <input type="text" id="cena_dopravce" name="cena_dopravce" value="<?= chran($h("cena_dopravce")) ?>" inputmode="decimal">
          </div>
          <?php endif; ?>
          <div class="pole">
            <label for="dispecink">Externí dispečink <span class="napoveda">— vůz klienta, který řídíme my</span></label>
            <select id="dispecink" name="dispecink"><?= volby(["" => "— podle karty dopravce —", "1" => "Ano, jízda pod dispečinkem", "0" => "Ne, běžná spedice"], $volba_dispecinku) ?></select>
          </div>
        </div>
        <?= upozorneni_dopravce_html($dopravce_firma, "Objednávku lze vystavit, rozhodnutí je na vás.") ?>
        <?php if ($dopravce_id): ?>
          <div class="pole-radek">
            <div class="pole">
              <label for="vozidlo_id">Vozidlo z karty dopravce</label>
              <select id="vozidlo_id" name="vozidlo_id"><?= volby($do_voleb($vozidla, "spz"), $h("vozidlo_id", $nova ? (string)(vstup_cislo("vozidlo") ?: "") : ""), "— nevybráno —") ?></select>
            </div>
            <div class="pole">
              <label for="ridic_id">Řidič z karty dopravce</label>
              <select id="ridic_id" name="ridic_id"><?= volby($do_voleb($ridici, "jmeno"), $h("ridic_id"), "— nevybrán —") ?></select>
            </div>
          </div>
        <?php else: ?>
          <p class="app-perex">Vozidla a řidiče z karty dopravce nabídneme, jakmile dopravce vyberete a přepravu uložíte.</p>
        <?php endif; ?>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="spz">SPZ do objednávky</label>
            <input type="text" id="spz" name="spz" value="<?= chran($h("spz")) ?>">
          </div>
          <div class="pole">
            <label for="ridic_jmeno">Řidič</label>
            <input type="text" id="ridic_jmeno" name="ridic_jmeno" value="<?= chran($h("ridic_jmeno")) ?>">
          </div>
          <div class="pole">
            <label for="ridic_telefon">Telefon na řidiče</label>
            <input type="tel" id="ridic_telefon" name="ridic_telefon" value="<?= chran($h("ridic_telefon")) ?>">
          </div>
        </div>
        <div class="pole">
          <label for="poznamka_dopravci">Pokyny pro dopravce <span class="napoveda">— tisknou se v objednávce</span></label>
          <textarea id="poznamka_dopravci" name="poznamka_dopravci" style="min-height:80px"><?= chran($h("poznamka_dopravci")) ?></textarea>
        </div>
      </div>

      <?php if ($ceny): ?>
        <div class="skupina">
          <h2>Obchod</h2>
          <div class="pole-radek tri">
            <div class="pole">
              <label for="cena_zakaznik">Cena zákazníka <span class="napoveda">Kč bez DPH</span></label>
              <input type="text" id="cena_zakaznik" name="cena_zakaznik" value="<?= chran($h("cena_zakaznik")) ?>" inputmode="decimal">
            </div>
            <div class="pole">
              <label for="faktura_vydana">Vydaná faktura</label>
              <input type="text" id="faktura_vydana" name="faktura_vydana" value="<?= chran($h("faktura_vydana")) ?>">
            </div>
            <div class="pole">
              <label>Marže</label>
              <p class="cislo" id="marze-nahled" style="padding:11px 0;margin:0;font-weight:700">—</p>
            </div>
          </div>
          <?php if (!$nova && !$klient_dispecinku) echo navrh_ceny_html($navrh, "cena_zakaznik"); ?>
        </div>
      <?php endif; ?>

      </fieldset>

      <div class="skupina">
        <h2>Doklady</h2>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="doklady">Stav dokladů</label>
            <select id="doklady" name="doklady"><?= volby(DOKLADY, $h("doklady", "ceka")) ?></select>
          </div>
          <div class="pole">
            <label for="doklady_poznamka">Poznámka k dokladům</label>
            <input type="text" id="doklady_poznamka" name="doklady_poznamka" value="<?= chran($h("doklady_poznamka")) ?>">
          </div>
          <div class="pole">
            <label for="faktura_prijata">Přijatá faktura dopravce</label>
            <input type="text" id="faktura_prijata" name="faktura_prijata" value="<?= chran($h("faktura_prijata")) ?>">
          </div>
          <?php if ($ceny && !$smi_dispecink): ?>
            <div class="pole">
              <label for="faktura_vydana_ucetni">Vydaná faktura</label>
              <input type="text" id="faktura_vydana_ucetni" name="faktura_vydana" value="<?= chran($h("faktura_vydana")) ?>">
            </div>
          <?php endif; ?>
        </div>
      </div>

      <fieldset class="cast"<?= $smi_dispecink ? "" : " disabled" ?>>
      <?php if (!$nova): ?>
        <div class="skupina">
          <h2>Stálá linka</h2>
          <div class="pole-zaskrtnuti">
            <input type="checkbox" id="sablona" name="sablona" value="1"<?= (int)$h("sablona") === 1 ? " checked" : "" ?>>
            <label for="sablona">Tohle je <b>šablona stálé linky</b> <span class="napoveda">— neukazuje se v seznamech ani na tabuli, jen se z ní generují přepravy</span></label>
          </div>
          <div class="pole-radek">
            <div class="pole">
              <label for="linka_nazev">Název linky</label>
              <input type="text" id="linka_nazev" name="linka_nazev" value="<?= chran($h("linka_nazev")) ?>" placeholder="např. Pardubice – Ostrava, denně">
            </div>
            <div class="pole">
              <span style="display:block;font-size:.88rem;font-weight:700;margin-bottom:6px">Dny v týdnu</span>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <?php $dny_linky = array_map("intval", array_filter(explode(",", $h("linka_dny")))); ?>
                <?php foreach (["Po","Út","St","Čt","Pá","So","Ne"] as $i => $den): ?>
                  <label style="display:flex;gap:5px;align-items:center;font-weight:400;font-size:.9rem">
                    <input type="checkbox" name="den_<?= $i + 1 ?>" value="1"<?= in_array($i + 1, $dny_linky, true) ? " checked" : "" ?> style="accent-color:var(--zluta)"> <?= $den ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="skupina">
        <h2>Interní poznámka</h2>
        <div class="pole">
          <label for="poznamka" class="jen-pro-ctecky">Interní poznámka</label>
          <textarea id="poznamka" name="poznamka" placeholder="Netiskne se v objednávce."><?= chran($h("poznamka")) ?></textarea>
        </div>
      </div>

      </fieldset>

      <button type="submit" class="tlacitko"><?= $nova ? "Založit přepravu" : ($smi_dispecink ? "Uložit změny" : "Uložit doklady a faktury") ?></button>
    </form>
  </div>

  <div>
    <?php if (!$nova): ?>
      <div class="formular">
        <div class="skupina">
          <h2>Shrnutí</h2>
          <ul class="udaje">
            <li><span class="klic">Číslo</span><span class="hodnota cislo"><?= chran($h("cislo")) ?></span></li>
            <?php if ($nabidka_puvod): ?>
              <li><span class="klic">Z nabídky</span><span class="hodnota"><a href="<?= chran(odkaz("nabidka", ["id" => $nabidka_puvod["id"]])) ?>" class="cislo"><?= chran($nabidka_puvod["cislo"]) ?></a></span></li>
            <?php endif; ?>
            <li><span class="klic">Stav</span><span class="hodnota"><?= stitek_stavu($h("stav")) ?></span></li>
            <?php if ($klient_dispecinku): ?>
              <li><span class="klic">Dispečink</span><span class="hodnota">vůz klienta <a href="<?= chran(odkaz("firma", ["id" => $klient_dispecinku["id"]])) ?>"><?= chran($klient_dispecinku["nazev"]) ?></a><br>
                <span class="druhotny"><a href="<?= chran(odkaz("vozy", array_filter(["klient" => $klient_dispecinku["id"], "tyden" => $h("nakladka_datum")]))) ?>">plán vozů</a> · odesílateli fakturuje klient</span></span></li>
            <?php endif; ?>
            <li><span class="klic">Objednávka</span><span class="hodnota"><?= $h("objednavka_datum") ? chran(datum_cas($h("objednavka_datum"))) : "nevystavena" ?><?= $h("objednavka_odeslana") ? "<br><span class=\"druhotny\">odeslána " . chran(datum_cas($h("objednavka_odeslana"))) . "</span>" : "" ?></span></li>
            <?php if ($h("potvrzeno_kdy")): ?><li><span class="klic">Potvrzeno</span><span class="hodnota"><?= chran(datum_cas($h("potvrzeno_kdy"))) ?> dopravcem</span></li><?php endif; ?>
            <?php if ($h("hlaseni")): ?><li><span class="klic">Hlášení</span><span class="hodnota" style="color:var(--pozor-text)">„<?= chran($h("hlaseni")) ?>"<br><span class="druhotny"><?= chran(datum_cas($h("hlaseni_kdy"))) ?></span></span></li><?php endif; ?>
            <?php if ($ceny && $klient_dispecinku): ?>
              <li><span class="klic">Marže</span><span class="hodnota">— <span style="font-weight:400;color:var(--text-tlum)">pod dispečinkem se nepočítá, odměnu ukáže podklad</span></span></li>
            <?php elseif ($ceny): ?>
              <li><span class="klic">Marže</span><span class="hodnota cislo"><?=
                ($preprava["cena_zakaznik"] === null && $preprava["cena_dopravce"] === null) ? "—"
                : chran(castka((float)$preprava["cena_zakaznik"] - (float)$preprava["cena_dopravce"])) ?></span></li>
            <?php endif; ?>
            <?php foreach ([["vydana", "faktura_vydana", "Vydaná faktura", $ceny], ["prijata", "faktura_prijata", "Přijatá faktura", true]] as [$dr, $sl, $popis, $smi]):
              if (!$smi || $h($sl) === "") continue;
              $fk = faktura_podle_cisla($dr, $h($sl)); ?>
              <li><span class="klic"><?= $popis ?></span><span class="hodnota cislo"><?= chran($h($sl)) ?><?php
                if ($fk) echo '<br><span class="druhotny" style="font-family:var(--pismo)">' . ($fk["uhrazeno"] ? "zaplaceno " . chran(datum($fk["uhrazeno"])) : "splatnost " . chran(datum($fk["splatnost"])) . (dnu_od_splatnosti($fk["splatnost"]) > 0 ? ", <span style=\"color:var(--chyba-text)\">" . dnu_od_splatnosti($fk["splatnost"]) . " dní po</span>" : "")) . '</span>';
                else echo '<br><span class="druhotny" style="font-family:var(--pismo)">bez záznamu faktury</span>';
              ?></span></li>
            <?php endforeach; ?>
            <li><span class="klic">Má na starosti</span><span class="hodnota"><?= chran($vlastnik_jmeno ?: "nikdo") ?></span></li>
            <li><span class="klic">Založeno</span><span class="hodnota"><?= chran(datum_cas($h("vytvoreno"))) ?><?= $upravil_jmeno !== "" ? "<br><span class=\"druhotny\">naposledy upravil " . chran($upravil_jmeno) . " " . chran(datum_cas($h("upraveno"))) . "</span>" : "" ?></span></li>
          </ul>
        </div>

        <?php if ($historie && $ceny_dopravce): ?>
          <div class="skupina">
            <h2>Naposledy na této trase</h2>
            <ul class="protokol">
              <?php foreach ($historie as $hist): ?>
                <li>
                  <a href="<?= chran(odkaz("preprava", ["id" => $hist["id"]])) ?>" class="cislo"><?= chran($hist["cislo"]) ?></a>
                  — <?= chran($hist["dopravce_nazev"] ?: "bez dopravce") ?>
                  <time><?= chran(datum($hist["nakladka_datum"])) ?>
                    · dopravce <?= chran(castka($hist["cena_dopravce"])) ?><?php
                    if (!empty($hist["dispecink_klient_id"])) echo " · pod dispečinkem";
                    elseif ($ceny) echo " · zákazník " . chran(castka($hist["cena_zakaznik"]))
                      . " · marže " . chran(castka((float)$hist["cena_zakaznik"] - (float)$hist["cena_dopravce"]));
                  ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="skupina">
          <h2>Odkazy ven</h2>
          <p class="app-perex">Bez hesla; kdo odkaz má, vidí jen tuhle přepravu a jen to, co mu patří. Platí měsíc po vykládce.</p>
          <?php foreach (DRUHY_ODKAZU as $d => $popis):
            $o = $odkazy_ven[$d];
            $telefon = $d === "ridic" ? $h("ridic_telefon") : ($d === "dopravce" ? (string)($dopravce_firma["kontakt_telefon"] ?? "") : "");
          ?>
            <div class="odkaz-ven">
              <b><?= chran($popis) ?></b>
              <?php if ($o): $adresa = verejna_adresa((string)$o["kod"]); ?>
                <input type="text" readonly value="<?= chran($adresa) ?>" onfocus="this.select()" aria-label="Odkaz pro <?= chran(mb_strtolower($popis)) ?>">
                <div class="tlacitka" style="margin:6px 0 0;gap:6px">
                  <a class="tlacitko obrys" style="padding:6px 10px;font-size:.82rem" href="<?= chran($adresa) ?>" target="_blank" rel="noopener noreferrer">Otevřít</a>
                  <?php if ($telefon !== "" && whatsapp_adresa($telefon, "") !== ""): ?>
                    <a class="tlacitko obrys" style="padding:6px 10px;font-size:.82rem" target="_blank" rel="noopener noreferrer"
                       href="<?= chran(whatsapp_adresa($telefon, ($d === "ridic" ? "Pokyny k jízdě " : "Objednávka přepravy ") . $h("cislo") . " od " . nastaveni("firma_nazev") . ": " . $adresa)) ?>">WhatsApp</a>
                  <?php endif; ?>
                  <form method="post" action="<?= chran($zpet()) ?>" style="display:inline" data-potvrdit="Zrušit odkaz pro <?= chran(mb_strtolower($popis)) ?>? Přestane okamžitě platit.">
                    <?= pole_token() ?><input type="hidden" name="akce" value="odkaz_zrusit"><input type="hidden" name="druh" value="<?= $d ?>">
                    <button type="submit" class="odkaz-tlacitko" style="margin:0">zrušit</button>
                  </form>
                </div>
                <span class="druhotny"><?= (int)$o["otevreni"] ?>× otevřeno<?= $o["naposledy"] ? ", naposledy " . chran(datum_cas($o["naposledy"])) : "" ?></span>
              <?php elseif ($smi_dispecink): ?>
                <form method="post" action="<?= chran($zpet()) ?>" style="margin-top:4px">
                  <?= pole_token() ?><input type="hidden" name="akce" value="odkaz_vytvorit"><input type="hidden" name="druh" value="<?= $d ?>">
                  <button type="submit" class="tlacitko obrys" style="padding:6px 12px;font-size:.84rem"<?= ($d !== "zakaznik" && !$dopravce_id) ? " disabled title=\"Nejdřív přiřaďte dopravce\"" : "" ?>>Vytvořit odkaz</button>
                </form>
              <?php else: ?>
                <span class="druhotny">zatím bez odkazu</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="skupina">
          <h2>Přílohy</h2>
          <?php if ($prilohy): ?>
            <ul class="protokol">
              <?php foreach ($prilohy as $pr): ?>
                <li>
                  <a href="<?= chran(odkaz("priloha", ["id" => $pr["id"]])) ?>" target="_blank" rel="noopener"><?= chran($pr["nazev"]) ?></a>
                  <form method="post" action="<?= chran($zpet()) ?>" style="display:inline" data-potvrdit="Smazat přílohu <?= chran($pr["nazev"]) ?>?">
                    <?= pole_token() ?><input type="hidden" name="akce" value="priloha_smazat"><input type="hidden" name="priloha_id" value="<?= (int)$pr["id"] ?>">
                    <button type="submit" class="odkaz-tlacitko">smazat</button>
                  </form>
                  <time><?= chran(velikost_souboru((int)$pr["velikost"])) ?> · <?= chran(datum_cas($pr["kdy"])) ?><?= $pr["kdo"] ? " · " . chran($pr["kdo"]) : "" ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="app-perex">Zatím žádná příloha. Sem patří dodací listy, přepravní listy a fotky z vykládky.</p>
          <?php endif; ?>
          <form method="post" action="<?= chran($zpet()) ?>" enctype="multipart/form-data" style="margin-top:10px">
            <?= pole_token() ?>
            <input type="hidden" name="akce" value="priloha_pridat">
            <div class="pole">
              <label for="soubor" class="jen-pro-ctecky">Soubor</label>
              <input type="file" id="soubor" name="soubor" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" required>
            </div>
            <button type="submit" class="tlacitko obrys" style="padding:8px 14px;font-size:.88rem">Nahrát přílohu</button>
            <p class="formular-poznamka">PDF nebo fotka do 8 MB.</p>
          </form>
        </div>

        <?php if ($smi_dispecink): ?>
        <div class="skupina" style="margin-bottom:0">
          <h2>Ukončení</h2>
          <?php if ($h("stav") !== "zruseno"): ?>
            <form method="post" action="<?= chran($zpet()) ?>"
                  data-potvrdit="Opravdu přepravu zrušit? Zůstane v evidenci, ale nebude se počítat do obratu.">
              <?= pole_token() ?>
              <input type="hidden" name="akce" value="zrusit">
              <button type="submit" class="tlacitko obrys">Zrušit přepravu</button>
            </form>
          <?php else: ?>
            <p class="app-perex">Přeprava je zrušená. Vrátit ji zpět jde změnou stavu ve formuláři.</p>
          <?php endif; ?>
          <?php if (je_spravce()): ?>
            <form method="post" action="<?= chran($zpet()) ?>" style="margin-top:10px"
                  data-potvrdit="Smazat přepravu nadobro i s body a přílohami? Tohle se vrátit nedá — zrušení je obvykle to, co chcete.">
              <?= pole_token() ?>
              <input type="hidden" name="akce" value="smazat">
              <button type="submit" class="odkaz-tlacitko" style="margin:0">Smazat nadobro</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($udalosti): ?>
        <div class="formular" style="margin-top:20px">
          <div class="skupina" style="margin-bottom:0">
            <h2>Protokol</h2>
            <ul class="protokol">
              <?php foreach ($udalosti as $u): ?>
                <li>
                  <?= chran($u["text"]) ?>
                  <time><?= chran(datum_cas($u["kdy"])) ?><?= $u["kdo"] ? " · " . chran($u["kdo"]) : "" ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php
pata();

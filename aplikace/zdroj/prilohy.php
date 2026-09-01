<?php
/* =========================================================================
   Přílohy k přepravě — skeny a fotky dokladů

   Soubory leží v data/prilohy/, kam web nevidí (adresář data/ má vlastní
   .htaccess, který zakazuje všechno). Ven je vydává jen stránka priloha.php
   po přihlášení.

   Uloží se pod vlastním jménem složeným z náhodných znaků a povolené
   přípony; původní jméno se drží jen v databázi jako popisek. Odesílatel
   tak nemá vliv na to, jak se soubor na disku jmenuje.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Co smí dovnitř. Typ se bere z téhle tabulky, ne z toho, co tvrdí
   prohlížeč — jinak by šlo podstrčit stránku, která se v prohlížeči spustí.
   SVG a cokoliv s HTML uvnitř tu schválně není. */
const PRILOHY_TYPY = [
  "pdf"  => "application/pdf",
  "jpg"  => "image/jpeg",
  "jpeg" => "image/jpeg",
  "png"  => "image/png",
  "webp" => "image/webp",
  "heic" => "image/heic",
  "heif" => "image/heif",
];

const PRILOHA_NEJVIC = 8388608;   /* 8 MB */

function prilohy_adresar(): string {
  $adresar = APLIKACE_CESTA . "/data/prilohy";
  if (!is_dir($adresar)) @mkdir($adresar, 0770, true);
  return $adresar;
}

function priloha_cesta(string $soubor): string {
  /* Jméno pochází z naší strany, ale basename je levná pojistka proti
     tomu, aby se přes ../ dalo sáhnout jinam. */
  return prilohy_adresar() . "/" . basename($soubor);
}

/* Ověří, že obsah odpovídá příponě. Bez toho by stačilo přejmenovat. */
function priloha_obsah_sedi(string $cesta, string $pripona): bool {
  if ($pripona === "pdf") {
    $zacatek = (string)@file_get_contents($cesta, false, null, 0, 5);
    return $zacatek === "%PDF-";
  }
  if (in_array($pripona, ["jpg", "jpeg", "png", "webp"], true)) {
    return @getimagesize($cesta) !== false;
  }
  /* HEIC z iPhonu starší PHP nerozpozná — u něj se spoléhá na příponu. */
  return true;
}

/* Uloží nahraný soubor. Vrací id přílohy, nebo null a větu v $chyba. */
function priloha_uloz(array $soubor, int $preprava_id, ?string &$chyba = null): ?int {
  $chyba = null;
  $stav = $soubor["error"] ?? UPLOAD_ERR_NO_FILE;

  if ($stav === UPLOAD_ERR_NO_FILE) { $chyba = "Nevybrali jste soubor."; return null; }
  if ($stav === UPLOAD_ERR_INI_SIZE || $stav === UPLOAD_ERR_FORM_SIZE) {
    $chyba = "Soubor je větší, než hosting povoluje. Zmenšete ho, nebo pošlete po částech.";
    return null;
  }
  if ($stav !== UPLOAD_ERR_OK) { $chyba = "Soubor se nepodařilo nahrát."; return null; }
  if ((int)$soubor["size"] > PRILOHA_NEJVIC) {
    $chyba = "Soubor má víc než 8 MB. Fotku stačí zmenšit.";
    return null;
  }

  $puvodni = (string)($soubor["name"] ?? "soubor");
  $pripona = strtolower((string)pathinfo($puvodni, PATHINFO_EXTENSION));
  if (!isset(PRILOHY_TYPY[$pripona])) {
    $chyba = "Tenhle typ souboru nepřijímáme. Jde nahrát PDF a fotky (JPG, PNG, WEBP, HEIC).";
    return null;
  }
  if (!priloha_obsah_sedi($soubor["tmp_name"], $pripona)) {
    $chyba = "Obsah souboru neodpovídá jeho příponě.";
    return null;
  }

  $adresar = prilohy_adresar();
  if (!is_dir($adresar) || !is_writable($adresar)) {
    $chyba = "Do adresáře data/prilohy nejde zapisovat. Nastavte mu práva 770.";
    return null;
  }

  $jmeno = bin2hex(random_bytes(16)) . "." . $pripona;
  if (!move_uploaded_file($soubor["tmp_name"], $adresar . "/" . $jmeno)) {
    $chyba = "Soubor se nepodařilo uložit.";
    return null;
  }
  @chmod($adresar . "/" . $jmeno, 0660);

  return vloz("prilohy", [
    "preprava_id" => $preprava_id,
    "nazev"       => mb_substr($puvodni, 0, 150),
    "soubor"      => $jmeno,
    "typ"         => PRILOHY_TYPY[$pripona],
    "velikost"    => (int)$soubor["size"],
    "uzivatel_id" => (int)(uzivatel()["id"] ?? 0),
    "kdy"         => date("Y-m-d H:i:s"),
  ]);
}

function priloha_smaz(array $priloha): void {
  $cesta = priloha_cesta((string)$priloha["soubor"]);
  if (is_file($cesta)) @unlink($cesta);
  dotaz("DELETE FROM prilohy WHERE id = ?", [(int)$priloha["id"]]);
}

/* Smaže všechny přílohy přepravy — volá se, když se maže přeprava. */
function prilohy_smaz_u_prepravy(int $preprava_id): void {
  foreach (radky("SELECT * FROM prilohy WHERE preprava_id = ?", [$preprava_id]) as $p) {
    priloha_smaz($p);
  }
}

function velikost_souboru(?int $bajtu): string {
  $bajtu = (int)$bajtu;
  if ($bajtu < 1024) return $bajtu . " B";
  if ($bajtu < 1048576) return cislo($bajtu / 1024, 0) . " kB";
  return cislo($bajtu / 1048576, 1) . " MB";
}

<?php
/* =========================================================================
   Veřejné odkazy bez hesla — zákazník, dopravce, řidič

   Místo přihlášení dlouhý náhodný kód (160 bitů). Kdo odkaz má, vidí jen
   tu jednu přepravu a jen to, co mu patří: zákazník stav, termíny, místa
   a svou cenu; dopravce svou objednávku; řidič pokyny. Cena dopravce
   a marže ven nejdou nikdy, cena zákazníka jde jen zákazníkovi.

   Platnost se nepamatuje, počítá se: odkaz přestane fungovat měsíc po
   vykládce. Zrušit ho jde kdykoliv dřív.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const DRUHY_ODKAZU = [
  "zakaznik" => "Zákazník",
  "dopravce" => "Dopravce",
  "ridic"    => "Řidič",
];

const ODKAZ_PLATNOST_DNU = 30;   /* po vykládce */
const ODKAZ_MINIMUM_DNU  = 7;    /* od vytvoření, i kdyby byla vykládka dávno */

/* Základní adresa aplikace pro odkazy ven. Nastavení má přednost —
   za proxy nebo při odesílání z příkazové řádky by se z požadavku
   odvodila špatně. */
function zakladni_adresa(): string {
  $z = trim(nastaveni("zakladni_adresa"));
  if ($z !== "") return rtrim($z, "/") . "/";
  $https = (($_SERVER["HTTPS"] ?? "") !== "" && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
  $host = (string)($_SERVER["HTTP_HOST"] ?? "idispecink.cz");
  $cesta = rtrim(dirname((string)($_SERVER["SCRIPT_NAME"] ?? "/aplikace/index.php")), "/");
  return ($https ? "https" : "http") . "://" . $host . $cesta . "/";
}

function verejna_adresa(string $kod): string {
  return zakladni_adresa() . "index.php?s=verejne&k=" . rawurlencode($kod);
}

/* Aktivní odkaz daného druhu — nebo null. */
function odkaz_verejny(int $preprava_id, string $druh): ?array {
  return radek("SELECT * FROM odkazy WHERE preprava_id = ? AND druh = ? AND zruseno = 0 ORDER BY id DESC LIMIT 1",
    [$preprava_id, $druh]);
}

/* Vrátí aktivní odkaz, nebo založí nový. */
function odkaz_verejny_zajisti(int $preprava_id, string $druh): array {
  $o = odkaz_verejny($preprava_id, $druh);
  if ($o) return $o;
  $id = vloz("odkazy", [
    "preprava_id" => $preprava_id,
    "druh"        => isset(DRUHY_ODKAZU[$druh]) ? $druh : "zakaznik",
    "kod"         => bin2hex(random_bytes(20)),
    "vytvoreno"   => date("Y-m-d H:i:s"),
    "vytvoril"    => (int)(uzivatel()["id"] ?? 0),
    "otevreni"    => 0,
    "zruseno"     => 0,
  ]);
  return radek("SELECT * FROM odkazy WHERE id = ?", [$id]);
}

function odkaz_verejny_zrus(int $preprava_id, string $druh): void {
  dotaz("UPDATE odkazy SET zruseno = 1 WHERE preprava_id = ? AND druh = ?", [$preprava_id, $druh]);
}

/* Do kdy odkaz platí (datum). */
function odkaz_platnost_do(array $odkaz, array $preprava): string {
  $zaklad = $preprava["vykladka_datum"] ?: ($preprava["nakladka_datum"] ?: substr((string)$odkaz["vytvoreno"], 0, 10));
  $po_vykladce = date("Y-m-d", strtotime($zaklad . " +" . ODKAZ_PLATNOST_DNU . " days"));
  $minimum     = date("Y-m-d", strtotime(substr((string)$odkaz["vytvoreno"], 0, 10) . " +" . ODKAZ_MINIMUM_DNU . " days"));
  return max($po_vykladce, $minimum);
}

function odkaz_plati(array $odkaz, array $preprava): bool {
  if ((int)$odkaz["zruseno"] === 1) return false;
  return date("Y-m-d") <= odkaz_platnost_do($odkaz, $preprava);
}

/* Najde odkaz podle kódu z adresy. Tvar se kontroluje dřív, než se sahá
   do databáze, a každé nenalezení se počítá proti adrese žadatele. */
function odkaz_podle_kodu(string $kod): ?array {
  if (!preg_match('/^[0-9a-f]{40}$/', $kod)) return null;
  $o = radek("SELECT * FROM odkazy WHERE kod = ?", [$kod]);
  return $o ?: null;
}

function odkaz_zaznamenej_otevreni(array $odkaz): void {
  dotaz("UPDATE odkazy SET naposledy = ?, otevreni = COALESCE(otevreni, 0) + 1 WHERE id = ?",
    [date("Y-m-d H:i:s"), (int)$odkaz["id"]]);
}

/* --- Omezení hádání kódů ------------------------------------------------ */

function verejne_pokusy_vycerpany(): bool {
  smaz_stare_pokusy();
  $pocet = (int)hodnota("SELECT COUNT(*) FROM pokusy WHERE adresa = ?", [hash("sha256", "verejne:" . adresa_zadatele())]);
  return $pocet >= 30;
}

function verejne_zapis_pokus(): void {
  vloz("pokusy", ["adresa" => hash("sha256", "verejne:" . adresa_zadatele()), "kdy" => date("Y-m-d H:i:s")]);
}

/* --- WhatsApp ----------------------------------------------------------- */

/* Telefon v mezinárodním tvaru bez plus a mezer. Devět číslic = české číslo. */
function telefon_mezinarodni(string $telefon): string {
  $cisla = preg_replace('/\D+/', "", $telefon) ?? "";
  if (strpos($cisla, "00") === 0) $cisla = substr($cisla, 2);
  if (strlen($cisla) === 9) $cisla = "420" . $cisla;
  return $cisla;
}

/* Odkaz, který otevře WhatsApp s předvyplněnou zprávou. Žádná služba,
   žádné API — odeslání potvrdí uživatel sám v telefonu. */
function whatsapp_adresa(string $telefon, string $text): string {
  $cislo = telefon_mezinarodni($telefon);
  if (strlen($cislo) < 9) return "";
  return "https://wa.me/" . $cislo . "?text=" . rawurlencode($text);
}

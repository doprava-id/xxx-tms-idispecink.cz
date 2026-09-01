<?php
/* =========================================================================
   Pomocné funkce — ošetření výstupu, formátování, čtení vstupu, vzkazy
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* --- Výstup ------------------------------------------------------------- */

/* Chrání výstup před vložením cizího kódu. Používá se na KAŽDÝ údaj
   z databáze i z formuláře. */
function chran($text): string {
  return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function presmeruj(string $kam): void {
  header("Location: " . $kam, true, 303);
  exit;
}

/* Odkaz uvnitř aplikace. */
function odkaz(string $stranka, array $parametry = []): string {
  $parametry = array_merge(["s" => $stranka], $parametry);
  return "index.php?" . http_build_query($parametry);
}

/* Nouzová stránka pro stavy, kdy aplikace nemůže běžet dál. */
function selhani(string $nadpis, string $text, int $stav = 500): void {
  http_response_code($stav);
  header("Content-Type: text/html; charset=utf-8");
  echo "<!doctype html><html lang=\"cs\"><head><meta charset=\"utf-8\">"
    . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
    . "<meta name=\"robots\" content=\"noindex\">"
    . "<title>" . chran($nadpis) . " — provozní systém</title>"
    . "<link rel=\"stylesheet\" href=\"../assets/css/firemni-styl.css\">"
    . "<link rel=\"stylesheet\" href=\"aplikace.css\"></head><body>"
    . "<main class=\"obal\" style=\"padding-top:48px;padding-bottom:48px;max-width:720px\">"
    . "<span class=\"nadpis-stitek\">Provozní systém</span>"
    . "<h1>" . chran($nadpis) . "</h1>"
    . "<div class=\"doplnit\">" . chran($text) . "</div>"
    . "</main></body></html>";
  exit;
}

/* --- Formátování -------------------------------------------------------- */

/* Haléře se ukazují jen když v částce opravdu jsou — zaokrouhlený podklad
   k fakturaci by jinak neseděl s tím, co se vyfakturuje. */
function castka($hodnota, string $mena = "Kč"): string {
  if ($hodnota === null || $hodnota === "") return "—";
  $h = round((float)$hodnota, 2);
  $desetin = (abs($h - round($h)) < 0.005) ? 0 : 2;
  return number_format($h, $desetin, ",", " ") . " " . $mena;
}

function cislo($hodnota, int $desetin = 0): string {
  if ($hodnota === null || $hodnota === "") return "—";
  return number_format((float)$hodnota, $desetin, ",", " ");
}

function datum($hodnota): string {
  if (!$hodnota) return "—";
  $d = date_create((string)$hodnota);
  return $d ? $d->format("j. n. Y") : "—";
}

function datum_kratce($hodnota): string {
  if (!$hodnota) return "—";
  $d = date_create((string)$hodnota);
  return $d ? $d->format("j. n.") : "—";
}

function datum_cas($hodnota): string {
  if (!$hodnota) return "—";
  $d = date_create((string)$hodnota);
  return $d ? $d->format("j. n. Y H:i") : "—";
}

function cas($hodnota): string {
  return $hodnota ? (string)$hodnota : "";
}

/* Okno „08:00–12:00", „od 08:00", „do 12:00" nebo prázdno. */
function okno($od, $do): string {
  $od = trim((string)$od); $do = trim((string)$do);
  if ($od !== "" && $do !== "") return $od . "–" . $do;
  if ($od !== "") return "od " . $od;
  if ($do !== "") return "do " . $do;
  return "";
}

/* Den v týdnu česky. */
function den_zkratka(string $datum): string {
  $dny = ["Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
  $d = date_create($datum);
  return $d ? $dny[(int)$d->format("N") - 1] : "";
}

/* --- Vstup -------------------------------------------------------------- */

/* Nejdřív tělo požadavku, potom adresa. Formuláře odesílají POST na adresu,
   která nese s= a id= — kdyby se četlo jen tělo, přišel by se odesláním
   ztratit záznam, kterého se formulář týká. */
function vstup(string $nazev, string $vychozi = ""): string {
  $h = $_POST[$nazev] ?? $_GET[$nazev] ?? $vychozi;
  if (!is_string($h)) return $vychozi;
  return trim($h);
}

function vstup_cislo(string $nazev): ?int {
  $h = vstup($nazev);
  if ($h === "") return null;
  return (int)$h;
}

/* Částka z formuláře — přijme „12 500", „12500,50" i „12500.50". */
function vstup_castka(string $nazev): ?float {
  $h = vstup($nazev);
  $h = str_replace([" ", "\u{00A0}", "Kč"], "", $h);
  $h = str_replace(",", ".", $h);
  if ($h === "" || !is_numeric($h)) return null;
  return round((float)$h, 2);
}

function vstup_datum(string $nazev): ?string {
  $h = vstup($nazev);
  if ($h === "") return null;
  $d = date_create($h);
  return $d ? $d->format("Y-m-d") : null;
}

/* Nezaškrtnuté políčko se neodesílá vůbec — proto se u zápisu kouká jen
   do těla požadavku; vrátit se k adrese by znamenalo, že políčko nejde
   odškrtnout. */
function vstup_ano_ne(string $nazev): int {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    return isset($_POST[$nazev]) && trim((string)$_POST[$nazev]) !== "" ? 1 : 0;
  }
  return vstup($nazev) !== "" ? 1 : 0;
}

/* --- Vzkazy mezi požadavky (POST–redirect–GET) -------------------------- */

function vzkaz(string $druh, string $text): void {
  $_SESSION["vzkazy"][] = ["druh" => $druh, "text" => $text];
}

function vyzvedni_vzkazy(): array {
  $v = $_SESSION["vzkazy"] ?? [];
  unset($_SESSION["vzkazy"]);
  return $v;
}

/* --- Databázové zkratky ------------------------------------------------- */

function dotaz(string $sql, array $parametry = []): PDOStatement {
  global $pdo;
  $p = $pdo->prepare($sql);
  $p->execute($parametry);
  return $p;
}

function radky(string $sql, array $parametry = []): array {
  return dotaz($sql, $parametry)->fetchAll();
}

function radek(string $sql, array $parametry = []): ?array {
  $r = dotaz($sql, $parametry)->fetch();
  return $r === false ? null : $r;
}

function hodnota(string $sql, array $parametry = []) {
  $r = dotaz($sql, $parametry)->fetch(PDO::FETCH_NUM);
  return $r === false ? null : $r[0];
}

/* Vloží řádek a vrátí jeho id. */
function vloz(string $tabulka, array $data): int {
  global $pdo;
  $sloupce = array_keys($data);
  $sql = "INSERT INTO `" . $tabulka . "` (`" . implode("`, `", $sloupce) . "`) VALUES ("
       . implode(", ", array_fill(0, count($sloupce), "?")) . ")";
  dotaz($sql, array_values($data));
  return (int)$pdo->lastInsertId();
}

function uprav(string $tabulka, int $id, array $data): void {
  if (!$data) return;
  $casti = [];
  foreach (array_keys($data) as $s) $casti[] = "`" . $s . "` = ?";
  $sql = "UPDATE `" . $tabulka . "` SET " . implode(", ", $casti) . " WHERE id = ?";
  dotaz($sql, array_merge(array_values($data), [$id]));
}

/* --- Nastavení systému -------------------------------------------------- */

$NASTAVENI = null;

function nastaveni(string $klic, string $vychozi = ""): string {
  global $NASTAVENI;
  if ($NASTAVENI === null) {
    $NASTAVENI = [];
    foreach (radky("SELECT klic, hodnota FROM nastaveni") as $r) {
      $NASTAVENI[$r["klic"]] = (string)$r["hodnota"];
    }
  }
  return $NASTAVENI[$klic] ?? $vychozi;
}

function uloz_nastaveni(string $klic, string $hodnota): void {
  global $NASTAVENI;
  $existuje = hodnota("SELECT COUNT(*) FROM nastaveni WHERE klic = ?", [$klic]);
  if ($existuje) {
    dotaz("UPDATE nastaveni SET hodnota = ? WHERE klic = ?", [$hodnota, $klic]);
  } else {
    dotaz("INSERT INTO nastaveni (klic, hodnota) VALUES (?, ?)", [$klic, $hodnota]);
  }
  /* Vyrovnávací paměť musí jít s hodnotou, jinak by čtení hned po zápisu
     vrátilo starý údaj — na tom stojí posun číselné řady. */
  if (is_array($NASTAVENI)) $NASTAVENI[$klic] = $hodnota;
}

/* --- Protokol ----------------------------------------------------------- */

function zapis_udalost(?int $preprava_id, string $text): void {
  vloz("udalosti", [
    "preprava_id" => $preprava_id,
    "uzivatel_id" => $_SESSION["uzivatel_id"] ?? null,
    "kdy"         => date("Y-m-d H:i:s"),
    "text"        => mb_substr($text, 0, 250),
  ]);
}

/* --- Číselná řada přeprav ----------------------------------------------- */

/* Tvar čísla drží Nastavení, aby řada navázala na to, co firma vystavuje
   dnes. Předpona smí obsahovat {RR} (dvoumístný rok) a {RRRR} (čtyřmístný);
   za ni se doplní pořadové číslo doplněné nulami na danou délku.

   Obsahuje-li předpona rok, začíná řada v lednu znovu od jedničky.
   Bez roku běží řada dál přes přelom roku. */
function slozene_cislo(string $predpona, int $poradi, int $mist): string {
  $predpona = str_replace(["{RRRR}", "{RR}"], [date("Y"), date("y")], $predpona);
  return $predpona . str_pad((string)$poradi, max(1, $mist), "0", STR_PAD_LEFT);
}

function dalsi_cislo(): string {
  global $pdo;

  $predpona = nastaveni("cislovani_predpona", "{RR}-");
  $mist     = (int)nastaveni("cislovani_mist", "4");
  $poradi   = max(1, (int)nastaveni("cislovani_dalsi", "1"));
  $rok      = nastaveni("cislovani_rok", date("Y"));

  /* Přelom roku u řady, která rok v čísle má. */
  $s_rokem = strpos($predpona, "{RR}") !== false || strpos($predpona, "{RRRR}") !== false;
  if ($s_rokem && $rok !== date("Y")) {
    $poradi = 1;
    uloz_nastaveni("cislovani_rok", date("Y"));
  }

  /* Kdyby se číslo přesto sešlo s existujícím (ruční zásah do nastavení,
     import starých dat), posune se dál, dokud je volno. */
  $cislo = slozene_cislo($predpona, $poradi, $mist);
  $pojistka = 0;
  while (hodnota("SELECT COUNT(*) FROM prepravy WHERE cislo = ?", [$cislo]) > 0 && $pojistka < 10000) {
    $poradi++; $pojistka++;
    $cislo = slozene_cislo($predpona, $poradi, $mist);
  }

  uloz_nastaveni("cislovani_dalsi", (string)($poradi + 1));
  return $cislo;
}

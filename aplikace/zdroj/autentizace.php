<?php
/* =========================================================================
   Přihlášení, sezení a ochrana formulářů

   - heslo se ukládá jen jako otisk (password_hash), nikdy čitelně,
   - sezení se po nečinnosti samo ukončí,
   - každý POST musí nést jednorázový token, jinak se odmítne (CSRF),
   - po několika neúspěšných pokusech se adresa na čtvrt hodiny zablokuje.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

function zahaj_sezeni(array $config): void {
  $https = (($_SERVER["HTTPS"] ?? "") !== "" && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");

  session_name("IDISPECINK");
  session_set_cookie_params([
    "lifetime" => 0,
    "path"     => rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/") . "/",
    "httponly" => true,
    "samesite" => "Lax",
    "secure"   => $https,
  ]);
  session_start();

  /* Odhlášení po nečinnosti. */
  $limit = max(5, (int)($config["odhlasit_po"] ?? 480)) * 60;
  if (isset($_SESSION["naposledy"]) && time() - $_SESSION["naposledy"] > $limit) {
    odhlas();
    $_SESSION["vzkazy"][] = ["druh" => "pozor", "text" => "Sezení vypršelo, přihlaste se prosím znovu."];
  }
  $_SESSION["naposledy"] = time();
}

function prihlasen(): bool {
  return !empty($_SESSION["uzivatel_id"]);
}

function uzivatel(): ?array {
  static $u = null;
  if (!prihlasen()) return null;
  if ($u === null) {
    $u = radek("SELECT * FROM uzivatele WHERE id = ? AND aktivni = 1", [$_SESSION["uzivatel_id"]]);
    if (!$u) { odhlas(); return null; }
  }
  return $u;
}

function je_spravce(): bool {
  $u = uzivatel();
  return $u && $u["role"] === "spravce";
}

/* Vidí uživatel cenu zákazníka a marži? Správce vždycky — spravuje
   nastavení i uživatele, skrývat před ním obchodní stranu nemá smysl. */
function role(): string {
  $u = uzivatel();
  return $u ? (string)$u["role"] : "";
}

/* Cena zákazníka a marže: správce vždy, dispečer a účetní podle práva,
   brigádník nikdy. */
function vidi_ceny(): bool {
  $u = uzivatel();
  if (!$u) return false;
  if ($u["role"] === "spravce") return true;
  if ($u["role"] === "brigadnik") return false;
  return (int)$u["vidi_ceny"] === 1;
}

/* Cena dopravce: každý kromě brigádníka. */
function vidi_ceny_dopravce(): bool {
  return prihlasen() && role() !== "brigadnik";
}

/* Zásahy do dispečinku — zakládat a měnit přepravy, trasu, dopravce,
   odkazy: účetní ne. */
function smi_dispecink(): bool {
  return prihlasen() && role() !== "ucetni";
}

/* Fakturace a podklady nesou ceny dopravce: brigádník ne. */
function smi_fakturaci(): bool {
  return prihlasen() && role() !== "brigadnik";
}

function vyzaduj_pravo(bool $ma, string $text): void {
  if (!$ma) {
    vzkaz("chyba", $text);
    presmeruj(odkaz("prehled"));
  }
}

function vyzaduj_ceny(): void {
  if (!vidi_ceny()) {
    vzkaz("chyba", "Na ceny zákazníka a marže nemáte právo.");
    presmeruj(odkaz("prehled"));
  }
}

function vyzaduj_prihlaseni(): void {
  if (!prihlasen() || !uzivatel()) {
    $_SESSION["kam_po_prihlaseni"] = $_SERVER["REQUEST_URI"] ?? "";
    presmeruj(odkaz("prihlaseni"));
  }
}

function vyzaduj_spravce(): void {
  vyzaduj_prihlaseni();
  if (!je_spravce()) {
    vzkaz("chyba", "Na tuhle část má právo jen správce.");
    presmeruj(odkaz("prehled"));
  }
}

function prihlas(array $uzivatel): void {
  session_regenerate_id(true);
  $_SESSION["uzivatel_id"] = (int)$uzivatel["id"];
  $_SESSION["naposledy"]   = time();
  uprav("uzivatele", (int)$uzivatel["id"], ["posledni_prihlaseni" => date("Y-m-d H:i:s")]);
}

function odhlas(): void {
  unset($_SESSION["uzivatel_id"]);
  session_regenerate_id(true);
}

/* --- Jednorázový token proti podvrženému odeslání ----------------------- */

function token(): string {
  if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(16));
  }
  return $_SESSION["token"];
}

function pole_token(): string {
  return "<input type=\"hidden\" name=\"token\" value=\"" . chran(token()) . "\">";
}

function over_token(): void {
  $poslany = (string)($_POST["token"] ?? "");
  if ($poslany === "" || empty($_SESSION["token"])
      || !hash_equals((string)$_SESSION["token"], $poslany)) {
    selhani("Formulář se nepodařilo ověřit",
      "Odeslání nebylo možné ověřit — nejspíš vypršelo sezení nebo byl formulář otevřený příliš dlouho. Vraťte se zpět, obnovte stránku a odešlete znovu.",
      400);
  }
}

/* --- Omezení pokusů o přihlášení ---------------------------------------- */

function adresa_zadatele(): string {
  return (string)($_SERVER["REMOTE_ADDR"] ?? "neznama");
}

function pokusy_vycerpany(int $limit): bool {
  smaz_stare_pokusy();
  $pocet = (int)hodnota("SELECT COUNT(*) FROM pokusy WHERE adresa = ?", [hash("sha256", adresa_zadatele())]);
  return $pocet >= $limit;
}

function zapis_pokus(): void {
  vloz("pokusy", ["adresa" => hash("sha256", adresa_zadatele()), "kdy" => date("Y-m-d H:i:s")]);
}

function smaz_pokusy(): void {
  dotaz("DELETE FROM pokusy WHERE adresa = ?", [hash("sha256", adresa_zadatele())]);
}

function smaz_stare_pokusy(): void {
  dotaz("DELETE FROM pokusy WHERE kdy < ?", [date("Y-m-d H:i:s", time() - 900)]);
}

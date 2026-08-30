<?php
/* =========================================================================
   iDispečink.cz — odeslání formulářů z webu

   Jediný obslužný skript pro oba formuláře (poptávka i registrace
   dopravce). Formulář pošle běžný POST — funguje proto i s vypnutým
   JavaScriptem. Po úspěchu přesměruje na odeslano.html (vzor
   POST–redirect–GET, obnovení stránky zprávu nepošle podruhé).

   Ochrana proti robotům: skryté pole „www" (honeypot). Člověk ho nevidí
   a nevyplní; robot ano — taková zpráva se zahodí, ale odpověď vypadá
   jako úspěch, aby robot nezkoušel dál.

   POZOR na doručitelnost: skript odesílá poštu ze serveru VAS Hostingu
   s adresou v hlavičce From na doméně idispecink.cz. Aby zprávy
   nekončily ve spamu, musí SPF záznam domény zahrnovat servery VAS
   Hostingu — nastavuje se v DNS, podrobnosti v README.
   ========================================================================= */

declare(strict_types=1);
mb_internal_encoding("UTF-8");

$PRIJEMCE   = "doprava@idispecink.cz";
$ODESILATEL = "web@idispecink.cz";   /* jen hlavička From — schránka existovat nemusí */

/* Popisky polí drží tady, ne v HTML — server nevidí data-popisek.
   Pole, které tu není, se do e-mailu nedostane. */
$FORMULARE = [
  "poptavka" => [
    "predmet" => "Poptávka přepravy z webu",
    "zpet"    => "poptat-prepravu.html",
    "pole"    => [
      "jmeno"   => "Jméno",
      "firma"   => "Firma",
      "email"   => "E-mail",
      "telefon" => "Telefon",
      "typ"     => "Předmět poptávky",
      "odkud"   => "Místo nakládky",
      "kam"     => "Místo vykládky",
      "termin"  => "Termín nakládky",
      "naklad"  => "Náklad",
      "zprava"  => "Zpráva",
    ],
    "povinna" => ["jmeno", "email", "zprava"],
  ],
  "registrace" => [
    "predmet" => "Registrace dopravce z webu",
    "zpet"    => "pro-dopravce.html",
    "pole"    => [
      "firma"   => "Firma",
      "ico"     => "IČO",
      "osoba"   => "Kontaktní osoba",
      "telefon" => "Telefon",
      "email"   => "E-mail",
      "vozy"    => "Počet vozidel",
      "typ"     => "Převažující typ vozidla",
      "oblast"  => "Oblast působení",
      "zprava"  => "Poznámka",
    ],
    "povinna" => ["firma", "ico", "osoba", "telefon", "email"],
  ],
];

function presmeruj(string $kam): void {
  header("Location: " . $kam, true, 303);
  exit;
}

function chybova_stranka(string $nadpis, string $text, string $zpet): void {
  http_response_code(500);
  header("Content-Type: text/html; charset=utf-8");
  $n = htmlspecialchars($nadpis, ENT_QUOTES);
  $t = htmlspecialchars($text, ENT_QUOTES);
  $z = htmlspecialchars($zpet, ENT_QUOTES);
  echo "<!doctype html><html lang=\"cs\"><head><meta charset=\"utf-8\">"
    . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
    . "<meta name=\"robots\" content=\"noindex\">"
    . "<title>{$n} — iDispečink.cz</title>"
    . "<link rel=\"stylesheet\" href=\"assets/css/firemni-styl.css\"></head><body>"
    . "<main><section class=\"hero jednoduchy\"><div class=\"obal\">"
    . "<span class=\"nadpis-stitek\">Formulář</span><h1>{$n}</h1>"
    . "<p class=\"uvod\">{$t} Napište nám prosím přímo na "
    . "<a href=\"mailto:doprava@idispecink.cz\">doprava@idispecink.cz</a> "
    . "nebo zavolejte na <a href=\"tel:+420734580243\">+420 734 580 243</a>.</p>"
    . "<div class=\"tlacitka\"><a class=\"tlacitko\" href=\"{$z}\">Zpět na formulář</a></div>"
    . "</div></section></main></body></html>";
  exit;
}

/* --- Vstupní kontroly --------------------------------------------------- */

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
  presmeruj("index.html");
}

$druh = $_POST["formular"] ?? "";
if (!isset($FORMULARE[$druh])) {
  presmeruj("index.html");
}
$formular = $FORMULARE[$druh];

/* Honeypot: vyplněné skryté pole = robot. Tvářit se jako úspěch. */
if (trim((string)($_POST["www"] ?? "")) !== "") {
  presmeruj("odeslano.html");
}

/* --- Sběr a kontrola polí ----------------------------------------------- */

$radky = [];
$hodnoty = [];
foreach ($formular["pole"] as $nazev => $popisek) {
  $hodnota = trim((string)($_POST[$nazev] ?? ""));
  $hodnota = mb_substr($hodnota, 0, $nazev === "zprava" ? 5000 : 500);
  if ($hodnota === "") continue;
  $hodnoty[$nazev] = $hodnota;
  $radky[] = $popisek . ": " . $hodnota;
}

foreach ($formular["povinna"] as $nazev) {
  if (!isset($hodnoty[$nazev])) {
    chybova_stranka(
      "Chybí povinné údaje",
      "Formulář se odeslal bez povinných polí, zpráva proto nebyla předána.",
      $formular["zpet"]
    );
  }
}

$radky[] = "";
$radky[] = "— Odesláno z webu idispecink.cz";
$telo = implode("\n", $radky);

/* --- Sestavení a odeslání e-mailu --------------------------------------- */

$hlavicky = [
  "From: " . $ODESILATEL,
  "MIME-Version: 1.0",
  "Content-Type: text/plain; charset=UTF-8",
  "Content-Transfer-Encoding: 8bit",
];

/* Adresu návštěvníka do Reply-To jen po validaci — ta zároveň vylučuje
   zavlečení dalších hlaviček přes zalomení řádku. */
$email = $hodnoty["email"] ?? "";
if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $hlavicky[] = "Reply-To: " . $email;
}

$predmet = mb_encode_mimeheader($formular["predmet"], "UTF-8", "B");

$odeslano = mail($PRIJEMCE, $predmet, $telo, implode("\r\n", $hlavicky));

if (!$odeslano) {
  chybova_stranka(
    "Odeslání se nepodařilo",
    "Zprávu se nepodařilo předat poštovnímu serveru.",
    $formular["zpet"]
  );
}

presmeruj("odeslano.html");

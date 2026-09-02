<?php
/* =========================================================================
   Pošta — odesílání e-mailů z aplikace

   Stejná cesta jako formuláře webu: PHP mail() na hostingu. Aby zprávy
   nekončily ve spamu, musí SPF záznam domény zahrnovat servery hostingu
   (viz README). Odesílatel je adresa z Nastavení, odpovědi chodí tomu,
   kdo zprávu poslal.

   Objednávka jde dopravci celá v těle zprávy — textově i jako HTML —
   a pod ní je odkaz, kde ji potvrdí a nahraje doklady. PDF se nedělá:
   aplikace nemá žádné knihovny a mít je nemá.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

/* Adresa bez zalomení řádku — zalomení v hlavičce by umožnilo podstrčit
   další hlavičky, proto se každá adresa ověřuje celá. */
function platny_email(string $adresa): bool {
  $adresa = trim($adresa);
  return $adresa !== "" && filter_var($adresa, FILTER_VALIDATE_EMAIL) !== false
    && !preg_match('/[\r\n]/', $adresa);
}

/* Pošle zprávu s textovou i HTML verzí. Vrací true při předání poštovnímu
   serveru; doručení tím zaručené není. */
function posli_email(string $komu, string $predmet, string $text, string $html, string $odpovedet = "", string $kopie = ""): bool {
  if (!platny_email($komu)) return false;

  $od = trim(nastaveni("email_odesilatel", "web@idispecink.cz"));
  if (!platny_email($od)) $od = "web@idispecink.cz";

  $hranice = "==idispecink-" . bin2hex(random_bytes(8)) . "==";
  $hlavicky = [
    "From: " . $od,
    "MIME-Version: 1.0",
    "Content-Type: multipart/alternative; boundary=\"" . $hranice . "\"",
    "X-Mailer: iDispecink provozni system",
  ];
  if (platny_email($odpovedet)) $hlavicky[] = "Reply-To: " . trim($odpovedet);
  if (platny_email($kopie))     $hlavicky[] = "Bcc: " . trim($kopie);

  $telo = "--" . $hranice . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($text)) . "\r\n"
    . "--" . $hranice . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($html)) . "\r\n"
    . "--" . $hranice . "--\r\n";

  $predmet = preg_replace('/[\r\n]+/', " ", $predmet) ?? "";
  return mail(trim($komu), mb_encode_mimeheader($predmet, "UTF-8", "B"), $telo, implode("\r\n", $hlavicky));
}

/* --- Objednávka přepravy jako zpráva ------------------------------------ */

/* Společné údaje pro textovou i HTML verzi. */
function objednavka_udaje(array $p): array {
  $body = body_prepravy((int)$p["id"]);
  $radky = [];
  foreach ($body as $b) {
    $r = (count($body) > 2 ? (int)$b["poradi"] . ". " : "") . nazev_druhu($b["druh"]) . ": " . ($b["misto"] ?: "—");
    if ($b["adresa"]) $r .= ", " . $b["adresa"];
    $termin = trim(datum($b["datum"]) . " " . okno($b["od"], $b["do"]));
    if ($termin !== "—") $r .= " · " . $termin;
    if ($b["kontakt"]) $r .= " · " . $b["kontakt"];
    if ($b["poznamka"]) $r .= " · " . $b["poznamka"];
    $radky[] = $r;
  }
  $naklad = array_filter([
    $p["zbozi"] ?: null,
    $p["hmotnost"] ? cislo($p["hmotnost"]) . " kg" : null,
    $p["palet"] ? (int)$p["palet"] . " palet" : null,
    $p["ldm"] ? cislo($p["ldm"], 1) . " LDM" : null,
  ]);
  return [
    "cislo"    => (string)$p["cislo"],
    "trasa"    => popis_trasy($body),
    "body"     => $radky,
    "naklad"   => implode(" · ", $naklad),
    "vozidlo"  => nazev_typu_vozidla($p["typ_vozidla"]) . ($p["spz"] ? ", SPZ " . $p["spz"] : "") . ($p["pozadavky"] ? " · " . $p["pozadavky"] : ""),
    "ridic"    => trim((string)$p["ridic_jmeno"] . " " . (string)$p["ridic_telefon"]),
    "cena"     => castka($p["cena_dopravce"]) . " bez DPH",
    "pokyny"   => trim((string)$p["poznamka_dopravci"]),
    "podminky" => trim(nastaveni("podminky")),
    "firma"    => nastaveni("firma_nazev"),
    "firma_adresa" => trim(nastaveni("firma_ulice") . ", " . nastaveni("firma_psc") . " " . nastaveni("firma_mesto"), ", "),
    "firma_ico"    => nastaveni("firma_ico"),
    "firma_dic"    => nastaveni("firma_dic"),
    "firma_telefon" => nastaveni("firma_telefon"),
    "firma_email"   => nastaveni("firma_email"),
    "dopravce" => (string)($p["d_nazev"] ?? ""),
  ];
}

function objednavka_text(array $u, string $uvod, string $odkaz): string {
  $r = [];
  if ($uvod !== "") { $r[] = $uvod; $r[] = ""; }
  $r[] = "OBJEDNÁVKA PŘEPRAVY " . $u["cislo"];
  $r[] = $u["trasa"];
  $r[] = "";
  $r[] = "Objednatel: " . $u["firma"] . ", " . $u["firma_adresa"] . ", IČO " . $u["firma_ico"] . ($u["firma_dic"] ? ", DIČ " . $u["firma_dic"] : "");
  $r[] = "Dopravce: " . $u["dopravce"];
  $r[] = "";
  $r[] = "TRASA";
  foreach ($u["body"] as $b) $r[] = "  " . $b;
  $r[] = "";
  if ($u["naklad"] !== "") $r[] = "Zboží: " . $u["naklad"];
  $r[] = "Vozidlo: " . $u["vozidlo"];
  if ($u["ridic"] !== "") $r[] = "Řidič: " . $u["ridic"];
  $r[] = "Sjednaná cena: " . $u["cena"];
  if ($u["pokyny"] !== "") { $r[] = ""; $r[] = "POKYNY"; $r[] = $u["pokyny"]; }
  if ($u["podminky"] !== "") { $r[] = ""; $r[] = "PODMÍNKY"; $r[] = $u["podminky"]; }
  if ($odkaz !== "") {
    $r[] = "";
    $r[] = "Objednávku potvrďte a doklady nahrajte zde:";
    $r[] = $odkaz;
    $r[] = "Odkaz platí měsíc po vykládce. Nepřeposílejte ho — kdo ho má, vidí objednávku.";
  }
  $r[] = "";
  $r[] = "— " . $u["firma"] . " · " . $u["firma_telefon"] . " · " . $u["firma_email"];
  return implode("\n", $r);
}

/* HTML pro poštovní klienty: jen vložené styly, žádné externí CSS ani
   obrázky — ty klienti nenačítají. Barvy firemní, plocha, linka, text. */
function objednavka_html(array $u, string $uvod, string $odkaz): string {
  $c = fn($t) => nl2br(chran($t));
  $radek = fn(string $k, string $v) => '<tr><td style="padding:6px 10px;border-top:1px solid #ddd;color:#555;white-space:nowrap;vertical-align:top">' . chran($k) . '</td><td style="padding:6px 10px;border-top:1px solid #ddd;color:#111;vertical-align:top">' . $v . '</td></tr>';

  $body = "";
  foreach ($u["body"] as $b) $body .= '<div style="margin:0 0 4px">' . chran($b) . '</div>';

  $h = '<!doctype html><html lang="cs"><body style="margin:0;padding:20px;background:#f3f1ec;font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#111">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #ddd;border-top:4px solid #F0B41E;padding:24px">';
  if ($uvod !== "") $h .= '<p style="margin:0 0 18px">' . $c($uvod) . '</p>';
  $h .= '<p style="margin:0;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>Objednávka přepravy</b></p>'
    . '<p style="margin:2px 0 14px;font-size:22px;font-weight:700;font-family:Consolas,Menlo,monospace">' . chran($u["cislo"]) . '</p>'
    . '<p style="margin:0 0 18px;font-size:16px"><b>' . chran($u["trasa"]) . '</b></p>'
    . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
    . $radek("Objednatel", chran($u["firma"]) . '<br>' . chran($u["firma_adresa"]) . '<br>IČO ' . chran($u["firma_ico"]) . ($u["firma_dic"] ? ', DIČ ' . chran($u["firma_dic"]) : ''))
    . $radek("Dopravce", chran($u["dopravce"]))
    . $radek("Trasa", $body)
    . ($u["naklad"] !== "" ? $radek("Zboží", chran($u["naklad"])) : "")
    . $radek("Vozidlo", chran($u["vozidlo"]))
    . ($u["ridic"] !== "" ? $radek("Řidič", chran($u["ridic"])) : "")
    . $radek("Sjednaná cena", '<b>' . chran($u["cena"]) . '</b>')
    . '</table>';
  if ($u["pokyny"] !== "") $h .= '<p style="margin:18px 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>Pokyny</b></p><p style="margin:0">' . $c($u["pokyny"]) . '</p>';
  if ($u["podminky"] !== "") $h .= '<p style="margin:18px 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>Podmínky</b></p><p style="margin:0;font-size:13px;color:#333">' . $c($u["podminky"]) . '</p>';
  if ($odkaz !== "") {
    $h .= '<p style="margin:22px 0 0"><a href="' . chran($odkaz) . '" style="display:inline-block;background:#F0B41E;color:#343F41;font-weight:700;padding:12px 20px;text-decoration:none">Potvrdit objednávku a nahrát doklady</a></p>'
      . '<p style="margin:8px 0 0;font-size:12px;color:#555">Odkaz platí měsíc po vykládce. Nepřeposílejte ho — kdo ho má, vidí objednávku.<br>' . chran($odkaz) . '</p>';
  }
  $h .= '<p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #ddd;font-size:13px;color:#555">' . chran($u["firma"]) . ' · ' . chran($u["firma_telefon"]) . ' · ' . chran($u["firma_email"]) . '</p>'
    . '</div></body></html>';
  return $h;
}

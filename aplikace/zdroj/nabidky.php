<?php
/* =========================================================================
   Nabídky — stupeň před zakázkou

   Poptávka přijde, dispečer ji ocení a pošle nabídku; z přijaté vznikne
   jedním kliknutím přeprava. U neúspěšné se zapíše důvod, aby bylo vidět,
   proč nabídky neprocházejí. Platnost nabídky se nesleduje — zadavatel ji
   z rozsahu vyřadil. Cena zákazníka je jádro nabídky, proto modul vidí
   jen ten, kdo na ceny má právo.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const STAVY_NABIDKY = ["otevrena" => "Otevřená", "prijata" => "Přijatá", "neprosla" => "Neprošla"];
const STAVY_NABIDKY_BARVA = ["otevrena" => "bezi", "prijata" => "hotovo", "neprosla" => "zrus"];
const DUVODY_NABIDKY = [
  "drahe"    => "Drahé",
  "pozde"    => "Pozdě",
  "bez_vozu" => "Neměli jsme vůz",
  "zrusil"   => "Zákazník zrušil",
  "jiny"     => "Jiný důvod",
];

function stitek_nabidky(?string $stav): string {
  return "<span class=\"stitek stitek-" . chran(STAVY_NABIDKY_BARVA[$stav] ?? "ceka") . "\">" . chran(STAVY_NABIDKY[$stav] ?? "—") . "</span>";
}

/* Číslo nabídky: stejný tvar jako přepravy s předponou N a vlastním
   počítadlem, aby se řady nepletly. */
function dalsi_cislo_nabidky(): string {
  $predpona = "N" . nastaveni("cislovani_predpona", "{RR}-");
  $mist     = (int)nastaveni("cislovani_mist", "4");
  $poradi   = max(1, (int)nastaveni("nabidky_dalsi", "1"));
  $rok      = nastaveni("nabidky_rok");
  $s_rokem  = strpos($predpona, "{RR}") !== false || strpos($predpona, "{RRRR}") !== false;
  if ($rok === "") {
    uloz_nastaveni("nabidky_rok", date("Y"));
  } elseif ($s_rokem && $rok !== date("Y")) {
    $poradi = 1;
    uloz_nastaveni("nabidky_rok", date("Y"));
  }
  $cislo = slozene_cislo($predpona, $poradi, $mist);
  $pojistka = 0;
  while (hodnota("SELECT COUNT(*) FROM nabidky WHERE cislo = ?", [$cislo]) > 0 && $pojistka < 10000) {
    $poradi++; $pojistka++;
    $cislo = slozene_cislo($predpona, $poradi, $mist);
  }
  uloz_nastaveni("nabidky_dalsi", (string)($poradi + 1));
  return $cislo;
}

/* Z přijaté nabídky vznikne přeprava se dvěma body. Odhad nákladu z nabídky
   se do sjednané ceny dopravce nepíše — ta se teprve domluví. */
function zaloz_prepravu_z_nabidky(array $n): int {
  $ted = date("Y-m-d H:i:s");
  $poznamka = "Z nabídky " . $n["cislo"];
  if ($n["cena_dopravce"] !== null) $poznamka .= ", odhad nákladu " . castka($n["cena_dopravce"]);
  if (trim((string)$n["poznamka"]) !== "") $poznamka .= "\n" . trim((string)$n["poznamka"]);

  $id = vloz("prepravy", [
    "cislo"         => dalsi_cislo(),
    "stav"          => "nova",
    "sablona"       => 0,
    "zakaznik_id"   => $n["zakaznik_id"] ?: null,
    "ref_zakaznika" => (string)$n["ref_zakaznika"],
    "zbozi"         => (string)$n["zbozi"],
    "hmotnost"      => $n["hmotnost"],
    "palet"         => $n["palet"],
    "ldm"           => $n["ldm"],
    "km"            => $n["km"],
    "typ_vozidla"   => (string)($n["typ_vozidla"] ?: "plachta"),
    "pozadavky"     => (string)$n["pozadavky"],
    "cena_zakaznik" => $n["cena"],
    "doklady"       => "ceka",
    "poznamka"      => $poznamka,
    "nabidka_id"    => (int)$n["id"],
    "vytvoreno"     => $ted,
    "upraveno"      => $ted,
    "vytvoril"      => (int)(uzivatel()["id"] ?? 0),
  ]);
  zaloz_body_z_poli($id, [
    "nakladka_misto" => $n["nakladka_misto"], "nakladka_adresa" => $n["nakladka_adresa"],
    "nakladka_datum" => $n["nakladka_datum"], "nakladka_od" => $n["nakladka_od"], "nakladka_do" => $n["nakladka_do"],
    "vykladka_misto" => $n["vykladka_misto"], "vykladka_adresa" => $n["vykladka_adresa"],
    "vykladka_datum" => $n["vykladka_datum"], "vykladka_od" => $n["vykladka_od"], "vykladka_do" => $n["vykladka_do"],
    "stav" => "nova",
  ]);
  prepocitej_trasu($id);
  return $id;
}

/* --- Nabídka jako zpráva zákazníkovi ------------------------------------ */

function nabidka_udaje(array $n): array {
  $dph = (float)str_replace(",", ".", nastaveni("dph_sazba", "21"));
  $termin = function (?string $datum, $od, $do): string {
    $t = trim(datum($datum) . " " . okno($od, $do));
    return $t === "—" ? "" : $t;
  };
  $naklad = array_filter([
    $n["zbozi"] ?: null,
    $n["hmotnost"] ? cislo($n["hmotnost"]) . " kg" : null,
    $n["palet"] ? (int)$n["palet"] . " palet" : null,
    $n["ldm"] ? cislo($n["ldm"], 1) . " LDM" : null,
  ]);
  $bod = function (string $misto, string $adresa, string $termin): string {
    $r = $misto !== "" ? $misto : "—";
    if ($adresa !== "") $r .= ", " . $adresa;
    if ($termin !== "") $r .= " · " . $termin;
    return $r;
  };
  return [
    "cislo"     => (string)$n["cislo"],
    "trasa"     => ($n["nakladka_misto"] ?: "?") . " → " . ($n["vykladka_misto"] ?: "?"),
    "nakladka"  => $bod((string)$n["nakladka_misto"], (string)$n["nakladka_adresa"], $termin($n["nakladka_datum"], $n["nakladka_od"], $n["nakladka_do"])),
    "vykladka"  => $bod((string)$n["vykladka_misto"], (string)$n["vykladka_adresa"], $termin($n["vykladka_datum"], $n["vykladka_od"], $n["vykladka_do"])),
    "naklad"    => implode(" · ", $naklad),
    "vozidlo"   => nazev_typu_vozidla($n["typ_vozidla"]) . ($n["pozadavky"] ? " · " . $n["pozadavky"] : ""),
    "km"        => $n["km"] ? (int)$n["km"] . " km" : "",
    "cena"      => $n["cena"] === null ? "—" : castka($n["cena"]) . " bez DPH",
    "cena_s_dph" => $n["cena"] === null ? "" : castka(round((float)$n["cena"] * (1 + $dph / 100), 2)) . " s DPH " . cislo($dph, 0) . " %",
    "text"      => trim((string)$n["text_pro_zakaznika"]),
    "zakaznik"  => (string)($n["zakaznik_nazev"] ?? ""),
    "kontakt"   => trim((string)$n["kontakt_jmeno"]),
    "reference" => (string)$n["ref_zakaznika"],
    "firma"     => nastaveni("firma_nazev"),
    "firma_adresa" => trim(nastaveni("firma_ulice") . ", " . nastaveni("firma_psc") . " " . nastaveni("firma_mesto"), ", "),
    "firma_ico"    => nastaveni("firma_ico"),
    "firma_dic"    => nastaveni("firma_dic"),
    "firma_telefon" => nastaveni("firma_telefon"),
    "firma_email"   => nastaveni("firma_email"),
  ];
}

function nabidka_text(array $u, string $uvod): string {
  $r = [];
  if ($uvod !== "") { $r[] = $uvod; $r[] = ""; }
  $r[] = "CENOVÁ NABÍDKA " . $u["cislo"];
  $r[] = $u["trasa"];
  $r[] = "";
  $r[] = "Dodavatel: " . $u["firma"] . ", " . $u["firma_adresa"] . ", IČO " . $u["firma_ico"] . ($u["firma_dic"] ? ", DIČ " . $u["firma_dic"] : "");
  if ($u["zakaznik"] !== "") $r[] = "Zákazník: " . $u["zakaznik"] . ($u["kontakt"] !== "" ? ", " . $u["kontakt"] : "");
  if ($u["reference"] !== "") $r[] = "Vaše reference: " . $u["reference"];
  $r[] = "";
  $r[] = "Nakládka: " . $u["nakladka"];
  $r[] = "Vykládka: " . $u["vykladka"];
  if ($u["naklad"] !== "") $r[] = "Zboží: " . $u["naklad"];
  $r[] = "Vozidlo: " . $u["vozidlo"];
  if ($u["km"] !== "") $r[] = "Vzdálenost: " . $u["km"];
  $r[] = "";
  $r[] = "CENA: " . $u["cena"] . ($u["cena_s_dph"] !== "" ? " (" . $u["cena_s_dph"] . ")" : "");
  if ($u["text"] !== "") { $r[] = ""; $r[] = $u["text"]; }
  $r[] = "";
  $r[] = "Přijetí nabídky prosím potvrďte odpovědí na tento e-mail.";
  $r[] = "";
  $r[] = "— " . $u["firma"] . " · " . $u["firma_telefon"] . " · " . $u["firma_email"];
  return implode("\n", $r);
}

/* HTML pro poštovní klienty — jen vložené styly, jako u objednávky. */
function nabidka_html(array $u, string $uvod): string {
  $c = fn($t) => nl2br(chran($t));
  $radek = fn(string $k, string $v) => '<tr><td style="padding:6px 10px;border-top:1px solid #ddd;color:#555;white-space:nowrap;vertical-align:top">' . chran($k) . '</td><td style="padding:6px 10px;border-top:1px solid #ddd;color:#111;vertical-align:top">' . $v . '</td></tr>';
  $h = '<!doctype html><html lang="cs"><body style="margin:0;padding:20px;background:#f3f1ec;font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#111">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #ddd;border-top:4px solid #F0B41E;padding:24px">';
  if ($uvod !== "") $h .= '<p style="margin:0 0 18px">' . $c($uvod) . '</p>';
  $h .= '<p style="margin:0;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#7a6a33"><b>Cenová nabídka</b></p>'
    . '<p style="margin:2px 0 14px;font-size:22px;font-weight:700;font-family:Consolas,Menlo,monospace">' . chran($u["cislo"]) . '</p>'
    . '<p style="margin:0 0 18px;font-size:16px"><b>' . chran($u["trasa"]) . '</b></p>'
    . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
    . $radek("Dodavatel", chran($u["firma"]) . '<br>' . chran($u["firma_adresa"]) . '<br>IČO ' . chran($u["firma_ico"]) . ($u["firma_dic"] ? ', DIČ ' . chran($u["firma_dic"]) : ''))
    . ($u["zakaznik"] !== "" ? $radek("Zákazník", chran($u["zakaznik"]) . ($u["kontakt"] !== "" ? ', ' . chran($u["kontakt"]) : '')) : "")
    . ($u["reference"] !== "" ? $radek("Vaše reference", chran($u["reference"])) : "")
    . $radek("Nakládka", chran($u["nakladka"]))
    . $radek("Vykládka", chran($u["vykladka"]))
    . ($u["naklad"] !== "" ? $radek("Zboží", chran($u["naklad"])) : "")
    . $radek("Vozidlo", chran($u["vozidlo"]))
    . ($u["km"] !== "" ? $radek("Vzdálenost", chran($u["km"])) : "")
    . $radek("Cena", '<b>' . chran($u["cena"]) . '</b>' . ($u["cena_s_dph"] !== "" ? '<br><span style="color:#555">' . chran($u["cena_s_dph"]) . '</span>' : ''))
    . '</table>';
  if ($u["text"] !== "") $h .= '<p style="margin:18px 0 0">' . $c($u["text"]) . '</p>';
  $h .= '<p style="margin:18px 0 0;font-size:13px;color:#333">Přijetí nabídky prosím potvrďte odpovědí na tento e-mail.</p>'
    . '<p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #ddd;font-size:13px;color:#555">' . chran($u["firma"]) . ' · ' . chran($u["firma_telefon"]) . ' · ' . chran($u["firma_email"]) . '</p>'
    . '</div></body></html>';
  return $h;
}

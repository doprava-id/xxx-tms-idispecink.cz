<?php
/* =========================================================================
   Druhý faktor — jednorázové kódy TOTP (RFC 6238) bez knihoven

   Systém je na veřejné adrese a nese osobní údaje, heslo samotné je málo.
   Kód generuje běžná aplikace v telefonu (Google Authenticator, Microsoft
   Authenticator, Aegis…); tajemství se zadá ručně nebo přes adresu
   otpauth://, QR kód se nekreslí — aplikace nemá čím.
   ========================================================================= */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

const TOTP_KROK_VTERIN = 30;
const BASE32_ABECEDA = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

function base32_koduj(string $data): string {
  $bity = ""; $ven = "";
  foreach (str_split($data) as $znak) $bity .= str_pad(decbin(ord($znak)), 8, "0", STR_PAD_LEFT);
  foreach (str_split($bity, 5) as $kus) $ven .= BASE32_ABECEDA[bindec(str_pad($kus, 5, "0", STR_PAD_RIGHT))];
  return $ven;
}

function base32_dekoduj(string $text): string {
  $text = strtoupper(preg_replace('/[^A-Za-z2-7]/', "", $text));
  $bity = ""; $ven = "";
  for ($i = 0; $i < strlen($text); $i++) {
    $bity .= str_pad(decbin(strpos(BASE32_ABECEDA, $text[$i])), 5, "0", STR_PAD_LEFT);
  }
  foreach (str_split($bity, 8) as $kus) {
    if (strlen($kus) === 8) $ven .= chr(bindec($kus));
  }
  return $ven;
}

/* Nové tajemství: 160 bitů náhody, zapsané v base32 (32 znaků). */
function totp_nove_tajemstvi(): string {
  return base32_koduj(random_bytes(20));
}

/* Kód pro daný krok (číslo půlminuty od roku 1970). */
function totp_kod(string $tajemstvi, int $krok): string {
  $data = pack("N", 0) . pack("N", $krok);
  $otisk = hash_hmac("sha1", $data, base32_dekoduj($tajemstvi), true);
  $posun = ord($otisk[19]) & 0x0F;
  $cislo = ((ord($otisk[$posun]) & 0x7F) << 24)
         | (ord($otisk[$posun + 1]) << 16)
         | (ord($otisk[$posun + 2]) << 8)
         | ord($otisk[$posun + 3]);
  return str_pad((string)($cislo % 1000000), 6, "0", STR_PAD_LEFT);
}

/* Ověří kód s tolerancí jedné půlminuty na obě strany. Krok, který už byl
   použitý, neprojde podruhé — odposlechnutý kód tak nejde přehrát. */
function totp_over(string $tajemstvi, string $kod, int $posledni_krok, ?int &$pouzity_krok = null): bool {
  $kod = preg_replace('/\s+/', "", $kod);
  if (!preg_match('/^\d{6}$/', $kod)) return false;
  $ted = (int)floor(time() / TOTP_KROK_VTERIN);
  foreach ([0, -1, 1] as $posun) {
    $krok = $ted + $posun;
    if ($krok <= $posledni_krok) continue;
    if (hash_equals(totp_kod($tajemstvi, $krok), $kod)) { $pouzity_krok = $krok; return true; }
  }
  return false;
}

/* Adresa pro aplikaci v telefonu; tajemství jde zadat i ručně. */
function totp_adresa(string $tajemstvi, string $email): string {
  return "otpauth://totp/" . rawurlencode("iDispecink:" . $email)
    . "?secret=" . $tajemstvi . "&issuer=" . rawurlencode("iDispecink") . "&algorithm=SHA1&digits=6&period=" . TOTP_KROK_VTERIN;
}

/* Tajemství po čtyřech znacích, ať se opisuje bez chyb. */
function totp_tajemstvi_citelne(string $tajemstvi): string {
  return implode(" ", str_split($tajemstvi, 4));
}

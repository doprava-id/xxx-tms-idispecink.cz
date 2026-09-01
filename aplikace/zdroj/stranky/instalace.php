<?php
/* První spuštění — založení správce a údajů firmy.
   Stránka je dostupná jen dokud v databázi není žádný aktivní uživatel;
   pak ji směrovač už nepustí. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$chyby = [];
$jmeno = vstup("jmeno");
$email = vstup("email");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  over_token();

  $heslo  = (string)($_POST["heslo"] ?? "");
  $znovu  = (string)($_POST["heslo_znovu"] ?? "");

  if ($jmeno === "") $chyby[] = "Vyplňte jméno.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $chyby[] = "Vyplňte platný e-mail.";
  if (mb_strlen($heslo) < 10) $chyby[] = "Heslo musí mít aspoň 10 znaků.";
  if ($heslo !== $znovu) $chyby[] = "Hesla se neshodují.";

  if (!$chyby) {
    $id = vloz("uzivatele", [
      "jmeno"     => $jmeno,
      "email"     => mb_strtolower($email),
      "heslo"     => password_hash($heslo, PASSWORD_DEFAULT),
      "role"      => "spravce",
      "vidi_ceny" => 1,
      "aktivni"   => 1,
      "vytvoreno" => date("Y-m-d H:i:s"),
    ]);

    /* Údaje firmy z veřejného rejstříku — stejné, jaké uvádí web.
       V Nastavení se dají kdykoliv upravit. */
    $zaklad = [
      "firma_nazev"   => "iDispečink.cz s.r.o.",
      "firma_ulice"   => "Příčná 1892/4",
      "firma_mesto"   => "Praha 1 – Nové Město",
      "firma_psc"     => "110 00",
      "firma_ico"     => "23359765",
      "firma_dic"     => "CZ23359765",
      "firma_telefon" => "+420 734 580 243",
      "firma_email"   => "doprava@idispecink.cz",
      "firma_web"     => "idispecink.cz",
      "firma_zapis"   => "C 425222 vedená u Městského soudu v Praze",
      /* Číselná řada — VÝCHOZÍ TVAR, který má navázat na to, co firma
         vystavuje dnes. Doplňuje se v Nastavení, dokud se nezmění,
         upozorňuje na sebe stránka Nastavení. */
      "cislovani_predpona" => "{RR}-",
      "cislovani_dalsi"    => "1",
      "cislovani_mist"     => "4",
      "cislovani_rok"      => date("Y"),
      "cislovani_potvrzeno" => "",
      "podminky"      => "",
    ];
    foreach ($zaklad as $k => $h) uloz_nastaveni($k, $h);

    prihlas(["id" => $id]);
    zapis_udalost(null, "Systém nainstalován, založen správce " . $jmeno);
    vzkaz("ok", "Systém je připravený. Doplňte prosím v Nastavení podmínky objednávky přepravy.");
    presmeruj(odkaz("prehled"));
  }
}

hlava("Instalace", "");
?>
<div class="app-uzka">
  <span class="nadpis-stitek">Provozní systém</span>
  <h1>První spuštění</h1>
  <p class="app-perex">V databázi zatím není žádný uživatel. Založte účet správce —
    dalších uživatelů přidáte kolik potřebujete v Nastavení.</p>

  <?php foreach ($chyby as $ch): ?>
    <p class="vzkaz vzkaz-chyba"><?= chran($ch) ?></p>
  <?php endforeach; ?>

  <form method="post" action="<?= chran(odkaz("instalace")) ?>" class="formular">
    <?= pole_token() ?>
    <div class="pole">
      <label for="jmeno">Jméno a příjmení</label>
      <input type="text" id="jmeno" name="jmeno" value="<?= chran($jmeno) ?>" required autocomplete="name">
    </div>
    <div class="pole">
      <label for="email">E-mail <span class="napoveda">— slouží jako přihlašovací jméno</span></label>
      <input type="email" id="email" name="email" value="<?= chran($email) ?>" required autocomplete="username">
    </div>
    <div class="pole-radek">
      <div class="pole">
        <label for="heslo">Heslo <span class="napoveda">— aspoň 10 znaků</span></label>
        <input type="password" id="heslo" name="heslo" required minlength="10" autocomplete="new-password">
      </div>
      <div class="pole">
        <label for="heslo_znovu">Heslo znovu</label>
        <input type="password" id="heslo_znovu" name="heslo_znovu" required minlength="10" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="tlacitko">Založit správce</button>
    <p class="formular-poznamka">Heslo se ukládá jen jako otisk, přečíst se nedá.
      Zapomenuté heslo proto nelze obnovit — jde jen nastavit nové.</p>
  </form>
</div>
<?php
pata();

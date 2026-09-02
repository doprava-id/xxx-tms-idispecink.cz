<?php
/* Můj účet — změna hesla a druhý faktor. Každý si spravuje sám; správce
   umí druhý faktor jen vypnout, když někdo přijde o telefon. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

$ja = uzivatel();
$u = radek("SELECT * FROM uzivatele WHERE id = ?", [(int)$ja["id"]]);
$ma_2f = trim((string)($u["totp_tajemstvi"] ?? "")) !== "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($akce === "heslo") {
    $stare = (string)($_POST["stare"] ?? "");
    $nove  = (string)($_POST["nove"] ?? "");
    $znovu = (string)($_POST["znovu"] ?? "");
    if (!password_verify($stare, (string)$u["heslo"])) {
      vzkaz("chyba", "Současné heslo nesedí.");
    } elseif (mb_strlen($nove) < 10) {
      vzkaz("chyba", "Nové heslo musí mít aspoň 10 znaků.");
    } elseif ($nove !== $znovu) {
      vzkaz("chyba", "Nové heslo se v obou polích neshoduje.");
    } else {
      uprav("uzivatele", (int)$u["id"], ["heslo" => password_hash($nove, PASSWORD_DEFAULT)]);
      zapis_udalost(null, "Uživatel " . $u["jmeno"] . " si změnil heslo");
      vzkaz("ok", "Heslo změněno.");
    }
    presmeruj(odkaz("ucet"));

  } elseif ($akce === "totp_zapnout") {
    /* Tajemství leží v sezení, dokud ho kód z telefonu nepotvrdí. */
    $tajemstvi = (string)($_SESSION["totp_navrh"] ?? "");
    if ($tajemstvi === "") { vzkaz("chyba", "Nejdřív si nechte vygenerovat tajemství."); presmeruj(odkaz("ucet")); }
    if (!totp_over($tajemstvi, vstup("kod"), 0, $krok)) {
      vzkaz("chyba", "Kód nesedí. Zkontrolujte, že je tajemství v aplikaci zadané celé, a zkuste to s novým kódem.");
      presmeruj(odkaz("ucet"));
    }
    uprav("uzivatele", (int)$u["id"], ["totp_tajemstvi" => $tajemstvi, "totp_krok" => (int)$krok]);
    unset($_SESSION["totp_navrh"]);
    zapis_udalost(null, "Uživatel " . $u["jmeno"] . " zapnul druhý faktor");
    vzkaz("ok", "Druhý faktor je zapnutý. Od teď se po hesle ptáme na kód z telefonu.");
    presmeruj(odkaz("ucet"));

  } elseif ($akce === "totp_vypnout") {
    if (!$ma_2f) presmeruj(odkaz("ucet"));
    if (!totp_over((string)$u["totp_tajemstvi"], vstup("kod"), (int)$u["totp_krok"], $krok)) {
      vzkaz("chyba", "Kód nesedí — vypnout druhý faktor jde jen s platným kódem.");
      presmeruj(odkaz("ucet"));
    }
    uprav("uzivatele", (int)$u["id"], ["totp_tajemstvi" => "", "totp_krok" => 0]);
    zapis_udalost(null, "Uživatel " . $u["jmeno"] . " vypnul druhý faktor");
    vzkaz("pozor", "Druhý faktor je vypnutý.");
    presmeruj(odkaz("ucet"));

  } elseif ($akce === "totp_nove") {
    $_SESSION["totp_navrh"] = totp_nove_tajemstvi();
    presmeruj(odkaz("ucet"));

  } elseif ($akce === "vzhled") {
    $vzhled = vstup("vzhled") === "svetly" ? "svetly" : "tmavy";
    uprav("uzivatele", (int)$u["id"], ["vzhled" => $vzhled]);
    vzkaz("ok", $vzhled === "svetly" ? "Světlý režim zapnutý." : "Tmavý režim zapnutý.");
    presmeruj(odkaz("ucet"));
  }
}

$navrh = !$ma_2f ? (string)($_SESSION["totp_navrh"] ?? "") : "";

hlava("Můj účet", "");
hlava_stranky("Uživatel", $u["jmeno"]);
?>
<div class="app-sloupce stejne">
  <div>
    <form method="post" action="<?= chran(odkaz("ucet")) ?>" class="formular" data-jednou>
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="heslo">
      <div class="skupina" style="margin-bottom:0">
        <h2>Heslo</h2>
        <p class="app-perex">Přihlášení: <span class="cislo"><?= chran($u["email"]) ?></span> · role <?= chran(ROLE[$u["role"]] ?? $u["role"]) ?><?= vidi_ceny() ? " · vidí ceny zákazníka a marže" : "" ?></p>
        <div class="pole">
          <label for="stare">Současné heslo</label>
          <input type="password" id="stare" name="stare" required autocomplete="current-password">
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="nove">Nové heslo <span class="napoveda">— aspoň 10 znaků</span></label>
            <input type="password" id="nove" name="nove" required minlength="10" autocomplete="new-password">
          </div>
          <div class="pole">
            <label for="znovu">Nové heslo znovu</label>
            <input type="password" id="znovu" name="znovu" required minlength="10" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="tlacitko">Změnit heslo</button>
      </div>
    </form>
  </div>

  <div>
    <div class="formular">
      <div class="skupina" style="margin-bottom:0">
        <h2>Druhý faktor</h2>
        <?php if ($ma_2f): ?>
          <p class="vzkaz vzkaz-ok">Zapnutý. Po hesle se ptáme na kód z aplikace v telefonu.</p>
          <form method="post" action="<?= chran(odkaz("ucet")) ?>" data-potvrdit="Opravdu druhý faktor vypnout? Přihlášení pak chrání jen heslo.">
            <?= pole_token() ?><input type="hidden" name="akce" value="totp_vypnout">
            <div class="pole">
              <label for="kod">Kód z telefonu <span class="napoveda">— pro vypnutí</span></label>
              <input type="text" id="kod" name="kod" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required>
            </div>
            <button type="submit" class="tlacitko obrys">Vypnout druhý faktor</button>
          </form>
        <?php elseif ($navrh !== ""): ?>
          <p class="app-perex">V telefonu otevřete aplikaci pro jednorázové kódy (Google Authenticator, Microsoft Authenticator, Aegis…), přidejte účet ručně a opište tajemství. Pak sem zadejte kód, který aplikace ukáže.</p>
          <p class="cislo" style="font-size:1.15rem;font-weight:700;letter-spacing:.06em;word-break:break-all;margin:0 0 6px"><?= chran(totp_tajemstvi_citelne($navrh)) ?></p>
          <p class="app-perex" style="word-break:break-all">Nebo adresa pro aplikaci: <a href="<?= chran(totp_adresa($navrh, (string)$u["email"])) ?>"><?= chran(totp_adresa($navrh, (string)$u["email"])) ?></a></p>
          <form method="post" action="<?= chran(odkaz("ucet")) ?>">
            <?= pole_token() ?><input type="hidden" name="akce" value="totp_zapnout">
            <div class="pole">
              <label for="kod">Kód z telefonu</label>
              <input type="text" id="kod" name="kod" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required autofocus>
            </div>
            <div class="tlacitka" style="margin:0">
              <button type="submit" class="tlacitko">Zapnout druhý faktor</button>
              <button type="submit" class="tlacitko obrys" name="akce" value="totp_nove">Nové tajemství</button>
            </div>
          </form>
        <?php else: ?>
          <p class="app-perex">Vypnutý. Systém je na veřejné adrese a nese osobní údaje zákazníků i řidičů — heslo samo je málo. Druhý faktor je kód z aplikace v telefonu, nic se neplatí a nepotřebuje to signál.</p>
          <form method="post" action="<?= chran(odkaz("ucet")) ?>">
            <?= pole_token() ?><input type="hidden" name="akce" value="totp_nove">
            <button type="submit" class="tlacitko">Nastavit druhý faktor</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <form method="post" action="<?= chran(odkaz("ucet")) ?>" class="formular" style="margin-top:20px">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="vzhled">
      <div class="skupina" style="margin-bottom:0">
        <h2>Vzhled</h2>
        <p class="app-perex">Firemní barvy zůstávají, mění se plochy. Volba platí jen pro vás.</p>
        <?php $vzhled = (string)($u["vzhled"] ?? "") === "svetly" ? "svetly" : "tmavy"; ?>
        <div class="pole-zaskrtnuti">
          <input type="radio" id="vzhled_tmavy" name="vzhled" value="tmavy"<?= $vzhled === "tmavy" ? " checked" : "" ?>>
          <label for="vzhled_tmavy">Tmavý <span class="napoveda">— kovové plochy, výchozí</span></label>
        </div>
        <div class="pole-zaskrtnuti">
          <input type="radio" id="vzhled_svetly" name="vzhled" value="svetly"<?= $vzhled === "svetly" ? " checked" : "" ?>>
          <label for="vzhled_svetly">Světlý <span class="napoveda">— krémové plochy, na denní světlo</span></label>
        </div>
        <button type="submit" class="tlacitko obrys">Uložit vzhled</button>
      </div>
    </form>
  </div>
</div>
<?php
pata();

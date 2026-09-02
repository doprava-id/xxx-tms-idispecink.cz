<?php
/* Přihlášení. Po několika neúspěšných pokusech se adresa na čtvrt
   hodiny zablokuje — bez toho by se heslo dalo hádat donekonečna. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

if (prihlasen()) presmeruj(odkaz("prehled"));

$chyba = "";
$email = vstup("email");
$limit = max(3, (int)($config["pokusu_prihlaseni"] ?? 5));

/* Druhý krok: heslo už sedělo, čeká se na kód z telefonu. Čekání má pět
   minut, pak se začíná od začátku. */
$cekajici = $_SESSION["cekajici_2f"] ?? null;
if (is_array($cekajici) && time() - (int)$cekajici["kdy"] > 300) { unset($_SESSION["cekajici_2f"]); $cekajici = null; }
$krok2 = is_array($cekajici) && vstup("krok") === "2";

/* Token se tu kontroluje měkce: formulář může viset otevřený déle,
   než vydrží sezení, a tvrdá chybová stránka by jen mátla. */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $poslany = (string)($_POST["token"] ?? "");
  if ($poslany === "" || !hash_equals((string)($_SESSION["token"] ?? ""), $poslany)) {
    vzkaz("pozor", "Přihlašovací formulář byl otevřený příliš dlouho. Zkuste to prosím znovu.");
    presmeruj(odkaz("prihlaseni"));
  }
}

if ($krok2 && $_SERVER["REQUEST_METHOD"] === "POST") {
  if (pokusy_vycerpany($limit)) {
    $chyba = "Příliš mnoho neúspěšných pokusů. Zkuste to prosím za čtvrt hodiny.";
  } else {
    $u = radek("SELECT * FROM uzivatele WHERE id = ? AND aktivni = 1", [(int)$cekajici["id"]]);
    if ($u && trim((string)$u["totp_tajemstvi"]) !== ""
        && totp_over((string)$u["totp_tajemstvi"], (string)($_POST["kod"] ?? ""), (int)$u["totp_krok"], $pouzity_krok)) {
      uprav("uzivatele", (int)$u["id"], ["totp_krok" => (int)$pouzity_krok]);
      unset($_SESSION["cekajici_2f"]);
      smaz_pokusy();
      prihlas($u);
      $kam = $_SESSION["kam_po_prihlaseni"] ?? "";
      unset($_SESSION["kam_po_prihlaseni"]);
      presmeruj($kam !== "" ? $kam : odkaz("prehled"));
    }
    zapis_pokus();
    $chyba = "Kód nesedí, nebo už byl použitý. Počkejte na další.";
  }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (pokusy_vycerpany($limit)) {
    $chyba = "Příliš mnoho neúspěšných pokusů. Zkuste to prosím za čtvrt hodiny.";
  } else {
    $heslo = (string)($_POST["heslo"] ?? "");
    $u = radek("SELECT * FROM uzivatele WHERE email = ? AND aktivni = 1", [mb_strtolower($email)]);

    if ($u && password_verify($heslo, (string)$u["heslo"])) {
      /* Otisk se přepočítá, když PHP mezitím zpřísnilo výchozí algoritmus. */
      if (password_needs_rehash((string)$u["heslo"], PASSWORD_DEFAULT)) {
        uprav("uzivatele", (int)$u["id"], ["heslo" => password_hash($heslo, PASSWORD_DEFAULT)]);
      }
      smaz_pokusy();
      if (trim((string)$u["totp_tajemstvi"]) !== "") {
        /* Heslo sedí, ale přihlášení čeká na kód z telefonu. */
        $_SESSION["cekajici_2f"] = ["id" => (int)$u["id"], "kdy" => time()];
        presmeruj(odkaz("prihlaseni", ["krok" => "2"]));
      }
      prihlas($u);
      $kam = $_SESSION["kam_po_prihlaseni"] ?? "";
      unset($_SESSION["kam_po_prihlaseni"]);
      presmeruj($kam !== "" ? $kam : odkaz("prehled"));
    }

    zapis_pokus();
    /* Neprozrazovat, jestli chybí e-mail nebo heslo. */
    $chyba = "E-mail nebo heslo nesedí.";
  }
}

hlava("Přihlášení", "");
?>
<div class="app-uzka">
  <span class="nadpis-stitek">Provozní systém</span>
  <h1>Přihlášení</h1>

  <?php if ($chyba !== ""): ?>
    <p class="vzkaz vzkaz-chyba" role="alert"><?= chran($chyba) ?></p>
  <?php endif; ?>

  <?php if ($krok2): ?>
    <form method="post" action="<?= chran(odkaz("prihlaseni", ["krok" => "2"])) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="krok" value="2">
      <p class="app-perex">Heslo sedí. Zadejte kód z aplikace v telefonu.</p>
      <div class="pole">
        <label for="kod">Kód z telefonu</label>
        <input type="text" id="kod" name="kod" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required autofocus>
      </div>
      <button type="submit" class="tlacitko">Přihlásit</button>
    </form>
    <p class="app-perex"><a href="<?= chran(odkaz("prihlaseni")) ?>">Začít znovu</a></p>
  <?php else: ?>
    <form method="post" action="<?= chran(odkaz("prihlaseni")) ?>" class="formular">
      <?= pole_token() ?>
      <div class="pole">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= chran($email) ?>" required autocomplete="username" autofocus>
      </div>
      <div class="pole">
        <label for="heslo">Heslo</label>
        <input type="password" id="heslo" name="heslo" required autocomplete="current-password">
      </div>
      <button type="submit" class="tlacitko">Přihlásit</button>
    </form>
  <?php endif; ?>

  <p class="app-perex"><a href="../index.html">Zpět na web idispecink.cz</a></p>
</div>
<?php
pata();

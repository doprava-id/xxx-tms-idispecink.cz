<?php
/* Nastavení — údaje firmy, číselná řada, podmínky objednávky a uživatelé.
   Celá stránka je jen pro správce. */

if (!defined("APLIKACE")) { http_response_code(403); exit("Přístup odepřen."); }

vyzaduj_spravce();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $akce = vstup("akce");

  if ($akce === "firma") {
    foreach (["firma_nazev", "firma_ulice", "firma_mesto", "firma_psc", "firma_ico",
              "firma_dic", "firma_telefon", "firma_email", "firma_web", "firma_zapis"] as $klic) {
      uloz_nastaveni($klic, vstup($klic));
    }
    vzkaz("ok", "Údaje firmy uloženy.");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "cislovani") {
    uloz_nastaveni("cislovani_predpona", vstup("cislovani_predpona"));
    uloz_nastaveni("cislovani_mist", (string)max(1, min(8, (int)vstup("cislovani_mist"))));
    uloz_nastaveni("cislovani_dalsi", (string)max(1, (int)vstup("cislovani_dalsi")));
    uloz_nastaveni("cislovani_rok", date("Y"));
    uloz_nastaveni("cislovani_potvrzeno", date("Y-m-d H:i:s"));
    vzkaz("ok", "Číselná řada nastavena.");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "posta") {
    $od = vstup("email_odesilatel"); $kopie = vstup("email_kopie"); $zakl = vstup("zakladni_adresa");
    if ($od !== "" && !platny_email($od)) { vzkaz("chyba", "Adresa odesílatele není platná."); presmeruj(odkaz("nastaveni")); }
    if ($kopie !== "" && !platny_email($kopie)) { vzkaz("chyba", "Adresa pro kopii není platná."); presmeruj(odkaz("nastaveni")); }
    if ($zakl !== "" && !preg_match('~^https?://[^\s]+$~', $zakl)) { vzkaz("chyba", "Základní adresa musí začínat http:// nebo https://."); presmeruj(odkaz("nastaveni")); }
    uloz_nastaveni("email_odesilatel", $od);
    uloz_nastaveni("email_kopie", $kopie);
    uloz_nastaveni("zakladni_adresa", $zakl);
    vzkaz("ok", "Nastavení pošty a odkazů uloženo.");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "hlidani") {
    uloz_nastaveni("hlidani_zapnuto", vstup_ano_ne("hlidani_zapnuto") ? "1" : "0");
    vzkaz("ok", "Hlídání " . (vstup_ano_ne("hlidani_zapnuto") ? "zapnuto" : "vypnuto") . ".");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "hlidani_ted") {
    $v = hlidani_odesli("ručně z Nastavení");
    vzkaz($v["chyba"] ? "chyba" : "ok", $v["chyba"] ?: "Ranní souhrn odeslán " . $v["poslano"] . " příjemcům.");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "podminky") {
    uloz_nastaveni("podminky", (string)($_POST["podminky"] ?? ""));
    vzkaz("ok", "Podmínky objednávky uloženy.");
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "uzivatel_novy") {
    $jmeno = vstup("u_jmeno");
    $email = mb_strtolower(vstup("u_email"));
    $heslo = (string)($_POST["u_heslo"] ?? "");
    if ($jmeno === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($heslo) < 10) {
      vzkaz("chyba", "Vyplňte jméno, platný e-mail a heslo aspoň o deseti znacích.");
    } elseif (hodnota("SELECT COUNT(*) FROM uzivatele WHERE email = ?", [$email])) {
      vzkaz("chyba", "Uživatel s tímhle e-mailem už existuje.");
    } else {
      vloz("uzivatele", [
        "jmeno"     => $jmeno,
        "email"     => $email,
        "heslo"     => password_hash($heslo, PASSWORD_DEFAULT),
        "role"      => isset(ROLE[vstup("u_role")]) ? vstup("u_role") : "dispecer",
        "vidi_ceny" => vstup_ano_ne("u_vidi_ceny"),
        "aktivni"   => 1,
        "vytvoreno" => date("Y-m-d H:i:s"),
      ]);
      zapis_udalost(null, "Přidán uživatel " . $jmeno);
      vzkaz("ok", "Uživatel přidán.");
    }
    presmeruj(odkaz("nastaveni"));

  } elseif ($akce === "uzivatel_zmena") {
    $uid = vstup_cislo("uzivatel_id");
    $u = $uid ? radek("SELECT * FROM uzivatele WHERE id = ?", [$uid]) : null;
    if (!$u) {
      vzkaz("chyba", "Uživatel nenalezen.");
      presmeruj(odkaz("nastaveni"));
    }
    $data = [
      "role"      => isset(ROLE[vstup("role")]) ? vstup("role") : (string)$u["role"],
      "vidi_ceny" => vstup_ano_ne("vidi_ceny"),
      "aktivni"   => vstup_ano_ne("aktivni"),
    ];
    $heslo = (string)($_POST["heslo"] ?? "");
    if ($heslo !== "") {
      if (mb_strlen($heslo) < 10) {
        vzkaz("chyba", "Nové heslo musí mít aspoň 10 znaků.");
        presmeruj(odkaz("nastaveni"));
      }
      $data["heslo"] = password_hash($heslo, PASSWORD_DEFAULT);
    }

    /* Systém bez správce by se nedal spravovat a poslední správce by si
       odebráním role zamkl dveře zvenčí. */
    $spravcu = (int)hodnota("SELECT COUNT(*) FROM uzivatele WHERE role = 'spravce' AND aktivni = 1 AND id <> ?", [$uid]);
    if ($spravcu === 0 && ($data["role"] !== "spravce" || $data["aktivni"] !== 1)) {
      vzkaz("chyba", "Tohle je poslední aktivní správce — roli ani přístup mu odebrat nejde.");
      presmeruj(odkaz("nastaveni"));
    }

    uprav("uzivatele", (int)$uid, $data);
    vzkaz("ok", "Uživatel " . $u["jmeno"] . " upraven.");
    presmeruj(odkaz("nastaveni"));
  }
}

$uzivatele = radky("SELECT * FROM uzivatele ORDER BY aktivni DESC, LOWER(jmeno)");
$ukazka = slozene_cislo(nastaveni("cislovani_predpona", "{RR}-"),
  (int)nastaveni("cislovani_dalsi", "1"), (int)nastaveni("cislovani_mist", "4"));

hlava("Nastavení", "nastaveni");
hlava_stranky("Provozní systém", "Nastavení",
  '<a class="tlacitko obrys" href="' . chran(odkaz("import")) . '">Import z CSV</a>');
?>

<div class="app-sloupce">
  <div>
    <form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="firma">
      <div class="skupina" style="margin-bottom:0">
        <h2>Údaje firmy</h2>
        <p class="app-perex">Tiskne se v hlavičce objednávky přepravy.</p>
        <div class="pole">
          <label for="firma_nazev">Název</label>
          <input type="text" id="firma_nazev" name="firma_nazev" value="<?= chran(nastaveni("firma_nazev")) ?>">
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="firma_ulice">Ulice a číslo</label>
            <input type="text" id="firma_ulice" name="firma_ulice" value="<?= chran(nastaveni("firma_ulice")) ?>">
          </div>
          <div class="pole">
            <label for="firma_psc">PSČ</label>
            <input type="text" id="firma_psc" name="firma_psc" value="<?= chran(nastaveni("firma_psc")) ?>">
          </div>
          <div class="pole">
            <label for="firma_mesto">Město</label>
            <input type="text" id="firma_mesto" name="firma_mesto" value="<?= chran(nastaveni("firma_mesto")) ?>">
          </div>
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="firma_ico">IČO</label>
            <input type="text" id="firma_ico" name="firma_ico" value="<?= chran(nastaveni("firma_ico")) ?>">
          </div>
          <div class="pole">
            <label for="firma_dic">DIČ</label>
            <input type="text" id="firma_dic" name="firma_dic" value="<?= chran(nastaveni("firma_dic")) ?>">
          </div>
        </div>
        <div class="pole-radek tri">
          <div class="pole">
            <label for="firma_telefon">Telefon</label>
            <input type="tel" id="firma_telefon" name="firma_telefon" value="<?= chran(nastaveni("firma_telefon")) ?>">
          </div>
          <div class="pole">
            <label for="firma_email">E-mail</label>
            <input type="email" id="firma_email" name="firma_email" value="<?= chran(nastaveni("firma_email")) ?>">
          </div>
          <div class="pole">
            <label for="firma_web">Web</label>
            <input type="text" id="firma_web" name="firma_web" value="<?= chran(nastaveni("firma_web")) ?>">
          </div>
        </div>
        <div class="pole">
          <label for="firma_zapis">Zápis v rejstříku</label>
          <input type="text" id="firma_zapis" name="firma_zapis" value="<?= chran(nastaveni("firma_zapis")) ?>">
        </div>
        <button type="submit" class="tlacitko">Uložit údaje firmy</button>
      </div>
    </form>

    <form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="podminky">
      <div class="skupina" style="margin-bottom:0">
        <h2>Podmínky objednávky přepravy</h2>
        <p class="app-perex">Text, který se tiskne dopravci na objednávce. Vkládá se
          ručně — obchodní a právní podmínky si systém nedomýšlí. Odstavce
          oddělte prázdným řádkem.</p>
        <?php if (trim(nastaveni("podminky")) === ""): ?>
          <!-- PLACEHOLDER: podmínky objednávky dodá zadavatel. -->
          <div class="doplnit" style="margin-bottom:14px">
            <b>Zatím prázdné.</b> Dokud text nevložíte, objednávka se tiskne bez podmínek.
          </div>
        <?php endif; ?>
        <div class="pole">
          <label for="podminky" class="jen-pro-ctecky">Podmínky</label>
          <textarea id="podminky" name="podminky" style="min-height:220px"><?= chran(nastaveni("podminky")) ?></textarea>
        </div>
        <button type="submit" class="tlacitko">Uložit podmínky</button>
      </div>
    </form>
  </div>

  <div>
    <form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="cislovani">
      <div class="skupina" style="margin-bottom:0">
        <h2>Číselná řada</h2>
        <p class="app-perex">Aby řada navázala na to, co vystavujete dnes, nastavte
          tvar předpony a číslo, kterým se má pokračovat. V předponě se
          <span class="cislo">{RR}</span> nahradí dvoumístným rokem a
          <span class="cislo">{RRRR}</span> čtyřmístným; obsahuje-li předpona rok,
          začíná řada v lednu znovu od jedničky.</p>
        <div class="pole">
          <label for="cislovani_predpona">Předpona</label>
          <input type="text" id="cislovani_predpona" name="cislovani_predpona" value="<?= chran(nastaveni("cislovani_predpona", "{RR}-")) ?>">
        </div>
        <div class="pole-radek">
          <div class="pole">
            <label for="cislovani_dalsi">Další číslo</label>
            <input type="number" id="cislovani_dalsi" name="cislovani_dalsi" value="<?= chran(nastaveni("cislovani_dalsi", "1")) ?>" min="1">
          </div>
          <div class="pole">
            <label for="cislovani_mist">Míst s nulami</label>
            <input type="number" id="cislovani_mist" name="cislovani_mist" value="<?= chran(nastaveni("cislovani_mist", "4")) ?>" min="1" max="8">
          </div>
        </div>
        <p class="app-perex">Příští přeprava dostane číslo <b class="cislo"><?= chran($ukazka) ?></b>.
          Nabídky mají stejný tvar s předponou N a vlastní počítadlo; příští: <b class="cislo"><?= chran(slozene_cislo("N" . nastaveni("cislovani_predpona", "{RR}-"), (int)nastaveni("nabidky_dalsi", "1"), (int)nastaveni("cislovani_mist", "4"))) ?></b>.</p>
        <button type="submit" class="tlacitko">Uložit číslování</button>
      </div>
    </form>

    <form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="posta">
      <div class="skupina" style="margin-bottom:0">
        <h2>Pošta a odkazy ven</h2>
        <div class="pole">
          <label for="email_odesilatel">Odesílatel <span class="napoveda">— hlavička From; SPF domény musí pustit servery hostingu</span></label>
          <input type="email" id="email_odesilatel" name="email_odesilatel" value="<?= chran(nastaveni("email_odesilatel", "web@idispecink.cz")) ?>">
        </div>
        <div class="pole">
          <label for="email_kopie">Skrytá kopie každé odeslané objednávky <span class="napoveda">— nepovinné</span></label>
          <input type="email" id="email_kopie" name="email_kopie" value="<?= chran(nastaveni("email_kopie")) ?>">
        </div>
        <div class="pole">
          <label for="zakladni_adresa">Základní adresa aplikace <span class="napoveda">— pro odkazy v e-mailech; prázdné = odvodit z požadavku</span></label>
          <input type="text" id="zakladni_adresa" name="zakladni_adresa" value="<?= chran(nastaveni("zakladni_adresa")) ?>" placeholder="https://idispecink.cz/aplikace/">
        </div>
        <button type="submit" class="tlacitko">Uložit poštu a odkazy</button>
      </div>
    </form>

    <form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular">
      <?= pole_token() ?>
      <input type="hidden" name="akce" value="hlidani">
      <div class="skupina" style="margin-bottom:0">
        <h2>Hlídání — ranní souhrn</h2>
        <p class="app-perex">E-mail všem uživatelům: nakládky bez dopravce do <?= HLIDANI_DNU_NAKLADKA ?> dnů, doklady chybějící déle než týden po vykládce a končící doklady dopravců. Ceny v něm nejsou.</p>
        <div class="pole-zaskrtnuti">
          <input type="checkbox" id="hlidani_zapnuto" name="hlidani_zapnuto" value="1"<?= hlidani_zapnuto() ? " checked" : "" ?>>
          <label for="hlidani_zapnuto">Posílat ranní souhrn</label>
        </div>
        <?php if (trim((string)($config["hlidani_klic"] ?? "")) !== ""): ?>
          <p class="app-perex">Naplánovaná úloha hostingu volá jednou ráno adresu
            <span class="cislo" style="word-break:break-all"><?= chran(zakladni_adresa() . "index.php?s=hlidani&klic=" . $config["hlidani_klic"]) ?></span>.</p>
        <?php else: ?>
          <p class="app-perex">Klíč pro naplánovanou úlohu (<span class="cislo">hlidani_klic</span>) v config.php chybí — souhrn se pošle při prvním otevření systému toho dne.</p>
        <?php endif; ?>
        <?php if (nastaveni("hlidani_vysledek") !== ""): ?><p class="app-perex">Naposledy: <?= chran(nastaveni("hlidani_vysledek")) ?></p><?php endif; ?>
        <div class="tlacitka" style="margin:0">
          <button type="submit" class="tlacitko">Uložit hlídání</button>
          <button type="submit" class="tlacitko obrys" name="akce" value="hlidani_ted">Poslat souhrn teď</button>
        </div>
      </div>
    </form>

    <div class="formular">
      <div class="skupina" style="margin-bottom:0">
        <h2>Data</h2>
        <ul class="seznam">
          <li><a href="<?= chran(odkaz("import")) ?>">Import přeprav z CSV</a> — načtení zásilek z exportu jiného systému.</li>
          <li><a href="<?= chran(odkaz("export", ["co" => "zaloha"])) ?>">Export všech přeprav</a> — celá evidence do CSV.</li>
          <li><a href="<?= chran(odkaz("export", ["co" => "firmy"])) ?>">Export firem</a> — adresář zákazníků a dopravců.</li>
        </ul>
        <p class="app-perex">Databáze sama leží v adresáři <span class="cislo">aplikace/data/</span>
          a web ji nevydá. Zálohu si stáhněte i přes FTP.</p>
      </div>
    </div>
  </div>
</div>

<h2 style="margin-top:36px">Uživatelé</h2>
<p class="app-perex">Právo na ceny zákazníka a marže se přepíná u každého
  uživatele zvlášť; správce je vidí vždy. Heslo vyplňujte jen když ho měníte.</p>

<?php
/* Formuláře stojí mimo tabulku a pole se k nim hlásí atributem form —
   <form> uvnitř <tr> není platné HTML a prohlížeč by ho z tabulky vytáhl. */
foreach ($uzivatele as $u): ?>
  <form id="uzivatel-<?= (int)$u["id"] ?>" method="post" action="<?= chran(odkaz("nastaveni")) ?>" hidden>
    <?= pole_token() ?>
    <input type="hidden" name="akce" value="uzivatel_zmena">
    <input type="hidden" name="uzivatel_id" value="<?= (int)$u["id"] ?>">
  </form>
<?php endforeach; ?>

<div class="tabulka-obal">
  <table class="id-tabulka">
    <thead>
      <tr><th>Jméno</th><th>E-mail</th><th>Role</th><th>Ceny</th><th>Přístup</th><th>Naposledy</th><th>Nové heslo</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($uzivatele as $u): $f = "uzivatel-" . (int)$u["id"]; ?>
      <tr<?= (int)$u["aktivni"] === 1 ? "" : ' class="zrusena"' ?>>
        <td><?= chran($u["jmeno"]) ?></td>
        <td class="cislo"><?= chran($u["email"]) ?></td>
        <td>
          <label for="role-<?= (int)$u["id"] ?>" class="jen-pro-ctecky">Role</label>
          <select id="role-<?= (int)$u["id"] ?>" name="role" form="<?= $f ?>"
                  style="padding:6px 8px;font-size:.88rem"><?= volby(ROLE, $u["role"]) ?></select>
        </td>
        <td>
          <label for="ceny-<?= (int)$u["id"] ?>" class="jen-pro-ctecky">Vidí ceny zákazníka a marže</label>
          <input type="checkbox" id="ceny-<?= (int)$u["id"] ?>" name="vidi_ceny" value="1" form="<?= $f ?>"
                 <?= ((int)$u["vidi_ceny"] === 1 || $u["role"] === "spravce") ? "checked" : "" ?>
                 style="width:18px;height:18px;accent-color:var(--zluta)">
        </td>
        <td>
          <label for="aktivni-<?= (int)$u["id"] ?>" class="jen-pro-ctecky">Má přístup</label>
          <input type="checkbox" id="aktivni-<?= (int)$u["id"] ?>" name="aktivni" value="1" form="<?= $f ?>"
                 <?= (int)$u["aktivni"] === 1 ? "checked" : "" ?>
                 style="width:18px;height:18px;accent-color:var(--zluta)">
        </td>
        <td><span class="druhotny"><?= chran(datum_cas($u["posledni_prihlaseni"])) ?></span></td>
        <td>
          <label for="heslo-<?= (int)$u["id"] ?>" class="jen-pro-ctecky">Nové heslo pro <?= chran($u["jmeno"]) ?></label>
          <input type="password" id="heslo-<?= (int)$u["id"] ?>" name="heslo" form="<?= $f ?>"
                 placeholder="beze změny" autocomplete="new-password" minlength="10"
                 style="padding:6px 8px;font-size:.88rem;width:140px">
        </td>
        <td>
          <button type="submit" form="<?= $f ?>" class="tlacitko obrys"
                  style="padding:6px 12px;font-size:.84rem">Uložit</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" action="<?= chran(odkaz("nastaveni")) ?>" class="formular" style="margin-top:20px;max-width:760px">
  <?= pole_token() ?>
  <input type="hidden" name="akce" value="uzivatel_novy">
  <div class="skupina" style="margin-bottom:0">
    <h2>Nový uživatel</h2>
    <div class="pole-radek">
      <div class="pole">
        <label for="u_jmeno">Jméno a příjmení</label>
        <input type="text" id="u_jmeno" name="u_jmeno" required>
      </div>
      <div class="pole">
        <label for="u_email">E-mail</label>
        <input type="email" id="u_email" name="u_email" required autocomplete="off">
      </div>
    </div>
    <div class="pole-radek">
      <div class="pole">
        <label for="u_heslo">Heslo <span class="napoveda">— aspoň 10 znaků</span></label>
        <input type="password" id="u_heslo" name="u_heslo" required minlength="10" autocomplete="new-password">
      </div>
      <div class="pole">
        <label for="u_role">Role</label>
        <select id="u_role" name="u_role"><?= volby(ROLE, "dispecer") ?></select>
      </div>
    </div>
    <div class="pole-zaskrtnuti">
      <input type="checkbox" id="u_vidi_ceny" name="u_vidi_ceny" value="1">
      <label for="u_vidi_ceny">Vidí ceny zákazníka a marže <span class="napoveda">— správce je vidí vždy</span></label>
    </div>
    <button type="submit" class="tlacitko">Přidat uživatele</button>
  </div>
</form>
<?php
pata();

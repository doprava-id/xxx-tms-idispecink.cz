/* iDispečink.cz — měření návštěvnosti. Google Analytics 4, jen se souhlasem.

   Dokud je GA_MERENI prázdné, skript nedělá vůbec nic: neměří se,
   lišta se nezobrazuje a tlačítko Nastavení cookies zůstává skryté.
   Zásady zpracování údajů měření popisují — proto ID vyplňte při
   nasazení, jinak zásady slibují víc, než web dělá. */
(function () {
  "use strict";

  /* PLACEHOLDER: měřicí ID vlastnosti Google Analytics 4 (tvar G-XXXXXXXXXX).
     Vydává ho administrace GA při založení vlastnosti — nelze si ho vymyslet. */
  var GA_MERENI = "";

  if (!GA_MERENI) return;

  var KLIC = "ga-souhlas";

  function ulozenaVolba() {
    try { return localStorage.getItem(KLIC); } catch (e) { return null; }
  }
  function ulozVolbu(hodnota) {
    try { localStorage.setItem(KLIC, hodnota); } catch (e) { /* soukromé okno */ }
  }

  function spustMereni() {
    var skript = document.createElement("script");
    skript.src = "https://www.googletagmanager.com/gtag/js?id=" + GA_MERENI;
    skript.async = true;
    document.head.appendChild(skript);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag("js", new Date());
    window.gtag("config", GA_MERENI, { anonymize_ip: true });
  }

  function zastavMereni() {
    /* Google skript se bez souhlasu vůbec nenačítá; při odvolání souhlasu
       stačí smazat jeho cookies a od příštího načtení se nic nespustí. */
    var domena = location.hostname.replace(/^www\./, "");
    document.cookie.split(";").forEach(function (polozka) {
      var nazev = polozka.split("=")[0].trim();
      if (nazev === "_ga" || nazev.indexOf("_ga_") === 0) {
        document.cookie = nazev + "=; max-age=0; path=/; domain=." + domena;
        document.cookie = nazev + "=; max-age=0; path=/";
      }
    });
  }

  function zobrazListu() {
    if (document.querySelector(".cookies-lista")) return;

    var lista = document.createElement("div");
    lista.className = "cookies-lista";
    lista.setAttribute("role", "region");
    lista.setAttribute("aria-label", "Souhlas s měřením návštěvnosti");
    lista.innerHTML =
      '<p>Web používá měření návštěvnosti Google Analytics. Zapne se jen s vaším' +
      ' souhlasem — bez něj se o vaší návštěvě nic neměří.' +
      ' <a href="zasady-osobnich-udaju.html">Zásady zpracování údajů</a></p>' +
      '<div class="cookies-lista-tlacitka">' +
      '<button type="button" class="tlacitko" data-volba="ano">Povolit měření</button>' +
      '<button type="button" class="tlacitko obrys" data-volba="ne">Odmítnout</button>' +
      '</div>';

    lista.addEventListener("click", function (udalost) {
      var tlacitko = udalost.target.closest("[data-volba]");
      if (!tlacitko) return;
      var volba = tlacitko.getAttribute("data-volba");
      ulozVolbu(volba);
      lista.remove();
      if (volba === "ano") { spustMereni(); } else { zastavMereni(); }
    });

    document.body.appendChild(lista);
  }

  /* Tlačítko v patičce — souhlas jde kdykoliv změnit. */
  var nastaveni = document.querySelector(".cookies-nastaveni");
  if (nastaveni) {
    nastaveni.hidden = false;
    nastaveni.addEventListener("click", zobrazListu);
  }

  var volba = ulozenaVolba();
  if (volba === "ano") {
    spustMereni();
  } else if (volba !== "ne") {
    zobrazListu();
  }
})();

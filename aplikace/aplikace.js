/* =========================================================================
   Provozní systém — obsluha rozhraní

   Skript je nadstavba. Bez něj se aplikace ovládá dál: nabídku rozbalí
   <noscript> blok v hlavičce každé stránky a formuláře odesílají běžný
   POST, protože žádné odeslání nestojí na JavaScriptu.
   ========================================================================= */
(function () {
  "use strict";

  /* Nabídka na úzkém displeji. */
  var prepinac = document.querySelector(".app-hlavicka .menu-prepinac");
  var menu = document.getElementById("app-menu");
  if (prepinac && menu) {
    prepinac.addEventListener("click", function () {
      var otevreno = menu.classList.toggle("otevreno");
      prepinac.setAttribute("aria-expanded", otevreno ? "true" : "false");
    });
  }

  /* Dvojí odeslání formuláře by založilo dvě stejné přepravy. */
  document.querySelectorAll("form[data-jednou]").forEach(function (formular) {
    formular.addEventListener("submit", function () {
      var tlacitko = formular.querySelector("button[type=submit]");
      if (!tlacitko) return;
      window.setTimeout(function () {
        tlacitko.disabled = true;
        tlacitko.textContent = "Ukládám…";
      }, 0);
    });
  });

  /* Potvrzení u nevratných akcí. */
  document.querySelectorAll("form[data-potvrdit]").forEach(function (formular) {
    formular.addEventListener("submit", function (udalost) {
      if (!window.confirm(formular.getAttribute("data-potvrdit"))) {
        udalost.preventDefault();
      }
    });
  });

  /* Marže se dopočítá hned při psaní, ať dispečer nepočítá v hlavě. */
  var zakaznik = document.getElementById("cena_zakaznik");
  var dopravce = document.getElementById("cena_dopravce");
  var vypis = document.getElementById("marze-nahled");
  if (zakaznik && dopravce && vypis) {
    var prepocti = function () {
      var a = parseFloat(String(zakaznik.value).replace(/\s/g, "").replace(",", "."));
      var b = parseFloat(String(dopravce.value).replace(/\s/g, "").replace(",", "."));
      if (isNaN(a) || isNaN(b)) { vypis.textContent = "—"; return; }
      var marze = a - b;
      var procenta = a > 0 ? (marze / a) * 100 : 0;
      vypis.textContent = marze.toLocaleString("cs-CZ", { maximumFractionDigits: 0 })
        + " Kč (" + procenta.toLocaleString("cs-CZ", { maximumFractionDigits: 1 }) + " %)";
    };
    zakaznik.addEventListener("input", prepocti);
    dopravce.addEventListener("input", prepocti);
    prepocti();
  }
})();

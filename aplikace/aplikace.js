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

  /* Klávesové zkratky: „/" skočí do hledání, Alt+N založí novou přepravu.
     V poli formuláře lomítko zůstává lomítkem. */
  var hledani = document.getElementById("app-hledat");
  document.addEventListener("keydown", function (udalost) {
    var cil = udalost.target;
    var vPoli = cil && (cil.tagName === "INPUT" || cil.tagName === "TEXTAREA" || cil.tagName === "SELECT" || cil.isContentEditable);
    if (udalost.key === "/" && !vPoli && hledani && !udalost.ctrlKey && !udalost.altKey && !udalost.metaKey) {
      udalost.preventDefault(); hledani.focus(); hledani.select();
    }
    if (udalost.altKey && !udalost.ctrlKey && (udalost.key === "n" || udalost.key === "N")) {
      var nova = document.querySelector('a[href*="s=preprava&id=nova"]');
      if (nova) { udalost.preventDefault(); window.location = nova.href; }
    }
  });

  /* Hromadné označení řádků v seznamu. */
  document.querySelectorAll("[data-vse]").forEach(function (vse) {
    vse.addEventListener("change", function () {
      document.querySelectorAll('input[name="id[]"]').forEach(function (radek) { radek.checked = vse.checked; });
    });
  });

  /* Návrh ceny: „použít" opíše částku do pole a zapíše, podle čeho vznikla. */
  document.querySelectorAll("[data-doplnit]").forEach(function (tlacitko) {
    tlacitko.addEventListener("click", function () {
      var pole = document.getElementById(tlacitko.getAttribute("data-doplnit"));
      if (!pole) return;
      pole.value = tlacitko.getAttribute("data-hodnota");
      pole.dispatchEvent(new Event("input", { bubbles: true }));
      var podle = document.getElementById("cena_podle");
      if (podle && tlacitko.hasAttribute("data-podle")) podle.value = tlacitko.getAttribute("data-podle");
      pole.focus();
    });
  });
})();

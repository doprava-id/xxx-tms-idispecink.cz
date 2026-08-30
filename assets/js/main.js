/* iDispečink.cz — chování webu. Bez závislostí. */
(function () {
  "use strict";

  /* --- Mobilní menu ---------------------------------------------------- */
  var prepinac = document.querySelector(".menu-prepinac");
  var menu = document.getElementById("hlavni-menu");

  if (prepinac && menu) {
    prepinac.addEventListener("click", function () {
      var otevreno = menu.classList.toggle("otevreno");
      prepinac.setAttribute("aria-expanded", otevreno ? "true" : "false");

      /* Menu je v DOM před svým tlačítkem, takže by Tab po otevření
         pokračoval do obsahu stránky a rozbalené položky přeskočil.
         Zaostření první položky drží pořadí smysluplné. */
      if (otevreno) {
        var prvni = menu.querySelector("a");
        if (prvni) prvni.focus();
      }
    });
  }

  /* --- Formuláře --------------------------------------------------------- */
  /* Formuláře odesílá server (odeslani.php) běžným POSTem — fungují proto
     i bez JavaScriptu. Tady se jen po odeslání znepřístupní tlačítko,
     aby netrpělivý návštěvník neposlal zprávu dvakrát. */
  var formulare = document.querySelectorAll("form.formular");

  Array.prototype.forEach.call(formulare, function (form) {
    form.addEventListener("submit", function () {
      var tlacitko = form.querySelector("button[type=submit]");
      if (tlacitko) {
        tlacitko.disabled = true;
        tlacitko.textContent = "Odesílám…";
      }
      var stav = form.querySelector(".formular-stav");
      if (stav) {
        stav.textContent = "Odesílám zprávu…";
      }
    });
  });

  /* --- Schovávání hlavičky při rolování --------------------------------- */
  /* Vylepšení nad základ: bez JavaScriptu třída .schovana nikdy nepřibude
     a přilepená hlavička prostě zůstává vidět. Při rolování dolů se
     schová, při rolování nahoru, u otevřeného menu nebo při zaostření
     klávesnicí se vrací. */
  var hlavicka = document.querySelector(".hlavicka");

  if (hlavicka) {
    var posledniY = window.scrollY || 0;

    window.addEventListener("scroll", function () {
      var y = window.scrollY || 0;
      var otevrene = menu && menu.classList.contains("otevreno");

      if (!otevrene && y > posledniY && y > 140) {
        hlavicka.classList.add("schovana");
      } else if (y < posledniY || y <= 140) {
        hlavicka.classList.remove("schovana");
      }
      posledniY = y;
    }, { passive: true });

    hlavicka.addEventListener("focusin", function () {
      hlavicka.classList.remove("schovana");
    });
  }

  /* --- Rok v patičce ---------------------------------------------------- */
  var roky = document.querySelectorAll("[data-rok]");
  Array.prototype.forEach.call(roky, function (prvek) {
    prvek.textContent = String(new Date().getFullYear());
  });
})();

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
    });
  }

  /* --- Formulář přes mailto -------------------------------------------- */
  /* Statický web nemá backend — formulář poskládá e-mail a otevře
     poštovního klienta návštěvníka. Adresu lze změnit atributem
     data-prijemce na elementu <form>. */
  var formulare = document.querySelectorAll("form[data-mailto]");

  Array.prototype.forEach.call(formulare, function (form) {
    form.addEventListener("submit", function (udalost) {
      udalost.preventDefault();

      if (typeof form.reportValidity === "function" && !form.reportValidity()) {
        return;
      }

      var prijemce = form.getAttribute("data-prijemce") || "doprava@idispecink.cz";
      var predmet = form.getAttribute("data-predmet") || "Poptávka z webu";
      var radky = [];

      Array.prototype.forEach.call(form.elements, function (prvek) {
        if (!prvek.name || prvek.type === "submit" || prvek.type === "button") return;
        var popisek = prvek.getAttribute("data-popisek") || prvek.name;
        var hodnota = (prvek.value || "").trim();
        if (!hodnota) return;
        radky.push(popisek + ": " + hodnota);
      });

      radky.push("", "— Odesláno z webu idispecink.cz");

      var odkaz =
        "mailto:" + encodeURIComponent(prijemce) +
        "?subject=" + encodeURIComponent(predmet) +
        "&body=" + encodeURIComponent(radky.join("\n"));

      /* Navigace přes odkaz, ne přes window.location — protokol mailto
         tak spolehlivěji předá řízení poštovnímu klientovi. */
      var spousteci = document.createElement("a");
      spousteci.href = odkaz;
      spousteci.style.display = "none";
      document.body.appendChild(spousteci);
      spousteci.click();
      document.body.removeChild(spousteci);

      var stav = form.querySelector(".formular-stav");
      if (stav) {
        stav.textContent =
          "Otevírám váš poštovní klient s předvyplněnou zprávou. " +
          "Pokud se nic nestalo, napište nám přímo na " + prijemce + ".";
      }
    });
  });

  /* --- Rok v patičce ---------------------------------------------------- */
  var roky = document.querySelectorAll("[data-rok]");
  Array.prototype.forEach.call(roky, function (prvek) {
    prvek.textContent = String(new Date().getFullYear());
  });
})();

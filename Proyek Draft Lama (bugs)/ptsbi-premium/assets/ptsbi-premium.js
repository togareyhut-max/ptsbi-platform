(function () {
  "use strict";
  document.documentElement.classList.add("ptsbi-js");
  var header = document.querySelector(".site-header, header#masthead, .elementor-location-header");
  if (header) header.setAttribute("role", "banner");
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener("click", function (e) {
      var id = link.getAttribute("href");
      if (!id || id.length < 2) return;
      var target = document.querySelector(id);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });
})();

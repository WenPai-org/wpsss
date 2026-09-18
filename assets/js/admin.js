(function () {
  "use strict";

  document.querySelectorAll(".js-wpsss-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var form = btn.closest("form");
      if (form) {
        form.submit();
      }
    });
  });
})();

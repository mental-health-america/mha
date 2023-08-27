
  (function () {
  "use strict";
  // Hide the close button on initial load
  document.getElementById("closeButton2").style.display = "none";

  // Add event listener to open the div and hide the first button
  document.getElementById("focusButton2").addEventListener("click", () => {
  const editKeys2 = document.getElementById("edit-keys2");
  const gscIID1 = document.getElementById("gsc-i-id1");
  const focusButton2 = document.getElementById("focusButton2");
  const closeButton2 = document.getElementById("closeButton2");
  editKeys2.style.display = "block";
  gscIID1.focus();
  focusButton2.style.display = "none";
  closeButton2.style.display = "block";
});

  // Add event listener to close the div and show the first button
  document.getElementById("closeButton2").addEventListener("click", () => {
  const editKeys2 = document.getElementById("edit-keys2");
  const focusButton2 = document.getElementById("focusButton2");
  const closeButton2 = document.getElementById("closeButton2");
  editKeys2.style.display = "none";
  focusButton2.style.display = "block";
  closeButton2.style.display = "none";
});
})(jQuery);


/* global functions */

/* initialize */
function init() {
  // Add back button in style of margin numbers
  var b = document.getElementsByTagName('body')[0];
  var lup = document.createElement('a');
  lup.setAttribute('href', 'javascript:history.go(-1)');
  lup.innerHTML = '&laquo; back';
  lup.style.zIndex = "99";
  lup.style.position = 'fixed';
  lup.style.padding = "6px 6px 6px 0";
  lup.style.color = "#999";
  lup.style.background = "#f6f6ff";
  lup.style.fontFamily = '"Lucida Grande", "Lucida Sans Unicode", sans-serif';
  lup.style.fontSize = '0.9em';
  lup.style.width = '8em';
  lup.style.left = "-2.8em";
  lup.style.textAlign = "right";
  lup.onmouseover = function () { lup.style.color = "#000"; };
  lup.onmouseout = function () { lup.style.color = "#999"; };
  b.insertBefore(lup, b.firstChild);
}
window.onload = init;

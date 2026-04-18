function setActiveNav(id) {
  $('.sidebar .nav-link').removeClass('active');
  $('#' + id).addClass('active');
}

function togglePassword() {
  const $input = $('#password');
  const $icon = $('#toggleIcon');

  const isHidden = $input.attr('type') === 'password';

  $input.attr('type', isHidden ? 'text' : 'password');

  $icon.toggleClass('fa-eye', !isHidden);
  $icon.toggleClass('fa-eye-slash', isHidden);
}

function togglePwd(inputId, iconId) {
  const $input = $('#' + inputId);
  const $icon  = $('#' + iconId);

  const hidden = $input.attr('type') === 'password';

  $input.attr('type', hidden ? 'text' : 'password');

  $icon.toggleClass('fa-eye', !hidden);
  $icon.toggleClass('fa-eye-slash', hidden);
}

function updateClock() {
  const now = new Date();

  $('.clock').text(
    now.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    }) +
    ' · ' +
    now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  );
}   

function updateStrength(val) {
  const $bars  = ['s1','s2','s3','s4'].map(id => $('#' + id));
  const $label = $('#strengthLabel');

  const colors = ['#D63031','#e17055','#fdcb6e','#52C78A'];
  const labels = ['Weak','Fair','Good','Strong'];

  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  $bars.forEach((b, i) => {
    b.css('background', i < score ? colors[score - 1] : 'var(--border)');
  });

  $label
    .text(val.length ? (labels[score - 1] || 'Weak') : '')
    .css('color', val.length ? (colors[score - 1] || colors[0]) : '');
}

/* ── Toast ── */
function showToast(message, isError = true) {
  let $root = $('#toast-root');

  if ($root.length === 0) {
    $root = $('<div id="toast-root"></div>').appendTo('body');
  }

  const icon = isError ? 'fa-circle-exclamation' : 'fa-circle-check';

  const $toast = $(`
    <div class="toast ${isError ? 'error' : ''}">
      <i class="fas ${icon}"></i> ${message}
    </div>
  `);

  $root.append($toast);

  setTimeout(function () {
    $toast.addClass('out');
    setTimeout(function () {
      $toast.remove();
    }, 280);
  }, 5000);
}

$(document).ready(function () {
  updateClock();
  setInterval(updateClock, 1000);
});
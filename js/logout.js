let seconds = 5;
const $numEl = $('#countdownNum');

const timer = setInterval(() => {
  seconds--;
  $numEl.text(seconds);

  if (seconds <= 0) {
    clearInterval(timer);
    window.location.href = 'login.php';
  }
}, 1000);

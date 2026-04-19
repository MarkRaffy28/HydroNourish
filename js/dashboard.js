const Dashboard = {
  refreshInterval: 5000,
  apiUrl: 'dashboard.php?ajax=1',

  init() {
    this.updateLatest();
    setInterval(() => this.updateLatest(), this.refreshInterval);

    if (typeof window.loadRecentActivities === 'function') {
      window.loadRecentActivities();
    }
  },

  updateLatest() {
    $.getJSON(`${this.apiUrl}&type=latest&_=${Date.now()}`)
      .done((data) => {
        if (data && data.id) {
          this.renderSensorData(data);
          this.updateErrorBanner(data);
        }
      })
      .fail((error) => console.error('Dashboard Update Error:', error));
  },

  renderSensorData(d) {
    EnvSoilUI.updateAll(d);

    this.updateTank($('#water-pct'), $('#water-bar'), d.water_level);
    this.updateTank($('#fert-pct'), $('#fert-bar'), d.fert_level);

    const wDist = d.water_distance !== null && d.water_distance !== undefined ? d.water_distance + ' cm' : '-- cm';
    const fDist = d.fert_distance !== null && d.fert_distance !== undefined ? d.fert_distance + ' cm' : '-- cm';
    $('#water-dist').text(wDist);
    $('#fert-dist').text(fDist);
  },

  updateTank($pctEl, $barEl, val) {
    $pctEl.text(`${val}%`);
    $barEl.css('height', `${val}%`);

    if (val <= 20) {
      $barEl.css('background', 'linear-gradient(180deg, #ff4d4d 0%, #b30000 100%)');
    } else {
      $barEl.css('background', '');
    }
  },

  updateErrorBanner(d) {
    EnvSoilUI.updateErrorBanner(d);
  },
};

$(function () {
  Dashboard.init();
});

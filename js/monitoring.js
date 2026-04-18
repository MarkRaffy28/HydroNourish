const CHART_DEFAULTS = {
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 400 },
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1b221b',
      borderColor: '#2e3d2e',
      borderWidth: 1,
      titleColor: '#8a9b8a',
      bodyColor: '#c8ddc8',
      padding: 10,
      titleFont: { family: 'Fira Code', size: 10 },
      bodyFont:  { family: 'Fira Code', size: 11, weight: '600' },
    }
  },
  scales: {
    x: { display: false },
    y: {
      grid:  { color: 'rgba(30,46,30,0.6)', drawBorder: false },
      ticks: { color: '#566656', font: { family: 'Fira Code', size: 10 }, maxTicksLimit: 4 },
      border: { display: false }
    }
  }
};

function makeDataset(color, fill = true) {
  return {
    data: [],
    borderColor: color,
    backgroundColor: fill ? color.replace(')', ', 0.08)').replace('rgb', 'rgba') : 'transparent',
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 4,
    pointHoverBackgroundColor: color,
    tension: 0.4,
    fill: fill ? 'origin' : false,
  };
}

let charts = {};

$(function() {
  charts = {
    temp:  new Chart($('#chartTemp')[0],  { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(255,171,0)')]   }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
    hum:   new Chart($('#chartHum')[0],   { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(41,182,246)')]   }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
    pres:  new Chart($('#chartPres')[0],  { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(206,147,216)')]  }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
    soil:  new Chart($('#chartSoil')[0],  { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(0,230,118)')]    }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
    water: new Chart($('#chartWater')[0], { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(0,230,118)')]    }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
    fert:  new Chart($('#chartFert')[0],  { type:'line', data:{ labels:[], datasets:[makeDataset('rgb(255,171,0)')]    }, options: JSON.parse(JSON.stringify(CHART_DEFAULTS)) }),
  };

  refreshAll();
  setInterval(refreshAll, 5000);
});

function fmtTime(dtStr) {
  if (!dtStr) return '--';
  const d = new Date(dtStr);
  return d.toLocaleTimeString('en-US', { hour12: false });
}

function fmtDateTime(dtStr) {
  if (!dtStr) return '--';
  const d = new Date(dtStr);
  return d.toLocaleString('en-US', { hour12: false });
}

function badge(val, onLabel = 'ON', offLabel = 'OFF') {
  if (val == 1) return `<span class="badge badge-blue"><i class="fas fa-check"></i> ${onLabel}</span>`;
  return `<span class="badge" style="background:var(--bg); color:var(--text-muted); border:1px solid var(--border)">${offLabel}</span>`;
}

function errBadge(val) {
  return val == 1
    ? `<span class="badge-error" style="animation:none;font-size:0.6rem;"><i class="fas fa-xmark"></i> ERR</span>`
    : `<span class="badge badge-green" style="font-size:0.6rem;">OK</span>`;
}

// GSM Quality mapping removed - handled by EnvSoilUI

function updateTank($pctEl, $barEl, val) {
  if (val == null || val < 0) {
    $pctEl.text('ERR');
    $barEl.css('width', '0%').attr('class', 'progress-fill red');
    return;
  }
  $pctEl.text(val + '%');
  $barEl.css('width', val + '%').attr('class', 'progress-fill ' + (val <= 20 ? 'red' : val <= 40 ? 'solar' : 'water'));
}

// Card state management removed - handled by EnvSoilUI

function updateLatest(d) {
  if (!d || !d.id) return;

  // Use shared UI component logic (wrapped to prevent crash)
  try {
    EnvSoilUI.updateAll(d);
    EnvSoilUI.updateErrorBanner(d);
  } catch(e) { console.warn("EnvSoilUI update error:", e); }

  // Update RTC Time in Hero
  $('#rtc-time-display').text(d.rtc_time || '--:--:--');

  // Still update tanks and pumps as they have unique elements on this page
  updateTank($('#water-pct'), $('#water-bar'), d.water_level);
  updateTank($('#fert-pct'),  $('#fert-bar'),  d.fert_level);

  // Debug distance fields
  console.log("Tank Distances:", { water: d.water_distance, fert: d.fert_distance });

  // Update Tank distances - Explicit check for null/undefined to allow 0
  const wDist = (d.water_distance !== null && d.water_distance !== undefined) ? d.water_distance + ' cm' : '-- cm';
  const fDist = (d.fert_distance !== null && d.fert_distance !== undefined) ? d.fert_distance + ' cm' : '-- cm';
  $('#water-dist').text(wDist);
  $('#fert-dist').text(fDist);

  // Update Tank Card Borders (Low Level Logic)
  $('#tank-water-card').toggleClass('low-level', d.water_low == 1).toggleClass('optimal-level', d.water_low == 0);
  $('#tank-fert-card').toggleClass('low-level', d.fert_low == 1).toggleClass('optimal-level', d.fert_low == 0);

  const $wIcon = $('#pump-water-icon'), $wStat = $('#pump-water-status'), $wBadge = $('#pump-water-badge');
  const $fIcon = $('#pump-fert-icon'), $fStat = $('#pump-fert-status'), $fBadge = $('#pump-fert-badge');

  if (d.watering == 1) {
    $wIcon.attr('class', 'fas fa-water water').css('color', '');
    $wStat.text('Running');
    $wBadge.attr('class', 'badge-active').html('<i class="fas fa-circle-check"></i> Active');
  } else {
    $wIcon.attr('class', 'fas fa-water').css('color', 'var(--text-muted)');
    $wStat.text('Idle');
    $wBadge.attr('class', 'badge').css({'background': 'var(--bg)', 'color': 'var(--text-muted)'}).html('Idle');
  }

  if (d.fertigating == 1) {
    $fIcon.attr('class', 'fas fa-seedling solar').css('color', '');
    $fStat.text('Running');
    $fBadge.attr('class', 'badge-active').html('<i class="fas fa-circle-check"></i> Active');
  } else {
    $fIcon.attr('class', 'fas fa-seedling').css('color', 'var(--text-muted)');
    $fStat.text('Idle');
    $fBadge.attr('class', 'badge').css({'background': 'var(--bg)', 'color': 'var(--text-muted)'}).html('Idle');
  }
}

function updateCharts(rows) {
  if (!rows || rows.length === 0) return;
  const labels = rows.map(r => fmtTime(r.created_at));
  const map = {
    temp:  rows.map(r => parseFloat(r.temperature)  || 0),
    hum:   rows.map(r => parseFloat(r.humidity)     || 0),
    pres:  rows.map(r => parseFloat(r.pressure)     || 0),
    soil:  rows.map(r => parseInt(r.soil_percent)   || 0),
    water: rows.map(r => parseInt(r.water_level)    || 0),
    fert:  rows.map(r => parseInt(r.fert_level)     || 0),
  };

  for (const [key, chart] of Object.entries(charts)) {
    chart.data.labels = labels;
    chart.data.datasets[0].data = map[key];
    chart.update('none');
  }

  const last = rows[rows.length - 1];
  $('#lbl-temp').text(parseFloat(last.temperature).toFixed(1) + ' °C');
  $('#lbl-hum').text(parseFloat(last.humidity).toFixed(1)    + ' %');
  $('#lbl-pres').text(parseFloat(last.pressure).toFixed(0)    + ' hPa');
  $('#lbl-soil').text(last.soil_percent  + ' %');
  $('#lbl-water').text(last.water_level   + ' %');
  $('#lbl-fert').text(last.fert_level    + ' %');
}

function updateLog(rows) {
  const $tbody = $('#logBody');
  $('#logCount').text(rows.length + ' rows');

  if (!rows || rows.length === 0) {
    $tbody.html(`<tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:24px">No data yet</td></tr>`);
    return;
  }

  const errFlags = r => {
    const hasError = [r.soil_error, r.water_error, r.fert_error, r.temp_error, r.hum_error, r.pres_error, r.rtc_error, r.gsm_error]
      .some(v => v == 1) || r.gsm_signal == -1;
    
    return hasError
      ? `<span class="badge-error" style="animation:none;font-size:0.6rem;padding:2px 5px;"><i class="fas fa-xmark"></i> ERRORS</span>`
      : `<span class="badge badge-green" style="font-size:0.6rem;padding:2px 5px;">CLEAR</span>`;
  };

  const rowsHtml = [...rows].reverse().map(r => `
    <tr>
      <td style="color:var(--text-muted)">${r.id}</td>
      <td>${fmtDateTime(r.created_at)}</td>
      <td>${r.soil_percent ?? '--'}%</td>
      <td>${r.water_level  ?? '--'}% <small style="color:var(--text-muted)">(${r.water_distance ?? '--'}cm)</small></td>
      <td>${r.fert_level   ?? '--'}% <small style="color:var(--text-muted)">(${r.fert_distance ?? '--'}cm)</small></td>
      <td>${parseFloat(r.temperature  ?? 0).toFixed(1)}</td>
      <td>${parseFloat(r.humidity     ?? 0).toFixed(1)}</td>
      <td>${parseFloat(r.pressure     ?? 0).toFixed(0)}</td>
      <td>${r.gsm_carrier ? r.gsm_carrier + ': ' : ''}${r.gsm_signal ?? '--'}</td>
      <td>${badge(r.watering, 'ON', 'OFF')}</td>
      <td>${badge(r.fertigating, 'ON', 'OFF')}</td>
      <td>${errFlags(r)}</td>
    </tr>
  `).join('');
  $tbody.html(rowsHtml);
}

function fetchLatest() {
  $.getJSON('monitoring.php?ajax=1&type=latest&_=' + Date.now())
    .done(updateLatest)
    .fail(e => console.warn('Latest fetch error:', e));
}

function fetchHistory() {
  $.getJSON('monitoring.php?ajax=1&type=history&limit=30&_=' + Date.now())
    .done(data => {
      updateCharts(data);
      updateLog(data);
    })
    .fail(e => console.warn('History fetch error:', e));
}

function refreshAll() {
  fetchLatest();
  fetchHistory();
}

const EnvSoilUI = {
  updateAll(d) {
    if (!d || !d.id) return;

    const soilAvgText = d.avg ? ` (Avg: ${d.avg.soil}%)` : '';
    this.setMetric('val-soil', d.soil_percent, '%');
    this.setSubLabel(
      'sub-soil',
      (d.soil_error == 1 ? '⚠ Sensor Error' : d.soil_percent < 30 ? 'Dry — critical' : 'Moisture OK') + soilAvgText,
    );
    this.setState('card-soil', d.soil_error == 1 ? 'error' : d.soil_percent < 30 ? 'warn' : 'optimal');

    const tempAvgText = d.avg ? ` (Avg: ${d.avg.temp}°C)` : '';
    this.setMetric('val-temp', parseFloat(d.temperature || 0).toFixed(1), '°C');
    this.setSubLabel(
      'sub-temp',
      (d.temp_error == 1 ? '⚠ Sensor Error' : d.temperature > 35 ? 'High temp' : 'Normal') + tempAvgText,
    );
    this.setState('card-temp', d.temp_error == 1 ? 'error' : d.temperature > 35 ? 'warn' : 'optimal');

    const humAvgText = d.avg ? ` (Avg: ${d.avg.hum}%)` : '';
    this.setMetric('val-hum', parseFloat(d.humidity || 0).toFixed(1), '%');
    this.setSubLabel('sub-hum', (d.hum_error == 1 ? '⚠ Sensor Error' : 'Environment RH') + humAvgText);
    this.setState('card-hum', d.hum_error == 1 ? 'error' : 'optimal');

    const presAvgText = d.avg ? ` (Avg: ${Math.round(d.avg.pres)} hPa)` : '';
    this.setMetric('val-pres', Math.round(d.pressure || 0), ' hPa');
    this.setSubLabel('sub-pres', (d.pres_error == 1 ? '⚠ Sensor Error' : 'Atmospheric OK') + presAvgText);
    this.setState('card-pres', d.pres_error == 1 ? 'error' : 'optimal');
  },

  setMetric(id, val, unit) {
    $(`#${id}`).html(`${val}<span class="stat-unit"> ${unit}</span>`);
  },

  setSubLabel(id, text) {
    $(`#${id}`).text(text);
  },

  setState(id, state) {
    const $el = $(`#${id}`);
    if (!$el.length) return;

    $el.removeClass('error warn optimal green solar water red');
    $el.find('.stat-icon-wrap, .stat-value').removeClass('green solar water red');
    $el.addClass(state);

    const $badge = $el.find('.stat-badge');
    if ($badge.length) {
      const badgeMap = { error: 'ALERT', warn: 'WARN', optimal: 'OPTIMAL' };
      const classMap = { error: 'alert', warn: 'warn', optimal: 'up' };
      $badge.text(badgeMap[state] || state.toUpperCase());
      $badge.attr('class', 'stat-badge ' + (classMap[state] || 'up'));
    }
  },

  updateErrorBanner(d, panelId = 'errorPanel', chipsId = 'errorChips') {
    const errors = [];
    if (d.soil_error == 1) errors.push('Soil Sensor');
    if (d.temp_error == 1) errors.push('Temperature');
    if (d.hum_error == 1) errors.push('Humidity');
    if (d.pres_error == 1) errors.push('Pressure');
    if (d.water_error == 1) errors.push('Water Tank');
    if (d.fert_error == 1) errors.push('Fertilizer Tank');

    if (d.rtc_error == 1) errors.push('RTC Clock');

    const $panel = $(`#${panelId}`);
    const $chips = $(`#${chipsId}`);

    if (errors.length > 0 && $panel.length && $chips.length) {
      $panel.addClass('visible');
      $chips.html(errors.map((e) => `<span class="error-chip">${e}</span>`).join(''));
    } else if ($panel.length) {
      $panel.removeClass('visible');
    }
  },
};

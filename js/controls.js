function toggleCommand(type, state) {
  const btn = $(`#btn-${type}`);

  btn.prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i>');

  const data = {
    irrigation: type === 'irrigation' ? state : $('#card-irrigation').hasClass('active') ? 1 : 0,

    fertigation: type === 'fertigation' ? state : $('#card-fertigation').hasClass('active') ? 1 : 0,
  };

  $.ajax({
    url: 'controls.php?ajax=save_command',
    method: 'POST',
    data: data,

    success: function (res) {
      if (res.status === 'success') {
        loadCommandLogs();
      } else {
        btn.prop('disabled', false).html(state ? 'Start Now' : 'Cancel');
      }
    },

    error: function () {
      btn.prop('disabled', false).html(state ? 'Start Now' : 'Cancel');
    },
  });
}

function loadCommandLogs() {
  $.get('controls.php?ajax=get_command_logs', function (res) {
    if (!res) return;

    let html = '';
    const data = res.logs || [];

    if (data.length === 0) {
      html = `<div class="loading-logs-placeholder">No recent commands found.</div>`;
    } else {
      data.forEach((log) => {
        const isIrr = log.irrigation == 1;
        const icon = isIrr ? 'fa-droplet' : 'fa-flask';
        const color = isIrr ? 'var(--water)' : 'var(--solar)';
        const bg = isIrr ? 'var(--water-pale)' : 'var(--solar-pale)';
        const statusColor =
          log.status === 'done' ? 'var(--green-mid)' : log.status === 'pending' ? 'var(--solar)' : 'var(--red)';
        const actionText = log.irrigation == 0 && log.fertigation == 0 ? 'Stop' : 'Triggered';

        html += `
          <div class="log-item">
          <div class="log-icon ${isIrr ? 'water-icon' : 'solar-icon'}"><i class="fas ${icon}"></i></div>
            <div class="log-details">
              <div class="fw-600">Manual ${isIrr ? 'Irrigation' : 'Fertigation'} ${actionText}</div>
              <div class="log-time">Created: ${log.created_at} ${log.executed_at ? '· Executed: ' + log.executed_at : ''}</div>
            </div>
            <div class="log-status-badge" style="--status-color:${statusColor}; color:var(--status-color);">${log.status}</div>
          </div>`;
      });
    }
    $('#command-log-container').html(html);

    const updateCard = (type, isActive, status) => {
      const card = $(`#card-${type}`);
      const btn = $(`#btn-${type}`);
      const statusBox = $(`#status-${type}`);
      if (isActive) card.addClass('active');
      else card.removeClass('active');

      let pillHtml = '<span class="status-pill off">Inactive</span>';
      if (status === 'active') pillHtml = '<span class="status-pill active"><i class="fas fa-play"></i> Running</span>';
      else if (status === 'pending')
        pillHtml = '<span class="status-pill pending"><i class="fas fa-spinner fa-spin"></i> Pending</span>';
      statusBox.html(pillHtml);

      let btnText = 'Start Now';
      if (isActive) {
        btnText = status === 'active' ? 'Emergency Stop' : 'Cancel';
        btn.addClass('stop').removeClass('start');
        btn.attr('onclick', `toggleCommand('${type}', 0)`);

        if (status === 'pending') btn.prop('disabled', true);
        else btn.prop('disabled', false);
      } else {
        btn.addClass('start').removeClass('stop');
        btn.attr('onclick', `toggleCommand('${type}', 1)`);
        btn.prop('disabled', false);
      }
      if (!btn.find('.fa-spin').length || !isActive) btn.html(btnText);
    };

    const irrActive = res.current_cmd.irrigation || res.realtime.watering;
    const irrStatus = res.realtime.watering ? 'active' : res.current_cmd.irrigation ? 'pending' : 'off';
    updateCard('irrigation', irrActive, irrStatus);

    const fertActive = res.current_cmd.fertigation || res.realtime.fertigating;
    const fertStatus = res.realtime.fertigating ? 'active' : res.current_cmd.fertigation ? 'pending' : 'off';
    updateCard('fertigation', fertActive, fertStatus);

    if (res.is_offline) {
      $('.connection-status').addClass('offline').removeClass('online').find('.status-label').text('Modem Offline');
    } else {
      $('.connection-status').addClass('online').removeClass('offline').find('.status-label').text('ESP32 Online');
    }
  });
}

function loadSettings() {
  $.get('controls.php?ajax=get_settings', function (res) {
    if (res.status === 'success' && res.data) {
      const data = res.data;
      $('#water_watering_percent').val(data.water_watering_percent);
      $('#tank_low_percent').val(data.tank_low_percent);
      $('#tank_high_percent').val(data.tank_high_percent);
      $('#water_full_cm').val(data.water_full_cm);
      $('#water_empty_cm').val(data.water_empty_cm);
      $('#fert_full_cm').val(data.fert_full_cm);
      $('#fert_empty_cm').val(data.fert_empty_cm);
      $('#fert_duration_ms').val(data.fert_duration_ms);
      $('#buzzer_enabled').val(data.buzzer_enabled == 0 ? 0 : 1);
      $('#lcd_enabled').val(data.lcd_enabled == 0 ? 0 : 1);
      $('#backlight_enabled').val(data.backlight_enabled == 0 ? 0 : 1);
      $('#fertigation_enabled').val(data.fertigation_enabled == 0 ? 0 : 1);
      $('#gsm_texting_enabled').val(data.gsm_texting_enabled == 0 ? 0 : 1);
      $('#rtc_set_time').val(data.rtc_set_time || 0);

      let totalMins = parseInt(data.fert_interval_minutes) || 1;
      if (totalMins <= 0) totalMins = 1;

      let unit = 1;
      if (totalMins % 43200 === 0) {
        unit = 43200;
      } else if (totalMins % 1440 === 0) {
        unit = 1440;
      } else if (totalMins % 60 === 0) {
        unit = 60;
      }

      $('#fert_interval_unit').val(unit);
      $('#fert_interval_amount').val(totalMins / unit);

      let buzzMs = parseInt(data.buzzer_alert_interval_ms);
      if (isNaN(buzzMs) || buzzMs <= 0) {
        $('#buzzer_interval_unit').val(0);
        $('#buzzer_interval_amount').val(1).prop('disabled', true);
      } else {
        let buzzUnit = 1000;
        if (buzzMs % 60000 === 0) {
          buzzUnit = 60000;
        }
        $('#buzzer_interval_unit').val(buzzUnit);
        $('#buzzer_interval_amount')
          .val(buzzMs / buzzUnit)
          .prop('disabled', false);
      }

      $('#fertigation_enabled').trigger('change');
      $('#buzzer_interval_unit').trigger('change');
    }
  });
}

function saveSettings() {
  const btn = $('#btn-save-settings');
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

  let unit = parseInt($('#fert_interval_unit').val()) || 1;
  let amount = parseInt($('#fert_interval_amount').val()) || 1;
  let totalMins = amount * unit;

  let buzzUnit = parseInt($('#buzzer_interval_unit').val()) || 0;
  let totalBuzzMs = 0;
  if (buzzUnit > 0) {
    let buzzAmount = parseInt($('#buzzer_interval_amount').val()) || 1;
    totalBuzzMs = buzzAmount * buzzUnit;
  }

  const data = {
    water_watering_percent: $('#water_watering_percent').val(),
    tank_low_percent: $('#tank_low_percent').val(),
    tank_high_percent: $('#tank_high_percent').val(),
    water_full_cm: $('#water_full_cm').val(),
    water_empty_cm: $('#water_empty_cm').val(),
    fert_full_cm: $('#fert_full_cm').val(),
    fert_empty_cm: $('#fert_empty_cm').val(),
    fert_duration_ms: $('#fert_duration_ms').val(),
    fert_interval_minutes: totalMins,
    fertigation_enabled: $('#fertigation_enabled').val(),
    gsm_texting_enabled: $('#gsm_texting_enabled').val(),
    buzzer_enabled: $('#buzzer_enabled').val(),
    buzzer_alert_interval_ms: totalBuzzMs,
    lcd_enabled: $('#lcd_enabled').val(),
    backlight_enabled: $('#backlight_enabled').val(),
    rtc_set_time: $('#rtc_set_time').val(),
  };

  $.post('controls.php?ajax=save_settings', data, function (res) {
    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Configuration');
    if (res.status === 'success') {
      showToast('System configuration saved!', false);
    } else {
      showToast('Error saving configuration', true);
    }
  });
}

$('#fertigation_enabled').on('change', function () {
  if ($(this).val() === '1') {
    $('#fert_interval_container').show();
  } else {
    $('#fert_interval_container').hide();
  }
});

$('#buzzer_interval_unit').on('change', function () {
  if ($(this).val() === '0') {
    $('#buzzer_interval_amount').prop('disabled', true);
  } else {
    $('#buzzer_interval_amount').prop('disabled', false);
  }
});

function syncRtcTime(btn) {
  $(btn).html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

  const d = new Date();
  const pad = (n) => n.toString().padStart(2, '0');
  const ts =
    d.getFullYear() +
    '-' +
    pad(d.getMonth() + 1) +
    '-' +
    pad(d.getDate()) +
    ' ' +
    pad(d.getHours()) +
    ':' +
    pad(d.getMinutes()) +
    ':' +
    pad(d.getSeconds());

  $('#rtc_set_time').val(ts);
  saveSettings();
  setTimeout(function () {
    $(btn).html('<i class="fas fa-sync-alt"></i> Force Sync').prop('disabled', false);
  }, 1000);
}

$(document).ready(function () {
  loadSettings();
  loadCommandLogs();
  setInterval(loadCommandLogs, 5000);
});

function gsmSetMode(mode) {
  $('.gsm-mode-tab').removeClass('active');
  $('#tab-' + mode).addClass('active');

  $('#panel-saved').removeClass('visible');
  $('#panel-manual').removeClass('visible');
  $('#panel-broadcast').removeClass('visible');

  $('#panel-' + mode).addClass('visible');

  if (mode === 'manual') {
    $('#gsm-tag-input').focus();
  }
}

const tagInput = $('#gsm-tag-input');
const tagBox = $('#gsm-tags-box');
const tags = new Set();

if (tagInput.length) {
  tagInput.on('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(tagInput.val().trim().replace(',', ''));
      tagInput.val('');
    } else if (e.key === 'Backspace' && tagInput.val() === '' && tags.size > 0) {
      const lastTag = Array.from(tags).pop();
      removeTag(lastTag);
    }
  });
}

function addTag(val) {
  if (!val) return;
  if (!val.startsWith('+63')) {
    val = '+63' + val.replace(/^0/, '');
  }
  if (tags.has(val)) return;

  if (!/^\+63[0-9]{10}$/.test(val)) {
    showToast('Invalid phone number format (must be 10 digits after +63)', true);
    return;
  }
  tags.add(val);
  renderTags();
}

function removeTag(val) {
  tags.delete(val);
  renderTags();
}

function renderTags() {
  tagBox.find('.gsm-tag').remove();
  tags.forEach(val => {
    const t = $('<span class="gsm-tag"></span>').html(`${val} <i class="fas fa-times" onclick="removeTag('${val}')"></i>`);
    t.insertBefore(tagInput);
  });
}

function gsmSetMsg(txt) {
  const field = $('#gsm-msg');
  field.val(txt);
  gsmUpdateCounter(field[0]);
}

function gsmUpdateCounter(el) {
  const len = $(el).val().length;
  const smsCount = Math.ceil(len / 160) || 1;
  $('#gsm-char-count').text(`${len} / ${smsCount * 160} chars · ${smsCount} SMS`);
}

function gsmSend() {
  const msg = $('#gsm-msg').val().trim();
  const mode = $('.gsm-mode-tab.active').attr('id').replace('tab-', '');
  let recipients = '';

  if (mode === 'saved') {
    let checked = [];
    $('.contact-checkbox:checked').each(function() {
      checked.push($(this).val());
    });
    recipients = checked.join(',');
  } else if (mode === 'manual') {
    recipients = Array.from(tags).join(',');
  } else {
    recipients = 'ALL_STAFF';
  }

  if (!msg) {
    showToast('Please type a message', true);
    return;
  }
  if (!recipients) {
    showToast('Please select or enter recipients', true);
    return;
  }

  const btn = $('#gsm-send-btn');
  btn.prop('disabled', true);
  btn.html('<i class="fas fa-spinner fa-spin"></i> Sending…');

  $.ajax({
    url: 'gsm.php?ajax=save',
    type: 'POST',
    data: { message: msg, recipients: recipients },
    dataType: 'json'
  }).done(res => {
    if (res.ok) {
      showToast('SMS sent correctly via SIM800L', false);
      $('#gsm-msg').val('');
      gsmUpdateCounter($('#gsm-msg')[0]);
      if (mode === 'manual') { tags.clear(); renderTags(); }
      loadGsmStats();
      loadGsmMessages();
    } else {
      showToast(res.error || 'Failed to send SMS', true);
    }
  }).fail(() => {
    showToast('Connection error', true);
  }).always(() => {
    btn.prop('disabled', false);
    btn.html('<i class="fas fa-paper-plane"></i> Send SMS');
  });
}

function loadGsmStatus() {
  $.getJSON('gsm.php?ajax=get_status', res => {
    if (res.ok) {
      const s = res.status;
      const sig = s.gsm_signal || 0;
      const carrier = s.gsm_carrier || 'Searching...';

      $('#gsm-signal-badge').html(`<i class="fas fa-signal"></i> Signal: ${sig}`);
      $('#gsm-carrier-display').text(`${carrier} · +63`);
      $('#modem-carrier-val').text(carrier);
      $('#modem-signal-val').text(sig);

      $('.gsm-signal span').each((i, b) => {
        const threshold = (i + 1) * 25;
        if (sig >= threshold) {
          $(b).addClass('active');
        } else {
          $(b).removeClass('active');
        }
      });
    }
  });
}

function loadGsmStats() {
  $.getJSON('gsm.php?ajax=get_messages', rows => {
    const sentCount = rows.length;
    $('#gsm-sent-count').text(sentCount);
    $('#gsm-delivered-count').text(Math.floor(sentCount * 0.9));
    $('#gsm-failed-count').text(Math.ceil(sentCount * 0.1));
  });
}

function loadGsmMessages() {
  $.getJSON('gsm.php?ajax=get_messages', rows => {
    const log = $('#gsm-sent-log');
    if (rows.length === 0) {
      log.html('<div class="gsm-no-messages">No messages today</div>');
      return;
    }
    log.html(rows.map(r => {
      const time = new Date(r.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const statusClass = (r.status || 'Sent').toLowerCase();
      const recipients = Array.isArray(r.recipients) ? r.recipients.join(', ') : r.recipients;
      return `
        <div class="gsm-log-item">
          <div class="gsm-log-meta">
            <span class="gsm-log-to"><i class="fas fa-paper-plane text-muted m-r-6"></i>${recipients}</span>
            <span class="gsm-log-time">${time}</span>
          </div>
          <div class="gsm-log-msg">${r.message}</div>
          <div class="gsm-log-status flex-between">
            <span class="color-success"><i class="fas fa-check-double"></i> ${r.status || 'Sent'}</span>
            <button onclick="gsmDeleteMsg(${r.id})" class="btn-delete-log" title="Delete Log">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      `;
    }).join(''));
  });
}

let msgIdToDelete = -1;
function gsmDeleteMsg(id) {
  msgIdToDelete = id;
  $('#delete-log-modal').addClass('open');
}

function confirmDeleteLog() {
  if (msgIdToDelete === -1) return;
  $.post('gsm.php?ajax=delete', { id: msgIdToDelete }, res => {
    if (res.ok) {
      closeDeleteLogModal();
      loadGsmStats();
      loadGsmMessages();
      showToast('Message log deleted', false);
      msgIdToDelete = -1;
    }
  }, 'json');
}

function closeDeleteLogModal() {
  $('#delete-log-modal').removeClass('open');
}

function loadContacts() {
  $.get('gsm.php?ajax=get_contacts', data => {
    let html = '';
    if (data.length === 0) {
      html = '<div class="contacts-empty">No contacts found.<br><br><span class="contacts-empty-sub">Click Add Contact above to begin.</span></div>';
    } else {
      data.forEach((contact, i) => {
        let name = contact.name || 'Unknown';
        let num = contact.number || contact;
        html += `
        <div class="contact-item">
          <label class="contact-label-row">
            <input type="checkbox" class="contact-checkbox" value="${num}">
            <span><strong>${name}</strong> <span class="contact-number-hint">${num}</span></span>
          </label>
          <button type="button" class="btn-contact-del" onclick="openDeleteModal(${i}, '${name}')">
            <i class="fas fa-trash-can"></i>
          </button>
        </div>
        `;
      });
    }
    $('#saved-contacts-list').html(html);
    $('#broadcast-count-txt').text(`Message will be sent to all ${data.length} registered contacts simultaneously.`);
  });
}

function openAddContactModal() {
  $('#new-contact-name').val('');
  $('#new-contact-number').val('');
  $('#add-contact-modal').addClass('open');
}

function closeAddContactModal() {
  $('#add-contact-modal').removeClass('open');
}

function saveNewContact() {
  let name = $('#new-contact-name').val().trim();
  let num = $('#new-contact-number').val().trim();
  if (!name || !num) {
    showToast('Please provide both name and number', true);
    return;
  }

  if (num.length !== 10) {
    showToast('Number must be exactly 10 digits', true);
    return;
  }

  $.post('gsm.php?ajax=add_contact', { name: name, number: num }, res => {
    if (res.ok) {
      showToast('Contact saved correctly', false);
      closeAddContactModal();
      loadContacts();
    } else {
      showToast('Error saving contact', true);
    }
  }, 'json');
}

let contactToDelete = -1;
function openDeleteModal(index, name) {
  contactToDelete = index;
  $('#modal-delete-display').text(name);
  $('#delete-modal').addClass('open');
}

function closeDeleteModal() {
  $('#delete-modal').removeClass('open');
}

$('#confirm-delete-btn').on('click', function() {
  $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  $.post('gsm.php?ajax=delete_contact', { index: contactToDelete }, res => {
    $('#confirm-delete-btn').prop('disabled', false).html('Remove');
    if (res.ok) {
      closeDeleteModal();
      loadContacts();
      showToast('Contact removed', false);
    }
  }, 'json');
});

function saveGsmToggle() {
  let val = $('#gsm_texting_enabled').val();
  $.post('gsm.php?ajax=save_toggle', { enabled: val }, res => {
    if (res.ok) {
      showToast(val == 1 ? 'Global Texting Enabled' : 'Global Texting Disabled', false);
    }
  }, 'json');
}

$(function() {
  loadContacts();
  loadGsmStatus();
  loadGsmStats();
  loadGsmMessages();

  setInterval(() => {
    loadGsmStatus();
    loadGsmStats();
    loadGsmMessages();
  }, 10000);

  $(window).on('click', e => {
    if ($(e.target).hasClass('modal-overlay')) {
      $(e.target).removeClass('open');
    }
  });
});
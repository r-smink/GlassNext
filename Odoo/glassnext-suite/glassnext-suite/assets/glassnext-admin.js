jQuery(function($){
  var rowIndex = $('#gn-other-costs-container .gn-other-cost-row').length;

  $('#gn-add-cost').on('click', function(){
    var html = '<div class="gn-other-cost-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:flex-end;">' +
      '<div><label>Omschrijving</label><input type="text" class="regular-text gn-oc-desc" name="gn_other_costs[' + rowIndex + '][description]" value=""></div>' +
      '<div><label>Bedrag excl. btw</label><input type="number" step="0.01" min="0" class="gn-oc-amount" name="gn_other_costs[' + rowIndex + '][amount]" value="0"></div>' +
      '<button type="button" class="button gn-remove-cost">Verwijder</button>' +
      '</div>';
    $('#gn-other-costs-container').append(html);
    rowIndex++;
  });

  $('#gn-other-costs-container').on('click', '.gn-remove-cost', function(){
    var rows = $('#gn-other-costs-container .gn-other-cost-row');
    if (rows.length > 1) {
      $(this).closest('.gn-other-cost-row').remove();
    } else {
      $(this).closest('.gn-other-cost-row').find('input').val('');
    }
  });

  // Tabs
  $('.gn-tabs-nav .gn-tab-link').on('click', function(){
    var tab = $(this).data('tab');
    $('.gn-tabs-nav .gn-tab-link, .gn-tab-panel').removeClass('active');
    $(this).addClass('active');
    $('.gn-tab-panel[data-tab="' + tab + '"]').addClass('active');

    // Re-init TinyMCE when switching to the email tab (editor was in a hidden container)
    if (tab === 'email' && typeof tinymce !== 'undefined') {
      setTimeout(function(){
        var editor = tinymce.get('gn_email_body_template');
        if (!editor) {
          tinymce.execCommand('mceAddEditor', true, 'gn_email_body_template');
        } else {
          editor.show();
        }
      }, 50);
    }
  });

  // Logo media uploader
  var logoFrame;
  $('#gn-choose-logo').on('click', function(e){
    e.preventDefault();
    if (logoFrame) { logoFrame.open(); return; }
    logoFrame = wp.media({
      title: 'Kies een logo voor de bevestigingsmail',
      button: { text: 'Logo gebruiken' },
      library: { type: 'image' },
      multiple: false
    });
    logoFrame.on('select', function(){
      var att = logoFrame.state().get('selection').first().toJSON();
      $('#gn_email_logo_id').val(att.id);
      $('#gn-email-logo-preview').html('<img src="' + att.url + '" style="max-height:80px;max-width:300px;border:1px solid #ddd;padding:4px;background:#fff;" />');
      $('#gn-remove-logo').show();
    });
    logoFrame.open();
  });

  $('#gn-remove-logo').on('click', function(e){
    e.preventDefault();
    $('#gn_email_logo_id').val('');
    $('#gn-email-logo-preview').html('');
    $('#gn-remove-logo').hide();
  });
});

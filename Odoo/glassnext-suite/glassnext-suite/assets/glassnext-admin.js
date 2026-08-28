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
  });
});

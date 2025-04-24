jQuery(function ($) {
  var tpl = wp.template('smartgrid-meta-row');

  // Add new row
  $('#smartgrid-meta-add').on('click', function (e) {
    e.preventDefault();
    $('#smartgrid-meta-repeater').append(
      tpl({
        data: { key: '', type: '' },
        placeholder: 'Select field…',
      })
    );
  });

  // Remove row
  $('#smartgrid-meta-repeater').on('click', '.smartgrid-meta-remove', function (e) {
    e.preventDefault();
    $(this).closest('.smartgrid-meta-row').remove();
  });

  // Sync hidden inputs
  $('#smartgrid-meta-repeater').on('change', '.smartgrid-meta-field, .smartgrid-meta-type', function () {
    var $row = $(this).closest('.smartgrid-meta-row'),
      key = $row.find('.smartgrid-meta-field').val(),
      type = $row.find('.smartgrid-meta-type').val();

    $row.find('input[name*="[enabled]"]').attr('name', 'smartgrid_filters[meta][' + key + '][enabled]');

    $row
      .find('input[name*="[type]"]')
      .attr('name', 'smartgrid_filters[meta][' + key + '][type]')
      .val(type);
  });
});

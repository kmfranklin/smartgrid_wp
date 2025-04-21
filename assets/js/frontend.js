jQuery(document).ready(function ($) {
  $('.smartgrid-container').each(function () {
    var $container = $(this);
    var gridId = $container.data('grid-id');

    $.post(
      smartgridVars.ajax_url,
      {
        action: 'smartgrid_fetch',
        grid_id: gridId,
      },
      function (response) {
        if (response.success) {
          $container.html(response.data.html);
        } else {
          $container.html('<p class="smartgrid-error">' + response.data + '</p>');
        }
      }
    );
  });
});

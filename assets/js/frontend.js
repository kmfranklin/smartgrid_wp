jQuery(function ($) {
  var container = $('.smartgrid-container');
  var loadMoreWrap = $('.smartgrid-load-more-wrap');

  // Performs the AJAX fetch for a given page
  function fetchPage(page, append) {
    var gridId = container.data('grid-id');

    // Show loading if first page
    if (!append) {
      container.html('<div class="smartgrid-loading">Loading grid...</div>');
    }

    $.post(
      smartgridVars.ajax_url,
      {
        action: 'smartgrid_fetch',
        grid_id: gridId,
        paged: page,
      },
      function (res) {
        if (res.success) {
          var html = res.data.html;

          if (append) {
            // Strip the outer wrapper before appending
            var itemsHtml = html.replace(/^<div class="smartgrid-items">/, '').replace(/<\div>$/, '');
            container.find('.smartgrid-items').append(itemsHtml);
          } else {
            container.html(html);
          }

          // Update the current page
          container.data('page', res.data.next_page);

          // Render or clear the Load More button
          if (res.data.more) {
            loadMoreWrap.html('<button class="smartgrid-load-more">View More</button>');
          } else {
            loadMoreWrap.empty();
          }
        } else {
          container.html('<p class="smartgrid-error">' + res.data + '</p>');
        }
      }
    );
  }

  // Initial load (page 1)
  fetchPage(1, false);

  // Delegate click on the dynamic button
  loadMoreWrap.on('click', '.smartgrid-load-more', function () {
    var nextPage = container.data('page') || 2;
    fetchPage(nextPage, true);
  });
});

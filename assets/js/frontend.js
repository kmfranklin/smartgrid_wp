/**
 * SmartGrid frontend script
 *
 * Handles initial AJAX load and "View More" pagination for each [smartgrid] instance
 *
 * @requires jQuery
 */
jQuery(function ($) {
  /**
   * Fetch a page of items via AJAX.
   *
   * @param {jQuery} $container The grid container element.
   * @param {number} page       The page number to fetch.
   * @param {boolean} append    Whether to append to existing items.
   */
  function fetchPage($container, page, append) {
    var gridId = $container.data('grid-id');
    var data = {
      action: 'smartgrid_fetch',
      grid_id: gridId,
      paged: page,
    };

    // Include taxonomy filters
    var $filterForm = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');
    $filterForm.find('input[name^="tax"]').each(function () {
      if (this.checked) {
        var name = this.name;
        data[name] = data[name] || [];
        data[name].push(this.value);
      }
    });

    // Show loading state on first load
    if (!append) {
      $container.html('<div class="smartgrid-loading">Loading grid...</div>');
    }

    $.post(smartgridVars.ajax_url, data, function (res) {
      if (res.success) {
        // Parse the incoming HTML into a jQuery object
        var $response = $(res.data.html);
        var $items = $response.filter('.smartgrid-items').add($response.find('.smartgrid-items'));

        if (append) {
          // Append only the children (individual cards)
          $container.find('.smartgrid-items').append($items.children());
        } else {
          // Replace the container with the new items wrapper
          $container.html($items);
        }

        // Update the page counter
        $container.data('page', res.data.next_page);

        // Render or remove the Load More button
        var $loadMoreWrap = $container.siblings('.smartgrid-load-more-wrap');
        if (res.data.more) {
          $loadMoreWrap.html('<button class="smartgrid-load-more">View More</button>');
        } else {
          $loadMoreWrap.empty();
        }
      } else {
        $container.html('<p class="smartgrid-error">' + res.data + '</p>');
      }
    });
  }

  // Initialize each grid on the page
  $('.smartgrid-container').each(function () {
    var $container = $(this);
    var gridId = $container.data('grid-id');
    var $loadMoreWrap = $container.siblings('.smartgrid-load-more-wrap');
    var $filterForm = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');

    // Intercept filter form submissions
    $filterForm.on('submit', function (e) {
      e.preventDefault();
      fetchPage($container, 1, false);
    });

    // Initial load
    fetchPage($container, initialPage, false);

    // Delegate View More clicks
    $loadMoreWrap.on('click', '.smartgrid-load-more', function () {
      var nextPage = $container.data('page') || 2;
      fetchPage($container, nextPage, true);
    });
  });
});

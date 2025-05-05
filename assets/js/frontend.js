/**
 * SmartGrid frontend script
 *
 * Handles AJAX grid loading, filtering, pagination, and noUiSlider initialization.
 *
 * @requires jQuery, noUiSlider
 */
jQuery(function ($) {
  /**
   * Initialize all noUiSlider instances on the page.
   * Also called after each AJAX refresh to bind new sliders.
   */
  function initSliders() {
    $('.smartgrid-slider').each(function () {
      var sliderEl = this;
      var $slider = $(this);
      var key = $slider.data('key');
      var min = parseFloat($slider.data('min'));
      var max = parseFloat($slider.data('max'));

      // Prevent double-init
      if (sliderEl.noUiSlider) {
        return;
      }

      noUiSlider.create(sliderEl, {
        start: [min, max],
        connect: true,
        range: { min: min, max: max },
        tooltips: [true, true],
        format: {
          to: function (v) {
            return Math.round(v);
          },
          from: function (v) {
            return Number(v);
          },
        },
      });

      // Push slider values into the hidden inputs
      sliderEl.noUiSlider.on('update', function (values) {
        var $form = $slider.closest('form');
        $form.find('input[name="meta[' + key + '][min]"]').val(values[0]);
        $form.find('input[name="meta[' + key + '][max]"]').val(values[1]);
      });
    });
  }

  /**
   * Perform the AJAX request to fetch grid items.
   *
   * @param {jQuery}  $container The grid container element.
   * @param {number}  page       The page number to fetch.
   * @param {boolean} append     Whether to append to existing items.
   */
  function fetchPage($container, page, append) {
    var gridId = $container.data('grid-id');
    var $form = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');
    var data = {
      action: 'smartgrid_fetch',
      grid_id: gridId,
      paged: page,
    };

    // — Taxonomy filters (dropdown & checkboxes)
    $form.find('[name^="tax"]').each(function () {
      if (!this.value) return;
      if (this.type === 'checkbox') {
        data[this.name] = data[this.name] || [];
        data[this.name].push(this.value);
      } else {
        data[this.name] = this.value;
      }
    });

    // — Meta checkboxes
    $form.find('[name^="meta"][type="checkbox"]').each(function () {
      if (!this.checked) return;
      data[this.name] = data[this.name] || [];
      data[this.name].push(this.value);
    });

    $form.find('input[type="hidden"][name^="meta"]').each(function () {
      data[this.name] = this.value;
    });

    // Show loading on first load
    if (!append) {
      $container.html('<div class="smartgrid-loading">Loading grid...</div>');
    }

    $.post(smartgridVars.ajax_url, data, function (res) {
      if (!res.success) {
        $container.html('<p class="smartgrid-error">' + res.data + '</p>');
        return;
      }

      var $html = $(res.data.html);
      var $items = $html.filter('.smartgrid-items').add($html.find('.smartgrid-items'));
      var $loadWrap = $container.siblings('.smartgrid-load-more-wrap');

      if (append) {
        $container.find('.smartgrid-items').append($items.children());
      } else {
        $container.html($items);
      }

      // Update pagination state
      $container.data('page', res.data.next_page);

      // Render or remove the Load More button
      if (res.data.more) {
        $loadWrap.html('<button class="smartgrid-load-more">View More</button>');
      } else {
        $loadWrap.empty();
      }

      // Re-init any sliders in the newly loaded content
      initSliders();
    });
  }

  // — Initial slider setup on page load
  initSliders();

  // — Hook up each [smartgrid] instance on the page
  $('.smartgrid-container').each(function () {
    var $container = $(this);
    var gridId = $container.data('grid-id');
    var $loadWrap = $container.siblings('.smartgrid-load-more-wrap');
    var $form = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');

    // Intercept filter form submit
    $form.on('submit', function (e) {
      e.preventDefault();
      fetchPage($container, 1, false);
    });

    // Initial load (page 1)
    fetchPage($container, 1, false);

    // Delegate click on dynamically-added "View More" button
    $loadWrap.on('click', '.smartgrid-load-more', function () {
      var nextPage = $container.data('page') || 2;
      fetchPage($container, nextPage, true);
    });
  });
});

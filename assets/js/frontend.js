// SmartGrid Frontend Script
// Handles AJAX grid loading, filtering, pagination, and noUiSlider initialization.

jQuery(function ($) {
  // -----------------------------
  // 1) Global slider "Clear" handler
  // -----------------------------
  $(document).on('click', '.smartgrid-slider-clear', function () {
    var $wrap = $(this).closest('.smartgrid-slider-wrapper');
    var sliderEl = $wrap.find('.smartgrid-slider')[0];
    if (!sliderEl || !sliderEl.noUiSlider) return;

    var slider = sliderEl.noUiSlider;
    var min = parseFloat($wrap.find('.smartgrid-slider').data('min'));
    var max = parseFloat($wrap.find('.smartgrid-slider').data('max'));
    slider.set([min, max]);

    $wrap.closest('form').trigger('submit');
  });

  // -----------------------------
  // 2) Initialize all noUiSlider instances on the page.
  //    Called on page load and after each AJAX refresh.
  // -----------------------------
  function initSliders() {
    $('.smartgrid-slider').each(function () {
      var sliderEl = this;
      var $slider = $(this);
      var key = $slider.data('key');
      var min = parseFloat($slider.data('min'));
      var max = parseFloat($slider.data('max'));

      // Prevent double-init
      if (sliderEl.noUiSlider) return;

      noUiSlider.create(sliderEl, {
        start: [min, max],
        connect: true,
        range: { min: min, max: max },
        format: {
          to: v => Math.round(v),
          from: v => Number(v),
        },
      });

      // Update hidden inputs and custom range text
      sliderEl.noUiSlider.on('update', function (values) {
        var lo = values[0],
          hi = values[1];
        var $wrapper = $slider.closest('.smartgrid-slider-wrapper');

        // hidden inputs
        $wrapper.find('input[name="meta[' + key + '][min]"]').val(lo);
        $wrapper.find('input[name="meta[' + key + '][max]"]').val(hi);

        // custom range label
        $wrapper.find('.smartgrid-slider-range').text(lo.toLocaleString() + ' – ' + hi.toLocaleString());
      });
    });
  }

  // -----------------------------
  // 3) Perform the AJAX request to fetch grid items.
  // -----------------------------
  function fetchPage($container, page, append, showLoader = true) {
    var gridId = $container.data('grid-id');
    var $form = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');
    var data = {
      action: 'smartgrid_fetch',
      grid_id: gridId,
      paged: page,
      s: $form.find('input.smartgrid-search-input').val() || '',
    };

    // Taxonomy filters
    $form.find('[name^="tax"]').each(function () {
      if (!this.value) return;
      if (this.type === 'checkbox') {
        data[this.name] = data[this.name] || [];
        data[this.name].push(this.value);
      } else {
        data[this.name] = this.value;
      }
    });

    // Meta checkbox filters
    $form.find('[name^="meta"][type="checkbox"]').each(function () {
      if (!this.checked) return;
      data[this.name] = data[this.name] || [];
      data[this.name].push(this.value);
    });

    // Meta range hidden inputs
    $form.find('input[type="hidden"][name^="meta"]').each(function () {
      data[this.name] = this.value;
    });

    // Show loader if desired
    if (showLoader && !append) {
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

      // Pagination state
      $container.data('page', res.data.next_page);

      // Load More button
      if (res.data.more) {
        $loadWrap.html('<button class="smartgrid-load-more">View More</button>');
      } else {
        $loadWrap.empty();
      }

      // Re-init new sliders
      initSliders();
    });
  }

  // -----------------------------
  // 4) Wire up each SmartGrid instance
  // -----------------------------
  initSliders();

  $('.smartgrid-container').each(function () {
    var $container = $(this);
    var gridId = $container.data('grid-id');
    var $loadWrap = $container.siblings('.smartgrid-load-more-wrap');
    var $form = $('.smartgrid-filters[data-grid-id="' + gridId + '"]');

    // Form submit
    $form.on('submit', function (e) {
      e.preventDefault();
      fetchPage($container, 1, false, true);
    });

    // Live search debounce
    var searchTimer;
    $form.on('input', 'input.smartgrid-search-input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        fetchPage($container, 1, false, false);
      }, 300);
    });

    // Initial load
    fetchPage($container, 1, false, true);

    // Load more
    $loadWrap.on('click', '.smartgrid-load-more', function () {
      var nextPage = $container.data('page') || 2;
      fetchPage($container, nextPage, true, true);
    });
  });
});

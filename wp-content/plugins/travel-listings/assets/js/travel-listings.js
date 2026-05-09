/**
 * Travel Listings - Frontend JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when DOM is ready
  $(document).ready(function () {
    initTravelListings();
    initLanguageDropdown();
  });

  /**
   * Initialize language dropdown functionality
   */
  function initLanguageDropdown() {
    var $dropdowns = $(".travel-lang-dropdown");

    if (!$dropdowns.length) {
      return;
    }

    // Toggle dropdown on button click
    $dropdowns.find(".lang-dropdown-toggle").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $dropdown = $(this).closest(".travel-lang-dropdown");
      var isOpen = $dropdown.hasClass("open");

      // Close all other dropdowns
      $dropdowns.not($dropdown).removeClass("open");
      $dropdowns
        .not($dropdown)
        .find(".lang-dropdown-toggle")
        .attr("aria-expanded", "false");

      // Toggle current dropdown
      $dropdown.toggleClass("open", !isOpen);
      $(this).attr("aria-expanded", !isOpen);
    });

    // Close dropdown when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".travel-lang-dropdown").length) {
        $dropdowns.removeClass("open");
        $dropdowns.find(".lang-dropdown-toggle").attr("aria-expanded", "false");
      }
    });

    // Close dropdown on escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        $dropdowns.removeClass("open");
        $dropdowns.find(".lang-dropdown-toggle").attr("aria-expanded", "false");
      }
    });
  }

  function initTravelListings() {
    var $filterForms = $('[data-filter-form], #travel-filter-form');
    var $listingsContainer = $("#travel-listings-container");
    var $categoryChips = $(".featured-category-chip, .travel-filter-chip");
    var $advancedFilters = $(".travel-filter-advanced");
    var $quickFilterRows = $(".travel-filter-variant-form__chips");
    var filterState = getEmptyFilterState();

    // Infinite scroll state
    var isLoading = false;
    var currentPage = 1;
    var maxPages = parseInt($listingsContainer.data("max-pages")) || 1;
    var postsPerPage = parseInt($listingsContainer.data("posts-per-page")) || 12;
    var observer = null;

    if (!$listingsContainer.length) {
      return;
    }

    if ($filterForms.length) {
      filterState = getFormValues($filterForms.first());
      syncAllForms(filterState);
      initAdvancedFilterToggles();
      initQuickFilterIndicators();

      $filterForms.on("submit", function (e) {
        e.preventDefault();
        filterState = getFormValues($(this));
        syncAllForms(filterState);
        filterListings();
      });

      $(document).on("click", ".travel-filter-reset, #reset-filter", function () {
        filterState = getEmptyFilterState();
        syncAllForms(filterState);
        filterListings();
      });

      var filterTimeout;
      $filterForms.find(".filter-input").on("change", function () {
        filterState = getFormValues($(this).closest("form"));
        syncAllForms(filterState);
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(function () {
          filterListings();
        }, 300);
      });

      if ($categoryChips.length) {
        $categoryChips.on("click", function () {
          var isResetChip = $(this).data("reset-filters");
          var selectedSlug = $(this).data("category-slug");

          if (isResetChip) {
            filterState = getEmptyFilterState();
            syncAllForms(filterState);
            filterListings();
            return;
          }

          var nextValue = selectedSlug;

          if ($(this).hasClass("is-active")) {
            nextValue = "";
          }

          filterState.category = nextValue;
          syncAllForms(filterState);
          filterListings();
        });
      }
    }

    function initAdvancedFilterToggles() {
      if (!$advancedFilters.length) {
        return;
      }

      var mobileQuery = window.matchMedia("(max-width: 640px)");

      function applyResponsiveDefault(force) {
        $advancedFilters.each(function () {
          var $details = $(this);

          if (!force && $details.data("userToggled")) {
            return;
          }

          $details.prop("open", !mobileQuery.matches);
          syncAdvancedToggleState($details);
        });
      }

      $advancedFilters.each(function () {
        syncAdvancedToggleState($(this));
      });

      $(document).on(
        "click",
        ".travel-filter-advanced__summary",
        function (event) {
          if ($(event.target).closest(".travel-filter-advanced__toggle").length) {
            return;
          }

          event.preventDefault();
        }
      );

      $(document).on(
        "click",
        ".travel-filter-advanced__toggle",
        function (event) {
          event.preventDefault();
          event.stopPropagation();

          var $details = $(this).closest(".travel-filter-advanced");
          var nextOpen = !$details.prop("open");

          $details.data("userToggled", true);
          $details.prop("open", nextOpen);
          syncAdvancedToggleState($details);
        }
      );

      $advancedFilters.on("toggle", function () {
        syncAdvancedToggleState($(this));
      });

      if (typeof mobileQuery.addEventListener === "function") {
        mobileQuery.addEventListener("change", function () {
          applyResponsiveDefault(false);
        });
      } else if (typeof mobileQuery.addListener === "function") {
        mobileQuery.addListener(function () {
          applyResponsiveDefault(false);
        });
      }

      applyResponsiveDefault(true);
    }

    function initQuickFilterIndicators() {
      if (!$quickFilterRows.length) {
        return;
      }

      function refreshIndicators() {
        $quickFilterRows.each(function () {
          syncQuickFilterIndicator($(this));
        });
      }

      refreshIndicators();

      $(window).on("resize", function () {
        refreshIndicators();
      });

      $quickFilterRows.on("scroll", function () {
        syncQuickFilterIndicator($(this));
      });
    }

    function syncAdvancedToggleState($details) {
      var isOpen = $details.prop("open");
      var $toggle = $details.find(".travel-filter-advanced__toggle").first();

      if ($toggle.length) {
        $toggle.attr("aria-expanded", isOpen ? "true" : "false");
      }
    }

    function getEmptyFilterState() {
      return {
        date_from: "",
        date_to: "",
        price_from: "",
        price_to: "",
        category: "",
      };
    }

    function getFormValues($form) {
      if (!$form.length) {
        return getEmptyFilterState();
      }

      return {
        date_from: $form.find('[name="date_from"]').first().val() || "",
        date_to: $form.find('[name="date_to"]').first().val() || "",
        price_from: $form.find('[name="price_from"]').first().val() || "",
        price_to: $form.find('[name="price_to"]').first().val() || "",
        category: $form.find('[name="category"]').first().val() || "",
      };
    }

    function setFormValues($form, values) {
      if (!$form.length) {
        return;
      }

      setFieldValue($form, "date_from", values.date_from);
      setFieldValue($form, "date_to", values.date_to);
      setFieldValue($form, "price_from", values.price_from);
      setFieldValue($form, "price_to", values.price_to);
      setFieldValue($form, "category", values.category);
    }

    function setFieldValue($form, fieldName, value) {
      var $field = $form.find('[name="' + fieldName + '"]').first();

      if ($field.length) {
        $field.val(value);
      }
    }

    function syncAllForms(values) {
      $filterForms.each(function () {
        setFormValues($(this), values);
      });

      syncCategoryChips(values);

      $quickFilterRows.each(function () {
        syncQuickFilterIndicator($(this));
      });
    }

    function syncCategoryChips(values) {
      if (!$categoryChips.length) {
        return;
      }

      var hasFilters = hasActiveFilters(values);

      $categoryChips.each(function () {
        var $chip = $(this);
        var isResetChip = !!$chip.data("reset-filters");
        var isActive = false;

        if (isResetChip) {
          isActive = !hasFilters;
        } else {
          isActive =
            !!values.category &&
            $chip.data("category-slug") === values.category;
        }

        $chip.toggleClass("is-active", isActive);
        $chip.attr("aria-pressed", isActive ? "true" : "false");
      });
    }

    function syncQuickFilterIndicator($row) {
      if (!$row.length) {
        return;
      }

      var $indicator = $row.find(".travel-filter-variant-form__chips-indicator");
      var $activeChip = $row.find(".travel-filter-chip.is-active").first();

      if (!$indicator.length || !$activeChip.length) {
        return;
      }

      var left = $activeChip.position().left;
      var width = $activeChip.outerWidth();

      $indicator.css({
        width: width + "px",
        transform: "translateX(" + left + "px)",
        opacity: 1,
      });
    }

    function hasActiveFilters(values) {
      return !!(
        values.date_from ||
        values.date_to ||
        values.price_from ||
        values.price_to ||
        values.category
      );
    }

    function filterListings() {
      var formData = {
        action: "filter_travel_listings",
        nonce: travelListings.nonce,
        date_from: filterState.date_from,
        date_to: filterState.date_to,
        price_from: filterState.price_from,
        price_to: filterState.price_to,
        category: filterState.category,
        posts_per_page: postsPerPage,
      };

      // Add loading state
      $listingsContainer.addClass("travel-listings-loading");
      $listingsContainer.css("opacity", "0.5");

      $.ajax({
        url: travelListings.ajaxurl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success && response.data.html) {
            // Filter out empty nodes and only keep article cards
            var $cards = $(response.data.html).filter(".travel-listing-card");
            $listingsContainer.empty().append($cards);

            // Re-add infinite scroll elements if there are more pages
            currentPage = 1;
            maxPages = response.data.max_pages || 1;
            $listingsContainer.data("page", 1);
            $listingsContainer.data("max-pages", maxPages);

            if (maxPages > 1) {
              var infiniteScrollHtml =
                '<div class="travel-listings-infinite-scroll">' +
                '<div id="travel-listings-sentinel" class="infinite-scroll-sentinel"></div>' +
                '<div id="travel-listings-loader" class="infinite-scroll-loader" style="display: none;">' +
                '<div class="loader-spinner"></div>' +
                "<span>Loading more listings...</span>" +
                "</div>" +
                '<div id="travel-listings-end" class="infinite-scroll-end" style="display: none;">' +
                "<span>All listings loaded</span>" +
                "</div>" +
                "</div>";
              $listingsContainer.append(infiniteScrollHtml);
              setupInfiniteScroll();
            }

            // Animate cards in
            animateCards();
          }
        },
        error: function (xhr, status, error) {
          console.error("Filter error:", error);
          $listingsContainer.html(
            '<div class="no-listings-found"><p>An error occurred. Please try again.</p></div>'
          );
        },
        complete: function () {
          $listingsContainer.removeClass("travel-listings-loading");
          $listingsContainer.css("opacity", "1");
        },
      });
    }

    function loadMoreListings() {
      if (isLoading || currentPage >= maxPages) {
        return;
      }

      isLoading = true;
      var nextPage = currentPage + 1;

      // Show loader
      var $loader = $("#travel-listings-loader");
      $loader.show();

      var formData = {
        action: "load_more_travel_listings",
        nonce: travelListings.nonce,
        paged: nextPage,
        posts_per_page: postsPerPage,
      };

      if ($filterForms.length) {
        formData.date_from = filterState.date_from;
        formData.date_to = filterState.date_to;
        formData.price_from = filterState.price_from;
        formData.price_to = filterState.price_to;
        formData.category = filterState.category;
      }

      $.ajax({
        url: travelListings.ajaxurl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success && response.data.html) {
            // Parse only the article cards from response
            var $newCards = $(response.data.html).filter(".travel-listing-card");

            // Find the infinite scroll container and insert cards before it
            var $infiniteScrollContainer = $listingsContainer.find(
              ".travel-listings-infinite-scroll"
            );
            if ($infiniteScrollContainer.length) {
              $infiniteScrollContainer.before($newCards);
            } else {
              $listingsContainer.append($newCards);
            }

            // Update page state
            currentPage = nextPage;
            maxPages = response.data.max_pages;
            $listingsContainer.data("page", currentPage);

            // Animate new cards
            animateNewCards($newCards);

            // Update infinite scroll UI
            updateInfiniteScrollUI();
          }
        },
        error: function (xhr, status, error) {
          console.error("Load more error:", error);
        },
        complete: function () {
          isLoading = false;
          $loader.hide();
        },
      });
    }

    function updateInfiniteScrollUI() {
      var $sentinel = $("#travel-listings-sentinel");
      var $loader = $("#travel-listings-loader");
      var $endMessage = $("#travel-listings-end");

      if (currentPage >= maxPages) {
        // Hide sentinel and loader, show end message
        $sentinel.hide();
        $loader.hide();
        if (maxPages > 1) {
          $endMessage.show();
        }
      } else {
        // Show sentinel, hide end message
        $sentinel.show();
        $endMessage.hide();
      }
    }

    function animateCards() {
      var $cards = $listingsContainer.find(".travel-listing-card");

      $cards.each(function (index) {
        var $card = $(this);
        $card.css({
          opacity: "0",
          transform: "translateY(20px)",
        });

        setTimeout(function () {
          $card.css({
            transition: "opacity 0.3s ease, transform 0.3s ease",
            opacity: "1",
            transform: "translateY(0)",
          });
        }, index * 50);
      });
    }

    function animateNewCards($cards) {
      $cards.each(function (index) {
        var $card = $(this);
        $card.css({
          opacity: "0",
          transform: "translateY(20px)",
        });

        setTimeout(function () {
          $card.css({
            transition: "opacity 0.3s ease, transform 0.3s ease",
            opacity: "1",
            transform: "translateY(0)",
          });
        }, index * 50);
      });
    }

    // Setup Intersection Observer for infinite scroll
    function setupInfiniteScroll() {
      var $sentinel = $("#travel-listings-sentinel");

      if (!$sentinel.length || !("IntersectionObserver" in window)) {
        return;
      }

      // Disconnect previous observer if exists
      if (observer) {
        observer.disconnect();
      }

      observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting && !isLoading && currentPage < maxPages) {
              loadMoreListings();
            }
          });
        },
        {
          root: null,
          rootMargin: "200px",
          threshold: 0,
        }
      );

      observer.observe($sentinel[0]);
    }

    // Initialize
    animateCards();
    setupInfiniteScroll();
    updateInfiniteScrollUI();
  }
})(jQuery);

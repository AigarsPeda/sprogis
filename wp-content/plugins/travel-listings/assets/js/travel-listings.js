/**
 * Travel Listings - Frontend JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when DOM is ready
  $(document).ready(function () {
    initTravelListings();
  });

  function initTravelListings() {
    var $filterForm = $("#travel-filter-form");
    var $listingsContainer = $("#travel-listings-container");
    var $resetBtn = $("#reset-filter");

    // Infinite scroll state
    var isLoading = false;
    var currentPage = 1;
    var maxPages = parseInt($listingsContainer.data("max-pages")) || 1;
    var postsPerPage = parseInt($listingsContainer.data("posts-per-page")) || 12;
    var observer = null;

    if (!$listingsContainer.length) {
      return;
    }

    // Handle form submission
    if ($filterForm.length) {
      $filterForm.on("submit", function (e) {
        e.preventDefault();
        filterListings();
      });

      // Handle reset button
      $resetBtn.on("click", function () {
        $filterForm[0].reset();
        filterListings();
      });

      // Optional: Auto-filter on input change (debounced)
      var filterTimeout;
      $filterForm.find(".filter-input").on("change", function () {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(function () {
          filterListings();
        }, 300);
      });
    }

    function filterListings() {
      var formData = {
        action: "filter_travel_listings",
        nonce: travelListings.nonce,
        date_from: $filterForm.find('[name="date_from"]').val(),
        date_to: $filterForm.find('[name="date_to"]').val(),
        price_from: $filterForm.find('[name="price_from"]').val(),
        price_to: $filterForm.find('[name="price_to"]').val(),
        category: $filterForm.find('[name="category"]').val(),
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

      // Include filter values if filter form exists
      if ($filterForm.length) {
        formData.date_from = $filterForm.find('[name="date_from"]').val();
        formData.date_to = $filterForm.find('[name="date_to"]').val();
        formData.price_from = $filterForm.find('[name="price_from"]').val();
        formData.price_to = $filterForm.find('[name="price_to"]').val();
        formData.category = $filterForm.find('[name="category"]').val();
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

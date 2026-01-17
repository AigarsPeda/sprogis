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

    if (!$filterForm.length) {
      return;
    }

    // Handle form submission
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

    function filterListings() {
      var formData = {
        action: "filter_travel_listings",
        nonce: travelListings.nonce,
        date_from: $filterForm.find('[name="date_from"]').val(),
        date_to: $filterForm.find('[name="date_to"]').val(),
        price_from: $filterForm.find('[name="price_from"]').val(),
        price_to: $filterForm.find('[name="price_to"]').val(),
        category: $filterForm.find('[name="category"]').val(),
        posts_per_page: 12,
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
            $listingsContainer.html(response.data.html);

            // Animate cards in
            animateCards();
          }
        },
        error: function (xhr, status, error) {
          console.error("Filter error:", error);
          $listingsContainer.html(
            '<div class="no-listings-found"><p>An error occurred. Please try again.</p></div>',
          );
        },
        complete: function () {
          $listingsContainer.removeClass("travel-listings-loading");
          $listingsContainer.css("opacity", "1");
        },
      });
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

    // Initial animation
    animateCards();
  }
})(jQuery);

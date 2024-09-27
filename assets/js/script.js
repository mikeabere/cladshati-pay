// assets/js/script.js

jQuery(function ($) {
  // Paystack payment handling
  if (typeof paystack !== "undefined") {
    const paystackPublicKey = cladshati_pay_vars.paystack_public_key;
    const paystackHandler = PaystackPop.setup({
      key: paystackPublicKey,
      onClose: function () {
        alert("Payment window closed.");
      },
      callback: function (response) {
        // Handle the successful payment here
        console.log(response);
        if (response.status === "success") {
          // You can redirect to the order received page or show a success message
          window.location.href = cladshati_pay_vars.order_received_url;
        }
      },
    });

    $("#cladshati-paystack-button").on("click", function (e) {
      e.preventDefault();
      paystackHandler.openIframe();
    });
  }

  // M-Pesa payment handling
  $("#cladshati-mpesa-form").on("submit", function (e) {
    e.preventDefault();
    const phoneNumber = $("#mpesa-phone-number").val();

    if (!phoneNumber) {
      showError("Please enter a valid phone number.");
      return;
    }

    // Disable the submit button and show loading state
    const submitButton = $(this).find('button[type="submit"]');
    submitButton.prop("disabled", true).text("Processing...");

    // Send AJAX request to process M-Pesa payment
    $.ajax({
      url: cladshati_pay_vars.ajax_url,
      type: "POST",
      data: {
        action: "cladshati_process_mpesa",
        phone_number: phoneNumber,
        nonce: cladshati_pay_vars.nonce,
      },
      success: function (response) {
        if (response.success) {
          showSuccess(
            "M-Pesa request sent. Please check your phone to complete the payment."
          );
          // You can redirect to a "payment pending" page or show instructions here
        } else {
          showError(
            response.data.message || "An error occurred. Please try again."
          );
        }
      },
      error: function () {
        showError("An error occurred. Please try again.");
      },
      complete: function () {
        // Re-enable the submit button
        submitButton.prop("disabled", false).text("Pay with M-Pesa");
      },
    });
  });

  function showError(message) {
    $(".cladshati-pay-error").text(message).show();
    $(".cladshati-pay-success").hide();
  }

  function showSuccess(message) {
    $(".cladshati-pay-success").text(message).show();
    $(".cladshati-pay-error").hide();
  }
});

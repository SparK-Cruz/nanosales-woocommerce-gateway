jQuery(document).ready(function ($) {
  var clipboard = new Clipboard('.clip');
  var qrelement = $('.qr-code');

  clipboard.on('success', function(e) {
    var $target = $(e.trigger);

    var oldText = $target.text();
    $target
      .text('✔︎')
      .prop('disabled', true);

    setTimeout(function() {
      $target
        .text(oldText)
        .prop('disabled', false);

    }, 2000);
  });

  new QRCode(qrelement[0], {
      text: qrelement.data('text'),
      width: 250,
      height: 250,
      colorDark : "#000034",
      colorLight : "#F4FAFF",
      correctLevel : QRCode.CorrectLevel.H
  });
});

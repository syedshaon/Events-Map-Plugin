jQuery(document).ready(function ($) {
  $(".select-marker-icon").click(function (e) {
    e.preventDefault();
    var mediaUploader = wp
      .media({
        title: "Select Custom Marker Icon",
        button: { text: "Use this icon" },
        multiple: false,
      })
      .on("select", function () {
        var attachment = mediaUploader.state().get("selection").first().toJSON();
        $("#events_on_map_marker_icon").val(attachment.url);
        $("#marker-icon-preview").attr("src", attachment.url).show();
      })
      .open();
  });
});

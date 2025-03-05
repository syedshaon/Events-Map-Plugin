jQuery(document).ready(function ($) {
  function bindImageUploader(button) {
    button.off("click").on("click", function (event) {
      event.preventDefault();

      let button = $(this);
      let imageField = button.siblings(".event-image-url");
      let previewImage = button.siblings(".event-image-preview");

      let frame = wp.media({
        title: "Select Event Image",
        multiple: false,
        library: { type: "image" },
        button: { text: "Use this image" },
      });

      frame.on("select", function () {
        let attachment = frame.state().get("selection").first().toJSON();
        imageField.val(attachment.url); // Store URL in hidden input
        previewImage.attr("src", attachment.url).show(); // Show preview
      });

      frame.open();
    });
  }

  // Bind to existing "Select Image" buttons
  $(".select-event-image").each(function () {
    bindImageUploader($(this));
  });

  // Handle adding new events
  $("#add-event").on("click", function () {
    let index = $("#events-table tbody tr").length;
    console.log("Adding event at index: ", index); // Debugging

    let newRow = `
      <tr>
          <td><input type="text" name="addresses[${index}][name]" /></td>
          <td><input type="date" name="addresses[${index}][start_date]" /></td>
          <td><input type="date" name="addresses[${index}][end_date]" /></td>
          <td><input type="text" name="addresses[${index}][organizer]" /></td> 
          <td><input type="text" name="addresses[${index}][location]" /></td> 
          <td>
              <input type="hidden" name="addresses[${index}][image]" class="event-image-url">
              <button type="button" class="button select-event-image">Select Image</button>
              <br>
              <img src="" width="50" class="event-image-preview" style="margin-top:5px; display:none;">
          </td>
          <td><button type="button" class="remove-event button">Remove</button></td>
      </tr>`;

    $("#events-table tbody").append(newRow);

    // Rebind image uploader for the new row
    bindImageUploader($("#events-table tbody tr:last-child .select-event-image"));
  });

  // Handle event removal
  $(document).on("click", ".remove-event", function () {
    let row = $(this).closest("tr");
    let eventName = row.find("input[name*='[name]']").val();
    let eventDate = row.find("input[name*='[start_date]']").val(); // Fixed from 'date'

    if (!eventName || !eventDate) {
      row.remove();
      return;
    }

    if (confirm("Are you sure you want to delete this event?")) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "delete_event",
          event_name: eventName,
          event_date: eventDate,
        },
        success: function (response) {
          if (response.success) {
            row.remove();
          } else {
            alert("Failed to delete event.");
          }
        },
        error: function () {
          alert("Error occurred while deleting event.");
        },
      });
    }
  });
});

document.addEventListener("DOMContentLoaded", function () {
  var calendarEl = document.getElementById("calendar");

  if (!calendarEl) {
    console.error("Calendar element not found.");
    return;
  }

  // ✅ Convert event data to FullCalendar format
  var formattedEvents = eventsData.events.map((event) => ({
    title: event.name,
    start: event.start_date,
    end: event.end_date,
    extendedProps: {
      location: event.location,
      image: event.image,
      organizer: event.organizer,
    },
  }));

  console.log("Formatted Events:", formattedEvents); // Debugging

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "dayGridMonth",
    events: formattedEvents,

    eventDidMount: function (info) {
      // ✅ Set event background color to blue
      info.el.style.backgroundColor = "#007BFF"; // Bootstrap blue
      info.el.style.color = "white"; // Ensure text remains visible

      // ✅ Add hover effect to show tooltip dynamically
      info.el.addEventListener("mouseenter", function () {
        let startDate = new Date(info.event.start);
        let endDate = new Date(info.event.end);

        let formattedDate = `${startDate.getDate()} to ${endDate.getDate()} ${endDate.toLocaleString("default", { month: "long" })}, ${endDate.getFullYear().toString().slice(-2)}`;

        showTooltip(info.el, info.event.title, info.event.extendedProps.organizer, formattedDate);
      });

      info.el.addEventListener("mouseleave", function () {
        hideTooltip();
      });

      // ✅ Make event title clickable
      info.el.style.cursor = "pointer";
      info.el.addEventListener("click", function () {
        centerMapOnEvent(info.event.extendedProps);
      });
    },
  });

  calendar.render();
});

// ✅ Function to show tooltip on hover
function showTooltip(el, title, organizer, date) {
  let tooltip = document.createElement("div");
  tooltip.className = "event-tooltip";
  tooltip.innerHTML = `<strong>${title}</strong><br>Organizer: ${organizer}<br>${date}`;
  document.body.appendChild(tooltip);

  let rect = el.getBoundingClientRect();
  tooltip.style.left = `${rect.left + window.scrollX + rect.width / 2}px`;
  tooltip.style.top = `${rect.top + window.scrollY - 40}px`;

  el.dataset.tooltipId = tooltip.id;
}

// ✅ Function to hide tooltip
function hideTooltip() {
  let tooltip = document.querySelector(".event-tooltip");
  if (tooltip) tooltip.remove();
}

function centerMapOnEvent(eventData) {
  if (!eventData.location) {
    console.warn("Event location not provided.");
    return;
  }

  if (eventMarkers[eventData.location]) {
    closeCurrentInfoWindow(); // ✅ Close any open info window

    const markerData = eventMarkers[eventData.location];
    map.setCenter(markerData.marker.getPosition());
    map.setZoom(9); // ✅ Adjust zoom level for better visibility
    markerData.infowindow.open(map, markerData.marker);
    currentInfoWindow = markerData.infowindow; // ✅ Store the newly opened info window
  } else {
    alert("Event location not found on the map.");
    console.error("Marker not found for location:", eventData.location);
  }
}

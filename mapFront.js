let map; // Global map variable
let eventMarkers = {}; // Store event markers and info windows

function formatDate(dateString) {
  const date = new Date(dateString);
  const day = date.getDate().toString().padStart(2, "0");
  const month = date.toLocaleString("en-US", { month: "long" });
  const year = date.getFullYear().toString().slice(-2);
  return `${day} ${month}, ${year}`;
}

function initMap() {
  const mapOptions = {
    center: { lat: 55.9533, lng: -3.1883 }, // Default center (Edinburgh)
    zoom: 8,
  };

  const mapElement = document.getElementById("map");
  if (!mapElement) {
    console.error("Map container not found.");
    return;
  }

  map = new google.maps.Map(mapElement, mapOptions);
  const geocoder = new google.maps.Geocoder();
  const customMarkerIcon = eventsData.markerIcon ? eventsData.markerIcon : "http://maps.google.com/mapfiles/ms/icons/red-dot.png";

  if (typeof eventsData !== "undefined" && Array.isArray(eventsData.events) && eventsData.events.length > 0) {
    eventsData.events.forEach((event, index) => {
      if (!event.location) return; // Skip events without a location

      setTimeout(() => {
        geocoder.geocode({ address: event.location }, (results, status) => {
          if (status === "OK" && results[0]) {
            const position = results[0].geometry.location;

            const marker = new google.maps.Marker({
              position: position,
              map: map,
              title: event.name,
              icon: {
                url: customMarkerIcon,
                scaledSize: new google.maps.Size(40, 40),
                anchor: new google.maps.Point(20, 40),
              },
              animation: google.maps.Animation.DROP,
            });

            const infowindow = new google.maps.InfoWindow({
              content: `
                  <div class="event-info">
                      ${event.image ? `<img src="${event.image}" alt="Event Image" width="100">` : ""}
                      <div>
                          <strong>${event.name}</strong><br>
                          <em>${formatDate(event.start_date)} to ${formatDate(event.end_date)}</em><br>
                          <strong>Location:</strong> ${event.location}<br>
                          <strong>Organizer:</strong> ${event.organizer}
                      </div>
                  </div>
              `,
            });

            marker.addListener("click", () => {
              closeCurrentInfoWindow(); // ✅ Close any open info window
              infowindow.open(map, marker);
              currentInfoWindow = infowindow;
            });

            // ✅ Store marker and info window for reference
            eventMarkers[event.location] = { marker, infowindow };
          } else {
            console.error("Geocode failed for location:", event.location, "Status:", status);
          }
        });
      }, index * 500); // Stagger geocoding requests
    });
  } else {
    console.warn("No valid event data found.");
  }
}

// ✅ Function to close the currently open info window
function closeCurrentInfoWindow() {
  if (currentInfoWindow) {
    currentInfoWindow.close();
    currentInfoWindow = null;
  }
}

// ✅ Ensure `initMap` is globally available
window.initMap = initMap;

// ✅ Attach event listeners for event list clicks
jQuery(document).ready(function ($) {
  $(".view-event-marker").on("click", function () {
    let address = $(this).data("location");

    if (!address) {
      alert("No location available.");
      return;
    }

    if (eventMarkers[address]) {
      closeCurrentInfoWindow(); // ✅ Close any open info window

      const markerData = eventMarkers[address];
      map.setCenter(markerData.marker.getPosition());
      markerData.infowindow.open(map, markerData.marker);
      currentInfoWindow = markerData.infowindow; // ✅ Store the newly opened info window
    } else {
      alert("Location marker not found on the map.");
    }
  });
});

let currentInfoWindow = null; // Store the currently open info window

function formatDate(dateString) {
  const date = new Date(dateString);

  // Extract day, month, and year
  const day = date.getDate().toString().padStart(2, "0"); // Ensures 2-digit day
  const month = date.toLocaleString("en-US", { month: "long" }); // Full month name
  const year = date.getFullYear().toString().slice(-2); // Last 2 digits of the year

  return `${day} ${month}, ${year}`;
}

function initMap() {
  // Default map options
  const mapOptions = {
    center: { lat: 55.953367141606684, lng: -3.2002647650107456 },

    zoom: 8,
  };

  // Ensure the map container exists
  const mapElement = document.getElementById("map");
  if (!mapElement) {
    console.error("Map container not found.");
    return;
  }

  // Create map instance
  const map = new google.maps.Map(mapElement, mapOptions);
  const customMarkerIcon = eventsData.markerIcon ? eventsData.markerIcon : "http://maps.google.com/mapfiles/ms/icons/red-dot.png";

  // Check if eventsData exists and has valid events
  if (typeof eventsData !== "undefined" && Array.isArray(eventsData.events) && eventsData.events.length > 0) {
    eventsData.events.forEach((event) => {
      if (!event.latitude || !event.longitude) return; // Skip invalid data

      const marker = new google.maps.Marker({
        position: { lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) },
        map: map,
        title: event.name,
        icon: {
          url: customMarkerIcon,
          scaledSize: new google.maps.Size(40, 40),
          anchor: new google.maps.Point(20, 40),
        },
        animation: google.maps.Animation.DROP, // Adds the falling effect
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
        infowindow.open(map, marker);
      });
    });
  } else {
    console.warn("No valid event data found.");
  }

  // Handle "View on Map" click event
  document.body.addEventListener("click", function (event) {
    if (event.target.classList.contains("view-event-marker")) {
      const lat = parseFloat(event.target.dataset.lat);
      const lng = parseFloat(event.target.dataset.lng);
      if (!isNaN(lat) && !isNaN(lng)) {
        map.setCenter({ lat, lng });
        map.setZoom(15);
      }
    }
  });

  // Apply dynamic map height and width from settings
  if (eventsData.mapHeight) {
    mapElement.style.height = eventsData.mapHeight;
  }
  if (eventsData.mapWidth) {
    mapElement.style.width = eventsData.mapWidth;
  }
}

// ✅ Ensure `initMap` is globally available
window.initMap = initMap;

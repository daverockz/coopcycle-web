import _ from 'lodash'

const mapsOptions = {
  types: ['address'],
  componentRestrictions: {
    country: (window.AppData.countryIso || 'fr').toUpperCase()
  }
}

const addressTypeToPropertyName = {
  postal_code: 'postalCode',
  locality: 'addressLocality'
}

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.AddressInput = function(el, options) {

  options = options || {
    elements: {}
  }

  if (!window.google) {
    console.error('Google Maps not loaded')
    return
  }

  google.maps.event.clearListeners(el, 'place_changed')
  const autocomplete = new google.maps.places.Autocomplete(el, mapsOptions)

  autocomplete.addListener('place_changed', function() {

    const place = autocomplete.getPlace()

    if (!place.geometry) {
      return;
    }

    if (options.elements.hasOwnProperty('latitude')) {
      options.elements.latitude.value = place.geometry.location.lat()
    }
    if (options.elements.hasOwnProperty('longitude')) {
      options.elements.longitude.value = place.geometry.location.lng()
    }

    if (options.hasOwnProperty('onLocationChange') && typeof options.onLocationChange === 'function') {
      options.onLocationChange({
        latitude: place.geometry.location.lat(),
        longitude: place.geometry.location.lng()
      })
    }

    const propertyNames = {}
    for (var i = 0; i < place.address_components.length; i++) {
      var addressType = place.address_components[i].types[0]
      if (addressTypeToPropertyName.hasOwnProperty(addressType)) {
        propertyNames[addressTypeToPropertyName[addressType]] = place.address_components[i].long_name
      }
    }

    _.each(propertyNames, (value, name) => {
      if (options.elements.hasOwnProperty(name)) {
        options.elements[name].value = value
      }
    })

  })
}
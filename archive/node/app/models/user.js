module.exports = function(data) {
    var _properties = {},
        _authed = false;

    // shallow copy
    if (typeof data === 'object') {
        for (var i in data) {
            _properties[i] = data[i];
        }
    }

    this.getDisplayName = function() {
        return _properties.screen_name;
    }

    this.getId = function() {
        return _properties.id;
    }

    this.setAuthed = function(authed) {
        _authed = authed;
    }

    this.isAuthed = function() {
        return _authed;
    }

    this.getFavourites = function() {
        return _properties.favourites || [];
    }

    this.get = function(key) {
        return _properties[key];
    }

    this.getFavouritesCount = function() {
        if (typeof _properties.favourites === 'undefined') {
            return 0;
        }
        return _properties.favourites.length;
    }

    this.getLatestFavouriteId = function() {
        if (typeof _properties.favourites === 'undefined') {
            return 0;
        }
        return _properties.favourites[0].id;
    }

    this.getData = function() {
        return _properties;
    }
}

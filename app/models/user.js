module.exports = function(data) {
    var _properties = {},
        _authed = false;

    // shallow copy
    if (typeof data === 'object') {
        for (var i in data) {
            _properties[i] = data[i];
        }
    }
}

var util = require("util"),
    User = require('../user');

var db = null;
require('../../db').getInstance(function(connection) {
    db = connection;
});

module.exports = function() {
    var _collection = 'users';

    this.getByTwitterId = function(id, callback) {
        db.collection(_collection, function(err, collection) {
            if (err) {
                util.debug("top error");
                throw err;
            }
            collection.findOne({'id':id}, function(err, result) {
                if (err) {
                    throw err;
                }
                if (typeof result === 'undefined') {
                    callback(null);
                } else {
                    var user = new User(result);
                    if (typeof callback === 'function') {
                        callback(user);
                    }
                }
            });
        });
    };

    this.createNew = function(data, callback) {
        db.collection(_collection, function(err, collection) {
            if (err) {
                throw err;
            }
            collection.insert(data, function(err, docs) {
                if (err) {
                    throw err;
                }
                // what is docs?
                util.debug(JSON.stringify(docs));
                var user = new User(docs);
                if (typeof callback === 'function') {
                    callback(user);
                }
            });
        });
    };

    this.getNew = function() {
        return new User();
    }
}

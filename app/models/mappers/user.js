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
                    callback(user);
                }
            });
        });
    };

    this.getByUsername = function(username, callback) {
        db.collection(_collection, function(err, collection) {
            if (err) {
                throw err;
            }
            collection.findOne({'screen_name':username}, function(err, result) {
                if (err) throw err;

                if (typeof result === 'undefined') {
                    callback(null);
                } else {
                    var user = new User(result);
                    callback(user);
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
                var user = new User(data);
                if (typeof callback === 'function') {
                    callback(user);
                }
            });
        });
    };

    this.updateById = function(id, data, callback) {
        db.collection(_collection, function(err, collection) {
            if (err) throw err;

            collection.update({'id':id}, data, function(err) {
                if (err) throw err;

                var user = new User(data);
                callback(user);
            });
        });
    };

    /* no likey
    this.updateFavourites = function(id, data, callback) {
        db.collection(_collection, function(err, collection) {
            if (err) throw err;

            collection.update({'id':id}, {"$pushAll": {"favourites": data}}, function(err, doc) {
                if (err) throw err;

                util.debug("updated user favourites");

                var user = new User(doc);
                callback(user);
            });
        });
    };
    */

    this.getNew = function() {
        return new User();
    }
}

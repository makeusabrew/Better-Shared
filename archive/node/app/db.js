var util = require("util"),
    mongo = require("mongodb");

module.exports = (function() {
    var db = null;

    return {
        getInstance: function(callback) {
            if (db === null) {
                // create, innit
                util.debug('creating new mongo DB connection');
                db = new mongo.Db('better_shared', new mongo.Server('localhost', mongo.Connection.DEFAULT_PORT, {}), {});
                db.open(function(err, client) {
                    if (err) {
                        util.debug("could not open mongo DB");
                        throw err;
                    }
                    util.debug("opened mongo connection");
                    callback(db);
                });
            } else {
                callback(db);
            }
        }
    };
})();

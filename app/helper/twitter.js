var util = require("util");

module.exports = {
    getAuth: function(req) {
        var auth = {
            token: null,
            secret: null
        };

        try {
            auth = JSON.parse(req.cookies.auth);
        } catch (e) {
            util.debug("couldn't parse auth cookie");
        }

        return {
            isAuthed: function() {
                return (auth.token != null && auth.secret != null);
            },

            getToken: function() {
                return auth.token;
            },

            getSecret: function() {
                return auth.secret;
            }
        }
    }
};

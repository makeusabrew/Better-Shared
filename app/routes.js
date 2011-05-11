module.exports = function(app) {
    /**
     * Home Page
     */
    app.get('/', function(req, res) {
        res.render('index', {
            authed: false
        });
    });

    /**
     * Auth endpoint (ping off to twitter)
     */
    app.get('/auth', function(req, res) {
        // redirect off to twitter
    });

    /**
     * Authed endpoint (return from twitter)
     */
    app.get('/authed', function(req, res) {
        // interpret twitter response, do stuff
    });
}

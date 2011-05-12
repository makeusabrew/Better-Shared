var OAuth = require("oauth").OAuth,
    util = require("util");

var oauth = new OAuth(
    'https://api.twitter.com/oauth/request_token',
    'https://api.twitter.com/oauth/access_token',
    'URF3oNKWUnTLQESUjrEfvg',
    'AitwGcDj5SInos1Jb909lYNMkszpDmEvhAfqCQYz2E',
    '1.0A',
    'http://twit3.dev:8124/authed',
    'HMAC-SHA1'
);

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
        oauth.getOAuthRequestToken(function(err, oauth_token, oauth_token_secret, results) {
            if (err) {
                util.debug('error requesting oauth token');
                return res.send(err);
            }
            // need to keep a hold of the token for a bit
            req.session.oauth_token_secret = oauth_token_secret;
            util.debug("saving oauth token secret in session ["+req.session.oauth_token_secret+"]");
            res.redirect("https://api.twitter.com/oauth/authorize?oauth_token="+oauth_token);
        });
    });

    /**
     * Authed endpoint (return from twitter)
     */
    app.get('/authed', function(req, res) {
        // interpret twitter response, do stuff
        req.session.oauth_verifier = req.oauth_verifier;
        util.log("using oauth token secret from session ["+req.session.oauth_token_secret+"]");
        util.log("using oauth token from URL param ["+req.query.oauth_token+"]");
        oauth.getOAuthAccessToken(req.query.oauth_token, req.session.oauth_token_secret, function (err, oauth_access_token, oauth_access_token_secret, results) {
            if (err) {
                util.debug('error requesting oauth access token');
                return res.send(err);
            }

            // done with session
            req.session.oauth_token_secret = null;

            // long live the cookies!
            util.debug("oauth_access_token ["+oauth_access_token+"] - oauth_access_token_secret ["+oauth_access_token_secret+"]");
            res.cookie('auth', JSON.stringify({"token":oauth_access_token, "secret":oauth_access_token_secret}));
            res.redirect('/');
        });
    });
}

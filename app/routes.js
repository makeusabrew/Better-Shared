var OAuth = require("oauth").OAuth,
    util = require("util"),
    TwitterHelper = require('./helper/twitter'),
    UserMapper = require('./models/mappers/user'),
    redis = require('redis');


var oauth = new OAuth(
    'https://api.twitter.com/oauth/request_token',
    'https://api.twitter.com/oauth/access_token',
    'URF3oNKWUnTLQESUjrEfvg',
    'AitwGcDj5SInos1Jb909lYNMkszpDmEvhAfqCQYz2E',
    '1.0A',
    'http://twit3.dev:8124/authed',
    'HMAC-SHA1'
);

// just grab one instance of a userMapper for now (todo: staticify?)
var userMapper = new UserMapper();

// redis pub/sub clients
var client1 = redis.createClient();
var client2 = redis.createClient();

client1.subscribe("get_favourites_backlog");
client1.on("message", function(channel, message) {
    util.debug(message);
    message = JSON.parse(message);
    switch (channel) {
        case 'get_favourites_backlog':
            userMapper.getByTwitterId(message.twitter_id, function(user) {
                var qStr = "http://api.twitter.com/1/favorites/"+user.getId()+".json?entities=true&page="+message.page;
                util.debug("GETting "+qStr);
                oauth.get(qStr, message.token, message.secret, function(err, data) {
                    if (err) throw err;

                    data = JSON.parse(data);

                    if (data.length) {
                        util.debug("got ["+data.length+"] favourites for page ["+message.page+"]");
                        userMapper.updateFavourites(user.getId(), data, function(user) {
                            util.debug("updated favourites");
                            client2.publish("get_favourites_backlog", JSON.stringify({
                                "twitter_id": message.twitter_id,
                                "page": ++message.page,
                                "token": message.token,
                                "secret": message.secret
                            }));
                        });
                    } else {
                        util.debug("got no more tweets - backlog complete");
                    }
                });
            });
            break;
        default:
            throw new Error('Unknown channel ['+channel+']');
            break;
    }
});

module.exports = function(app) {
    /**
     * Home Page
     */
    app.get('/', authState('any'), function(req, res) {
        util.debug("*** "+req.session.user_id);
        if (req.activeUser.isAuthed()) {
            var auth = TwitterHelper.getAuth(req);
            oauth.get("http://api.twitter.com/1/account/rate_limit_status.json", auth.getToken(), auth.getSecret(), function(err, data) {
                if (err) {
                    throw err;
                }
                var parsedData = JSON.parse(data);
                res.render('index', {
                    "activeUser": req.activeUser,
                    "apiInfo": parsedData
                });
            });
        } else {
            res.render('index', {
                "activeUser": req.activeUser
            });
        }
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

            oauth.get("http://api.twitter.com/1/account/verify_credentials.json", oauth_access_token, oauth_access_token_secret, function(err, data) {
                if (err) {
                    return res.send(err);
                }

                var parsedData = JSON.parse(data);

                userMapper.getByTwitterId(parsedData.id, function(user) {
                    if (user === null) {
                        // new user
                        userMapper.createNew(parsedData, function(user) {
                            // got user by now, defo
                            util.debug("created new user - adding favourites backlog message to queue");
                            client2.publish("get_favourites_backlog", JSON.stringify{
                                "twitter_id": parsedData.id,
                                "page": 1,
                                "token": oauth_access_token,
                                "secret": oauth_access_token_secret
                            }));
                            req.session.user_id = user.getId();
                            res.redirect('/');
                        });
                    } else {
                        util.debug("got user from DB ["+user.getDisplayName()+"]");
                        req.session.user_id = user.getId();
                        res.redirect('/');
                    }
                });
            });
        });
    });

    app.get('/user/:username', authState('any'), function(req, res) {
        res.render('user', {
            "activeUser": req.activeUser,
            "favourites": req.activeUser.getFavourites(),
            "user": req.user
        });
    });

    /**
     * param pre-conditions
     */

     app.param('username', function(req, res, next, username) {
        userMapper.getByUsername(username, function(user) {
            if (user === null) {
                return next(new Error('Could not find user'));
            }
            req.user = user;
            next();
        });
    });

    // pre-route checks
    function authState(authed) {
        return function(req, res, next) {
            userMapper.getByTwitterId(req.session.user_id, function(user) {
                if (user !== null) {
                    user.setAuthed(true);
                } else {
                    user = userMapper.getNew();
                }
                req.activeUser = user;
                if (authed === 'any') {
                    next();
                } else {
                    if (req.activeUser.isAuthed() !== authed) {
                        next(new Error('Incorrect auth state ['+req.activeUser.isAuthed().toString()+']'));
                    } else {
                        next();
                    }
                }
            });
        }
    }
}

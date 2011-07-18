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
                var date = new Date();
                var resetMinutes = Math.floor((parsedData.reset_time_in_seconds - Math.floor(date.getTime() / 1000)) / 60);
                parsedData.reset_friendly_string = resetMinutes +" minutes";
                res.render('index', {
                    "activeUser": req.activeUser,
                    "apiInfo": parsedData,
                    "newUser": (typeof req.query.welcome !== 'undefined')
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
                            pubClient.publish("get_favourites_backlog", JSON.stringify({
                                "twitter_id": parsedData.id,
                                "page": 1,
                                "token": oauth_access_token,
                                "secret": oauth_access_token_secret
                            }));
                            req.session.user_id = user.getId();
                            res.redirect('/?welcome');
                        });
                    } else {
                        var cachedCount = user.getFavouritesCount();
                        util.debug("got user from DB ["+user.getDisplayName()+"] with cached favourites count ["+cachedCount+"]");

                        //@todo improve this - make sure we preserve user's favourites
                        parsedData.favourites = user.getFavourites();

                        userMapper.updateById(parsedData.id, parsedData, function(user) {
                            if (user.get('favourites_count') != cachedCount) {
                                util.debug("new favourites count ["+user.get('favourites_count')+"] differs from cache - queuing fetch");
                                pubClient.publish("get_new_favourites", JSON.stringify({
                                    "twitter_id": user.getId(),
                                    "since_id": user.getLatestFavouriteId(),
                                    "token": oauth_access_token,
                                    "secret": oauth_access_token_secret
                                }));
                            }
                            req.session.user_id = user.getId();
                            res.redirect('/');
                        });
                    }
                });
            });
        });
    });

    app.get('/user/:username', authState('any'), function(req, res) {
        res.render('user', {
            "activeUser": req.activeUser,
            "favourites": req.user.getFavourites(),
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

/**
 * pub / sub bits. shouldn't be here, but we need to find a nice way to share the clients
 */
var client = redis.createClient();
var pubClient = redis.createClient();

client.subscribe("get_favourites_backlog");
client.subscribe("get_new_favourites");

client.on("message", function(channel, message) {
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

                        var uData = user.getData();
                        if (typeof uData.favourites === 'undefined') {
                            uData.favourites = [];
                        }

                        for (var i = 0, j = data.length; i < j; i++) {
                            uData.favourites.push(data[i]);
                        }

                        userMapper.updateById(user.getId(), uData, function(user) {
                            util.debug("updated favourites");
                            if (data.length == 20) {
                                pubClient.publish("get_favourites_backlog", JSON.stringify({
                                    "twitter_id": message.twitter_id,
                                    "page": ++message.page,
                                    "token": message.token,
                                    "secret": message.secret
                                }));
                            } else {
                                util.debug("didn't get enough tweets to bother requesting another page - backlog complete");
                            }
                        });
                    } else {
                        util.debug("got no more tweets - backlog complete");
                    }
                });
            });
            break;
        case 'get_new_favourites':
            userMapper.getByTwitterId(message.twitter_id, function(user) {
                var qStr = "http://api.twitter.com/1/favorites/"+user.getId()+".json?entities=true&since_id="+message.since_id
                util.debug("GETting "+qStr);
                oauth.get(qStr, message.token, message.secret, function(err, data) {
                    if (err) throw err;

                    data = JSON.parse(data);

                    if (data.length) {
                        // @todo don't forget, we need a way of working out if we got *all* tweets
                        // since the one we were after!

                        var uData = user.getData();
                        if (typeof uData.favourites === 'undefined') {
                            uData.favourites = [];
                        }

                        for (var i = data.length-1; i >= 0; i--) {
                            uData.favourites.unshift(data[i]);
                        }

                        userMapper.updateById(user.getId(), uData, function(user) {
                            /* do we need more?
                            if (allTweets == false) {
                                // ah, it's not. too many tweets!
                                pubClient.publish("get_new_favourites", JSON.stringify({
                                    "twitter_id": message.twitter_id,
                                    "since_id": data[data.length-1].id,
                                    "token": message.token,
                                    "secret": message.secret
                                }));
                            }
                            */
                        });
                    } else {
                        util.debug("got no more tweets");
                    }
                });
            });
            break;
        default:
            throw new Error('Unknown channel ['+channel+']');
            break;
    }
});

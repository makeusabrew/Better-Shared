var express = require('express');

module.exports = function(app) {
    /**
     * Production config
     */
    app.configure(function() {
        // we want cookies
        app.use(express.cookieParser());
        // we want sessions...
        app.use(express.session({secret: 'fooBarBaz'}));
        // access log style stuff
        app.use(express.logger());
        // so we can inspect stuff from the request body (?)
        app.use(express.bodyParser());
        // routing (duh)
        app.use(app.router);
        // static files
        app.use(express.static(__dirname + '/../public'));
        // templating engine
        app.set('view engine', 'jade');
    });
    /**
     * Dev config
     */
    app.configure('development', function() {
        app.use(express.errorHandler({dumpExceptions: true, showStack: true}));
    });
}

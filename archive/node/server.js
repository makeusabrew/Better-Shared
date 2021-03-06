/* global and npm managed deps go first */
var express = require('express'),
    util    = require('util'),
    zeromq  = require('zeromq');

var app = express.createServer();

var queue = zeromq.createSocket('rep');

queue.bind('tcp://127.0.0.1:5554', function(err) {
    if (err) throw err;
    queue.on('message', function(data) {
        util.debug("got data "+data.toString("utf8"));
    });
});

require('./app/config')(app);

require('./app/routes')(app);

app.listen(8124);

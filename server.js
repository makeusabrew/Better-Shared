/* global and npm managed deps go first */
var express = require('express'),
    util    = require('util');

var app = express.createServer();

require('./app/config')(app);

require('./app/routes')(app);

app.listen(8124);

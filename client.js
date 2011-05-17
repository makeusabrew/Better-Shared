var util    = require('util'),
    zeromq  = require('zeromq');

var s = zeromq.createSocket('req');
s.connect('tcp://127.0.0.1:5554');
s.send("foo bar");

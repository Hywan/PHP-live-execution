# PHP Live Execution

PHP live execution is an example of what is possible to do when combining a
[Websocket](https://github.com/hoaproject/Websocket) server and PHP
[FastCGI](https://github.com/hoaproject/Fastcgi) to execute PHP in realtime,
with the help of [Hoa](http://hoa-project.net/). Outputs and exceptions are
indicated directly on the result panel, while errors are indicated in the
editor.

[See the video](https://vimeo.com/40688620)!

**Note**: This is purely a POC! This code is not intended to be used in
production without cautions.

## Usage

First of all, install the dependencies:

    $ git submodule update --init
    $ composer install

Then, start the application:

    $ php-cgi -b 127.0.0.1:9000&
    $ php Server.php
    $ open index.html

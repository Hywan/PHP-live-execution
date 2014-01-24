# PHP Live Execution

PHP live execution is an example of what is possible to do when combining a
Websocket server and PHP FastCGI to execute PHP in realtime, with the help of
Hoa. Outputs and exceptions are indicated directly on the result panel, while
errors are indicated in the editor.

[See the video](https://vimeo.com/40688620)!

**Note**: This is purely a POC! This code is not intented to be used in
production without cautions.

## Usage

First of all, install the dependencies:

    $ composer install

Then, start the application:

    $ php-cgi 127.0.0.1:9000&
    $ php Server.php
    $ open index.html

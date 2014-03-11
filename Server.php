<?php

require_once 'vendor/autoload.php';

/**
 * Temporary files.
 */
$master = new Hoa\File\ReadWrite(Hoa\File\Temporary::create());
$tmp    = new Hoa\File\ReadWrite(Hoa\File\Temporary::create());
$trace  = new Hoa\File\ReadWrite(Hoa\File\Temporary::create());

/**
 * Websocket server.
 */
$ws     = new Hoa\Websocket\Server(new Hoa\Socket\Server('tcp://127.0.0.1:8889'));

/**
 * PHP FastCGI responder.
 */
$fcgi   = new Hoa\Fastcgi\Responder(new Hoa\Socket\Client('tcp://127.0.0.1:9000'));

$salt   = '__hoa_' . uniqid();

$content = <<<'EOL'
<?php

declare(ticks=1);
ob_start();

register_shutdown_function(function ( ) use ( &$__hoa_trace ) {

    $error = error_get_last();

    switch($error['type']) {

        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_COMPILE_WARNING:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
            ob_end_clean();
            file_put_contents('{{ traceStreamName }}', serialize($error));

            exit('{{ salt }}');
    }

    ob_end_clean();
    file_put_contents('{{ traceStreamName }}', serialize($__hoa_trace));

    return;
});

register_tick_function(function ( ) use ( &$__hoa_length, &$__hoa_trace ) {

    $__hoa_length = $__hoa_length ?: 0;
    $__hoa_trace  = $__hoa_trace  ?: array();

    if($__hoa_length >= ($length = ob_get_length()))
        return;

    $backtrace                      = debug_backtrace();
    $output                         = $backtrace[count($backtrace) - 2];
    $__hoa_trace[$output['file']][] = array(
        $output['line'],
        trim(substr(ob_get_contents(), $__hoa_length, $length))
    );
    $__hoa_length = $length;

    return;
});

try {

    require '{{ tmpStreamName }}';

} catch ( Exception $e ) {

    $__backtrace = $e->getTrace();
    $__output    = $__backtrace[count($__backtrace) - 2];
    $__hoa_trace[$__output['file']][] = array(
        $__output['line'],
        'caught: ' . $e->getMessage()
    );
}
EOL;

$content = strtr(
    $content,
    array(
        '{{ traceStreamName }}' => $trace->getStreamName(),
        '{{ salt }}'            => $salt,
        '{{ tmpStreamName }}'   => $tmp->getStreamName()
    )
);
$master->writeAll($content);
$headers = array(
    'REQUEST_METHOD'  => 'GET',
    'REQUEST_URI'     => '/',
    'REQUEST_TIME'    => time(),
    'SCRIPT_FILENAME' => $master->getStreamName()
);
$master->close();

/**
 * When receiving code from editor.
 */
$ws->on('message', function ( Hoa\Core\Event\Bucket $bucket )
                        use ( $tmp, $fcgi, $headers, $salt, $trace ) {

    $data    = $bucket->getData();
    $message = $data['message'];
    $tmp->truncate(0);
    $tmp->writeAll($message);

    // when an error occured.
    if($salt === $fcgi->send($headers)) {

        $bucket->getSource()->send($message);
        $bucket->getSource()->send(
            'error:' . json_encode(unserialize($trace->readAll()))
        );

        return;
    }

    $_trace = unserialize($trace->readAll());
    $code   = explode("\n", $message);

    foreach($_trace as $file => $outputs)
        foreach($outputs as $output) {

            list($line, $out) = $output;

            if(false === strpos($out, "\n"))
                $code[$line - 1] .= ' // ' . $out;
            else
                $code[$line - 1] .= "\n" . '/**' . "\n" . ' * ' .
                                    implode("\n" . ' * ', explode("\n", $out)) .
                                    "\n" . ' */';
        }

    $bucket->getSource()->send(implode("\n", $code));
    $trace->truncate(0);

    return;
});

echo 'Now, open file://', __DIR__, DS, 'index.html', "\n";

/**
 * And here we go :-).
 */
$ws->run();

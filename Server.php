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
$master->writeAll(
    '<?php' . "\n" .
    'declare(ticks=1); '. "\n" .
    'ob_start();' . "\n" .
    'register_shutdown_function(function ( ) use ( &$__hoa_trace ) {' . "\n" .
    '    $error = error_get_last();' . "\n" .
    '    switch($error[\'type\']) {' . "\n" .
    '        case E_ERROR:' . "\n" .
    '        case E_PARSE:' . "\n" .
    '        case E_CORE_ERROR:' . "\n" .
    '        case E_COMPILE_ERROR:' . "\n" .
    '        case E_COMPILE_WARNING:' . "\n" .
    '        case E_USER_ERROR:' . "\n" .
    '        case E_RECOVERABLE_ERROR:' . "\n" .
    '            ob_end_clean();' . "\n" .
    '            file_put_contents(' . "\n" .
    '                \'' . $trace->getStreamName() . '\',' . "\n" .
    '                serialize($error)' . "\n" .
    '            );' . "\n" .
    '            exit(\'' . $salt . '\');' . "\n" .
    '    }' . "\n" .
    '    ob_end_clean();' . "\n" .
    '    file_put_contents(' . "\n" .
    '        \'' . $trace->getStreamName() . '\',' . "\n" .
    '        serialize($__hoa_trace)' . "\n" .
    '    );' . "\n" .
    '});' . "\n" .
    'register_tick_function(function ( ) use ( &$__hoa_length, &$__hoa_trace ) {' . "\n" .
    '    $__hoa_length = $__hoa_length ?: 0;' . "\n" .
    '    $__hoa_trace  = $__hoa_trace  ?: array();' . "\n" .
    '    if($__hoa_length >= ($length = ob_get_length()))' . "\n" .
    '        return;' . "\n" .
    '    $backtrace = debug_backtrace();' . "\n" .
    '    $output    = $backtrace[count($backtrace) - 2];' . "\n" .
    '    $__hoa_trace[$output[\'file\']][] = array(' . "\n" .
    '        $output[\'line\'],' . "\n" .
    '        trim(substr(ob_get_contents(), $__hoa_length, $length))' . "\n" .
    '    );' . "\n" .
    '    $__hoa_length = $length;' . "\n" .
    '});' . "\n" .
    'try {' . "\n" .
    '    require \'' . $tmp->getStreamName() . '\';' . "\n" .
    '}' . "\n" .
    'catch ( Exception $e ) {' . "\n" .
    '    $__backtrace = $e->getTrace();' . "\n" .
    '    $__output    = $__backtrace[count($__backtrace) - 2];' . "\n" .
    '    $__hoa_trace[$__output[\'file\']][] = array(' . "\n" .
    '        $__output[\'line\'],' . "\n" .
    '        \'caught: \' . $e->getMessage()' . "\n" .
    '    );' . "\n" .
    '}'
);
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
    if($salt === $content = $fcgi->send($headers)) {

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

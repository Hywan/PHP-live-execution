var result = CodeMirror.fromTextArea(
    document.querySelector('#result textarea'),
    {
        mode: 'text/x-php',
        lineNumbers: true,
        theme: 'def',
        readOnly: true
    }
);

var ws     = new WebSocket('ws://127.0.0.1:8889');
var editor = CodeMirror.fromTextArea(
    document.querySelector('#editor textarea'),
    {
        mode       : 'application/x-httpd-php',
        lineNumbers: true,
        theme      : 'def',
        indentUnit : 4,
        tabSize    : 4,
        autofocus  : true,
        onKeyEvent : function ( editor, evt ) {

            if('keyup' != evt.type)
                return;

            __editorCancel();
            __editorTimeoutId = window.setTimeout(function ( code ) {

                __editorSend(code);
            }, 250, evt.keyCode);
        }
    }
);

var __editorTimeoutId = null;
var __editorCancel    = function ( ) {

    if(null === __editorTimeoutId)
        return;

    window.clearTimeout(__editorTimeoutId);
    __editorTimeoutId = null;
};
var __editorSend      = function ( code ) {

    if(code >= 37 && 40 >= code)
        return;

    __editorError.forEach(function ( e ) {

        editor.clearMarker(e);
    });
    __editorError = [];
    ws.send(editor.getValue());
};
var __editorError     = [];

ws.onmessage = function ( message ) {

    var msg = message.data;

    if(null != (handle = /^error:(.*)$/.exec(msg))) {

        var error = JSON.parse(handle[1]);
        __editorError.push(editor.setMarker(
            error.line - 1,
            '<em title="' + error.message + '">✖</em> %N%',
            'error'
        ));

        return;
    }

    result.setValue(message.data);
};
ws.onclose = function ( e ) {

    document.querySelector('#error').style.visibility = 'visible';
};

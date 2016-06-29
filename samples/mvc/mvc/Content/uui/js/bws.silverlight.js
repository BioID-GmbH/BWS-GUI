/*! BioID Web Service - 2016-06-28
*   Silverlight extension for image capture - v1.0.6
* https://www.bioid.com
* Copyright (C) BioID GmbH.
*/

var silverlightHost = null;
function createSilverlightPlugin(source) {
    console.log('start silverlight plugin');
    $('#guiapp').show();
    $('#guicanvas').hide();
    $('#guisilverlight').show();
    $('#guifeature').attr('data-res', 'guiSilverlightTitle');
    $('#guifeature').html('BWS unified user interface (Silverlight version)');
    Silverlight.createObjectEx({
        source: source,
        parentElement: document.getElementById('guisilverlight'),
        id: 'SilverlightCapture',
        properties: {
            width: '100%',
            height: '100%',
            version: '5.0.61118.0',
            enableHtmlAccess: 'true',
            windowless: 'true'
        },
        events: {
            onLoad: onSilverlightPluginLoaded,
            onError: onSilverlightError
        },
        context: null
    });
}
function onSilverlightPluginLoaded(plugIn, userContext, sender) {
    console.log('silverlight plugin loaded');
    $('#guiskip').hide();
    $('#guistartapp').hide();
    $('#guisplash').hide();
    silverlightHost = sender.getHost();
}
function onSilverlightError(sender, args) {
    var appSource = '';
    if (sender !== null && sender !== 0) {
        appSource = sender.getHost().Source;
    }
    var errMsg = 'Unhandled Error in Silverlight Application ' + appSource + '\n';
    console.log(errMsg);
    $('#guierror').html(formatText('silverlightError', appSource));
    $('#guiskip').show();
    $('#guistartapp').show();
}
function upload(dataURL) {
    capture.upload(dataURL);
}
function motionBar(motion) {
    capture.drawMotionBar(motion);
}

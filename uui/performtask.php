<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width" />
    <title>BioID Web Service</title>
    <link rel="stylesheet" type="text/css" href="/uui/css/gui.css">
</head>
<body>
    <header>
        <div id="guilogo">
            <img src="/uui/images/logo.png" alt="BioID Logo" width="115" />
        </div>
        <div id="guibuttons">
            <ul class="guinav">
                <li class="guicancel"><a id="guicancel" href="" title="Abort and navigate back to caller" data-res="buttonCancel"></a></li>
                <li class="guimirror"><button id="guimirror" title="Mirror the display of the captured images" style="display:none" data-res="buttonMirror"></button></li>
                <li class="guistart"><button id="guistart" title="Start the recording of images" style="display:none" data-res="buttonStart"></button></li>
            </ul>
        </div>
        <div id="guititle">
            <div class="guititle" data-res="guiTitle"></div>
            <div class="guisubtitle" id="guisubtitle" data-res="guiSubTitleVerification"></div>
        </div>
        <div id="guifeature" data-res="guiHtml5Title"></div>
    </header>

    <main id="body">
        <section id="guisplash">
            <div id="guiprompt">
                <p data-res="prompt"></p>
                <p data-res="silverlight" style="display:none"></p>
                <p id="guierror" class="guierror"></p>        
                <div id="guistartapp" style="display:none">
                    <p data-res="guimobileapp"></p>
                    <p><span class="guimobileapp"><a id="guimobileapp" title="Start the BioID app"></a></span></p>
                </div>          
                <a href="" id="guiskip" class="btn" title="Skip biometric process" style="display:none" data-res="buttonContinue">Continue without biometrics</a>
            </div>
        </section>
        <section id="guiapp" style="display:none">
            <div id="guimessage" style="display:none"></div>
            <canvas id="guicanvas"></canvas>
            <div id="guisilverlight"></div>
            <canvas id="guimotionbar"></canvas>
            <div id="guiarrows" style="display:none">
                <img class="toparrow" id="guiarrowup" src="/uui/images/up.png" width="128" height="128" alt="upward" title="look slightly upward..." style="display:none" data-res="lookUp" />
                <img class="leftarrow" id="guiarrowleft" src="/uui/images/left.png" width="128" height="128" alt="left" title="look a little bit to the left..." style="display:none" data-res="lookRight" />
                <img class="rightarrow" id="guiarrowright" src="/uui/images/right.png" width="128" height="128" alt="right" title="look a little bit to the right..." style="display:none" data-res="lookLeft" />
                <img class="bottomarrow" id="guiarrowdown" src="/uui/images/down.png" width="128" height="128" alt="downward" title="look slightly downward..." style="display:none" data-res="lookDown" />
            </div>
            <div id="guistatus">
                <!-- up to max recordings (2-5 for enrollment and 2 for verification) status images -->
                <div class="guiimage" id="guiimage1" style="display:none">
                    <div id="guiwait1" class="guiwait"></div>
                    <div id="guiupload1" class="guiupload" style="display:none" data-res="uploadInfo">Uploading...</div>
                    <img id="guiuploaded1" src="/uui/images/upload.gif" style="display:none" />
                </div>
                <div class="guiimage" id="guiimage2" style="display:none">
                    <div id="guiwait2" class="guiwait"></div>
                    <div id="guiupload2" class="guiupload" style="display:none" data-res="uploadInfo">Uploading...</div>
                    <img id="guiuploaded2" src="/uui/images/upload.gif" style="display:none" />
                </div>
                <div class="guiimage" id="guiimage3" style="display:none">
                    <div id="guiwait3" class="guiwait"></div>
                    <div id="guiupload3" class="guiupload" style="display:none" data-res="uploadInfo">Uploading...</div>
                    <img id="guiuploaded3" src="/uui/images/upload.gif" style="display:none" />
                </div>
                <div class="guiimage" id="guiimage4" style="display:none">
                    <div id="guiwait4" class="guiwait"></div>
                    <div id="guiupload4" class="guiupload" style="display:none" data-res="uploadInfo">Uploading...</div>
                    <img id="guiuploaded4" src="/uui/images/upload.gif" style="display:none" />
                </div>
                <div class="guiimage" id="guiimage5" style="display:none">
                    <div id="guiwait5" class="guiwait"></div>
                    <div id="guiupload5" class="guiupload" style="display:none" data-res="uploadInfo">Uploading...</div>
                    <img id="guiuploaded5" src="/uui/images/upload.gif" style="display:none" />
                </div>
            </div>
        </section>
    </main>

    <script src="/uui/js/jquery-2.2.3.min.js"></script>
    <script src="/uui/js/bws.capture.js"></script>
    <!-- 
        Silverlight stuff - only used if HTML5 version fails! 
        Note: 
        Silverlight is no longer used or blocked by modern browser. 
        This implementation is for browser generation like MS Internet Explorer or Apple Safari.
        If you have no need for supporting this browser generation you can remove the both lines of including silverlight javascript files.
        The variable 'silverlightHost' is in this case undefined and has no use but also has no conflict. If you prefer a pure HTML5 implementation, delete this variable in the code!
    -->
    <script src="/uui/js/Silverlight.js"></script>
    <script src="/uui/js/bws.silverlight.js"></script>
 
    <script type="text/javascript">
        // BEGIN OF CONFIGURATION
        // Beside setting these values by url parameter you can set by your server
        // e.g. MVC @Model.host | PHP <?=$host?>"
        var token = getUrlParameter('access_token');
        var returnURL = getUrlParameter('return_url');
        var state = getUrlParameter('state');
        var host = getUrlParameter('bws_host');    
        var task = getUrlParameter('task');
        var challengeResponse = getUrlParameter('challengeresponse') == 'true' ? true : false;
        var autoEnrollment = getUrlParameter('autoenrollment') == 'true' ? true : false;    
        var autostart = getUrlParameter('autostart') == 'true' ? true : false;
     
        // disable console log
        // console.log = function () { }

        // END OF CONFIGURATION

        var recordings = 4;
        if (challengeResponse == false) {
            if (task == 'enrollment') { recordings = 3; }
            else { recordings = 2; }
        }

        // localized messages
        var guimessages = {'':''};
        var capture = null;
        var executions = 3;
        var maxHeight = 480;

        // jQuery - shorthand for $( document ).ready()
        // Document Object Model (DOM) is ready
        $(function () {
            initialize();
            
            // set navigation for the buttons
            $('#guicancel').attr('href', returnURL + '?error=user_abort&access_token=' + token + '&state=' + state);
            $('#guiskip').attr('href', returnURL + '?error=user_skip&access_token=' + token + '&state=' + state);
            $('#guimobileapp').attr('href', 'bioid-verify://?access_token=' + token + '&return_url=' + returnURL + '&state=' + state);
        });

        // helper to read the url parameter
        function getUrlParameter(param) {
            var pageUrl = window.location.search.substring(1);
            var urlVariables = pageUrl.split('&');
            for (var i = 0; i < urlVariables.length; i++) {
                var parameterName = urlVariables[i].split('=');
                if (parameterName[0] == param) {
                    return parameterName[1];
                }
            }
        }

        // initialize - load content in specific language and initialize bws capture
        function initialize() {

            // change gui subtitle if task is enrollment
            if (task == 'enrollment') {
                $('#guisubtitle').attr('data-res', 'guiSubTitleEnrollment');
            }

            // try to get language info from the browser.
            var lang = window.navigator.userLanguage || window.navigator.language;
            if (lang) {
                if (lang.length > 2) {
                    // Convert e.g. 'en-US' to 'en'.
                    lang = lang.substring(0, 2);
                }
                // If we do not support the current navigator language,
                // default to english.
                if (lang != 'en' && lang != 'de') { lang = 'en'; }
            }
            else {
                // Default to english if we did not succeed in getting
                // a language from the browser.
                lang = 'en';
            }

            var language = $.getJSON('/uui/lang/' + lang + '.json').
                done(function (data)
                {
                    console.log('Loaded the language-specific resource successfully');
                    guimessages = data;
                    localize(data);
                    
                    if (autoEnrollment) { var maxHeight = 480; }
                    else { var maxHeight = 320; }

                    // init BWS capture jQuery plugin (see bws.capture.js)
                    capture = bws.initcapture(document.getElementById('guicanvas'), document.getElementById('guimotionbar'), token, {
                        host: host,
                        task: task,
                        maxheight: maxHeight,
                        challengeResponse: challengeResponse,
                        recordings: recordings,
                        display: 'circle'
                    });
                    // and start everything
                    onStart();

                }).fail(function(jqxhr, textStatus, error ) {
                    var err = textStatus + ', ' + error;
                    console.log('Loading of language-specific resource failed with: ' + err );
                });
        }

        // localization of displayed strings
        function localize(localizedData) {
            // loops through all HTML elements that must be localized.
            var resourceElements = $('[data-res]');
            for (var i = 0; i < resourceElements.length; i++) {
                var element = resourceElements[i];
                var resourceKey = $(element).attr('data-res');
                if (resourceKey) {
                    // Get all the resources that start with the key.
                    for (var key in localizedData) {
                        if (key.indexOf(resourceKey) == 0) {
                            var value = localizedData[key];
                            // Dot notation in resource key - assign the resource value to the elements property
                            if (key.indexOf('.') > -1) {
                                var attrKey = key.substring(key.indexOf(".") + 1);
                                $(element).attr(attrKey, value);
                            }
                            // No dot notation in resource key, assign the resource value to the element's innerHTML.
                            else { $(element).html(value); }
                        }
                    }
                }
            }
        }

        // localization and string formatting (additional arguments replace {0}, {1}, etc. in guimessages[key])
        function formatText(key) {
            var formatted = key;
            if (guimessages[key] !== undefined) {
                formatted = guimessages[key];
            }
            for (var i = 1; i < arguments.length; i++) {
                formatted = formatted.replace('{' + (i - 1) + '}', arguments[i]);
            }
            return formatted;
        }

        // called by onStart or the Silverlight plugin to update GUI
        function captureStarted() {
            $('#guisplash').hide();
            $('#guiapp').show();
            $('#guimessage').show();
            $('#guiarrows').show();
            $('#guimirror').show().click(mirror);

            if (autostart) {
                setTimeout(function () { startRecording(); }, 1400);
            }
            else {
                $('#guistart').show().click(startRecording);
            }
        }

        // called from Start button and onStart to initiate a new recording
        function startRecording() {
            $('#guistart').hide();
            capture.startCountdown(function () {
                for (var i = 1; i <= recordings; i++) {
                    $('#guiuploaded' + i).hide();
                    $('#guiupload' + i).hide();
                    $('#guiwait' + i).show();
                    $('#guiimage' + i).show();
                }
                capture.startRecording();
                if (typeof silverlightHost !== 'undefined' && silverlightHost !== null) {
                    silverlightHost.Content.MainPage.StartCapturing();
                }
            });
        }

        // called from onStart when recording is done
        function stopRecording() {
            capture.stopRecording();
            for (var i = 1; i <= recordings; i++) {
                $('#guiimage' + i).hide();
            }
            if (typeof silverlightHost !== 'undefined' && silverlightHost !== null) {
                silverlightHost.Content.MainPage.StopCapturing();
            }
        }

        // called from Mirror button to mirror the captured image
        function mirror() {
            if (typeof silverlightHost !== 'undefined' && silverlightHost !== null) {
                silverlightHost.Content.MainPage.MirrorDisplay();
            } else {
                capture.mirror();
            }
        }

        // startup code
        function onStart() {
            capture.start(function () {
                $('#guicanvas').show();
                $('#guisilverlight').hide();
                captureStarted();
            }, function (error) {
                if (error !== undefined) {
                    // different browsers use different errors
                    if (error.code === 1 || error.name === 'PermissionDeniedError') {
                        // in the spec we find code == 1 and name == PermissionDeniedError for the permission denied error
                        $('#guierror').html(formatText('capture-error', formatText('PermissionDenied')));
                    } else {
                        // otherwise try to print the error
                        $('#guierror').html(formatText('capture-error', error));
                    }                  
                    // show button for continue (skip biometric task)
                    $('#guiskip').show();
                } else {
                    // no error info typically says that browser doesn't support getUserMedia
                    $('#guierror').html(formatText('nogetUserMedia'));
                    // fallback if no getUserMedia available - try to load silverlight plugin
                    // Note: If you do not included the required silverlight js files nothing happens!
                    if (typeof silverlightHost !== 'undefined' && silverlightHost == null) {
                        createSilverlightPlugin();
                    }
                    else {
                        console.log('To run Silverlight plugin you must include the required js files!')
                        
                        // show button for continue (skip biometric task)
                        $('#guiskip').show();

                        // for mobile browser we offer the possibility to start the mobile app version
                        $('#guistartapp').show();
                        $('#guimobileapp').click(function () {
                            $('#guimobileapp').hide();
                        });
                    }
                }
            }, function (error, retry) {
                // done
                stopRecording();
                executions--;
                if (error !== undefined && retry && executions > 0) {
                    // if failed restart if retries are left, but wait a bit until the user has read the error message!
                    setTimeout(function () { startRecording(); }, 1800);
                } else {
                    // done: redirect to caller ...
                    var url = returnURL + '?access_token=' + token;
                    if (error !== undefined) {
                        url = url + '&error=' + error;
                    }
                    url = url + '&state=' + state;
                    window.location.replace(url);
                }
            }, function (status, message, dataURL) {
                if(status === 'DisplayTag') {
                    if(message.search('up')<0){$('#guiarrowup').hide();}else{$('#guiarrowup').show();}
                    if(message.search('down')<0){$('#guiarrowdown').hide();}else{$('#guiarrowdown').show();}
                    if(message.search('left')<0){$('#guiarrowleft').hide();}else{$('#guiarrowleft').show();}
                    if(message.search('right')<0){$('#guiarrowright').hide();}else{$('#guiarrowright').show();}
                } else {
                    // report a message on the screen
                    var uploaded = capture.getUploaded();
                    var recording = uploaded + capture.getUploading();
                    var msg = formatText(status, recording, recordings);
                    var $msg = $('#guimessage');
                    $msg.html(msg);
                    $msg.stop(true).fadeIn(400).fadeOut(3000);

                    // we display some animations/images dpending on the status
                    if(status === 'Uploading') {
                        // begin an upload
                        $('#guiwait' + recording).hide();
                        $('#guiupload' + recording).show();
                    } else if(status === 'Uploaded') {
                        // successfull upload (we should have a dataURL)
                        if(dataURL) {
                            $('#guiupload' + uploaded).hide();
                            $image = $('#guiuploaded' + uploaded);
                            $image.attr('src', dataURL);
                            $image.attr('width', 90);
                            $image.attr('height', 120);
                            $image.show();
                        }
                    } else if (status === 'NoFaceFound' || status === 'MultipleFacesFound') {
                        // upload failed
                        recording++;
                        $('#guiupload' + recording).hide();
                        $('#guiwait' + recording).show();
                    }
                }
            });
        };
    </script>
</body>
</html>

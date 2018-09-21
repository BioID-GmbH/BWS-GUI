<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width" />
    <title>BWS Unified User Interface</title>
    <link href="./css/uui.min.css" rel="stylesheet" />
</head>
<body>
    <header class="header">
        <img class="logo" src="./images/bioid-logo.svg" alt="BioID Logo" />
        <div id="uuititle" class="title" data-res="titleVerification"></div>
    </header>
    <main class="main">
        <section class="navigation">
            <a id="uuicancel" href=""><img src="./images/abort.svg" class="button-grow" alt="abort" title="Abort and navigate back to caller" data-res="buttonCancel" /></a>
            <a id="uuimirror" href="#" class="hidden"><img src="./images/mirror.svg" class="button-grow" alt="mirror" title="Mirror the display of the captured images" data-res="buttonMirror" /></a>
        </section>
        <section id="uuisplash">
            <div id="uuiprompt" class="prompt">
                <p data-res="prompt"></p>
                <p id="uuierror" class="alert-danger"></p>
                <a id="uuiskip" class="button" href="" title="Skip biometric process" data-res="buttonContinue">Skip biometrics</a>
                <a id="uuimobileapp" class="button" href="" title="Start the BioID app" data-res="buttonMobileApp">Start BioID app</a>
            </div>
        </section>
        <section id="uuiwebapp" class="webapp">
            <div id="uuiinstruction" class="instruction transparent-background"></div>
            <canvas id="uuicanvas" class="liveview">
            </canvas>
            <div id="uuimessage" class="message"></div>
            <div id="uuihead" class="head"></div>
            <a id="uuistart" class="startbutton" href="#"><img src="./images/play.svg" class="button-big-grow" alt="start" title="Start the recording of images" data-res="buttonStart" /></a>
            <div class="uploadstatus">
                <!-- status images for up to 4 recordings -->
                <div id="uuiimage1" class="image">
                    <div id="uuiwait1" class="spinner spinner-wait"></div>
                    <div id="uuiupload1" class="spinner spinner-upload" data-res="uploadInfo">Uploading...</div>
                    <img id="uuiuploaded1" class="image-uploaded" />
                </div>
                <div id="uuiimage2" class="image">
                    <div id="uuiwait2" class="spinner spinner-wait"></div>
                    <div id="uuiupload2" class="spinner spinner-upload" data-res="uploadInfo">Uploading...</div>
                    <img id="uuiuploaded2" class="image-uploaded" />
                </div>
                <div id="uuiimage3" class="image">
                    <div id="uuiwait3" class="spinner spinner-wait"></div>
                    <div id="uuiupload3" class="spinner spinner-upload" data-res="uploadInfo">Uploading...</div>
                    <img id="uuiuploaded3" class="image-uploaded" />
                </div>
                <div id="uuiimage4" class="image">
                    <div id="uuiwait4" class="spinner spinner-wait"></div>
                    <div id="uuiupload4" class="spinner spinner-upload" data-res="uploadInfo">Uploading...</div>
                    <img id="uuiuploaded4" class="image-uploaded" />
                </div>
            </div>
        </section>
    </main>

<?php
$access_token = $_GET["access_token"];
if(empty($access_token)) { 
	die("Invalid call to the BioID Web Service unified user interface (missing access token)! Please ensure that you have been sent to this page from a valid BWS client application.");
}
$return_url = filter_var($_GET["return_url"], FILTER_SANITIZE_URL);
if (!filter_var($return_url, FILTER_VALIDATE_URL)) {
	die("Invalid call to the BioID Web Service unified user interface (invalid return url)! Please ensure that you have been sent to this page from a valid BWS client application.");
}
$api_url = filter_var($_GET["api_url"], FILTER_SANITIZE_URL);
if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
	$bws_host = $_GET["bws_host"]; // backward compatibility (using api_url is recommended)
	if(empty($bws_host)) { 
		$bws_host = "bws.bioid.com"; 
	}
	$api_url = "https://$bws_host/extension/"; 
}
if(substr_compare($api_url, "/", -1) != 0) {
	$api_url = $api_url + "/";
}

$state = $_GET["state"];
$autostart = (boolean)$_GET["autostart"];
$trait = $_GET["trait"]; // backward compatibility (typically overwritten by $claims->traits)
$task =  "verification";
$autoenroll = false;
$maxHeight = 320;
$maxtries = 3;
$recordings = 1;
$challenge = false;
$challenges = "[]";

// parse the token ...
$claims = json_decode(base64_decode(explode(".", $access_token)[1]));
if(!empty($claims->traits)) {
	if($claims->traits == 1) { $trait = "Face"; }
	elseif($claims->traits == 2) { $trait = "Periocular"; }
	elseif($claims->traits == 3) { $trait = "Face,Periocular"; }
}
if(!empty($claims->task)) {
	if(($claims->task & 0x100) == 0x100) { $recordings = 2; }
	if(($claims->task & 0x10) == 0x10) { $task = "identification"; }
	elseif(($claims->task & 0x20) == 0x20) { $task = "enrollment"; $maxHeight = 480; $recordings = 3; }
	elseif(($claims->task & 0x1000) == 0x1000) { $autoenroll = true; $maxHeight = 480; }
	if(($claims->task & 0x200) == 0x200) { 
		$challenge = true;
		$challenges = $claims->challenge;
		$recordings = count(json_decode($challenges)[0]) + 1;
	}
	$maxtries = $claims->task & 0x0F;
}
?> 

    <script src="./js/jquery-3.3.1.min.js"></script>
    <script src="./js/getUserMedia.min.js"></script>
    <script src="./js/bws.capture.js"></script>
    <script src="./js/three.min.js"></script>
    <script src="./js/objLoader.min.js"></script>

    <script type="text/javascript">
        // BEGIN OF CONFIGURATION
        var token = <?php echo "\"$access_token\""; ?>;
        var returnURL = <?php echo "\"$return_url\""; ?>;
        var state = <?php echo "\"$state\""; ?>;
        var apiurl = <?php echo "\"$api_url\""; ?>;
        var task = <?php echo "\"$task\""; ?>;
        var trait = <?php echo "\"$trait\""; ?>;
        var executions = <?php echo "\"$maxtries\""; ?>;
        var recordings = <?php echo "\"$recordings\""; ?>;
        var autostart = <?php echo $autostart ? 'true' : 'false'; ?>;
        var challengeResponse = <?php echo $challenge ? 'true' : 'false'; ?>;
        var challenges = <?php echo $challenges; ?>;
        var maxHeight = <?php echo $maxHeight; ?>;
        // END OF CONFIGURATION

        // BWS capture jQuery plugin
        var bwsCapture = null;

        // counter for current execution
        var currentExecution = 0;

        var currentTag = '';
        var parentTag = '';

        // enrollment without challenge response
        var enrollmentTags = ['left', 'right', 'right', 'left', 'up', 'down', 'down', 'up'];
        var predefinedTags = ['left', 'right', 'up', 'down'];

        // localized messages (english defaults, might get overloaded in initialize())
        var localizedData = {
            'titleEnrollment': 'Enrollment',
            'titleVerification': 'Verification',
            'titleIdentification': 'Identification',
            'titleLiveDetection':  'Liveness Detection',

            'buttonCancel.title': 'Abort and navigate back to caller',
            'buttonMirror.title': 'Mirror the display of the captured images',
            'buttonStart.title': 'Start the recording of images',
            'buttonContinue.title': 'Skip biometric process',
            'buttonContinue': 'Skip biometrics',
            'buttonMobileApp.title': 'Continue biometric process with BioID app',
            'buttonMobileApp': 'Start BioID app',

            'prompt': 'This web page requires the HTML5 Media Capture and Streams API (getUserMedia(), as supported by the actual versions of Opera, Firefox, Chrome and Edge).<br/>You also have to grant access to your camera.',
            'mobileapp': 'If you have installed the BioID App on your mobile device, you can use this app for enrollment or verification.',

            'uploadInfo': 'Uploading...',

            'capture-error': 'The user might have denied access to their camera.<br />Sorry, but without access to a camera, biometric face/periocular recognition is not possible!',
            'nogetUserMedia': 'Your browser does not support the HTML5 Media Capture and Streams API. You might want to use the BioID mobile App instead.',
            'permissionDenied': 'Permission Denied!',
			'webgl-error': 'WebGL is disabled or unavailable. If possible activate WebGL or use another browser.',

            'UserInstruction-3': '3 ...',
            'UserInstruction-2': '2 ...',
            'UserInstruction-1': '1 ...',
            'UserInstruction-FollowMe': 'Follow Me',
            'UserInstruction-NoMovement': 'Please move your head...',

            'Perform-enrollment': 'Training ...',
            'Perform-verification': 'Verifying ...',
            'Perform-identification': 'Identifying ...',
            'Perform-livenessdetection':  'Processing ...',

            'NoFaceFound': 'No face found',
            'MultipleFacesFound': 'Upload failed: multiple faces were found',
            'LiveDetectionFailed': 'Live detection failed<br/>Retrying ...',
            'ChallengeResponseFailed': 'Challenge-Response failed!<br/>Follow the head ...',
            'NotRecognized': 'You have not been recognized!<br/>Retrying ...'
        };

        /* ----------------- Set button functionality ------------------------------------------*/


        // jQuery - shortcut for $(document).ready()
        // Document Object Model (DOM) is ready
        $(function () {
            initialize();

            // set navigation for the buttons
            $('#uuicancel').attr('href', returnURL + '?error=user_abort&access_token=' + token + '&state=' + state);
            $('#uuiskip').attr('href', returnURL + '?error=user_skip&access_token=' + token + '&state=' + state);

            // set url for the BioID mobile app
            if (task === 'verification') {
                $('#uuimobileapp').attr('href', 'bioid-verify://?access_token=' + token + '&return_url=' + returnURL + '&state=' + state);
            }
            else if (task === 'enrollment') {
                $('#uuimobileapp').attr('href', 'bioid-enroll://?access_token=' + token + '&return_url=' + returnURL + '&state=' + state);
            }

            // hide button after first click
            $('#uuimobileapp').click(function () {
                $('#uuimobileapp').hide();
            });
        });

        // called from Start button and onStart to initiate a new recording
        function startRecording(countdown) {
          $('#uuistart').hide();
          var tags = challengeResponse && challenges.length > currentExecution && challenges[currentExecution].length > 0 ? challenges[currentExecution] : [];
          bwsCapture.startRecording(tags, countdown);
        }

        // called from Mirror button to mirror the captured image
        function mirror() {
            bwsCapture.mirror();
        }


        /* ---------------- Localization of strings ----------------------------------------------*/


        // localization of displayed strings
        function localize() {
            // loops through all HTML elements that must be localized.
            let resourceElements = $('[data-res]');
            for (let i = 0; i < resourceElements.length; i++) {
                let element = resourceElements[i];
                let resourceKey = $(element).attr('data-res');
                if (resourceKey) {
                    // Get all the resources that start with the key.
                    for (let key in localizedData) {
                        if (key.indexOf(resourceKey) === 0) {
                            let value = localizedData[key];
                            // Dot notation in resource key - assign the resource value to the elements property
                            if (key.indexOf('.') > -1) {
                                let attrKey = key.substring(key.indexOf('.') + 1);
                                $(element).attr(attrKey, value);
                            }
                            // No dot notation in resource key, assign the resource value to the element's innerHTML.
                            else if (key === resourceKey) {
                                $(element).html(value);
                            }
                        }
                    }
                }
            }
        }

        // localization and string formatting (additional arguments replace {0}, {1}, etc. in localizedData[key])
        function formatText(key) {
            var formatted = key;
            if (localizedData[key] !== undefined) {
                formatted = localizedData[key];
            }
            for (let i = 1; i < arguments.length; i++) {
                formatted = formatted.replace('{' + (i - 1) + '}', arguments[i]);
            }
            return formatted;
        }


        /* ----------------- Initialize BWS capture jQuery plugin --------------------------------*/


        // initialize - load content in specific language and initialize bws capture
        function initialize() {
            // change title if task is enrollment
            if (task === 'enrollment') {
                $('#uuititle').attr('data-res', 'titleEnrollment');
            }
            // change title if task is identification
            else if (task === 'identification') {
                $('#uuititle').attr('data-res', 'titleIdentification');
            }
            else if (task === 'livenessdetection') {
                $('#uuititle').attr('data-res', 'titleLiveDetection');
            }

            // try to get language info from the browser.
            let userLangAttribute = navigator.language || navigator.userLanguage || navigator.browserLanguage || 'en';
            userLangAttribute = 'en';
            let userLang = userLangAttribute.slice(0, 2);
            // let userLocation = userLangAttribute.slice(-2) || 'us';

            $.getJSON('./language/' + userLang + '.json').
            done(function (data) {
                console.log('Loaded the language-specific resource successfully');
                localizedData = data;
            }).fail(function (jqxhr, textStatus, error) {
                console.log('Loading of language-specific resource failed with: ' + textStatus + ', ' + error);
            }).always(function () {
                localize();
                // init BWS capture jQuery plugin (see bws.capture.js)
                bwsCapture = bws.initcapture(document.getElementById('uuicanvas'), token, {
                    apiurl: apiurl,
                    task: task,
                    trait: trait,
                    challengeResponse: challengeResponse,
                    recordings: recordings,
                    maxheight: maxHeight
                });
                let success = initHead();
                if (!success) {
                    $('#uuierror').html(formatText('webgl-error'));
                    $('#uuiskip').show();
                }
                else {
                    // and start everything
                    onStart();
                }
            });
        }


        /* ------------------ Start BWS capture jQuery plugin -----------------------------------*/

        // startup code
        function onStart() {
            bwsCapture.start(function () {
                $('#uuicanvas').show();
                captureStarted();
            }, function (error) {
                // show button for continue without biometrics (skip biometric task)
                $('#uuiskip').show();
                // show button for BioID app (interapp communication)
                if (task === 'verification' || task === 'enrollment') {
                    $('#uuimobileapp').show();
                }
                if (error !== undefined) {
                    // different browsers use different errors
                    if (error.code === 1 || error.name === 'PermissionDeniedError') {
                        // in the spec we find code == 1 and name == PermissionDeniedError for the permission denied error
                        $('#uuierror').html(formatText('capture-error', formatText('PermissionDenied')));
                    } else {
                        // otherwise try to print the error
                        $('#uuierror').html(formatText('capture-error', error));
                    }
                } else {
                    // no error info typically says that browser doesn't support getUserMedia
                    $('#uuierror').html(formatText('nogetUserMedia'));
                }
            }, function (error, retry) {
                // done
                stopRecording();
                currentExecution++;

                if (error !== undefined && retry && currentExecution < executions) {
                    // if failed restart if retries are left, but wait a bit until the user has read the error message!
                    setTimeout(function () { startRecording(true); }, 1800);
                    console.log('Current Execution: ' + currentExecution);
                } else {
                    // done: redirect to caller ...
                    let url = returnURL + '?access_token=' + token;
                    if (error !== undefined) {
                        url = url + '&error=' + error;
                    }
                    url = url + '&state=' + state;
                    window.location.replace(url);
                }
            }, function (status, message, dataURL) {
                let $msg;
                if (status === 'DisplayTag') {
                    setCurrentTag(message);
                    $msg = $('#uuiinstruction');
                    $msg.html(formatText('UserInstruction-FollowMe'));
                    $msg.stop(true).fadeIn();
                } else {
                    // report a message on the screen                  
                    let msg = formatText(status);

                    // user instructions
                    if (status.indexOf('UserInstruction') > -1) {
                        $msg = $('#uuiinstruction');
                        if (status === 'UserInstruction-Start') {
                          let counter = recordings;
                          if (counter > 4) {
                            counter = 4;
                          }
                          for (let i = 1; i <= counter; i++) {
                            $('#uuiuploaded' + i).hide();
                            $('#uuiupload' + i).hide();
                            $('#uuiwait' + i).show();
                            $('#uuiimage' + i).show();
                          }
                          resetHeadDisplay();
                        }
                        else {
                            $msg.html(msg);
                            $msg.stop(true).fadeIn();
                        }
                    }

                    // perform tasks
                    if (status.indexOf('Perform') > -1 || status.indexOf('Retry') > -1) {
                        // hide head and userinstruction
                        hideHead();
                        $('#uuiinstruction').hide();
                        $('#uuicanvas').css('filter', 'blur(10px)');

                        // show message
                        $msg = $('#uuimessage');
                        $msg.html(msg);
                        $msg.stop(true).fadeIn().delay(1800).fadeOut();
                    }

                    // results of uploading or perform task
                    if (status.indexOf('Failed') > -1 ||
                        status.indexOf('NotRecognized') > -1 ||
                        status.indexOf('NoFaceFound') > -1 ||
                        status.indexOf('MultiFacesFound') > - 1) {

                        // hide head and userinstruction
                        hideHead();
                        $('#uuiinstruction').hide();
                        $('#uuicanvas').css('filter', 'blur(10px)');

                        // show message
                        $msg = $('#uuimessage');
                        $msg.html(msg);
                        $msg.stop(true).fadeIn().delay(1800).fadeOut();
                        setTimeout(function () { $('#uuicanvas').css('filter', ''); }, 1900);
                    }

                    // display some animations/images depending on the status
                    let uploaded = bwsCapture.getUploaded();
                    let recording = uploaded + bwsCapture.getUploading();
                    // use modulo calculation for images more than 4
                    let modRecording = ((recording-1) % 4) + 1;
                    let modUploaded = ((uploaded-1) % 4) + 1;

                    if (status === 'Uploading') {
                        // begin an upload - current image
                        $('#uuiwait' + modRecording).hide();
                        $('#uuiupload' + modRecording).show();
                        $('#uuiuploaded' + modRecording).hide();
                    } else if (status === 'Uploaded') {
                        // successfull upload (we should have a dataURL)
                        if (dataURL) {
                            $('#uuiupload' + modUploaded).hide();
                            let $image = $('#uuiuploaded' + modUploaded);
                            $image.attr('src', dataURL);
                            $image.show();
                        }
                    } else if (status === 'NoFaceFound' || status === 'MultipleFacesFound') {
                        // upload failed
                        recording++;
                        modRecording = ((recording-1) % 4) + 1;
                        $('#uuiupload' + modRecording).hide();
                        $('#uuiwait' + modRecording).show();
                    }
                }
            });
        }

        // called by onStart to update GUI
        function captureStarted() {
            $('#uuisplash').hide();
            $('#uuiwebapp').show();
            $('#uuimessage').show();
            $('#uuiinstruction').show();

            // Currently not neccessary - therefore the button is not shown!
            // $('#uuimirror').show().click(mirror);

            if (autostart) {
              setTimeout(function () { startRecording(true); }, 1000);
            }
            else {
              $('#uuistart').show().click(function () { startRecording(task === 'enrollment'); });
            }
        }

        // called from onStart when recording is done
        function stopRecording() {
            $('#uuiinstruction').hide();
            hideHead();

            bwsCapture.stopRecording();
            for (let i = 1; i <= 4; i++) {
                $('#uuiimage' + i).hide();
            }
        }

        /* -------------------- Displaying head ---------------------------------------------------*/

        var camera, scene, renderer;
        var startTime;
        var resetHead = false;
        const maxVertical = 0.25;
        const maxHorizontal = 0.25;

        function initHead() {
		    // renderer
            try {
                renderer = new THREE.WebGLRenderer({ alpha: true });
            }
            catch (e) {
                return false;
            }
			
            let container = document.getElementById('uuihead');
            document.body.appendChild(container);

            let width = $('#uuihead').width();
            let height = $('#uuihead').height();
            let uuihead = $('#uuihead');
            $('#uuiwebapp').append(uuihead);

            // camera
            camera = new THREE.PerspectiveCamera(20, width / height, 1, 1000);
            camera.position.set(0, 0, 5);

            // scene
            scene = new THREE.Scene();
            let ambientLight = new THREE.AmbientLight(0x4953FF, 0.4);
            scene.add(ambientLight);
            let pointLight = new THREE.PointLight(0x3067FF, 0.8);
            camera.add(pointLight);
            scene.add(camera);

            // texture
            let manager = new THREE.LoadingManager();
            manager.onProgress = function (item, loaded, total) {
                console.log(item, loaded, total);
            };

            // model
            let onProgress = function (xhr) {
                if (xhr.lengthComputable) {
                    let percentComplete = xhr.loaded / xhr.total * 100;
                    console.log(Math.round(percentComplete, 2) + '% downloaded');
                }
            };
            let onError = function (xhr) {};
            let loader = new THREE.OBJLoader(manager);
            let material = new THREE.MeshLambertMaterial({ transparent: false, opacity: 0.6 });

            loader.load('/bws/model/head.obj', function (head) {
                head.traverse(function (child) {
                    if (child instanceof THREE.Mesh) {
                     //   child.material = material;
                    }
                });
                head.name = 'BioIDHead';
                head.position.y = 0;
                scene.add(head);
            }, onProgress, onError);
 
            renderer.setClearColor(0x000000, 0); // the default
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.setSize(width, height);

            container.appendChild(renderer.domElement);
            window.addEventListener('resize', onHeadResize, false);
			
			return true;
        }

        function onHeadResize() {
            let width = 0;
            let height = 0;

            let canvasWidth = $('#uuicanvas').width();
            let canvasHeight = $('#uuicanvas').height();

            if (canvasWidth > canvasHeight) {
                height = canvasHeight - canvasHeight/3;
                width = height * 3 / 4;
            }
            else {
                width = canvasWidth - canvasWidth / 3;
                height = width * 4 / 3;
            }
         
            // Avoid floating for canvas size (performance issue)
            width = Math.floor(width);
            height = Math.floor(height);

            $('#uuihead').css({'margin-top': Math.floor(-height/2), 'margin-left': Math.floor(-width/2) });
            $('#uuihead').attr('width', width);
            $('#uuihead').attr('height', height);

            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height);
            renderer.render(scene, camera);
        }

        function resetHeadDisplay() {
            currentTag = '';
            parentTag = '';
            resetHead = true;
        }

        function setCurrentTag(tag) {
            if (currentTag !== '') {
                parentTag = currentTag;
            }
           
            currentTag = tag;
            startTime = new Date().getTime();

            animateHead();
            console.log('DisplayTag: ' + tag);
        }

        function animateHead() {
            // animation time
            let speed = 0.000005;
            let endTime = new Date().getTime();
            let deltaTime = (endTime - startTime);
            let delta = deltaTime * speed;

            let head = scene.getObjectByName('BioIDHead');
            let doAnimation = false;

            if (head) {
                if (resetHead) {
                    // reset head rotation to center
                    head.rotation.x = 0;
                    head.rotation.y = 0;
                    resetHead = false;
                    doAnimation = true;
                    showHead();
                }
                else {
                    if (currentTag === 'any') {
                        if (task === 'enrollment') {
                            // get predefined direction for better enrollment
                            let recording = bwsCapture.getUploaded() + bwsCapture.getUploading() - 1;
                            currentTag = enrollmentTags[recording];
                        }
                        else {
                            currentTag = predefinedTags[Math.floor(Math.random() * Math.floor(4))];
                        }
                    }

                    if (currentTag === 'down') {
                        head.rotation.y = 0;
                        if (parentTag === 'up') {
                            if (head.rotation.x <= 0) {
                                head.rotation.x += delta;
                                doAnimation = true;
                            }
                        }
                        else {
                            if (head.rotation.x >= 0 && head.rotation.x < maxVertical) {
                                head.rotation.x += delta;
                                doAnimation = true;
                            }
                        }
                    }
                    else if (currentTag === 'up') {
                        head.rotation.y = 0;
                        if (parentTag === 'down') {
                            if (head.rotation.x >= 0) {
                                head.rotation.x -= delta;
                                doAnimation = true;
                            }
                        }
                        else {
                            if (head.rotation.x >= -maxVertical && head.rotation.x <= 0) {
                                head.rotation.x -= delta;
                                doAnimation = true;
                            }
                        }
                    }
                    else if (currentTag === 'left') {
                        head.rotation.x = 0;
                        if (parentTag === 'right') {
                            if (head.rotation.y >= 0) {
                                head.rotation.y -= delta;
                                doAnimation = true;
                            }
                        }
                        else {
                            if (head.rotation.y >= -maxHorizontal && head.rotation.y <= 0) {
                                head.rotation.y -= delta;
                                doAnimation = true;
                            }
                        }
                    }
                    else if (currentTag === 'right') {
                        head.rotation.x = 0;
                        if (parentTag === 'left') {
                            if (head.rotation.y <= 0) {
                                head.rotation.y += delta;
                                doAnimation = true;
                            }
                        }
                        else {
                            if (head.rotation.y >= 0 && head.rotation.y <= maxHorizontal) {
                                head.rotation.y += delta;
                                doAnimation = true;
                            }
                        }
                    }
                }

                if (doAnimation) {
                    requestAnimationFrame(animateHead);
                }
                renderer.render(scene, camera);
            }
        }

        function showHead() {
            onHeadResize();
            $('#uuihead').show();
        }

        function hideHead() {
            $('#uuihead').hide();
        }

    </script>
</body>
</html>
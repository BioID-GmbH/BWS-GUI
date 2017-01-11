/*! BioID Web Service - 2017-05-01
*   image capture and recognition library - v1.1.0
* https://www.bioid.com
* Copyright (C) BioID GmbH.
*/

/*jslint eqeqeq: true, plusplus: true, bitwise: true, white: true, undef: true, strict: false */

(function (bws, $, undefined) {
    // init image capture and recognition library
    bws.initcapture = function (canvasElement, motionBarCanvasElement, issuedToken, options) {
        var defaults = {
            host: 'bws.bioid.com',
            task: 'verification', // | identification | enrollment
            trait: 'FACE',
            maxheight: 480,
            recordings: 2,
            maxupload: 20,
            challengeResponse: false,
            motionareaheight: 160,
            threshold: 20,
            mirror: true,
            color: false,
            display: 'circle',
            // motion drawing (if motionBarCanvasElement != undefined)
            motionBackground: 'rgba(100, 150, 200, 0.1)',
            motionColor: 'rgba(100, 200, 0, 0.4)',
            motionThresholdColor: '#aa0000'
        };

        // apply options to our default settings
        var settings = $.extend({}, defaults, options);
        if (!settings.apiurl) { settings.apiurl = 'https://' + settings.host + '/extension/'; }
        // the issued token
        var token = issuedToken;
        // the canvas to draw the image and overlays
        var canvas = canvasElement;
        if (!canvas) { // we can't do anything without a canvas
            alert('Please provide a valid canvas element to initialize the BWS capture module!');
        }
        var motionbarcanvas = motionBarCanvasElement;

        // private helper elements
        var video = document.createElement('video');
        var copycanvas = document.createElement('canvas');
        var motioncanvas = document.createElement('canvas');

        // we need to put some additional things into our closure
        var videoStream;
        var processInterval; 
        var noMotionTimer;
        var noActivityTimer;

        // template for motion detection
        var templateImage;
        var templateWidth;
        var templateHeight;
        var templateXpos;
        var templateYpos;
        var centerX;
        var centerY;

        // possible status values: Uploading, Uploaded, DisplayTag, NoFaceFound, MultipleFacesFound, NoMovement, Verifying, Training, LiveDetectionFailed, ChallengeResponseFailed, NotRecognized
        var statusCallback; // arguments: status { message | tag } { dataURL }
        var doneCallback; // arguments: error
        var uploaded = 0, uploading = 0, captured = 0, capturing = false;
        var tag = 'any'; // any, up, down, left, right
        var tags = [];

        var motionResult = { trigger: false, motion: 0 };

        // public method to start capturing. The functions
        // onSuccess(), onFailure(error) and onDone(error) must be applied,
        // the function onStatus(status, message, dataURL) is optional
        var start = function (onSuccess, onFailure, onDone, onStatus) {
            console.log('Starting capture...');
            doneCallback = onDone;
            statusCallback = onStatus;

            if (videoStream) {
                // we have been started already
                video.play();
                return;
            }

            // add event handlers - these are not coming with Chrome :(
            video.onplaying = function () { console.log('Video playing', video.videoWidth, video.videoHeight, video.clientWidth, video.clientHeight); };
            video.onpause = function () { console.log('Video paused'); };

            // different browsers use different APIs :(
            navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
            window.URL = window.URL || window.webkitURL;

            if (navigator.getUserMedia) {
                // we could apply the width and height constraints here, but those are not yet registered
                navigator.getUserMedia({ video: true, audio: false }, function (stream) {
                    console.log('Video capture stream has been created');
                    videoStream = stream;

                    if (window.URL) {
                        video.src = window.URL.createObjectURL(stream);
                    } else if (video.mozSrcObject) {
                        video.mozSrcObject = stream;
                    } else {
                        video.src = stream;
                    }
                    // start timer to init the various canvases ...
                    initializeCanvases();
                    console.log('capture started');
                    onSuccess();
                }, function (error) {
                    console.log('Video capture failed to start with error ' + error);
                    onFailure(error);
                });
            }
            else {
                console.log('getUserMedia not supported!');
                onFailure();
            }
        };

        // private method to init the size of the canvases
        function initializeCanvases() {
            // ensure that the stuff is running
            video.play();
            // we need to wait a few ms until we have a video available
            setTimeout(function () {
                console.log('setting canvas size', video.videoWidth, video.videoHeight);
                if (video.videoWidth === 0) {
                    // retry
                    initializeCanvases();
                } else {
                    canvas.width = canvas.clientWidth;
                    canvas.height = canvas.clientHeight;
                    var aspectratio = video.videoWidth / video.videoHeight < 3 / 4 ? video.videoWidth / video.videoHeight : 3 / 4;
                    copycanvas.height = video.videoHeight > settings.maxheight ? settings.maxheight : video.videoHeight;
                    copycanvas.width = copycanvas.height * aspectratio;
                    motioncanvas.height = settings.motionareaheight;
                    motioncanvas.width = motioncanvas.height * aspectratio;
                    if (settings.mirror) { mirror(); }
                    // set an interval-timer to grab about 20 frames per second
                    processInterval = setInterval(function () { processFrame(); }, 50);
                }
            }, 100);
        }

        // we give a NoMovement response every 6 seconds
        function startMotionTimer() {
            clearInterval(noMotionTimer);
            noMotionTimer = setInterval(function () {
                if (uploading + uploaded < settings.recordings) {
                    if (statusCallback) { statusCallback('NoMovement'); }
                }
            }, 6000);
        }

        // after a given time without activity from the user we abort the process
        function startActivityTimer() {
            clearInterval(noActivityTimer);
            noActivityTimer = setInterval(function () {
                if (uploading === 0) {
                    stop();
                    doneCallback('Activity time is over!');
                }
                else {
                    startActivityTimer();
                }
            }, 20000);
        }

        // generate a new challenge response tag or resets it to 'any'
        function setTag() {
            if (settings.challengeResponse) {
                var currentRecording = uploaded + uploading;
                if (currentRecording > 0 && currentRecording < settings.recordings) {
                    if (tags.length >= currentRecording) {
                        // use the preset (typically via the BWS access token) tags!
                        tag = tags[currentRecording - 1];
                    }
                    else {
                        var newtag = tag;
                        if (currentRecording % 2 === 1) {
                            // create a random tag
                            var r = Math.random();
                            if (currentRecording === 1) {
                                if (r < 0.25) { newtag = 'up'; }
                                else if (r < 0.5) { newtag = 'down'; }
                                else if (r < 0.75) { newtag = 'left'; }
                                else { newtag = 'right'; }
                            }
                            else {
                                // create a tag in a direction different to the last movement axis 
                                if (tag === 'up' || tag === 'down') {
                                    if (r < 0.5) { newtag = 'left'; }
                                    else { newtag = 'right'; }
                                }
                                else {
                                    if (r < 0.5) { newtag = 'up'; }
                                    else { newtag = 'down'; }
                                }
                            }
                        }
                        else {
                            // create a tag in the opposite direction of the last tag
                            switch (tag) {
                                case 'left':
                                    newtag = 'right';
                                    break;
                                case 'right':
                                    newtag = 'left';
                                    break;
                                case 'up':
                                    newtag = 'down';
                                    break;
                                case 'down':
                                    newtag = 'up';
                                    break;
                                default:
                                    break;
                            }
                        }
                        console.log('Switched tag for recording #' + currentRecording + ' from ' + tag + ' to ' + newtag);
                        tag = newtag;
                    }
                }
                else { tag = 'any'; }
            }
         
            if (statusCallback) { statusCallback('DisplayTag', tag); }
            if (tag !== 'any' && capturing) {
                // give user some time to react!
                capturing = false;
                setTimeout(function () { capturing = true; }, 1000);
            }
        }

        // private worker method for each frame
        function processFrame() {
            var x = 0, y = 0, w = copycanvas.width, h = copycanvas.height, aspectratio = w / h;
            var cutoff = video.videoWidth - (video.videoHeight * aspectratio);
            var draw = canvas.getContext('2d');
            var copy = copycanvas.getContext('2d');

            // we draw the frames manually using the private video element and the copy interim canvas
            copy.drawImage(video, cutoff / 2, 0, video.videoWidth - cutoff, video.videoHeight, 0, 0, copycanvas.width, copycanvas.height);

            // ensure that the canvas does not scale internally
            canvas.width = canvas.clientWidth;
            canvas.height = canvas.clientHeight;
            // white background
            draw.fillStyle = 'white';
            draw.fillRect(0, 0, canvas.width, canvas.height);
            // center image in canvas
            if (canvas.width / canvas.height > aspectratio) {
                // center horizontally
                y = 0;
                h = canvas.height;
                w = h * aspectratio;
                x = (canvas.width - w) / 2;
                radius = h / 2;
            }
            else {
                // center vertically
                x = 0;
                w = canvas.width;
                h = w / aspectratio;
                y = (canvas.height - h) / 2;
                radius = w / 2;
            }

            draw.drawImage(copycanvas, 0, 0, copycanvas.width, copycanvas.height, x, y, w, h);
           
            if (settings.display === 'circle') {          
                // now we 'fade-out' the edges of the image so that the user concentrates on the center
                var gradient = draw.createRadialGradient(canvas.width / 2, canvas.height / 2, 0, canvas.width / 2, canvas.height / 2, w * 0.5);
                gradient.addColorStop(0, 'transparent');
                gradient.addColorStop(0.6, 'rgba(255, 255, 255, 0.3)');
                gradient.addColorStop(0.9, 'rgba(255, 255, 255, 0.7)');
                gradient.addColorStop(1, 'rgba(255, 255, 255, 1)');
                draw.fillStyle = gradient;
                draw.fillRect(0, 0, canvas.width, canvas.height);
            }
       
            if (capturing && uploaded < settings.recordings) {
                // we may need to switch on the tags again ??????
                //if (settings.challengeResponse && tag === 'any') { setTag(); }

                // first frame has trigger true
                motionResult = { trigger: true, motion: 0 };

                if (captured > settings.maxupload) {
                    stop();
                    doneCallback('The maximum number of uploads has been reached!');
                }

                // scale current image into the motion canvas
                var motionctx = motioncanvas.getContext('2d');
                motionctx.drawImage(copycanvas, copycanvas.width / 8, copycanvas.height / 8, copycanvas.width - copycanvas.width / 4, copycanvas.height - copycanvas.height / 4, 0, 0, motioncanvas.width, motioncanvas.height);
                var currentImageData = motionctx.getImageData(0, 0, motioncanvas.width, motioncanvas.height);
              
                if (templateImage) {
                    // calculate motion ...
                    motionResult = motionDetection(currentImageData);
                    // draw motion bar
                    drawMotionBar(motionResult.motion);
                }
                if (motionResult.trigger) {
                    if (uploaded + uploading < settings.recordings) {
                        // in case we are not already bussy with some uploads start upload procedure
                        upload();
                        // current image is the new reference frame - create template
                        createTemplate(currentImageData);
                    }
                }
            }
        }

        // public method to draw the motion bar
        function drawMotionBar(motion) {
            if (motionbarcanvas) {
                motionbarcanvas.width = motionbarcanvas.clientWidth;
                motionbarcanvas.height = motionbarcanvas.clientHeight;
                $(motionbarcanvas).show();
                motion = motion;
                var motionValue = motionbarcanvas.height * (1 - motion / 100.0);
                var threshold = motionbarcanvas.height * (1 - settings.threshold / 100.0);
                var draw = motionbarcanvas.getContext('2d');
                draw.fillStyle = settings.motionBackground;
                draw.fillRect(0, 0, motionbarcanvas.width, motionbarcanvas.height);
                draw.fillStyle = settings.motionColor;
                draw.fillRect(0, motionValue, motionbarcanvas.width, motionbarcanvas.height - motionValue);
                draw.fillStyle = settings.motionThresholdColor;
                draw.fillRect(0, threshold, motionbarcanvas.width, 2);
            }
        }

        // public method that uploads an image to the BWS
        function upload(dataURL) {
            startMotionTimer();
            // start upload procedure, but only if we still have to
            if (capturing && uploaded + uploading < settings.recordings) {
                captured++;
                uploading++;
                if (statusCallback) { statusCallback('Uploading'); }
                // create (gray-) png and upload ...
                if (!dataURL) {
                    dataURL = settings.color ? copycanvas.toDataURL() : bws.toGrayDataURL(copycanvas);
                }
                console.log('sizeof dataURL', dataURL.length);
                if (!$.support.cors) {
                    // the call below typically requires Cross-Origin Resource Sharing!
                    console.log('this browser does not support cors, e.g. IE8 or 9');
                }
                var jqxhr = $.ajax({
                    type: 'POST',
                    url: settings.apiurl + 'upload?tag=' + tag + '&index=' + captured + '&trait=' + settings.trait,
                    data: dataURL,
                    // don't forget the authentication header
                    headers: { 'Authorization': 'Bearer ' + token }
                }).done(function (data, textStatus, jqXHR) {
                    uploading--;
                    if (data.Accepted) {
                        uploaded++;
                        console.log('upload succeeded', data.Warnings);
                        if (statusCallback) { statusCallback('Uploaded', data.Warnings.toString(), dataURL); }
                    } else {
                        console.log('upload error', data.Error);
                        if (statusCallback) { statusCallback(data.Error); }
                        // show a new tag if neccessary (if it was off)
                        if (settings.challengeResponse) { // && tag === 'any') {
                            setTag();
                        }
                        startMotionTimer();
                    }
                    if (uploaded >= settings.recordings && uploading === 0) {
                        // go for biometric task
                        performTask();
                    }
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    // ups, call failed, typically due to
                    // Unauthorized (invalid token) or
                    // BadRequest (Invalid or unsupported sample format) or
                    // InternalServerError (An exception occured)
                    console.log('upload failed', textStatus, errorThrown, jqXHR.responseText);
                    stop();
                    // redirect to caller with error response..
                    doneCallback(errorThrown);
                });
                // show a new tag if neccessary
                setTag();
            }
        }

        // perform biometric task enrollment or verification with already uploaded images
        function performTask() {
            clearInterval(noMotionTimer);
            var url;
            if (settings.task === 'enrollment') {
                // go for enrollment
                url = settings.apiurl + 'enroll';
                if (statusCallback) { statusCallback('Training'); }
            } else if (settings.task === 'identification') {
                // go for identification
                url = settings.apiurl + 'identify';
                if (statusCallback) { statusCallback('Identifying'); }
            }
            else {
                // or for verification
                url = settings.apiurl + 'verify';
                if (statusCallback) { statusCallback('Verifying'); }
            }

            // perform the call
            var jqxhr = $.ajax({
                type: 'GET',
                url: url,
                headers: { 'Authorization': 'Bearer ' + token }
            }).done(function (data, textStatus, jqXHR) {
                if (data.Success) {
                    console.log('task succeeded');
                    stop();
                    doneCallback();
                } else {
                    console.log('task failed', data.Error);
                    var err = data.Error ? data.Error : 'NotRecognized';
                    if (statusCallback) { statusCallback(err); }
                    recording(false); // stop() -> in case of NoTemplateAvailable or no re-tries any more!?
                    doneCallback(err, err !== 'NoTemplateAvailable');
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                // ups, call failed, typically due to
                // Unauthorized (invalid token) or
                // BadRequest (Invalid package) or
                // InternalServerError (An exception occured)
                console.log('task failed', textStatus, errorThrown, jqXHR.responseText);
                stop();
                // redirect to caller with error response..
                doneCallback(errorThrown);
            });
        }

        // template for cross-correlation
        function createTemplate(first) {
            console.log("createTemplate");

            centerX = first.width / 2;
            centerY = first.height / 2;

            templateImage = null;
            templateWidth = first.width / 4;
            templateHeight = first.height / 4 + first.height / 8;
            templateXpos = centerX - templateWidth / 2;
            templateYpos = centerY - templateHeight / 2;

            // cut out the template
            // we use a small width, quarter-size image around the center as template
            templateImage = new Uint8ClampedArray(templateWidth * templateHeight);           
              
            var counter = 0;
            var p = first.data;
            for (var y = templateYpos; y < templateYpos + templateHeight; y++) {
                // we use only the green plane here 
                var bufferIndex = (y * first.width * 4) + templateXpos * 4 + 1;
                for (var x = templateXpos; x < templateXpos + templateWidth; x++) {
                    var templatepixel = p[bufferIndex];
                    templateImage[counter++] = templatepixel;
                    // we use only the green plane here 
                    bufferIndex += 4;
                }
            }         
        }

        // motion detection by a normalized cross-correlation
        function motionDetection(current) {
            motionResult = { trigger: false, motion: 0 };

            // this is the major computing step: Perform a normalized cross-correlation between the template of the first image and each incoming image
            // this algorithm is basically called "Template Matching" - we use the normalized cross correlation to be independent of lighting changes
            // we calculate the correlation of template and image over the whole image area
            var bestHitX = 0;
            var bestHitY = 0;
            var maxCorr = 0;
            var searchWidth = current.width / 4;
            var searchHeight = current.height / 4;
            var p = current.data;

            for (y = centerY - searchHeight; y <= centerY + searchHeight - templateHeight; y++) {
                for (x = centerX - searchWidth; x <= centerX + searchWidth - templateWidth; x++) {
                    var nominator = 0;
                    var denominator = 0;
                    var templateIndex = 0;

                    // Calculate the normalized cross-correlation coefficient for this position
                    for (var ty = 0; ty < templateHeight; ty++) {
                        // we use only the green plane here 
                        var bufferIndex = x * 4 + 1 + (y + ty) * current.width * 4;
                        for (var tx = 0; tx < templateWidth; tx++) {
                            var imagepixel = p[bufferIndex];
                            nominator += templateImage[templateIndex++] * imagepixel;
                            denominator += imagepixel * imagepixel;
                            // we use only the green plane here 
                            bufferIndex += 4;
                        }
                    }

                    // The NCC coefficient is then (watch out for division-by-zero errors for pure black images):
                    var ncc = 0.0;
                    if (denominator > 0) {
                        ncc = nominator * nominator / denominator;
                    }
                    // Is it higher than what we had before?
                    if (ncc > maxCorr) {
                        maxCorr = ncc;
                        bestHitX = x;
                        bestHitY = y;
                    }
                }
            }

            // now the most similar position of the template is (bestHitX, bestHitY). Calculate the difference from the origin:
            var distX = bestHitX - templateXpos;
            var distY = bestHitY - templateYpos;
            var movementDiff = Math.sqrt(distX * distX + distY * distY);
            // the maximum movement possible is a complete shift into one of the corners, i.e:
            var maxDistX = searchWidth - templateWidth / 2;
            var maxDistY = searchHeight - templateHeight / 2;
            var maximumMovement = Math.sqrt(maxDistX * maxDistX + maxDistY * maxDistY);

            // the percentage of the detected movement is therefore:
            var movementPercentage = movementDiff / maximumMovement * 100;
            if (movementPercentage > 100) {
                movementPercentage = 100;
            }
            motionResult.motion = movementPercentage;

            // trigger if movementPercentage is above threshold (default: when 20% of maximum movement is exceeded)
            if (movementPercentage > settings.threshold) {
                motionResult.trigger = true;
            }

            return motionResult;
        }

        // public method to pause capturing
        var stop = function () {
            console.log('Pausing capture...');
            recording(false);
            video.pause();
            clearInterval(processInterval);
            videoStream = null;
        };

        // start or stop recording
        function recording(capture, challenges) {
            templateImage = null;
            clearInterval(noMotionTimer);
            clearInterval(noActivityTimer);   
            uploaded = 0;
            uploading = 0;
            capturing = capture;
            tags = challenges ? challenges : [];
            if (motionbarcanvas) { $(motionbarcanvas).hide(); }
            setTag();
        }

        var startCountdown = function (finishedCallback) {
            if (statusCallback) {
                statusCallback('3');
            }
            setTimeout(function () {
                if (statusCallback) {
                    statusCallback('2');
                }
            }, 400);
            setTimeout(function () {
                if (statusCallback) {
                    statusCallback('1');
                }
            }, 800);
            setTimeout(function () {
                if (statusCallback) {
                    statusCallback('');
                }
                if (finishedCallback) finishedCallback();
                startActivityTimer();
            }, 1200);
        };

        // public method to mirror the display of the captured image
        var mirror = function () {
            var copy = copycanvas.getContext('2d');
            copy.translate(copycanvas.width, 0);
            copy.scale(-1, 1);
        };

        return {
            start: start,
            stop: stop,
            startCountdown: startCountdown,
            startRecording: function (challenges) { recording(true, challenges); },
            stopRecording: function () { recording(false); },
            drawMotionBar: drawMotionBar,
            upload: upload,
            mirror: mirror,
            getUploading: function () { return uploading; },
            getUploaded: function () { return uploaded; }
        };   
    };

    // replacement of the HTMLCanvasElement.toDataURL method that creates 8bit gray PNGs
    // see: http://www.w3.org/TR/PNG/, http://www.ietf.org/rfc/rfc1950.txt and http://www.ietf.org/rfc/rfc1951.txt
    bws.toGrayDataURL = function (canvas) {
        var i, j,
            width = canvas.width,
            height = canvas.height,
            depth = 8;
        // pixel data and row filter identifier size
        var pix_size = height * (width + 1);
        // deflate header, pix_size, block headers (N 64kB blocks), adler32 checksum
        var data_size = 2 + pix_size + 5 * Math.floor((0xfffe + pix_size) / 0xffff) + 4;
        // offsets and sizes of Png chunks (= 4byte length + 4byte type + data + 4 byte crc = 12byte + data)
        var ihdr_offs = 8,								    // IHDR offset and size
            ihdr_size = 12 + 13,                            // width 4, height 4, depth 1, Colour type 1, Compression method 1, Filter method 1, Interlace method 1
            idat_offs = ihdr_offs + ihdr_size,	            // IDAT offset and size
            idat_size = 12 + data_size,
            iend_offs = idat_offs + idat_size,	            // IEND offset and size
            iend_size = 12,
            buffer_size = iend_offs + iend_size;            // total PNG size

        var buffer = new Uint8ClampedArray(buffer_size);
        var _crc32 = [];

        // initialize non-zero elements
        // first 8 bytes (in decimal: 137 80 78 71 13 10 26 10)
        writeString(buffer, 0, '\x89PNG\r\n\x1A\n');
        var offset = ihdr_offs;
        offset += writeByte4(buffer, offset, ihdr_size - 12);
        offset += writeString(buffer, offset, 'IHDR');
        offset += writeByte4(buffer, offset, width);
        offset += writeByte4(buffer, offset, height);
        buffer[offset] = 8;
        writeByte4(buffer, idat_offs, idat_size - 12);
        writeString(buffer, idat_offs + 4, 'IDAT');
        writeByte4(buffer, iend_offs, iend_size - 12);
        writeString(buffer, iend_offs + 4, 'IEND');

        // initialize deflate header
        var header = ((8 + (7 << 4)) << 8) | (3 << 6); // CMF(deflate + 32K windows size)|FLG(compression level 3))
        header += 31 - (header % 31); // check bits
        writeByte2(buffer, idat_offs + 8, header);

        // initialize deflate block headers
        var totalsize = 0;
        for (i = 0; totalsize < pix_size; i++) {
            var size, bits;
            if (totalsize + 0xffff < pix_size) {
                size = 0xffff;
                bits = 0; // BTYPE = 00 (Non-compressed blocks)
            } else {
                size = pix_size - totalsize;
                bits = 1; // BFINAL | BTYPE = 00
            }
            var offs = idat_offs + 8 + 2 + i * 5 + totalsize;
            buffer[offs++] = bits;
            buffer[offs++] = size & 0xff;
            buffer[offs++] = (size >> 8) & 0xff;
            buffer[offs++] = ~size & 0xff;
            buffer[offs++] = (~size >> 8) & 0xff;
            totalsize += size;
        }

        // create crc32 lookup table
        for (i = 0; i < 256; i++) {
            var c = i;
            for (j = 0; j < 8; j++) {
                if (c & 1) {
                    c = -306674912 ^ ((c >> 1) & 0x7fffffff);
                } else {
                    c = (c >> 1) & 0x7fffffff;
                }
            }
            _crc32[i] = c;
        }

        var setGrayValues = function (canvas) {
            // compute gray values for dest buffer
            var d = canvas.getContext('2d').getImageData(0, 0, width, height).data; // this is a Uint8ClampedArray
            var offset = idat_offs + 8 + 2 + 5, i = 0, r = 0, gray, c;

            // at the same time we compute adler32 of output pixels and row filter bytes (about 50ms faster than doing it in seperate loops)
            var BASE = 65521; // largest prime smaller than 65536 
            var NMAX = 5552;  // NMAX is the largest n such that 255n(n+1)/2 + (n+1)(BASE-1) <= 2^32-1
            var s1 = 1;
            var s2 = 0;
            var n = NMAX;

            for (var y = 0; y < height; y++) {
                // adler32 for row filter value 0
                //s1 += 0;
                s2 += s1;
                if ((n -= 1) === 0) {
                    s1 %= BASE;
                    s2 %= BASE;
                    n = NMAX;
                }
                // skip row filter byte
                if (++i === 0xffff) {
                    // skip block header
                    offset += i + 5;
                    i = 0;
                }

                for (var x = 0; x < width; x++) {
                    gray = d[r] * 0.3 + d[r + 1] * 0.59 + d[r + 2] * 0.11;
                    buffer[offset + i] = gray;
                    // adler32
                    s1 += buffer[offset + i];
                    s2 += s1;
                    if ((n -= 1) === 0) {
                        s1 %= BASE;
                        s2 %= BASE;
                        n = NMAX;
                    }
                    r += 4;
                    if (++i === 0xffff) {
                        // skip block header
                        offset += i + 5;
                        i = 0;
                    }
                }
            }
            // adler32
            s1 %= BASE;
            s2 %= BASE;
            writeByte4(buffer, idat_offs + idat_size - 8, (s2 << 16) | s1);
        };

        // helper functions for that ctx
        function crc32(buf, offs, size) {
            var crc = -1;
            for (var i = 4; i < size - 4; i += 1) {
                crc = _crc32[(crc ^ buf[offs + i]) & 0xff] ^ ((crc >> 8) & 0x00ffffff);
            }
            writeByte4(buf, offs + size - 4, crc ^ -1);
        }

        function writeString(buf, offs, s) {
            for (var i = 0; i < s.length; i++) {
                buf[offs++] = s.charCodeAt(i);
            }
            return s.length;
        }

        function writeByte2(buf, offs, w) {
            buf[offs++] = (w >> 8) & 0xff;
            buf[offs++] = w & 0xff;
            return 2;
        }

        function writeByte4(buf, offs, w) {
            buf[offs++] = (w >> 24) & 0xff;
            buf[offs++] = (w >> 16) & 0xff;
            buf[offs++] = (w >> 8) & 0xff;
            buf[offs++] = w & 0xff;
            return 4;
        }

        // we immediately perform the conversion
        setGrayValues(canvas);

        // compute crc32 of the PNG chunks
        crc32(buffer, ihdr_offs, ihdr_size);
        crc32(buffer, idat_offs, idat_size);
        crc32(buffer, iend_offs, iend_size);

        // encode the image
        // convert PNG to string (needs to be done in blocks, as chrome throws stack overflow exception)
        var s = '';
        for (i = 0; i < buffer_size; i += 0xffff) {
            var elements = i + 0xffff < buffer_size ? 0xffff : buffer_size - i;
            var view = new Uint8ClampedArray(buffer.buffer, i, elements);
            s += String.fromCharCode.apply(null, view); 
        }
        // Base64 encoding
        s = btoa(s);
        return 'data:image/png;base64,' + s;
    };
}(window.bws = window.bws || {}, jQuery));
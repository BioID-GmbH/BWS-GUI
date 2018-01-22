BWS-GUI
=======

BWS HTML5 Unified User Interface
------------------------

The **BioID Web Service** (BWS) is a cloud-based online service providing a powerful multimodal biometric technology with liveness detection to application developers. But often developers have some trouble writing a user interface collecting the data required to perform the biometric tasks, notably face images. Therefore we want to provide some sample code that might be used as a user interface to the BWS, in this case for a web based application using HTML5 with jQuery.

The **uui** folder contains everything you typically need to run a biometric task within a web browser. There are some `PerformTask` sample implementations (MVC, plain HTML5, PHP, ...) containing the client side code to perform the biometric task. The required javascript-, css- and image-files are in the corresponding folders. The jQuery script `bws.capture.js` does most of the work regarding image capturing, motion detection and AJAX-communication to the BWS. In `clientBin` you find an optionally usable Silverlight module that can be used to capture images in case that the browser does not support the HTML5 Media Capture API. The folder `lang` is for language support and contains json files for each supported language (currently EN and DE are available).

### Examples:

Complete samples are provided in the **samples** folder, e.g. there is a Visual Studio ASP.NET MVC project in the `mvc` subfolder. 

To successfully run one of the sample web apps, you need to have access to an existing BWS installation. If you don't have this access you can [register for a trial instance](https://bwsportal.bioid.com/register).

You can also try this BWS user interface via our [Playgound website](https://playground.bioid.com/ExploreBiometrics).

You can find some more information about our [face recognition](https://www.bioid.com) technology.

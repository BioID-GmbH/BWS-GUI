BWS-GUI
=======

BWS HTML5 Unified User Interface (UUI)
-------------------------------------

The **BioID Web Service** (BWS) is a cloud-based online service providing a powerful multimodal biometric technology with [liveness detection][liveness] to application developers. Often developers have some trouble writing a user interface collecting the data required to perform the biometric tasks, notably face images. Therefore we want to provide some sample code that might be used as a user interface to the BWS, in this case for a web based application using HTML5 with jQuery.

The **uui** folder contains the basics you typically need to run a biometric task within a web browser. There are some sample implementations (ASP.NET Core, PHP) containing the client side code [uui.cshtml](./samples/aspnetcore/uuisample/Views/Home/Uui.cshtml) or [uui.php](./samples/php/uui.php) to perform the biometric task. The required javascript-, css- and image-files are in the corresponding folders. The jQuery script [bws.capture.js](./uui/js/bws.capture.js) does most of the work regarding image capturing, motion detection and AJAX-communication to the BWS. The folder [language](./uui/language)  is for language support and contains json files for each supported language (currently EN and DE are available). The folder [model](./uui/model) contains the new 3D head. It shows the user the prompted direction for movements.

### Examples:

A complete sample is provided in the **samples** folder, there is a Visual Studio ASP.NET Core project in the `aspnetcore` subfolder. 
For PHP only the uui.php file is available! You must request a BWS token from your server before you can call this Unified User Interface (UUI). Please take a look at the [developer documentation][docs] that helps you to integrate our APIs with REST or SOAP protocol.

To successfully run one of the sample web apps, you need to have access to an existing BWS installation. If you don't have this access you can [register for a BWS trial instance][trial] - requires login.

You can also try this BWS user interface via our [Playgound website][playground] - requires login.

BioID offers a strong liveness detection API which is independent from special sensors like 3D cameras. It simply analyzes two selfies to determine whether or not these were taken from a real person or a fake. Most notably, it reliably blocks photo and video replay attacks as well as 3D masks by combining motion and texture based analysis with artificial intelligence (DCNNs).

Have a look here for more information on liveness detection:[Liveness detection | Anti-spoofing | Face - BioID][liveness]

You can find some more information about our [face recognition][bioid] technology.

[bioid]: https://www.bioid.com "BioID GmbH Homepage"
[docs]: https://developer.bioid.com/bwsreference "BWS documentation"
[playground]: https://playground.bioid.com "BioID Playground"
[trial]: https://bwsportal.bioid.com/register "Register for a trial instance"
[liveness]: https://www.bioid.com/liveness-detection/ "liveness detection"

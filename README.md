BWS-GUI
=======

BWS HTML5 User Interface

The **BioID Web Service** (BWS) is a cloud-based online service providing a powerful multimodal biometric technology with liveness detection to application-developers. But often developers have some trouble writing a user interface collecting the data required to perform the biometric tasks, notably face images. Therefore we want to provide some sample code that might be used as a user interface to the BWS, in this case for a web based application using HTML5 with jQuery.

With this Visual Studio 2013 ASP.NET MVC project we provide some views, especially one for biometric verification and one for biometric enrollment together with a jQuery script and an optional Silverlight module. The jQuery script does most of the work regarding image capturing, motion detection and AJAX-communication to the BWS. The Silverlight module is only needed to capture images in case that the Browser does not support the HTML5 Media Capture API.

To successfully run this sample web app, you need to have access to an existing BWS installation. If you don't have this access you can [register for a trial instance](https://playground.bioid.com/BioIDWebService/TrialInstanceRequisition).

You can also try this BWS user interface via our [Playgound web site](https://playground.bioid.com/ExploreBiometrics).

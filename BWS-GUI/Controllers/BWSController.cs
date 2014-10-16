using BWS_GUI.Models;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Web.Mvc;

namespace BWS_GUI.Controllers
{
    public class BWSController : Controller
    {
        /// <summary>
        /// The verification user interface.
        /// </summary>
        /// <param name="access_token">A BWS web token that has previously
        /// been issued by the BWS Token Extension.</param>
        /// <param name="return_url">An URL to redirect the user after the
        /// verification task has been finished.</param>
        /// <param name="bws_host">The BWS host the user interface shall use to 
        /// perform the biometric actions. This has to be the same host as the 
        /// one that has been used to request the access_token.</param>
        /// <param name="state">Client specific data to maintain state between
        /// request and callback.</param>
        /// <param name="challengeresponse">A boolean parameter used in conjunction
        /// with the live detection feature. If set to true, the user interface
        /// prompts the user to move the head according to the shown arrows and
        /// tags the uploaded samples with the expected head movement direction.
        /// This information is later used by the live detection procedure to
        /// perform a challenge-response authentication.</param>
        /// <param name="autoenrollment">A boolean parameter that should be set
        /// to the same value as in the call to the Token Extension. It says the
        /// user-interface to use a slightly bigger image as it would use for
        /// verification only, to ensure that the enrollment data is of good quality.</param>
        /// <returns>A view containing the verification user interface.</returns>
        public ActionResult Verify(string access_token, string return_url, string bws_host = "bws.bioid.com", string state = "", bool challengeresponse = false, bool autoenrollment = false)
        {
            return View(new BWSViewModel
            {
                Token = access_token,
                Host = bws_host,
                ReturnUrl = return_url,
                State = state,
                ChallengeResponse = challengeresponse,
                AutoEnroll = autoenrollment,
            });
        }

        /// <summary>
        /// The enrollment user interface.
        /// </summary>
        /// <param name="access_token">A BWS web token that has previously
        /// been issued by the BWS Token Extension.</param>
        /// <param name="return_url">An URL to redirect the user after the
        /// enrollment task has been finished.</param>
        /// <param name="bws_host">The BWS host the user interface shall use to 
        /// perform the biometric actions. This has to be the same host as the 
        /// one that has been used to request the access_token.</param>
        /// <param name="state">Client specific data to maintain state between
        /// request and callback.</param>
        /// <param name="challengeresponse">A boolean parameter used in conjunction
        /// with the live detection feature. If set to true, the user interface
        /// prompts the user to move the head according to the shown arrows and
        /// tags the uploaded samples with the expected head movement direction.
        /// This information is later used by the live detection procedure to
        /// perform a challenge-response authentication.</param>
        /// <returns>A view containing the enrollment user interface.</returns>
        public ActionResult Enroll(string access_token, string return_url, string bws_host = "bws.bioid.com", string state = "", bool challengeresponse = false)
        {
            return View(new BWSViewModel
            {
                Token = access_token,
                Host = bws_host,
                ReturnUrl = return_url,
                State = state,
                ChallengeResponse = challengeresponse,
            });
        }


        /// <summary>
        /// A dummy landing page
        /// </summary>
        public ActionResult Index()
        {
            return View();
        }

        /// <summary>
        /// Explicit testing ...
        /// </summary>
        public ActionResult Test()
        {
            return View(new TestViewModel());
        }

        /// <summary>
        /// Implementation of a test method for the verification user interface.
        /// </summary>
        /// <param name="testData">Test-data that shall be used.</param>
        /// <returns>A view containing the verification user interface.</returns>
        [HttpPost]
        public ActionResult Verify(TestViewModel testData)
        {
            // first we need to request a BWS token
            string token = RequestToken(testData, "verify");
            // then we can go for the verification user interface
            var model = new BWSViewModel
            {
                Token = token,
                Host = testData.Host,
                ReturnUrl = new Uri(Request.Url, "/BWS/Callback").ToString(),
                AutoEnroll = testData.AutoEnroll,
                ChallengeResponse = testData.ChallengeResponse
            };
            return View(model);
        }

        /// <summary>
        /// Implementation of a test method for the enrollment user interface.
        /// </summary>
        /// <param name="testData">Test-data that shall be used.</param>
        /// <returns>A view containing the enrollment user interface.</returns>
        [HttpPost]
        public ActionResult Enroll(TestViewModel testData)
        {
            // first we need to request a BWS token
            string token = RequestToken(testData, "enroll");
            // then we can go for the enrollment user interface
            var model = new BWSViewModel
            {
                Token = token,
                Host = testData.Host,
                ReturnUrl = new Uri(Request.Url, "/BWS/Callback").ToString(),
                ChallengeResponse = testData.ChallengeResponse
            };
            return View(model);
        }

        /// <summary>
        /// A sample callback page as called by one of the user interface pages Enroll or Verify.
        /// </summary>
        /// <param name="access_token">The BWS web token that has been passed to the user interface.</param>
        /// <param name="error">An optional error string reported by the user interface.</param>
        /// <param name="state">The state parameter as passed to the user interface.</param>
        /// <returns>A view showing the error and/or results.</returns>
        public ActionResult Callback(string access_token, string error, string state)
        {
            var model = new CallbackViewModel();
            if (!string.IsNullOrWhiteSpace(error))
            {
                if (error == "user_abort")
                    model.GuiError = "You aborted the BWS user interface.";
                else
                    model.GuiError = error;
            }

            // let's see whether we can fetch some results
            try
            {
                var httpClient = new HttpClient();
                // Note: for demonstration we use the token for authentication, but it is recommended to use your App-ID and -secret with basic authentication:
                //httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Basic", Convert.ToBase64String(System.Text.Encoding.GetEncoding("iso-8859-1").GetBytes(string.Format("{0}:{1}", appId, appSecret))));
                httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", access_token);
                // Note: here we assume that bws.bioid.com is the host that was used for the biometric task!
                UriBuilder uri = new UriBuilder("https", "bws.bioid.com", 443, "extension/result", string.Format("?access_token={0}", access_token));
                var response = httpClient.GetAsync(uri.Uri).Result;
                var responseStream = response.Content.ReadAsStreamAsync().Result;
                // we got a valid response
                if (response.StatusCode == System.Net.HttpStatusCode.OK)
                {
                    var json = JObject.Load(new JsonTextReader(new StreamReader(responseStream)));
                    model.Success = (bool)json["Success"];
                    model.BCID = (string)json["BCID"];
                    model.Action = (string)json["Action"];
                    model.Error = (string)json["Error"];
                }
                // no result has been logged for this token
                else if (response.StatusCode == System.Net.HttpStatusCode.NoContent)
                {
                    model.Error = "You have not performed any biometric action.";
                }
                // we got an error
                else if (responseStream.Length > 0)
                {
                    var json = JObject.Load(new JsonTextReader(new StreamReader(responseStream)));
                    model.Error = (string)json["Message"];
                }
            }
            catch (Exception ex)
            {
                model.Error = string.Format("Error: {0}", ex.Message);
            }
            return View(model);
        }

        /// <summary>
        /// Request a bearer token from the BWS token extension. 
        /// </summary>
        /// <returns>the issued token</returns>
        private string RequestToken(TestViewModel testData, string task)
        {
            string token = string.Empty;

            // Note: this information is typicall hardcoded or taken from a secure configuration!
            string appId = testData.AppID;
            string appSecret = testData.AppSecret;
            string bwsHost = testData.Host;

            try
            {
                var httpClient = new HttpClient();
                // requires Basic Authentication
                string encodedSecret = Convert.ToBase64String(System.Text.Encoding.GetEncoding("iso-8859-1").GetBytes(string.Format("{0}:{1}", appId, appSecret)));
                httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Basic", encodedSecret);
                // call the BWS Token Extension at the BWS host
                string query = string.Format("?id={0}&bcid={1}&task={2}{3}", appId, testData.BCID, task, task == "verify" && testData.AutoEnroll ? "&autoenroll=true" : "");
                UriBuilder uri = new UriBuilder("https", bwsHost, 443, "extension/token", query);
                var response = httpClient.GetAsync(uri.Uri).Result;
                // the issued token is in the response body
                token = response.Content.ReadAsStringAsync().Result;
            }
            catch { }
            return token;
        }
    }
}

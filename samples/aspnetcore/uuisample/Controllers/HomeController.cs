using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.WebUtilities;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.Diagnostics;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Threading.Tasks;
using uuisample.Models;

namespace uuisample.Controllers
{
    public class HomeController : Controller
    {
        private readonly IHostingEnvironment _hostingEnvironment;

        // TODO: fill in your BWS Application-ID and -secret:
        const string _appID = "your-BWS-appID";
        const string _appSecret = "your-BWS-appSecret";

        public HomeController(IHostingEnvironment hostingEnvironment)
        {
            _hostingEnvironment = hostingEnvironment;
        }

        public IActionResult Index()
        {
            // This is only necessary to check if the uui folder is on the right place!
            if (!System.IO.File.Exists(Path.Combine(_hostingEnvironment.WebRootPath, "uui/css/uui.css")))
                throw new FileNotFoundException("Please copy the complete 'uui' folder into the 'wwwroot' directory!");

            return View();
        }

        // Call the UUI
        public async Task<ActionResult> Uui(UuiFormModel model)
        {
            if (string.IsNullOrEmpty(model.ApiUrl))
            {
                return View("Index", new MessageViewModel { Theme = "danger", Heading = "Call to UUI failed!", Text = "Please specify the Web API endpoint." });
            }

            try
            {
                // well lets start by fetching a BWS token
                using (var httpClient = new HttpClient())
                {
                    string credentials = Convert.ToBase64String(Encoding.GetEncoding("iso-8859-1").GetBytes($"{_appID}:{_appSecret}"));
                    httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Basic", credentials);
                    string query = $"token?id={_appID}&bcid={model.BCID}&task={model.Operation}&livedetection=true&challenge={model.ChallengeResponse}&autoenroll={model.AutoEnroll}";

                    if (!model.ApiUrl.EndsWith("/")) model.ApiUrl += "/";
                    var uri = new Uri(new Uri(model.ApiUrl), query);

                    var response = await httpClient.GetAsync(uri);
                    if (!response.IsSuccessStatusCode)
                    {
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Call to UUI failed!", Text = response.Content.ReadAsStringAsync().Result });
                    }

                    // lets read the token
                    string access_token = await response.Content.ReadAsStringAsync();

                    // parse the token to find settings for the user interface
                    string claimstring = Encoding.UTF8.GetString(WebEncoders.Base64UrlDecode(access_token.Split('.')[1]));
                    var claims = JObject.Parse(claimstring);
                    TokenTask taskFlags = (TokenTask)claims["task"].Value<int>();

                    int recordings = (taskFlags & TokenTask.LiveDetection) == TokenTask.LiveDetection ? (taskFlags & TokenTask.Enroll) == TokenTask.Enroll ? 4 : 2 : 1;
                    string challengesJson = "[]";
                    if ((taskFlags & TokenTask.ChallengeResponse) == TokenTask.ChallengeResponse)
                    {
                        recordings = 4;
                        string challenges = (string)claims["challenge"];
                        if (!string.IsNullOrEmpty(challenges))
                        {
                            challengesJson = challenges;
                            string[][] challengeSequences = JsonConvert.DeserializeObject<string[][]>(challenges);
                            if (challengeSequences.Length > 0 && challengeSequences[0].Length > 0)
                            {
                                recordings = challengeSequences[0].Length + 1;
                            }
                        }
                    }

                    // render the BWS unified user interface
                    return View("uui", new UuiViewModel
                    {
                        Task = (taskFlags & TokenTask.Enroll) == TokenTask.Enroll ? "enrollment" :
                            (taskFlags & TokenTask.Identify) == TokenTask.Identify ? "identification" :
                            (taskFlags & TokenTask.LiveOnly) == TokenTask.LiveOnly ? "livenessdetection" :
                            "verification",
                        MaxTries = (int)(taskFlags & TokenTask.MaxTriesMask),
                        Recordings = recordings,
                        MotionThreshold = MobileDevice.IsMobileDevice(Request) ? Constants.MotionThresholdMobile : Constants.MotionThreshold,
                        ChallengeResponse = (taskFlags & TokenTask.ChallengeResponse) == TokenTask.ChallengeResponse,
                        ChallengesJson = challengesJson,
                        Token = access_token,
                        ApiUrl = model.ApiUrl,
                        ReturnUrl = $"{Request.Scheme}://{Request.Host}/home/uuicallback",
                        State = "encrypted_app_status",
                        Trait = model.Face ? (model.Periocular ? "Face,Periocular" : "Face") : "Periocular",
                        AutoEnroll = (taskFlags & TokenTask.AutoEnroll) == TokenTask.AutoEnroll,
                        AutoStart = false
                    });
                }
            }
            catch (Exception ex)
            {
                return View("Index", new MessageViewModel { Theme = "danger", Heading = "Call to UUI failed!", Text = ex.Message });
            }
        }
        
        public async Task<ActionResult> UuiCallback(string access_token, string state, string error)
        {
            if(string.IsNullOrEmpty(state) || string.IsNullOrEmpty(access_token))
            {
                return View("Index", new MessageViewModel { Theme = "danger", Heading = "Invalid UUI callback!", Text = "The data passed to the UUI callback method is not valid!" });
            }
            if (!string.IsNullOrWhiteSpace(error))
            {
                if (error == "user_abort")
                {
                    return View("Index", new MessageViewModel { Theme = "danger", Heading = "User abort", Text = "The biometric operation has been aborted." });
                }
            }

            try
            {
                 // decode the token:
                string claimstring = Encoding.UTF8.GetString(WebEncoders.Base64UrlDecode(access_token.Split('.')[1]));
                var claims = JObject.Parse(claimstring);
                var taskFlags = claims["task"].Value<int>();

                Uri host = new Uri((string)claims["aud"]);

                // ask the token service for the result
                JObject json = null;
                using (var httpClient = new HttpClient())
                {
                    string credentials = Convert.ToBase64String(Encoding.GetEncoding("iso-8859-1").GetBytes($"{_appID}:{_appSecret}"));
                    httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Basic", credentials);
                    UriBuilder uri = new UriBuilder("https", host.Host, 443, "extension/result", $"?access_token={access_token}");
                    var response = await httpClient.GetAsync(uri.Uri);
                    if (!response.IsSuccessStatusCode)
                    {
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Result API failure", Text = $"Cannot fetch result for the biometric operation ({response.StatusCode})." });
                    }
                    if (response.StatusCode == System.Net.HttpStatusCode.NoContent)
                    {
                        if (!string.IsNullOrWhiteSpace(error))
                        {
                            return View("Index", new MessageViewModel { Theme = "danger", Heading = "UUI Error", Text = $"The UUI reports the error: '{error}'" });
                        }
                        return View("Index");
                    }
                    string content = await response.Content.ReadAsStringAsync();
                    json = JObject.Load(new JsonTextReader(new StringReader(content)));
                }
                // check BCID and Action!
                string bcid = (string)json["BCID"];
                string action = (string)json["Action"];
                bool success = (bool)json["Success"];
                string bwsError = (string)json["Error"];
                switch (action)
                {
                    case "enrollment":
                        if (success)
                        {
                            return View("Index", new MessageViewModel { Theme = "success", Heading = "Congratulations!", Text = "You have been enrolled successfully." });
                        }
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Enrollment failed!", Text = $"The BWS result API reports the error-code: '{bwsError}'" });
                    case "verification":
                        if (success)
                        {
                            return View("Index", new MessageViewModel { Theme = "success", Heading = "Congratulations!", HtmlText = "You have been <strong>verified</strong> successfully." });
                        }
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Verification failed!", Text = string.IsNullOrEmpty(bwsError) ? "You have not been recognized." : $"The BWS result API reports the error-code: '{bwsError}'" });
                    case "identification":
                        if (success)
                        {
                            var matches = json["Matches"];
                            return View("Index", new MessageViewModel
                            {
                                Theme = "success",
                                Heading = "Identification result",
                                HtmlText = $"<div><code>{matches}</code></div><hr /><div>Note: your BCID is <code>{bcid}</code>, which should be shown on top of the list, if it is you that performed the identification.</div>"
                            }
                            );
                        }
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Identification failed!", Text = $"The BWS result API reports the error-code: '{bwsError}'" });
                    case "livenessdetection":
                        if (success)
                        {
                            return View("Index", new MessageViewModel { Theme = "success", Heading = "Liveness Detection succeeded!", HtmlText = "It is very likely that we received images recorded from a live person." });
                        }
                        return View("Index", new MessageViewModel { Theme = "danger", Heading = "Liveness Detection failed!", Text = string.IsNullOrEmpty(bwsError) ? "The received images did not prove that they have been recorded from a live person." : $"The BWS result API reports the error-code: '{bwsError}'" });
                }

                return View("Index");
            }
            catch (Exception ex)
            {
                return View("Index", new MessageViewModel { Theme = "danger", Heading = "Ups, internal server error.!", Text = ex.Message });
            }
        }

        public class UuiViewModel
        {
            public string Task { get; set; }
            public string ApiUrl { get; set; }
            public string Token { get; set; }
            public string ReturnUrl { get; set; }
            public string State { get; set; }
            public string Trait { get; set; }
            public int Recordings { get; set; }
            public int MaxTries { get; set; }
            public int MotionThreshold { get; set; }
            public bool ChallengeResponse { get; set; }
            public string ChallengesJson { get; set; }
            public bool AutoEnroll { get; set; }
            public bool AutoStart { get; set; }
        }

        // Flags as used in the BWS token to identify the tasks that need to be performed with this token.
        [Flags]
        public enum TokenTask
        {
            Verify = 0,
            Identify = 0x10,
            Enroll = 0x20,
            LiveOnly = 0x80,
            MaxTriesMask = 0x0F,
            LiveDetection = 0x100,
            ChallengeResponse = 0x200,
            AutoEnroll = 0x1000
        }

        public IActionResult Error()
        {
            return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
        }
    }
}

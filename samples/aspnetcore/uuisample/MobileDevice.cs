using Microsoft.AspNetCore.Http;
using Microsoft.Net.Http.Headers;

namespace uuisample
{
    public static class MobileDevice
    {
        public static bool IsMobileDevice(HttpRequest request)
        {
            try
            {
                string userAgent = request.Headers[HeaderNames.UserAgent].ToString();
                userAgent = userAgent.ToLower();

                // Based on the information from mozilla.org
                // "In summary, we recommend looking for the string 'Mobi' anywhere in the User Agent to detect a mobile device."
                // see https://developer.mozilla.org/en-US/docs/Web/HTTP/Browser_detection_using_the_user_agent
                if (userAgent.Contains("mobi"))
                {
                    return true;
                }
            }
            catch
            {
                return false;
            }
            return false;
        }
    }
}
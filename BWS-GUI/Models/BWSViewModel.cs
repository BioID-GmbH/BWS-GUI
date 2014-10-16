using System.ComponentModel.DataAnnotations;

namespace BWS_GUI.Models
{
    public class BWSViewModel
    {
        public string Token { get; set; }
        public string Host { get; set; }
        public string ReturnUrl { get; set; }
        public string State { get; set; }
        public bool AutoEnroll { get; set; }
        public bool ChallengeResponse { get; set; }
    }

    public class TestViewModel
    {
        public TestViewModel()
        {
            Host = "bws.bioid.com";
        }
        [Required]
        [DataType(DataType.Url)]
        public string Host { get; set; }
        [Required]
        public string AppID { get; set; }
        [DataType(DataType.Password)]
        public string AppSecret { get; set; }
        [Required]
        public string BCID { get; set; }
        [Required]
        public bool ChallengeResponse { get; set; }
        [Required]
        public bool AutoEnroll { get; set; }
    }
    
    public class CallbackViewModel
    {
        public bool Success { get; set; }
        public string Action { get; set; }
        public string BCID { get; set; }
        public string Error { get; set; }
        public string GuiError { get; set; }
    }
}
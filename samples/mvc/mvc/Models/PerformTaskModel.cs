using System.ComponentModel.DataAnnotations;

namespace mvc.Models
{
    public class PerformTaskModel
    {
        [Required]
        [DataType(DataType.Url)]
        [Display(Name = "Web API URL")]
        public string ApiUrl { get; set; } = "https://bws.bioid.com/extension/";
        [Required]
        [Display(Name = "Biometric Class ID")]
        public string BCID { get; set; }
        [Required]
        public bool ChallengeResponse { get; set; } = true;
        [Required]
        public bool AutoEnroll { get; set; }
        public bool MotionBar { get; set; } = true;
        public bool ShowHead { get; set; } = true;
        public string Result { get; set; }
        public string Error { get; set; }
    }
}